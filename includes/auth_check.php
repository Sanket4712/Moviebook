<?php
/**
 * MovieBook - Authentication Check
 * 
 * Include this file at the top of protected pages.
 * Redirects to login page if user is not authenticated.
 */

require_once __DIR__ . '/session.php';

// Check if user is logged in
if (!isLoggedIn()) {
    // Store the intended destination
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    
    // Set error message
    setFlashMessage('Please log in to access this page.', 'error');
    
    // Redirect to login page
    header('Location: /Moviebook/auth/login.php');
    exit();
}

// Optional: Check session timeout (24 hours)
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 86400) {
    destroyUserSession();
    setFlashMessage('Your session has expired. Please log in again.', 'error');
    header('Location: /Moviebook/auth/login.php');
    exit();
}
