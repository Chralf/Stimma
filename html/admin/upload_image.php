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

// Kontrollera om en fil har laddats upp
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Ingen fil uppladdad']);
    exit;
}

$file = $_FILES['image'];
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
$maxFileSize = 5 * 1024 * 1024; // 5MB

// Validera filtyp
if (!in_array($file['type'], $allowedTypes)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Ogiltig filtyp. Endast JPG, PNG och GIF är tillåtna.']);
    exit;
}

// Validera filstorlek
if ($file['size'] > $maxFileSize) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Filen är för stor. Max storlek är 5MB.']);
    exit;
}

// Skapa mapp för uppladdningar om den inte finns
$uploadDir = '../upload/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generera ett unikt filnamn
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid('lesson_') . '.' . $extension;
$targetPath = $uploadDir . $filename;

// Flytta den uppladdade filen
if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    $relativeUrl = '/upload/' . $filename;
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'url' => $relativeUrl]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Kunde inte spara filen']);
} 