<?php
/**
 * MovieBook - Movie Data Contract
 * 
 * This file enforces the global movie data contract.
 * ANY page rendering a movie card MUST validate through this layer.
 * 
 * Required fields for a valid movie:
 * - id: INT, non-zero
 * - title: STRING, non-empty
 * - poster_url: STRING, non-empty (valid image URL)
 * - description: STRING, non-empty (for details page)
 * 
 * Recommended fields (warn if missing):
 * - release_date: DATE
 * - genre: STRING
 * - rating: DECIMAL
 */

/**
 * Check if a movie passes the minimum display contract
 * 
 * @param array $movie Movie data array
 * @return bool True if movie can be displayed
 */
function isValidMovie($movie) {
    if (!is_array($movie)) {
        return false;
    }
    
    // Required fields - must exist and be non-empty
    $hasId = !empty($movie['id']) && intval($movie['id']) > 0;
    $hasTitle = !empty($movie['title']) && trim($movie['title']) !== '';
    $hasPoster = !empty($movie['poster_url']) && filter_var($movie['poster_url'], FILTER_VALIDATE_URL);
    
    return $hasId && $hasTitle && $hasPoster;
}

/**
 * Check if a movie passes the FULL display contract (for details page)
 * Requires description in addition to basic fields
 * 
 * @param array $movie Movie data array
 * @return bool True if movie has complete data
 */
function isCompleteMovie($movie) {
    if (!isValidMovie($movie)) {
        return false;
    }
    
    // Additional requirement for details page
    $hasDescription = !empty($movie['description']) && trim($movie['description']) !== '';
    
    return $hasDescription;
}

/**
 * Filter an array of movies to only include valid ones
 * 
 * @param array $movies Array of movie records
 * @return array Filtered array with only valid movies
 */
function filterValidMovies($movies) {
    if (!is_array($movies)) {
        return [];
    }
    return array_values(array_filter($movies, 'isValidMovie'));
}

/**
 * Get movie validation errors for debugging
 * 
 * @param array $movie Movie data array
 * @return array List of validation error messages
 */
function getMovieValidationErrors($movie) {
    $errors = [];
    
    if (!is_array($movie)) {
        return ['Movie data is not an array'];
    }
    
    if (empty($movie['id']) || intval($movie['id']) <= 0) {
        $errors[] = 'Missing or invalid ID';
    }
    
    if (empty($movie['title']) || trim($movie['title']) === '') {
        $errors[] = 'Missing or empty title';
    }
    
    if (empty($movie['poster_url'])) {
        $errors[] = 'Missing poster URL';
    } elseif (!filter_var($movie['poster_url'], FILTER_VALIDATE_URL)) {
        $errors[] = 'Invalid poster URL format';
    }
    
    if (empty($movie['description']) || trim($movie['description']) === '') {
        $errors[] = 'Missing or empty description';
    }
    
    return $errors;
}

/**
 * Prepare movie for card rendering with fallbacks
 * This DOES NOT bypass validation - use only after isValidMovie() passes
 * 
 * @param array $movie Raw movie data
 * @return array Movie with safe defaults for optional fields
 */
function prepareMovieForCard($movie) {
    return [
        'id' => intval($movie['id']),
        'title' => htmlspecialchars($movie['title'] ?? 'Unknown'),
        'poster_url' => $movie['poster_url'],
        'backdrop_url' => $movie['backdrop_url'] ?? $movie['poster_url'],
        'description' => htmlspecialchars($movie['description'] ?? ''),
        'genre' => htmlspecialchars($movie['genre'] ?? ''),
        'rating' => floatval($movie['rating'] ?? 0),
        'release_date' => $movie['release_date'] ?? null,
        'release_year' => $movie['release_date'] ? date('Y', strtotime($movie['release_date'])) : null,
        'runtime' => intval($movie['runtime'] ?? 0),
        'director' => htmlspecialchars($movie['director'] ?? ''),
        'status' => $movie['status'] ?? 'now_showing'
    ];
}
