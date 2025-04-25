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
 * Email verification and login handler
 * 
 * This file handles:
 * - Email verification process
 * - Login token validation
 * - User session creation
 * - Error handling and user feedback
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required configuration and function files
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/database.php';
require_once __DIR__ . '/include/functions.php';
require_once __DIR__ . '/include/auth.php';

// Get system name from environment variable or use default
$systemName = trim(getenv('SYSTEM_NAME'), '"\'') ?: 'Stimma';

// Initialize error and success message variables
$error = '';
$success = '';

// If already logged in, redirect to home page
if (isLoggedIn()) {
    $_SESSION['flash_message'] = 'Du är redan inloggad.';
    $_SESSION['flash_type'] = 'info';
    redirect('index.php');
    exit;
}

// Check if token and email parameters exist
if (!isset($_GET['token']) || !isset($_GET['email'])) {
    $error = 'Inloggningslänken är ofullständig. Vänligen använd hela länken från e-postmeddelandet.';
} else {
    $token = $_GET['token'];
    $email = $_GET['email'];
    
    // Check if user exists
    $user = queryOne("SELECT * FROM " . DB_DATABASE . ".users WHERE email = ?", [$email]);
    
    if (!$user) {
        $error = 'Ingen användare hittades med denna e-postadress.';
    } else if ($user['verified_at']) {
        // If user is already verified, create session directly
        createLoginSession($user);
        redirect('index.php');
        exit; // Stop execution here
    } else if ($user['verification_token'] !== $token) {
        $error = 'Inloggningslänken är ogiltig. Vänligen begär en ny inloggningslänk.';
    } else {
        // Create login session
        createLoginSession($user);
        
        // Clear verification token and set verified_at
        execute("UPDATE " . DB_DATABASE . ".users SET verification_token = NULL, verified_at = NOW() WHERE id = ?", [$user['id']]);
        
        // Redirect to home page
        redirect('index.php');
        exit; // Stop execution here
    }
}

// If we have an error message, display it
if (!empty($error)) {
    $page_title = $systemName . ' - Verifiera e-post';
    require_once 'include/header.php';
    ?>
    <!-- Error display container -->
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-body text-center p-4">
                        <!-- Error icon -->
                        <div class="mb-4">
                            <i class="bi bi-exclamation-circle text-danger" style="font-size: 3rem;"></i>
                        </div>
                        <h1 class="h4 mb-3">Verifiera inloggning</h1>
                        
                        <!-- Error message display -->
                        <div class="alert alert-danger d-flex align-items-center" role="alert">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <div><?= $error ?></div>
                        </div>
                        
                        <!-- Retry button -->
                        <div class="d-grid gap-2">
                            <a href="index.php" class="btn btn-primary">
                                <i class="bi bi-arrow-repeat me-2"></i> Försök igen
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    require_once 'include/footer.php';
} else {
    // If no error, show loading page
    $page_title = 'Bearbetar inloggning - ' . $systemName;
    require_once 'include/header.php';
    ?>
    <!-- Loading container -->
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-body text-center p-4">
                        <!-- Success icon -->
                        <div class="mb-4">
                            <i class="bi bi-mortarboard-fill text-primary" style="font-size: 3rem;"></i>
                        </div>
                        <h1 class="h4 mb-3">Bearbetar inloggning</h1>
                        <p class="text-muted mb-4">Vänta medan vi bearbetar din inloggningsbegäran...</p>
                        
                        <!-- Loading spinner -->
                        <div class="d-flex justify-content-center mb-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Laddar...</span>
                            </div>
                        </div>
                        
                        <p class="text-muted small">Du kommer att omdirigeras automatiskt</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    require_once 'include/footer.php';
}
