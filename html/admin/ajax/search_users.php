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

// Validera input
if (!isset($_GET['search'])) {
    echo json_encode(['success' => false, 'message' => 'Ogiltig förfrågan.']);
    exit;
}

$search = trim($_GET['search']);

// Sök efter användare som matchar söksträngen
$users = queryAll("SELECT email, name FROM " . DB_DATABASE . ".users 
                  WHERE (email LIKE ? OR name LIKE ?) 
                  AND email != ? 
                  ORDER BY name ASC 
                  LIMIT 10", 
                  ["%$search%", "%$search%", $_SESSION['user_email']]);

// Om inga användare hittades, kontrollera om söksträngen är en e-postadress
if (empty($users) && filter_var($search, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => true, 
        'users' => [], 
        'message' => 'Ingen användare hittades med denna e-postadress. Användaren måste skapas först.'
    ]);
} else {
    echo json_encode(['success' => true, 'users' => $users]);
} 