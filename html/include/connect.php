<?php
// Load environment variables manually
$env_file = __DIR__ . '/../.env';
$env_vars = [];

try {
    if (file_exists($env_file)) {
        $env_contents = file_get_contents($env_file);
        $env_lines = explode("\n", $env_contents);
        
        foreach ($env_lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            
            $parts = explode('=', $line, 2);
            if (count($parts) == 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1], '"\'');
                $env_vars[$key] = $value;
            }
        }
    }

    // Validate required database connection parameters
    $db_host = $env_vars['DB_HOST'] ?? null;
    $db_user = $env_vars['DB_USERNAME'] ?? null;
    $db_password = $env_vars['DB_PASSWORD'] ?? null;
    $db_name = $env_vars['DB_DATABASE'] ?? null;

    if (!$db_host || !$db_user || !$db_password || !$db_name) {
        throw new Exception('Missing database configuration. Please check your .env file.');
    }

    // Establish database connection
    $conn = mysqli_connect($db_host, $db_user, $db_password, $db_name);

    // Check connection
    if (!$conn) {
        throw new Exception('Database connection failed: ' . mysqli_connect_error());
    }

    mysqli_set_charset($conn, 'utf8mb4');
} catch (Exception $e) {
    // Log the full error for server-side debugging
    
    // Return a generic error to the client
    http_response_code(500);
    die('A system error occurred. Please contact the administrator.');
}
