<?php
/**
 * Authentication and authorization check for admin pages
 * 
 * This file should be included at the beginning of all admin PHP files
 * to ensure consistent security logic across the admin area.
 */

// Kontrollera om användaren är inloggad
if (!isLoggedIn()) {
    redirect('../index.php');
    exit;
}

// Hämta användarens roller
$user = queryOne("SELECT is_admin, is_editor FROM " . DB_DATABASE . ".users WHERE email = ?", [$_SESSION['user_email']]);
$isAdmin = $user && $user['is_admin'] == 1;
$isEditor = $user && $user['is_editor'] == 1;

// Kontrollera om användaren har admin- eller redaktörsrättigheter
if (!$isAdmin && !$isEditor) {

    redirect('../index.php');
    exit;
}
