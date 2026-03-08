<?php
/**
 * MovieBook - Theater Authentication Check
 * 
 * Include this file at the top of theater-only pages.
 * Checks active_role from session - no database queries.
 */

require_once __DIR__ . '/session.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    setFlashMessage('Please log in to access this page.', 'error');
    header('Location: /Moviebook/auth/login.php');
    exit();
}

// Check if active role is theater (session-based, no DB query)
if (getActiveRole() !== 'theater') {
    setFlashMessage('You do not have permission to access this page.', 'error');
    header('Location: /Moviebook/User/home.php');
    exit();
}
