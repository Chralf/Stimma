<?php
/**
 * Stimma - Lär dig i små steg
 * Copyright (C) 2025 Christian Alfredsson
 * 
 * This program is free software; licensed under GPL v2.
 * See LICENSE and LICENSE-AND-TRADEMARK.md for details.
 * 
 * The name "Stimma" is a trademark and subject to restrictions.
 */
?>

<?php
require_once '../include/config.php';
require_once '../include/database.php';
require_once '../include/functions.php';
require_once '../include/auth.php';

// Om användaren redan är inloggad som admin, omdirigera till dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

// Kontrollera om användaren är inloggad
if (isLoggedIn()) {
    // Kontrollera om användaren har admin-behörighet
    $user = queryOne("SELECT is_admin FROM " . DB_DATABASE . ".users WHERE id = ?", [$_SESSION['user_id']]);
    
    if ($user && $user['is_admin']) {
        // Sätt admin-session och omdirigera till dashboard
        $_SESSION['admin_logged_in'] = true;
        
        // Logga inloggningen
        logActivity($_SESSION['user_email'], "Loggade in i admin-panelen");
        
        header('Location: index.php');
        exit;
    } else {
        // Användaren är inloggad men har inte admin-behörighet
        $_SESSION['message'] = 'Du har inte behörighet att komma åt admin-sektionen.';
        $_SESSION['message_type'] = 'danger';
        header('Location: ../index.php');
        exit;
    }
} else {
    // Användaren är inte inloggad, omdirigera till huvudinloggningen
    $_SESSION['message'] = 'Du måste logga in för att komma åt admin-sektionen.';
    $_SESSION['message_type'] = 'warning';
    header('Location: ../index.php');
    exit;
}
?>