<?php
/**
 * Letterboxd CSV Parser API - READ ONLY
 * 
 * =============================================================================
 * SAFETY GUARANTEES
 * =============================================================================
 * 
 * 1. This API NEVER inserts data into any database table
 * 2. This API ONLY parses CSV and returns movie titles
 * 3. This API ONLY returns structured JSON for preview
 * 4. Admin must individually confirm each movie before saving
 * 
 * INVARIANT: AI assists the admin, AI never owns the database.
 * =============================================================================
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once '../includes/session.php';
require_once '../includes/tmdb.php';
require_once '../includes/db.php';

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
        case 'parse':
            parseCSV();
            break;
        case 'bulk_import':
            bulkImportMovies();
            break;
        case 'lookup':
            lookupMovie();
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action. Use: parse, bulk_import, lookup']);
    }
} catch (Exception $e) {
    error_log("Letterboxd Parser API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}

/**
 * Parse Letterboxd CSV and extract movie data for bulk import
 * Extracts: title, year, rating (if available)
 */
function parseCSV() {
    error_log("Letterboxd CSV parse request");
    
    if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        $error = 'No CSV file uploaded';
        if (isset($_FILES['csv']['error'])) {
            $error .= ' (error code: ' . $_FILES['csv']['error'] . ')';
        }
        error_log("CSV upload error: " . $error);
        echo json_encode(['success' => false, 'error' => $error]);
        return;
    }
    
    $file = $_FILES['csv']['tmp_name'];
    $filename = $_FILES['csv']['name'];
    
    error_log("CSV file received: " . $filename);
    
    // Validate file extension
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    if ($ext !== 'csv') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'File must be a CSV (got .' . $ext . ')']);
        return;
    }
    
    // Validate file size (max 5MB)
    if ($_FILES['csv']['size'] > 5 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'File too large (max 5MB, got ' . round($_FILES['csv']['size'] / 1024 / 1024, 2) . 'MB)']);
        return;
    }
    
    $handle = fopen($file, 'r');
    if (!$handle) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Could not read file']);
        return;
    }
    
    error_log("CSV file opened successfully, parsing...");
    
    $movies = [];
    $headers = null;
    $titleIndex = -1;
    $yearIndex = -1;
    $ratingIndex = -1;
    $lineNumber = 0;
    
    while (($row = fgetcsv($handle)) !== false) {
        $lineNumber++;
        
        // First row is headers
        if ($headers === null) {
            $headers = array_map('strtolower', array_map('trim', $row));
            
            // Find relevant columns
            foreach ($headers as $i => $header) {
                if (in_array($header, ['name', 'title', 'film', 'movie', 'movie name'])) {
                    $titleIndex = $i;
                }
                if (in_array($header, ['year', 'release year', 'release_year', 'year released', 'releasedate'])) {
                    $yearIndex = $i;
                }
                // More flexible rating detection for Letterboxd
                if (in_array($header, ['rating', 'your rating', 'my rating', 'your rating (10)', 'rating10'])) {
                    $ratingIndex = $i;
                }
            }
            
            if ($titleIndex === -1) {
                fclose($handle);
                http_response_code(400);
                echo json_encode([
                    'success' => false, 
                    'error' => 'CSV must have a "Name" or "Title" column. Found columns: ' . implode(', ', $headers)
                ]);
                return;
            }
            continue;
        }
        
        // Extract movie data
        if (isset($row[$titleIndex]) && !empty(trim($row[$titleIndex]))) {
            $title = trim($row[$titleIndex]);
            $year = ($yearIndex >= 0 && isset($row[$yearIndex])) ? trim($row[$yearIndex]) : '';
            $rating = ($ratingIndex >= 0 && isset($row[$ratingIndex])) ? trim($row[$ratingIndex]) : '';
            
            // Try to extract year from title if not in separate column
            if (empty($year)) {
                $extractedYear = extractYearFromTitle($title);
                if ($extractedYear) {
                    $year = $extractedYear;
                }
            }
            
            // Skip movies without a valid year
            if (empty($year) || !is_numeric($year)) {
                continue;
            }
            
            $movieData = [
                'title' => $title,
                'release_year' => intval($year)
            ];
            
            // Include rating if present (Letterboxd uses 0.5-5 stars)
            if (!empty($rating) && is_numeric($rating)) {
                $movieData['rating'] = floatval($rating);
            }
            
            $movies[] = $movieData;
            error_log("Parsed movie: " . $title . " (" . $year . ")" . (isset($movieData['rating']) ? " Rating: " . $movieData['rating'] : ""));
        }
    }
    
    fclose($handle);
    
    error_log("CSV parsing complete: found " . count($movies) . " valid movies");
    
    if (empty($movies)) {
        echo json_encode([
            'success' => false,
            'error' => 'No valid movies found in CSV. Each row must have a title and year.'
        ]);
        return;
    }
    
    // Check for ratings availability
    $moviesWithRatings = count(array_filter($movies, fn($m) => isset($m['rating'])));
    
    error_log("Movies with ratings: " . $moviesWithRatings);
    
    echo json_encode([
        'success' => true,
        'total' => count($movies),
        'movies' => $movies,
        'has_ratings' => $moviesWithRatings > 0,
        'ratings_count' => $moviesWithRatings,
        'notice' => 'Ready to import ' . count($movies) . ' movies. Click "Import All" to add them to your database instantly.'
    ]);
}

/**
 * Extract year from movie title (e.g., "Title (2024)" -> 2024)
 * @param string $title Movie title with potential year
 * @return int|null Year if found, null otherwise
 */
function extractYearFromTitle($title) {
    // Look for 4-digit year in parentheses at the end: (2024)
    if (preg_match('/\((\d{4})\)$/', trim($title), $matches)) {
        $year = intval($matches[1]);
        if ($year >= 1800 && $year <= 2100) {
            return $year;
        }
    }
    return null;
}

/**
 * Bulk import movies directly into database without TMDB lookup
 * Uses admin_movies.php bulk_add endpoint
 */
function bulkImportMovies() {
    global $pdo;
    
    // Get JSON input with movies array
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['movies']) || !is_array($input['movies'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Movies array required']);
        return;
    }
    
    // Validate database connection
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        return;
    }
    
    // Forward to admin_movies.php bulk_add endpoint
    require_once 'admin_movies.php';
    
    // The admin_movies.php will handle the bulk_add internally
    // We need to call it directly since we're already in the same request
    
    // Prepare the request for bulk_add
    $movies = $input['movies'];
    
    error_log("Starting bulk import of " . count($movies) . " movies");
    
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
            
            $newMovieId = intval($pdo->lastInsertId());
            error_log("Created movie ID $newMovieId: " . $title . " (" . $releaseYear . ") Rating: $rating");
            
            $createdIds[] = [
                'id' => $newMovieId,
                'title' => $title,
                'year' => $releaseYear,
                'rating' => $rating
            ];
            $created++;
        }
        
        $pdo->commit();
        
        error_log("Letterboxd bulk import: Created=$created, Duplicates=$duplicates, By user " . getUserId());
        
        echo json_encode([
            'success' => true,
            'message' => $created > 0 
                ? "Successfully imported $created movies!" 
                : "No new movies imported (all duplicates)",
            'created' => $created,
            'duplicates' => $duplicates,
            'errors' => count($errors),
            'created_movies' => $createdIds
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Letterboxd bulk import failed: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Import failed: ' . $e->getMessage()]);
    }
}

/**
 * Lookup a single movie on TMDB for confirmation (READ ONLY)
 * Uses advanced search with title normalization and year tolerance
 */
function lookupMovie() {
    $title = trim($_GET['title'] ?? '');
    $year = trim($_GET['year'] ?? '');
    
    if (empty($title)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Title required']);
        return;
    }
    
    error_log("Letterboxd Lookup: '$title' (year: $year)");
    
    // Use advanced search with normalization and year tolerance
    $yearInt = !empty($year) && is_numeric($year) ? intval($year) : null;
    $result = searchTMDBAdvanced($title, $yearInt);
    
    // Check for API errors (distinguish from empty results)
    if (!$result['success']) {
        $errorType = $result['error'] ?? 'unknown';
        $errorMessage = $result['error_message'] ?? 'Unknown error';
        
        error_log("Letterboxd Lookup TMDB Error: $errorType - $errorMessage");
        
        // Return specific error types for UI handling
        if ($errorType === 'auth_error') {
            echo json_encode([
                'success' => false,
                'error_type' => 'tmdb_unavailable',
                'title' => $title,
                'message' => 'TMDB API authentication failed. Check API key.'
            ]);
            return;
        }
        
        if ($errorType === 'network_error') {
            echo json_encode([
                'success' => false,
                'error_type' => 'tmdb_unavailable',
                'title' => $title,
                'message' => 'Could not connect to TMDB. Retry later.'
            ]);
            return;
        }
        
        echo json_encode([
            'success' => false,
            'error_type' => 'tmdb_error',
            'title' => $title,
            'message' => $errorMessage
        ]);
        return;
    }
    
    // Check for empty results (genuine "not found")
    if (empty($result['results'])) {
        error_log("Letterboxd Lookup: No results for '$title'");
        echo json_encode([
            'success' => true,
            'matched' => false,
            'title' => $title,
            'error_type' => 'not_found',
            'message' => 'No TMDB match found'
        ]);
        return;
    }
    
    // Multiple results - return all for selection
    $results = $result['results'];
    
    // If multiple close matches, return them all for admin selection
    if (count($results) > 1) {
        $candidates = [];
        foreach (array_slice($results, 0, 5) as $movie) {
            $candidates[] = [
                'tmdb_id' => $movie['id'],
                'title' => $movie['title'],
                'year' => $movie['release_date'] ? substr($movie['release_date'], 0, 4) : '',
                'poster_url' => getPosterUrl($movie['poster_path'] ?? '', 'w92'),
                'overview' => substr($movie['overview'] ?? '', 0, 100) . '...'
            ];
        }
        
        // Also get full details for the best match (first result)
        $formResult = formatTMDBForAdminForm($results[0]['id']);
        
        // Check if we got an error fetching details
        if (!$formResult['success']) {
            // Return candidates without form data - user can still select
            echo json_encode([
                'success' => true,
                'matched' => true,
                'multiple_matches' => true,
                'original_title' => $title,
                'candidates' => $candidates,
                'form_data' => null,
                'notice' => 'Multiple matches found but could not fetch details. Select one to retry.',
                'warning' => $formResult['error_message']
            ]);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'matched' => true,
            'multiple_matches' => true,
            'original_title' => $title,
            'candidates' => $candidates,
            'form_data' => $formResult['data'],
            'notice' => 'Multiple matches found. Review carefully or select the correct one.'
        ]);
        return;
    }
    
    // Single match - get full details
    $match = $results[0];
    $tmdbId = $match['id'];
    
    $formResult = formatTMDBForAdminForm($tmdbId);
    
    // Check for API errors (not "movie not found" - we already found it via search)
    if (!$formResult['success']) {
        // Determine if this is a retryable error
        $isRetryable = in_array($formResult['error'], ['rate_limited', 'server_error', 'network_error', 'ssl_error']);
        
        echo json_encode([
            'success' => false,
            'error_type' => $isRetryable ? 'tmdb_unavailable' : 'details_failed',
            'title' => $title,
            'message' => $formResult['error_message'] ?? 'Could not fetch movie details',
            'retry_after' => $formResult['retry_after'] ?? null,
            'retryable' => $isRetryable
        ]);
        return;
    }
    
    $formData = $formResult['data'];
    
    error_log("Letterboxd Lookup SUCCESS: '$title' -> '{$formData['title']}' (TMDB ID: $tmdbId)");
    
    echo json_encode([
        'success' => true,
        'matched' => true,
        'multiple_matches' => false,
        'original_title' => $title,
        'form_data' => $formData,
        'notice' => 'Review this data carefully before confirming.'
    ]);
}

/**
 * Get details for a specific TMDB ID (for selection from multiple matches)
 */
function getMovieDetailsAction() {
    $tmdbId = intval($_GET['tmdb_id'] ?? 0);
    
    if (!$tmdbId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'TMDB ID required']);
        return;
    }
    
    $formResult = formatTMDBForAdminForm($tmdbId);
    
    // Check for API errors
    if (!$formResult['success']) {
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
        'form_data' => $formResult['data']
    ]);
}
