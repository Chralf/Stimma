<?php
/**
 * Stimma - Learn in small steps
 * Copyright (C) 2025 Christian Alfredsson
 * 
 * This program is free software; licensed under GPL v2.
 * See LICENSE and LICENSE-AND-TRADEMARK.md for details.
 * 
 * The name "Stimma" is a trademark and subject to restrictions.
 */

/**
 * AI Chat Handler
 * 
 * This file handles AI chat functionality including:
 * - Chat session management
 * - Message history tracking
 * - AI response generation
 * - Chat statistics and analytics
 */

// Include required files
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

/**
 * Get or create a chat session
 * 
 * Retrieves an existing chat session or creates a new one if none exists.
 * Each session is associated with a user and has a unique identifier.
 * 
 * @param int $userId The ID of the user requesting the chat session
 * @return array The chat session data
 */
function getChatSession($userId) {
    // Check if user has an active session
    $session = queryOne("SELECT * FROM " . DB_DATABASE . ".chat_sessions 
                        WHERE user_id = ? AND ended_at IS NULL 
                        ORDER BY created_at DESC LIMIT 1", [$userId]);
    
    if ($session) {
        return $session;
    }
    
    // Create new session if none exists
    $sessionId = execute("INSERT INTO " . DB_DATABASE . ".chat_sessions 
                         (user_id, created_at) VALUES (?, NOW())", [$userId]);
    
    return [
        'id' => $sessionId,
        'user_id' => $userId,
        'created_at' => date('Y-m-d H:i:s')
    ];
}

/**
 * Save a chat message
 * 
 * Stores a message in the chat history with its role (user or assistant)
 * and associated metadata.
 * 
 * @param int $sessionId The ID of the chat session
 * @param string $role The role of the message sender (user/assistant)
 * @param string $content The message content
 * @param array $metadata Additional message metadata
 * @return int The ID of the saved message
 */
function saveChatMessage($sessionId, $role, $content, $metadata = []) {
    return execute("INSERT INTO " . DB_DATABASE . ".chat_messages 
                   (session_id, role, content, metadata, created_at) 
                   VALUES (?, ?, ?, ?, NOW())", 
                   [$sessionId, $role, $content, json_encode($metadata)]);
}

/**
 * Get chat message history
 * 
 * Retrieves the message history for a specific chat session,
 * optionally limited to a maximum number of messages.
 * 
 * @param int $sessionId The ID of the chat session
 * @param int $limit Maximum number of messages to retrieve
 * @return array List of chat messages
 */
function getChatHistory($sessionId, $limit = 10) {
    return queryAll("SELECT * FROM " . DB_DATABASE . ".chat_messages 
                    WHERE session_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT ?", [$sessionId, $limit]);
}

/**
 * Generate AI response
 * 
 * Processes a user message and generates an AI response using
 * the configured AI model and parameters.
 * 
 * @param string $userMessage The user's input message
 * @param array $history Previous chat messages for context
 * @return array The AI's response and metadata
 */
function generateAIResponse($userMessage, $history = []) {
    // Prepare messages array for AI model
    $messages = [];
    
    // Add system message with instructions
    $messages[] = [
        'role' => 'system',
        'content' => "You are a helpful AI assistant. Provide clear, concise responses."
    ];
    
    // Add chat history if available
    foreach ($history as $message) {
        $messages[] = [
            'role' => $message['role'],
            'content' => $message['content']
        ];
    }
    
    // Add current user message
    $messages[] = [
        'role' => 'user',
        'content' => $userMessage
    ];
    
    // Get AI configuration from environment
    $model = getenv('AI_MODEL') ?: 'gpt-3.5-turbo';
    $temperature = (float)(getenv('AI_TEMPERATURE') ?: 0.7);
    $maxTokens = (int)(getenv('AI_MAX_TOKENS') ?: 1000);
    
    // Call AI API
    $response = callAIAPI($model, $messages, [
        'temperature' => $temperature,
        'max_tokens' => $maxTokens
    ]);
    
    return [
        'content' => $response['choices'][0]['message']['content'],
        'metadata' => [
            'model' => $model,
            'usage' => $response['usage']
        ]
    ];
}

/**
 * End chat session
 * 
 * Marks a chat session as ended and records the end time.
 * 
 * @param int $sessionId The ID of the chat session to end
 * @return bool True if successful, false otherwise
 */
function endChatSession($sessionId) {
    return execute("UPDATE " . DB_DATABASE . ".chat_sessions 
                   SET ended_at = NOW() 
                   WHERE id = ?", [$sessionId]);
}

/**
 * Get chat statistics
 * 
 * Retrieves statistics about chat usage including:
 * - Total messages
 * - Average response time
 * - Most common topics
 * 
 * @param int $userId Optional user ID to filter statistics
 * @return array Chat statistics
 */
function getChatStatistics($userId = null) {
    $params = [];
    $where = '';
    
    if ($userId) {
        $where = "WHERE user_id = ?";
        $params[] = $userId;
    }
    
    return queryOne("SELECT 
        COUNT(*) as total_messages,
        AVG(TIMESTAMPDIFF(SECOND, created_at, updated_at)) as avg_response_time
        FROM " . DB_DATABASE . ".chat_messages 
        $where", $params);
} 