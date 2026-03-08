<?php
/**
 * Lists API
 * Create, update, delete user-created movie lists
 */

session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'create':
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if (empty($title)) {
            echo json_encode(['success' => false, 'error' => 'Title required']);
            exit;
        }
        
        if (strlen($title) > 100) {
            echo json_encode(['success' => false, 'error' => 'Title too long']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("INSERT INTO user_lists (user_id, title, description, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$userId, $title, $description]);
            $listId = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'list' => [
                    'id' => $listId,
                    'title' => $title,
                    'description' => $description
                ]
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
        break;
        
    case 'delete':
        $listId = intval($_POST['list_id'] ?? 0);
        
        try {
            // Verify ownership
            $stmt = $pdo->prepare("SELECT id FROM user_lists WHERE id = ? AND user_id = ?");
            $stmt->execute([$listId, $userId]);
            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'List not found']);
                exit;
            }
            
            // Delete list items first
            $stmt = $pdo->prepare("DELETE FROM list_items WHERE list_id = ?");
            $stmt->execute([$listId]);
            
            // Delete list
            $stmt = $pdo->prepare("DELETE FROM user_lists WHERE id = ?");
            $stmt->execute([$listId]);
            
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
        break;
        
    case 'add_film':
        $listId = intval($_POST['list_id'] ?? 0);
        $movieId = intval($_POST['movie_id'] ?? 0);
        
        if (!$listId || !$movieId) {
            echo json_encode(['success' => false, 'error' => 'Missing parameters']);
            exit;
        }
        
        try {
            // Verify ownership
            $stmt = $pdo->prepare("SELECT id FROM user_lists WHERE id = ? AND user_id = ?");
            $stmt->execute([$listId, $userId]);
            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'List not found']);
                exit;
            }
            
            // Check if already in list
            $stmt = $pdo->prepare("SELECT id FROM list_items WHERE list_id = ? AND movie_id = ?");
            $stmt->execute([$listId, $movieId]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'Already in list']);
                exit;
            }
            
            // Get next position
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(position), 0) + 1 FROM list_items WHERE list_id = ?");
            $stmt->execute([$listId]);
            $position = $stmt->fetchColumn();
            
            // Add to list
            $stmt = $pdo->prepare("INSERT INTO list_items (list_id, movie_id, position) VALUES (?, ?, ?)");
            $stmt->execute([$listId, $movieId, $position]);
            
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
        break;
        
    case 'remove_film':
        $listId = intval($_POST['list_id'] ?? 0);
        $movieId = intval($_POST['movie_id'] ?? 0);
        
        try {
            // Verify ownership
            $stmt = $pdo->prepare("SELECT id FROM user_lists WHERE id = ? AND user_id = ?");
            $stmt->execute([$listId, $userId]);
            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'List not found']);
                exit;
            }
            
            $stmt = $pdo->prepare("DELETE FROM list_items WHERE list_id = ? AND movie_id = ?");
            $stmt->execute([$listId, $movieId]);
            
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
        break;
        
    case 'get':
        $listId = intval($_GET['list_id'] ?? 0);
        
        try {
            $stmt = $pdo->prepare("
                SELECT l.*, 
                       (SELECT COUNT(*) FROM list_items WHERE list_id = l.id) as film_count
                FROM user_lists l 
                WHERE l.id = ? AND l.user_id = ?
            ");
            $stmt->execute([$listId, $userId]);
            $list = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$list) {
                echo json_encode(['success' => false, 'error' => 'List not found']);
                exit;
            }
            
            // Get films in list
            $stmt = $pdo->prepare("
                SELECT m.id, m.title, m.poster_url, m.release_date
                FROM list_items li
                JOIN movies m ON li.movie_id = m.id
                WHERE li.list_id = ?
                ORDER BY li.position
            ");
            $stmt->execute([$listId]);
            $list['films'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'list' => $list]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
        break;
        
    case 'list_all':
        try {
            $stmt = $pdo->prepare("
                SELECT l.*, 
                       (SELECT COUNT(*) FROM list_items WHERE list_id = l.id) as film_count
                FROM user_lists l 
                WHERE l.user_id = ?
                ORDER BY l.created_at DESC
            ");
            $stmt->execute([$userId]);
            $lists = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Fetch first 4 posters for each list
            foreach ($lists as &$list) {
                $stmt = $pdo->prepare("
                    SELECT m.id, m.poster_url, m.title
                    FROM list_items li
                    JOIN movies m ON li.movie_id = m.id
                    WHERE li.list_id = ?
                    ORDER BY li.position
                    LIMIT 4
                ");
                $stmt->execute([$list['id']]);
                $list['posters'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            echo json_encode(['success' => true, 'lists' => $lists]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
        break;
    
    case 'update':
        $listId = intval($_POST['list_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if (empty($title)) {
            echo json_encode(['success' => false, 'error' => 'Title required']);
            exit;
        }
        
        if (strlen($title) > 100) {
            echo json_encode(['success' => false, 'error' => 'Title too long']);
            exit;
        }
        
        try {
            // Verify ownership
            $stmt = $pdo->prepare("SELECT id FROM user_lists WHERE id = ? AND user_id = ?");
            $stmt->execute([$listId, $userId]);
            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'List not found']);
                exit;
            }
            
            $stmt = $pdo->prepare("UPDATE user_lists SET title = ?, description = ? WHERE id = ?");
            $stmt->execute([$title, $description, $listId]);
            
            echo json_encode([
                'success' => true,
                'list' => [
                    'id' => $listId,
                    'title' => $title,
                    'description' => $description
                ]
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
        break;
    
    case 'get_films':
        $listId = intval($_GET['list_id'] ?? 0);
        
        try {
            // Verify ownership or public
            $stmt = $pdo->prepare("SELECT id, title, description, is_public FROM user_lists WHERE id = ? AND (user_id = ? OR is_public = 1)");
            $stmt->execute([$listId, $userId]);
            $list = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$list) {
                echo json_encode(['success' => false, 'error' => 'List not found']);
                exit;
            }
            
            // Get all films in list
            $stmt = $pdo->prepare("
                SELECT m.id, m.title, m.poster_url, m.release_date, m.rating, li.position
                FROM list_items li
                JOIN movies m ON li.movie_id = m.id
                WHERE li.list_id = ?
                ORDER BY li.position
            ");
            $stmt->execute([$listId]);
            $films = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true, 
                'list' => $list,
                'films' => $films,
                'count' => count($films)
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
