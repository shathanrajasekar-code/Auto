<?php
/**
 * Namma AutoParts - Core Configuration File
 * Database connection & Global Constants
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Secure session settings
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    // If running on HTTPS, set cookie_secure to 1. Since local dev could be HTTP, we check.
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

// Database configuration constants
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'namma_autoparts');
define('DB_CHARSET', 'utf8mb4');

// General Site Constants
define('SITE_NAME', 'VeloParts');
define('SITE_URL', 'http://localhost:8080');
define('UPLOAD_DIR', dirname(__DIR__) . '/assets/uploads/');
define('DEFAULT_COMMISSION_RATE', 10.00); // 10% default admin commission

// Set Default Timezone to IST (India Standard Time)
date_default_timezone_set('Asia/Kolkata');

// Database Connection using PDO
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // In production, log error instead of displaying detailed message
    die("Database Connection Failed: " . htmlspecialchars($e->getMessage()));
}

// Language switcher logic
if (isset($_GET['lang'])) {
    $target_lang = $_GET['lang'] === 'ta' ? 'ta' : 'en';
    $_SESSION['lang'] = $target_lang;
    
    // Redirect back to current page without lang parameter
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $query = $_GET;
    unset($query['lang']);
    $query_str = http_build_query($query);
    $redirect_url = $uri . ($query_str ? '?' . $query_str : '');
    header("Location: " . $redirect_url);
    exit;
}

// Load Localization
$lang = isset($_SESSION['lang']) ? $_SESSION['lang'] : 'en';
if ($lang === 'ta') {
    $translations = include __DIR__ . '/lang_ta.php';
} else {
    $translations = include __DIR__ . '/lang_en.php';
}

/**
 * Localization helper function
 */
function __($key) {
    global $translations;
    return isset($translations[$key]) ? $translations[$key] : $key;
}
