"""
Upload Code.gs ke Google Apps Script via REST API
Menggunakan cookie/token dari Chrome yang sudah login Google
"""
import json, os, sys, subprocess, urllib.request, urllib.error

SCRIPT_ID = '1MkIYWQ_0mrKaQSRxh5kcxKBrM6JNbjziKBEsZ7O8t0G3Y4Q8_7Q'
CODE_FILE = 'apps-script/Code.gs'

# Baca kode
with open(CODE_FILE, 'r', encoding='utf-8') as f:
    code = f.read()

print(f"Code.gs OK: {len(code)} bytes, box() present: {'function box(' in code}")

# Cek apakah ada .clasprc.json
clasprc = os.path.join(os.environ['USERPROFILE'], '.clasprc.json')
if os.path.exists(clasprc):
    with open(clasprc) as f:
        print("Found .clasprc.json:", f.read()[:100])
else:
    print("No .clasprc.json found - need to login")

# Coba buat .clasprc.json jika ada token di env
# Alternatif: pakai clasp login --no-localhost
print("\nCoba: clasp login --no-localhost")
print("Ini akan buka URL di terminal untuk diakses dari device lain")
