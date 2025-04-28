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

// Hämta kursdata om vi redigerar en befintlig kurs
$course = null;
if (isset($_GET['id'])) {
    $course = queryOne("SELECT * FROM " . DB_DATABASE . ".courses WHERE id = ?", [$_GET['id']]);
    if (!$course) {
        $_SESSION['message'] = 'Kursen hittades inte.';
        $_SESSION['message_type'] = 'danger';
        header('Location: courses.php');
        exit;
    }
}

// Hantera formulärskickning
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = isset($_POST['status']) && $_POST['status'] === 'active' ? 'active' : 'inactive';
    
    if (empty($title)) {
        $error = 'Titel är obligatoriskt.';
    } else {
        if (isset($_GET['id'])) {
            // Uppdatera befintlig kurs
            execute("UPDATE " . DB_DATABASE . ".courses SET 
                    title = ?, 
                    description = ?, 
                    status = ?,
                    updated_at = NOW() 
                    WHERE id = ?", 
                    [$title, $description, $status, $_GET['id']]);
            
            $_SESSION['message'] = 'Kursen har uppdaterats.';
        } else {
            // Hitta högsta sort_order
            $maxOrder = queryOne("SELECT MAX(sort_order) as max_order FROM " . DB_DATABASE . ".courses")['max_order'] ?? 0;
            
            // Skapa ny kurs med nästa sort_order
            execute("INSERT INTO " . DB_DATABASE . ".courses 
                    (title, description, status, sort_order, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, NOW(), NOW())", 
                    [$title, $description, $status, $maxOrder + 1]);
            
            $_SESSION['message'] = 'Kursen har skapats.';
        }
        
        $_SESSION['message_type'] = 'success';
        header('Location: courses.php');
        exit;
    }
}

// Sätt sidtitel
$page_title = isset($_GET['id']) ? 'Redigera kurs' : 'Skapa ny kurs';

// Inkludera header
require_once 'include/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-muted"><?= $page_title ?></h6>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?= $error ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="id" value="<?= $course['id'] ?? '' ?>">
                        
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="title" name="title" 
                                   value="<?= htmlspecialchars($course['title'] ?? '') ?>" required>
                            <label for="title">Titel</label>
                        </div>

                        <div class="form-floating mb-3">
                            <textarea class="form-control" id="description" name="description" 
                                      style="height: 100px"><?= htmlspecialchars($course['description'] ?? '') ?></textarea>
                            <label for="description">Beskrivning</label>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="status" name="status" 
                                   value="active" <?= ($course['status'] ?? '') === 'active' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="status">Aktiv</label>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary">Spara</button>
                            <a href="courses.php" class="btn btn-secondary">Avbryt</a>
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
