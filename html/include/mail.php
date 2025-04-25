<?php
/**
 * Skicka e-post via SMTP
 *
 * @param string $to Mottagarens e-postadress
 * @param string $subject Ämne
 * @param string $message Meddelande (HTML)
 * @param string $from Avsändarens e-postadress
 * @param string $fromName Avsändarens namn
 * @return bool True om det lyckades, false vid fel
 */
function sendSmtpMail($to, $subject, $message, $from = null, $fromName = null) {
    // Hämta inställningar från .env
    $host = getenv('MAIL_HOST') ?: 'localhost';
    $port = getenv('MAIL_PORT') ?: 25;
    $username = getenv('MAIL_USERNAME') ?: '';
    $password = getenv('MAIL_PASSWORD') ?: '';
    $encryption = getenv('MAIL_ENCRYPTION') ?: 'ssl';
    
    // Använd standardvärden om inget anges
    $from = $from ?: (getenv('MAIL_FROM_ADDRESS') ?: 'noreply@tropheus.se');
    $fromName = $fromName ?: (getenv('MAIL_FROM_NAME') ?: 'Stimma');
    
    // För felsökning
    $debug = [];
    $debug[] = "Ansluter till $host:$port med $encryption...";
    
    // Anslut till SMTP-server
    if ($encryption == 'ssl') {
        $socket = fsockopen("ssl://$host", $port, $errno, $errstr, 30);
    } else {
        $socket = fsockopen($host, $port, $errno, $errstr, 30);
    }
    
    if (!$socket) {
        error_log("SMTP-fel: $errstr ($errno)");
        return false;
    }
    
    // Läs serverns hälsning
    $response = fgets($socket, 515);
    $debug[] = "SERVER: $response";
    
    if (substr($response, 0, 3) != '220') {
        error_log("SMTP-fel: Ogiltig hälsning: $response");
        fclose($socket);
        return false;
    }
    
    // Skicka EHLO
    fputs($socket, "EHLO " . parse_url(SITE_URL, PHP_URL_HOST) . "\r\n");
    $response = fgets($socket, 515);
    $debug[] = "SERVER: $response";
    
    // Läs alla serveralternativ
    while (substr($response, 3, 1) == '-') {
        $response = fgets($socket, 515);
        $debug[] = "SERVER: $response";
    }
    
    // Logga in om användarnamn och lösenord anges
    if (!empty($username) && !empty($password)) {
        // AUTH LOGIN
        fputs($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket, 515);
        $debug[] = "SERVER: $response";
        
        if (substr($response, 0, 3) != '334') {
            error_log("SMTP-fel: AUTH accepterades inte: $response");
            fclose($socket);
            return false;
        }
        
        // Skicka användarnamn (base64-kodat)
        fputs($socket, base64_encode($username) . "\r\n");
        $response = fgets($socket, 515);
        $debug[] = "SERVER: $response";
        
        if (substr($response, 0, 3) != '334') {
            error_log("SMTP-fel: Användarnamn accepterades inte: $response");
            fclose($socket);
            return false;
        }
        
        // Skicka lösenord (base64-kodat)
        $encodedPassword = base64_encode($password);
        $debug[] = "Skickar lösenord (base64): [längd: " . strlen($encodedPassword) . "]";
        fputs($socket, $encodedPassword . "\r\n");
        $response = fgets($socket, 515);
        $debug[] = "SERVER: $response";
        
        if (substr($response, 0, 3) != '235') {
            error_log("SMTP-fel: Autentisering misslyckades: $response");
            fclose($socket);
            return false;
        }
    }
    
    // FROM
    fputs($socket, "MAIL FROM:<$from>\r\n");
    $response = fgets($socket, 515);
    $debug[] = "SERVER: $response";
    
    if (substr($response, 0, 3) != '250') {
        error_log("SMTP-fel: FROM accepterades inte: $response");
        fclose($socket);
        return false;
    }
    
    // TO
    fputs($socket, "RCPT TO:<$to>\r\n");
    $response = fgets($socket, 515);
    $debug[] = "SERVER: $response";
    
    if (substr($response, 0, 3) != '250') {
        error_log("SMTP-fel: TO accepterades inte: $response");
        fclose($socket);
        return false;
    }
    
    // DATA
    fputs($socket, "DATA\r\n");
    $response = fgets($socket, 515);
    $debug[] = "SERVER: $response";
    
    if (substr($response, 0, 3) != '354') {
        error_log("SMTP-fel: DATA accepterades inte: $response");
        fclose($socket);
        return false;
    }
    
    // Förbered rubriker
    $headers = "From: $fromName <$from>\r\n";
    $headers .= "To: $to\r\n";
    $headers .= "Subject: $subject\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "X-Mailer: Stimma Mailer\r\n";
    $headers .= "\r\n";
    
    // Skicka e-postinnehåll
    fputs($socket, $headers . $message . "\r\n.\r\n");
    $response = fgets($socket, 515);
    $debug[] = "SERVER: $response";
    
    if (substr($response, 0, 3) != '250') {
        error_log("SMTP-fel: Meddelandet accepterades inte: $response");
        fclose($socket);
        return false;
    }
    
    // QUIT
    fputs($socket, "QUIT\r\n");
    $response = fgets($socket, 515);
    $debug[] = "SERVER: $response";
    
    // Stäng anslutningen
    fclose($socket);
    
    // Logga felsökningsinformation
    //error_log("SMTP Debug: " . implode(" | ", $debug));
    
    return true;
}
