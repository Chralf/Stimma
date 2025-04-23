<?php

// Skicka inloggningslänk
$mail = new PHPMailer(true);
try {
    sendLoginEmail($mail, $email, $token, $host);
    $success = "En inloggningslänk har skickats till din e-postadress.";
    
} catch (Exception $e) {
    $error = "Det gick inte att skicka e-postmeddelandet: " . $mail->ErrorInfo;
} 