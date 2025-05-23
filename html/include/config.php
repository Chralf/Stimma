<?php
/**
 * Stimma - Configuration
 * 
 * This file contains all configuration settings for the application.
 * Edit these values to match your environment.
 */

// Force HTTPS
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    $location = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $location);
    exit;
}

// Path Configuration
define('ROOT_PATH', dirname(__DIR__)); // One level up from this file
define('ENV_PATH', ROOT_PATH . '/.env');
define('ADMIN_PATH', ROOT_PATH . '/admin');
define('FRONTEND_PATH', ROOT_PATH);

// Load environment variables
function loadEnv($path) {
    if (!file_exists($path)) {
        die("Error: .env file not found at: " . $path);
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
        $value = trim($value);
        
            // Remove quotes if present
            if (strpos($value, '"') === 0 || strpos($value, "'") === 0) {
                $value = substr($value, 1, -1);
            }
            
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
    }
}
}

// Load environment variables from .env file
loadEnv(ENV_PATH);

// Database Configuration
define('DB_CONNECTION', getenv('DB_CONNECTION') ?: 'mysql');
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_DATABASE', getenv('DB_DATABASE') ?: 'stimma');
define('DB_USERNAME', getenv('DB_USERNAME') ?: '');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');

// Site Configuration
define('SITE_NAME', getenv('SITE_NAME') ?: 'Stimma');
define('SITE_URL', getenv('SITE_URL') ?: 'https://' . $_SERVER['HTTP_HOST']);
define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: 'admin@example.com');

// Mail Configuration
define('MAIL_HOST', getenv('MAIL_HOST') ?: '');
define('MAIL_PORT', getenv('MAIL_PORT') ?: '465');
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: '');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: '');
define('MAIL_ENCRYPTION', getenv('MAIL_ENCRYPTION') ?: 'ssl');
define('MAIL_FROM_ADDRESS', getenv('MAIL_FROM_ADDRESS') ?: '');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'Stimma');
define('MAIL_ALLOWED_RECIPIENTS', getenv('MAIL_ALLOWED_RECIPIENTS') ?: '');

// AI Configuration
define('AI_SERVER', getenv('AI_SERVER') ?: '');
define('AI_API_KEY', getenv('AI_API_KEY') ?: '');
define('AI_MODEL', getenv('AI_MODEL') ?: '');
define('AI_MAX_COMPLETION_TOKENS', getenv('AI_MAX_COMPLETION_TOKENS') ?: 4096);
define('AI_TEMPERATURE', getenv('AI_TEMPERATURE') ?: 0.7);
define('AI_TOP_P', getenv('AI_TOP_P') ?: 0.9);
define('AI_STREAM', getenv('AI_STREAM') ?: false);
define('AI_MAX_MESSAGE_LENGTH', getenv('AI_MAX_MESSAGE_LENGTH') ?: 1000);
define('AI_RATE_LIMIT_REQUESTS', getenv('AI_RATE_LIMIT_REQUESTS') ?: 50);
define('AI_RATE_LIMIT_MINUTES', getenv('AI_RATE_LIMIT_MINUTES') ?: 5);

// Auth Configuration
define('AUTH_TOKEN_EXPIRY_MINUTES', getenv('AUTH_TOKEN_EXPIRY_MINUTES') ?: 15);
define('SESSION_REGENERATE_MINUTES', getenv('SESSION_REGENERATE_MINUTES') ?: 30);
define('SESSION_LIFETIME', getenv('SESSION_LIFETIME') ?: 30);
define('SESSION_LIFETIME_HOURS', getenv('SESSION_LIFETIME_HOURS') ?: 24);

// Session settings
if (session_status() === PHP_SESSION_NONE) {
    // Set cookie parameters before starting session
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $httponly = true;

    // Use only cookies for session handling
    ini_set('session.use_cookies', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_trans_sid', 0);
    
    // Set session cookie parameters
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME * 24 * 60 * 60,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => $httponly,
        'samesite' => 'Lax'
    ]);
    
    // Start session
    session_start();
    
    // Renew session if older than a day
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 86400)) {
        // Save important session data
        $userId = $_SESSION['user_id'] ?? null;
        $userEmail = $_SESSION['user_email'] ?? null;
        
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        // Restore important session data
        if ($userId) $_SESSION['user_id'] = $userId;
        if ($userEmail) $_SESSION['user_email'] = $userEmail;
    }
    
    // Update last activity timestamp
    $_SESSION['last_activity'] = time();
}

// Cache for allowed domains
$allowedDomainsCache = null;

/**
 * Check if a domain is allowed
 * 
 * @param string $domain The domain to check
 * @return bool True if domain is allowed, false otherwise
 */
function isDomainAllowed($domain) {
    global $allowedDomainsCache;
    
    // Use cached result if available
    if ($allowedDomainsCache !== null) {
        return in_array($domain, $allowedDomainsCache);
    }
    
    // Get allowed domains from configuration
    $allowedDomainsStr = MAIL_ALLOWED_RECIPIENTS;
    if (empty($allowedDomainsStr)) {
        $allowedDomainsCache = [];
        return false;
    }
    
    // Parse domains and cache the result
    $allowedDomainsCache = array_map('trim', explode(',', $allowedDomainsStr));
    return in_array($domain, $allowedDomainsCache);
}