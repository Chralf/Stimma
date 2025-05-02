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

// Hämta användarens e-post för användning i kursbehörigheter
$userEmail = $_SESSION['user_email'];

// Hämta kursdata om vi redigerar en befintlig kurs
$course = null;
if (isset($_GET['id'])) {
    $courseId = (int)$_GET['id'];
    $course = queryOne("SELECT * FROM " . DB_DATABASE . ".courses WHERE id = ?", [$courseId]);
    
    if (!$course) {
        $_SESSION['message'] = 'Kursen hittades inte.';
        $_SESSION['message_type'] = 'danger';
        header('Location: courses.php');
        exit;
    }
    
    // Kontrollera om användaren har behörighet att redigera kursen
    if (!$isAdmin) {
        // Kontrollera om användaren är redaktör för denna specifika kurs
        $isEditor = queryOne("SELECT 1 FROM " . DB_DATABASE . ".course_editors WHERE course_id = ? AND email = ?", [$courseId, $userEmail]);
        if (!$isEditor) {
            $_SESSION['message'] = 'Du har inte behörighet att redigera denna kurs.';
            $_SESSION['message_type'] = 'danger';
            header('Location: courses.php');
            exit;
        }
    }

    // Hämta kursredaktörer
    $editors = queryAll("SELECT ce.email, u.name 
                        FROM " . DB_DATABASE . ".course_editors ce 
                        JOIN " . DB_DATABASE . ".users u ON ce.email = u.email 
                        WHERE ce.course_id = ?", [$courseId]);
}

// Hantera formulärskickning
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = isset($_POST['status']) && $_POST['status'] === 'active' ? 'active' : 'inactive';
    $imageUrl = $course['image_url'] ?? null;
    
    if (empty($title)) {
        $error = 'Titel är obligatoriskt.';
    } else {
        // Hantera bilduppladdning
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
                    $imageUrl = '/upload/' . $fileName;
                    
                    // Ta bort gammal bild om den finns
                    if (isset($course['image_url']) && $course['image_url'] !== $imageUrl) {
                        $oldImagePath = __DIR__ . '/..' . $course['image_url'];
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
                        }
                    }
                } else {
                    $error = 'Kunde inte ladda upp bilden.';
                }
            }
        }
        
        if (!isset($error)) {
            if (isset($_GET['id'])) {
                // Uppdatera befintlig kurs
                execute("UPDATE " . DB_DATABASE . ".courses SET 
                        title = ?, 
                        description = ?, 
                        status = ?,
                        image_url = ?,
                        updated_at = NOW() 
                        WHERE id = ?", 
                        [$title, $description, $status, $imageUrl, $_GET['id']]);
                
                $_SESSION['message'] = 'Kursen har uppdaterats.';
            } else {
                // Hitta högsta sort_order
                $maxOrder = queryOne("SELECT MAX(sort_order) as max_order FROM " . DB_DATABASE . ".courses")['max_order'] ?? 0;
                
                // Hämta användarens ID
                $author = queryOne("SELECT id FROM " . DB_DATABASE . ".users WHERE email = ?", [$_SESSION['user_email']]);
                $authorId = $author ? $author['id'] : null;
                
                // Skapa ny kurs med nästa sort_order
                execute("INSERT INTO " . DB_DATABASE . ".courses 
                        (title, description, status, sort_order, image_url, author_id, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())", 
                        [$title, $description, $status, $maxOrder + 1, $imageUrl, $authorId]);
                
                // Hämta det nya kurs-ID:t
                $newCourseId = getDb()->lastInsertId();
                
                // Lägg till skaparen som redaktör för kursen
                execute("INSERT INTO " . DB_DATABASE . ".course_editors 
                        (course_id, email, created_by) 
                        VALUES (?, ?, ?)", 
                        [$newCourseId, $_SESSION['user_email'], $_SESSION['user_email']]);
                
                $_SESSION['message'] = 'Kursen har skapats.';
            }
            
            $_SESSION['message_type'] = 'success';
            header('Location: courses.php');
            exit;
        }
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
                    
                    <form method="post" action="" enctype="multipart/form-data">
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

                        <div class="mb-3">
                            <label for="image" class="form-label">Bild</label>
                            <?php if (!empty($course['image_url'])): ?>
                                <div class="mb-2">
                                    <p class="text-muted">Nuvarande bild:</p>
                                    <img src="../<?= htmlspecialchars($course['image_url']) ?>" alt="Kursbild" class="img-thumbnail" style="max-width: 200px;">
                                    <input type="hidden" name="image_url" value="<?= htmlspecialchars($course['image_url']) ?>">
                                    <div class="form-text">Sökväg: <?= htmlspecialchars($course['image_url']) ?></div>
                                </div>
                                <p class="text-muted">Ladda upp ny bild för att ersätta den nuvarande:</p>
                            <?php endif; ?>
                            <input type="file" class="form-control" id="image" name="image" accept="image/jpeg,image/png,image/gif">
                            <div class="form-text">Max 5MB. Tillåtna format: JPG, PNG, GIF</div>
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

<?php if (isset($course['id'])): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="card shadow mb-4">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-muted">Kursredaktörer</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="input-group">
                        <input type="text" class="form-control" id="editorSearch" placeholder="Sök efter användare...">
                        <button class="btn btn-primary" type="button" id="addEditorBtn" disabled>Lägg till redaktör</button>
                    </div>
                    <div id="userSearchResults" class="list-group mt-2" style="display: none;"></div>
                </div>
                <div id="editorsList">
                    <?php
                    $editors = queryAll("SELECT ce.email, u.name 
                                       FROM " . DB_DATABASE . ".course_editors ce 
                                       JOIN " . DB_DATABASE . ".users u ON ce.email COLLATE utf8mb4_swedish_ci = u.email COLLATE utf8mb4_swedish_ci 
                                       WHERE ce.course_id = ?", [$course['id']]);
                    
                    foreach ($editors as $editor):
                    ?>
                    <div class="d-flex justify-content-between align-items-center mb-2 editor-item" data-email="<?= htmlspecialchars($editor['email']) ?>">
                        <span><?= htmlspecialchars($editor['name'] ?? $editor['email']) ?></span>
                        <button class="btn btn-sm btn-danger remove-editor" type="button">Ta bort</button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

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

    // Hantera kursredaktörer
    const addEditorBtn = document.getElementById('addEditorBtn');
    const editorSearch = document.getElementById('editorSearch');
    const userSearchResults = document.getElementById('userSearchResults');
    const editorsList = document.getElementById('editorsList');
    const courseId = <?= $course['id'] ?? 'null' ?>;
    let selectedUser = null;

    if (addEditorBtn && courseId) {
        // Sök efter användare när användaren skriver
        editorSearch.addEventListener('input', function() {
            const search = this.value.trim();
            if (search.length < 2) {
                userSearchResults.style.display = 'none';
                addEditorBtn.disabled = true;
                return;
            }

            fetch(`ajax/search_users.php?search=${encodeURIComponent(search)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        userSearchResults.innerHTML = '';
                        if (data.users.length > 0) {
                            data.users.forEach(user => {
                                const item = document.createElement('a');
                                item.href = '#';
                                item.className = 'list-group-item list-group-item-action';
                                item.textContent = user.name ? `${user.name} (${user.email})` : user.email;
                                item.addEventListener('click', function(e) {
                                    e.preventDefault();
                                    editorSearch.value = user.name ? `${user.name} (${user.email})` : user.email;
                                    selectedUser = user;
                                    userSearchResults.style.display = 'none';
                                    addEditorBtn.disabled = false;
                                });
                                userSearchResults.appendChild(item);
                            });
                            userSearchResults.style.display = 'block';
                        } else {
                            const noResults = document.createElement('div');
                            noResults.className = 'list-group-item';
                            if (data.message) {
                                const alert = document.createElement('div');
                                alert.className = 'alert alert-warning mb-0';
                                alert.innerHTML = `
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    ${data.message}
                                `;
                                noResults.appendChild(alert);
                            } else {
                                noResults.textContent = 'Inga användare hittades';
                                noResults.classList.add('text-muted');
                            }
                            userSearchResults.appendChild(noResults);
                            userSearchResults.style.display = 'block';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        });

        // Lägg till redaktör
        addEditorBtn.addEventListener('click', function() {
            if (!selectedUser) return;

            fetch('ajax/add_course_editor.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `course_id=${courseId}&email=${encodeURIComponent(selectedUser.email)}&csrf_token=<?= $_SESSION['csrf_token'] ?>`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const editorItem = document.createElement('div');
                    editorItem.className = 'd-flex justify-content-between align-items-center mb-2 editor-item';
                    editorItem.setAttribute('data-email', selectedUser.email);
                    editorItem.innerHTML = `
                        <span>${selectedUser.name ? `${selectedUser.name} (${selectedUser.email})` : selectedUser.email}</span>
                        <button class="btn btn-sm btn-danger remove-editor" type="button">Ta bort</button>
                    `;
                    editorsList.appendChild(editorItem);
                    editorSearch.value = '';
                    selectedUser = null;
                    addEditorBtn.disabled = true;
                } else {
                    alert(data.message || 'Ett fel uppstod');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Ett fel uppstod');
            });
        });

        // Ta bort redaktör
        editorsList.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-editor')) {
                const editorItem = e.target.closest('.editor-item');
                const email = editorItem.getAttribute('data-email');

                fetch('ajax/remove_course_editor.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `course_id=${courseId}&email=${encodeURIComponent(email)}&csrf_token=<?= $_SESSION['csrf_token'] ?>`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        editorItem.remove();
                    } else {
                        alert(data.message || 'Ett fel uppstod');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Ett fel uppstod');
                });
            }
        });

        // Dölj sökresultat när man klickar utanför
        document.addEventListener('click', function(e) {
            if (!editorSearch.contains(e.target) && !userSearchResults.contains(e.target)) {
                userSearchResults.style.display = 'none';
            }
        });
    }
});
</script>
<?php endif; ?>

<?php
// Inkludera footer
require_once 'include/footer.php';
