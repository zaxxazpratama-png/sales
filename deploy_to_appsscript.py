"""
Script: deploy_to_appsscript.py
Tujuan: Upload Code.gs terbaru langsung ke Google Apps Script via Apps Script API
menggunakan token autentikasi dari browser yang sudah login.
"""

import subprocess, json, os, sys

# ========== KONFIGURASI ==========
SCRIPT_ID   = '1MkIYWQ_0mrKaQSRxh5kcxKBrM6JNbjziKBEsZ7O8t0G3Y4Q8_7Q'
CODE_FILE   = 'apps-script/Code.gs'

# Baca kode terbaru
with open(CODE_FILE, 'r', encoding='utf-8') as f:
    code_content = f.read()

print(f"✅ Code.gs dibaca: {len(code_content)} bytes")
print(f"   function box() ada: {'function box(' in code_content}")

# Coba pakai clasp (Apps Script CLI)
try:
    result = subprocess.run(
        ['clasp', '--version'],
        capture_output=True, text=True, timeout=10
    )
    print(f"\n📦 clasp versi: {result.stdout.strip()}")
    HAS_CLASP = True
except FileNotFoundError:
    HAS_CLASP = False
    print("\n⚠️  clasp tidak ditemukan - mencoba install...")

if not HAS_CLASP:
    # Install clasp
    r = subprocess.run(
        ['npm', 'install', '-g', '@google/clasp'],
        capture_output=True, text=True, timeout=60
    )
    print(r.stdout[-500:] if r.stdout else '')
    print(r.stderr[-300:] if r.stderr else '')
    try:
        r2 = subprocess.run(['clasp', '--version'], capture_output=True, text=True)
        print(f"✅ clasp installed: {r2.stdout.strip()}")
        HAS_CLASP = True
    except:
        print("❌ clasp install gagal")

if HAS_CLASP:
    # Buat .clasp.json
    clasp_cfg = json.dumps({"scriptId": SCRIPT_ID, "rootDir": "apps-script"})
    with open('.clasp.json', 'w') as f:
        f.write(clasp_cfg)
    print(f"✅ .clasp.json ditulis")

    # Push code
    print("\n🚀 Menjalankan: clasp push --force ...")
    r = subprocess.run(
        ['clasp', 'push', '--force'],
        capture_output=True, text=True, timeout=60
    )
    print("STDOUT:", r.stdout)
    print("STDERR:", r.stderr)
    
    if r.returncode == 0 or 'Pushed' in r.stdout or 'pushed' in r.stdout.lower():
        print("\n✅✅ BERHASIL! Code.gs telah di-push ke Google Apps Script!")
        print("Sekarang deploy via: clasp deploy --description 'box-grid v3'")
        
        # Deploy
        rd = subprocess.run(
            ['clasp', 'deploy', '--description', '1-char-per-box v3'],
            capture_output=True, text=True, timeout=60
        )
        print("Deploy STDOUT:", rd.stdout)
        print("Deploy STDERR:", rd.stderr)
    else:
        print("\n❌ Push gagal. Mungkin perlu login clasp dulu.")
        print("   Jalankan: clasp login")
