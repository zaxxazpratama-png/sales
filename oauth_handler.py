"""
Server lokal untuk menangkap OAuth callback dari Google,
lalu otomatis paste ke clasp stdin.
"""
import http.server, threading, subprocess, sys, os, time, urllib.parse

AUTH_CODE = None
PROC = None

class OAuthHandler(http.server.BaseHTTPRequestHandler):
    def do_GET(self):
        global AUTH_CODE
        params = urllib.parse.parse_qs(urllib.parse.urlparse(self.path).query)
        if 'code' in params:
            AUTH_CODE = params['code'][0]
            self.send_response(200)
            self.end_headers()
            self.wfile.write(b"<html><body><h1>Authorization successful!</h1><p>Clasp sedang memproses...</p></body></html>")
            print(f"\n[OK] Auth code diterima: {AUTH_CODE[:30]}...")
        else:
            self.send_response(400)
            self.end_headers()
            self.wfile.write(b"No code found")
    def log_message(self, *args):
        pass  # Suppress log

def run_server():
    server = http.server.HTTPServer(('localhost', 8888), OAuthHandler)
    server.timeout = 120
    server.handle_request()  # Handle 1 request saja

# Jalankan server di background thread
t = threading.Thread(target=run_server, daemon=True)
t.start()
print("[INFO] Server OAuth menunggu di localhost:8888...")

# Jalankan clasp login --no-localhost di process terpisah
env = os.environ.copy()
env['FORCE_COLOR'] = '0'

print("[INFO] Memulai clasp login...")
PROC = subprocess.Popen(
    ['clasp', 'login', '--no-localhost'],
    stdin=subprocess.PIPE,
    stdout=subprocess.PIPE,
    stderr=subprocess.STDOUT,
    text=True,
    env=env
)

# Baca URL dari output clasp
import re
oauth_url = None
output = ""
start = time.time()

while time.time() - start < 30:
    line = PROC.stdout.readline()
    if not line:
        time.sleep(0.1)
        continue
    output += line
    print(f"  clasp> {line.rstrip()}")
    match = re.search(r'https://accounts\.google\.com\S+', line)
    if match:
        oauth_url = match.group(0)
        print(f"\n[INFO] URL OAuth ditemukan: {oauth_url[:80]}...")
        # Buka di browser
        import subprocess as sp
        sp.Popen(['cmd', '/c', 'start', '', oauth_url.replace('&', '^&')], shell=True)
        break

if not oauth_url:
    print("[ERROR] URL OAuth tidak ditemukan dalam output clasp")
    sys.exit(1)

# Tunggu callback dari browser
print("\n[INFO] Menunggu callback dari Google OAuth (max 120 detik)...")
timeout = 120
elapsed = 0
while AUTH_CODE is None and elapsed < timeout:
    time.sleep(1)
    elapsed += 1
    if elapsed % 10 == 0:
        print(f"  Menunggu... {elapsed}s")

if AUTH_CODE is None:
    print("[ERROR] Timeout - tidak ada auth code yang diterima")
    PROC.terminate()
    sys.exit(1)

# Kirim URL ke clasp stdin
callback_url = f"http://localhost:8888?code={AUTH_CODE}&state=KGfYXIYIO8NDhKgZYaM71I8x5LQ1O8DmAZiHTrLNpMk"
print(f"\n[INFO] Mengirim callback URL ke clasp: {callback_url[:60]}...")
PROC.stdin.write(callback_url + "\n")
PROC.stdin.flush()

# Tunggu clasp selesai
try:
    out, _ = PROC.communicate(timeout=30)
    print(f"\n[INFO] Clasp output: {out}")
except:
    pass

# Cek apakah .clasprc.json sudah ada
clasprc = os.path.join(os.environ['USERPROFILE'], '.clasprc.json')
if os.path.exists(clasprc):
    print("\n[SUCCESS] Login clasp BERHASIL! .clasprc.json ditemukan!")
else:
    print("\n[ERROR] Login belum berhasil - .clasprc.json tidak ada")
    sys.exit(1)

print("\n[INFO] Sekarang menjalankan clasp push...")
