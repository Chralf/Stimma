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

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Kontrollera CSRF-token
if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['message'] = 'Ogiltig CSRF-token.';
    $_SESSION['message_type'] = 'danger';
    header('Location: index.php');
    exit;
}

if (isset($_GET['id'])) {
    try {
        // Hämta modulinformation för loggning
        $module = queryOne("SELECT * FROM modules WHERE id = ?", [$_GET['id']]);

        // Delete the module
        execute("DELETE FROM modules WHERE id = ?", [$_GET['id']]);

        // Logga borttagningen
        if ($module) {
            logActivity($_SESSION['user_email'], "Raderade modulen '" . $module['title'] . "' (ID: " . $_GET['id'] . ")");
        }

        $_SESSION['message'] = 'Modulen har tagits bort';
        $_SESSION['message_type'] = 'success';
    } catch (Exception $e) {
        $_SESSION['message'] = 'Ett fel uppstod: ' . $e->getMessage();
        $_SESSION['message_type'] = 'danger';
    }
}

header('Location: index.php');
exit;
