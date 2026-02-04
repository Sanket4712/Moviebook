<?php
/**
 * MovieBook - Watchlist API
 * 
 * Handles AJAX requests for watchlist operations.
 * Actions: add, remove, check, list
 */

header('Content-Type: application/json');

require_once '../includes/session.php';
require_once '../includes/db.php';

// Verify user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Please log in to manage your watchlist']);
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
        case 'add':
            if ($movieId <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid movie ID']);
                exit();
            }
            
            // Check if already in watchlist
            $stmt = $pdo->prepare("SELECT id FROM watchlist WHERE user_id = ? AND movie_id = ?");
            $stmt->execute([$userId, $movieId]);
            
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'Movie already in watchlist', 'inWatchlist' => true]);
                exit();
            }
            
            // Add to watchlist
            $stmt = $pdo->prepare("INSERT INTO watchlist (user_id, movie_id) VALUES (?, ?)");
            $stmt->execute([$userId, $movieId]);
            
            echo json_encode(['success' => true, 'message' => 'Added to watchlist', 'inWatchlist' => true]);
            break;
            
        case 'remove':
            if ($movieId <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid movie ID']);
                exit();
            }
            
            $stmt = $pdo->prepare("DELETE FROM watchlist WHERE user_id = ? AND movie_id = ?");
            $stmt->execute([$userId, $movieId]);
            
            echo json_encode(['success' => true, 'message' => 'Removed from watchlist', 'inWatchlist' => false]);
            break;
            
        case 'toggle':
            if ($movieId <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid movie ID']);
                exit();
            }
            
            // Check if in watchlist
            $stmt = $pdo->prepare("SELECT id FROM watchlist WHERE user_id = ? AND movie_id = ?");
            $stmt->execute([$userId, $movieId]);
            
            if ($stmt->fetch()) {
                // Remove
                $stmt = $pdo->prepare("DELETE FROM watchlist WHERE user_id = ? AND movie_id = ?");
                $stmt->execute([$userId, $movieId]);
                echo json_encode(['success' => true, 'message' => 'Removed from watchlist', 'inWatchlist' => false]);
            } else {
                // Add
                $stmt = $pdo->prepare("INSERT INTO watchlist (user_id, movie_id) VALUES (?, ?)");
                $stmt->execute([$userId, $movieId]);
                echo json_encode(['success' => true, 'message' => 'Added to watchlist', 'inWatchlist' => true]);
            }
            break;
            
        case 'check':
            if ($movieId <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid movie ID']);
                exit();
            }
            
            $stmt = $pdo->prepare("SELECT id FROM watchlist WHERE user_id = ? AND movie_id = ?");
            $stmt->execute([$userId, $movieId]);
            
            $inWatchlist = $stmt->fetch() ? true : false;
            echo json_encode(['success' => true, 'inWatchlist' => $inWatchlist]);
            break;
            
        case 'list':
            $stmt = $pdo->prepare("
                SELECT w.id, w.movie_id, w.added_at, m.title, m.poster_url, m.release_date, m.rating, m.genre
                FROM watchlist w
                JOIN movies m ON w.movie_id = m.id
                WHERE w.user_id = ?
                ORDER BY w.added_at DESC
            ");
            $stmt->execute([$userId]);
            $watchlist = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'watchlist' => $watchlist, 'count' => count($watchlist)]);
            break;
            
        case 'count':
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM watchlist WHERE user_id = ?");
            $stmt->execute([$userId]);
            $count = $stmt->fetchColumn();
            
            echo json_encode(['success' => true, 'count' => intval($count)]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action. Use: add, remove, toggle, check, list, count']);
    }
} catch (PDOException $e) {
    error_log("Watchlist API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
