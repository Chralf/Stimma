<?php
require_once '../include/config.php';
require_once '../include/database.php';
require_once '../../include/functions.php';
require_once '../../include/auth.php';

// Aktivera felrapportering för felsökning
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include AJAX-compatible authentication check
require_once '../include/ajax_auth_check.php';

// Kontrollera CSRF-token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Ogiltig CSRF-token.']);
    exit;
}

// Hämta användarens behörigheter
$userEmail = $_SESSION['user_email'];
$isAdmin = isAdmin($userEmail);
error_log("User $userEmail is " . ($isAdmin ? "admin" : "not admin"));

// Validera input
if (!isset($_POST['course_id']) || !isset($_POST['email'])) {
    echo json_encode(['success' => false, 'message' => 'Ogiltig förfrågan.']);
    exit;
}

$courseId = (int)$_POST['course_id'];
$newEditorEmail = trim($_POST['email']);

// Kontrollera om användaren har behörighet att lägga till redaktörer
if (!$isAdmin) {
    $isEditor = queryOne("SELECT 1 FROM " . DB_DATABASE . ".course_editors WHERE course_id = ? AND email = ?", [$courseId, $userEmail]);
    if (!$isEditor) {
        echo json_encode(['success' => false, 'message' => 'Du har inte behörighet att lägga till redaktörer för denna kurs.']);
        exit;
    }
}

// Kontrollera om kursen finns
$course = queryOne("SELECT 1 FROM " . DB_DATABASE . ".courses WHERE id = ?", [$courseId]);
if (!$course) {
    echo json_encode(['success' => false, 'message' => 'Kursen hittades inte.']);
    exit;
}

// Kontrollera om e-postadressen redan är redaktör
$existingEditor = queryOne("SELECT 1 FROM " . DB_DATABASE . ".course_editors WHERE course_id = ? AND email = ?", [$courseId, $newEditorEmail]);
if ($existingEditor) {
    echo json_encode(['success' => false, 'message' => 'Denna e-postadress är redan redaktör för kursen.']);
    exit;
}

// Kontrollera om användaren finns
$userExists = queryOne("SELECT 1 FROM " . DB_DATABASE . ".users WHERE email = ?", [$newEditorEmail]);
if (!$userExists) {
    echo json_encode(['success' => false, 'message' => 'Användaren måste skapas först.']);
    exit;
}

// Lägg till redaktör
try {
    $sql = "INSERT INTO " . DB_DATABASE . ".course_editors (course_id, email, created_by) VALUES (?, ?, ?)";
    
    $result = execute($sql, [$courseId, $newEditorEmail, $userEmail]);
    
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Kunde inte lägga till redaktören.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Ett fel uppstod när redaktören skulle läggas till.']);
} 