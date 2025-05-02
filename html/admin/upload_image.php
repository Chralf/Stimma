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

// Kontrollera CSRF-token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Kontrollera om en fil har laddats upp
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Ingen fil uppladdad']);
    exit;
}

$file = $_FILES['image'];
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
$maxFileSize = 5 * 1024 * 1024; // 5MB

// Validera filtyp (MIME)
if (!in_array($file['type'], $allowedTypes)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Ogiltig filtyp. Endast JPG, PNG och GIF är tillåtna.']);
    exit;
}

// Validera filändelse
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($extension, $allowedExtensions)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Ogiltig filändelse. Endast JPG, PNG och GIF är tillåtna.']);
    exit;
}

// Validera filstorlek
if ($file['size'] > $maxFileSize) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Filen är för stor. Max storlek är 5MB.']);
    exit;
}

// Validera att det är en riktig bild
$imageInfo = getimagesize($file['tmp_name']);
if ($imageInfo === false) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Filen är inte en giltig bild.']);
    exit;
}

// Kontrollera bildens dimensioner
$maxWidth = 1920;
$maxHeight = 1080;
if ($imageInfo[0] > $maxWidth || $imageInfo[1] > $maxHeight) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Bilden är för stor. Max dimensioner är ' . $maxWidth . 'x' . $maxHeight . ' pixlar.']);
    exit;
}

// Skapa mapp för uppladdningar om den inte finns
$uploadDir = '../upload/';
if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Kunde inte skapa uppladdningsmapp']);
        exit;
    }
}

// Generera ett säkert filnamn
$filename = bin2hex(random_bytes(16)) . '.' . $extension;
$targetPath = $uploadDir . $filename;

// Flytta den uppladdade filen
if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    // Sätt rätt filrättigheter
    chmod($targetPath, 0644);
    
    // Logga uppladdningen
    logActivity($_SESSION['user_email'], "Laddade upp bild: " . $filename);
    
    $relativeUrl = '/upload/' . $filename;
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'url' => $relativeUrl]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Kunde inte spara filen']);
} 