# 📋 FORMGOOGLE - Sales Order Form
### PT. Sinergi Emas Perdana

Form web untuk input Sales Order yang secara otomatis:
- ✅ Menyimpan data ke **Google Spreadsheet**
- ✅ Upload file ke **Google Drive**
- ✅ Kirim **notifikasi email** ke admin

> 🎉 **100% GRATIS** — Hanya butuh akun Gmail biasa. Tidak perlu kartu kredit!

---

## 🚀 Cara Setup (Hanya 3 Langkah!)

### Langkah 1 — Buat Google Spreadsheet & Folder Drive

1. Buka **https://sheets.google.com** → Buat spreadsheet baru → catat namanya
2. Buka **https://drive.google.com** → Buat folder baru → untuk simpan file upload

---

### Langkah 2 — Deploy Google Apps Script

> **Ini yang membuat semuanya bekerja. Ikuti dengan teliti.**

1. Buka **https://script.google.com**
2. Klik tombol **"New Project"** (pojok kiri atas)
3. Hapus semua isi editor, lalu **copy-paste** seluruh isi file:
   ```
   apps-script/Code.gs
   ```
4. Di dalam kode, **ganti 3 nilai ini**:
   ```javascript
   SPREADSHEET_ID: 'GANTI_INI',   // ID dari URL spreadsheet kamu
   DRIVE_FOLDER_ID: 'GANTI_INI',  // ID dari URL folder Drive kamu
   NOTIF_EMAIL: 'GANTI_INI',      // Email kamu untuk terima notifikasi
   ```

   **Cara dapat Spreadsheet ID:**
   ```
   URL: https://docs.google.com/spreadsheets/d/AMBIL_INI/edit
   ```

   **Cara dapat Folder ID:**
   ```
   URL: https://drive.google.com/drive/folders/AMBIL_INI
   ```

5. Klik **Save** (ikon disket) → beri nama project, misal: `FormSO-SEP`

6. Klik menu **Deploy** → **New Deployment**

7. Konfigurasi:
   - **Type**: Web App
   - **Description**: Sales Order Form
   - **Execute as**: Me
   - **Who has access**: Anyone

8. Klik **Deploy** → Muncul popup **"Authorization required"**

9. Klik **Authorize access** → pilih akun Gmail kamu → **Allow**

10. Copy **URL Web App** yang muncul (panjang, mulai dari `https://script.google.com/macros/s/...`)

---

### Langkah 3 — Isi file `.env`

Buka file `.env`, paste URL tadi:

```env
APPS_SCRIPT_URL=https://script.google.com/macros/s/XXXXX.../exec
```

**Selesai!** Tidak ada yang lain.

---

## ▶️ Cara Buka Form

Pastikan XAMPP sudah running, buka di browser:
```
http://localhost/ALATTEMPUR/FORMGOOGLE/public/
```

---

## 📁 Struktur Folder

```
FORMGOOGLE/
├── apps-script/
│   └── Code.gs            ← Kode untuk di-paste ke script.google.com
├── public/
│   ├── index.php          ← Form utama (buka ini di browser)
│   ├── submit.php         ← Handler POST otomatis
│   └── assets/ (css + js)
├── src/
│   ├── Config.php
│   ├── Validator.php
│   └── AppsScriptService.php  ← Kirim data ke Apps Script via cURL
├── uploads/               ← Temp upload (auto-delete)
├── logs/error.log         ← Log error
├── vendor/                ← Composer (hanya phpdotenv)
├── composer.json
└── .env                   ← ISI APPS_SCRIPT_URL di sini!
```

---

## 🔄 Alur Sistem

```
User isi form → Submit → submit.php
                              ↓
                    Validasi input PHP
                              ↓
                    cURL POST ke Apps Script URL
                              ↓
                    Google Apps Script:
                      ✅ Tulis ke Google Sheets
                      ✅ Upload file ke Google Drive
                      ✅ Kirim email via Gmail
                              ↓
                    Redirect ke form + pesan sukses
```

---

## ⚠️ Troubleshooting

| Masalah | Solusi |
|---|---|
| "APPS_SCRIPT_URL belum diisi" | Isi file `.env` dengan URL Apps Script |
| "cURL error" | Pastikan XAMPP mengaktifkan extension `curl` di php.ini |
| Data tidak masuk Sheet | Cek SPREADSHEET_ID di Code.gs sudah benar |
| Email tidak terkirim | Cek NOTIF_EMAIL di Code.gs |
| File tidak upload | Cek DRIVE_FOLDER_ID di Code.gs |
| Apps Script error 403 | Re-deploy dengan setting "Who has access: Anyone" |

---

## 💡 Tips

- Setelah ganti kode di Apps Script, selalu **Deploy → New Deployment** (bukan Update)
- URL Apps Script yang lama tetap bekerja, tapi best practice buat deployment baru
- Cek tab **"ERROR_LOG"** di spreadsheet jika ada masalah
