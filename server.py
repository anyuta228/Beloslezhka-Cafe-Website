import http.server
from http.server import ThreadingHTTPServer
import socketserver
import os
import json
import urllib.request
import urllib.parse
import mimetypes
import random
import re
import sys

PORT = 3000
DIRECTORY = os.path.dirname(os.path.abspath(__file__))

mimetypes.init()
mimetypes.add_type('text/javascript', '.js')
mimetypes.add_type('text/javascript', '.mjs')
mimetypes.add_type('text/css', '.css')
mimetypes.add_type('image/svg+xml', '.svg')
mimetypes.add_type('image/webp', '.webp')
mimetypes.add_type('image/avif', '.avif')
mimetypes.add_type('font/woff2', '.woff2')
mimetypes.add_type('font/woff', '.woff')
mimetypes.add_type('application/json', '.json')
mimetypes.add_type('video/mp4', '.mp4')

VK_TOKEN = 'vk1.a.QHdnDIOLf_OdAuHKoji22YKwZNiky-y0tQgicbBbyfcDBj8--xfCBlFHyc4voxSIOG1JWEQRhfgy2-Pqoi-l-B4GIDU7lyOJi51ZSgoQiKEDjQca9bFGk38BXdvLnWHt0YwANAbdefGjiHtBDdpxvZTmo19F0QYiIBwZY5_Izhr6Ntvk59abN-rGvlSe2isUxDD9nQ2JXPWavZFs96jK9g'
VK_USER_ID = '710846762'

class ThreadedBelkaHandler(http.server.SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=DIRECTORY, **kwargs)

    def guess_type(self, path):
        base, ext = os.path.splitext(path)
        ext = ext.lower()
        if ext in ['.js', '.mjs']:
            return 'text/javascript'
        if ext == '.css':
            return 'text/css'
        if ext == '.woff2':
            return 'font/woff2'
        if ext == '.webp':
            return 'image/webp'
        if ext == '.avif':
            return 'image/avif'
        if ext == '.mp4':
            return 'video/mp4'
        return super().guess_type(path)

    def end_headers(self):
        self.send_header('Cache-Control', 'no-store, no-cache, must-revalidate')
        self.send_header('Access-Control-Allow-Origin', '*')
        super().end_headers()

    def do_OPTIONS(self):
        self.send_response(200)
        self.send_header("Access-Control-Allow-Origin", "*")
        self.send_header("Access-Control-Allow-Headers", "Content-Type, Range")
        self.send_header("Access-Control-Allow-Methods", "POST, GET, OPTIONS")
        self.end_headers()

    def do_POST(self):
        parsed_path = urllib.parse.urlparse(self.path).path
        if parsed_path.rstrip('/') == '/send.php':
            content_length = int(self.headers.get('Content-Length', 0))
            post_data = self.rfile.read(content_length)
            
            try:
                data = json.loads(post_data.decode('utf-8'))
            except Exception:
                data = {}

            name = data.get('name', '')
            phone = data.get('phone', data.get('contact', ''))
            date_val = data.get('date', 'Не указана')
            time_val = data.get('time', 'Не указано')
            guests = str(data.get('guests', 'Не указано'))
            comment = data.get('comment', data.get('text', '—'))
            req_type = data.get('type', 'booking')

            if not name or not phone:
                self.send_response(400)
                self.send_header("Content-Type", "application/json; charset=UTF-8")
                self.end_headers()
                self.wfile.write(json.dumps({'error': 'Заполните имя и контактные данные'}).encode('utf-8'))
                return

            self.send_response(200)
            self.send_header("Content-Type", "application/json; charset=UTF-8")
            self.end_headers()
            self.wfile.write(json.dumps({'success': True}).encode('utf-8'))
            return

        self.send_response(404)
        self.end_headers()

    def do_GET(self):
        clean_path = urllib.parse.urlparse(self.path).path
        local_file = os.path.join(DIRECTORY, clean_path.lstrip('/'))

        if not os.path.exists(local_file) and '.' not in os.path.basename(clean_path):
            self.path = '/index.html'
            local_file = os.path.join(DIRECTORY, 'index.html')

        range_header = self.headers.get('Range')
        if range_header and os.path.isfile(local_file):
            try:
                file_size = os.path.getsize(local_file)
                range_match = re.match(r'bytes=(\d+)-(\d*)', range_header)
                if range_match:
                    start = int(range_match.group(1))
                    end = int(range_match.group(2)) if range_match.group(2) else file_size - 1
                    end = min(end, file_size - 1)
                    length = end - start + 1

                    self.send_response(206)
                    self.send_header('Content-Type', self.guess_type(local_file))
                    self.send_header('Content-Range', f'bytes {start}-{end}/{file_size}')
                    self.send_header('Content-Length', str(length))
                    self.send_header('Accept-Ranges', 'bytes')
                    self.end_headers()

                    with open(local_file, 'rb') as f:
                        f.seek(start)
                        chunk_size = 64 * 1024
                        bytes_to_send = length
                        while bytes_to_send > 0:
                            read_bytes = min(chunk_size, bytes_to_send)
                            chunk = f.read(read_bytes)
                            if not chunk:
                                break
                            self.wfile.write(chunk)
                            bytes_to_send -= len(chunk)
                    return
            except (ConnectionResetError, BrokenPipeError):
                return
            except Exception:
                pass

        super().do_GET()

def run():
    ThreadingHTTPServer.allow_reuse_address = True
    server = ThreadingHTTPServer(("0.0.0.0", PORT), ThreadedBelkaHandler)
    server.daemon_threads = True
    print(f"SERVER_READY:http://localhost:{PORT}")
    sys.stdout.flush()
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        pass
    finally:
        server.server_close()

if __name__ == '__main__':
    run()
