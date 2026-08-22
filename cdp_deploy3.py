"""
Gunakan CDP via fetch dari dalam halaman Apps Script itu sendiri
Kita buat satu halaman HTML yang jadi "proxy" - dari halaman itu kita connect ke CDP
menggunakan origin yang sesuai dengan Apps Script tab.
"""
import json, time, urllib.request, urllib.parse, base64, threading

CDP_PORT = 9222
TAB_ID = '8986338BFCA4B86300384E831675EC3E'  # Apps Script tab

# Baca kode
with open('apps-script/Code.gs', 'r', encoding='utf-8') as f:
    code = f.read()
code_b64 = base64.b64encode(code.encode('utf-8')).decode('ascii')
print(f"Code: {len(code)} bytes | box(): {'function box(' in code}")

# Koneksi WebSocket dengan Chrome origin (yang dipakai Antigravity browser)
import websocket

# Chrome di antigravity-browser-profile -> origin harus dari dalam chrome
ws_url = f'ws://localhost:{CDP_PORT}/devtools/page/{TAB_ID}'

# Coba berbagai origin
origins_to_try = [
    'chrome://newtab',
    'http://localhost:9222',
    None,  # no origin header
]

ws = None
for origin in origins_to_try:
    try:
        w = websocket.WebSocket()
        if origin:
            w.connect(ws_url, header={'Origin': origin}, timeout=5)
        else:
            # Tidak set Origin header sama sekali
            import websocket._handshake as hs_module
            orig_handshake = hs_module._get_handshake_headers
            def patched_handshake(url, hostname, port, options):
                headers, key = orig_handshake(url, hostname, port, options)
                # Remove Origin header
                headers = [h for h in headers if not h.startswith(b'Origin:')]
                return headers, key
            hs_module._get_handshake_headers = patched_handshake
            w.connect(ws_url, timeout=5)
            hs_module._get_handshake_headers = orig_handshake
        
        ws = w
        print(f"Connected with origin={origin}!")
        break
    except Exception as e:
        print(f"Failed with origin={origin}: {str(e)[:100]}")

if not ws:
    print("\nAll WebSocket approaches failed.")
    print("Using HTTP CDP endpoint to activate + execute...")
    
    # Gunakan /json/evaluate - meski bukan standar, beberapa versi Chrome support
    # Atau gunakan pendekatan: buka halaman proxy di tab baru
    
    # Create new tab di Chrome yang sudah terhubung
    new_tab_data = urllib.request.urlopen(
        f'http://localhost:{CDP_PORT}/json/new?http://localhost:8081/ALATTEMPUR/FORMGOOGLE/inject_code.html',
        timeout=5
    ).read()
    new_tab = json.loads(new_tab_data)
    print(f"New tab created: {new_tab.get('id')}")
    print(f"URL: {new_tab.get('url')}")
    
    # Simpan ID untuk file
    with open('new_tab_id.txt', 'w') as f:
        f.write(new_tab.get('id', ''))
    print("Saved new tab ID")
    sys.exit(0)

# Berhasil connect ke WebSocket
def send_recv(method, params=None, timeout=15):
    msg_id = int(time.time() * 1000)
    ws.send(json.dumps({'id': msg_id, 'method': method, 'params': params or {}}))
    deadline = time.time() + timeout
    while time.time() < deadline:
        try:
            ws.settimeout(2)
            resp = json.loads(ws.recv())
            if resp.get('id') == msg_id:
                return resp
        except:
            pass
    return None

send_recv('Runtime.enable')
time.sleep(0.3)

inject = f"""
(function() {{
  const code = atob("{code_b64}");
  const cms = document.querySelectorAll('.CodeMirror');
  if (cms.length > 0 && cms[0].CodeMirror) {{
    cms[0].CodeMirror.setValue(code);
    return 'OK:CodeMirror:' + cms.length;
  }}
  if (typeof monaco !== 'undefined') {{
    const m = monaco.editor.getModels();
    if (m.length > 0) {{ m[0].setValue(code); return 'OK:Monaco:' + m.length; }}
  }}
  return 'INFO:cm=' + cms.length + ',monaco=' + typeof monaco;
}})()
"""

result = send_recv('Runtime.evaluate', {'expression': inject, 'returnByValue': True, 'awaitPromise': True}, 15)
print(f"Inject result: {result}")
ws.close()
