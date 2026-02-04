<?php
/**
 * MovieBook - Session Management
 * 
 * Initializes sessions and provides helper functions for authentication state.
 * IMPORTANT: All role decisions use active_role exclusively. users.role is DEPRECATED.
 */

// Prevent multiple session starts
if (session_status() === PHP_SESSION_NONE) {
    // Configure session before starting
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    
    session_start();
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current logged in user data
 * @return array|null User data or null if not logged in
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'role' => $_SESSION['active_role'] ?? 'user',  // Uses active_role
        'profile_pic' => $_SESSION['user_profile_pic'] ?? null
    ];
}

/**
 * Get current user's name
 * @return string
 */
function getUserName() {
    return $_SESSION['user_name'] ?? 'Guest';
}

/**
 * Get user ID
 * @return int|null
 */
function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current active role (the ONLY source for routing/authorization)
 * @return string
 */
function getActiveRole() {
    return $_SESSION['active_role'] ?? 'guest';
}

/**
 * DEPRECATED: Use getActiveRole() instead
 * Kept for backwards compatibility during migration
 * @return string
 */
function getUserRole() {
    return getActiveRole();
}

/**
 * Get available roles for a user from user_roles table
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 * @return array List of available roles
 */
function getAvailableRoles($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT role FROM user_roles WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Check if user has a specific role
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 * @param string $role Role to check
 * @return bool
 */
function userHasRole($pdo, $userId, $role) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM user_roles WHERE user_id = ? AND role = ?
    ");
    $stmt->execute([$userId, $role]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Set active role in session with security measures
 * Regenerates session ID to prevent role fixation
 * @param string $role Role to set
 */
function setActiveRole($role) {
    // Regenerate session ID for security
    session_regenerate_id(true);
    $_SESSION['active_role'] = $role;
    $_SESSION['role_set_time'] = time();
}

/**
 * Set user session after login
 * @param array $user User data from database
 * @param string $activeRole The role selected at login
 */
function setUserSession($user, $activeRole = 'user') {
    // Regenerate session ID for security
    session_regenerate_id(true);
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['active_role'] = $activeRole;  // Intent-based active role
    $_SESSION['user_profile_pic'] = $user['profile_pic'] ?? null;
    $_SESSION['login_time'] = time();
    $_SESSION['role_set_time'] = time();
}

/**
 * Destroy user session (logout)
 */
function destroyUserSession() {
    $_SESSION = [];
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

/**
 * Display flash message and clear it
 * @param string $type Type of message (success, error, info)
 * @return string|null
 */
function getFlashMessage($type = 'error') {
    $key = 'flash_' . $type;
    if (isset($_SESSION[$key])) {
        $message = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $message;
    }
    return null;
}

/**
 * Set flash message
 * @param string $message Message to display
 * @param string $type Type of message
 */
function setFlashMessage($message, $type = 'error') {
    $_SESSION['flash_' . $type] = $message;
}
