<?php
/**
 * MovieBook - Movies API
 * 
 * Handles movie search, listing, and TMDB integration.
 * Actions: list, get, search, popular
 */

header('Content-Type: application/json');

require_once '../includes/db.php';
require_once '../includes/tmdb.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

try {
    switch ($action) {
        
        case 'list':
            // Get all movies from database
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = min(50, max(10, intval($_GET['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            $sort = $_GET['sort'] ?? 'rating';
            
            $validSorts = ['rating', 'release_date', 'title', 'id'];
            if (!in_array($sort, $validSorts)) $sort = 'rating';
            
            $order = $sort === 'title' ? 'ASC' : 'DESC';
            
            $stmt = $pdo->prepare("SELECT * FROM movies ORDER BY {$sort} {$order} LIMIT ? OFFSET ?");
            $stmt->execute([$limit, $offset]);
            $movies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->query("SELECT COUNT(*) FROM movies");
            $total = $stmt->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'movies' => $movies,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            break;
            
        case 'get':
            // Get single movie by ID
            $id = intval($_GET['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid movie ID']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ?");
            $stmt->execute([$id]);
            $movie = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($movie) {
                echo json_encode(['success' => true, 'movie' => $movie]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Movie not found']);
            }
            break;
            
        case 'search':
            // Search movies in local DB
            $query = trim($_GET['q'] ?? '');
            if (strlen($query) < 2) {
                echo json_encode(['success' => false, 'error' => 'Search query too short']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                SELECT * FROM movies 
                WHERE title LIKE ? OR description LIKE ? OR director LIKE ?
                ORDER BY rating DESC LIMIT 20
            ");
            $searchTerm = "%{$query}%";
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
            $movies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'movies' => $movies, 'query' => $query]);
            break;
            
        case 'search_tmdb':
            // Search TMDB directly
            $query = trim($_GET['q'] ?? '');
            if (strlen($query) < 2) {
                echo json_encode(['success' => false, 'error' => 'Search query too short']);
                exit;
            }
            
            $result = searchTMDB($query);
            if ($result && isset($result['results'])) {
                $movies = array_map(function($m) {
                    return [
                        'tmdb_id' => $m['id'],
                        'title' => $m['title'],
                        'release_date' => $m['release_date'] ?? null,
                        'poster_url' => getPosterUrl($m['poster_path'] ?? ''),
                        'rating' => $m['vote_average'] ?? 0,
                        'overview' => $m['overview'] ?? ''
                    ];
                }, array_slice($result['results'], 0, 20));
                
                echo json_encode(['success' => true, 'movies' => $movies, 'source' => 'tmdb']);
            } else {
                echo json_encode(['success' => false, 'error' => 'TMDB search failed']);
            }
            break;
            
        case 'import':
            // Import a movie from TMDB by ID
            $tmdbId = intval($_POST['tmdb_id'] ?? 0);
            if ($tmdbId <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid TMDB ID']);
                exit;
            }
            
            $movie = getOrFetchMovie($tmdbId, $pdo);
            if ($movie) {
                echo json_encode(['success' => true, 'movie' => $movie]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to import movie']);
            }
            break;
            
        case 'popular':
            // Get popular movies from DB (or fetch from TMDB if needed)
            $stmt = $pdo->query("SELECT * FROM movies ORDER BY rating DESC LIMIT 20");
            $movies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'movies' => $movies]);
            break;
            
        case 'recent':
            // Get recently added movies
            $stmt = $pdo->query("SELECT * FROM movies ORDER BY id DESC LIMIT 20");
            $movies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'movies' => $movies]);
            break;
            
        case 'by_genre':
            // Get movies by genre
            $genre = trim($_GET['genre'] ?? '');
            if (empty($genre)) {
                echo json_encode(['success' => false, 'error' => 'Genre required']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT * FROM movies WHERE genre LIKE ? ORDER BY rating DESC LIMIT 20");
            $stmt->execute(["%{$genre}%"]);
            $movies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'movies' => $movies, 'genre' => $genre]);
            break;
            
        default:
            // Default: return all movies paginated
            $stmt = $pdo->query("SELECT * FROM movies ORDER BY rating DESC LIMIT 50");
            $movies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'movies' => $movies]);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
