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
 * Logout handler
 * 
 * This file handles user logout functionality:
 * - Destroys the user's session
 * - Clears authentication data
 * - Redirects to the login page
 */

// Start session and include required files
session_start();
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/database.php';
require_once __DIR__ . '/include/functions.php';
require_once __DIR__ . '/include/auth.php';

// Set flash message for user feedback
$_SESSION['flash_message'] = 'Du har loggats ut.';
$_SESSION['flash_type'] = 'info';

// Execute logout function to clear session and authentication data
logout();
