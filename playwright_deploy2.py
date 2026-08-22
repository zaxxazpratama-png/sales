"""
Step 2: Deploy versi baru ke Apps Script setelah kode sudah di-save.
"""
import asyncio

async def deploy_version():
    from playwright.async_api import async_playwright
    
    SCRIPT_ID = '1MkIYWQ_0mrKaQSRxh5kcxKBrM6JNbjziKBEsZ7O8t0G3Y4Q8_7Q'
    
    async with async_playwright() as p:
        browser = await p.chromium.connect_over_cdp("http://localhost:9222")
        
        # Cari Apps Script page
        apps_script_page = None
        for ctx in browser.contexts:
            for pg in ctx.pages:
                if 'script.google.com' in pg.url:
                    apps_script_page = pg
                    break
            if apps_script_page:
                break
        
        if not apps_script_page:
            print("Apps Script page not found!")
            return
        
        print(f"Found Apps Script page: {apps_script_page.url[:60]}")
        
        # Bring to front
        await apps_script_page.bring_to_front()
        await asyncio.sleep(1)
        
        # Save dulu dengan Ctrl+S
        print("Saving (Ctrl+S)...")
        await apps_script_page.keyboard.press('Control+s')
        await asyncio.sleep(3)
        
        # Screenshot untuk verifikasi
        await apps_script_page.screenshot(path='public/assets/before_deploy.png')
        print("Screenshot saved")
        
        # Klik tombol Deploy
        print("Looking for Deploy button...")
        
        # Cari via text atau button
        deploy_locators = [
            apps_script_page.get_by_text('Deploy', exact=True).first,
            apps_script_page.locator('button:has-text("Deploy")').last,
            apps_script_page.locator('[data-action="deploy"]').first,
        ]
        
        clicked = False
        for loc in deploy_locators:
            try:
                count = await loc.count()
                print(f"  Locator count: {count}")
                if count > 0:
                    await loc.click()
                    clicked = True
                    print("  Clicked Deploy!")
                    break
            except Exception as e:
                print(f"  Error: {e}")
        
        if not clicked:
            print("Deploy button not found via locator, trying JS click...")
            result = await apps_script_page.evaluate("""
            () => {
                const btns = Array.from(document.querySelectorAll('button, [role="button"]'));
                const deployBtn = btns.find(b => b.textContent.trim() === 'Deploy' || b.innerText.trim() === 'Deploy');
                if (deployBtn) {
                    deployBtn.click();
                    return 'Clicked: ' + deployBtn.textContent.trim();
                }
                return 'Not found. Buttons: ' + btns.slice(0, 10).map(b => b.textContent.trim().substring(0,20)).join(', ');
            }
            """)
            print(f"JS click result: {result}")
        
        await asyncio.sleep(2)
        
        # Screenshot setelah klik
        await apps_script_page.screenshot(path='public/assets/after_deploy_click.png')
        
        # Cari "Manage deployments" atau menu item
        print("Looking for 'Manage deployments'...")
        try:
            manage = apps_script_page.get_by_text('Manage deployments').first
            count = await manage.count()
            print(f"Manage deployments count: {count}")
            if count > 0:
                await manage.click()
                await asyncio.sleep(2)
                await apps_script_page.screenshot(path='public/assets/manage_deploy.png')
                
                # Klik edit icon pada deployment pertama
                print("Looking for edit icon...")
                # Coba klik pencil/edit button
                edit_btns = apps_script_page.locator('[aria-label*="edit" i], [aria-label*="Edit" i], [title*="edit" i]')
                count = await edit_btns.count()
                print(f"Edit buttons: {count}")
                if count > 0:
                    await edit_btns.first.click()
                    await asyncio.sleep(1)
                    
                    # Pilih "New version" di dropdown
                    await apps_script_page.screenshot(path='public/assets/edit_deploy.png')
                    print("Looking for version dropdown...")
                    
                    # Cari dan klik "New version"
                    new_version = apps_script_page.get_by_text('New version').first
                    nv_count = await new_version.count()
                    print(f"New version count: {nv_count}")
                    if nv_count > 0:
                        await new_version.click()
                        await asyncio.sleep(1)
                    
                    # Klik Deploy button final
                    print("Final deploy click...")
                    final_deploy = apps_script_page.locator('button:has-text("Deploy")').last
                    fd_count = await final_deploy.count()
                    print(f"Final deploy count: {fd_count}")
                    if fd_count > 0:
                        await final_deploy.click()
                        await asyncio.sleep(4)
                        await apps_script_page.screenshot(path='public/assets/deployed.png')
                        print("DEPLOYED!")
                        
                        # Ambil URL deployment
                        url_el = apps_script_page.locator('a[href*="exec"]').first
                        if await url_el.count() > 0:
                            exec_url = await url_el.get_attribute('href')
                            print(f"Deployment URL: {exec_url}")
                            with open('public/assets/deploy_url.txt', 'w') as f:
                                f.write(exec_url)
        except Exception as e:
            print(f"Deploy UI error: {e}")
            # Screenshot for debug
            await apps_script_page.screenshot(path='public/assets/error_state.png')
        
        print("\nAll done! Check screenshots in public/assets/")
        await browser.close()

asyncio.run(deploy_version())
