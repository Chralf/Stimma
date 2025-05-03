<?php
// Force HTTPS
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    $location = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $location);
    exit;
}

// Load environment variables from .env file
function loadEnv($path = '.env') {
    // We'll search the directory tree upward for the .env file
    $currentDir = dirname($_SERVER['SCRIPT_FILENAME']);
    $rootDir = $_SERVER['DOCUMENT_ROOT'] ?: dirname(dirname(dirname($currentDir))); // Set a limit to stop at website root
    
    $envPath = $currentDir . '/' . $path;
    
    // Keep going up directories until we find the file or reach the root directory
    while (!file_exists($envPath) && $currentDir !== $rootDir && $currentDir !== dirname($currentDir)) {
        $currentDir = dirname($currentDir);
        $envPath = $currentDir . '/' . $path;
    }
    
    // If we found the .env file, use it; otherwise, throw an exception
    if (file_exists($envPath)) {
        $path = $envPath;
    } else {
        throw new Exception(".env file not found in any parent directory up to the root");
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse line
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        // Set as environment variable
        putenv("$name=$value");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}


// Load environment variables
loadEnv('.env');

// Database configuration
define('DB_CONNECTION', getenv('DB_CONNECTION'));
define('DB_HOST', getenv('DB_HOST'));
define('DB_PORT', getenv('DB_PORT'));
define('DB_DATABASE', getenv('DB_DATABASE'));
define('DB_USERNAME', getenv('DB_USERNAME'));
define('DB_PASSWORD', trim(getenv('DB_PASSWORD'), '"'));

// Site configuration
define('SITE_NAME', getenv('SITE_NAME'));
// Ensure SITE_URL always uses HTTPS
define('SITE_URL', 'https://' . $_SERVER['HTTP_HOST']); 
define('ADMIN_EMAIL', getenv('ADMIN_EMAIL'));

// Session settings
// Kontrollera om sessionen redan är startad
if (session_status() === PHP_SESSION_NONE) {
    // Sätt cookie-parametrar innan session startas
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'; // Använd HTTPS om tillgängligt
    $httponly = true; // Förhindra JS-åtkomst till sessionscookien

    // Hämta session livstid från .env eller använd standardvärde (30 dagar)
    $sessionLifetime = getenv('SESSION_LIFETIME') ? (int)getenv('SESSION_LIFETIME') : 30;
    $sessionLifetimeSeconds = $sessionLifetime * 24 * 60 * 60;
    
    // Använd endast cookies för sessionshantering (ingen lagring på servern)
    ini_set('session.use_cookies', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_trans_sid', 0);
    
    // Sätt cookie-livstid från .env
    ini_set('session.cookie_lifetime', $sessionLifetimeSeconds);
    
    // Sätt session cookie-parametrar
    session_set_cookie_params([
        'lifetime' => $sessionLifetimeSeconds,
        'path' => getenv('SESSION_PATH') ?: '/',
        'domain' => getenv('SESSION_DOMAIN') ?: '',
        'secure' => $secure,
        'httponly' => getenv('SESSION_HTTPONLY') ? (bool)getenv('SESSION_HTTPONLY') : true,
        'samesite' => getenv('SESSION_SAMESITE') ?: 'Lax'
    ]);
    
    // Starta sessionen
    session_start();
    
    // Förnya sessionen om den är äldre än en dag för att förlänga livstiden
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 86400)) {
        // Spara undan viktig sessionsdata
        $userId = $_SESSION['user_id'] ?? null;
        $userEmail = $_SESSION['user_email'] ?? null;
        
        // Regenerera sessions-ID för säkerhet
        session_regenerate_id(true);
        
        // Återställ viktig sessionsdata
        if ($userId) $_SESSION['user_id'] = $userId;
        if ($userEmail) $_SESSION['user_email'] = $userEmail;
    }
    
    // Uppdatera senaste aktivitetstidpunkt
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
    
    // Get allowed domains from environment variable
    $allowedDomainsStr = getenv('MAIL_ALLOWED_RECIPIENTS');
    if (empty($allowedDomainsStr)) {
        $allowedDomainsCache = [];
        return false;
    }
    
    // Parse domains and cache the result
    $allowedDomainsCache = array_map('trim', explode(',', $allowedDomainsStr));
    return in_array($domain, $allowedDomainsCache);
}