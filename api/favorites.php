<?php
/**
 * MovieBook - Favorites/Likes API
 * 
 * Handles AJAX requests for favorites (likes) operations.
 * Actions: toggle, add, remove, list
 */

header('Content-Type: application/json');

require_once '../includes/session.php';
require_once '../includes/db.php';

// Verify user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Please log in to manage your favorites']);
    exit();
}

$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$movieId = intval($_POST['movie_id'] ?? $_GET['movie_id'] ?? 0);

if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

try {
    switch ($action) {
        case 'toggle':
            if ($movieId <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid movie ID']);
                exit();
            }
            
            // Check if already favorited
            $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND movie_id = ?");
            $stmt->execute([$userId, $movieId]);
            
            if ($stmt->fetch()) {
                // Remove from favorites
                $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND movie_id = ?");
                $stmt->execute([$userId, $movieId]);
                echo json_encode(['success' => true, 'message' => 'Removed from favorites', 'liked' => false]);
            } else {
                // Check limit (max 4 favorites like Letterboxd)
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ?");
                $stmt->execute([$userId]);
                $count = $stmt->fetchColumn();
                
                if ($count >= 4) {
                    echo json_encode(['success' => false, 'error' => 'Maximum 4 favorites allowed. Remove one first.', 'limit_reached' => true]);
                    exit();
                }
                
                // Add to favorites
                $stmt = $pdo->prepare("INSERT INTO favorites (user_id, movie_id) VALUES (?, ?)");
                $stmt->execute([$userId, $movieId]);
                echo json_encode(['success' => true, 'message' => 'Added to favorites', 'liked' => true]);
            }
            break;
            
        case 'add':
            if ($movieId <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid movie ID']);
                exit();
            }
            
            // Check if already exists
            $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND movie_id = ?");
            $stmt->execute([$userId, $movieId]);
            
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'Already in favorites', 'liked' => true]);
                exit();
            }
            
            $stmt = $pdo->prepare("INSERT INTO favorites (user_id, movie_id) VALUES (?, ?)");
            $stmt->execute([$userId, $movieId]);
            
            echo json_encode(['success' => true, 'message' => 'Added to favorites', 'liked' => true]);
            break;
            
        case 'remove':
            if ($movieId <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid movie ID']);
                exit();
            }
            
            $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND movie_id = ?");
            $stmt->execute([$userId, $movieId]);
            
            echo json_encode(['success' => true, 'message' => 'Removed from favorites', 'liked' => false]);
            break;
            
        case 'check':
            if ($movieId <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid movie ID']);
                exit();
            }
            
            $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND movie_id = ?");
            $stmt->execute([$userId, $movieId]);
            
            $liked = $stmt->fetch() ? true : false;
            echo json_encode(['success' => true, 'liked' => $liked]);
            break;
            
        case 'list':
            $stmt = $pdo->prepare("
                SELECT f.id, f.movie_id, f.added_at, f.position,
                       m.title, m.poster_url, m.release_date, m.rating, m.genre
                FROM favorites f
                JOIN movies m ON f.movie_id = m.id
                WHERE f.user_id = ?
                ORDER BY f.position ASC, f.added_at DESC
            ");
            $stmt->execute([$userId]);
            $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'favorites' => $favorites, 'count' => count($favorites)]);
            break;
            
        case 'count':
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ?");
            $stmt->execute([$userId]);
            $count = $stmt->fetchColumn();
            
            echo json_encode(['success' => true, 'count' => intval($count)]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action. Use: toggle, add, remove, check, list, count']);
    }
} catch (PDOException $e) {
    error_log("Favorites API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
