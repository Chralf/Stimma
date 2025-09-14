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

// Kontrollera om användaren är inloggad
if (!isLoggedIn()) {
    redirect('../index.php');
    exit;
}

// Kontrollera om användaren har admin- eller redaktörsrättigheter
$user = queryOne("SELECT is_admin, is_editor FROM " . DB_DATABASE . ".users WHERE email = ?", [$_SESSION['user_email']]);
$isAdmin = $user && $user['is_admin'] == 1;
$isEditor = $user && $user['is_editor'] == 1;

if (!$isAdmin && !$isEditor) {
    $_SESSION['message'] = 'Du har inte behörighet att radera lektioner.';
    $_SESSION['message_type'] = 'warning';
    redirect('../index.php');
    exit;
}

// Kontrollera CSRF-token
if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['message'] = 'Ogiltig CSRF-token.';
    $_SESSION['message_type'] = 'danger';
    header('Location: lessons.php');
    exit;
}

// Kontrollera att ID finns
if (!isset($_GET['id'])) {
    $_SESSION['message'] = 'Inget ID angivet.';
    $_SESSION['message_type'] = 'danger';
    header('Location: lessons.php');
    exit;
}

// Hämta lektionsinformation för loggning och behörighetskontroll
$lesson = queryOne("SELECT * FROM " . DB_DATABASE . ".lessons WHERE id = ?", [$_GET['id']]);

// Kontrollera om lektion finns
if (!$lesson) {
    $_SESSION['message'] = 'Lektionen kunde inte hittas.';
    $_SESSION['message_type'] = 'danger';
    header('Location: lessons.php');
    exit;
}

// Kontrollera behörigheter för redaktörer (admins har redan full behörighet)
if (!$isAdmin && $isEditor) {
    // Kontrollera om redaktören har behörighet för denna kurs
    $isSpecificEditor = queryOne("SELECT 1 FROM " . DB_DATABASE . ".course_editors WHERE course_id = ? AND email = ?", 
                              [$lesson['course_id'], $_SESSION['user_email']]);
    if (!$isSpecificEditor) {
        $_SESSION['message'] = 'Du har inte behörighet att radera lektioner i denna kurs.';
        $_SESSION['message_type'] = 'danger';
        header('Location: lessons.php');
        exit;
    }
}

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
