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
 * Main index page
 * 
 * This file handles:
 * - User authentication and login
 * - New user registration
 * - Course and lesson progress tracking
 * - Display of next available lesson
 * - Progress statistics
 */

// Include required configuration and function files
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/database.php';
require_once __DIR__ . '/include/functions.php';
require_once __DIR__ . '/include/auth.php';

// Get system configuration from environment variables with fallbacks
$systemName = trim(getenv('SYSTEM_NAME'), '"\'') ?: 'Stimma';
$systemDescription = trim(getenv('SYSTEM_DESCRIPTION'), '"\'') ?: '';

// Initialize error and success message variables
$error = '';
$success = '';

// Handle form submission for login/registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token for security
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $error = 'Ogiltig förfrågan. Vänligen försök igen.';
    } else {
        $email = trim($_POST['email'] ?? '');
        
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Check if user already exists
            $user = queryOne("SELECT * FROM " . DB_DATABASE . ".users WHERE email = ?", [$email]);
            
            if ($user) {
                // Existing user - send login link
                if (sendLoginToken($email)) {
                    $success = '<i class="bi bi-envelope-paper-fill me-2"></i>
                               <h4 class="mt-3">Inloggningslänk på väg!</h4>
                               <p class="mb-0">Vi har skickat en inloggningslänk till din e-postadress. 
                               Länken är giltig i ' . ((int)getenv('AUTH_TOKEN_EXPIRY_MINUTES') ?: 15) . ' minuter.
                               Om du inte ser e-postmeddelandet, kolla gärna i din skräppost.</p>';
                } else {
                    $error = 'Något gick fel vid utskick av inloggningslänk. Försök igen.';
                }
            } else {
                // New user - check domain
                $domain = substr(strrchr($email, "@"), 1);
                $allowedDomains = explode(',', getenv('MAIL_ALLOWED_RECIPIENTS'));
                
                if (in_array($domain, $allowedDomains)) {
                    // Create new user with only existing table columns
                    execute("INSERT INTO " . DB_DATABASE . ".users (email, created_at) 
                             VALUES (?, NOW())", 
                             [$email]);
                    
                    // Send login link
                    if (sendLoginToken($email)) {
                        $success = '<i class="bi bi-envelope-paper-fill me-2"></i>
                                   <h4 class="mt-3">Konto skapat och inloggningslänk på väg!</h4>
                                   <p class="mb-0">Vi har skapat ett konto för dig och skickat en inloggningslänk till din e-postadress. 
                                   Länken är giltig i ' . ((int)getenv('AUTH_TOKEN_EXPIRY_MINUTES') ?: 15) . ' minuter.
                                   Om du inte ser e-postmeddelandet, kolla gärna i din skräppost.</p>';
                    } else {
                        $error = 'Något gick fel vid utskick av inloggningslänk. Försök igen.';
                    }
                } else {
                    $error = 'Endast e-postadresser från godkända domäner är tillåtna för nya användare.';
                }
            }
        } else {
            $error = 'Ange en giltig e-postadress.';
        }
    }
}

// Check if user is logged in
$isLoggedIn = isLoggedIn();

// If not logged in - show email form
if (!$isLoggedIn): 
    // Set page title
    $page_title = $systemName . ' - Digitala verktyg för lärare';
    // Include header
    require_once 'include/header.php';
?>
    <!-- Login/Registration container -->
    <div class="container-sm min-vh-100 d-flex align-items-center px-3">
        <div class="row justify-content-center w-100">
            <div class="col-12 col-md-5 col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-body text-center p-4">
                        <!-- Logo and system description -->
                        <h1 class="display-4 mb-3"><img src="<?= BASE_PATH_URL ?>/images/logo.png" height="50px" alt="<?= $systemName ?>"></h1>
                        <?php if ($systemDescription): ?>
                            <p class="lead text-muted mb-4"><?= $systemDescription ?></p>
                        <?php endif; ?>
                        
                        <!-- Success message display -->
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <?= $success ?>
                            </div>
                        <?php else: ?>
                            <!-- Error message display -->
                            <?php if ($error): ?>
                                <div class="alert alert-danger">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    <p class="mb-0"><?= $error ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Email input form -->
                            <form action="index.php" method="post" class="form">
                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                <div class="form-floating mb-3">
                                    <input type="email" class="form-control" id="email" name="email" placeholder="namn@alingsas.se" required>
                                    <label for="email">E-postadress</label>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Skicka inloggningslänk</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php 
    // Include footer
    require_once 'include/footer.php';
else: 
    // Get user ID from session
    $userId = $_SESSION['user_id'];
    
    // Fetch active lessons and courses
    $lessons = query("
        SELECT l.*, c.title as course_title, c.status as course_status
        FROM " . DB_DATABASE . ".lessons l
        JOIN " . DB_DATABASE . ".courses c ON l.course_id = c.id
        WHERE c.status = 'active'
        ORDER BY c.title, l.sort_order
    ");
    
    // Get user's progress
    $progress = query("SELECT * FROM " . DB_DATABASE . ".progress WHERE user_id = ?", [$userId]);
    
    // Create array for easy access to user progress
    $userProgress = [];
    foreach ($progress as $item) {
        $userProgress[$item['lesson_id']] = $item;
    }
    
    // Find next available lesson
    $nextLesson = null;
    $nextCourse = null;
    
    // Group lessons by course and sort by course order
    $courseGroups = [];
    foreach ($lessons as $lesson) {
        if (!isset($courseGroups[$lesson['course_id']])) {
            $courseGroups[$lesson['course_id']] = [
                'title' => $lesson['course_title'],
                'sort_order' => $lesson['sort_order'],
                'lessons' => []
            ];
        }
        $courseGroups[$lesson['course_id']]['lessons'][] = $lesson;
    }

    // Sort courses by sort_order
    uasort($courseGroups, function($a, $b) {
        return $a['sort_order'] <=> $b['sort_order'];
    });

    // Find first incomplete lesson in first incomplete course
    foreach ($courseGroups as $courseGroup) {
        $hasIncomplete = false;
        foreach ($courseGroup['lessons'] as $lesson) {
            if (!isset($userProgress[$lesson['id']]) || $userProgress[$lesson['id']]['status'] !== 'completed') {
                $nextLesson = $lesson;
                $nextCourse = $courseGroup['title'];
                $hasIncomplete = true;
                break;
            }
        }
        if ($hasIncomplete) {
            break;
        }
    }
    
    // Group lessons by course for display
    $groupedLessons = [];
    foreach ($lessons as $lesson) {
        if (!isset($groupedLessons[$lesson['course_title']])) {
            $groupedLessons[$lesson['course_title']] = [
                'lessons' => [],
                'sort_order' => $lesson['sort_order']
            ];
        }
        $groupedLessons[$lesson['course_title']]['lessons'][] = $lesson;
    }
    
    // Calculate progress statistics
    $lessonCount = 0;
    $completedCount = 0;
    foreach ($groupedLessons as $courseData) {
        foreach ($courseData['lessons'] as $lesson) {
            $lessonCount++;
            if (isset($userProgress[$lesson['id']]) && $userProgress[$lesson['id']]['status'] === 'completed') {
                $completedCount++;
            }
        }
    }
    $progressPercent = $lessonCount > 0 ? round(($completedCount / $lessonCount) * 100) : 0;

    // Check if user is admin
    $isAdmin = false;
    if (isLoggedIn()) {
        $user = queryOne("SELECT is_admin FROM " . DB_DATABASE . ".users WHERE id = ?", [$_SESSION['user_id']]);
        $isAdmin = $user ? (bool)$user['is_admin'] : false;
    }

    // Set page title
    $page_title = $systemName . ' - Digitala verktyg för lärare';
    // Include header
    require_once 'include/header.php';
?>
    <!-- Main content container -->
    <div class="container-sm py-4">
        <div class="row">
            <!-- Left sidebar (empty on desktop) -->
            <div class="col-lg-2 d-none d-lg-block"></div>
            <!-- Main content area -->
            <div class="col-12 col-lg-8">
                <main>
                    <!-- Next lesson card -->
                    <?php if ($nextLesson): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge bg-secondary mb-2">Nästa lektion</span>
                                    <h2 class="card-title h4"><?= sanitize($nextLesson['title']) ?></h2>
                                    <p class="text-muted mb-2">Kurs: <?= sanitize($nextCourse) ?></p>
                                    <?php if (!empty($nextLesson['description'])): ?>
                                        <p class="card-text"><?= sanitize($nextLesson['description']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <a href="lesson.php?id=<?= $nextLesson['id'] ?>" class="btn btn-success">
                                    Fortsätt lära
                                    <i class="bi bi-arrow-right ms-2"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="h4 mb-0">Dina kurser</h2>
                        <span class="badge bg-text text-dark"><?= count($groupedLessons) ?> kurser</span>
                    </div>
                    <?php if (empty($groupedLessons)): ?>
                        <div class="alert alert-info">
                            Det finns inga lektioner tillgängliga ännu.
                        </div>
                    <?php else: ?>
                        <?php foreach ($groupedLessons as $courseTitle => $courseData): 
                            $courseLessons = $courseData['lessons'];
                            // Räkna framsteg för denna kurs
                            $courseTotal = count($courseLessons);
                            $courseCompleted = 0;
                            foreach ($courseLessons as $lesson) {
                                if (isset($userProgress[$lesson['id']]) && $userProgress[$lesson['id']]['status'] === 'completed') {
                                    $courseCompleted++;
                                }
                            }
                            $coursePercent = round(($courseCompleted / $courseTotal) * 100);
                        ?>
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h2 class="h5 mb-0"><?= sanitize($courseTitle) ?></h2>
                                    <span class="badge bg-light text-primary"><?= $courseCompleted ?>/<?= $courseTotal ?></span>
                                </div>
                            </div>
                            
                            <div class="list-group list-group-flush">
                                <?php foreach ($courseLessons as $lesson): 
                                    $isCompleted = isset($userProgress[$lesson['id']]) && $userProgress[$lesson['id']]['status'] === 'completed';
                                ?>
                                <a href="lesson.php?id=<?= $lesson['id'] ?>" class="list-group-item list-group-item-action py-3">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <?php if ($isCompleted): ?>
                                            <i class="bi bi-check-circle-fill text-success" style="font-size: 1.25rem;"></i>
                                            <?php else: ?>
                                            <i class="bi bi-circle text-muted" style="font-size: 1.25rem;"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?= sanitize($lesson['title']) ?></h6>
                                            <?php if (!empty($lesson['description'])): ?>
                                            <p class="text-muted small mb-0"><?= sanitize($lesson['description']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <i class="bi bi-chevron-right text-muted ms-2"></i>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </main>
            </div>
            <div class="col-lg-2 d-none d-lg-block"></div>
        </div>
    </div>
<?php 
    // Inkludera footer
    require_once 'include/footer.php';
endif; ?>
