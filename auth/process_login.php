<?php
/**
 * MovieBook - Login Processing
 * 
 * Handles user login form submission with intent-based role selection.
 * Role is determined by user choice, validated against user_roles table.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/session.php';
require_once '../includes/db.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit();
}

// Get form data
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$loginAs = $_POST['login_as'] ?? 'user';  // Default to 'user'
$remember = isset($_POST['remember']);

// Validate input
if (empty($email) || empty($password)) {
    setFlashMessage('Please fill in all fields.', 'error');
    header('Location: login.php');
    exit();
}

// Validate login_as value
$validRoles = ['user', 'theater', 'admin'];
if (!in_array($loginAs, $validRoles)) {
    $loginAs = 'user';  // Force default if invalid
}

// Check database connection
if (!$pdo) {
    setFlashMessage('Database connection failed. Please check if MySQL is running.', 'error');
    header('Location: login.php');
    exit();
}

try {
    // Detect column names in users table
    $columns = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_COLUMN, 0);
    
    // Map name column
    $nameColumn = 'name';
    if (in_array('username', $columns)) {
        $nameColumn = 'username';
    } elseif (in_array('full_name', $columns)) {
        $nameColumn = 'full_name';
    } elseif (in_array('fullname', $columns)) {
        $nameColumn = 'fullname';
    } elseif (!in_array('name', $columns)) {
        $nameColumn = 'email';
    }
    
    // Profile pic column
    $profilePicColumn = 'NULL as profile_pic';
    if (in_array('profile_pic', $columns)) {
        $profilePicColumn = 'profile_pic';
    } elseif (in_array('profile_picture', $columns)) {
        $profilePicColumn = 'profile_picture as profile_pic';
    } elseif (in_array('avatar', $columns)) {
        $profilePicColumn = 'avatar as profile_pic';
    }
    
    // Find user by email AND role (since multiple users can have the same email with different roles)
    // Join with user_roles to find the correct user record for the requested role
    // Handle profile_pic which may be NULL alias or actual column
    $profilePicSelect = (strpos($profilePicColumn, 'NULL') === 0) ? $profilePicColumn : "u.{$profilePicColumn}";
    $query = "SELECT u.id, u.{$nameColumn} as name, u.email, u.password, {$profilePicSelect} 
              FROM users u 
              INNER JOIN user_roles ur ON u.id = ur.user_id 
              WHERE u.email = ? AND ur.role = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$email, $loginAs]);
    $user = $stmt->fetch();
    
    // Check if user was found with the requested role
    if (!$user) {
        // Check if email exists at all (for better error message)
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $checkStmt->execute([$email]);
        if (!$checkStmt->fetch()) {
            setFlashMessage('No account found with this email address.', 'error');
        } else {
            // Email exists but not with this role
            if ($loginAs === 'theater') {
                setFlashMessage('You do not have theater access. Please contact support if you believe this is an error.', 'error');
            } elseif ($loginAs === 'admin') {
                setFlashMessage('You do not have admin access.', 'error');
            } else {
                setFlashMessage('Access denied for the requested role.', 'error');
            }
        }
        header('Location: login.php');
        exit();
    }
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        error_log("Login failed for {$email}: Password verification failed");
        setFlashMessage('Incorrect password. Please try again.', 'error');
        header('Location: login.php');
        exit();
    }
    
    // Password is correct and role is valid - create session with active_role
    setUserSession($user, $loginAs);
    
    // Set longer session if "remember me" is checked
    if ($remember) {
        ini_set('session.cookie_lifetime', 60 * 60 * 24 * 30); // 30 days
    }
    
    // Clear any redirect after login
    $redirect = $_SESSION['redirect_after_login'] ?? null;
    unset($_SESSION['redirect_after_login']);
    
    // Redirect based on active_role (set by setUserSession)
    if ($loginAs === 'admin') {
        header('Location: ../Admin/dashboard.php');
    } elseif ($loginAs === 'theater') {
        header('Location: ../Theater/dashboard.php');
    } elseif ($redirect) {
        header('Location: ' . $redirect);
    } else {
        header('Location: ../User/home.php');
    }
    exit();
    
} catch (PDOException $e) {
    error_log("Login PDO error: " . $e->getMessage());
    setFlashMessage('Database error: ' . $e->getMessage(), 'error');
    header('Location: login.php');
    exit();
}
