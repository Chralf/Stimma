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
 * Lesson page handler
 * 
 * This file handles the display and interaction with individual lessons in the system.
 * It includes:
 * - Lesson content display
 * - Quiz functionality
 * - Progress tracking
 * - Navigation between lessons
 * - Admin controls
 */

// Include required configuration and function files
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/database.php';
require_once __DIR__ . '/include/functions.php';
require_once __DIR__ . '/include/auth.php';

// Get system name from environment variable with fallback
$systemName = trim(getenv('SYSTEM_NAME'), '"\'') ?: 'AI-kurser';

// Check if user is logged in, redirect if not
if (!isLoggedIn()) {
    $_SESSION['flash_message'] = 'Du måste vara inloggad för att se denna sida.';
    $_SESSION['flash_type'] = 'warning';
    redirect('index.php');
    exit;
}

/**
 * Helper function to check if the current request is an AJAX request
 * @return bool True if the request is AJAX, false otherwise
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

// Get lesson ID from URL parameter
$lessonId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get current user's ID from session
$userId = $_SESSION['user_id'];

// Fetch lesson information including course details
$lesson = queryOne("
    SELECT l.*, c.title as course_title, c.status as course_status
    FROM " . DB_DATABASE . ".lessons l
    JOIN " . DB_DATABASE . ".courses c ON l.course_id = c.id
    WHERE l.id = ?
", [$lessonId]);

// Redirect if lesson doesn't exist
if (!$lesson) {
    $_SESSION['flash_message'] = 'Lektionen kunde inte hittas.';
    $_SESSION['flash_type'] = 'danger';
    redirect('index.php');
    exit;
}

// Get user's progress for this lesson
$progress = queryOne("
    SELECT * FROM " . DB_DATABASE . ".progress 
    WHERE user_id = ? AND lesson_id = ?
", [$userId, $lessonId]);

// Check if lesson is completed
$isCompleted = $progress && $progress['status'] === 'completed';

// Handle form submission for quiz answers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['answer'])) {
    // Validate CSRF token for security
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        if (isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Ogiltig förfrågan. Vänligen försök igen.']);
            exit;
        }
        $_SESSION['flash_message'] = 'Ogiltig förfrågan. Vänligen försök igen.';
        $_SESSION['flash_type'] = 'danger';
        redirect("lesson.php?id=$lessonId");
        exit;
    }
    
    // Get and validate user's answer
    $userAnswer = (int)$_POST['answer'];
    $correctAnswer = (int)$lesson['quiz_correct_answer'];
    
    // Check if answer is correct
    $isCorrect = ($userAnswer === $correctAnswer);
    
    // Check if all previous lessons in the course are completed
    $previousLessons = query("SELECT id FROM " . DB_DATABASE . ".lessons 
                            WHERE course_id = ? 
                            AND sort_order < (SELECT sort_order FROM " . DB_DATABASE . ".lessons WHERE id = ?)
                            ORDER BY sort_order", 
                            [$lesson['course_id'], $lessonId]);
    
    $allPreviousCompleted = true;
    foreach ($previousLessons as $prevLesson) {
        $prevProgress = queryOne("SELECT status FROM " . DB_DATABASE . ".progress 
                                WHERE user_id = ? AND lesson_id = ?", 
                                [$userId, $prevLesson['id']]);
        if (!$prevProgress || $prevProgress['status'] !== 'completed') {
            $allPreviousCompleted = false;
            break;
        }
    }
    
    // Update user's progress if answer is correct and previous lessons are completed
    if ($isCorrect && $allPreviousCompleted) {
        if (!$progress) {
            // Create new progress record if it doesn't exist
            execute("
                INSERT INTO " . DB_DATABASE . ".progress 
                (user_id, lesson_id, status, score)
                VALUES (?, ?, 'completed', 1)
            ", [$userId, $lessonId]);
        } else {
            // Update existing progress record
            execute("
                UPDATE " . DB_DATABASE . ".progress 
                SET status = 'completed', 
                    score = 1
                WHERE user_id = ? AND lesson_id = ?
            ", [$userId, $lessonId]);
        }
        
        $isCompleted = true;
        
        // Get next lesson in the course
        $nextLesson = queryOne("
            SELECT l.*, c.title as course_title
            FROM " . DB_DATABASE . ".lessons l
            JOIN " . DB_DATABASE . ".courses c ON l.course_id = c.id
            WHERE l.course_id = ? AND l.sort_order > ?
            ORDER BY l.sort_order ASC
            LIMIT 1
        ", [$lesson['course_id'], $lesson['sort_order']]);
        
        // Handle AJAX response
        if (isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'nextLesson' => $nextLesson ? [
                    'id' => $nextLesson['id'],
                    'title' => $nextLesson['title']
                ] : null
            ]);
            exit;
        }
        
        $_SESSION['show_confetti'] = true;
        redirect("lesson.php?id=$lessonId");
        exit;
    } else if (!$allPreviousCompleted) {
        // Handle case where previous lessons aren't completed
        $message = 'Du måste klara alla tidigare lektioner innan du kan gå vidare.';
        if (isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $message]);
            exit;
        }
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = 'warning';
    } else if (!$isCorrect) {
        // Handle incorrect answer
        $message = 'Fel svar. Försök igen!';
        if (isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $message]);
            exit;
        }
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = 'danger';
    }
}

// Prepare quiz options from JSON data
$quizOptions = [];
if (!empty($lesson['quiz_options'])) {
    $quizOptions = json_decode($lesson['quiz_options'], true);
    if (!is_array($quizOptions)) {
        $quizOptions = [];
    }
}

// Get next lesson in the course for navigation
$nextLesson = queryOne("
    SELECT l.*, c.title as course_title
    FROM " . DB_DATABASE . ".lessons l
    JOIN " . DB_DATABASE . ".courses c ON l.course_id = c.id
    WHERE l.course_id = ? AND l.sort_order > ?
    ORDER BY l.sort_order ASC
    LIMIT 1
", [$lesson['course_id'], $lesson['sort_order']]);

// Check if current user is an admin
$user = queryOne("SELECT is_admin FROM " . DB_DATABASE . ".users WHERE id = ?", [$_SESSION['user_id']]);

// Set page title with course name
$page_title = $systemName . ' - ' . sanitize($lesson['course_title']) . ' - ' . sanitize($lesson['title']);

// Include header template
include 'include/header.php';
?>

<!-- Main content container -->
<div class="container-sm py-4">
    <div class="row">
        <!-- Left sidebar (empty on desktop) -->
        <div class="col-lg-2 d-none d-lg-block"></div>
        
        <!-- Main content area -->
        <div class="col-12 col-lg-8">
            <main>
                <!-- Lesson content card -->
                <div class="card mb-4">
                    <div class="card-body">
                        <!-- Flash message display -->
                        <?php if (isset($_SESSION['flash_message']) && $_SESSION['flash_type'] !== 'danger'): ?>
                        <div class="alert alert-<?= $_SESSION['flash_type'] ?? 'info' ?>" role="alert">
                            <?= $_SESSION['flash_message'] ?>
                        </div>
                        <?php 
                            unset($_SESSION['flash_message'], $_SESSION['flash_type']);
                        endif; ?>
                        
                        <!-- Completion status badge -->
                        <?php if ($isCompleted): ?>
                        <div class="text-end mb-3">
                            <span class="badge bg-success"><i class="bi bi-check-circle-fill me-1"></i> Avklarad</span>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Lesson title with admin edit link -->
                        <h1 class="h2 mb-3"><?= sanitize($lesson['title']) ?>
                    
                        <?php if ($user['is_admin']): ?>
                            <a href="admin/edit_lesson.php?id=<?= $lesson['id'] ?>"> <i class="bi bi-pencil-square small"></i></a>
                        <?php endif; ?>
                        </h1>
                        
                        <!-- Lesson description -->
                        <?php if (!empty($lesson['description'])): ?>
                            <div class="lead mb-4"><?= nl2br(sanitize($lesson['description'])) ?></div>
                        <?php endif; ?>
                        
                        <!-- Lesson content with optional image -->
                        <div class="row">
                            <?php if (!empty($lesson['image_url'])): ?>
                            <div class="col-md-4">
                                <div class="mb-3 mb-md-0">
                                    <img src="<?= BASE_PATH_URL . sanitize($lesson['image_url']) ?>" alt="<?= sanitize($lesson['title']) ?>" class="img-fluid rounded">
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="<?= !empty($lesson['image_url']) ? 'col-md-8' : 'col-12' ?>">
                                <div class="content">
                                    <?= $lesson['content'] ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- AI chat interface card -->
                <div class="card mb-4">
                    <div class="card-header d-flex align-items-center" id="aiChatToggle">
                        <i class="bi bi-robot me-2"></i> Fråga AI om detta ämne
                    </div>
                    <div class="card-body" id="aiChatBody">
                        <div class="mb-3" id="aiMessages">
                            <div class="alert alert-info">
                                <div>
                                    <?php if (!empty($lesson['ai_instruction'])): ?>
                                        <?= $lesson['ai_instruction'] ?>
                                    <?php else: ?>
                                        Hej! Jag är din AI-assistent. Ställ gärna frågor om "<?= sanitize($lesson['title']) ?>" så hjälper jag dig.
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="input-group">
                            <textarea id="aiInput" class="form-control" 
                                    placeholder="Skriv här för att chatta med AI..." 
                                    rows="1" 
                                    style="resize: none; overflow-y: hidden;"
                            ></textarea>
                            <button id="aiSendBtn" class="btn btn-primary">
                                <i class="bi bi-send"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Quiz-sektion -->
                <div class="card">
                    <div class="card-body">
                        <?php if (!empty($lesson['quiz_question'])): ?>
                            <div class="quiz-section mb-4">
                                <h3 class="h4 mb-3">Quiz</h3>
                                <?php if (isset($_SESSION['flash_message']) && $_SESSION['flash_type'] === 'danger'): ?>
                                <div class="alert alert-danger mb-3">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    <?= $_SESSION['flash_message'] ?>
                                </div>
                                <?php 
                                    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
                                endif; ?>
                                <div class="quiz-question mb-3">
                                    <?= $lesson['quiz_question'] ?>
                                </div>
                                <form method="post" class="quiz-form" id="quizForm">
                                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                    <div class="quiz-options">
                                        <?php
                                        $answers = [
                                            1 => $lesson['quiz_answer1'],
                                            2 => $lesson['quiz_answer2'],
                                            3 => $lesson['quiz_answer3']
                                        ];
                                        foreach ($answers as $key => $answer):
                                            if (!empty($answer)):
                                        ?>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="radio" name="answer" id="answer<?= $key ?>" value="<?= $key ?>" required>
                                            <label class="form-check-label" for="answer<?= $key ?>">
                                                <?= $answer ?>
                                            </label>
                                        </div>
                                        <?php
                                            endif;
                                        endforeach;
                                        ?>
                                    </div>
                                    <button type="submit" class="btn btn-primary mt-3">Skicka svar</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">Inget quiz tillgängligt för denna lektion.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Extra utrymme under quizrutan -->
                <div class="py-5"></div>
            </main>
        </div>
        <div class="col-lg-2 d-none d-lg-block"></div>
    </div>
</div>



<script>
// Konfigurera marked om det finns tillgängligt
if (typeof marked !== 'undefined') {
    marked.setOptions({
        breaks: true,
        gfm: true,
        headerIds: false
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Hämta alla nödvändiga element
    const aiInput = document.getElementById('aiInput');
    const aiSendBtn = document.getElementById('aiSendBtn');
    const aiMessages = document.getElementById('aiMessages');
    const aiChatToggle = document.getElementById('aiChatToggle');
    const aiChatBody = document.getElementById('aiChatBody');

    // Toggle AI chat
    aiChatToggle.addEventListener('click', function() {
        aiChatBody.classList.toggle('active');
    });

    // Funktion för att automatiskt justera höjden på textarean
    function autoResizeTextarea(element) {
        element.style.height = 'auto';
        element.style.height = (element.scrollHeight) + 'px';
    }

    // Lägg till event listeners för automatisk höjdjustering
    aiInput.addEventListener('input', function() {
        autoResizeTextarea(this);
    });

    // Återställ höjden när meddelandet skickas
    function resetTextareaHeight() {
        aiInput.style.height = 'auto';
        aiInput.rows = 1;
    }

    // Add message to chat
    function addMessage(message, isUser = false) {
        const messageDiv = document.createElement('div');
        messageDiv.className = isUser ? 'alert alert-primary mb-3' : 'alert alert-info mb-3';
        
        if (isUser && typeof marked !== 'undefined') {
            try {
                messageDiv.innerHTML = marked.parse(message);
            } catch (e) {
                console.warn('Kunde inte formatera meddelande med marked.js:', e);
                messageDiv.textContent = message;
            }
        } else {
            messageDiv.innerHTML = message;
        }
        
        aiMessages.appendChild(messageDiv);
        aiMessages.scrollTop = aiMessages.scrollHeight;
    }

    // Uppdatera sendAIMessage funktionen
    function sendAIMessage() {
        const message = aiInput.value.trim();
        if (!message) return;

        // Add user message
        addMessage(message, true);
        aiInput.value = '';
        resetTextareaHeight();  // Återställ höjden

        // Show typing indicator
        const typingIndicator = document.createElement('div');
        typingIndicator.className = 'd-flex justify-content-center mb-3';
        typingIndicator.innerHTML = `
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Laddar...</span>
            </div>
        `;
        aiMessages.appendChild(typingIndicator);

        // Send to server
        fetch('ai_chat.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                lesson_id: <?= $lessonId ?>,
                message: message,
                ai_prompt: '<?= addslashes($lesson['ai_prompt'] ?? '') ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            // Remove typing indicator
            typingIndicator.remove();
            // Add AI response
            addMessage(data.response, false);
        })
        .catch(error => {
            console.error('Error:', error);
            typingIndicator.remove();
            addMessage('Ett fel uppstod. Försök igen senare.', false);
        });
    }

    // Event listeners för att skicka meddelande
    aiSendBtn.addEventListener('click', sendAIMessage);
    aiInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendAIMessage();
        }
    });

    // Quiz form submission
    const quizForm = document.getElementById('quizForm');
    if (quizForm) {
        quizForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(quizForm);
            
            fetch('lesson.php?id=<?= $lessonId ?>', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log('Server response:', data);
                if (data.success) {
                    // Visa framgångsmeddelande
                    const quizSection = document.querySelector('.quiz-section');
                    quizSection.innerHTML = `
                        <div class="text-center">
                            <i class="bi bi-trophy-fill text-success" style="font-size: 3rem;"></i>
                            <h3 class="mt-3">Bra jobbat!</h3>
                            <p class="text-muted mb-3">Du har klarat denna lektion!</p>
                            ${data.nextLesson ? `
                                <div class="d-grid">
                                    <a href="lesson.php?id=${data.nextLesson.id}" class="btn btn-success btn-lg">
                                        <i class="bi bi-arrow-right-circle-fill me-2"></i> Fortsätt till nästa lektion: <strong>${data.nextLesson.title}</strong>
                                    </a>
                                </div>
                            ` : '<p class="text-muted">Detta var sista lektionen i denna kurs!</p>'}
                        </div>
                    `;
                    
                    // Visa konfetti vid framgång
                    setTimeout(() => {
                        try {
                            stimmaConfetti.show({
                                particleCount: 600,
                                gravity: 0.6,
                                spread: 180,
                                startY: 0.8,
                                direction: 'up',
                                colors: [
                                    '#FFC700',
                                    '#FF5252',
                                    '#3377FF',
                                    '#4CAF50',
                                    '#9C27B0',
                                    '#FF9800'
                                ]
                            });
                        } catch (e) {
                            // Tysta fel
                        }
                    }, 200);
                } else {
                    // Visa felmeddelande
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'alert alert-danger d-flex align-items-center mb-3';
                    errorDiv.innerHTML = `
                        <i class="bi bi-exclamation-circle me-2"></i>
                        <div>${data.message}</div>
                    `;
                    
                    const quizSection = document.querySelector('.quiz-section');
                    const existingError = quizSection.querySelector('.alert-danger');
                    if (existingError) {
                        existingError.remove();
                    }
                    quizSection.insertBefore(errorDiv, quizSection.querySelector('.quiz-question'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Visa ett generiskt felmeddelande om något går fel
                const errorDiv = document.createElement('div');
                errorDiv.className = 'alert alert-danger d-flex align-items-center mb-3';
                errorDiv.innerHTML = `
                    <i class="bi bi-exclamation-circle me-2"></i>
                    <div>Ett fel uppstod. Försök igen senare.</div>
                `;
                
                const quizSection = document.querySelector('.quiz-section');
                const existingError = quizSection.querySelector('.alert-danger');
                if (existingError) {
                    existingError.remove();
                }
                quizSection.insertBefore(errorDiv, quizSection.querySelector('.quiz-question'));
            });
        });
    }
});
</script>



<?php include 'include/footer.php'; ?>