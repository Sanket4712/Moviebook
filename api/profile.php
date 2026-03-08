<?php
/**
 * Profile Update API
 * Handles profile picture, bio, username updates
 */

session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

// Check auth
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'update_bio':
        $bio = trim($_POST['bio'] ?? '');
        if (strlen($bio) > 200) {
            echo json_encode(['success' => false, 'error' => 'Bio too long (max 200 chars)']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("UPDATE users SET bio = ? WHERE id = ?");
            $stmt->execute([$bio, $userId]);
            echo json_encode(['success' => true, 'bio' => $bio]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
        break;
        
    case 'update_username':
        $username = trim($_POST['username'] ?? '');
        if (strlen($username) < 2 || strlen($username) > 50) {
            echo json_encode(['success' => false, 'error' => 'Username must be 2-50 characters']);
            exit;
        }
        
        // Check if username taken
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE name = ? AND id != ?");
            $stmt->execute([$username, $userId]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'Username already taken']);
                exit;
            }
            
            $stmt = $pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
            $stmt->execute([$username, $userId]);
            $_SESSION['user_name'] = $username;
            echo json_encode(['success' => true, 'username' => $username]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
        break;
    
    // Combined update for edit profile modal
    case 'update_all':
        $username = trim($_POST['username'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        
        // Validate
        if (strlen($username) < 2 || strlen($username) > 50) {
            echo json_encode(['success' => false, 'error' => 'Username must be 2-50 characters']);
            exit;
        }
        if (strlen($bio) > 200) {
            echo json_encode(['success' => false, 'error' => 'Bio too long (max 200 chars)']);
            exit;
        }
        
        try {
            // Check if username taken by another user
            $stmt = $pdo->prepare("SELECT id FROM users WHERE name = ? AND id != ?");
            $stmt->execute([$username, $userId]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'Username already taken']);
                exit;
            }
            
            $stmt = $pdo->prepare("UPDATE users SET name = ?, bio = ? WHERE id = ?");
            $stmt->execute([$username, $bio, $userId]);
            $_SESSION['user_name'] = $username;
            
            echo json_encode([
                'success' => true, 
                'username' => $username, 
                'bio' => $bio
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
        break;
        
    case 'upload_avatar':
        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'No file uploaded']);
            exit;
        }
        
        $file = $_FILES['avatar'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        if (!in_array($file['type'], $allowed)) {
            echo json_encode(['success' => false, 'error' => 'Invalid file type']);
            exit;
        }
        
        if ($file['size'] > 2 * 1024 * 1024) { // 2MB limit
            echo json_encode(['success' => false, 'error' => 'File too large (max 2MB)']);
            exit;
        }
        
        // Create uploads directory
        $uploadDir = '../uploads/avatars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate unique filename
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
        $filepath = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Update database - use profile_pic column (matches schema)
            $avatarUrl = '/Moviebook/uploads/avatars/' . $filename;
            try {
                $stmt = $pdo->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
                $stmt->execute([$avatarUrl, $userId]);
                echo json_encode(['success' => true, 'avatar_url' => $avatarUrl]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save file']);
        }
        break;
        
    case 'get_profile':
        try {
            $stmt = $pdo->prepare("SELECT name, email, bio, profile_pic FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'user' => $user]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
        break;
    
    // Get all stats for real-time updates
    case 'get_stats':
        try {
            $stats = [];
            
            // Films watched (diary count)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM diary WHERE user_id = ?");
            $stmt->execute([$userId]);
            $stats['films'] = (int)$stmt->fetchColumn();
            $stats['diary'] = $stats['films'];
            
            // Watchlist count
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM watchlist WHERE user_id = ?");
            $stmt->execute([$userId]);
            $stats['watchlist'] = (int)$stmt->fetchColumn();
            
            // Lists count
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_lists WHERE user_id = ?");
            $stmt->execute([$userId]);
            $stats['lists'] = (int)$stmt->fetchColumn();
            
            // Favorites/likes count
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ?");
            $stmt->execute([$userId]);
            $stats['likes'] = (int)$stmt->fetchColumn();
            
            echo json_encode(['success' => true, 'stats' => $stats]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
