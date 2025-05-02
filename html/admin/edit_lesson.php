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

// Include centralized authentication and authorization check
require_once 'include/auth_check.php';

// Sätt sidtitel
$page_title = isset($_GET['id']) ? 'Redigera lektion' : 'Skapa ny lektion';

// Om ID finns, hämta lektionsinformation
if (isset($_GET['id'])) {
    $lesson = queryOne("SELECT * FROM " . DB_DATABASE . ".lessons WHERE id = ?", [$_GET['id']]);
    if (!$lesson) {
        $_SESSION['message'] = 'Lektionen hittades inte.';
        $_SESSION['message_type'] = 'danger';
        header('Location: lessons.php');
        exit;
    }
}

// Hämta alla kurser för dropdown
$courses = queryAll("SELECT * FROM " . DB_DATABASE . ".courses ORDER BY sort_order ASC");

// Hantera formulär
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $course_id = (int)($_POST['course_id'] ?? 0);
    $status = isset($_POST['status']) && $_POST['status'] === 'active' ? 'active' : 'inactive';
    
    // Hantera innehåll från editorn
    $content = $_POST['content'] ?? '';
    $ai_instruction = $_POST['ai_instruction'] ?? '';
    $ai_prompt = $_POST['ai_prompt'] ?? '';
    $quiz_question = $_POST['quiz_question'] ?? '';
    
    // Hantera vanliga textfält
    $quiz_answer1 = trim($_POST['quiz_answer1'] ?? '');
    $quiz_answer2 = trim($_POST['quiz_answer2'] ?? '');
    $quiz_answer3 = trim($_POST['quiz_answer3'] ?? '');
    $quiz_correct_answer = (int)($_POST['quiz_correct_answer'] ?? 0);
    
    // Hantera bilduppladdning
    $image_url = $_POST['image_url'] ?? ''; // Behåll befintlig bild om ingen ny laddas upp
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['image']['type'], $allowedTypes)) {
            $error = 'Endast JPG, PNG och GIF bilder är tillåtna.';
        } elseif ($_FILES['image']['size'] > $maxSize) {
            $error = 'Bilden får inte vara större än 5MB.';
        } else {
            // Sökväg till upload-mappen
            $uploadDir = __DIR__ . '/../upload/';
            $fileName = uniqid() . '_' . basename($_FILES['image']['name']);
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                $image_url = $fileName;
            } else {
                $error = 'Kunde inte ladda upp bilden.';
            }
        }
    }
    
    if (empty($title)) {
        $error = 'Titel är obligatoriskt.';
    } else {

        
        if (isset($_GET['id'])) {
            // Uppdatera befintlig lektion
            execute("UPDATE " . DB_DATABASE . ".lessons SET 
                    title = ?, 
                    content = ?, 
                    course_id = ?,
                    image_url = ?,
                    video_url = ?,
                    status = ?,
                    ai_instruction = ?,
                    ai_prompt = ?,
                    quiz_question = ?,
                    quiz_answer1 = ?,
                    quiz_answer2 = ?,
                    quiz_answer3 = ?,
                    quiz_correct_answer = ?,
                    updated_at = NOW() 
                    WHERE id = ?", 
                    [$title, $content, $course_id, $image_url, $_POST['video_url'], $status, 
                     $ai_instruction, $ai_prompt, $quiz_question,
                     $quiz_answer1, $quiz_answer2, $quiz_answer3, $quiz_correct_answer,
                     $_GET['id']]);
            
            // Logga ändringen
            logActivity($_SESSION['user_email'], "Uppdaterade lektionen '" . $title . "' (ID: " . $_GET['id'] . ")");
            
            $_SESSION['message'] = 'Lektionen har uppdaterats.';
        } else {
            // Hitta högsta sort_order för denna kurs
            $maxOrder = queryOne("SELECT MAX(sort_order) as max_order FROM " . DB_DATABASE . ".lessons WHERE course_id = ?", [$course_id])['max_order'] ?? 0;
            
            // Hämta användarens ID
            $author = queryOne("SELECT id FROM " . DB_DATABASE . ".users WHERE email = ?", [$_SESSION['user_email']]);
            $authorId = $author ? $author['id'] : null;
            
            // Skapa ny lektion
            execute("INSERT INTO " . DB_DATABASE . ".lessons 
                    (title, content, course_id, image_url, video_url, status, 
                     ai_instruction, ai_prompt, quiz_question,
                     quiz_answer1, quiz_answer2, quiz_answer3, quiz_correct_answer,
                     sort_order, author_id, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())", 
                    [$title, $content, $course_id, $image_url, $_POST['video_url'], $status,
                     $ai_instruction, $ai_prompt, $quiz_question,
                     $quiz_answer1, $quiz_answer2, $quiz_answer3, $quiz_correct_answer,
                     $maxOrder + 1, $authorId]);
            
            $newId = getDb()->lastInsertId();
            logActivity($_SESSION['user_email'], "Skapade ny lektion '" . $title . "' (ID: " . $newId . ")");
            
            $_SESSION['message'] = 'Lektionen har skapats.';
        }
        
        $_SESSION['message_type'] = 'success';
        header('Location: lessons.php?course_id=' . $course_id);
        exit;
    }
}

// Inkludera header
require_once 'include/header.php';

$title = '';
$content = '';
$status = 'active';
$id = null;
$courseId = null;
$imageUrl = '';
$aiInstruction = '';
$aiPrompt = '';
$quizQuestion = '';
$quizAnswer1 = '';
$quizAnswer2 = '';
$quizAnswer3 = '';
$quizCorrectAnswer = 0;

// Kontrollera om vi redigerar en befintlig lektion eller skapar en ny
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];
    $lesson = queryOne("SELECT * FROM " . DB_DATABASE . ".lessons WHERE id = ?", [$id]);
    
    if ($lesson) {
        $title = $lesson['title'] ?? '';
        $content = $lesson['content'] ?? '';
        $status = $lesson['status'] ?? 'active';
        $courseId = $lesson['course_id'] ?? null;
        $imageUrl = $lesson['image_url'] ?? '';
        $aiInstruction = $lesson['ai_instruction'] ?? '';
        $aiPrompt = $lesson['ai_prompt'] ?? '';
        $quizQuestion = $lesson['quiz_question'] ?? '';
        $quizAnswer1 = $lesson['quiz_answer1'] ?? '';
        $quizAnswer2 = $lesson['quiz_answer2'] ?? '';
        $quizAnswer3 = $lesson['quiz_answer3'] ?? '';
        $quizCorrectAnswer = (int)($lesson['quiz_correct_answer'] ?? 0);
    } else {
        $_SESSION['message'] = "Lektionen kunde inte hittas.";
        $_SESSION['message_type'] = "danger";
        header('Location: courses.php');
        exit;
    }
} elseif (isset($_GET['course_id']) && is_numeric($_GET['course_id'])) {
    // Ny lektion för en specifik kurs
    $courseId = (int)$_GET['course_id'];
    
    // Kontrollera att kursen finns
    $course = queryOne("SELECT * FROM " . DB_DATABASE . ".courses WHERE id = ?", [$courseId]);
    if (!$course) {
        $_SESSION['message'] = "Kursen kunde inte hittas.";
        $_SESSION['message_type'] = "danger";
        header('Location: courses.php');
        exit;
    }
} else {
    $_SESSION['message'] = "Ingen kurs vald.";
    $_SESSION['message_type'] = "danger";
    header('Location: courses.php');
    exit;
}

// Hämta alla kurser för dropdown
$courses = queryAll("SELECT * FROM " . DB_DATABASE . ".courses ORDER BY sort_order ASC");
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-muted"><?= $id ? 'Redigera lektion' : 'Skapa ny lektion' ?></h6>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['message'])): ?>
                        <div class="alert alert-<?= $_SESSION['message_type'] ?>">
                            <?= $_SESSION['message'] ?>
                        </div>
                        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
                    <?php endif; ?>
                    
                    <form method="post" action="" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?= htmlspecialchars($title ?? '') ?>" required>
                                    <label for="title">Titel</label>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <div class="form-floating">
                                    <select class="form-select" id="course_id" name="course_id" required>
                                        <?php foreach ($courses as $course): ?>
                                            <option value="<?= $course['id'] ?>" 
                                                    <?= $courseId == $course['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($course['title']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="course_id">Kurs</label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="content" class="form-label">Innehåll</label>
                            <?php require_once 'include/editor.php'; renderEditor($content ?? '', 'content', 'contentEditor'); ?>
                        </div>

                        <div class="mb-3">
                            <label for="image" class="form-label">Bild</label>
                            <?php if (!empty($imageUrl)): ?>
                                <div class="mb-2">
                                    <p class="text-muted">Nuvarande bild:</p>
                                    <img src="../upload/<?= htmlspecialchars($imageUrl) ?>" alt="Lektionsbild" class="img-thumbnail" style="max-width: 200px;">
                                    <input type="hidden" name="image_url" value="<?= htmlspecialchars($imageUrl) ?>">
                                    <div class="form-text">Sökväg: <?= htmlspecialchars($imageUrl) ?></div>
                                </div>
                                <p class="text-muted">Ladda upp ny bild för att ersätta den nuvarande:</p>
                            <?php endif; ?>
                            <input type="file" class="form-control" id="image" name="image" accept="image/jpeg,image/png,image/gif">
                            <div class="form-text">Max 5MB. Tillåtna format: JPG, PNG, GIF</div>
                        </div>

                        <div class="mb-3">
                            <div class="form-floating">
                                <input type="url" class="form-control" id="video_url" name="video_url" 
                                       value="<?= htmlspecialchars($lesson['video_url'] ?? '') ?>"
                                       placeholder="https://www.youtube.com/watch?v=...">
                                <label for="video_url">YouTube-länk</label>
                            </div>
                            <div class="form-text">Klistra in en YouTube-länk (t.ex. https://www.youtube.com/watch?v=...) eller en embed-länk</div>
                        </div>

                        <div class="mb-3">
                            <label for="ai_instruction" class="form-label">AI Instruktion <div class="form-text">Instruktioner som visas för användaren i AI-chatten.</div></label>
                            <?php require_once 'include/editor.php'; renderEditor($aiInstruction ?? '', 'ai_instruction', 'aiInstructionEditor'); ?>
                            
                        </div>

                        <div class="mb-3">
                            <label for="ai_prompt" class="form-label">AI Prompt <div class="form-text">Prompt som skickas till AI:n för att styra svaren. (Be gärna ett AI om hjälp)</div></label>
                            
                            <?php require_once 'include/editor.php'; renderEditor($aiPrompt ?? '', 'ai_prompt', 'aiPromptEditor'); ?>
                           
                        </div>

                        <div class="mb-3">
                            <label for="quiz_question" class="form-label">Quiz-fråga</label>
                            <?php require_once 'include/editor.php'; renderEditor($quizQuestion ?? '', 'quiz_question', 'quizQuestionEditor'); ?>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="quiz_answer1" name="quiz_answer1" 
                                           value="<?= htmlspecialchars($quizAnswer1 ?? '') ?>">
                                    <label for="quiz_answer1">Svarsalternativ 1</label>
                                </div>
                            </div>

                            <div class="col-md-4 mb-3">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="quiz_answer2" name="quiz_answer2" 
                                           value="<?= htmlspecialchars($quizAnswer2 ?? '') ?>">
                                    <label for="quiz_answer2">Svarsalternativ 2</label>
                                </div>
                            </div>

                            <div class="col-md-4 mb-3">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="quiz_answer3" name="quiz_answer3" 
                                           value="<?= htmlspecialchars($quizAnswer3 ?? '') ?>">
                                    <label for="quiz_answer3">Svarsalternativ 3</label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-floating">
                                <select class="form-select" id="quiz_correct_answer" name="quiz_correct_answer">
                                    <option value="">Välj rätt svar...</option>
                                    <option value="1" <?= $quizCorrectAnswer === 1 ? 'selected' : '' ?>>Svar 1</option>
                                    <option value="2" <?= $quizCorrectAnswer === 2 ? 'selected' : '' ?>>Svar 2</option>
                                    <option value="3" <?= $quizCorrectAnswer === 3 ? 'selected' : '' ?>>Svar 3</option>
                                </select>
                                <label for="quiz_correct_answer">Rätt svar</label>
                            </div>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="status" name="status" 
                                   value="active" <?= $status === 'active' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="status">Aktiv</label>
                        </div>

                        <div class="mt-4 d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary">
                                <?= $id ? 'Spara ändringar' : 'Skapa lektion' ?>
                            </button>
                            <a href="lessons.php" class="btn btn-secondary">Avbryt</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Inkludera footer
require_once 'include/footer.php';
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const imageInput = document.getElementById('image');
    if (imageInput) {
        imageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;

            // Validera filtyp
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!allowedTypes.includes(file.type)) {
                alert('Endast JPG, PNG och GIF bilder är tillåtna.');
                e.target.value = '';
                return;
            }

            // Validera filstorlek (5MB)
            const maxSize = 5 * 1024 * 1024;
            if (file.size > maxSize) {
                alert('Bilden får inte vara större än 5MB.');
                e.target.value = '';
                return;
            }

            // Validera bilddimensioner
            const img = new Image();
            img.onload = function() {
                const maxWidth = 1920;
                const maxHeight = 1080;
                if (this.width > maxWidth || this.height > maxHeight) {
                    alert('Bilden är för stor. Max dimensioner är ' + maxWidth + 'x' + maxHeight + ' pixlar.');
                    e.target.value = '';
                }
            };
            img.src = URL.createObjectURL(file);
        });
    }
});
</script>
