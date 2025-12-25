<?php
error_reporting(0);
ini_set('display_errors', 0);

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

// Cache helper functions
function getCachedData($cache_key) {
    global $conn;
    $stmt = $conn->prepare("SELECT cache_data FROM tmdb_cache WHERE cache_key = ? AND expires_at > NOW()");
    $stmt->bind_param("s", $cache_key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['cache_data'];
    }
    return null;
}

function setCachedData($cache_key, $data, $cache_type, $hours = 6) {
    global $conn;
    $expires_at = date('Y-m-d H:i:s', strtotime("+$hours hours"));
    
    $stmt = $conn->prepare("INSERT INTO tmdb_cache (cache_key, cache_data, cache_type, expires_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE cache_data = ?, expires_at = ?");
    $stmt->bind_param("ssssss", $cache_key, $data, $cache_type, $expires_at, $data, $expires_at);
    $stmt->execute();
}

switch($action) {
    case 'our_picks':
        getOurPicks();
        break;
    case 'trending':
        getTrendingMovies();
        break;
    case 'now_playing':
        getNowPlayingMovies();
        break;
    case 'popular':
        getPopularMovies();
        break;
    case 'top_rated':
        getTopRatedMovies();
        break;
    case 'upcoming':
        getUpcomingMovies();
        break;
    case 'now_showing':
        getNowShowingMovies();
        break;
    case 'movie_details':
        getMovieDetails();
        break;
    case 'search':
        searchMovies();
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
}

function getOurPicks() {
    global $conn;
    
    try {
        $query = "SELECT * FROM our_picks ORDER BY display_order ASC, added_at DESC";
        $result = $conn->query($query);
        
        if (!$result) {
            echo json_encode(['results' => []]);
            return;
        }
        
        $picks = [];
        while($row = $result->fetch_assoc()) {
            $picks[] = $row;
        }
        
        echo json_encode(['results' => $picks]);
    } catch (Exception $e) {
        echo json_encode(['results' => []]);
    }
}

function getTrendingMovies() {
    // Check cache first
    $cache_key = 'trending_movies_week';
    $cached = getCachedData($cache_key);
    if ($cached) {
        echo $cached;
        return;
    }
    
    $url = TMDB_BASE_URL . "/trending/movie/week?language=en-US";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . TMDB_API_TOKEN,
        'accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    // curl_close is deprecated in PHP 8.0+, handles are auto-closed
    
    // Cache for 6 hours
    setCachedData($cache_key, $response, 'trending', 6);
    
    echo $response;
}

function getPopularMovies() {
    // Check cache first
    $cache_key = 'popular_movies';
    $cached = getCachedData($cache_key);
    if ($cached) {
        echo $cached;
        return;
    }
    
    $url = TMDB_BASE_URL . "/movie/popular?language=en-US&page=1";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . TMDB_API_TOKEN,
        'accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    // curl_close is deprecated in PHP 8.0+, handles are auto-closed
    
    // Cache for 12 hours
    setCachedData($cache_key, $response, 'popular', 12);
    
    echo $response;
}

function getTopRatedMovies() {
    $url = TMDB_BASE_URL . "/movie/top_rated?language=en-US&page=1";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . TMDB_API_TOKEN,
        'accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    // curl_close is deprecated in PHP 8.0+, handles are auto-closed
    
    echo $response;
}

function getUpcomingMovies() {
    $url = TMDB_BASE_URL . "/movie/upcoming?language=en-US&page=1";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . TMDB_API_TOKEN,
        'accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    // curl_close is deprecated in PHP 8.0+, handles are auto-closed
    
    echo $response;
}

function getNowPlayingMovies() {
    // Check cache first
    $cache_key = 'now_playing_movies';
    $cached = getCachedData($cache_key);
    if ($cached) {
        echo $cached;
        return;
    }
    
    $url = TMDB_BASE_URL . "/movie/now_playing?language=en-US&page=1";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . TMDB_API_TOKEN,
        'accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    // curl_close is deprecated in PHP 8.0+, handles are auto-closed
    
    // Cache for 6 hours
    setCachedData($cache_key, $response, 'now_playing', 6);
    
    echo $response;
}

function getNowShowingMovies() {
    global $conn;
    
    $query = "SELECT m.*, 
              MIN(s.price) as min_price,
              COUNT(DISTINCT s.id) as showtime_count
              FROM movies m
              LEFT JOIN showtimes s ON m.id = s.movie_id
              WHERE m.is_showing = 1
              AND (m.show_end_date IS NULL OR m.show_end_date >= CURDATE())
              GROUP BY m.id
              ORDER BY m.created_at DESC";
    
    $result = $conn->query($query);
    $movies = [];
    
    while($row = $result->fetch_assoc()) {
        $movies[] = $row;
    }
    
    echo json_encode(['results' => $movies]);
}

function getMovieDetails() {
    global $conn;
    $movie_id = $_GET['id'] ?? 0;
    
    if ($movie_id) {
        $stmt = $conn->prepare("SELECT * FROM movies WHERE id = ?");
        $stmt->bind_param("i", $movie_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            echo json_encode($row);
        } else {
            echo json_encode(['error' => 'Movie not found']);
        }
    } else {
        echo json_encode(['error' => 'Movie ID required']);
    }
}

function searchMovies() {
    $query = $_GET['query'] ?? '';
    
    if (empty($query)) {
        echo json_encode(['error' => 'Search query required']);
        return;
    }
    
    $url = TMDB_BASE_URL . "/search/movie?query=" . urlencode($query) . "&language=en-US";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . TMDB_API_TOKEN,
        'accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    // curl_close is deprecated in PHP 8.0+, handles are auto-closed
    
    echo $response;
}
?>
