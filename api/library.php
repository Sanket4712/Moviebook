<?php
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../config.php';

// CORS Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    $conn = getDBConnection();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Check authentication for protected routes
function requireAuth() {
    if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
}

switch($action) {
    case 'get_library':
        requireAuth();
        getLibrary();
        break;
    case 'add_to_library':
        requireAuth();
        addToLibrary();
        break;
    case 'remove_from_library':
        requireAuth();
        removeFromLibrary();
        break;
    case 'check_in_library':
        requireAuth();
        checkInLibrary();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getLibrary() {
    global $conn;
    $user_id = $_SESSION['user_id'];
    $type = $_GET['type'] ?? 'watchlist';
    
    $stmt = $conn->prepare("SELECT * FROM user_library WHERE user_id = ? AND library_type = ? ORDER BY added_at DESC");
    $stmt->bind_param("is", $user_id, $type);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    
    echo json_encode(['success' => true, 'items' => $items]);
}

function addToLibrary() {
    global $conn;
    $user_id = $_SESSION['user_id'];
    
    $input = json_decode(file_get_contents('php://input'), true);
    $tmdb_id = $input['tmdb_id'] ?? 0;
    $movie_title = $input['movie_title'] ?? '';
    $movie_poster = $input['movie_poster'] ?? '';
    $movie_year = $input['movie_year'] ?? '';
    $library_type = $input['library_type'] ?? 'watchlist';
    
    if (!$tmdb_id || !$movie_title) {
        echo json_encode(['success' => false, 'message' => 'Movie ID and title are required']);
        return;
    }
    
    // Check if already exists
    $stmt = $conn->prepare("SELECT id FROM user_library WHERE user_id = ? AND tmdb_id = ? AND library_type = ?");
    $stmt->bind_param("iis", $user_id, $tmdb_id, $library_type);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Already in your ' . $library_type]);
        return;
    }
    
    $stmt = $conn->prepare("INSERT INTO user_library (user_id, tmdb_id, movie_title, movie_poster, movie_year, library_type) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissss", $user_id, $tmdb_id, $movie_title, $movie_poster, $movie_year, $library_type);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Added to ' . $library_type]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add']);
    }
}

function removeFromLibrary() {
    global $conn;
    $user_id = $_SESSION['user_id'];
    
    $input = json_decode(file_get_contents('php://input'), true);
    $tmdb_id = $input['tmdb_id'] ?? 0;
    $library_type = $input['library_type'] ?? 'watchlist';
    
    $stmt = $conn->prepare("DELETE FROM user_library WHERE user_id = ? AND tmdb_id = ? AND library_type = ?");
    $stmt->bind_param("iis", $user_id, $tmdb_id, $library_type);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Removed from ' . $library_type]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to remove']);
    }
}

function checkInLibrary() {
    global $conn;
    $user_id = $_SESSION['user_id'];
    $tmdb_id = $_GET['tmdb_id'] ?? 0;
    
    if (!$tmdb_id) {
        echo json_encode(['success' => false, 'in_library' => []]);
        return;
    }
    
    $stmt = $conn->prepare("SELECT library_type FROM user_library WHERE user_id = ? AND tmdb_id = ?");
    $stmt->bind_param("ii", $user_id, $tmdb_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $in_library = [];
    while ($row = $result->fetch_assoc()) {
        $in_library[] = $row['library_type'];
    }
    
    echo json_encode(['success' => true, 'in_library' => $in_library]);
}
?>
