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
// Kontrollera att användaren är inloggad
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Kontrollera om användaren är admin eller redaktör
$user = queryOne("SELECT is_admin, is_editor FROM " . DB_DATABASE . ".users WHERE email = ?", [$_SESSION['user_email']]);
$isAdmin = $user && $user['is_admin'] == 1;
$isEditor = $user && $user['is_editor'] == 1;

if (!$isAdmin && !$isEditor) {
    // Användaren är varken admin eller redaktör - logga ut
    session_unset();
    session_destroy();
    header('Location: login.php?access=denied');
    exit;
}

// Enkel session timeout (30 minuter)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header('Location: login.php?timeout=1');
    exit;
}

// Uppdatera senaste aktiviteten
$_SESSION['last_activity'] = time();

// Generera CSRF-token om den inte redan finns
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Bestäm aktiv sida
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Admin - <?= $page_title ?? 'Administration' ?></title>
    <link rel="mask-icon" href="<?= BASE_PATH_URL ?>/images/safari-pinned-tab.svg" color="#007bff">
    <meta name="msapplication-TileColor" content="#007bff">
    <meta name="theme-color" content="#007bff">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    
    <!-- jQuery UI -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/base/jquery-ui.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/jquery-ui.min.js"></script>
    
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= BASE_PATH_URL ?>/include/css/style.css">
    
    <!-- CSRF Token för AJAX-anrop -->
    <script>
        const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?>';
    </script>
</head>
<body>
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <div class="sidebar d-flex flex-column h-100">
        <div class="px-3 mb-4 text-center">
            <h3 class="text-white"><img src="../images/logo.png" alt="Stimma" class="me-2 img-white" height="75"></h3>
        </div>
        <div class="d-flex flex-column h-100">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="index.php" class="nav-link text-white px-3 py-2 d-flex align-items-center <?= $current_page === 'index.php' ? 'active' : '' ?>">
                        <i class="bi bi-graph-up me-2"></i> Översikt
                    </a>
                </li>
                <li class="nav-item">
                    <a href="courses.php" class="nav-link text-white px-3 py-2 d-flex align-items-center <?= ($current_page === 'courses.php' || $current_page === 'lessons.php') ? 'active' : '' ?>">
                        <i class="bi bi-journal-text me-2"></i> Kurser
                    </a>
                </li>
                <?php if ($isAdmin): ?>
                <li class="nav-item">
                    <a href="users.php" class="nav-link text-white px-3 py-2 d-flex align-items-center <?= $current_page === 'users.php' ? 'active' : '' ?>">
                        <i class="bi bi-people me-2"></i> Användare
                    </a>
                </li>
                <li class="nav-item">
                    <a href="logs.php" class="nav-link text-white px-3 py-2 d-flex align-items-center <?= $current_page === 'logs.php' ? 'active' : '' ?>">
                        <i class="bi bi-clipboard-data me-2"></i> Loggar
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            <ul class="nav flex-column mt-auto">
                <li class="nav-item">
                    <a href="../index.php" class="nav-link text-white px-3 py-2 d-flex align-items-center">
                        <i class="bi bi-house-gear me-2"></i> Användarvyn
                    </a>
                </li>
                <li class="nav-item">
                    <div class="nav-link text-white px-3 py-2 d-flex align-items-center">
                        <i class="bi bi-person me-2"></i>
                        <?= $_SESSION['user_email'] ?>
                    </div>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_PATH_URL ?>/logout.php" class="nav-link text-white px-3 py-2 d-flex align-items-center mb-3">
                        <i class="bi bi-box-arrow-right me-2"></i> Logga ut
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="main-content">
        <nav class="navbar navbar-expand-lg navbar-light bg-white py-3">
            <div class="container-fluid px-4">
                <h5 class="mb-0"><?= $page_title ?? 'Administration' ?></h5>
            </div>
        </nav>

        <div class="container-fluid px-4 py-4">
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?= $_SESSION['message_type'] ?? 'info' ?>">
                    <?= $_SESSION['message'] ?>
                </div>
                <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            <?php endif; ?>
