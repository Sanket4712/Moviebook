<?php
/**
 * MovieBook - Diary API
 * 
 * Handles AJAX requests for diary (watched movies) operations.
 * Actions: add, update, delete, list
 */

header('Content-Type: application/json');

require_once '../includes/session.php';
require_once '../includes/db.php';

// Verify user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Please log in to manage your diary']);
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
            
            $watchedDate = $_POST['watched_date'] ?? date('Y-m-d');
            $rating = isset($_POST['rating']) ? floatval($_POST['rating']) : null;
            $liked = isset($_POST['liked']) ? (bool)$_POST['liked'] : false;
            $rewatch = isset($_POST['rewatch']) ? (bool)$_POST['rewatch'] : false;
            $review = trim($_POST['review'] ?? '');
            
            // Insert diary entry
            $stmt = $pdo->prepare("
                INSERT INTO diary (user_id, movie_id, watched_date, rating, liked, rewatch, review)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $movieId, $watchedDate, $rating, $liked, $rewatch, $review]);
            
            $entryId = $pdo->lastInsertId();
            
            // Check if movie was in watchlist before removing
            // (so client can sync watchlist button state)
            $stmt = $pdo->prepare("SELECT id FROM watchlist WHERE user_id = ? AND movie_id = ?");
            $stmt->execute([$userId, $movieId]);
            $wasInWatchlist = $stmt->fetch() ? true : false;
            
            // Remove from watchlist if present (watched = no longer need to watch later)
            if ($wasInWatchlist) {
                $stmt = $pdo->prepare("DELETE FROM watchlist WHERE user_id = ? AND movie_id = ?");
                $stmt->execute([$userId, $movieId]);
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'Added to diary', 
                'entryId' => $entryId,
                'watched' => true,
                'removedFromWatchlist' => $wasInWatchlist
            ]);
            break;
            
        case 'update':
            $entryId = intval($_POST['entry_id'] ?? 0);
            if ($entryId <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid entry ID']);
                exit();
            }
            
            $rating = isset($_POST['rating']) ? floatval($_POST['rating']) : null;
            $liked = isset($_POST['liked']) ? (bool)$_POST['liked'] : false;
            $review = trim($_POST['review'] ?? '');
            
            $stmt = $pdo->prepare("
                UPDATE diary SET rating = ?, liked = ?, review = ?
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$rating, $liked, $review, $entryId, $userId]);
            
            echo json_encode(['success' => true, 'message' => 'Diary entry updated']);
            break;
            
        case 'delete':
            $entryId = intval($_POST['entry_id'] ?? 0);
            if ($entryId <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid entry ID']);
                exit();
            }
            
            $stmt = $pdo->prepare("DELETE FROM diary WHERE id = ? AND user_id = ?");
            $stmt->execute([$entryId, $userId]);
            
            echo json_encode(['success' => true, 'message' => 'Diary entry deleted']);
            break;
            
        case 'check':
            if ($movieId <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid movie ID']);
                exit();
            }
            
            $stmt = $pdo->prepare("SELECT id, rating, liked FROM diary WHERE user_id = ? AND movie_id = ? ORDER BY watched_date DESC LIMIT 1");
            $stmt->execute([$userId, $movieId]);
            $entry = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true, 
                'watched' => $entry ? true : false,
                'entry' => $entry ?: null
            ]);
            break;
            
        case 'list':
            $limit = intval($_GET['limit'] ?? 50);
            
            $stmt = $pdo->prepare("
                SELECT d.id, d.movie_id, d.watched_date, d.rating, d.liked, d.rewatch, d.review,
                       m.title, m.poster_url, m.release_date as year
                FROM diary d
                JOIN movies m ON d.movie_id = m.id
                WHERE d.user_id = ?
                ORDER BY d.watched_date DESC
                LIMIT ?
            ");
            $stmt->execute([$userId, $limit]);
            $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Group by month
            $grouped = [];
            foreach ($entries as $entry) {
                $month = date('F Y', strtotime($entry['watched_date']));
                if (!isset($grouped[$month])) {
                    $grouped[$month] = [];
                }
                $grouped[$month][] = $entry;
            }
            
            echo json_encode(['success' => true, 'diary' => $entries, 'grouped' => $grouped, 'count' => count($entries)]);
            break;
            
        case 'count':
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM diary WHERE user_id = ?");
            $stmt->execute([$userId]);
            $count = $stmt->fetchColumn();
            
            echo json_encode(['success' => true, 'count' => intval($count)]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action. Use: add, update, delete, check, list, count']);
    }
} catch (PDOException $e) {
    error_log("Diary API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
