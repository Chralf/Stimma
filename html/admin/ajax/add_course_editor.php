<?php
require_once '../include/config.php';
require_once '../include/database.php';
require_once '../../include/functions.php';
require_once '../../include/auth.php';

header('Content-Type: application/json');

// Aktivera felrapportering för felsökning
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Kontrollera om användaren är inloggad
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Du måste vara inloggad för att utföra denna åtgärd.']);
    exit;
}

// Kontrollera CSRF-token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    error_log("CSRF token mismatch. Session token: " . $_SESSION['csrf_token'] . ", POST token: " . ($_POST['csrf_token'] ?? 'not set'));
    echo json_encode(['success' => false, 'message' => 'Ogiltig CSRF-token.']);
    exit;
}

// Hämta användarens behörigheter
$userEmail = $_SESSION['user_email'];
$isAdmin = isAdmin($userEmail);
error_log("User $userEmail is " . ($isAdmin ? "admin" : "not admin"));

// Validera input
if (!isset($_POST['course_id']) || !isset($_POST['email'])) {
    error_log("Missing parameters in add_course_editor.php. POST data: " . print_r($_POST, true));
    echo json_encode(['success' => false, 'message' => 'Ogiltig förfrågan.']);
    exit;
}

$courseId = (int)$_POST['course_id'];
$newEditorEmail = trim($_POST['email']);
error_log("Attempting to add editor: $newEditorEmail to course: $courseId");

// Kontrollera om användaren har behörighet att lägga till redaktörer
if (!$isAdmin) {
    $isEditor = queryOne("SELECT 1 FROM " . DB_DATABASE . ".course_editors WHERE course_id = ? AND email = ?", [$courseId, $userEmail]);
    error_log("User $userEmail is " . ($isEditor ? "editor" : "not editor") . " for course $courseId");
    if (!$isEditor) {
        echo json_encode(['success' => false, 'message' => 'Du har inte behörighet att lägga till redaktörer för denna kurs.']);
        exit;
    }
}

// Kontrollera om kursen finns
$course = queryOne("SELECT 1 FROM " . DB_DATABASE . ".courses WHERE id = ?", [$courseId]);
if (!$course) {
    error_log("Course $courseId not found");
    echo json_encode(['success' => false, 'message' => 'Kursen hittades inte.']);
    exit;
}

// Kontrollera om e-postadressen redan är redaktör
$existingEditor = queryOne("SELECT 1 FROM " . DB_DATABASE . ".course_editors WHERE course_id = ? AND email = ?", [$courseId, $newEditorEmail]);
if ($existingEditor) {
    error_log("User $newEditorEmail is already an editor for course $courseId");
    echo json_encode(['success' => false, 'message' => 'Denna e-postadress är redan redaktör för kursen.']);
    exit;
}

// Kontrollera om användaren finns
$userExists = queryOne("SELECT 1 FROM " . DB_DATABASE . ".users WHERE email = ?", [$newEditorEmail]);
if (!$userExists) {
    error_log("User $newEditorEmail does not exist");
    echo json_encode(['success' => false, 'message' => 'Användaren måste skapas först.']);
    exit;
}

// Lägg till redaktör
try {
    $sql = "INSERT INTO " . DB_DATABASE . ".course_editors (course_id, email, created_by) VALUES (?, ?, ?)";
    error_log("Executing SQL: $sql with params: [$courseId, $newEditorEmail, $userEmail]");
    
    $result = execute($sql, [$courseId, $newEditorEmail, $userEmail]);
    error_log("Execute result: " . ($result ? "true" : "false"));
    
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        error_log("Failed to insert into course_editors. Course ID: $courseId, Email: $newEditorEmail, Created by: $userEmail");
        echo json_encode(['success' => false, 'message' => 'Kunde inte lägga till redaktören.']);
    }
} catch (Exception $e) {
    error_log("Error adding course editor: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'Ett fel uppstod när redaktören skulle läggas till.']);
} 