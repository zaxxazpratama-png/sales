# 🌐 Panduan Migrasi & Deploy ke cPanel: idpanel.site

Dokumen ini berisi panduan langkah demi langkah untuk memindahkan **FORMGOOGLE** (dan integrasi **TIKORSEMIGOOGLE**) ke domain Anda **`https://idpanel.site`** menggunakan cPanel & database MySQL.

---

## 📦 1. Persiapan File di cPanel

1. Buka **cPanel** &rarr; masuk ke menu **File Manager**.
2. Masuk ke folder **`public_html`** (atau folder subdomain jika ingin menggunakan subdomain seperti `sales.idpanel.site`).
3. Upload seluruh file project **FORMGOOGLE** ke dalam folder tersebut (termasuk folder `src/`, `public/`, `data/`, `vendor/`, dan `.htaccess`).
4. Pastikan file `.htaccess` ter-upload (Jika tidak terlihat, klik **Settings** di pojok kanan atas File Manager &rarr; centang **Show Hidden Files (dotfiles)**).

---

## 🗄️ 2. Buat Database MySQL & Import SQL

1. Di cPanel, buka menu **MySQL® Databases**:
   - Buat database baru, contoh: `idpanel_sales`
   - Buat user database baru, contoh: `idpanel_user` (catat password yang dibuat)
   - Tambahkan user ke database dan centang **ALL PRIVILEGES**.

2. Buka menu **phpMyAdmin** di cPanel:
   - Pilih database yang baru dibuat (`idpanel_sales`).
   - Klik tab **Import** di bagian atas.
   - Pilih file [`setup_cpanel.sql`](file:///c:/xampp/htdocs/ALATTEMPUR/FORMGOOGLE/setup_cpanel.sql) dari project ini.
   - Klik tombol **Import / Go** di bagian bawah.
   - ✅ Seluruh tabel (`users`, `sales`, `orders`, `settings`, `tikor_devices`, `tikor_heartbeats`) dan data awal akan otomatis dibuat!

---

## ⚙️ 3. Konfigurasi File `.env` di cPanel

1. Di **File Manager** cPanel pada folder `public_html`, buat file bernama **`.env`** (atau edit dari file `.env.example`).
2. Masukkan konfigurasi database & domain:

```env
# ============================================
# DATABASE MYSQL cPanel
# ============================================
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=nama_cpanel_idpanel_sales
DB_USERNAME=nama_cpanel_idpanel_user
DB_PASSWORD=password_database_anda

# ============================================
# APP CONFIG
# ============================================
APP_NAME="Sales Order Form - PT. TALENTA INTEGRITAS NASIONAL"
APP_URL=https://idpanel.site
APP_ENV=production
APP_DEBUG=false

# ============================================
# GOOGLE APPS SCRIPT CONFIG
# ============================================
APPS_SCRIPT_URL=https://script.google.com/macros/s/AKfycbwIRsM7AJx9q7CdJle7T6LAeTQnllIK8PIBwNQB_LwO42pZrhBgxUTTj12mLGJVHmog/exec

# ============================================
# EMAIL SMTP CONFIG (Opsional)
# ============================================
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_NAME="PT. TALENTA INTEGRITAS NASIONAL"
MAIL_FROM_EMAIL=
MAIL_TO_EMAIL=
MAIL_TO_NAME="Admin TIN"
```

---

## 🔗 4. Daftar URL yang Dapat Diakses

Setelah setup selesai, semua URL berikut langsung aktif di domain Anda:

| Fitur | URL di `idpanel.site` | Keterangan |
|---|---|---|
| **Cek Status Sistem** | `https://idpanel.site/test_connection.php` | Halaman diagnostik database & sistem |
| **Panel Login Admin** | `https://idpanel.site/admin` | Login Superadmin & Team Leader |
| **Dashboard Admin** | `https://idpanel.site/dashboard` atau `/admin/dashboard.php` | Kelola Sales, Order, Paket, dll |
| **Formulir Sales** | `https://idpanel.site/SEP-001` atau `/s/SEP-001` | Form pendaftaran CBN mitra |
| **Preview PDF CBN** | `https://idpanel.site/preview_cbn.php` | Generator & preview PDF blanko CBN |

---

## 🔑 5. Akun Login Default

- **Superadmin**:
  - Username: `superadmin`
  - Password: *(Password superadmin yang telah Anda set sebelumnya)*
- **Team Leader (Contoh)**:
  - Username: `suharta`
  - Kode TL: `TIN-SUHARTA`

---

## 📡 6. Integrasi TIKORSEMIGOOGLE

Tabel **`tikor_devices`** dan **`tikor_heartbeats`** sudah dibuat di dalam `setup_cpanel.sql`. Jika Anda menempatkan file API TIKOR di `https://idpanel.site/api/heartbeat.php`, tracker dapat langsung mengirim koordinat GPS dan status online sales ke database MySQL cPanel ini secara terpusat!
