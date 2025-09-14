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

require_once '../include/database.php';

// Kontrollera att data har skickats
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['lessons']) || !isset($_POST['course_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

try {
    // Avkoda JSON-data
    $lessons = json_decode($_POST['lessons'], true);
    $courseId = (int)$_POST['course_id'];
    
    if (!is_array($lessons)) {
        throw new Exception('Invalid data format');
    }
    
    // Kontrollera att kursen finns
    $course = queryOne("SELECT * FROM " . DB_DATABASE . ".courses WHERE id = ?", [$courseId]);
    if (!$course) {
        throw new Exception('Course not found');
    }
    
    // Börja en transaktion
    execute("START TRANSACTION");
    
    // Uppdatera ordningen för varje lektion
    foreach ($lessons as $lesson) {
        if (!isset($lesson['id']) || !isset($lesson['order'])) {
            continue;
        }
        
        execute("UPDATE " . DB_DATABASE . ".lessons SET sort_order = ? WHERE id = ? AND course_id = ?", 
                [(int)$lesson['order'], (int)$lesson['id'], $courseId]);
    }
    
    // Commit transaktionen
    execute("COMMIT");
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // Rollback vid fel
    execute("ROLLBACK");
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
