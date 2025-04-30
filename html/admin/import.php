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

// Hantera AJAX-anrop separat
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
    // Kontrollera att användaren är inloggad
    if (!isLoggedIn() || !isset($_SESSION['user_id'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Du måste vara inloggad.']);
        exit;
    }

    // Kontrollera behörighet
    $user = queryOne("SELECT is_admin, is_editor FROM " . DB_DATABASE . ".users WHERE id = ?", [$_SESSION['user_id']]);
    if (!$user || ($user['is_admin'] !== 1 && $user['is_editor'] !== 1)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Du har inte behörighet.']);
        exit;
    }

    // Hantera filuppladdning för AJAX
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['course_file'])) {
        $file = $_FILES['course_file'];
        
        // Kontrollera att det är en JSON-fil
        if ($file['type'] !== 'application/json') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Endast JSON-filer är tillåtna.']);
            exit;
        }

        // Läs innehållet i filen
        $content = file_get_contents($file['tmp_name']);
        $data = json_decode($content, true);
        
        if (!$data || !isset($data['course'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Ogiltig JSON-fil.']);
            exit;
        }

        try {
            // Börja transaktion
            execute("START TRANSACTION");
            
            // Hämta användarens ID
            $user = queryOne("SELECT id FROM " . DB_DATABASE . ".users WHERE email = ?", [$_SESSION['user_email']]);
            if (!$user) {
                throw new Exception('Kunde inte hitta användarinformation.');
            }
            
            // Skapa kursen
            $courseId = execute("
                INSERT INTO " . DB_DATABASE . ".courses (
                    title, description, difficulty_level, duration_minutes, 
                    prerequisites, tags, image_url, status, sort_order, 
                    featured, author_id, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ", [
                $data['course']['title'],
                $data['course']['description'],
                $data['course']['difficulty_level'] ?? 'beginner',
                $data['course']['duration_minutes'] ?? 0,
                $data['course']['prerequisites'] ?? null,
                $data['course']['tags'] ?? null,
                $data['course']['image_url'] ?? null,
                'inactive', // Sätt alltid till inaktiv vid import
                $data['course']['sort_order'] ?? 0,
                $data['course']['featured'] ?? 0,
                $user['id'] // Använd den inloggade användarens ID som author_id
            ]);

            // Förbered statusinformation
            $status = [
                'success' => true,
                'message' => 'Kursen importeras...',
                'current' => 'Kurs: ' . $data['course']['title'],
                'progress' => 0,
                'steps' => []
            ];
            
            // Lägg till lektionerna
            if (isset($data['lessons']) && is_array($data['lessons'])) {
                $totalLessons = count($data['lessons']);
                foreach ($data['lessons'] as $index => $lesson) {
                    execute("
                        INSERT INTO " . DB_DATABASE . ".lessons (
                            course_id, title, estimated_duration, image_url, 
                            video_url, content, resource_links, tags, status, 
                            sort_order, ai_instruction, ai_prompt, 
                            quiz_question, quiz_answer1, quiz_answer2, 
                            quiz_answer3, quiz_correct_answer, author_id, created_at, 
                            updated_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ", [
                        $courseId,
                        $lesson['title'],
                        $lesson['estimated_duration'] ?? 5,
                        $lesson['image_url'] ?? null,
                        $lesson['video_url'] ?? null,
                        $lesson['content'] ?? null,
                        $lesson['resource_links'] ?? null,
                        $lesson['tags'] ?? null,
                        $lesson['status'] ?? 'active',
                        $lesson['sort_order'] ?? 0,
                        $lesson['ai_instruction'] ?? null,
                        $lesson['ai_prompt'] ?? null,
                        $lesson['quiz_question'] ?? null,
                        $lesson['quiz_answer1'] ?? null,
                        $lesson['quiz_answer2'] ?? null,
                        $lesson['quiz_answer3'] ?? null,
                        $lesson['quiz_correct_answer'] ?? null,
                        $user['id'] // Använd samma author_id som för kursen
                    ]);

                    // Lägg till status för varje lektion
                    $status['steps'][] = [
                        'message' => 'Lektioner importeras...',
                        'current' => 'Lektion ' . ($index + 1) . ' av ' . $totalLessons . ': ' . $lesson['title'],
                        'progress' => round(($index + 1) / $totalLessons * 100)
                    ];
                }
            }
            
            // Slutför transaktionen
            execute("COMMIT");
            
            // Lägg till slutstatus
            $status['steps'][] = [
                'message' => 'Importen är klar!',
                'current' => 'Omdirigerar...',
                'progress' => 100,
                'redirect' => 'courses.php'
            ];
            
            // Skicka all statusinformation i ett svar
            header('Content-Type: application/json');
            echo json_encode($status);
            exit;
            
        } catch (Exception $e) {
            // Återställ transaktionen vid fel
            execute("ROLLBACK");
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Ett fel uppstod vid import av kursen: ' . $e->getMessage()
            ]);
            exit;
        }
    }
    exit;
}

// Vanlig sidvisning
// Kontrollera att användaren är inloggad
if (!isLoggedIn() || !isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Kontrollera behörighet
$user = queryOne("SELECT is_admin, is_editor FROM " . DB_DATABASE . ".users WHERE id = ?", [$_SESSION['user_id']]);
if (!$user || ($user['is_admin'] !== 1 && $user['is_editor'] !== 1)) {
    header('Location: login.php');
    exit;
}

// Sätt sidtitel
$page_title = 'Importera kurs';

// Inkludera header
require_once 'include/header.php';
?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-muted">Importera kurs</h6>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" id="importForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <div class="mb-3">
                <label for="course_file" class="form-label">Välj kursfil (JSON)</label>
                <input type="file" class="form-control" id="course_file" name="course_file" accept=".json" required>
                <div class="form-text">Välj en JSON-fil som innehåller kursdata som exporterats från en annan Stimma-installation.</div>
            </div>
            <button type="submit" class="btn btn-primary">Importera kurs</button>
            <a href="courses.php" class="btn btn-secondary">Avbryt</a>
        </form>

        <!-- Progress indicator -->
        <div id="importProgress" class="mt-4" style="display: none;">
            <div class="progress mb-3" style="height: 25px;">
                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                     role="progressbar" 
                     style="width: 0%" 
                     aria-valuenow="0" 
                     aria-valuemin="0" 
                     aria-valuemax="100"></div>
            </div>
            <div id="importStatus" class="text-center">
                <div class="spinner-border text-primary mb-2" role="status">
                    <span class="visually-hidden">Laddar...</span>
                </div>
                <p class="mb-0" id="currentItem">Förbereder import...</p>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('importForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const progressBar = document.querySelector('.progress-bar');
    const importProgress = document.getElementById('importProgress');
    const currentItem = document.getElementById('currentItem');
    
    // Visa progress-indikatorn
    importProgress.style.display = 'block';
    
    // Skicka formuläret med fetch
    fetch('import.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let currentStep = 0;
            
            // Funktion för att visa nästa steg
            function showNextStep() {
                if (currentStep < data.steps.length) {
                    const step = data.steps[currentStep];
                    
                    // Uppdatera progress-baren och status
                    if (step.progress !== undefined) {
                        progressBar.style.width = step.progress + '%';
                        progressBar.setAttribute('aria-valuenow', step.progress);
                    }
                    
                    // Uppdatera statusmeddelandet
                    if (step.current) {
                        currentItem.textContent = step.current;
                    }
                    
                    // Om det är sista steget och det finns en redirect
                    if (currentStep === data.steps.length - 1 && step.redirect) {
                        setTimeout(() => {
                            window.location.href = step.redirect;
                        }, 1000);
                    } else {
                        // Visa nästa steg efter 1 sekund
                        setTimeout(showNextStep, 1000);
                    }
                    
                    currentStep++;
                }
            }
            
            // Starta visningen av stegen
            showNextStep();
        } else {
            // Visa felmeddelande
            alert(data.message || 'Ett fel uppstod vid importen');
            importProgress.style.display = 'none';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Ett fel uppstod vid importen');
        importProgress.style.display = 'none';
    });
});
</script>

<?php
// Inkludera footer
require_once 'include/footer.php';
