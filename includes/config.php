<?php
// Prevent multiple session starts
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

mysqli_report(MYSQLI_REPORT_OFF);

// Load .env configuration
function load_env_file($filePath) {
    if (!file_exists($filePath)) return;
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim(trim($value), "\"'");
            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}
load_env_file(__DIR__ . '/../.env');

// Database configuration
define('DB_SERVER', getenv('DB_HOST') ?: 'localhost');
define('DB_USERNAME', getenv('DB_USER') ?: 'root');
define('DB_PASSWORD', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_DATABASE', getenv('DB_NAME') ?: 'sweetpop');

$con = @mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
if (!$con) {
    $con = @mysqli_connect(DB_SERVER, DB_USERNAME, '1234', DB_DATABASE);
}
if (!$con) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}
mysqli_set_charset($con, "utf8mb4");

// Global $db alias for compatibility with both $con and $db
$db = $con;

// App configuration
define('APP_NAME', 'SWEETPOP');
define('APP_TAGLINE', 'sweet treats for your main-character moments ✿');

// Cart Helper Functions
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

function get_cart_count() {
    $count = 0;
    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $qty) {
            $count += (int)$qty;
        }
    }
    return $count;
}

function format_rupiah($amount) {
    return 'Rp ' . number_format((float)$amount, 0, ',', '.');
}

// Flash messages
function set_flash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Store Settings Helper
function get_store_settings() {
    $settingsFile = __DIR__ . '/../system_settings.json';
    $defaults = [
        'store_name' => 'SWEETPOP',
        'tagline' => 'Dessert, Cake & Sweet Things for Your Main-Character Era',
        'whatsapp' => '+62 895-3275-17570',
        'email_support' => 'halo@sweetpop.com',
        'operating_hours' => '09:00 - 21:00 WIB (Setiap Hari)',
        'flat_shipping' => 15000,
        'announcement' => '🍓 Free delivery min. order Rp50K! • Freshly baked daily in Yogyakarta with premium ingredients ♡',
        'store_status' => 'open'
    ];
    if (file_exists($settingsFile)) {
        $loaded = json_decode(file_get_contents($settingsFile), true);
        if (is_array($loaded)) {
            return array_merge($defaults, $loaded);
        }
    }
    return $defaults;
}

function format_wa_number($phone) {
    $clean = preg_replace('/[^0-9]/', '', $phone);
    if (substr($clean, 0, 1) === '0') {
        $clean = '62' . substr($clean, 1);
    }
    return $clean ?: '62895327517570';
}

// Google Gemini AI Helpers (Managed strictly via .env)
function get_gemini_api_key() {
    $key = getenv('GEMINI_API_KEY');
    if (!$key && isset($_ENV['GEMINI_API_KEY'])) {
        $key = $_ENV['GEMINI_API_KEY'];
    }
    return trim($key ?: '');
}

function get_gemini_model() {
    $m = getenv('GEMINI_MODEL');
    if (!$m && isset($_ENV['GEMINI_MODEL'])) {
        $m = $_ENV['GEMINI_MODEL'];
    }
    return trim($m ?: 'gemini-3.5-flash');
}
?>
