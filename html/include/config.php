<?php
// Force HTTPS
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    $location = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $location);
    exit;
}

// Load environment variables from .env file
function loadEnv($path) {
    if (!file_exists($path)) {
        throw new Exception(".env file not found");
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

// Define base path
define('BASE_PATH', dirname(__DIR__));

// Definiera bas-URL för länkar och bilder - enkel lösning för undermappar
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
define('BASE_PATH_URL', $scriptDir);

// Load environment variables
loadEnv(BASE_PATH . '/.env');

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