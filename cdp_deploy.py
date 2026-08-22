"""
Script: Deploy Code.gs ke Apps Script via Chrome DevTools Protocol (CDP)
Cara: Koneksi ke CDP port 9222, cari tab Apps Script, eksekusi JS untuk
paste kode baru dan deploy via keyboard shortcuts.
"""
import json, time, urllib.request, websocket, threading, sys, os

CDP_PORT = 9222
SCRIPT_ID = '1MkIYWQ_0mrKaQSRxh5kcxKBrM6JNbjziKBEsZ7O8t0G3Y4Q8_7Q'
CODE_FILE = 'apps-script/Code.gs'

# Baca kode terbaru
with open(CODE_FILE, 'r', encoding='utf-8') as f:
    code = f.read()

print(f"Code.gs: {len(code)} bytes | function box(): {'function box(' in code}")

# Ambil daftar tab dari CDP
tabs_json = urllib.request.urlopen(f'http://localhost:{CDP_PORT}/json', timeout=5).read()
tabs = json.loads(tabs_json)
print(f"\nTotal tabs: {len(tabs)}")
for t in tabs:
    print(f"  [{t.get('type')}] {t.get('title','')[:70]} - {t.get('url','')[:60]}")

# Cari tab Apps Script
as_tab = None
for t in tabs:
    url = t.get('url', '')
    if 'script.google.com' in url and 'projects' in url:
        as_tab = t
        print(f"\nFound Apps Script tab: {t['title']}")
        print(f"  ID: {t['id']}")
        print(f"  WS: {t.get('webSocketDebuggerUrl', 'N/A')}")
        break

if not as_tab:
    print("\nApps Script tab NOT found! Available tabs:")
    for t in tabs:
        if t.get('type') == 'page':
            print(f"  {t.get('title')}: {t.get('url')}")
    sys.exit(1)

ws_url = as_tab.get('webSocketDebuggerUrl')
if not ws_url:
    print("No WebSocket URL for this tab - tab might be inactive")
    sys.exit(1)

# Koneksi WebSocket ke CDP
result_event = threading.Event()
result_data = {}
msg_id = [0]

ws = websocket.WebSocket()
ws.connect(ws_url, timeout=10)
print(f"\nConnected to CDP WebSocket!")

def send_cmd(method, params=None):
    msg_id[0] += 1
    cmd = {'id': msg_id[0], 'method': method, 'params': params or {}}
    ws.send(json.dumps(cmd))
    # Tunggu response
    deadline = time.time() + 10
    while time.time() < deadline:
        try:
            ws.settimeout(2)
            resp = json.loads(ws.recv())
            if resp.get('id') == msg_id[0]:
                return resp
        except:
            pass
    return None

# 1. Aktifkan tab Apps Script
print("Activating Apps Script tab...")
r = urllib.request.urlopen(f'http://localhost:{CDP_PORT}/json/activate/{as_tab["id"]}', timeout=5)
time.sleep(1)

# 2. Navigasi ke Apps Script editor jika belum ada
print("Enabling Runtime...")
send_cmd('Runtime.enable')
time.sleep(0.5)

# 3. Inject kode langsung via JS: gunakan editor.setValue() atau document approach
# Encode kode sebagai JSON-safe string
code_json = json.dumps(code)

# Coba berbagai cara inject ke editor Apps Script (Monaco editor)
inject_js = f"""
(function() {{
  // Coba inject ke Monaco editor (Apps Script pakai CodeMirror)
  const code = {code_json};
  
  // Method 1: Cari CodeMirror instance
  const cmEls = document.querySelectorAll('.CodeMirror');
  if (cmEls.length > 0) {{
    const cm = cmEls[0].CodeMirror;
    if (cm) {{
      cm.setValue(code);
      return 'SUCCESS: CodeMirror setValue';
    }}
  }}
  
  // Method 2: Monaco editor
  if (typeof monaco !== 'undefined') {{
    const models = monaco.editor.getModels();
    if (models.length > 0) {{
      models[0].setValue(code);
      return 'SUCCESS: Monaco setValue';
    }}
  }}
  
  // Method 3: window.scriptEditorController
  if (window.scriptEditorController) {{
    return 'Found scriptEditorController: ' + JSON.stringify(Object.keys(window.scriptEditorController));
  }}
  
  // Debug: list available globals
  const relevant = Object.keys(window).filter(k => 
    k.toLowerCase().includes('editor') || 
    k.toLowerCase().includes('codemirror') ||
    k.toLowerCase().includes('monaco') ||
    k.toLowerCase().includes('script')
  );
  return 'Debug globals: ' + relevant.join(', ');
}})()
"""

print("\nInjecting code via CDP Runtime.evaluate...")
result = send_cmd('Runtime.evaluate', {
    'expression': inject_js,
    'returnByValue': True,
    'awaitPromise': False
})

if result:
    val = result.get('result', {}).get('result', {}).get('value', 'no value')
    print(f"Result: {val}")
else:
    print("No result from evaluate")

ws.close()
print("\nDone!")
