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

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $page_title ?? SITE_NAME ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <!-- jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.js"></script>
    
    <!-- Custom CSS -->
    <link href="/include/css/style.css" rel="stylesheet">
    
<head>
<body>


<?php if (isLoggedIn()): ?>

    <nav class="navbar navbar-expand-sm navbar-light bg-white shadow-sm">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center w-100">
                <!-- Logo med länk till startsidan -->
                <h1 class="h3 mb-0">
                    <a href="/">
                        <img src="/images/logo.png" height="50px" alt="<?= SITE_NAME ?>">
                    </a>
                </h1>
                
                <!-- Höger sida med användarinfo och knappar på samma rad -->
                <div class="d-flex align-items-center">
                    <!-- E-post med trunkering för långa adresser -->
                    <div class="btn btn-text text-muted p-1 d-inline-flex align-items-center justify-content-center" title="<?= $_SESSION['user_email'] ?>">
                        <?= $_SESSION['user_email'] ?>
                    </div>
                    
                    <!-- Knappar för admin och utloggning -->
                    <?php 
                    // Kontrollera om användaren är admin
                    $isAdmin = false;
                    if (isset($_SESSION['user_id'])) {
                        $user = queryOne("SELECT is_admin FROM " . DB_DATABASE . ".users WHERE id = ?", [$_SESSION['user_id']]);
                        $isAdmin = $user ? (bool)$user['is_admin'] : false;
                    }
                    if ($isAdmin): ?>
                        <a href="/admin/index.php" class="btn btn-link p-1 me-2 d-inline-flex align-items-center justify-content-center d-none d-sm-inline-flex" title="Administrera">
                            <i class="bi bi-gear"></i>
                        </a>
                    <?php endif; ?>
                    <a href="logout.php" class="btn btn-link p-1 d-inline-flex align-items-center justify-content-center" title="Logga ut">
                        <i class="bi bi-box-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </nav>

<?php endif; ?>
