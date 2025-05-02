<?php
require_once '../include/config.php';
require_once '../include/database.php';
require_once '../../include/functions.php';
require_once '../../include/auth.php';

header('Content-Type: application/json');

// Kontrollera om användaren är inloggad
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Du måste vara inloggad för att utföra denna åtgärd.']);
    exit;
}

// Validera CSRF-token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Ogiltig CSRF-token.']);
    exit;
}

// Validera input
if (!isset($_POST['course_id']) || !isset($_POST['email'])) {
    echo json_encode(['success' => false, 'message' => 'Saknad information.']);
    exit;
}

$courseId = (int)$_POST['course_id'];
$email = $_POST['email'];
$userEmail = $_SESSION['user_email'];

// Kontrollera om användaren har behörighet att ta bort redaktörer
$isAdmin = isAdmin($userEmail);
if (!$isAdmin) {
    // Kontrollera om användaren är redaktör för kursen
    $isEditor = queryOne("SELECT 1 FROM " . DB_DATABASE . ".course_editors WHERE course_id = ? AND email = ?", [$courseId, $userEmail]);
    if (!$isEditor) {
        echo json_encode(['success' => false, 'message' => 'Du har inte behörighet att ta bort redaktörer från denna kurs.']);
        exit;
    }
}

try {
    // Ta bort redaktören
    execute("DELETE FROM " . DB_DATABASE . ".course_editors WHERE course_id = ? AND email = ?", [$courseId, $email]);
    
    // Logga aktiviteten
    logActivity($userEmail, "Removed editor {$email} from course {$courseId}");
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("Error removing course editor: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ett fel uppstod vid borttagning av redaktör.']);
} 