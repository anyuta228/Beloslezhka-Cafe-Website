<?php
/**
 * Кафе «Белоснежка» — Конфигурация базы данных и вспомогательные функции API
 * Сервер: RU-CENTER (nic.ru) MySQL bel7916394.mysql
 */

// 0. Загрузка параметров окружения из файла .env (если он существует)
if (!function_exists('loadEnv')) {
    function loadEnv($path = __DIR__ . '/../.env') {
        if (!file_exists($path) || !is_readable($path)) {
            return;
        }
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) return;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (strpos($line, '=') !== false) {
                list($key, $val) = explode('=', $line, 2);
                $key = trim($key);
                $val = trim($val);
                if (strlen($val) >= 2) {
                    $first = $val[0];
                    $last = substr($val, -1);
                    if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                        $val = substr($val, 1, -1);
                    }
                }
                if (getenv($key) === false) {
                    putenv("$key=$val");
                    $_ENV[$key] = $val;
                    $_SERVER[$key] = $val;
                }
            }
        }
    }
}
loadEnv();

// 1. Безопасные заголовки CORS и кодировки (защита от несанкционированного доступа с чужих сайтов)
if (!function_exists('handleCors')) {
    function handleCors() {
        $allowedOrigins = [
            'https://belka-cafe.ru',
            'http://belka-cafe.ru',
            'https://www.belka-cafe.ru',
            'http://www.belka-cafe.ru',
            'http://localhost:3000',
            'http://127.0.0.1:3000',
            'http://localhost:5173',
            'http://127.0.0.1:5173'
        ];
        $envOrigins = getenv('ALLOWED_ORIGINS');
        if ($envOrigins) {
            $extra = array_map('trim', explode(',', $envOrigins));
            $allowedOrigins = array_unique(array_merge($allowedOrigins, $extra));
        }

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if (!empty($origin) && in_array($origin, $allowedOrigins, true)) {
            header("Access-Control-Allow-Origin: " . $origin);
            header("Access-Control-Allow-Credentials: true");
        }

        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Content-Type: application/json; charset=UTF-8");

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
}
handleCors();

// 2. Механизм защиты от флуда и брутфорса (Rate Limiting)
if (!function_exists('checkRateLimit')) {
    function checkRateLimit($action, $maxRequests = 10, $periodSeconds = 60) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] 
            ?? $_SERVER['HTTP_X_FORWARDED_FOR'] 
            ?? $_SERVER['REMOTE_ADDR'] 
            ?? '127.0.0.1';
        
        if (strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }
        
        $ipHash = md5($ip . '_' . $action);
        $tmpDir = sys_get_temp_dir() . '/belka_rate_limits';
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0700, true);
        }
        
        $file = $tmpDir . '/' . $ipHash . '.json';
        $now = time();
        $data = ['times' => []];
        
        if (file_exists($file)) {
            $raw = @file_get_contents($file);
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && isset($decoded['times'])) {
                $data = $decoded;
            }
        }
        
        $filtered = [];
        foreach ($data['times'] as $t) {
            if ($now - $t < $periodSeconds) {
                $filtered[] = $t;
            }
        }
        
        if (count($filtered) >= $maxRequests) {
            http_response_code(429);
            header('Retry-After: ' . $periodSeconds);
            echo json_encode([
                'success' => false,
                'error'   => 'Слишком много запросов. Пожалуйста, подождите перед следующей попыткой.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $filtered[] = $now;
        @file_put_contents($file, json_encode(['times' => $filtered]), LOCK_EX);
    }
}

// 3. Запуск сессии для авторизации админа с безопасными флагами
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

// 4. Параметры подключения к базе данных (читаются из .env с безопасным fallback)
if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: 'bel7916394.mysql');
if (!defined('DB_PORT')) define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'bel7916394_db');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'bel7916394_mysql');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') ?: 'pnj-CM3d');

// 5. Параметры VK API для уведомлений
if (!defined('VK_ACCESS_TOKEN')) {
    define('VK_ACCESS_TOKEN', getenv('VK_ACCESS_TOKEN') ?: 'vk1.a.QHdnDIOLf_OdAuHKoji22YKwZNiky-y0tQgicbBbyfcDBj8--xfCBlFHyc4voxSIOG1JWEQRhfgy2-Pqoi-l-B4GIDU7lyOJi51ZSgoQiKEDjQca9bFGk38BXdvLnWHt0YwANAbdefGjiHtBDdpxvZTmo19F0QYiIBwZY5_Izhr6Ntvk59abN-rGvlSe2isUxDD9nQ2JXPWavZFs96jK9g');
}
if (!defined('VK_USER_ID')) {
    define('VK_USER_ID', getenv('VK_USER_ID') ?: '710846762');
}

/**
 * Получение единственного экземпляра PDO подключения
 */
function getDB() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        syncStandardDishPhotos($pdo);
        return $pdo;
    } catch (PDOException $e) {
        // Fallback: попытка подключения через localhost, если bel7916394.mysql не резолвится локально
        try {
            $dsnLocal = "mysql:host=localhost;port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsnLocal, DB_USER, DB_PASS, $options);
            syncStandardDishPhotos($pdo);
            return $pdo;
        } catch (PDOException $e2) {
            // Безопасное логирование ошибки без утечки стектрейса и имен пользователю
            error_log("Database connection error: " . $e->getMessage() . " / Fallback error: " . $e2->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => 'Ошибка подключения к базе данных. Пожалуйста, повторите попытку позже.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

/**
 * Автоматическая синхронизация фотографий стандартных блюд в базе данных nic.ru
 */
function syncStandardDishPhotos($pdo) {
    static $synced = false;
    if ($synced) return;
    $synced = true;
    try {
        // 1. Нормализация дублирующих названий
        $pdo->exec("UPDATE dishes SET name = 'С хрустящим баклажаном' WHERE name = 'С хрустящими баклажанами'");
        $pdo->exec("UPDATE dishes SET name = 'Овощи запечённые' WHERE name = 'Овощи запеченные'");
        $pdo->exec("UPDATE dishes SET img = '/images/placeholder_dish.svg' WHERE img LIKE '%placeholder.webp%'");

        // 2. Очистка неверного фото шашлыка с жареных пельменей с курицей
        $pdo->exec("UPDATE dishes SET img = '/images/placeholder_dish.svg' WHERE name LIKE '%пельмени с курицей%' AND (img LIKE '%mangal%' OR img LIKE '%shashlik%' OR img LIKE '%chick%')");

        // 3. Список стандартных актуальных фотографий и описаний
        $updates = [
            [
                'name' => 'Пепперони',
                'img' => '/assets/webp/pizza_pepperoni-P8mK2wL5.webp',
                'note' => 'пикантная колбаса пепперони, сыр моцарелла, томатный соус, прованские травы',
                'desc' => 'Фирменная пицца на тонком тесте с пикантной колбасой пепперони, томатным соусом и тягучим сыром моцарелла.',
                'tag' => 'Хит'
            ],
            [
                'name' => 'Гавайская',
                'img' => '/assets/webp/pizza_hawaiian-W4rM8kL2.webp',
                'note' => 'куриное филе, сочные ананасы, соус цезарь, сыр моцарелла',
                'desc' => 'Классическая аппетитная пицца с сочным куриным филе, кусочками сладкого ананаса, нежным соусом цезарь и щедрым слоем тягучей моцареллы.',
                'tag' => null
            ],
            [
                'name' => 'Цезарь с креветками',
                'img' => '/assets/webp/salad_caesar_shrimp-B7wK2mN4.webp',
                'note' => 'креветки, салатные листья, помидоры, сыр голландский, сухари, соус цезарь',
                'desc' => 'Аппетитный салат с обжаренными креветками, свежими листьями салата, спелыми помидорами, голландским сыром и хрустящими сухарями под соусом цезарь.',
                'tag' => 'Хит · Новинка'
            ],
            [
                'name' => 'С хрустящим баклажаном',
                'img' => '/assets/webp/salad_crispy_eggplant-N4mK7vX3.webp',
                'note' => 'хрустящие баклажаны, спелые помидоры, сливочный сыр фета, свежая зелень, кунжут, кисло-сладкий соус',
                'desc' => 'Аппетитный салат с хрустящими обжаренными баклажанами, сочными томатами, нежным сыром фета, свежей зеленью и кунжутом под пикантной кисло-сладкой заправкой.',
                'tag' => null
            ],
            [
                'name' => 'Курица по-французски',
                'img' => '/assets/webp/chicken_french-K7rP2wL9.webp',
                'note' => 'куриное филе, спелые помидоры, сыр, репчатый лук, фирменный соус',
                'desc' => 'Сочное куриное филе, запечённое со спелыми томатами под аппетитной сырной корочкой и пикантным авторским соусом.',
                'tag' => 'Новинка'
            ],
            [
                'name' => 'Овощи запечённые',
                'img' => '/assets/webp/baked_vegetables-H3mK9wB8.webp',
                'note' => 'сезонные овощи, кабачки, болгарский перец, шампиньоны, пряные травы',
                'desc' => 'Аппетитные сезонные овощи, запечённые до сочности и лёгкого румянца: кружочки кабачков, сладкий болгарский перец и шампиньоны с пряными травами.',
                'tag' => null
            ],
            [
                'name' => 'Печень по-татарски',
                'img' => '/assets/webp/liver_tatar-B8mK9wM7.webp',
                'note' => 'говяжья печень, лук репчатый, маринованные огурцы, томатный соус, зелень, тонкий лаваш',
                'desc' => 'Нежная говяжья печень, обжаренная с репчатым луком, маринованными огурчиками и восточными специями в томатном соусе. Подаётся на тонком лаваше со сладким перцем и свежей зеленью.',
                'tag' => null
            ],
            [
                'name' => 'Рулеты из баклажана',
                'img' => '/assets/webp/eggplant_rolls-E9mK2wQ4.webp',
                'note' => 'обжаренные баклажаны, сыр, грецкий орех, чеснок, свежая зелень',
                'desc' => 'Нежные рулетики из обжаренных баклажанов с пикантной начинкой из сыра, чеснока и свежей зелени, посыпанные дроблёным грецким орехом.',
                'tag' => null
            ],
            [
                'name' => 'Овощная тарелка',
                'img' => '/assets/webp/vegetable_plate-C4rK9wM5.webp',
                'note' => 'хрустящие огурцы, спелые томаты, болгарский перец, морковча, свежая зелень',
                'desc' => null,
                'tag' => null
            ],
            [
                'name' => 'Рулетики из ветчины с сыром',
                'img' => '/assets/webp/ham_rolls_cheese-B7kL9wM2.webp',
                'note' => 'отборная ветчина, тёртый сыр, куриное яйцо, чеснок, майонез, перья зелёного лука',
                'desc' => 'Тонкие слайсы нежной ветчины с пикантной начинкой из тёртого сыра с чесноком и яйцом, перевязанные зелёным луком.',
                'tag' => null
            ],
            [
                'name' => 'Свинина в соусе с грибами',
                'img' => '/assets/webp/pork_mushrooms-B5kN9wL2.webp',
                'note' => 'сочная свинина, свежие грибы, сливочный соус, специи',
                'desc' => 'Нежные кусочки сочной свинины, обжаренные со свежими грибами в насыщенном сливочном соусе с ароматными специями.',
                'tag' => null
            ]
        ];

        // 4. Обновление стандартных блюд (если нет ручной загрузки админа в /uploads/)
        $stmt = $pdo->prepare("UPDATE dishes SET img = :img, note = COALESCE(NULLIF(note, ''), :note), description = COALESCE(NULLIF(description, ''), :desc), tag = COALESCE(NULLIF(tag, ''), :tag) WHERE (img IS NULL OR img = '' OR img LIKE '%placeholder%' OR img LIKE '%gavayskaya%' OR img LIKE '%margarita%' OR (img NOT LIKE '/uploads/%' AND img NOT LIKE 'uploads/%' AND img NOT LIKE 'http%')) AND (name = :name OR LOWER(TRIM(name)) = LOWER(:name))");

        foreach ($updates as $u) {
            $stmt->execute([
                ':img'  => $u['img'],
                ':note' => $u['note'],
                ':desc' => $u['desc'],
                ':tag'  => $u['tag'],
                ':name' => $u['name']
            ]);
        }

        // Прямое гарантированное обновление ключевых блюд
        $pdo->exec("UPDATE dishes SET img = '/assets/webp/pizza_hawaiian-W4rM8kL2.webp', note = 'куриное филе, сочные ананасы, соус цезарь, сыр моцарелла', description = 'Классическая аппетитная пицца с сочным куриным филе, кусочками сладкого ананаса, нежным соусом цезарь и щедрым слоем тягучей моцареллы.' WHERE LOWER(TRIM(name)) = 'гавайская' AND (img NOT LIKE '/uploads/%' AND img NOT LIKE 'uploads/%')");
        $pdo->exec("UPDATE dishes SET img = '/assets/webp/pizza_pepperoni-P8mK2wL5.webp', tag = 'Хит', note = 'пикантная колбаса пепперони, сыр моцарелла, томатный соус, прованские травы', description = 'Фирменная пицца на тонком тесте с пикантной колбасой пепперони, томатным соусом и тягучим сыром моцарелла.' WHERE LOWER(TRIM(name)) = 'пепперони' AND (img NOT LIKE '/uploads/%' AND img NOT LIKE 'uploads/%')");
        $pdo->exec("UPDATE dishes SET img = '/assets/webp/salad_caesar_shrimp-B7wK2mN4.webp', tag = 'Хит · Новинка', description = 'Аппетитный салат с обжаренными креветками, свежими листьями салата, спелыми помидорами, голландским сыром и хрустящими сухарями под соусом цезарь.' WHERE LOWER(TRIM(name)) LIKE '%цезарь с креветками%' AND (img NOT LIKE '/uploads/%' AND img NOT LIKE 'uploads/%')");

        // 5. Проверка наличия пиццы Пепперони в БД (если отсутствует — вставляем)
        $chkPep = $pdo->prepare("SELECT id FROM dishes WHERE LOWER(TRIM(name)) = 'пепперони' LIMIT 1");
        $chkPep->execute();
        if (!$chkPep->fetch()) {
            $insPep = $pdo->prepare("INSERT INTO dishes (category_ref, section_title, name, price, price_text, weight, note, description, img, tag, is_available) VALUES ('pizza', 'Пицца', 'Пепперони', 550, '550 ₽', '', 'пикантная колбаса пепперони, сыр моцарелла, томатный соус, прованские травы', 'Фирменная пицца на тонком тесте с пикантной колбасой пепперони, томатным соусом и тягучим сыром моцарелла.', '/assets/webp/pizza_pepperoni-P8mK2wL5.webp', 'Хит', 1)");
            $insPep->execute();
        }

        // 6. Проверка наличия пиццы Гавайская в БД (если отсутствует — вставляем)
        $chkHaw = $pdo->prepare("SELECT id FROM dishes WHERE LOWER(TRIM(name)) = 'гавайская' LIMIT 1");
        $chkHaw->execute();
        if (!$chkHaw->fetch()) {
            $insHaw = $pdo->prepare("INSERT INTO dishes (category_ref, section_title, name, price, price_text, weight, note, description, img, tag, is_available) VALUES ('pizza', 'Пицца', 'Гавайская', 550, '550 ₽', '', 'куриное филе, сочные ананасы, соус цезарь, сыр моцарелла', 'Классическая аппетитная пицца с сочным куриным филе, кусочками сладкого ананаса, нежным соусом цезарь и щедрым слоем тягучей моцареллы.', '/assets/webp/pizza_hawaiian-W4rM8kL2.webp', NULL, 1)");
            $insHaw->execute();
        }

        // 7. Удаление дубликатов 'С хрустящим баклажаном' (оставляем только первое)
        $dupChk = $pdo->query("SELECT id FROM dishes WHERE name = 'С хрустящим баклажаном' ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
        if (count($dupChk) > 1) {
            $deleteIds = array_slice($dupChk, 1);
            $inClause = implode(',', array_map('intval', $deleteIds));
            $pdo->exec("DELETE FROM dishes WHERE id IN ($inClause)");
        }
    } catch (Exception $e) {
        // Безопасный перехват без прерывания запроса
        error_log("syncStandardDishPhotos error: " . $e->getMessage());
    }
}

/**
 * Отправка ответа в формате JSON и завершение
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Чтение входящего JSON тела запроса
 */
function getJsonInput() {
    $raw = file_get_contents('php://input');
    if (empty($raw)) {
        return $_POST;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? array_merge($_POST, $decoded) : $_POST;
}

/**
 * Проверка прав администратора
 */
function checkAdminAuth() {
    // 1. Проверка сессии
    if (!empty($_SESSION['admin_logged_in']) && !empty($_SESSION['admin_user'])) {
        return true;
    }

    // 2. Проверка токена Authorization: Bearer <token>
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
        $token = $matches[1];
        // Проверяем токен из сессии
        if (!empty($_SESSION['auth_token']) && hash_equals($_SESSION['auth_token'], $token)) {
            return true;
        }
    }

    jsonResponse(['success' => false, 'error' => 'Требуется авторизация администратора'], 401);
}
