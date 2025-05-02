<?php
/**
 * Stimma - Learn in small steps
 * Copyright (C) 2025 Christian Alfredsson
 * 
 * This program is free software; licensed under GPL v2.
 * See LICENSE and LICENSE-AND-TRADEMARK.md for details.
 * 
 * The name "Stimma" is a trademark and subject to restrictions.
 */

/**
 * Send reminders to users who haven't completed their daily tasks
 * 
 * This script runs as a cron job and sends email reminders to users
 * who haven't completed their daily tasks. It only uses the .env file
 * for configuration and is completely independent of other files.
 */

// Ensure script is run from command line
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from command line.\n");
}

// Load environment variables from .env file
function loadEnv($path) {
    if (!file_exists($path)) {
        die("Error: .env file not found at $path\n");
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
    }
}

// Load environment variables
loadEnv(__DIR__ . '/../.env');

// Database configuration
$dbHost = getenv('DB_HOST');
$dbUser = getenv('DB_USERNAME');
$dbPass = trim(getenv('DB_PASSWORD'), '"');
$dbName = getenv('DB_DATABASE');

// Site configuration
$siteName = getenv('SITE_NAME');
$siteUrl = getenv('SYSTEM_URL');
$mailFrom = getenv('MAIL_FROM_ADDRESS');
$mailFromName = getenv('MAIL_FROM_NAME') ?: $siteName;

// SMTP configuration
$smtpHost = getenv('MAIL_HOST') ?: 'localhost';
$smtpPort = getenv('MAIL_PORT') ?: 25;
$smtpUsername = getenv('MAIL_USERNAME') ?: '';
$smtpPassword = getenv('MAIL_PASSWORD') ?: '';
$smtpEncryption = getenv('MAIL_ENCRYPTION') ?: 'ssl';

// Connect to database
try {
    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

// Get users who haven't completed their daily tasks
$sql = "SELECT DISTINCT u.id, u.email, u.name 
        FROM users u 
        WHERE u.email IS NOT NULL 
        AND u.email != '' 
        AND EXISTS (
            SELECT 1 
            FROM progress p
            JOIN lessons l ON p.lesson_id = l.id
            JOIN courses c ON l.course_id = c.id
            WHERE p.user_id = u.id
            AND c.status = 'active'
        )
        AND EXISTS (
            SELECT 1 
            FROM lessons l
            JOIN courses c ON l.course_id = c.id
            LEFT JOIN progress p ON l.id = p.lesson_id AND p.user_id = u.id
            WHERE c.status = 'active'
            AND (p.status IS NULL OR p.status != 'completed')
        )";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error fetching users: " . $e->getMessage() . "\n");
}

// Function to get next lesson for a user
function getNextLesson($pdo, $userId) {
    $sql = "SELECT l.*, c.title as course_title, c.description as course_description
            FROM lessons l
            JOIN courses c ON l.course_id = c.id
            LEFT JOIN progress p ON l.id = p.lesson_id AND p.user_id = ?
            WHERE c.status = 'active'
            AND (p.status IS NULL OR p.status != 'completed')
            ORDER BY c.title, l.sort_order
            LIMIT 1";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        return null;
    }
}

// Function to get completed lessons count
function getCompletedLessonsCount($pdo, $userId) {
    $sql = "SELECT COUNT(*) as count
            FROM progress p
            WHERE p.user_id = ?
            AND p.status = 'completed'";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetch()['count'];
    } catch (PDOException $e) {
        return 0;
    }
}

// Function to send SMTP mail
function sendSmtpMail($to, $subject, $message, $from = null, $fromName = null, $siteUrl = null) {
    global $smtpHost, $smtpPort, $smtpUsername, $smtpPassword, $smtpEncryption, $mailFrom, $mailFromName;
    
    // Use defaults if not specified
    $from = $from ?: $mailFrom;
    $fromName = $fromName ?: $mailFromName;
    
    // Connect to SMTP server
    if ($smtpEncryption == 'ssl') {
        $socket = fsockopen("ssl://$smtpHost", $smtpPort, $errno, $errstr, 30);
    } else {
        $socket = fsockopen($smtpHost, $smtpPort, $errno, $errstr, 30);
    }
    
    if (!$socket) {
        return false;
    }
    
    // Read server greeting
    $response = fgets($socket, 515);
    
    if (substr($response, 0, 3) != '220') {
        fclose($socket);
        return false;
    }
    
    // Send EHLO
    fputs($socket, "EHLO " . parse_url($siteUrl, PHP_URL_HOST) . "\r\n");
    $response = fgets($socket, 515);
    
    // Read all server options
    while (substr($response, 3, 1) == '-') {
        $response = fgets($socket, 515);
    }
    
    // Login if username and password are provided
    if (!empty($smtpUsername) && !empty($smtpPassword)) {
        // AUTH LOGIN
        fputs($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket, 515);
        
        if (substr($response, 0, 3) != '334') {
            fclose($socket);
            return false;
        }
        
        // Send username (base64 encoded)
        fputs($socket, base64_encode($smtpUsername) . "\r\n");
        $response = fgets($socket, 515);
        
        if (substr($response, 0, 3) != '334') {
            fclose($socket);
            return false;
        }
        
        // Send password (base64 encoded)
        fputs($socket, base64_encode($smtpPassword) . "\r\n");
        $response = fgets($socket, 515);
        
        if (substr($response, 0, 3) != '235') {
            fclose($socket);
            return false;
        }
    }
    
    // FROM
    fputs($socket, "MAIL FROM:<$from>\r\n");
    $response = fgets($socket, 515);
    
    if (substr($response, 0, 3) != '250') {
        fclose($socket);
        return false;
    }
    
    // TO
    fputs($socket, "RCPT TO:<$to>\r\n");
    $response = fgets($socket, 515);
    
    if (substr($response, 0, 3) != '250') {
        fclose($socket);
        return false;
    }
    
    // DATA
    fputs($socket, "DATA\r\n");
    $response = fgets($socket, 515);
    
    if (substr($response, 0, 3) != '354') {
        fclose($socket);
        return false;
    }
    
    // Prepare headers
    $headers = "From: $fromName <$from>\r\n";
    $headers .= "To: $to\r\n";
    $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "X-Mailer: Stimma Mailer\r\n";
    $headers .= "\r\n";
    
    // Send email content
    fputs($socket, $headers . $message . "\r\n.\r\n");
    $response = fgets($socket, 515);
    
    if (substr($response, 0, 3) != '250') {
        fclose($socket);
        return false;
    }
    
    // QUIT
    fputs($socket, "QUIT\r\n");
    $response = fgets($socket, 515);
    
    // Close connection
    fclose($socket);
    
    return true;
}

// Send reminders
foreach ($users as $user) {
    // Test filter - only send to christian@iteca.se
 //   if ($user['email'] !== 'christian@iteca.se') {
  //      continue;
  //  }

    // Get next lesson and completed count
    $nextLesson = getNextLesson($pdo, $user['id']);
    $completedCount = getCompletedLessonsCount($pdo, $user['id']);
    
    $to = $user['email'];
    $subject = "Stimma: Lär dig mer om " . ($nextLesson ? $nextLesson['title'] : "din nästa lektion");
    
    $message = "
        <html>
        <head>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    line-height: 1.6;
                    color: #333;
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                }
                .button { 
                    display: inline-block; 
                    padding: 12px 24px; 
                    background-color: #0d6efd; 
                    color: white; 
                    text-decoration: none; 
                    border-radius: 5px; 
                    margin: 20px 0;
                    font-weight: bold;
                    text-align: center;
                }
                .highlight {
                    color: #0d6efd;
                    font-weight: bold;
                }
                .emoji {
                    font-size: 1.2em;
                }
            </style>
        </head>
        <body>
            <h2>Hej {$user['name']}! <span class='emoji'>👋</span></h2>
            
            " . ($completedCount > 0 ? "
            <p>Imponerande! Du har redan slutfört <span class='highlight'>$completedCount lektioner</span>. 
            Varje steg du tar i din utbildning är en investering i din framtid. <span class='emoji'>💪</span></p>
            " : "") . "
            
            " . ($nextLesson ? "
            <p>Din nästa lektion väntar på dig i <span class='highlight'>{$nextLesson['course_title']}</span>:</p>
            <h3>{$nextLesson['title']}</h3>
            " . (!empty($nextLesson['content']) ? "<p>" . substr(strip_tags($nextLesson['content']), 0, 200) . "...</p>" : "") . "
            " : "
            <p>Din nästa lektion väntar på dig! Fortsätt din resa mot kunskap och utveckling.</p>
            ") . "
            
            <div style='text-align: center;'>
                <a href='$siteUrl' class='button'>
                    Fortsätt din utbildning <span class='emoji'>🚀</span>
                </a>
            </div>
            
        </body>
        </html>
    ";
    
    if (sendSmtpMail($to, $subject, $message, null, null, $siteUrl)) {
        echo "Reminder sent to {$user['email']}\n";
    } else {
        echo "Failed to send reminder to {$user['email']}\n";
    }
}

echo "Reminder process completed.\n"; 