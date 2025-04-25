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
 * Login handler
 * 
 * This file handles the sending of login links via email.
 * It uses PHPMailer to send secure login tokens to users.
 */

// Send login link via email
$mail = new PHPMailer(true);
try {
    // Attempt to send login email with token
    sendLoginEmail($mail, $email, $token, $host);
    $success = "En inloggningslänk har skickats till din e-postadress.";
    
} catch (Exception $e) {
    // Handle email sending failure
    $error = "Det gick inte att skicka e-postmeddelandet: " . $mail->ErrorInfo;
} 