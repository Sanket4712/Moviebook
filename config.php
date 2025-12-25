<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'moviebook');

// TMDB API Configuration
define('TMDB_API_KEY', '4c5d58fa93e867d22f81b1f86ed50a75');
define('TMDB_API_TOKEN', 'eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiI0YzVkNThmYTkzZTg2N2QyMmY4MWIxZjg2ZWQ1MGE3NSIsIm5iZiI6MTc2MDYwNjE0Ni43NTUsInN1YiI6IjY4ZjBiN2MyY2U4Nzk0MmY5OGM3N2JkNyIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.YomwzMc36r-cPCG7HG7h0MS0jcgvDu7WM21Ei3PzO-c');
define('TMDB_BASE_URL', 'https://api.themoviedb.org/3');
define('TMDB_IMAGE_BASE_URL', 'https://image.tmdb.org/t/p');

// Create database connection
function getDBConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        return $conn;
    } catch (Exception $e) {
        die("Database connection error: " . $e->getMessage());
    }
}
?>
