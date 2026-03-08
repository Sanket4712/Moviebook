<?php
/**
 * TMDB Lookup API for Admin Panel - READ ONLY
 * 
 * =============================================================================
 * SAFETY GUARANTEES
 * =============================================================================
 * 
 * 1. This API NEVER inserts data into any database table
 * 2. This API NEVER returns executable SQL
 * 3. This API NEVER auto-triggers inserts or updates
 * 4. This API ONLY returns structured JSON data for form pre-fill
 * 5. Admin must still click "Add Movie" to actually insert into database
 * 
 * INVARIANT: AI assists the admin, AI never owns the database.
 * =============================================================================
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once '../includes/session.php';
require_once '../includes/tmdb.php';

// STRICT ADMIN ROLE CHECK
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

if (getActiveRole() !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit();
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'search':
            searchMovies();
            break;
        case 'details':
            getDetails();
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action. Use: search, details']);
    }
} catch (Exception $e) {
    error_log("TMDB Lookup API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}

/**
 * Search TMDB for movies (READ ONLY)
 */
function searchMovies() {
    $query = trim($_GET['q'] ?? '');
    if (strlen($query) < 2) {
        echo json_encode(['success' => true, 'movies' => []]);
        return;
    }
    
    $result = searchTMDB($query, 1);
    
    // Check for API errors
    if (!$result['success']) {
        echo json_encode([
            'success' => false, 
            'error' => $result['error_message'] ?? 'TMDB search failed',
            'error_type' => $result['error'] ?? 'api_error'
        ]);
        return;
    }
    
    // Format results for admin selection (just show basic info)
    $movies = [];
    foreach (array_slice($result['results'] ?? [], 0, 10) as $movie) {
        $movies[] = [
            'tmdb_id' => $movie['id'],
            'title' => $movie['title'],
            'year' => $movie['release_date'] ? substr($movie['release_date'], 0, 4) : '',
            'poster_url' => getPosterUrl($movie['poster_path'] ?? '', 'w92'),
            'overview' => substr($movie['overview'] ?? '', 0, 100) . '...'
        ];
    }
    
    echo json_encode(['success' => true, 'movies' => $movies]);
}

/**
 * Get full movie details for form pre-fill (READ ONLY - never inserts)
 */
function getDetails() {
    $tmdbId = intval($_GET['tmdb_id'] ?? 0);
    if (!$tmdbId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'TMDB ID required']);
        return;
    }
    
    // Use the formatTMDBForAdminForm function which is READ ONLY
    $formResult = formatTMDBForAdminForm($tmdbId);
    
    // Check for API errors
    if (!$formResult['success']) {
        http_response_code($formResult['error'] === 'invalid_data' ? 404 : 503);
        echo json_encode([
            'success' => false, 
            'error' => $formResult['error_message'] ?? 'Could not fetch movie details',
            'error_type' => $formResult['error'] ?? 'api_error',
            'retry_after' => $formResult['retry_after'] ?? null
        ]);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'form_data' => $formResult['data'],
        'notice' => 'This data is for form pre-fill only. You must click Add Movie to save.'
    ]);
}
