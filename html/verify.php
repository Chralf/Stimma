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
// Starta session om den inte redan är startad
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/database.php';
require_once __DIR__ . '/include/functions.php';
require_once __DIR__ . '/include/auth.php';

// Hämta systemnamn från miljövariabel eller använd standardvärde
$systemName = trim(getenv('SYSTEM_NAME'), '"\'') ?: 'Stimma';

$error = '';
$success = '';

// Om redan inloggad, omdirigera till startsidan
if (isLoggedIn()) {
    $_SESSION['flash_message'] = 'Du är redan inloggad.';
    $_SESSION['flash_type'] = 'info';
    redirect('index.php');
    exit;
}

// Kontrollera om token och e-post finns
if (!isset($_GET['token']) || !isset($_GET['email'])) {
    $error = 'Inloggningslänken är ofullständig. Vänligen använd hela länken från e-postmeddelandet.';
} else {
    $token = $_GET['token'];
    $email = $_GET['email'];
    
    // Kontrollera om användaren finns
    $user = queryOne("SELECT * FROM " . DB_DATABASE . ".users WHERE email = ?", [$email]);
    
    if (!$user) {
        $error = 'Ingen användare hittades med denna e-postadress.';
    } else if ($user['verified_at']) {
        // Om användaren redan är verifierad, skapa session direkt
        createLoginSession($user);
        redirect('index.php');
        exit; // Stoppa exekvering här
    } else if ($user['verification_token'] !== $token) {
        $error = 'Inloggningslänken är ogiltig. Vänligen begär en ny inloggningslänk.';
    } else {
        // Skapa inloggningssession
        createLoginSession($user);
        
        // Rensa verifieringstoken och sätt verified_at
        execute("UPDATE " . DB_DATABASE . ".users SET verification_token = NULL, verified_at = NOW() WHERE id = ?", [$user['id']]);
        
        // Omdirigera till startsidan
        redirect('index.php');
        exit; // Stoppa exekvering här
    }
}

// Om vi har ett felmeddelande, visa det
if (!empty($error)) {
    $page_title = $systemName . ' - Verifiera e-post';
    require_once 'include/header.php';
    ?>
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="mb-4">
                            <i class="bi bi-exclamation-circle text-danger" style="font-size: 3rem;"></i>
                        </div>
                        <h1 class="h4 mb-3">Verifiera inloggning</h1>
                        
                        <div class="alert alert-danger d-flex align-items-center" role="alert">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <div><?= $error ?></div>
                        </div>
                        
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
    // Om vi inte har ett felmeddelande, visa en laddningssida
    $page_title = 'Bearbetar inloggning - ' . $systemName;
    require_once 'include/header.php';
    ?>
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="mb-4">
                            <i class="bi bi-mortarboard-fill text-primary" style="font-size: 3rem;"></i>
                        </div>
                        <h1 class="h4 mb-3">Bearbetar inloggning</h1>
                        <p class="text-muted mb-4">Vänta medan vi bearbetar din inloggningsbegäran...</p>
                        
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
