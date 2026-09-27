const http = require('http');
const fs = require('fs');
const path = require('path');
const url = require('url');

const DEFAULT_PORT = 3000;
const ROOT_DIR = __dirname;

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
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Range');
  res.setHeader('Cache-Control', 'no-store, no-cache, must-revalidate');

  if (req.method === 'OPTIONS') {
    res.writeHead(200);
    res.end();
    return;
  }

  const parsedUrl = url.parse(req.url);
  let pathname = decodeURIComponent(parsedUrl.pathname);

  // Handle send.php with real VK message delivery
  if (req.method === 'POST' && pathname === '/send.php') {
    let body = '';
    req.on('data', chunk => { body += chunk; });
    req.on('end', () => {
      try {
        const input = JSON.parse(body || '{}');
        const vkToken = 'vk1.a.QHdnDIOLf_OdAuHKoji22YKwZNiky-y0tQgicbBbyfcDBj8--xfCBlFHyc4voxSIOG1JWEQRhfgy2-Pqoi-l-B4GIDU7lyOJi51ZSgoQiKEDjQca9bFGk38BXdvLnWHt0YwANAbdefGjiHtBDdpxvZTmo19F0QYiIBwZY5_Izhr6Ntvk59abN-rGvlSe2isUxDD9nQ2JXPWavZFs96jK9g';
        const vkUserId = '710846762';

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
          message = `⚠️ [ЖАЛОБА - ТЕСТ] Оставить жалобу\n\n👤 Ваше имя: ${name}\n📞 Телефон или e-mail: ${phone}\n💬 Суть жалобы: ${cleanComment || comment}`;
        } else if (type === 'review' || input.isReview || (comment && comment.includes('[ОТЗЫВ]'))) {
          const rating = input.rating || '5';
          const stars = '⭐'.repeat(Math.max(1, Math.min(5, parseInt(rating, 10))));
          let cleanComment = comment.replace(/^\[ОТЗЫВ\]\s*(Оценка:[^\n]+\n)?/u, '').trim();
          message = `⭐ [ОТЗЫВ - ТЕСТ] Новый отзыв!\n\n👤 Имя: ${name}\n📞 Контакт: ${phone}\n✨ Оценка: ${rating} из 5 ${stars}\n💬 Отзыв: ${cleanComment || comment}`;
        } else {
          message = `🍽 [БРОНИРОВАНИЕ - ТЕСТ] Новая заявка на бронь!\n\n👤 Имя: ${name}\n📞 Телефон: ${phone}\n📅 Дата: ${date}\n⏰ Время: ${time}\n👥 Гостей: ${guests}\n💬 Комментарий: ${comment}`;
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
