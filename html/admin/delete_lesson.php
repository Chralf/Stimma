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

    // Kontrollera om det finns fler lektioner kvar i kursen
    $remainingLessons = queryOne("SELECT COUNT(*) as count FROM " . DB_DATABASE . ".lessons WHERE course_id = ?", [$lesson['course_id']]);
    
    if ($remainingLessons['count'] > 0) {
        // Om det finns fler lektioner, stanna på lessons.php
        header('Location: lessons.php?course_id=' . $lesson['course_id']);
    } else {
        // Om det inte finns fler lektioner, gå till courses.php
        header('Location: courses.php');
    }
    exit;
} catch (Exception $e) {
    $_SESSION['message'] = 'Ett fel uppstod: ' . $e->getMessage();
    $_SESSION['message_type'] = 'danger';
    header('Location: lessons.php?course_id=' . $lesson['course_id']);
    exit;
}
