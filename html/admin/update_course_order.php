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

// Include AJAX-compatible authentication check
require_once 'include/ajax_auth_check.php';

// Verifiera CSRF-token
if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Kontrollera om data har skickats
if (!isset($_POST['courses'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'No data received']);
    exit;
}

try {
    $courses = json_decode($_POST['courses'], true);
    
    if (!is_array($courses)) {
        throw new Exception('Invalid data format');
    }
    
    // Uppdatera ordningen för varje kurs
    foreach ($courses as $course) {
        execute("UPDATE " . DB_DATABASE . ".courses SET sort_order = ? WHERE id = ?", 
                [$course['order'], $course['id']]);
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
