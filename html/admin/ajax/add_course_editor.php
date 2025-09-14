<?php
require_once '../../include/config.php';
require_once '../../include/database.php';
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
    // Starta transaktion för att säkerställa att båda uppdateringarna genomförs eller ingen
    execute("START TRANSACTION");
    
    // Lägg till i course_editors-tabellen
    $sql1 = "INSERT INTO " . DB_DATABASE . ".course_editors (course_id, email, created_by) VALUES (?, ?, ?)";
    $result1 = execute($sql1, [$courseId, $newEditorEmail, $userEmail]);
    
    // Uppdatera is_editor i users-tabellen om den inte redan är satt till 1
    $sql2 = "UPDATE " . DB_DATABASE . ".users SET is_editor = 1 WHERE email = ? AND (is_editor = 0 OR is_editor IS NULL)";
    $result2 = execute($sql2, [$newEditorEmail]);
    
    if ($result1) {
        // Bekräfta transaktion
        execute("COMMIT");
        echo json_encode(['success' => true]);
    } else {
        // Återställ transaktion vid fel
        execute("ROLLBACK");
        echo json_encode(['success' => false, 'message' => 'Kunde inte lägga till redaktören.']);
    }
} catch (Exception $e) {
    // Återställ transaktion vid fel och visa felmeddelande
    execute("ROLLBACK");
    echo json_encode(['success' => false, 'message' => 'Ett fel uppstod när redaktören skulle läggas till.']);
} 