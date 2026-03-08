<?php
/**
 * Admin Movie Management API
 * 
 * =============================================================================
 * SECURITY INVARIANTS (DO NOT MODIFY WITHOUT EXPLICIT APPROVAL)
 * =============================================================================
 * 
 * 1. ADMIN-ONLY ACCESS
 *    All operations require: $_SESSION['active_role'] === 'admin'
 *    No email-based checks. No hardcoded admin users. No exceptions.
 * 
 * 2. SINGLE WRITE PATH
 *    This file is the ONLY place in the codebase that may INSERT/UPDATE/DELETE
 *    the movies table. All other write paths have been blocked.
 * 
 * 3. DUPLICATE PREVENTION
 *    - Application level: PHP checks LOWER(title) + YEAR(release_date)
 *    - Database level: Unique constraint on (title, release_year)
 *    Both levels MUST pass before insert succeeds.
 * 
 * 4. DELETE BEHAVIOR (force=true)
 *    When admin force-deletes a movie with dependencies:
 *    - CASCADE DELETE: watchlist, diary, favorites, showtimes are removed
 *    - This is PERMANENT and cannot be undone
 *    - Admin sees dependency warning and must explicitly confirm
 * 
 * 5. LOGGING
 *    All write operations are logged with user ID and movie details.
 * 
 * =============================================================================
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in JSON response

require_once '../includes/session.php';
require_once '../includes/db.php';

// STRICT ADMIN ROLE CHECK - The ONLY way to authorize admin actions
function requireAdmin() {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit();
    }
    
    if (getActiveRole() !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin access required. Current role: ' . getActiveRole()]);
        exit();
    }
}

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            handleGet($action);
            break;
        case 'POST':
            handlePost($action);
            break;
        case 'DELETE':
            handleDelete($action);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log("Admin movies API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

/**
 * Handle GET requests
 */
function handleGet($action) {
    global $pdo;
    requireAdmin();
    
    switch ($action) {
        case 'list':
            listMovies();
            break;
        case 'get':
            getMovie();
            break;
        case 'check_duplicate':
            checkDuplicate();
            break;
        case 'stats':
            getStats();
            break;
        default:
            listMovies();
    }
}

/**
 * Handle POST requests
 */
function handlePost($action) {
    global $pdo;
    requireAdmin();
    
    switch ($action) {
        case 'add':
            addMovie();
            break;
        case 'bulk_add':
            bulkAddMovies();
            break;
        case 'update':
            updateMovie();
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}

/**
 * Handle DELETE requests
 */
function handleDelete($action) {
    global $pdo;
    requireAdmin();
    
    deleteMovie();
}

/**
 * List all movies with pagination
 */
function listMovies() {
    global $pdo;
    
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(10, intval($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    
    // Build query
    $where = [];
    $params = [];
    
    if ($search) {
        $where[] = "(title LIKE ? OR director LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($status && in_array($status, ['now_showing', 'coming_soon', 'ended'])) {
        $where[] = "status = ?";
        $params[] = $status;
    }
    
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Get total count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM movies $whereClause");
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();
    
    // Get movies
    $stmt = $pdo->prepare("
        SELECT id, title, description, poster_url, backdrop_url, director, genre, 
               runtime, rating, release_date, status, created_at
        FROM movies 
        $whereClause
        ORDER BY created_at DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $movies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'movies' => $movies,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => intval($total),
            'pages' => ceil($total / $limit)
        ]
    ]);
}

/**
 * Get single movie by ID
 */
function getMovie() {
    global $pdo;
    
    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Movie ID required']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ?");
    $stmt->execute([$id]);
    $movie = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$movie) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Movie not found']);
        return;
    }
    
    echo json_encode(['success' => true, 'movie' => $movie]);
}

/**
 * Check for duplicate movie (title + year)
 */
function checkDuplicate() {
    global $pdo;
    
    $title = trim($_GET['title'] ?? '');
    $year = intval($_GET['year'] ?? 0);
    $excludeId = intval($_GET['exclude_id'] ?? 0);
    
    if (!$title) {
        echo json_encode(['success' => true, 'duplicate' => false]);
        return;
    }
    
    $query = "SELECT id, title, release_date FROM movies WHERE LOWER(title) = LOWER(?)";
    $params = [$title];
    
    if ($year) {
        $query .= " AND YEAR(release_date) = ?";
        $params[] = $year;
    }
    
    if ($excludeId) {
        $query .= " AND id != ?";
        $params[] = $excludeId;
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'duplicate' => $existing ? true : false,
        'existing' => $existing
    ]);
}

/**
 * Get movie statistics for dashboard
 */
function getStats() {
    global $pdo;
    
    $stats = [];
    
    // Total movies
    $stats['total'] = intval($pdo->query("SELECT COUNT(*) FROM movies")->fetchColumn());
    
    // By status
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM movies GROUP BY status");
    $byStatus = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $byStatus[$row['status']] = intval($row['count']);
    }
    $stats['now_showing'] = $byStatus['now_showing'] ?? 0;
    $stats['coming_soon'] = $byStatus['coming_soon'] ?? 0;
    $stats['ended'] = $byStatus['ended'] ?? 0;
    
    // Average rating
    $stats['avg_rating'] = floatval($pdo->query("SELECT AVG(rating) FROM movies WHERE rating > 0")->fetchColumn() ?? 0);
    
    // Added this month
    $stats['added_this_month'] = intval($pdo->query("
        SELECT COUNT(*) FROM movies 
        WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
    ")->fetchColumn());
    
    echo json_encode(['success' => true, 'stats' => $stats]);
}

/**
 * Add new movie - REQUIRES ADMIN
 * Supports quick_add flag for bulk imports with minimal required fields
 */
function addMovie() {
    global $pdo;
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    // Check for quick_add mode (used by bulk import)
    $quickAdd = isset($input['quick_add']) && $input['quick_add'] === true;
    
    // Required fields - minimal for quick_add, full for normal add
    if ($quickAdd) {
        $required = ['title', 'release_year'];
    } else {
        $required = ['title', 'release_year', 'language', 'runtime', 'poster_url', 'description', 'genre', 'director', 'cast', 'country'];
    }
    
    $errors = [];
    foreach ($required as $field) {
        if (empty(trim($input[$field] ?? ''))) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    
    if ($errors) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Validation failed', 'errors' => $errors]);
        return;
    }
    
    // Extract and sanitize data with defaults for quick_add
    $title = trim($input['title']);
    $releaseYear = intval($input['release_year']);
    $releaseDate = $input['release_date'] ?? "$releaseYear-01-01";
    
    // Default placeholder poster for quick-add movies
    $defaultPoster = 'https://via.placeholder.com/300x450/1a1a2e/e94560?text=' . urlencode(substr($title, 0, 20));
    
    if ($quickAdd) {
        // Use sensible defaults for all optional fields
        $language = trim($input['language'] ?? 'English');
        $runtime = intval($input['runtime'] ?? 0);
        $posterUrl = trim($input['poster_url'] ?? '') ?: $defaultPoster;
        $backdropUrl = trim($input['backdrop_url'] ?? '') ?: null;
        $description = trim($input['description'] ?? '') ?: 'No description available.';
        $genre = trim($input['genre'] ?? '') ?: 'Unclassified';
        $director = trim($input['director'] ?? '') ?: 'Unknown';
        $status = $input['status'] ?? 'ended'; // Imported movies are typically watched films
    } else {
        $language = trim($input['language']);
        $runtime = intval($input['runtime']);
        $posterUrl = trim($input['poster_url']);
        $backdropUrl = trim($input['backdrop_url'] ?? '');
        $description = trim($input['description']);
        $genre = trim($input['genre']);
        $director = trim($input['director']);
        $status = $input['status'] ?? 'now_showing';
    }
    
    $rating = floatval($input['rating'] ?? 0);
    
    // Validate status
    if (!in_array($status, ['now_showing', 'coming_soon', 'ended'])) {
        $status = $quickAdd ? 'ended' : 'now_showing';
    }
    
    // Check for duplicate (title + year)
    $stmt = $pdo->prepare("SELECT id FROM movies WHERE LOWER(title) = LOWER(?) AND YEAR(release_date) = ?");
    $stmt->execute([$title, $releaseYear]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => "A movie with title '$title' and year $releaseYear already exists"]);
        return;
    }
    
    // Validate poster URL (skip for quick_add placeholder)
    if (!$quickAdd && !filter_var($posterUrl, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid poster URL format']);
        return;
    }
    
    // Insert movie
    $stmt = $pdo->prepare("
        INSERT INTO movies (
            title, description, poster_url, backdrop_url, director, genre, 
            runtime, rating, release_date, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $title, $description, $posterUrl, $backdropUrl, $director, $genre,
        $runtime, $rating, $releaseDate, $status
    ]);
    
    $movieId = $pdo->lastInsertId();
    
    // Log the action
    $mode = $quickAdd ? 'QUICK' : 'FULL';
    error_log("Admin movie added ($mode): ID=$movieId, Title='$title', By user " . getUserId());
    
    echo json_encode([
        'success' => true,
        'message' => 'Movie added successfully',
        'movie_id' => intval($movieId),
        'quick_add' => $quickAdd
    ]);
}

/**
 * Bulk add movies from Letterboxd import - REQUIRES ADMIN
 * Creates movies with minimal data and sensible defaults
 * Skips duplicates silently and tracks counts
 */
function bulkAddMovies() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['movies']) || !is_array($input['movies'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Movies array required']);
        return;
    }
    
    $movies = $input['movies'];
    $created = 0;
    $duplicates = 0;
    $errors = [];
    $createdIds = [];
    
    // Default placeholder poster base URL
    $placeholderBase = 'https://via.placeholder.com/300x450/1a1a2e/e94560?text=';
    
    // Prepare statements for efficiency
    $checkStmt = $pdo->prepare("SELECT id FROM movies WHERE LOWER(title) = LOWER(?) AND YEAR(release_date) = ?");
    $insertStmt = $pdo->prepare("
        INSERT INTO movies (
            title, description, poster_url, backdrop_url, director, genre, 
            runtime, rating, release_date, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $pdo->beginTransaction();
    
    try {
        foreach ($movies as $movie) {
            // Validate required fields
            $title = trim($movie['title'] ?? '');
            $releaseYear = intval($movie['release_year'] ?? 0);
            
            if (empty($title) || $releaseYear < 1800 || $releaseYear > 2100) {
                $errors[] = "Invalid movie: " . ($title ?: 'empty title');
                continue;
            }
            
            // Check for duplicate
            $checkStmt->execute([$title, $releaseYear]);
            if ($checkStmt->fetch()) {
                $duplicates++;
                continue;
            }
            
            // Build movie data with defaults
            $releaseDate = "$releaseYear-01-01";
            $description = 'No description available.';
            $posterUrl = $placeholderBase . urlencode(substr($title, 0, 20));
            $backdropUrl = null;
            $director = 'Unknown';
            $genre = 'Unclassified';
            $runtime = 0;
            $status = 'ended';
            
            // Map Letterboxd rating (0-5 stars) to 0-10 scale
            $rating = 0;
            if (isset($movie['rating']) && is_numeric($movie['rating'])) {
                $letterboxdRating = floatval($movie['rating']);
                // Letterboxd uses 0.5-5 stars, we convert to 0-10
                $rating = min(10, max(0, $letterboxdRating * 2));
            }
            
            // Insert movie
            $insertStmt->execute([
                $title, $description, $posterUrl, $backdropUrl, $director, $genre,
                $runtime, $rating, $releaseDate, $status
            ]);
            
            $createdIds[] = [
                'id' => intval($pdo->lastInsertId()),
                'title' => $title,
                'year' => $releaseYear
            ];
            $created++;
        }
        
        $pdo->commit();
        
        error_log("Admin bulk import: Created=$created, Duplicates=$duplicates, By user " . getUserId());
        
        echo json_encode([
            'success' => true,
            'message' => "Imported $created movies",
            'created' => $created,
            'duplicates' => $duplicates,
            'errors' => count($errors),
            'error_details' => array_slice($errors, 0, 10), // Limit error details
            'created_movies' => array_slice($createdIds, 0, 50) // Return first 50 for UI
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Bulk import failed: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Bulk import failed: ' . $e->getMessage()]);
    }
}

/**
 * Update existing movie - REQUIRES ADMIN
 */
function updateMovie() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $id = intval($input['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Movie ID required']);
        return;
    }
    
    // Check movie exists
    $stmt = $pdo->prepare("SELECT id FROM movies WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Movie not found']);
        return;
    }
    
    // Build update query dynamically
    $updates = [];
    $params = [];
    
    $allowedFields = ['title', 'description', 'poster_url', 'backdrop_url', 'director', 
                      'genre', 'runtime', 'rating', 'release_date', 'status'];
    
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updates[] = "$field = ?";
            $params[] = $input[$field];
        }
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No fields to update']);
        return;
    }
    
    // Check for duplicate if title changed
    if (isset($input['title'])) {
        $year = isset($input['release_date']) ? substr($input['release_date'], 0, 4) : null;
        if ($year) {
            $stmt = $pdo->prepare("SELECT id FROM movies WHERE LOWER(title) = LOWER(?) AND YEAR(release_date) = ? AND id != ?");
            $stmt->execute([trim($input['title']), $year, $id]);
            if ($stmt->fetch()) {
                http_response_code(409);
                echo json_encode(['success' => false, 'error' => 'A movie with this title and year already exists']);
                return;
            }
        }
    }
    
    $params[] = $id;
    $stmt = $pdo->prepare("UPDATE movies SET " . implode(', ', $updates) . " WHERE id = ?");
    $stmt->execute($params);
    
    error_log("Admin movie updated: ID=$id, By user " . getUserId());
    
    echo json_encode(['success' => true, 'message' => 'Movie updated successfully']);
}

/**
 * Delete movie - REQUIRES ADMIN
 */
function deleteMovie() {
    global $pdo;
    
    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Movie ID required']);
        return;
    }
    
    // Check movie exists
    $stmt = $pdo->prepare("SELECT title FROM movies WHERE id = ?");
    $stmt->execute([$id]);
    $movie = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$movie) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Movie not found']);
        return;
    }
    
    // Check for dependent records
    $dependencies = [];
    
    // Watchlist entries
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM watchlist WHERE movie_id = ?");
    $stmt->execute([$id]);
    $watchlistCount = intval($stmt->fetchColumn());
    if ($watchlistCount > 0) {
        $dependencies[] = "$watchlistCount watchlist entries";
    }
    
    // Diary entries
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM diary WHERE movie_id = ?");
    $stmt->execute([$id]);
    $diaryCount = intval($stmt->fetchColumn());
    if ($diaryCount > 0) {
        $dependencies[] = "$diaryCount diary entries";
    }
    
    // Favorites
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE movie_id = ?");
    $stmt->execute([$id]);
    $favoritesCount = intval($stmt->fetchColumn());
    if ($favoritesCount > 0) {
        $dependencies[] = "$favoritesCount favorites";
    }
    
    // Showtimes
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM showtimes WHERE movie_id = ?");
    $stmt->execute([$id]);
    $showtimesCount = intval($stmt->fetchColumn());
    if ($showtimesCount > 0) {
        $dependencies[] = "$showtimesCount showtimes";
    }
    
    // If dependencies exist and force not set, block deletion
    $force = isset($_GET['force']) && $_GET['force'] === 'true';
    
    if (!empty($dependencies) && !$force) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'Cannot delete movie with dependent records',
            'dependencies' => $dependencies,
            'requires_confirmation' => true
        ]);
        return;
    }
    
    // Delete with cascade (if forced)
    $pdo->beginTransaction();
    try {
        if ($force) {
            $pdo->prepare("DELETE FROM watchlist WHERE movie_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM diary WHERE movie_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM favorites WHERE movie_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM showtimes WHERE movie_id = ?")->execute([$id]);
        }
        
        $pdo->prepare("DELETE FROM movies WHERE id = ?")->execute([$id]);
        $pdo->commit();
        
        error_log("Admin movie deleted: ID=$id, Title='{$movie['title']}', By user " . getUserId());
        
        echo json_encode(['success' => true, 'message' => 'Movie deleted successfully']);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}
