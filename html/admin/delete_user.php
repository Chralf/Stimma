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

// Kontrollera om användaren är inloggad och är admin
if (!isLoggedIn()) {
    $_SESSION['message'] = 'Du måste vara inloggad för att se denna sida.';
    $_SESSION['message_type'] = 'warning';
    header('Location: ../index.php');
    exit;
}

// Kontrollera CSRF-token
if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['message'] = 'Ogiltig CSRF-token.';
    $_SESSION['message_type'] = 'danger';
    header('Location: users.php');
    exit;
}

// Kontrollera att ID finns
if (!isset($_GET['id'])) {
    $_SESSION['message'] = 'Inget ID angivet.';
    $_SESSION['message_type'] = 'danger';
    header('Location: users.php');
    exit;
}

$id = (int)$_GET['id'];

// Hämta användarinformation för loggning
$user = queryOne("SELECT * FROM " . DB_DATABASE . ".users WHERE id = ?", [$id]);

if (!$user) {
    $_SESSION['message'] = 'Användaren kunde inte hittas.';
    $_SESSION['message_type'] = 'danger';
    header('Location: users.php');
    exit;
}

try {
    // Radera användaren
    execute("DELETE FROM " . DB_DATABASE . ".users WHERE id = ?", [$id]);
    
    // Logga borttagningen
    logActivity($_SESSION['user_email'], "Raderade användaren '" . $user['email'] . "' (ID: " . $id . ")");
    
    $_SESSION['message'] = 'Användaren har raderats.';
    $_SESSION['message_type'] = 'success';
} catch (Exception $e) {
    $_SESSION['message'] = 'Ett fel uppstod när användaren skulle raderas.';
    $_SESSION['message_type'] = 'danger';
}

// Omdirigera tillbaka till användarlistan
header('Location: users.php');
exit;
?> 