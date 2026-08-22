<?php
/**
 * FORMGOOGLE - Root Router
 * Memproses direct access, railway, ngrok, clean URL path /KODE_SALES, dan static asset loader
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($requestUri, PHP_URL_PATH);

// Bersihkan base folder path: /ALATTEMPUR/FORMGOOGLE/ atau path hosting/ngrok/railway
$scriptName = dirname($_SERVER['SCRIPT_NAME']);
if ($scriptName !== '/' && $scriptName !== '\\' && strpos($path, $scriptName) === 0) {
    $path = substr($path, strlen($scriptName));
}
$path = trim($path, '/');

// 0. Static Asset Loader (CSS, JS, Fonts, Images, PDF)
if (preg_match('#^(public/)?(assets/.+|asli_bg\.jpg|asli_page_1\.png|asli\.pdf)$#', $path, $matches)) {
    $targetRel = $matches[2];
    $assetFile = file_exists(__DIR__ . '/public/' . $targetRel) 
        ? __DIR__ . '/public/' . $targetRel 
        : __DIR__ . '/' . $targetRel;
    if (file_exists($assetFile)) {
        $ext = strtolower(pathinfo($assetFile, PATHINFO_EXTENSION));
        $mimes = [
            'css'   => 'text/css; charset=UTF-8',
            'js'    => 'application/javascript; charset=UTF-8',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'svg'   => 'image/svg+xml',
            'ico'   => 'image/x-icon',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'   => 'font/ttf',
            'pdf'   => 'application/pdf',
        ];
        header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
        header('Cache-Control: public, max-age=86400');
        readfile($assetFile);
        exit;
    }
}

// 1. Jika rute admin atau dashboard
if ($path === 'admin' || strpos($path, 'admin/') === 0 || $path === 'dashboard.php' || $path === 'dashboard') {
    if ($path === 'dashboard.php' || $path === 'dashboard' || $path === 'admin/dashboard.php' || $path === 'admin/dashboard') {
        require __DIR__ . '/public/admin/dashboard.php';
        exit;
    }
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
