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

// Kontrollera att ID finns
if (!isset($_GET['id'])) {
    $_SESSION['message'] = 'Inget ID angivet.';
    $_SESSION['message_type'] = 'danger';
    header('Location: lessons.php');
    exit;
}

// Hämta lektionsinformation för loggning
$lesson = queryOne("SELECT * FROM " . DB_DATABASE . ".lessons WHERE id = ?", [$_GET['id']]);

try {
    // Radera lektionen
    execute("DELETE FROM " . DB_DATABASE . ".lessons WHERE id = ?", [$_GET['id']]);
    
    // Logga borttagningen
    if ($lesson) {
        logActivity($_SESSION['user_email'], "Raderade lektionen '" . $lesson['title'] . "' (ID: " . $_GET['id'] . ")");
    }
    
    $_SESSION['message'] = 'Lektionen har raderats.';
    $_SESSION['message_type'] = 'success';
} catch (Exception $e) {
    $_SESSION['message'] = 'Ett fel uppstod: ' . $e->getMessage();
    $_SESSION['message_type'] = 'danger';
}

header('Location: lessons.php');
exit;
