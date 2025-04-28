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

// Kontrollera att course_id finns
if (!isset($_GET['course_id']) || !is_numeric($_GET['course_id'])) {
    $_SESSION['message'] = "Ingen kurs vald.";
    $_SESSION['message_type'] = "danger";
    header('Location: courses.php');
    exit;
}

$courseId = (int)$_GET['course_id'];

// Hämta kursinformation
$course = queryOne("SELECT * FROM " . DB_DATABASE . ".courses WHERE id = ?", [$courseId]);

if (!$course) {
    $_SESSION['message'] = "Kursen kunde inte hittas.";
    $_SESSION['message_type'] = "danger";
    header('Location: courses.php');
    exit;
}

// Sätt sidtitel med kursnamn
$page_title = 'Lektionshantering - ' . htmlspecialchars($course['title']);

// Extra CSS för sorterbar tabell
$extra_css = '.grip-handle { cursor: move; color: #adb5bd; } .grip-handle:hover { color: #6c757d; }';

// Hämta alla lektioner för kursen
$lessons = query("SELECT * FROM " . DB_DATABASE . ".lessons WHERE course_id = ? ORDER BY sort_order, title", [$courseId]);

// Definiera extra JavaScript för drag-and-drop sortering
$extra_scripts = '<script>
    const CSRF_TOKEN = \'' . htmlspecialchars($_SESSION['csrf_token']) . '\';
    
    $(document).ready(function() {
        $("#sortable-lessons").sortable({
            items: "tr",
            handle: ".grip-handle",
            axis: "y",
            helper: function(e, tr) {
                var $originals = tr.children();
                var $helper = tr.clone();
                $helper.children().each(function(index) {
                    $(this).width($originals.eq(index).width());
                });
                return $helper;
            },
            update: function(event, ui) {
                // Samla in den nya ordningen
                const lessonIds = [];
                $("#sortable-lessons tr").each(function(index) {
                    lessonIds.push({
                        id: $(this).data("id"),
                        order: index
                    });
                });
                
                // Skicka den nya ordningen till servern
                $.ajax({
                    url: "update_lesson_order.php",
                    method: "POST",
                    headers: {
                        "X-CSRF-Token": CSRF_TOKEN
                    },
                    data: { 
                        lessons: JSON.stringify(lessonIds),
                        course_id: ' . $courseId . '
                    },
                    success: function(response) {
                        console.log("Lektionsordning uppdaterad");
                    },
                    error: function(error) {
                        console.error("Fel vid uppdatering av lektionsordning", error);
                    }
                });
            }
        });
    });
</script>';

// Inkludera header
require_once 'include/header.php';
?>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-muted">Lektioner</h6>
        <a href="edit_lesson.php?course_id=<?= $courseId ?>" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-lg"></i> Ny lektion
        </a>
    </div>
    <div class="card-body">
        <?php if (count($lessons) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th width="50"></th>
                            <th>Titel</th>
                            <th>Status</th>
                            <th>Quiz</th>
                            <th>AI</th>
                            <th>Ordning</th>
                            <th>Skapad</th>
                            <th>Åtgärder</th>
                        </tr>
                    </thead>
                    <tbody id="sortable-lessons">
                        <?php foreach ($lessons as $lesson): ?>
                            <tr class="sortable-row" data-id="<?= $lesson['id'] ?>">
                                <td>
                                    <i class="bi bi-grip-vertical grip-handle"></i>
                                </td>
                                <td>
                                    <a href="edit_lesson.php?id=<?= $lesson['id'] ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($lesson['title']) ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $lesson['status'] === 'active' ? 'success' : 'secondary' ?>">
                                        <?= $lesson['status'] === 'active' ? 'Aktiv' : 'Inaktiv' ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($lesson['quiz_question'])): ?>
                                        <span class="badge bg-primary">
                                            <i class="bi bi-check-circle-fill"></i> Quiz
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">
                                            <i class="bi bi-dash-circle"></i> Inget quiz
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($lesson['ai_instruction']) || !empty($lesson['ai_prompt'])): ?>
                                        <span class="badge bg-info">
                                            <i class="bi bi-robot"></i> AI-stöd
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">
                                            <i class="bi bi-dash-circle"></i> Inget AI-stöd
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $lesson['sort_order'] ?></td>
                                <td><?= date('Y-m-d', strtotime($lesson['created_at'])) ?></td>
                                <td>
                                    <a href="edit_lesson.php?id=<?= $lesson['id'] ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="delete_lesson.php?id=<?= $lesson['id'] ?>&csrf_token=<?= htmlspecialchars($_SESSION['csrf_token']) ?>" 
                                       onclick="return confirm('Är du säker på att du vill radera denna lektion?')"
                                       class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                Inga lektioner har skapats för denna kurs än. Klicka på "Ny lektion" för att komma igång.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Inkludera footer
require_once 'include/footer.php';
