<?php
/**
 * TMDB API Service - READ ONLY
 * 
 * Provides functions to FETCH data from The Movie Database (TMDB) API.
 * 
 * CRITICAL: This module is READ-ONLY. It CANNOT insert movies into the database.
 * All movie creation MUST go through the Admin panel and admin_movies.php API.
 * 
 * INVARIANT: AI assists the admin, AI never owns the database.
 * Every movie exists only because an admin explicitly chose to add it.
 */

// TMDB API Configuration
define('TMDB_API_KEY', 'eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiI0ZTQwNjI1MDRhNTIwZjk5MjFlN2YxZDNhYTlkMjZjNCIsIm5iZiI6MTczNzAxNTQ3Ny4yNjgsInN1YiI6IjY3ODg0Y2U1MTgyNjA0MzQ5MDBhOTk5ZiIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.placeholder'); // User should replace this
define('TMDB_API_BASE', 'https://api.themoviedb.org/3');
define('TMDB_IMAGE_BASE', 'https://image.tmdb.org/t/p/');

/**
 * Get SSL certificate path for cURL on Windows/XAMPP
 * Returns the path to a CA bundle if found, null otherwise
 */
function getSSLCertPath() {
    // Common CA bundle locations on Windows/XAMPP
    $possiblePaths = [
        'C:/xampp/php/extras/ssl/cacert.pem',
        'C:/xampp/apache/bin/curl-ca-bundle.crt',
        'C:/xampp/perl/vendor/lib/mozilla/cacert.pem',
        __DIR__ . '/../cacert.pem',  // Project-local fallback
        __DIR__ . '/cacert.pem',
    ];
    
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    
    return null;
}

/**
 * Check if we're running on localhost
 */
function isLocalhost() {
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    return in_array($host, ['localhost', '127.0.0.1', '::1']) 
        || strpos($host, 'localhost:') === 0;
}

/**
 * Make a request to TMDB API with robust error handling
 * 
 * Returns structured response:
 * [
 *   'success' => bool,
 *   'data' => array|null,
 *   'error' => string|null (error type: 'network_error', 'ssl_error', 'auth_error', 'rate_limited', 'server_error', 'parse_error'),
 *   'error_message' => string|null (human-readable message),
 *   'http_code' => int,
 *   'retry_after' => int|null (seconds to wait before retry, for rate limiting)
 * ]
 */
function tmdbRequest($endpoint, $params = []) {
    $url = TMDB_API_BASE . $endpoint;
    
    // Use the API key from environment or fallback
    $apiKey = getenv('TMDB_API_KEY') ?: '4e4062504a520f9921e7f1d3aa9d26c4';
    $params['api_key'] = $apiKey;
    
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    // Log the request (mask API key for security)
    $logUrl = preg_replace('/api_key=[^&]+/', 'api_key=***', $url);
    error_log("TMDB Request: $logUrl");
    
    // Initialize cURL
    $ch = curl_init();
    
    // Base cURL options
    $curlOpts = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json'
        ],
        CURLOPT_HEADER => true, // Include headers in output for rate limit parsing
    ];
    
    // SSL Configuration - explicit handling for Windows/XAMPP
    $caPath = getSSLCertPath();
    
    if ($caPath !== null) {
        // CA bundle found - use proper SSL verification
        $curlOpts[CURLOPT_SSL_VERIFYPEER] = true;
        $curlOpts[CURLOPT_SSL_VERIFYHOST] = 2;
        $curlOpts[CURLOPT_CAINFO] = $caPath;
        error_log("TMDB SSL: Using CA bundle at $caPath");
    } elseif (isLocalhost()) {
        // No CA bundle found, but we're on localhost - disable verification with warning
        $curlOpts[CURLOPT_SSL_VERIFYPEER] = false;
        $curlOpts[CURLOPT_SSL_VERIFYHOST] = 0;
        error_log("TMDB SSL WARNING: No CA bundle found. SSL verification disabled for localhost development.");
    } else {
        // Production environment without CA bundle - this is a configuration error
        $curlOpts[CURLOPT_SSL_VERIFYPEER] = true;
        $curlOpts[CURLOPT_SSL_VERIFYHOST] = 2;
        error_log("TMDB SSL ERROR: No CA bundle found in production. Request may fail.");
    }
    
    curl_setopt_array($ch, $curlOpts);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $curlErrno = curl_errno($ch);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Log response summary
    error_log("TMDB Response: HTTP $httpCode, Size: " . strlen($response) . " bytes, cURL errno: $curlErrno");
    
    // Handle cURL/network errors
    if ($response === false || $curlErrno !== 0) {
        $errorType = 'network_error';
        $errorMessage = 'Could not connect to TMDB';
        
        // Provide more specific error messages
        switch ($curlErrno) {
            case CURLE_SSL_CONNECT_ERROR:
            case CURLE_SSL_CERTPROBLEM:
            case CURLE_SSL_CIPHER:
            case CURLE_SSL_CACERT:
            case CURLE_SSL_CACERT_BADFILE:
                $errorType = 'ssl_error';
                $errorMessage = 'SSL certificate error. Check server SSL configuration.';
                break;
            case CURLE_COULDNT_RESOLVE_HOST:
                $errorMessage = 'Could not resolve TMDB server. Check internet connection.';
                break;
            case CURLE_COULDNT_CONNECT:
                $errorMessage = 'Could not connect to TMDB server. Check internet/firewall.';
                break;
            case CURLE_OPERATION_TIMEDOUT:
                $errorMessage = 'TMDB request timed out. Try again.';
                break;
        }
        
        error_log("TMDB cURL Error [$curlErrno]: $curlError");
        
        return [
            'success' => false,
            'data' => null,
            'error' => $errorType,
            'error_message' => $errorMessage,
            'http_code' => 0,
            'retry_after' => null
        ];
    }
    
    // Separate headers and body
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    // Handle HTTP 401/403 - Authentication errors
    if ($httpCode === 401 || $httpCode === 403) {
        error_log("TMDB Auth Error: HTTP $httpCode - Invalid or missing API key");
        return [
            'success' => false,
            'data' => null,
            'error' => 'auth_error',
            'error_message' => 'TMDB API authentication failed. Check API key configuration.',
            'http_code' => $httpCode,
            'retry_after' => null
        ];
    }
    
    // Handle HTTP 429 - Rate limiting
    if ($httpCode === 429) {
        // Parse Retry-After header if present, otherwise use default
        $retryAfter = 2; // Default 2 seconds
        if (preg_match('/Retry-After:\s*(\d+)/i', $headers, $matches)) {
            $retryAfter = intval($matches[1]);
        }
        
        error_log("TMDB Rate Limited: Retry after $retryAfter seconds");
        
        return [
            'success' => false,
            'data' => null,
            'error' => 'rate_limited',
            'error_message' => "TMDB rate limit exceeded. Wait $retryAfter seconds.",
            'http_code' => 429,
            'retry_after' => $retryAfter
        ];
    }
    
    // Handle HTTP 5xx - Server errors
    if ($httpCode >= 500 && $httpCode < 600) {
        error_log("TMDB Server Error: HTTP $httpCode");
        return [
            'success' => false,
            'data' => null,
            'error' => 'server_error',
            'error_message' => "TMDB server error (HTTP $httpCode). Try again later.",
            'http_code' => $httpCode,
            'retry_after' => 5 // Suggest retry after 5 seconds for server errors
        ];
    }
    
    // Handle other HTTP errors (4xx except 401/403/429)
    if ($httpCode >= 400 && $httpCode < 500) {
        error_log("TMDB HTTP Error: $httpCode - $body");
        return [
            'success' => false,
            'data' => null,
            'error' => 'api_error',
            'error_message' => "TMDB API error (HTTP $httpCode)",
            'http_code' => $httpCode,
            'retry_after' => null
        ];
    }
    
    // Parse JSON response
    $data = json_decode($body, true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        error_log("TMDB JSON Parse Error: " . json_last_error_msg() . " - Body: " . substr($body, 0, 200));
        return [
            'success' => false,
            'data' => null,
            'error' => 'parse_error',
            'error_message' => 'Invalid response from TMDB server',
            'http_code' => $httpCode,
            'retry_after' => null
        ];
    }
    
    // Success!
    return [
        'success' => true,
        'data' => $data,
        'error' => null,
        'error_message' => null,
        'http_code' => $httpCode,
        'retry_after' => null
    ];
}

/**
 * Legacy wrapper for backward compatibility
 * Returns the data array directly (like the old function)
 */
function tmdbRequestLegacy($endpoint, $params = []) {
    $result = tmdbRequest($endpoint, $params);
    return $result['success'] ? $result['data'] : null;
}

/**
 * Search for movies on TMDB with optional year filtering
 * Returns: ['success' => bool, 'results' => array, 'error' => string|null]
 */
function searchTMDB($query, $page = 1, $year = null) {
    $params = [
        'query' => $query,
        'page' => $page,
        'include_adult' => false
    ];
    
    // Add year filter if provided (TMDB supports year parameter)
    if ($year && is_numeric($year) && $year > 1800 && $year < 2100) {
        $params['year'] = intval($year);
    }
    
    $result = tmdbRequest('/search/movie', $params);
    
    if (!$result['success']) {
        return [
            'success' => false,
            'results' => [],
            'error' => $result['error'],
            'error_message' => $result['error_message'] ?? 'Unknown error'
        ];
    }
    
    return [
        'success' => true,
        'results' => $result['data']['results'] ?? [],
        'total_results' => $result['data']['total_results'] ?? 0,
        'error' => null
    ];
}

/**
 * Advanced movie search with title normalization and year tolerance
 */
function searchTMDBAdvanced($title, $year = null) {
    // Normalize title: remove year suffix, parentheses, extra whitespace
    $normalizedTitle = normalizeMovieTitle($title);
    
    // Extract year from title if not provided
    if (!$year) {
        $year = extractYearFromTitle($title);
    }
    
    error_log("TMDB Advanced Search: '$normalizedTitle' (year: " . ($year ?: 'none') . ")");
    
    // First search: with year filter if available
    if ($year) {
        $result = searchTMDB($normalizedTitle, 1, $year);
        if ($result['success'] && !empty($result['results'])) {
            return $result;
        }
        
        // Second search: ±1 year tolerance
        $resultPlus = searchTMDB($normalizedTitle, 1, $year + 1);
        $resultMinus = searchTMDB($normalizedTitle, 1, $year - 1);
        
        $combinedResults = [];
        if ($resultPlus['success']) $combinedResults = array_merge($combinedResults, $resultPlus['results']);
        if ($resultMinus['success']) $combinedResults = array_merge($combinedResults, $resultMinus['results']);
        
        if (!empty($combinedResults)) {
            return [
                'success' => true,
                'results' => $combinedResults,
                'total_results' => count($combinedResults),
                'error' => null
            ];
        }
    }
    
    // Final fallback: search without year filter
    return searchTMDB($normalizedTitle, 1);
}

/**
 * Normalize movie title for better search accuracy
 */
function normalizeMovieTitle($title) {
    // Remove year in parentheses: "Contact (1997)" -> "Contact"
    $title = preg_replace('/\s*\(\d{4}\)\s*$/', '', $title);
    
    // Remove year at end without parentheses: "Contact 1997" -> "Contact"
    $title = preg_replace('/\s+\d{4}\s*$/', '', $title);
    
    // Remove special characters except letters, numbers, spaces, and basic punctuation
    $title = preg_replace('/[^\p{L}\p{N}\s\-:\'\.]/u', '', $title);
    
    // Collapse multiple spaces
    $title = preg_replace('/\s+/', ' ', $title);
    
    return trim($title);
}

/**
 * Extract year from movie title
 */
function extractYearFromTitle($title) {
    // Match year in parentheses: "Contact (1997)"
    if (preg_match('/\((\d{4})\)\s*$/', $title, $matches)) {
        return intval($matches[1]);
    }
    
    // Match year at end: "Contact 1997"
    if (preg_match('/\s(\d{4})\s*$/', $title, $matches)) {
        $year = intval($matches[1]);
        // Validate it's a reasonable movie year
        if ($year > 1880 && $year <= date('Y') + 5) {
            return $year;
        }
    }
    
    return null;
}


/**
 * Get popular movies from TMDB (READ ONLY)
 */
function getPopularMovies($page = 1) {
    return tmdbRequest('/movie/popular', ['page' => $page]);
}

/**
 * Get top rated movies from TMDB (READ ONLY)
 */
function getTopRatedMovies($page = 1) {
    return tmdbRequest('/movie/top_rated', ['page' => $page]);
}

/**
 * Get now playing movies from TMDB (READ ONLY)
 */
function getNowPlayingMovies($page = 1) {
    return tmdbRequest('/movie/now_playing', ['page' => $page]);
}

/**
 * Get movie details from TMDB (READ ONLY)
 */
function getMovieDetails($tmdbId) {
    return tmdbRequest("/movie/{$tmdbId}", [
        'append_to_response' => 'credits'
    ]);
}

/**
 * Get poster URL from TMDB path
 */
function getPosterUrl($path, $size = 'w500') {
    if (empty($path)) return null;
    return TMDB_IMAGE_BASE . $size . $path;
}

/**
 * Get backdrop URL from TMDB path
 */
function getBackdropUrl($path, $size = 'w1280') {
    if (empty($path)) return null;
    return TMDB_IMAGE_BASE . $size . $path;
}

/**
 * TMDB Genre ID to Name mapping
 */
function getGenreMap() {
    return [
        28 => 'Action',
        12 => 'Adventure',
        16 => 'Animation',
        35 => 'Comedy',
        80 => 'Crime',
        99 => 'Documentary',
        18 => 'Drama',
        10751 => 'Family',
        14 => 'Fantasy',
        36 => 'History',
        27 => 'Horror',
        10402 => 'Music',
        9648 => 'Mystery',
        10749 => 'Romance',
        878 => 'Science Fiction',
        10770 => 'TV Movie',
        53 => 'Thriller',
        10752 => 'War',
        37 => 'Western'
    ];
}

/**
 * Format TMDB movie data for admin form pre-fill
 * Returns structured data that can be used in the admin movie form
 * Does NOT insert into database - admin must confirm
 */
function formatTMDBForAdminForm($tmdbId) {
    $result = getMovieDetails($tmdbId);
    
    // Check for API errors FIRST - propagate error details
    if (!$result['success']) {
        error_log("formatTMDBForAdminForm: TMDB API error for ID $tmdbId - " . ($result['error_message'] ?? $result['error']));
        return [
            'success' => false,
            'error' => $result['error'],
            'error_message' => $result['error_message'] ?? 'Could not fetch movie details from TMDB',
            'retry_after' => $result['retry_after'] ?? null
        ];
    }
    
    $details = $result['data'];
    
    // Check if we got valid movie data
    if (empty($details) || !isset($details['id'])) {
        error_log("formatTMDBForAdminForm: Empty or invalid response for TMDB ID $tmdbId");
        return [
            'success' => false,
            'error' => 'invalid_data',
            'error_message' => 'TMDB returned invalid movie data',
            'retry_after' => null
        ];
    }
    
    // Extract director from credits
    $director = '';
    $cast = [];
    if (isset($details['credits']['crew'])) {
        foreach ($details['credits']['crew'] as $crew) {
            if ($crew['job'] === 'Director') {
                $director = $crew['name'];
                break;
            }
        }
    }
    if (isset($details['credits']['cast'])) {
        foreach (array_slice($details['credits']['cast'], 0, 5) as $actor) {
            $cast[] = $actor['name'];
        }
    }
    
    // Extract genres
    $genres = [];
    if (isset($details['genres'])) {
        foreach ($details['genres'] as $genre) {
            $genres[] = $genre['name'];
        }
    }
    
    // Return successful result with form data
    return [
        'success' => true,
        'error' => null,
        'error_message' => null,
        'data' => [
            'title' => $details['title'] ?? '',
            'description' => $details['overview'] ?? '',
            'poster_url' => getPosterUrl($details['poster_path'] ?? ''),
            'backdrop_url' => getBackdropUrl($details['backdrop_path'] ?? ''),
            'director' => $director,
            'cast' => implode(', ', $cast),
            'genre' => implode(', ', $genres),
            'runtime' => $details['runtime'] ?? 0,
            'rating' => $details['vote_average'] ?? 0,
            'release_date' => $details['release_date'] ?? null,
            'release_year' => $details['release_date'] ? substr($details['release_date'], 0, 4) : '',
            'country' => isset($details['production_countries'][0]) ? $details['production_countries'][0]['name'] : '',
            'language' => isset($details['spoken_languages'][0]) ? $details['spoken_languages'][0]['english_name'] : '',
            'tmdb_id' => $tmdbId
        ]
    ];
}

// =============================================================================
// DISABLED FUNCTIONS - These used to insert movies directly but are now blocked
// =============================================================================

/**
 * DISABLED - cacheMovieFromTMDB
 * This function previously inserted movies directly into the database.
 * It has been disabled to enforce admin-only movie creation.
 */
function cacheMovieFromTMDB($tmdbMovie, $pdo) {
    error_log("BLOCKED: cacheMovieFromTMDB called - direct movie insertion is disabled. Use Admin panel.");
    return null;
}

/**
 * DISABLED - getOrFetchMovie
 * This function previously inserted movies if not cached.
 * It has been disabled to enforce admin-only movie creation.
 */
function getOrFetchMovie($tmdbId, $pdo) {
    // Only lookup, never insert
    $stmt = $pdo->prepare("SELECT * FROM movies WHERE tmdb_id = ?");
    $stmt->execute([$tmdbId]);
    $movie = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($movie) {
        return $movie;
    }
    
    // Return null instead of auto-inserting
    error_log("Movie with TMDB ID $tmdbId not in database. Must be added through Admin panel.");
    return null;
}

/**
 * DISABLED - importPopularMovies
 */
function importPopularMovies($pdo, $pages = 3) {
    error_log("BLOCKED: importPopularMovies called - bulk movie import is disabled. Use Admin panel.");
    return 0;
}

/**
 * DISABLED - importTopRatedMovies
 */
function importTopRatedMovies($pdo, $pages = 2) {
    error_log("BLOCKED: importTopRatedMovies called - bulk movie import is disabled. Use Admin panel.");
    return 0;
}
