const http = require('http');
const fs = require('fs');
const path = require('path');
const url = require('url');

const DEFAULT_PORT = 3000;
const ROOT_DIR = __dirname;

// Безопасная загрузка переменных окружения из .env
try {
  const envPath = path.join(ROOT_DIR, '.env');
  if (fs.existsSync(envPath)) {
    const envContent = fs.readFileSync(envPath, 'utf-8');
    envContent.split('\n').forEach(line => {
      line = line.trim();
      if (!line || line.startsWith('#')) return;
      const idx = line.indexOf('=');
      if (idx !== -1) {
        const k = line.slice(0, idx).trim();
        let v = line.slice(idx + 1).trim();
        if ((v.startsWith('"') && v.endsWith('"')) || (v.startsWith("'") && v.endsWith("'"))) {
          v = v.slice(1, -1);
        }
        if (!process.env[k]) process.env[k] = v;
      }
    });
  }
} catch (e) {}

const MIME_TYPES = {
  '.html': 'text/html; charset=utf-8',
  '.js': 'application/javascript; charset=utf-8',
  '.mjs': 'application/javascript; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.webp': 'image/webp',
  '.avif': 'image/avif',
  '.svg': 'image/svg+xml',
  '.ico': 'image/x-icon',
  '.woff2': 'font/woff2',
  '.woff': 'font/woff',
  '.ttf': 'font/ttf',
  '.mp4': 'video/mp4'
};

const server = http.createServer((req, res) => {
  const origin = req.headers['origin'];
  const allowedOrigins = [
    'https://belka-cafe.ru',
    'http://belka-cafe.ru',
    'https://www.belka-cafe.ru',
    'http://www.belka-cafe.ru',
    'http://localhost:3000',
    'http://127.0.0.1:3000'
  ];
  if (origin && allowedOrigins.includes(origin)) {
    res.setHeader('Access-Control-Allow-Origin', origin);
    res.setHeader('Access-Control-Allow-Credentials', 'true');
  } else if (!origin) {
    // Same-origin browser request
  }
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Range, Authorization');
  res.setHeader('Cache-Control', 'no-store, no-cache, must-revalidate');

  if (req.method === 'OPTIONS') {
    res.writeHead(200);
    res.end();
    return;
  }

  const parsedUrl = url.parse(req.url, true);
  let pathname = decodeURIComponent(parsedUrl.pathname);

  // Rewrite /admin/ subpath asset requests to root assets/
  if (pathname.startsWith('/admin/assets/')) {
    pathname = pathname.replace('/admin/assets/', '/assets/');
  } else if (pathname.startsWith('/admin/images/')) {
    pathname = pathname.replace('/admin/images/', '/images/');
  } else if (pathname.startsWith('/admin/uploads/')) {
    pathname = pathname.replace('/admin/uploads/', '/uploads/');
  }

  // Route /api/* requests to dev API handler
  if (pathname.startsWith('/api/')) {
    try {
      const { handleApiRequest } = require('./api_dev_server.js');
      handleApiRequest(req, res, pathname, parsedUrl.query);
      return;
    } catch (apiErr) {
      console.error('API Error:', apiErr);
      res.writeHead(500, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({ error: apiErr.message }));
      return;
    }
  }

  // Handle save_webp for quick WebP conversions from Puppeteer
  if (req.method === 'POST' && pathname === '/save_webp') {
    let body = '';
    const filename = req.headers['x-filename'] || 'temp.webp';
    req.on('data', chunk => { body += chunk; });
    req.on('end', () => {
      const buf = Buffer.from(body, 'base64');
      fs.writeFileSync(path.join(ROOT_DIR, 'assets/webp/' + filename), buf);
      fs.writeFileSync(path.join(ROOT_DIR, 'dist_production/assets/webp/' + filename), buf);
      console.log(`Saved webp ${filename}! Size: ${buf.length}`);
      res.writeHead(200);
      res.end('OK');
    });
    return;
  }

  // Handle send.php with real VK message delivery
  if (req.method === 'POST' && pathname === '/send.php') {
    let body = '';
    req.on('data', chunk => { body += chunk; });
    req.on('end', () => {
      try {
        const input = JSON.parse(body || '{}');
        const vkToken = process.env.VK_ACCESS_TOKEN || '';
        const vkUserId = process.env.VK_USER_ID || '710846762';

        const name = input.name || '';
        const phone = input.phone || input.contact || '';
        const date = input.date || 'Не указана';
        const time = input.time || 'Не указано';
        const guests = String(input.guests || 'Не указано');
        const comment = input.comment || input.text || '—';
        const type = input.type || 'booking';

        let message = '';
        if (type === 'complaint' || input.isComplaint || (comment && comment.includes('[ЖАЛОБА]'))) {
          let cleanComment = comment.replace(/^\[ЖАЛОБА\]\s*(Оставить жалобу)?\s*(Ваше имя:[^\n]+\n)?\s*(Телефон или e-mail для связи:[^\n]+\n)?\s*(Суть жалобы или замечания:)?/u, '').trim();
          if (!cleanComment && input.text) cleanComment = input.text.trim();
          message = `⚠️ [ЖАЛОБА] Оставить жалобу\n\n👤 Ваше имя: ${name}\n📞 Телефон или e-mail: ${phone}\n💬 Суть жалобы: ${cleanComment || comment}`;
        } else if (type === 'review' || input.isReview || (comment && comment.includes('[ОТЗЫВ]'))) {
          const rating = input.rating || '5';
          const stars = '⭐'.repeat(Math.max(1, Math.min(5, parseInt(rating, 10))));
          let cleanComment = comment.replace(/^\[ОТЗЫВ\]\s*(Оценка:[^\n]+\n)?/u, '').trim();
          message = `⭐ [ОТЗЫВ] Новый отзыв от гостя!\n\n👤 Имя: ${name}\n📞 Контакт: ${phone}\n✨ Оценка: ${rating} из 5 ${stars}\n💬 Отзыв: ${cleanComment || comment}`;
        } else {
          message = `🍽 [БРОНИРОВАНИЕ] Новая заявка на бронь!\n\n👤 Имя: ${name}\n📞 Телефон: ${phone}\n📅 Дата: ${date}\n⏰ Время: ${time}\n👥 Гостей: ${guests}\n💬 Комментарий: ${comment}`;
        }

        // Also save to local database for the admin panel live monitor!
        try {
          const dbPath = path.join(ROOT_DIR, 'scratch/db_local.json');
          if (fs.existsSync(dbPath)) {
            const db = JSON.parse(fs.readFileSync(dbPath, 'utf-8'));
            if (!db.bookings) db.bookings = [];
            const newId = db.bookings.reduce((max, b) => Math.max(max, Number(b.id) || 0), 0) + 1;
            const newBooking = {
              id: newId,
              type: (type === 'complaint' || input.isComplaint) ? 'complaint' : ((type === 'review' || input.isReview) ? 'review' : 'table'),
              name,
              phone,
              booking_date: date !== 'Не указана' ? date : '',
              booking_time: time !== 'Не указано' ? time : '',
              guests: guests !== 'Не указано' ? guests : '',
              hall: input.hall || 'Основной зал',
              comment,
              rating: input.rating ? Number(input.rating) : null,
              status: 'new',
              source: 'website',
              created_at: new Date().toISOString().replace('T', ' ').slice(0, 19)
            };
            db.bookings.unshift(newBooking);
            fs.writeFileSync(dbPath, JSON.stringify(db, null, 2), 'utf-8');
            console.log(`[send.php] Saved booking #${newId} to local DB for admin live monitor!`);
          }
        } catch (dbErr) {
          console.error('[send.php] Local DB save error:', dbErr);
        }

        console.log('[send.php] Sending VK notification:\n', message);

        const https = require('https');
        const qs = require('querystring');
        const params = {
          access_token: vkToken,
          user_id: vkUserId,
          message: message,
          random_id: Math.floor(Math.random() * 10000000),
          v: '5.131'
        };

        const vkReq = https.get('https://api.vk.com/method/messages.send?' + qs.stringify(params), vkRes => {
          let vkData = '';
          vkRes.on('data', c => vkData += c);
          vkRes.on('end', () => {
            console.log('[send.php] VK API response:', vkData);
            res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
            res.end(JSON.stringify({ success: true }));
          });
        });
        vkReq.on('error', err => {
          console.error('[send.php] VK API error:', err);
          res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
          res.end(JSON.stringify({ success: true }));
        });
      } catch (e) {
        console.error('[send.php] Error:', e);
        res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
        res.end(JSON.stringify({ success: true }));
      }
    });
    return;
  }

  // File resolution
  if (pathname === '/') {
    pathname = '/index.html';
  }

  let filePath = path.join(ROOT_DIR, pathname);

  // Security check
  if (!filePath.startsWith(ROOT_DIR)) {
    res.writeHead(403);
    res.end('Forbidden');
    return;
  }

  fs.stat(filePath, (err, stats) => {
    if (!err && stats.isDirectory()) {
      const dirIndex = path.join(filePath, 'index.html');
      if (fs.existsSync(dirIndex)) {
        serveFile(req, res, dirIndex);
        return;
      }
    }

    if (err || !stats.isFile()) {
      // SPA Fallback for client-side routing
      if (!path.extname(pathname)) {
        filePath = path.join(ROOT_DIR, 'index.html');
        serveFile(req, res, filePath);
        return;
      }
      res.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' });
      res.end('404 Not Found');
      return;
    }

    serveFile(req, res, filePath, stats);
  });
});

function serveFile(req, res, filePath, stats) {
  if (!stats) {
    try {
      stats = fs.statSync(filePath);
    } catch {
      res.writeHead(404);
      res.end('404 Not Found');
      return;
    }
  }

  const ext = path.extname(filePath).toLowerCase();
  const contentType = MIME_TYPES[ext] || 'application/octet-stream';
  const fileSize = stats.size;
  const range = req.headers.range;

  if (range && (ext === '.mp4' || ext === '.webm')) {
    const parts = range.replace(/bytes=/, "").split("-");
    const start = parseInt(parts[0], 10);
    const end = parts[1] ? parseInt(parts[1], 10) : fileSize - 1;

    if (start >= fileSize || end >= fileSize) {
      res.writeHead(416, {
        'Content-Range': `bytes */${fileSize}`
      });
      res.end();
      return;
    }

    const chunksize = (end - start) + 1;
    const file = fs.createReadStream(filePath, { start, end });

    res.writeHead(206, {
      'Content-Range': `bytes ${start}-${end}/${fileSize}`,
      'Accept-Ranges': 'bytes',
      'Content-Length': chunksize,
      'Content-Type': contentType
    });

    file.pipe(res);
  } else {
    res.writeHead(200, {
      'Content-Length': fileSize,
      'Content-Type': contentType,
      'Accept-Ranges': 'bytes'
    });

    fs.createReadStream(filePath).pipe(res);
  }
}

function start(port) {
  server.listen(port, '0.0.0.0', () => {
    console.log(`SERVER_READY:http://localhost:${port}`);
  }).on('error', (err) => {
    if (err.code === 'EADDRINUSE') {
      console.log(`Port ${port} in use, trying ${port + 1}...`);
      start(port + 1);
    } else {
      console.error('Server error:', err);
    }
  });
}

start(DEFAULT_PORT);
