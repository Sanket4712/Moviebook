<?php
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../config.php';

// CORS Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    $conn = getDBConnection();
} catch (Exception $e) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

switch($action) {
    case 'get_reviews':
        getReviews();
        break;
    case 'add_review':
        addReview();
        break;
    case 'get_average_rating':
        getAverageRating();
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
}

function getReviews() {
    global $conn;
    $tmdb_id = $_GET['tmdb_id'] ?? 0;
    
    if (!$tmdb_id) {
        echo json_encode(['success' => false, 'message' => 'Movie ID required']);
        return;
    }
    
    $stmt = $conn->prepare("SELECT * FROM reviews WHERE tmdb_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $tmdb_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $reviews = [];
    while($row = $result->fetch_assoc()) {
        $reviews[] = $row;
    }
    
    echo json_encode(['success' => true, 'reviews' => $reviews]);
}

function addReview() {
    global $conn;
    
    // Check if user is logged in
    if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
        echo json_encode(['success' => false, 'message' => 'Please login to submit a review']);
        return;
    }
    
    $tmdb_id = $_POST['tmdb_id'] ?? 0;
    $movie_title = $_POST['movie_title'] ?? '';
    $rating = $_POST['rating'] ?? 0;
    $review_text = $_POST['review_text'] ?? '';
    
    // Use session data for user info
    $user_name = $_SESSION['username'];
    $user_email = $_SESSION['email'];
    
    // Validate
    if (!$tmdb_id || !$movie_title || !$rating) {
        echo json_encode(['success' => false, 'message' => 'Movie ID, title and rating are required']);
        return;
    }
    
    if ($rating < 1 || $rating > 5) {
        echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5']);
        return;
    }
    
    // Check if user already reviewed this movie
    $stmt = $conn->prepare("SELECT id FROM reviews WHERE tmdb_id = ? AND user_email = ?");
    $stmt->bind_param("is", $tmdb_id, $user_email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'You have already reviewed this movie']);
        return;
    }
    
    $stmt = $conn->prepare("INSERT INTO reviews (tmdb_id, movie_title, user_name, user_email, rating, review_text) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssis", $tmdb_id, $movie_title, $user_name, $user_email, $rating, $review_text);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Review submitted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error submitting review']);
    }
}

function getAverageRating() {
    global $conn;
    $tmdb_id = $_GET['tmdb_id'] ?? 0;
    
    if (!$tmdb_id) {
        echo json_encode(['success' => false, 'average' => 0, 'count' => 0]);
        return;
    }
    
    $stmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM reviews WHERE tmdb_id = ?");
    $stmt->bind_param("i", $tmdb_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'average' => round($result['avg_rating'] ?? 0, 1),
        'count' => $result['review_count'] ?? 0
    ]);
}
?>
