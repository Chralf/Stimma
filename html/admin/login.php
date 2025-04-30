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
    // Kontrollera om användaren är admin eller redaktör
    $user = queryOne("SELECT is_admin, is_editor FROM " . DB_DATABASE . ".users WHERE email = ?", [$_SESSION['user_email']]);
    if ($user && ($user['is_admin'] == 1 || $user['is_editor'] == 1)) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: index.php');
        exit;
    } else {
        // Användaren är inloggad men har inte admin- eller redaktörsbehörighet
        $_SESSION['message'] = 'Du har inte behörighet att komma åt admin-sektionen.';
        $_SESSION['message_type'] = 'warning';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $_SESSION['message'] = 'Vänligen fyll i både e-post och lösenord.';
        $_SESSION['message_type'] = 'danger';
    } else {
        $user = queryOne("SELECT id, email, password FROM " . DB_DATABASE . ".users WHERE email = ?", [$email]);
        
        if ($user && password_verify($password, $user['password'])) {
            // Sätt alla nödvändiga session-variabler
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            
            // Kontrollera om användaren är kursredaktör
            $editor = queryOne("SELECT COUNT(*) as count FROM " . DB_DATABASE . ".course_editors WHERE email = ?", [$email]);
            $isCourseEditor = $editor && $editor['count'] > 0;
            
            if ($isCourseEditor) {
                $_SESSION['admin_logged_in'] = true;
                
                // Logga inloggningen
                logActivity($user['email'], "Loggade in i admin-panelen");
                
                // Omdirigera till admin-sidan
                header('Location: courses.php');
                exit;
            } else {
                $_SESSION['message'] = 'Du har inte behörighet att komma åt admin-sektionen.';
                $_SESSION['message_type'] = 'danger';
                header('Location: ../index.php');
                exit;
            }
        } else {
            $_SESSION['message'] = 'Felaktig e-post eller lösenord.';
            $_SESSION['message_type'] = 'danger';
        }
    }
}
?>