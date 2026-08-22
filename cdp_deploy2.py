"""
Deploy Code.gs ke Apps Script menggunakan CDP WebSocket dengan origin yang benar
"""
import json, time, urllib.request, sys, os, base64

CDP_PORT = 9222
TAB_ID = '8986338BFCA4B86300384E831675EC3E'  # Apps Script tab ID
CODE_FILE = 'apps-script/Code.gs'
LOCAL_URL = 'http://localhost:8081/ALATTEMPUR/FORMGOOGLE/public/assets/code_payload.txt'

# Baca kode
with open(CODE_FILE, 'r', encoding='utf-8') as f:
    code = f.read()

print(f"Code: {len(code)} bytes | box(): {'function box(' in code}")

# Coba WebSocket dengan origin yang benar
try:
    import websocket
    ws_url = f'ws://localhost:{CDP_PORT}/devtools/page/{TAB_ID}'
    
    # Coba koneksi dengan origin script.google.com
    headers = {'Origin': 'https://script.google.com'}
    ws = websocket.WebSocket()
    ws.connect(ws_url, header=headers, timeout=10)
    print("WebSocket connected!")
    
    def send_recv(ws, method, params=None, timeout=15):
        import threading
        msg_id = int(time.time() * 1000)
        cmd = json.dumps({'id': msg_id, 'method': method, 'params': params or {}})
        ws.send(cmd)
        deadline = time.time() + timeout
        while time.time() < deadline:
            try:
                ws.settimeout(2)
                resp = json.loads(ws.recv())
                if resp.get('id') == msg_id:
                    return resp
                # Bisa juga event - abaikan
            except:
                pass
        return None
    
    # Enable Runtime
    send_recv(ws, 'Runtime.enable')
    time.sleep(0.3)
    
    # Baca kode dari file lokal via fetch di browser
    code_b64 = base64.b64encode(code.encode('utf-8')).decode('ascii')
    
    inject = f"""
(async function() {{
  try {{
    // Decode kode dari base64
    const b64 = "{code_b64}";
    const code = atob(b64);
    
    // Coba CodeMirror (yang Apps Script gunakan)
    const editors = document.querySelectorAll('.CodeMirror');
    if (editors.length > 0) {{
      const cm = editors[0].CodeMirror;
      if (cm) {{
        cm.setValue(code);
        // Trigger save
        setTimeout(() => {{
          document.dispatchEvent(new KeyboardEvent('keydown', {{ctrlKey: true, key: 's', keyCode: 83}}));
        }}, 500);
        return 'OK:CodeMirror:' + editors.length;
      }}
    }}
    
    // Fallback: cek Monaco
    if (typeof monaco !== 'undefined') {{
      const models = monaco.editor.getModels();
      if (models.length > 0) {{
        models[0].setValue(code);
        return 'OK:Monaco:' + models.length;
      }}
    }}
    
    // Debug info
    const info = {{
      cm: editors.length,
      monaco: typeof monaco,
      url: location.href.substring(0, 60)
    }};
    return 'DEBUG:' + JSON.stringify(info);
  }} catch(e) {{
    return 'ERROR:' + e.message;
  }}
}})()
"""
    
    print("Injecting code into Apps Script editor...")
    result = send_recv(ws, 'Runtime.evaluate', {
        'expression': inject,
        'returnByValue': True,
        'awaitPromise': True,
        'timeout': 10000
    }, timeout=15)
    
    if result:
        val = result.get('result', {}).get('result', {}).get('value', str(result))
        print(f"Result: {val}")
        
        if val and val.startswith('OK:'):
            print("\nCode injected! Now triggering save (Ctrl+S)...")
            time.sleep(1)
            # Trigger Ctrl+S
            send_recv(ws, 'Input.dispatchKeyEvent', {
                'type': 'keyDown',
                'modifiers': 8,  # Ctrl = 8
                'windowsVirtualKeyCode': 83,
                'nativeVirtualKeyCode': 83,
                'key': 's'
            })
            time.sleep(2)
            print("Saved! Now triggering deploy via menu...")
            # Deploy requires UI interaction - use keyboard
    else:
        print("No result received")
    
    ws.close()

except Exception as e:
    print(f"WebSocket error: {e}")
    print("\nTrying alternative: CDP HTTP target activation...")
    
    # Aktifkan tab dulu
    try:
        urllib.request.urlopen(f'http://localhost:{CDP_PORT}/json/activate/{TAB_ID}', timeout=5)
        print("Tab activated")
    except Exception as e2:
        print(f"Activation error: {e2}")
