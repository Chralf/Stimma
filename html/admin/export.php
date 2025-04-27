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

require_once '../include/config.php';
require_once '../include/database.php';
require_once '../include/functions.php';
require_once '../include/auth.php';

// Kontrollera att användaren är inloggad
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Kontrollera admin-rättigheter
$user = queryOne("SELECT is_admin FROM " . DB_DATABASE . ".users WHERE id = ?", [$_SESSION['user_id']]);
if (!$user || $user['is_admin'] !== 1) {
    header('Location: login.php');
    exit;
}

// Kontrollera att ett kurs-ID har skickats med
if (!isset($_GET['id'])) {
    $_SESSION['message'] = 'Inget kurs-ID angivet.';
    $_SESSION['message_type'] = 'danger';
    header('Location: courses.php');
    exit;
}

$courseId = (int)$_GET['id'];

// Hämta kursinformation
$course = queryOne("SELECT * FROM " . DB_DATABASE . ".courses WHERE id = ?", [$courseId]);

if (!$course) {
    $_SESSION['message'] = 'Kursen hittades inte.';
    $_SESSION['message_type'] = 'danger';
    header('Location: courses.php');
    exit;
}

// Hämta alla lektioner för kursen
$lessons = queryAll("SELECT * FROM " . DB_DATABASE . ".lessons WHERE course_id = ? ORDER BY sort_order", [$courseId]);

// Bygg exportobjektet
$exportData = [
    'course' => [
        'title' => $course['title'],
        'description' => $course['description'],
        'difficulty_level' => $course['difficulty_level'],
        'duration_minutes' => $course['duration_minutes'],
        'prerequisites' => $course['prerequisites'],
        'tags' => $course['tags'],
        'image_url' => $course['image_url'],
        'status' => 'inactive', // Sätt alltid till inaktiv vid export
        'sort_order' => $course['sort_order'],
        'featured' => $course['featured'],
        'created_at' => $course['created_at'],
        'updated_at' => $course['updated_at']
    ],
    'lessons' => []
];

// Lägg till lektionerna
foreach ($lessons as $lesson) {
    $exportData['lessons'][] = [
        'title' => $lesson['title'],
        'estimated_duration' => $lesson['estimated_duration'],
        'image_url' => $lesson['image_url'],
        'video_url' => $lesson['video_url'],
        'content' => $lesson['content'],
        'resource_links' => $lesson['resource_links'],
        'tags' => $lesson['tags'],
        'status' => $lesson['status'],
        'sort_order' => $lesson['sort_order'],
        'ai_instruction' => $lesson['ai_instruction'],
        'ai_prompt' => $lesson['ai_prompt'],
        'quiz_question' => $lesson['quiz_question'],
        'quiz_answer1' => $lesson['quiz_answer1'],
        'quiz_answer2' => $lesson['quiz_answer2'],
        'quiz_answer3' => $lesson['quiz_answer3'],
        'quiz_correct_answer' => $lesson['quiz_correct_answer'],
        'created_at' => $lesson['created_at'],
        'updated_at' => $lesson['updated_at']
    ];
}

// Konvertera till JSON med UTF-8 och särskilda tecken hanterade
$json = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// Sätt headers för nedladdning
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="course_' . $courseId . '_' . date('Y-m-d') . '.json"');

// Skriv ut JSON
echo $json;
exit; 