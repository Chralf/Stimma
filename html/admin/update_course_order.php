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
session_start();
require_once '../include/config.php';
require_once '../include/functions.php';

// Kontrollera om användaren är inloggad och är admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
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
