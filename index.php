<?php
/**
 * FORMGOOGLE - Root Router
 * Memproses direct access, ngrok, dan clean URL path /KODE_SALES
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($requestUri, PHP_URL_PATH);

// Bersihkan base folder path: /ALATTEMPUR/FORMGOOGLE/ atau path hosting/ngrok
$scriptName = dirname($_SERVER['SCRIPT_NAME']);
if ($scriptName !== '/' && $scriptName !== '\\' && strpos($path, $scriptName) === 0) {
    $path = substr($path, strlen($scriptName));
}
$path = trim($path, '/');

// 1. Jika rute admin
if ($path === 'admin' || strpos($path, 'admin/') === 0) {
    require __DIR__ . '/public/admin/index.php';
    exit;
}

// 2. Jika request file statis atau endpoint tertentu
if ($path === 'submit.php' || $path === 'public/submit.php') {
    require __DIR__ . '/public/submit.php';
    exit;
}
if ($path === 'preview_cbn.php' || $path === 'public/preview_cbn.php') {
    require __DIR__ . '/public/preview_cbn.php';
    exit;
}

// 3. Jika path memiliki slug sales (misal: /SEP-001 atau /s/SEP-001)
if (!empty($path) && $path !== 'index.php' && $path !== 'public' && $path !== 'public/index.php') {
    $slug = $path;
    if (strpos($slug, 's/') === 0) {
        $slug = substr($slug, 2);
    }
    // Abaikan jika request file statis .css / .js / dll
    if (!preg_match('/\.(php|css|js|png|jpg|jpeg|gif|svg|ico)$/i', $slug)) {
        $_GET['sales'] = $slug;
    }
}

// Teruskan ke form utama di public/index.php
require __DIR__ . '/public/index.php';
