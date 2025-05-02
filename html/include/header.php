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
 * Header template file
 * 
 * This file contains the common header elements for all pages including:
 * - HTML head section with meta tags and required CSS/JS
 * - Navigation bar for logged-in users
 * - User information and admin controls
 * 
 * Required variables:
 * - $page_title: Page specific title
 * - SITE_NAME: Global site name constant

 */

// Start of HTML document with Swedish language setting
?>
<!DOCTYPE html>
<html lang="sv">
<head>
    <!-- Basic meta tags for character encoding and responsive design -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($page_title ?? SITE_NAME) ?></title>
    
    <!-- Preconnect to CDN domains for better performance -->
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    
    <!-- External CSS dependencies -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <!-- External JavaScript dependencies -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js" defer></script>
    
    <!-- Custom CSS for site-specific styles -->
    <link href="include/css/style.css" rel="stylesheet">
</head>
<body>

<?php if (isLoggedIn()): ?>
    <!-- Navigation bar for logged-in users with responsive design -->
    <nav class="navbar navbar-expand-sm navbar-light bg-white shadow-sm" role="navigation" aria-label="Main navigation">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center w-100">
                <!-- Logo section with link to homepage -->
                <h1 class="h3 mb-0">
                    <a href="index.php" aria-label="Hem">
                        <img src="images/logo.png" height="50px" alt="<?= htmlspecialchars(SITE_NAME) ?>">
                    </a>
                </h1>
                
                <!-- Right side section with user info and action buttons -->
                <div class="d-flex align-items-center">
                    <!-- User email display with truncation for long addresses -->
                    <div class="btn btn-text text-muted p-1 d-inline-flex align-items-center justify-content-center" 
                         title="<?= htmlspecialchars($_SESSION['user_email'] ?? '') ?>"
                         aria-label="Your email address">
                        <?= htmlspecialchars($_SESSION['user_email'] ?? '') ?>
                    </div>
                    
                    <!-- Admin and logout buttons -->
                    <?php 
                    // Check if user has admin privileges or is a course editor
                    $isAdmin = false;
                    $isCourseEditor = false;
                    
                    if (isset($_SESSION['user_id'])) {
                        // Check admin status
                        $user = queryOne("SELECT is_admin FROM " . DB_DATABASE . ".users WHERE id = ?", [$_SESSION['user_id']]);
                        $isAdmin = $user ? (bool)$user['is_admin'] : false;
                        
                        // Check if user is a course editor
                        $editor = queryOne("SELECT 1 FROM " . DB_DATABASE . ".course_editors WHERE email = ?", [$_SESSION['user_email']]);
                        $isCourseEditor = $editor ? true : false;
                    }
                    
                    if ($isAdmin || $isCourseEditor): ?>
                        <!-- Admin panel link (hidden on small screens) -->
                        <a href="admin/index.php" 
                           class="btn btn-link p-1 me-2 d-inline-flex align-items-center justify-content-center d-none d-sm-inline-flex" 
                           title="Administrera"
                           aria-label="Administration panel">
                            <i class="bi bi-gear text-dark" aria-hidden="true"></i>
                        </a>
                    <?php endif; ?>
                    <!-- Logout button -->
                    <a href="logout.php" 
                       class="btn btn-link p-1 d-inline-flex align-items-center justify-content-center" 
                       title="Logga ut"
                       aria-label="Log out">
                        <i class="bi bi-box-arrow-right text-dark" aria-hidden="true"></i>
                    </a>
                </div>
            </div>
        </div>
    </nav>
<?php endif; ?>
