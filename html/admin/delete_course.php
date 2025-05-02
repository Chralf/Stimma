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
    $_SESSION['message'] = 'Du har inte behörighet att radera kurser.';
    $_SESSION['message_type'] = 'warning';
    redirect('../index.php');
    exit;
}

// Kontrollera CSRF-token
if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['message'] = 'Ogiltig CSRF-token.';
    $_SESSION['message_type'] = 'danger';
    header('Location: courses.php');
    exit;
}

// Kontrollera om ID finns
if (!isset($_GET['id'])) {
    $_SESSION['message'] = 'Ingen kurs specificerad.';
    $_SESSION['message_type'] = 'danger';
    header('Location: courses.php');
    exit;
}

$courseId = (int)$_GET['id'];

// Hämta kursinformation
$course = queryOne("SELECT * FROM " . DB_DATABASE . ".courses WHERE id = ?", [$courseId]);

if (!$course) {
    $_SESSION['message'] = 'Kursen kunde inte hittas.';
    $_SESSION['message_type'] = 'danger';
    header('Location: courses.php');
    exit;
}

// Kontrollera om kursen har lektioner
$lessons = query("SELECT COUNT(*) as count FROM " . DB_DATABASE . ".lessons WHERE course_id = ?", [$courseId]);
$lessonCount = $lessons[0]['count'];

if ($lessonCount > 0) {
    $_SESSION['message'] = 'Kursen kan inte raderas eftersom den innehåller lektioner. Ta bort alla lektioner först.';
    $_SESSION['message_type'] = 'warning';
    header('Location: courses.php');
    exit;
}

// Kontrollera om användaren har behörighet att radera kursen
// Om användaren är admin har de behörighet för alla kurser
// Om användaren är redaktör måste vi kontrollera om de har behörighet för just denna kurs
if (!$isAdmin) {
    // Kontrollera om användaren är redaktör för denna specifika kurs
    $isSpecificEditor = queryOne("SELECT 1 FROM " . DB_DATABASE . ".course_editors WHERE course_id = ? AND email = ?", [$courseId, $_SESSION['user_email']]);
    if (!$isSpecificEditor) {
        $_SESSION['message'] = 'Du har inte behörighet att radera denna kurs.';
        $_SESSION['message_type'] = 'danger';
        header('Location: courses.php');
        exit;
    }
}

// Radera kursen
try {
    execute("DELETE FROM " . DB_DATABASE . ".courses WHERE id = ?", [$courseId]);
    $_SESSION['message'] = 'Kursen har raderats.';
    $_SESSION['message_type'] = 'success';
} catch (Exception $e) {
    $_SESSION['message'] = 'Ett fel uppstod när kursen skulle raderas.';
    $_SESSION['message_type'] = 'danger';
}

// Omdirigera tillbaka till kurslistan
header('Location: courses.php');
exit;
?>
