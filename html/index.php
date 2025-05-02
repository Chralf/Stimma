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
require_once 'include/config.php';
require_once 'include/database.php';
require_once 'include/functions.php';
require_once 'include/auth.php';

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
                        <h1 class="display-4 mb-3"><img src="images/logo.png" height="50px" alt="<?= $systemName ?>"></h1>
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
        SELECT l.*, c.title as course_title, c.status as course_status, c.image_url as course_image_url
        FROM " . DB_DATABASE . ".lessons l
        JOIN " . DB_DATABASE . ".courses c ON l.course_id = c.id
        WHERE c.status = 'active'
        ORDER BY c.title, l.sort_order
    ");
    
    // Get user's progress
    $progress = query("SELECT * FROM " . DB_DATABASE . ".progress WHERE user_id = ?", [$userId]);
    
    // Get user's email and domain
    $user = queryOne("SELECT email FROM " . DB_DATABASE . ".users WHERE id = ?", [$userId]);
    $userDomain = substr(strrchr($user['email'], "@"), 1);
    
    // Fetch organization courses (courses created by users with same domain)
    $orgCourses = query("
        SELECT DISTINCT c.*, u.email as author_email
        FROM " . DB_DATABASE . ".courses c
        JOIN " . DB_DATABASE . ".users u ON c.author_id = u.id
        WHERE c.status = 'active'
        AND SUBSTRING_INDEX(u.email, '@', -1) = ?
        ORDER BY c.sort_order
    ", [$userDomain]);
    
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
    <div class="container-fluid px-3 px-md-4 py-4">
        <div class="row">
            <!-- Left sidebar (empty on desktop) -->
            <div class="col-lg-2 d-none d-lg-block"></div>
            <!-- Main content area -->
            <div class="col-12 col-lg-8">
                <main>
                    <!-- Next lesson card -->
                    <?php if ($nextLesson): ?>
                    <div class="card shadow-sm mb-4">
                        <div class="card-body px-3 py-3">
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
                                <div class="me-md-3">
                                    <span class="badge bg-secondary mb-2">Nästa lektion</span>
                                    <h2 class="card-title fs-5 text-truncate"><?= sanitize($nextLesson['title']) ?></h2>
                                    <p class="text-muted small mb-2">Kurs: <?= sanitize($nextCourse) ?></p>
                                    <?php if (!empty($nextLesson['description'])): ?>
                                        <p class="card-text small"><?= sanitize($nextLesson['description']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <a href="lesson.php?id=<?= $nextLesson['id'] ?>" class="btn btn-primary btn-sm mt-2 mt-md-0">
                                    Fortsätt lära
                                    <i class="bi bi-arrow-right ms-2"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="h2 fs-5 fs-md-3 mb-0">Mina kurser</h2>
                    </div>
                    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3 mx-2 mx-md-0">
                        <?php 
                        $hasStartedCourses = false;
                        foreach ($groupedLessons as $courseTitle => $courseData): 
                            $courseLessons = $courseData['lessons'];
                            // Calculate course progress
                            $courseTotal = count($courseLessons);
                            $courseCompleted = 0;
                            $courseStarted = false;
                            
                            foreach ($courseLessons as $lesson) {
                                if (isset($userProgress[$lesson['id']]) && $userProgress[$lesson['id']]['status'] === 'completed') {
                                    $courseCompleted++;
                                    $courseStarted = true;
                                }
                            }
                            
                            if (!$courseStarted) {
                                continue; // Skip to next course if not started
                            }
                            $hasStartedCourses = true;
                            
                            // Get the next lesson in this course
                            $nextLessonInCourse = null;
                            foreach ($courseLessons as $lesson) {
                                if (!isset($userProgress[$lesson['id']]) || $userProgress[$lesson['id']]['status'] !== 'completed') {
                                    $nextLessonInCourse = $lesson;
                                    break;
                                }
                            }
                        ?>
                        <div class="col">
                            <div class="card shadow-sm h-100">
                                <?php 
                                    // Get first lesson of the course to access course data
                                    $firstLesson = reset($courseLessons);
                                    $courseImageUrl = $firstLesson['course_image_url'] ?? null;
                                ?>
                                <?php if ($courseImageUrl): ?>
                                    <div class="ratio ratio-16x9">
                                        <img src="<?= sanitize($courseImageUrl) ?>" class="card-img-top object-fit-cover max-height-150 max-height-md-none" alt="<?= sanitize($courseTitle) ?>">
                                    </div>
                                <?php else: ?>
                                    <div class="ratio ratio-16x9">
                                        <img src="images/placeholder.png" class="card-img-top object-fit-cover max-height-150 max-height-md-none" alt="<?= sanitize($courseTitle) ?>">
                                    </div>
                                <?php endif; ?>
                                
                                <div class="card-body d-flex flex-column px-3 py-3">
                                    <h5 class="card-title text-truncate"><?= sanitize($courseTitle) ?></h5>
                                    <div class="d-flex align-items-center my-3">
                                        <div class="progress flex-grow-1" style="height: 8px;">
                                            <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" role="progressbar" 
                                                 style="width: <?= ($courseCompleted / $courseTotal) * 100 ?>%"></div>
                                        </div>
                                        <span class="ms-2 text-muted small"><?= $courseCompleted ?> av <?= $courseTotal ?> lektioner klarade</span>
                                    </div>
                                    
                                    <?php if ($nextLessonInCourse): ?>
                                        <p class="card-text text-muted small mb-3">
                                            Nästa: <?= sanitize($nextLessonInCourse['title']) ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <div class="mt-auto">
                                        <?php if ($nextLessonInCourse): ?>
                                            <a href="lesson.php?id=<?= $nextLessonInCourse['id'] ?>" class="btn btn-primary btn-sm d-block w-100">
                                                Fortsätt lära
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-outline-primary btn-sm d-block w-100" disabled>Kursen är klar!</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (!$hasStartedCourses): ?>
                        <div class="alert alert-info mt-4 mx-2 mx-md-0">
                            Du har inte påbörjat några kurser än.
                        </div>
                    <?php endif; ?>

                    <hr class="my-4 border-light">

                    <?php if (!empty($orgCourses)): ?>
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2 class="h2 fs-5 fs-md-3 mb-0">Min organisations kurser</h2>
                        </div>
                        
                        <!-- Search filter -->
                        <div class="mb-4 mx-2 mx-md-0">
                            <div class="input-group">
                                <input type="text" class="form-control" id="orgCourseSearch" placeholder="Filtrera kurser...">
                                <span class="input-group-text">
                                    <i class="bi bi-search"></i>
                                </span>
                            </div>
                        </div>

                        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3 mx-2 mx-md-0" id="orgCourseGrid">
                            <?php foreach ($orgCourses as $course): 
                                // Get lessons for this course
                                $courseLessons = query("
                                    SELECT * FROM " . DB_DATABASE . ".lessons 
                                    WHERE course_id = ? 
                                    ORDER BY sort_order
                                ", [$course['id']]);
                                
                                // Check if course is started
                                $courseStarted = false;
                                $courseCompleted = 0;
                                foreach ($courseLessons as $lesson) {
                                    if (isset($userProgress[$lesson['id']]) && $userProgress[$lesson['id']]['status'] === 'completed') {
                                        $courseStarted = true;
                                        $courseCompleted++;
                                    }
                                }
                                
                                // Skip if already shown in "Mina kurser"
                                if ($courseStarted) {
                                    continue;
                                }
                            ?>
                            <div class="col">
                                <div class="card shadow-sm h-100">
                                    <?php if ($course['image_url']): ?>
                                        <div class="ratio ratio-16x9">
                                            <img src="<?= sanitize($course['image_url']) ?>" class="card-img-top object-fit-cover max-height-150 max-height-md-none" alt="<?= sanitize($course['title']) ?>">
                                        </div>
                                    <?php else: ?>
                                        <div class="ratio ratio-16x9">
                                            <img src="images/placeholder.png" class="card-img-top object-fit-cover max-height-150 max-height-md-none" alt="<?= sanitize($course['title']) ?>">
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="card-body d-flex flex-column px-3 py-3">
                                        <h5 class="card-title text-truncate"><?= sanitize($course['title']) ?></h5>
                                        <p class="card-text text-muted small mb-3">
                                            <?= count($courseLessons) ?> lektioner
                                        </p>
                                        <p class="card-text text-muted small">
                                            Skapad av: <?= sanitize($course['author_email']) ?>
                                        </p>
                                        
                                        <div class="mt-auto">
                                            <a href="lesson.php?id=<?= $courseLessons[0]['id'] ?>" class="btn btn-outline-primary btn-sm d-block w-100">
                                                Börja kursen
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <hr class="my-4 border-light">
                    <?php endif; ?>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="h2 fs-5 fs-md-3 mb-0">Kurser</h2>
                    </div>
                    
                    <!-- Search filter -->
                    <div class="mb-4 mx-2 mx-md-0">
                        <div class="input-group">
                            <input type="text" class="form-control" id="courseSearch" placeholder="Filtrera kurser...">
                            <span class="input-group-text">
                                <i class="bi bi-search"></i>
                            </span>
                        </div>
                    </div>

                    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3 mx-2 mx-md-0" id="courseGrid">
                        <?php 
                        $hasUnstartedCourses = false;
                        foreach ($groupedLessons as $courseTitle => $courseData): 
                            $courseLessons = $courseData['lessons'];
                            // Check if course is started
                            $courseStarted = false;
                            foreach ($courseLessons as $lesson) {
                                if (isset($userProgress[$lesson['id']]) && $userProgress[$lesson['id']]['status'] === 'completed') {
                                    $courseStarted = true;
                                    break;
                                }
                            }
                            
                            if ($courseStarted) {
                                continue; // Skip to next course if already started
                            }
                            $hasUnstartedCourses = true;
                            
                            // Get the first lesson in this course
                            $firstLesson = reset($courseLessons);
                        ?>
                        <div class="col">
                            <div class="card shadow-sm h-100">
                                <?php 
                                    $courseImageUrl = $firstLesson['course_image_url'] ?? null;
                                ?>
                                <?php if ($courseImageUrl): ?>
                                    <div class="ratio ratio-16x9">
                                        <img src="<?= sanitize($courseImageUrl) ?>" class="card-img-top object-fit-cover max-height-150 max-height-md-none" alt="<?= sanitize($courseTitle) ?>">
                                    </div>
                                <?php else: ?>
                                    <div class="ratio ratio-16x9">
                                        <img src="images/placeholder.png" class="card-img-top object-fit-cover max-height-150 max-height-md-none" alt="<?= sanitize($courseTitle) ?>">
                                    </div>
                                <?php endif; ?>
                                
                                <div class="card-body d-flex flex-column px-3 py-3">
                                    <h5 class="card-title text-truncate"><?= sanitize($courseTitle) ?></h5>
                                    <p class="card-text text-muted small mb-3">
                                        <?= count($courseLessons) ?> lektioner
                                    </p>
                                </div>
                                
                                <div class="card-footer bg-white border-top-0">
                                    <a href="lesson.php?id=<?= $firstLesson['id'] ?>" class="btn btn-outline-primary btn-sm w-100">
                                        Börja kursen
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (!$hasUnstartedCourses): ?>
                        <div class="alert alert-info mt-4 mx-2 mx-md-0">
                            Det finns inga fler kurser tillgängliga just nu.
                        </div>
                    <?php endif; ?>
                </main>
            </div>
            <div class="col-lg-2 d-none d-lg-block"></div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Search for all courses
            const searchInput = document.getElementById('courseSearch');
            const courseGrid = document.getElementById('courseGrid');
            const courseCards = courseGrid.getElementsByClassName('col');
            const noResultsAlert = document.createElement('div');
            noResultsAlert.className = 'alert alert-info mt-4';
            noResultsAlert.textContent = 'Inga kurser matchar din sökning.';
            noResultsAlert.style.display = 'none';
            courseGrid.parentNode.insertBefore(noResultsAlert, courseGrid.nextSibling);

            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                let visibleCount = 0;

                Array.from(courseCards).forEach(card => {
                    const title = card.querySelector('.card-title').textContent.toLowerCase();
                    const description = card.querySelector('.card-text')?.textContent.toLowerCase() || '';
                    const matches = title.includes(searchTerm) || description.includes(searchTerm);
                    
                    card.style.display = matches ? '' : 'none';
                    if (matches) visibleCount++;
                });

                noResultsAlert.style.display = visibleCount === 0 ? '' : 'none';
            });

            // Search for organization courses
            const orgSearchInput = document.getElementById('orgCourseSearch');
            const orgCourseGrid = document.getElementById('orgCourseGrid');
            if (orgCourseGrid) {
                const orgCourseCards = orgCourseGrid.getElementsByClassName('col');
                const orgNoResultsAlert = document.createElement('div');
                orgNoResultsAlert.className = 'alert alert-info mt-4';
                orgNoResultsAlert.textContent = 'Inga kurser matchar din sökning.';
                orgNoResultsAlert.style.display = 'none';
                orgCourseGrid.parentNode.insertBefore(orgNoResultsAlert, orgCourseGrid.nextSibling);

                orgSearchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    let visibleCount = 0;

                    Array.from(orgCourseCards).forEach(card => {
                        const title = card.querySelector('.card-title').textContent.toLowerCase();
                        const description = card.querySelector('.card-text')?.textContent.toLowerCase() || '';
                        const matches = title.includes(searchTerm) || description.includes(searchTerm);
                        
                        card.style.display = matches ? '' : 'none';
                        if (matches) visibleCount++;
                    });

                    orgNoResultsAlert.style.display = visibleCount === 0 ? '' : 'none';
                });
            }
        });
    </script>
<?php 
    // Inkludera footer
    require_once 'include/footer.php';
endif; ?>
