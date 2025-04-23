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

require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/database.php';
require_once __DIR__ . '/include/functions.php';
require_once __DIR__ . '/include/auth.php';

// Sätt header för JSON
header('Content-Type: application/json');

try {
    // Kontrollera om användaren är inloggad
    if (!isLoggedIn()) {
        throw new Exception('Du måste vara inloggad för att använda AI-chatten.');
    }

    // Hämta och validera inkommande data
    $data = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Ogiltig JSON-data.');
    }

    $lessonId = $data['lesson_id'] ?? 0;
    $message = $data['message'] ?? '';
    $aiPrompt = $data['ai_prompt'] ?? '';

    // Validera inkommande data
    if (!$lessonId || !$message) {
        throw new Exception('Ogiltig förfrågan. Lektion och meddelande krävs.');
    }
    
    // Validera meddelandelängd
    $maxMessageLength = (int)getenv('AI_MAX_MESSAGE_LENGTH') ?: 500;
    if (strlen($message) > $maxMessageLength) {
        throw new Exception("Meddelandet är för långt. Max {$maxMessageLength} tecken tillåtna.");
    }
    
    // Implementera rate limiting
    $userId = $_SESSION['user_id'];
    $now = time();
    $lastRequest = $_SESSION['ai_last_request'] ?? 0;
    $requestCount = $_SESSION['ai_request_count'] ?? 0;
    
    // Hämta konfigureringsvariabler
    $maxRequests = (int)getenv('AI_RATE_LIMIT_REQUESTS') ?: 10;
    $timeWindow = (int)getenv('AI_RATE_LIMIT_MINUTES') ?: 5;
    $timeWindowSeconds = $timeWindow * 60;
    
    // Begränsa till maxantal förfrågningar under tidsfönstret
    if ($now - $lastRequest < $timeWindowSeconds) {
        if ($requestCount >= $maxRequests) {
            throw new Exception("För många förfrågningar. Vänligen försök igen senare.");
        }
        $_SESSION['ai_request_count'] = $requestCount + 1;
    } else {
        // Återställ räknaren om det var mer än tidsfönstret sedan senaste frågan
        $_SESSION['ai_request_count'] = 1;
    }
    
    $_SESSION['ai_last_request'] = $now;

    // Hämta lektionsinformation
    $lesson = queryOne("SELECT * FROM " . DB_DATABASE . ".lessons WHERE id = ?", [$lessonId]);
    if (!$lesson) {
        throw new Exception('Lektionen kunde inte hittas.');
    }

    // Skapa systemprompt med lektionskontext
    $systemPrompt = "Du är en hjälpsam AI-assistent som hjälper användaren med lektionen '{$lesson['title']}'. ";
    $systemPrompt .= "Användaren är en lärare som vill lära sig mer om ämnet. ";
    $systemPrompt .= "Var vänlig och pedagogisk i dina svar. ";

    // Lägg till AI-prompt om den finns
    if (!empty($aiPrompt)) {
        $systemPrompt .= "\n\nInstruktioner för denna lektion:\n" . $aiPrompt;
    }

    // Skapa meddelanden för OpenAI
    $messages = [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $message]
    ];

    // Skicka förfrågan till OpenAI
    $response = sendOpenAIRequest($messages);

    // Lägg till debugging
    error_log("AI Response: " . print_r($response, true));

    // Formatera svaret med vår egen markdown-parser
    $formattedResponse = parseMarkdown($response);

    // Lägg till debugging
    error_log("Formatted Response: " . print_r($formattedResponse, true));

    // Returnera svaret
    echo json_encode(['response' => $formattedResponse]);

} catch (Exception $e) {
    error_log("AI Chat Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
