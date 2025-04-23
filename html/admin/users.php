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

// Kontrollera om användaren är inloggad som admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    $_SESSION['flash_message'] = 'Du måste vara inloggad som admin för att se denna sida.';
    $_SESSION['flash_type'] = 'warning';
    redirect('../index.php');
    exit;
}

// Sätt sidtitel
$page_title = 'Användarhantering';

// Hantera radering av användare
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $userId = $_GET['id'];
    
    // Hämta användarens e-postadress innan radering
    $userEmail = queryOne("SELECT email FROM " . DB_DATABASE . ".users WHERE id = ?", [$userId])['email'] ?? 'Okänd e-post';
    
    // Börja en transaktion för att säkerställa att både användare och framsteg raderas
    execute("START TRANSACTION");
    
    try {
        // Radera användarens framsteg
        execute("DELETE FROM " . DB_DATABASE . ".progress WHERE user_id = ?", [$userId]);
        
        // Radera användaren
        execute("DELETE FROM " . DB_DATABASE . ".users WHERE id = ?", [$userId]);
        
        // Commit transaktionen
        execute("COMMIT");
        
        // Logga borttagningen
        logActivity($_SESSION['user_email'], "Raderade användare med ID: " . $userId . " (E-post: " . $userEmail . ")");
        
        $_SESSION['message'] = "Användaren och tillhörande framsteg har raderats.";
        $_SESSION['message_type'] = "success";
    } catch (Exception $e) {
        // Rollback vid fel
        execute("ROLLBACK");
        
        $_SESSION['message'] = "Ett fel uppstod vid radering av användaren: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }
    
    // Omdirigera för att undvika omladdningsproblem
    header('Location: users.php');
    exit;
}

// Hantera ändring av admin-status
if (isset($_POST['action']) && $_POST['action'] === 'toggle_admin' && isset($_POST['user_id'])) {
    $userId = (int)$_POST['user_id'];
    $isAdmin = (int)$_POST['is_admin'];
    
    try {
        execute("UPDATE " . DB_DATABASE . ".users SET is_admin = ? WHERE id = ?", [$isAdmin, $userId]);
        
        // Logga ändringen
        logActivity($_SESSION['user_email'], "Ändrade admin-status för användare med ID: " . $userId . " till " . ($isAdmin ? "admin" : "icke-admin"));
        
        $_SESSION['message'] = "Användarens admin-status har uppdaterats.";
        $_SESSION['message_type'] = "success";
    } catch (Exception $e) {
        $_SESSION['message'] = "Ett fel uppstod vid uppdatering av admin-status: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }
    
    // Omdirigera för att undvika omladdningsproblem
    header('Location: users.php');
    exit;
}

// Hantera skapande av ny användare
if (isset($_POST['action']) && $_POST['action'] === 'create_user') {
    $email = $_POST['email'] ?? '';
    $isAdmin = isset($_POST['is_admin']) ? 1 : 0;
    
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Kontrollera om användaren redan finns
        $user = queryOne("SELECT * FROM " . DB_DATABASE . ".users WHERE email = ?", [$email]);
        
        if ($user) {
            $error = 'En användare med denna e-postadress finns redan.';
        } else {
            // Skapa användaren automatiskt med endast de kolumner som finns i tabellen
            execute("INSERT INTO " . DB_DATABASE . ".users (email, created_at) 
                     VALUES (?, NOW())", 
                     [$email]);
            
            // Logga skapandet av användaren
            logActivity($_SESSION['user_email'], "Skapade ny användare: " . $email);
            
            $_SESSION['message'] = 'Användaren har skapats.';
            $_SESSION['message_type'] = 'success';
            header('Location: users.php');
            exit;
        }
    } else {
        $error = 'Ange en giltig e-postadress.';
    }
}

// Hämta det totala antalet lektioner i systemet
$totalLessonsInSystem = queryOne("SELECT COUNT(*) as count FROM " . DB_DATABASE . ".lessons")['count'] ?? 0;

// Hämta alla användare med statistik
$users = queryAll("
    SELECT u.*, 
           COUNT(p.id) as completed_lessons
    FROM " . DB_DATABASE . ".users u
    LEFT JOIN " . DB_DATABASE . ".progress p ON u.id = p.user_id AND p.status = 'completed'
    GROUP BY u.id
    ORDER BY u.created_at DESC
");

// Inkludera header
require_once 'include/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <h6 class="m-0 font-weight-bold text-muted me-3">Användare</h6>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            + Användare
                        </button>
                    </div>
                    <div class="input-group" style="width: 300px;">
                        <span class="input-group-text bg-light border-0">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" id="emailFilter" class="form-control bg-light border-0 small" placeholder="Filtrera e-postadresser..." aria-label="Sök">
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>E-post</th>
                                    <th>Verifierad</th>
                                    <th>Admin</th>
                                    <th>Framsteg</th>
                                    <th>Åtgärder</th>
                                </tr>
                            </thead>
                            <tbody id="userTableBody">
                                <?php if (count($users) > 0): ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr class="user-row" data-email="<?= htmlspecialchars(strtolower($user['email'])) ?>" data-id="<?= $user['id'] ?>">
                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                            <td>
                                                <?php if ($user['verified_at']): ?>
                                                    <span class="badge bg-success">Ja</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Nej</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Är du säker på att du vill ändra admin-status för denna användare?');">
                                                    <input type="hidden" name="action" value="toggle_admin">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <input type="hidden" name="is_admin" value="<?= $user['is_admin'] ? '0' : '1' ?>">
                                                    <button type="submit" class="btn btn-sm <?= $user['is_admin'] ? 'btn-success' : 'btn-secondary' ?>">
                                                        <i class="bi <?= $user['is_admin'] ? 'bi-check-circle-fill' : 'bi-circle' ?>"></i>
                                                        <?= $user['is_admin'] ? 'Admin' : 'Ej admin' ?>
                                                    </button>
                                                </form>
                                            </td>
                                            <td>
                                                <?php
                                                $completedLessons = $user['completed_lessons'] ?? 0;
                                                $progressPercent = $totalLessonsInSystem > 0 ? round(($completedLessons / $totalLessonsInSystem) * 100) : 0;
                                                ?>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar bg-success" role="progressbar" 
                                                         style="width: <?= $progressPercent ?>%;"
                                                         aria-valuenow="<?= $progressPercent ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-danger delete-user" 
                                                        data-id="<?= $user['id'] ?>"
                                                        data-email="<?= htmlspecialchars($user['email']) ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">Inga användare hittades</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal för att lägga till användare -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addUserModalLabel">Lägg till ny användare</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Stäng"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_user">
                    <div class="mb-3">
                        <label for="email" class="form-label">E-postadress</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_admin" name="is_admin">
                            <label class="form-check-label" for="is_admin">
                                Administratör
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Avbryt</button>
                    <button type="submit" class="btn btn-primary">Skapa användare</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Definiera extra JavaScript
$extra_scripts = '<script>
    document.addEventListener("DOMContentLoaded", function() {            
        // Email filtering functionality
        const emailFilter = document.getElementById("emailFilter");
        const userRows = document.querySelectorAll(".user-row");
        const noResultsRow = document.createElement("tr");
        const userTableBody = document.getElementById("userTableBody");
        
        noResultsRow.innerHTML = "<td colspan=\"5\" class=\"text-center\">Inga användare matchar filtret</td>";
        noResultsRow.classList.add("no-results");
        noResultsRow.style.display = "none";
        userTableBody.appendChild(noResultsRow);
        
        emailFilter.addEventListener("keyup", function() {
            const searchTerm = emailFilter.value.toLowerCase().trim();
            let visibleCount = 0;
            
            userRows.forEach(row => {
                const email = row.getAttribute("data-email");
                
                if (email.includes(searchTerm)) {
                    row.style.display = "";
                    visibleCount++;
                } else {
                    row.style.display = "none";
                }
            });
            
            // Show or hide the "no results" message
            if (visibleCount === 0 && userRows.length > 0) {
                noResultsRow.style.display = "";
            } else {
                noResultsRow.style.display = "none";
            }
        });

        // Delete user functionality
        const deleteButtons = document.querySelectorAll(".delete-user");
        deleteButtons.forEach(button => {
            button.addEventListener("click", function() {
                const userId = this.getAttribute("data-id");
                const userEmail = this.getAttribute("data-email");
                
                if (confirm("Är du säker på att du vill radera användaren " + userEmail + "? Detta kan inte ångras.")) {
                    window.location.href = "users.php?action=delete&id=" + userId;
                }
            });
        });
    });
</script>';

// Inkludera footer
require_once 'include/footer.php';
