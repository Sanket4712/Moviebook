<?php
/**
 * MovieBook - Signup Processing
 * 
 * Handles user registration form submission.
 */

require_once '../includes/session.php';
require_once '../includes/db.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: signup.php');
    exit();
}

// Get form data
$fullName = trim($_POST['fullName'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirmPassword'] ?? '';

// Validate input
$errors = [];

if (empty($fullName)) {
    $errors[] = 'Full name is required.';
}

if (empty($email)) {
    $errors[] = 'Email is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address.';
}

if (empty($phone)) {
    $errors[] = 'Phone number is required.';
}

if (empty($password)) {
    $errors[] = 'Password is required.';
} elseif (strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters long.';
}

if ($password !== $confirmPassword) {
    $errors[] = 'Passwords do not match.';
}

if (!empty($errors)) {
    setFlashMessage(implode(' ', $errors), 'error');
    header('Location: signup.php');
    exit();
}

// Check database connection
if (!$pdo) {
    setFlashMessage('Database connection error. Please try again later.', 'error');
    header('Location: signup.php');
    exit();
}

try {
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        setFlashMessage('An account with this email already exists.', 'error');
        header('Location: signup.php');
        exit();
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new user
    $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'user')");
    $stmt->execute([$fullName, $email, $phone, $hashedPassword]);
    
    $userId = $pdo->lastInsertId();
    
    // Auto-login after signup
    $user = [
        'id' => $userId,
        'name' => $fullName,
        'email' => $email,
        'role' => 'user',
        'profile_pic' => null
    ];
    
    setUserSession($user);
    
    setFlashMessage('Account created successfully! Welcome to MovieBook.', 'success');
    header('Location: /movie book/User/home.php');
    exit();
    
} catch (PDOException $e) {
    error_log("Signup error: " . $e->getMessage());
    setFlashMessage('An error occurred. Please try again.', 'error');
    header('Location: signup.php');
    exit();
}
