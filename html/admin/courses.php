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

// Hantera radering av kurs
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $courseId = (int)$_GET['id'];
    
    // Kontrollera om användaren har behörighet att radera kursen
    if (!isAdmin($_SESSION['user_email'])) {
        // Kontrollera om användaren är redaktör för kursen
        $isEditor = queryOne("SELECT 1 FROM " . DB_DATABASE . ".course_editors WHERE course_id = ? AND email = ?", [$courseId, $_SESSION['user_email']]);
        if (!$isEditor) {
            $_SESSION['message'] = 'Du har inte behörighet att radera denna kurs.';
            $_SESSION['message_type'] = 'danger';
            header('Location: courses.php');
            exit;
        }
    }
    
    // Kontrollera om kursen har lektioner
    $lessons = query("SELECT COUNT(*) as count FROM " . DB_DATABASE . ".lessons WHERE course_id = ?", [$courseId]);
    $lessonCount = $lessons[0]['count'];

    if ($lessonCount > 0) {
        $_SESSION['message'] = 'Kursen kan inte raderas eftersom den innehåller lektioner. Ta bort alla lektioner först.';
        $_SESSION['message_type'] = 'warning';
    } else {
        try {
            execute("DELETE FROM " . DB_DATABASE . ".courses WHERE id = ?", [$courseId]);
            $_SESSION['message'] = 'Kursen har raderats.';
            $_SESSION['message_type'] = 'success';
        } catch (Exception $e) {
            $_SESSION['message'] = 'Ett fel uppstod när kursen skulle raderas.';
            $_SESSION['message_type'] = 'danger';
        }
    }
    
    header('Location: courses.php');
    exit;
}

// Sätt sidtitel
$page_title = 'Kurshantering';

// Hämta användarens e-post
$userEmail = $_SESSION['user_email'];

// Hämta användarens rättigheter
$user = queryOne("SELECT is_admin, is_editor FROM " . DB_DATABASE . ".users WHERE email = ?", [$userEmail]);
$isAdmin = $user && $user['is_admin'] == 1;

// Hämta kurser baserat på användarens behörighet
if ($isAdmin) {
    // Administratörer ser alla kurser
    $courses = queryAll("
        SELECT c.*, COUNT(l.id) as lesson_count 
        FROM " . DB_DATABASE . ".courses c 
        LEFT JOIN " . DB_DATABASE . ".lessons l ON c.id = l.course_id 
        GROUP BY c.id 
        ORDER BY c.sort_order ASC
    ");
} else {
    // Redaktörer ser bara sina tilldelade kurser
    $courses = queryAll("
        SELECT c.*, COUNT(l.id) as lesson_count 
        FROM " . DB_DATABASE . ".courses c 
        LEFT JOIN " . DB_DATABASE . ".lessons l ON c.id = l.course_id 
        INNER JOIN " . DB_DATABASE . ".course_editors ce ON c.id = ce.course_id 
        WHERE ce.email = ?
        GROUP BY c.id 
        ORDER BY c.sort_order ASC
    ", [$userEmail]);
}

// Inkludera header
require_once 'include/header.php';
?>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-muted">Kurser</h6>
        <div class="d-flex gap-2">
            <a href="import.php" class="btn btn-sm btn-secondary">
                <i class="bi bi-upload"></i> Importera kurs
            </a>
            <a href="edit_course.php" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-lg"></i> Ny kurs
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th style="width: 50px;"></th>
                        <th>Titel</th>
                        <th>Status</th>
                        <th>Antal lektioner</th>
                        <th style="width: 120px;">Åtgärder</th>
                    </tr>
                </thead>
                <tbody id="sortable-courses">
                    <?php foreach ($courses as $course): 
                        $lessons = query("SELECT * FROM " . DB_DATABASE . ".lessons WHERE course_id = ? ORDER BY sort_order, title", [$course['id']]);
                        $lessonCount = count($lessons);
                    ?>
                    <tr data-id="<?= $course['id'] ?>">
                        <td>
                            <i class="bi bi-grip-vertical grip-handle text-muted"></i>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <a href="lessons.php?course_id=<?= $course['id'] ?>" class="text-decoration-none">
                                    <?= htmlspecialchars($course['title']) ?>
                                </a>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-<?= $course['status'] === 'active' ? 'success' : 'secondary' ?>">
                                <?= $course['status'] === 'active' ? 'Aktiv' : 'Inaktiv' ?>
                            </span>
                        </td>
                        <td><?= $lessonCount ?></td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="lessons.php?course_id=<?= $course['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-list-ul"></i>
                                </a>
                                <a href="edit_course.php?id=<?= $course['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="export.php?id=<?= $course['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Exportera kurs">
                                    <i class="bi bi-box-arrow-up"></i>
                                </a>
                                <a href="delete_course.php?id=<?= $course['id'] ?>&csrf_token=<?= htmlspecialchars($_SESSION['csrf_token']) ?>" 
                                   onclick="return confirm('Är du säker på att du vill radera denna kurs? Om kursen innehåller lektioner måste dessa tas bort först.')"
                                   class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
        </div>
    </div>

<?php
// Definiera extra JavaScript
$extra_scripts = '<script>
    const CSRF_TOKEN = \'' . htmlspecialchars($_SESSION['csrf_token']) . '\';
    
    function deleteCourse(id) {
        if (confirm(\'Är du säker på att du vill ta bort denna kurs? Alla lektioner i kursen kommer också att tas bort.\')) {
            $.post(\'delete_course.php\', { id: id }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(\'Ett fel uppstod vid borttagning av kursen.\');
                }
            });
        }
    }

    $(document).ready(function() {
        // Hantera expandering av lektionslistan
        $(".expand-button").click(function() {
            const courseId = $(this).data("course-id");
            const lessonContainer = $("#lessons-" + courseId);
            const icon = $(this).find("i");
            
            if (lessonContainer.is(":visible")) {
                lessonContainer.hide();
                icon.removeClass("bi-chevron-down").addClass("bi-chevron-right");
            } else {
                lessonContainer.show();
                icon.removeClass("bi-chevron-right").addClass("bi-chevron-down");
            }
        });

        // Sorterbar funktionalitet för kurser
        $("#sortable-courses").sortable({
            items: "tr:not(.lesson-container)",
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
                const courseIds = [];
                $("#sortable-courses tr:not(.lesson-container)").each(function(index) {
                    const id = $(this).data("id");
                    if (id) { // Kontrollera att vi bara inkluderar kurser med ID
                        courseIds.push({
                            id: id,
                            order: index
                        });
                    }
                });
                
                // Skicka den nya ordningen till servern
                $.ajax({
                    url: "update_course_order.php",
                    method: "POST",
                    headers: {
                        "X-CSRF-Token": CSRF_TOKEN
                    },
                    data: { 
                        courses: JSON.stringify(courseIds)
                    },
                    success: function(response) {
                        console.log("Kursordning uppdaterad");
                    },
                    error: function(error) {
                        console.error("Fel vid uppdatering av kursordning", error);
                    }
                });
            }
        });
    });
</script>';

// Inkludera footer
require_once 'include/footer.php';
