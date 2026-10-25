const fs = require('fs');
const path = require('path');
const url = require('url');

const DB_FILE = path.join(__dirname, 'scratch/db_local.json');

// Initialize local DB if not exists
function getLocalDB() {
  if (fs.existsSync(DB_FILE)) {
    try {
      return JSON.parse(fs.readFileSync(DB_FILE, 'utf-8'));
    } catch (e) {}
  }

  // Load from extracted data
  let categories = [];
  let dishes = [];
  try {
    const ai = JSON.parse(fs.readFileSync(path.join(__dirname, 'scratch/categories_ai.json'), 'utf-8'));
    categories = ai.map((c, idx) => ({
      id: idx + 1,
      category_id: c.id,
      label: c.label,
      badge: c.badge || null,
      hero_img: c.heroImg || null,
      tagline: c.tagline || '',
      sort_order: (idx + 1) * 10,
      is_active: 1
    }));
  } catch (e) {}

  try {
    const oi = JSON.parse(fs.readFileSync(path.join(__dirname, 'scratch/menu_oi.json'), 'utf-8'));
    let dishId = 1;
    oi.forEach(tab => {
      (tab.sections || []).forEach((sec, secIdx) => {
        (sec.items || []).forEach((item, itemIdx) => {
          const numMatch = String(item.price || '').replace(/\s+/g, '').match(/\d+/);
          const priceNum = numMatch ? parseInt(numMatch[0], 10) : 0;
          dishes.push({
            id: dishId++,
            category_ref: tab.id,
            section_id: secIdx + 1,
            section_title: sec.title || '',
            name: item.name || '',
            weight: item.weight || '',
            price: priceNum,
            price_text: item.price || (priceNum + ' ₽'),
            note: item.note || '',
            description: item.description || '',
            tag: item.tag || '',
            img: item.img || '',
            is_available: 1,
            sort_order: (itemIdx + 1) * 10
          });
        });
      });
    });
  } catch (e) {}

  const defaultDb = {
    admins: [
      { id: 1, username: 'admin', password: 'belka2026', name: 'Управляющий Белоснежка', role: 'admin' }
    ],
    categories,
    dishes,
    promotions: [
      {
        id: 1,
        slug: 'birthday',
        category: 'birthday',
        tag: 'Именинникам',
        badge: '-5% наличными',
        title: 'Скидка на День Рождения',
        subtitle: 'Отпразднуйте с выгодой и размахом',
        description: 'Дарим скидку 5% на всё меню кухни в ваш день рождения, а также 3 дня до и 3 дня после праздника. Для активации акции потребуется предъявить паспорт в кафе при расчёте. Живая музыка и поздравление от заведения.',
        bonus_label: 'Условие акции:',
        bonus: 'Скидка 5% действует при предъявлении паспорта и оплате наличными',
        action_label: 'Забронировать',
        action_type: 'table',
        img: null,
        is_active: 1,
        sort_order: 10
      },
      {
        id: 2,
        slug: 'banquet',
        category: 'banquet',
        tag: 'Банкеты',
        badge: 'Свои напитки и фрукты',
        title: 'Заказ банкета «Под ключ»',
        subtitle: 'Юбилеи, праздники и памятные даты',
        description: 'Индивидуальный расчет меню от 2200 ₽/чел. Вместительные залы до 150 и до 25 персон с профессиональным световым и звуковым оборудованием, сценой и танцполом. Разрешены свои напитки и фрукты.',
        bonus_label: 'Бонус:',
        bonus: 'Свои напитки и фрукты без пробкового сбора',
        action_label: 'Забронировать',
        action_type: 'event',
        img: null,
        is_active: 1,
        sort_order: 20
      },
      {
        id: 3,
        slug: 'wedding',
        category: 'wedding',
        tag: 'Свадьба',
        badge: '-5% наличными',
        title: 'Свадебное торжество',
        subtitle: 'Главный день в красивом банкетном зале',
        description: 'Торжественный банкет в просторном зале до 150 посадочных мест со сценой и танцполом. Скидка 5% на свадебное банкетное меню предоставляется только при оплате наличными.',
        bonus_label: 'Условие акции:',
        bonus: 'Скидка 5% только при оплате наличными',
        action_label: 'Забронировать',
        action_type: 'event',
        img: null,
        is_active: 1,
        sort_order: 30
      }
    ],
    halls: [
      {
        id: 1,
        slug: 'main',
        name: 'Большой банкетный зал',
        capacity: 'до 150 персон',
        capacity_badge: 'ДО 150 ЧЕЛОВЕК',
        type_badge: 'ГЛАВНЫЙ ЗАЛ',
        description: 'Величественный зал с высокими сводчатыми потолками, колоннами и торжественным убранством. Оснащен сценой, большим танцполом, профессиональным световым и акустическим оборудованием. Идеально подходит для проведения свадеб, масштабных юбилеев, выпускных и новогодних корпоративов.',
        features: [
          'Вместимость: до 150 посадочных мест',
          'Сцена, танцпол и профессиональный звук',
          'Возможность индивидуальной рассадки гостей',
          'Просторная зона для фотосессии и первого танца'
        ],
        img: '/assets/webp/hall_main_dancefloor-DDEYboG4.webp',
        photos: [
          {
            src: '/assets/webp/hall_main_dancefloor-DDEYboG4.webp',
            title: 'Танцпол и панорама зала',
            desc: 'Просторный танцпол с узорным центром и концертным светом для ярких торжеств',
            alt: 'Большой банкетный зал: просторный танцпол и праздничная иллюминация'
          },
          {
            src: '/assets/webp/hall_main_stage-LHCo79Kn.webp',
            title: 'Сцена и живой звук',
            desc: 'Подиум с подсветкой, акустическое оборудование и барельеф «Белоснежка»',
            alt: 'Большой банкетный зал: профессиональная сцена для живой музыки'
          },
          {
            src: '/assets/webp/hall_main_bar-CgExjKXM.webp',
            title: 'Барная зона «Белоснежка»',
            desc: 'Барная стойка со светящейся вывеской, напитками и подсветкой',
            alt: 'Большой банкетный зал: фирменный бар «Белоснежка»'
          },
          {
            src: '/assets/webp/hall_main_tables-BWH-sCjJ.webp',
            title: 'Банкетная зона и диваны',
            desc: 'Комфортная посадка гостей, мягкие диваны и живописное настенное панно',
            alt: 'Большой банкетный зал: банкетная зона с диванами и художественным панно'
          },
          {
            src: '/assets/webp/hall_main_lounge-BBt0F-Ax.webp',
            title: 'Лаунж с винной витриной',
            desc: 'Уютный мягкий уголок с диванами, телевизором и коллекционной витриной',
            alt: 'Большой банкетный зал: уютная лаунж-зона с винной витриной'
          },
          {
            src: '/assets/webp/hall_main_panorama-DdLej2El.webp',
            title: 'Торжественная рассадка',
            desc: 'Вместимость до 150 гостей, колонны и сияющие звёзды под потолком',
            alt: 'Большой банкетный зал: вид на зал и торжественную рассадку'
          }
        ]
      },
      {
        id: 2,
        slug: 'small',
        name: 'Малый банкетный зал',
        capacity: 'до 25 персон',
        capacity_badge: 'ДО 25 ЧЕЛОВЕК',
        type_badge: 'КАМЕРНЫЙ ЗАЛ',
        description: 'Уютное и уединенное пространство для душевных праздников в кругу самых близких. Прекрасно подходит для небольших дней рождения, семейных юбилеев, камерных корпоративов, деловых ужинов и поминальных трапез в спокойной приватной атмосфере без посторонних.',
        features: [
          'Вместимость: до 25 человек',
          'Приватная и теплая камерная атмосфера',
          'Персональный сервис и внимание к деталям',
          'Индивидуальный расчет банкетного меню'
        ],
        img: '/assets/webp/hall_small_overview-BPheGfPs.webp',
        photos: [
          {
            src: '/assets/webp/hall_small_overview-BPheGfPs.webp',
            title: 'Панорама Малого зала',
            desc: 'Уютный зал с хрустальной люстрой, художественными барельефами и праздничной сервировкой',
            alt: 'Малый банкетный зал: общая панорама, хрустальная люстра и банкетный стол'
          },
          {
            src: '/assets/webp/hall_small_banquet-DnvCC_qU.webp',
            title: 'Банкетная рассадка и декор',
            desc: 'Столы для компании до 25 персон, кружевные портьеры, настенное панно и мягкий свет',
            alt: 'Малый банкетный зал: сервировка банкетного стола и вид на панно'
          },
          {
            src: '/assets/webp/hall_small_mirror-CT1DIpjN.webp',
            title: 'Лаунж с зеркалом и ТВ',
            desc: 'Большое панорамное зеркало с подсветкой, экран для видеопоздравлений и приватная атмосфера',
            alt: 'Малый банкетный зал: банкетный стол, панорамное зеркало с подсветкой и ТВ'
          },
          {
            src: '/assets/webp/hall_small_mural_street-BPH5CGiP.webp',
            title: 'Барельеф «Итальянская улочка»',
            desc: 'Объёмная художественная роспись ручной работы с фактурной каменной кладкой и цветами',
            alt: 'Малый банкетный зал: художественный барельеф итальянской улочки'
          },
          {
            src: '/assets/webp/hall_small_mural_feast-B7viDVYW.webp',
            title: 'Панно «Королевский пир»',
            desc: 'Фактурная картина торжественного застолья и скульптурные элементы в кирпичном обрамлении',
            alt: 'Малый банкетный зал: барельеф торжественного застолья и скульптурный лев'
          }
        ]
      }
    ],
    gallery: [
      { id: 1, title: 'Сцена и свет', description: 'Профессиональная сцена для живой музыки, танцев и шоу-программ', category: 'Большой зал', img: '/assets/webp/hall_main_stage-LHCo79Kn.webp' },
      { id: 2, title: 'Барная зона «Белоснежка»', description: 'Авторская подсветка, коктейльная карта и напитки на любой вкус', category: 'Бар', img: '/assets/webp/hall_main_bar-CgExjKXM.webp' },
      { id: 3, title: 'Лаунж с зеркалом и ТВ', description: 'Малый зал с зеркальной галереей для уютных и приватных встреч', category: 'Малый зал', img: '/assets/webp/hall_small_mirror-CT1DIpjN.webp' },
      { id: 4, title: 'Банкетный зал', description: 'Торжественная рассадка до 150 персон для свадеб, юбилеев и корпоративов', category: 'Большой зал', img: '/assets/webp/hall_main_tables-BWH-sCjJ.webp' },
      { id: 5, title: 'Лаунж с винной витриной', description: 'Уютный мягкий уголок с диванами, телевизором и винной витриной', category: 'Большой зал', img: '/assets/webp/hall_main_lounge-BBt0F-Ax.webp' },
      { id: 6, title: 'Танцпол и панорама', description: 'Просторный танцпол и световая иллюминация для вечерних праздников', category: 'Большой зал', img: '/assets/webp/hall_main_dancefloor-DDEYboG4.webp' },
      { id: 7, title: 'Панорама Малого зала', description: 'Уютный камерный зал для теплых семейных праздников и юбилеев', category: 'Малый зал', img: '/assets/webp/hall_small_overview-BPheGfPs.webp' },
      { id: 8, title: 'Барельеф «Белоснежка»', description: 'Фирменное резное панно с волшебным фонтаном, голубями и гномами', category: 'Интерьер', img: '/assets/jpg/hall_bas_relief-BuX69IWm.jpg' },
      { id: 9, title: 'Барная стойка с подсветкой', description: 'Стильная вечерняя подсветка барной стойки и высокие стулья', category: 'Бар', img: '/assets/jpg/hall_neon_bar-DPUHqe-q.jpg' },
      { id: 10, title: 'Бокалы над баром', description: 'Стеклянные бокалы над барной стойкой в мягком свете подвесной подсветки', category: 'Бар', img: '/assets/jpg/hall_hanging_glasses-BtudWEH3.jpg' },
      { id: 11, title: 'Сказочный декор', description: 'Светящиеся ростовые цветы и декоративная сказочная роспись стен', category: 'Интерьер', img: '/assets/jpg/hall_flowers_decor-i_3QX-6o.jpg' },
      { id: 12, title: 'Барельеф «Итальянская улочка»', description: 'Объёмная художественная роспись ручной работы с фактурной каменной кладкой', category: 'Малый зал', img: '/assets/webp/hall_small_mural_street-BPH5CGiP.webp' }
    ],
    bookings: [
      {
        id: 1,
        type: 'booking',
        name: 'Александр',
        phone: '+7 (917) 123-45-67',
        booking_date: '15.10.2026',
        booking_time: '19:00',
        guests: '4',
        comment: 'Столик у окна пожалуйста',
        status: 'new',
        created_at: new Date().toISOString()
      }
    ],
    settings: {
      site_name: 'Кафе «Белоснежка»',
      site_phone: '+7 (937) 355-50-50',
      site_phone_secondary: '+7 (34767) 5-25-25',
      site_address: 'Республика Башкортостан, г. Октябрьский, ул. Северная, 23/1',
      work_hours_weekdays: 'Вс – Чт: 11:00 – 00:00',
      work_hours_weekend: 'Пт – Сб: 11:00 – 02:00'
    }
  };

  saveLocalDB(defaultDb);
  return defaultDb;
}

function saveLocalDB(data) {
  try {
    fs.writeFileSync(DB_FILE, JSON.stringify(data, null, 2), 'utf-8');
  } catch (e) {
    console.error('Error saving local db:', e);
  }
}

function sendJson(res, data, code = 200) {
  res.setHeader('Content-Type', 'application/json; charset=utf-8');
  res.setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, proxy-revalidate');
  res.setHeader('Pragma', 'no-cache');
  res.setHeader('Expires', '0');
  res.writeHead(code);
  res.end(JSON.stringify(data, null, 2));
}

// Read body helper
function readBody(req) {
  return new Promise((resolve) => {
    let body = '';
    req.on('data', chunk => { body += chunk; });
    req.on('end', () => {
      try {
        resolve(JSON.parse(body || '{}'));
      } catch (e) {
        resolve({});
      }
    });
  });
}

/**
 * Handle incoming API requests
 */
async function handleApiRequest(req, res, pathname, query) {
  const db = getLocalDB();
  const method = req.method;
  // 0. Site Data API (Bootstrap all dynamic data for public site)
  if (pathname.includes('/site_data.php')) {
    return sendJson(res, {
      success: true,
      settings: db.settings || {},
      categories: db.categories || [],
      sections: db.sections || [],
      dishes: db.dishes || [],
      promotions: (db.promotions || []).filter(p => p.is_active !== 0),
      halls: db.halls || [],
      gallery: (db.gallery || []).filter(g => g.is_active !== 0)
    });
  }

  // 1. Auth API
  if (pathname.includes('/auth.php')) {
    const action = query.action || 'login';
    if (method === 'POST' && action === 'login') {
      const input = await readBody(req);
      const user = (db.admins || []).find(a => a.username === input.username && a.password === input.password);
      if (user) {
        return sendJson(res, {
          success: true,
          token: 'dev_token_' + Date.now(),
          user: { id: user.id, username: user.username, name: user.name, role: user.role }
        });
      }
      return sendJson(res, { success: false, error: 'Неверное имя пользователя или пароль' }, 401);
    }
    if (action === 'me') {
      return sendJson(res, { success: true, user: db.admins[0] });
    }
    if (action === 'logout') {
      return sendJson(res, { success: true, message: 'Вышли' });
    }
    if (action === 'change_password') {
      const input = await readBody(req);
      if (input.old_password === db.admins[0].password) {
        db.admins[0].password = input.new_password;
        saveLocalDB(db);
        return sendJson(res, { success: true, message: 'Пароль изменён' });
      }
      return sendJson(res, { success: false, error: 'Неверный старый пароль' }, 400);
    }
  }

  // 2. Menu API
  if (pathname.includes('/menu.php')) {
    if (method === 'GET') {
      return sendJson(res, {
        success: true,
        categories: db.categories,
        sections: [],
        dishes: db.dishes
      });
    }
    if (method === 'POST') {
      const input = await readBody(req);
      const action = query.action || input.action;

      if (action === 'toggle_available') {
        const dish = db.dishes.find(d => d.id == input.id);
        if (dish) {
          dish.is_available = dish.is_available ? 0 : 1;
          saveLocalDB(db);
          return sendJson(res, { success: true, is_available: dish.is_available });
        }
        return sendJson(res, { success: false, error: 'Not found' }, 404);
      }

      if (action === 'add_dish') {
        let priceNum = 0;
        if (typeof input.price === 'number' && !isNaN(input.price)) {
          priceNum = input.price;
        } else {
          const m = String(input.price || '').replace(/\s+/g, '').match(/\d+/);
          if (m) priceNum = parseInt(m[0], 10);
        }
        let priceText = input.price_text ? String(input.price_text).trim() : '';
        if (!priceText) {
          const raw = String(input.price || '').trim();
          if (/чел/i.test(raw)) {
            priceText = (/от/i.test(raw) ? 'от ' : '') + priceNum + ' ₽/чел.';
          } else if (/от/i.test(raw)) {
            priceText = 'от ' + priceNum + ' ₽';
          } else {
            priceText = priceNum + ' ₽';
          }
        }
        const newDish = {
          id: Date.now(),
          category_ref: input.category_ref,
          section_title: input.section_title || '',
          name: input.name,
          weight: input.weight || '',
          price: priceNum,
          price_text: priceText,
          note: input.note || '',
          description: input.description || '',
          tag: input.tag || '',
          img: input.img || '',
          is_available: input.is_available !== undefined ? Number(input.is_available) : 1,
          sort_order: db.dishes.length * 10
        };
        db.dishes.unshift(newDish);
        saveLocalDB(db);
        return sendJson(res, { success: true, id: newDish.id, message: 'Блюдо добавлено' });
      }

      if (action === 'edit_dish') {
        const idx = db.dishes.findIndex(d => d.id == input.id);
        if (idx !== -1) {
          let priceNum = 0;
          if (typeof input.price === 'number' && !isNaN(input.price)) {
            priceNum = input.price;
          } else {
            const m = String(input.price || '').replace(/\s+/g, '').match(/\d+/);
            if (m) priceNum = parseInt(m[0], 10);
          }
          let priceText = input.price_text ? String(input.price_text).trim() : '';
          if (!priceText) {
            const raw = String(input.price || '').trim();
            if (/чел/i.test(raw)) {
              priceText = (/от/i.test(raw) ? 'от ' : '') + priceNum + ' ₽/чел.';
            } else if (/от/i.test(raw)) {
              priceText = 'от ' + priceNum + ' ₽';
            } else {
              priceText = priceNum + ' ₽';
            }
          }
          db.dishes[idx] = {
            ...db.dishes[idx],
            ...input,
            price: priceNum,
            price_text: priceText
          };
          saveLocalDB(db);
          return sendJson(res, { success: true, message: 'Блюдо обновлено' });
        }
        return sendJson(res, { success: false, error: 'Not found' }, 404);
      }

      if (action === 'delete_dish') {
        db.dishes = db.dishes.filter(d => d.id != input.id);
        saveLocalDB(db);
        return sendJson(res, { success: true, message: 'Блюдо удалено' });
      }
    }
  }

  // 3. Bookings API
  if (pathname.includes('/bookings.php')) {
    if (method === 'GET') {
      let list = db.bookings || [];
      if (query.status && query.status !== 'all') list = list.filter(b => b.status === query.status);
      if (query.type && query.type !== 'all') list = list.filter(b => b.type === query.type);
      const newCount = (db.bookings || []).filter(b => b.status === 'new').length;
      return sendJson(res, { success: true, new_count: newCount, bookings: list });
    }
    if (method === 'POST') {
      const input = await readBody(req);
      const action = query.action || input.action || 'create';

      if (action === 'create') {
        const b = {
          id: Date.now(),
          type: input.type || 'booking',
          name: input.name,
          phone: input.phone || input.contact,
          booking_date: input.date || '',
          booking_time: input.time || '',
          guests: input.guests || '',
          comment: input.comment || input.text || '',
          rating: input.rating,
          status: 'new',
          created_at: new Date().toISOString()
        };
        db.bookings.unshift(b);
        saveLocalDB(db);
        return sendJson(res, { success: true, booking_id: b.id });
      }

      if (action === 'update_status') {
        const b = db.bookings.find(x => x.id == input.id);
        if (b) {
          b.status = input.status;
          saveLocalDB(db);
          return sendJson(res, { success: true });
        }
      }

      if (action === 'delete') {
        db.bookings = db.bookings.filter(x => x.id != input.id);
        saveLocalDB(db);
        return sendJson(res, { success: true });
      }
    }
  }

  // 4. Promotions API
  if (pathname.includes('/promotions.php')) {
    if (method === 'GET') {
      return sendJson(res, { success: true, promotions: db.promotions || [] });
    }
    if (method === 'POST') {
      const input = await readBody(req);
      const action = query.action || input.action;

      if (action === 'add') {
        const p = { id: Date.now(), ...input };
        db.promotions.push(p);
        saveLocalDB(db);
        return sendJson(res, { success: true, id: p.id });
      }
      if (action === 'edit') {
        const idx = db.promotions.findIndex(x => x.id == input.id);
        if (idx !== -1) {
          db.promotions[idx] = { ...db.promotions[idx], ...input };
          saveLocalDB(db);
          return sendJson(res, { success: true });
        }
      }
      if (action === 'delete') {
        db.promotions = db.promotions.filter(x => x.id != input.id);
        saveLocalDB(db);
        return sendJson(res, { success: true });
      }
    }
  }

  // 5. Halls API
  if (pathname.includes('/halls.php')) {
    if (method === 'GET') {
      return sendJson(res, { success: true, halls: db.halls || [] });
    }
    if (method === 'POST') {
      const input = await readBody(req);
      const idx = db.halls.findIndex(h => h.id == input.id);
      if (idx !== -1) {
        db.halls[idx] = { ...db.halls[idx], ...input };
        saveLocalDB(db);
        return sendJson(res, { success: true });
      }
    }
  }

  // 6. Gallery API
  if (pathname.includes('/gallery.php')) {
    if (method === 'GET') {
      return sendJson(res, { success: true, gallery: db.gallery || [] });
    }
    if (method === 'POST') {
      const input = await readBody(req);
      const action = query.action || input.action;
      if (action === 'add') {
        const g = { id: Date.now(), ...input };
        db.gallery.push(g);
        saveLocalDB(db);
        return sendJson(res, { success: true, id: g.id });
      }
      if (action === 'edit') {
        const idx = db.gallery.findIndex(g => g.id == input.id);
        if (idx !== -1) {
          db.gallery[idx] = { ...db.gallery[idx], ...input };
          saveLocalDB(db);
          return sendJson(res, { success: true });
        }
      }
      if (action === 'delete') {
        db.gallery = db.gallery.filter(g => g.id != input.id);
        saveLocalDB(db);
        return sendJson(res, { success: true });
      }
    }
  }

  // 7. Settings API
  if (pathname.includes('/settings.php')) {
    if (method === 'GET') {
      return sendJson(res, { success: true, settings: db.settings || {} });
    }
    if (method === 'POST') {
      const input = await readBody(req);
      db.settings = { ...db.settings, ...(input.settings || input) };
      saveLocalDB(db);
      return sendJson(res, { success: true });
    }
  }

  // 8. Upload API
  if (pathname.includes('/upload.php')) {
    if (method !== 'POST') {
      return sendJson(res, { success: false, error: 'Метод не поддерживается' }, 405);
    }

    const contentType = req.headers['content-type'] || '';
    if (!contentType.includes('multipart/form-data')) {
      return sendJson(res, { success: false, error: 'Ожидается multipart/form-data' }, 400);
    }

    const boundaryMatch = contentType.match(/boundary=(?:"([^"]+)"|([^;]+))/i);
    if (!boundaryMatch) {
      return sendJson(res, { success: false, error: 'Разделитель формы не найден' }, 400);
    }
    const boundary = (boundaryMatch[1] || boundaryMatch[2]).trim();

    const chunks = [];
    req.on('data', chunk => chunks.push(chunk));
    req.on('end', () => {
      try {
        const buffer = Buffer.concat(chunks);
        const boundaryBuf = Buffer.from('--' + boundary);

        let startIdx = buffer.indexOf(boundaryBuf);
        if (startIdx === -1) {
          return sendJson(res, { success: false, error: 'Неверные данные формы' }, 400);
        }

        const nextIdx = buffer.indexOf(boundaryBuf, startIdx + boundaryBuf.length);
        if (nextIdx === -1) {
          return sendJson(res, { success: false, error: 'Файл не найден в запросе' }, 400);
        }

        const partBuffer = buffer.slice(startIdx + boundaryBuf.length, nextIdx);
        const headerEnd = partBuffer.indexOf('\r\n\r\n');
        if (headerEnd === -1) {
          return sendJson(res, { success: false, error: 'Заголовки файла повреждены' }, 400);
        }

        const headerText = partBuffer.slice(0, headerEnd).toString('utf-8');
        let fileData = partBuffer.slice(headerEnd + 4);
        if (fileData.length >= 2 && fileData.slice(-2).toString() === '\r\n') {
          fileData = fileData.slice(0, -2);
        }

        const filenameMatch = headerText.match(/filename="([^"]+)"/i);
        let ext = 'jpg';
        if (filenameMatch) {
          const rawName = filenameMatch[1];
          const matchExt = path.extname(rawName).toLowerCase().replace('.', '');
          if (['jpg', 'jpeg', 'png', 'webp', 'avif'].includes(matchExt)) {
            ext = matchExt === 'jpeg' ? 'jpg' : matchExt;
          }
        }

        const newFilename = 'img_' + Date.now() + '_' + Math.random().toString(36).substring(2, 8) + '.' + ext;
        const uploadDir = path.join(__dirname, 'uploads');
        const distUploadDir = path.join(__dirname, 'dist_production/uploads');
        fs.mkdirSync(uploadDir, { recursive: true });
        fs.mkdirSync(distUploadDir, { recursive: true });

        const targetPath = path.join(uploadDir, newFilename);
        const distTargetPath = path.join(distUploadDir, newFilename);

        fs.writeFileSync(targetPath, fileData);
        fs.writeFileSync(distTargetPath, fileData);

        console.log(`[Upload API] Saved file ${newFilename} (${fileData.length} bytes)`);

        return sendJson(res, {
          success: true,
          url: '/uploads/' + newFilename,
          filename: newFilename,
          message: 'Файл успешно загружен'
        });
      } catch (err) {
        console.error('[Upload API] Error saving file:', err);
        return sendJson(res, { success: false, error: 'Ошибка сохранения файла: ' + err.message }, 500);
      }
    });
    return;
  }

  sendJson(res, { error: 'Unknown API route' }, 404);
}

module.exports = { handleApiRequest };
