<?php
/**
 * MovieBook - Logout
 * 
 * Destroys user session and redirects to landing page.
 */

require_once '../includes/session.php';

// Destroy the session
destroyUserSession();

// Redirect to landing page
header('Location: /Moviebook/index.php');
exit();
