<?php
/**
 * Кафе «Белоснежка» — Авторизация администратора
 */
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = getJsonInput();
$action = $_GET['action'] ?? $input['action'] ?? 'login';

$db = getDB();

if ($method === 'POST') {
    if ($action === 'login') {
        // Защита от подбора паролей: максимум 5 попыток за 5 минут с одного IP
        checkRateLimit('admin_login', 5, 300);

        $username = trim($input['username'] ?? '');
        $password = trim($input['password'] ?? '');

        if (empty($username) || empty($password)) {
            jsonResponse(['success' => false, 'error' => 'Введите логин и пароль'], 400);
        }

        try {
            $stmt = $db->prepare("SELECT id, username, password_hash, name, role FROM admins WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();
        } catch (PDOException $e) {
            $stmt = $db->prepare("SELECT id, username, password_hash, role FROM admins WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();
            if ($admin) $admin['name'] = 'Администратор';
        }

        if (!$admin) {
            jsonResponse(['success' => false, 'error' => 'Неверное имя пользователя или пароль'], 401);
        }

        $hash = $admin['password_hash'];
        $isValid = false;

        // Строгая криптографическая проверка через BCrypt
        if (password_verify($password, $hash)) {
            $isValid = true;
            // Автоматическое перехэширование если изменились алгоритмические требования
            if (password_needs_rehash($hash, PASSWORD_BCRYPT)) {
                $newHash = password_hash($password, PASSWORD_BCRYPT);
                $db->prepare("UPDATE admins SET password_hash = ? WHERE id = ?")->execute([$newHash, $admin['id']]);
            }
        }

        if (!$isValid) {
            jsonResponse(['success' => false, 'error' => 'Неверное имя пользователя или пароль'], 401);
        }

        // Генерируем токен сессии
        $token = bin2hex(random_bytes(32));
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_user'] = $admin['username'];
        $_SESSION['admin_name'] = $admin['name'];
        $_SESSION['admin_role'] = $admin['role'];
        $_SESSION['auth_token'] = $token;

        // Обновляем время входа
        $db->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);

        jsonResponse([
            'success' => true,
            'token'   => $token,
            'user'    => [
                'id'       => $admin['id'],
                'username' => $admin['username'],
                'name'     => $admin['name'],
                'role'     => $admin['role']
            ]
        ]);
    }

    if ($action === 'logout') {
        $_SESSION = [];
        if (session_id()) {
            session_destroy();
        }
        jsonResponse(['success' => true, 'message' => 'Вы успешно вышли']);
    }

    if ($action === 'change_password') {
        checkAdminAuth();
        $adminId = $_SESSION['admin_id'];
        $oldPass = trim($input['old_password'] ?? '');
        $newPass = trim($input['new_password'] ?? '');

        if (strlen($newPass) < 8) {
            jsonResponse(['success' => false, 'error' => 'Новый пароль должен содержать не менее 8 символов'], 400);
        }

        // Проверка по списку распространенных и скомпрометированных паролей
        $weakList = ['12345678', 'password', 'admin123', 'admin777', 'belka2026', 'qwerty1234', '11111111', 'belosnezhka', 'cafebelka'];
        if (in_array(strtolower($newPass), $weakList, true)) {
            jsonResponse(['success' => false, 'error' => 'Выбранный пароль слишком простой или скомпрометирован. Придумайте более надежный пароль.'], 400);
        }

        $stmt = $db->prepare("SELECT id, password_hash FROM admins WHERE id = ?");
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($oldPass, $admin['password_hash'])) {
            jsonResponse(['success' => false, 'error' => 'Текущий пароль указан неверно'], 400);
        }

        $newHash = password_hash($newPass, PASSWORD_BCRYPT);
        $db->prepare("UPDATE admins SET password_hash = ? WHERE id = ?")->execute([$newHash, $adminId]);

        jsonResponse(['success' => true, 'message' => 'Пароль успешно обновлён']);
    }
}

if ($method === 'GET') {
    if ($action === 'me') {
        if (!empty($_SESSION['admin_logged_in'])) {
            jsonResponse([
                'success' => true,
                'user' => [
                    'id'       => $_SESSION['admin_id'],
                    'username' => $_SESSION['admin_user'],
                    'name'     => $_SESSION['admin_name'],
                    'role'     => $_SESSION['admin_role']
                ]
            ]);
        } else {
            jsonResponse(['success' => false, 'authenticated' => false], 401);
        }
    }
}

jsonResponse(['success' => false, 'error' => 'Действие не поддерживается'], 400);
