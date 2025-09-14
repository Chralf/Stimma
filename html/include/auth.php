<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';
require_once 'mail.php';

/**
 * Kontrollera om användaren är inloggad
 * 
 * @return bool True om användaren är inloggad, false annars
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Skicka inloggningstoken till användarens e-post
 * 
 * @param string $email Användarens e-post
 * @return bool True om det lyckades, false vid fel
 */
function sendLoginToken($email) {
    // Generera en unik token
    $token = bin2hex(random_bytes(32));
    
    // Hämta inloggningstokenexpirering från konfiguration eller använd standardvärde (15 minuter)
    $tokenExpiryMinutes = (int)getenv('AUTH_TOKEN_EXPIRY_MINUTES') ?: 15;
    
    // Uppdatera användaren med ny token
    $expires = date('Y-m-d H:i:s', strtotime("+{$tokenExpiryMinutes} minutes"));
    execute("UPDATE " . DB_DATABASE . ".users 
             SET verification_token = ?, verified_at = NULL 
             WHERE email = ?", 
             [$token, $email]);
    
    // Skapa inloggningslänk med fullständig URL och e-post
    $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
    $basePath = $scriptPath === '/' ? '' : $scriptPath;
    $loginUrl = 'https://' . $_SERVER['HTTP_HOST'] . $basePath . '/verify.php?token=' . $token . '&email=' . urlencode($email);
        
    // Hämta systemnamn från .env
    $systemName = trim(getenv('SYSTEM_NAME'), '"\'') ?: 'AI-kurser';
    
    // Förbered e-post med SMTP-funktionen
    $subject = mb_encode_mimeheader("Inloggningslänk till " . $systemName, 'UTF-8', 'Q');
    
    $htmlMessage = "
    <!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">
    <html xmlns=\"http://www.w3.org/1999/xhtml\">
    <head>
        <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />
        <title>Inloggningslänk</title>
    </head>
    <body style=\"font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 0;\">
        <table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\" style=\"margin: 0; padding: 0;\">
            <tr>
                <td style=\"padding: 20px;\">
                    <h2 style=\"font-family: Arial, sans-serif; color: #000000;\">Inloggningslänk till " . $systemName . "</h2>
                    <p style=\"font-family: Arial, sans-serif; color: #000000;\">Klicka på knappen nedan för att logga in:</p>
                    
                    <!--[if mso]>
                    <v:roundrect xmlns:v=\"urn:schemas-microsoft-com:vml\" xmlns:w=\"urn:schemas-microsoft-com:office:word\" href='" . $loginUrl . "' style=\"height:40px;v-text-anchor:middle;width:120px;\" arcsize=\"10%\" stroke=\"f\" fillcolor=\"#0d6efd\">
                    <w:anchorlock/>
                    <center>
                    <![endif]-->
                    <a href='" . $loginUrl . "' style=\"background-color: #0d6efd; border-radius: 5px; color: #ffffff; display: inline-block; font-family: Arial, sans-serif; font-size: 16px; line-height: 40px; text-align: center; text-decoration: none; width: 120px; -webkit-text-size-adjust: none;\">Logga in</a>
                    <!--[if mso]>
                    </center>
                    </v:roundrect>
                    <![endif]-->
                    
                    <p style=\"font-family: Arial, sans-serif; color: #000000; margin-top: 20px;\">Om knappen inte fungerar, kopiera denna länk och klistra in i din webbläsare:</p>
                    <p style=\"font-family: Arial, sans-serif; color: #000000;\">" . $loginUrl . "</p>
                    <p style=\"font-family: Arial, sans-serif; color: #000000;\">Länken är giltig i {$tokenExpiryMinutes} minuter.</p>
                    <p style=\"font-family: Arial, sans-serif; color: #000000;\">Om du inte har begärt denna länk kan du ignorera detta meddelande.</p>
                </td>
            </tr>
        </table>
    </body>
    </html>
    ";
    
    // Använd SMTP-funktionen från mail.php
    $mailFrom = getenv('MAIL_FROM_ADDRESS') ?: 'noreply@tropheus.se';
    $mailFromName = trim(getenv('MAIL_FROM_NAME'), '"\'') ?: 'AI-kurser';
    
    // Använd sendSmtpMail från mail.php
    $mailSent = sendSmtpMail($email, $subject, $htmlMessage, $mailFrom, $mailFromName);
    
    // Logga specifikt för inloggningstoken
    if ($mailSent) {
        logActivity($email, "Inloggningstoken skickat framgångsrikt", [
            'action' => 'login_token_sent',
            'token_expiry_minutes' => $tokenExpiryMinutes,
            'email' => $email
        ]);
    } else {
        logActivity($email, "Inloggningstoken misslyckades att skickas", [
            'action' => 'login_token_failed',
            'email' => $email
        ]);
    }
    
    return $mailSent;
}

/**
 * Verifiera inloggningstoken
 * 
 * @param string $email Användarens e-post
 * @param string $token Autentiseringstoken
 * @return array|false Användaruppgifter om token är giltig, false annars
 */
function verifyLoginToken($email, $token) {
    // Hämta inloggningstokenexpirering från konfiguration eller använd standardvärde (15 minuter)
    $tokenExpiryMinutes = (int)getenv('AUTH_TOKEN_EXPIRY_MINUTES') ?: 15;
    
    // Väljer användare där:
    // 1. E-posten matchar
    // 2. Verifieringstoken matchar
    // 3. Token har uppdaterats inom expireringstiden
    $sql = "SELECT * FROM " . DB_DATABASE . ".users 
            WHERE email = ? 
            AND verification_token = ?";
    
    $user = queryOne($sql, [$email, $token]);
    
    if (!$user) {
        // Logga misslyckad tokenverifiering
        logActivity($email, "Misslyckad inloggning: ogiltig token");
        return false;
    }
    
    // Logga lyckad tokenverifiering
    logActivity($email, "Lyckad inloggning med token");
    
    return $user;
}

/**
 * Skapa en inloggningssession för användaren
 * 
 * @param array $user Användaruppgifter från databasen
 * @return void
 */
function createLoginSession($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    
    // Check if user is admin or editor, and set admin_logged_in flag
    if (isset($user['is_admin']) && $user['is_admin'] == 1 || isset($user['is_editor']) && $user['is_editor'] == 1) {
        $_SESSION['admin_logged_in'] = true;
    }
}

/**
 * Logga ut användaren
 */
function logout() {
    // Spara e-post för loggning innan sessionen tas bort
    $email = $_SESSION['user_email'] ?? 'okänd användare';
    
    // Rensa sessionsvariabler och förstör sessionen
    unset($_SESSION['user_id']);
    unset($_SESSION['user_email']);
    session_destroy();
    
    redirect('index.php');
}

/**
 * Kontrollerar om en användare är admin
 * @param string $email Användarens e-postadress
 * @return bool True om användaren är admin, annars false
 */
function isAdmin($email) {
    $user = queryOne("SELECT is_admin FROM " . DB_DATABASE . ".users WHERE email = ?", [$email]);
    return $user && $user['is_admin'] == 1;
}
