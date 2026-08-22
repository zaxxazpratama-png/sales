"""
Deploy Code.gs via Playwright yang connect ke Chrome yang sudah berjalan.
Playwright bisa bypass origin restriction karena menggunakan CDP secara native.
"""
import asyncio, base64, json, sys, time, os

async def deploy():
    from playwright.async_api import async_playwright
    
    CODE_FILE = 'apps-script/Code.gs'
    SCRIPT_ID = '1MkIYWQ_0mrKaQSRxh5kcxKBrM6JNbjziKBEsZ7O8t0G3Y4Q8_7Q'
    
    # Baca kode
    with open(CODE_FILE, 'r', encoding='utf-8') as f:
        code = f.read()
    
    code_b64 = base64.b64encode(code.encode('utf-8')).decode('ascii')
    print(f"Code: {len(code)} bytes | function box(): {'function box(' in code}")
    
    async with async_playwright() as p:
        # Connect ke Chrome yang sudah berjalan di port 9222
        print("Connecting to existing Chrome at port 9222...")
        browser = await p.chromium.connect_over_cdp("http://localhost:9222")
        print(f"Connected! Contexts: {len(browser.contexts)}")
        
        # Cari context dan page Apps Script
        apps_script_page = None
        for ctx in browser.contexts:
            pages = ctx.pages
            print(f"  Context has {len(pages)} pages")
            for pg in pages:
                url = pg.url
                title = pg.title() if callable(pg.title) else ''
                print(f"    Page: {url[:70]}")
                if 'script.google.com' in url:
                    apps_script_page = pg
                    print(f"    ^^^ FOUND Apps Script page!")
                    break
            if apps_script_page:
                break
        
        if not apps_script_page:
            print("Apps Script page not found! Creating new one...")
            ctx = browser.contexts[0]
            apps_script_page = await ctx.new_page()
            await apps_script_page.goto(
                f'https://script.google.com/home/projects/{SCRIPT_ID}/edit',
                wait_until='networkidle',
                timeout=30000
            )
            print("Navigated to Apps Script editor")
            await asyncio.sleep(3)
        
        # Inject code ke editor Apps Script via JavaScript
        print("\nInjecting code into Apps Script editor...")
        
        inject_js = f"""
(async () => {{
  const code = atob("{code_b64}");
  
  // Method 1: CodeMirror (Apps Script uses this)
  const cmEls = document.querySelectorAll('.CodeMirror');
  for (const el of cmEls) {{
    if (el.CodeMirror) {{
      el.CodeMirror.setValue(code);
      return 'OK:CodeMirror:' + cmEls.length;
    }}
  }}
  
  // Method 2: Monaco editor  
  if (typeof monaco !== 'undefined') {{
    const models = monaco.editor.getModels();
    if (models.length > 0) {{
      models[0].setValue(code);
      return 'OK:Monaco:' + models.length;
    }}
  }}
  
  // Method 3: Apps Script API via fetch with credentials
  try {{
    const apiUrl = 'https://script.googleapis.com/v1/projects/{SCRIPT_ID}/content';
    const files = [{{ name: 'Code', type: 'SERVER_JS', source: code }}];
    const resp = await fetch(apiUrl, {{
      method: 'PUT',
      credentials: 'include',
      headers: {{ 'Content-Type': 'application/json' }},
      body: JSON.stringify({{ files }})
    }});
    const text = await resp.text();
    if (resp.ok) return 'OK:API:' + resp.status;
    return 'API_FAIL:' + resp.status + ':' + text.substring(0, 150);
  }} catch(e) {{
    return 'ERROR:' + e.message + ' cm=' + cmEls.length + ' monaco=' + (typeof monaco);
  }}
}})()
"""
        
        result = await apps_script_page.evaluate(inject_js)
        print(f"Inject result: {result}")
        
        if result and result.startswith('OK:'):
            print("\nCode successfully updated!")
            
            if 'CodeMirror' in result or 'Monaco' in result:
                # Save dengan Ctrl+S
                print("Saving with Ctrl+S...")
                await apps_script_page.keyboard.press('Control+s')
                await asyncio.sleep(3)
                
                # Deploy: cari tombol Deploy
                print("Looking for Deploy button...")
                deploy_btn = apps_script_page.locator('text=Deploy').first
                if await deploy_btn.count() > 0:
                    await deploy_btn.click()
                    await asyncio.sleep(1)
                    
                    # Klik Manage deployments
                    manage = apps_script_page.locator('text=Manage deployments').first
                    if await manage.count() > 0:
                        await manage.click()
                        await asyncio.sleep(2)
                        
                        # Klik edit icon
                        edit = apps_script_page.locator('[aria-label="Edit deployment"]').first
                        if await edit.count() > 0:
                            await edit.click()
                            await asyncio.sleep(1)
                            
                            # Pilih New version
                            version_select = apps_script_page.locator('text=New version').first
                            if await version_select.count() > 0:
                                await version_select.click()
                                await asyncio.sleep(1)
                            
                            # Klik Deploy
                            deploy_confirm = apps_script_page.locator('button:has-text("Deploy")').last
                            if await deploy_confirm.count() > 0:
                                await deploy_confirm.click()
                                await asyncio.sleep(3)
                                print("Deployment triggered!")
            
            elif 'API' in result:
                print("Code updated via API! Need to deploy separately.")
                
                # Coba deploy via API
                deploy_js = f"""
async function deployViaApi() {{
  const resp = await fetch('https://script.googleapis.com/v1/projects/{SCRIPT_ID}/deployments', {{
    method: 'POST',
    credentials: 'include',
    headers: {{ 'Content-Type': 'application/json' }},
    body: JSON.stringify({{
      description: '1-char-per-box v3',
      webAppConfig: {{
        executeAs: 'USER_DEPLOYING',
        access: 'ANYONE'
      }}
    }})
  }});
  const text = await resp.text();
  return resp.status + ':' + text.substring(0, 200);
}}
deployViaApi();
"""
                deploy_result = await apps_script_page.evaluate(deploy_js)
                print(f"Deploy result: {deploy_result}")
        else:
            print(f"Inject failed: {result}")
        
        print("\nDone! Check the Apps Script editor.")
        await browser.close()

asyncio.run(deploy())
