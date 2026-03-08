<?php
/**
 * MovieBook - Showtime Data Layer (SHOWTIME-FIRST ARCHITECTURE)
 * 
 * HARD RULES:
 * 1. ALL queries start from showtimes table, not movies
 * 2. A movie appears in booking ONLY IF it has active shows
 * 3. City is REQUIRED, not optional
 * 4. Date range determines what's "Now Showing" vs "Upcoming"
 * 5. NO movie metadata (release_date, status, popularity) affects booking
 * 
 * BOOKING FLOW:
 * City → Date → Theaters with shows → Movies derived from shows
 */

/**
 * Get all movies currently showing in a city
 * SHOWTIME-FIRST: Starts from showtimes, derives movies
 * 
 * @param PDO $pdo Database connection
 * @param string $city City name (REQUIRED)
 * @param string|null $date Date to check (defaults to today)
 * @return array Movies with active showtimes
 */
function getShowingMoviesInCity($pdo, $city, $date = null) {
    if (!$pdo || empty($city)) {
        return [];
    }
    
    $targetDate = $date ?? date('Y-m-d');
    
    try {
        // SHOWTIME-FIRST: Query showtimes, get movies
        $stmt = $pdo->prepare("
            SELECT 
                m.id,
                m.title,
                m.poster_url,
                m.backdrop_url,
                m.genre,
                m.rating,
                m.runtime,
                m.description,
                m.director,
                COUNT(DISTINCT s.id) as show_count,
                MIN(s.show_time) as first_show,
                MAX(s.show_time) as last_show,
                COUNT(DISTINCT t.id) as theater_count
            FROM showtimes s
            INNER JOIN theaters t ON s.theater_id = t.id
            INNER JOIN movies m ON s.movie_id = m.id
            WHERE t.city = ?
            AND t.is_active = 1
            AND s.show_date = ?
            AND m.poster_url IS NOT NULL AND m.poster_url != ''
            GROUP BY m.id
            ORDER BY show_count DESC, m.rating DESC
        ");
        $stmt->execute([$city, $targetDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("getShowingMoviesInCity failed: " . $e->getMessage());
        return [];
    }
}

/**
 * Get movies with upcoming shows in city (future dates only)
 * SHOWTIME-FIRST: Derives movies from scheduled showtimes
 * 
 * @param PDO $pdo Database connection
 * @param string $city City name (REQUIRED)
 * @param int $days Number of days to look ahead (default 7)
 * @return array Movies with upcoming showtimes
 */
function getUpcomingShowsInCity($pdo, $city, $days = 7) {
    if (!$pdo || empty($city)) {
        return [];
    }
    
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $endDate = date('Y-m-d', strtotime("+{$days} days"));
    
    try {
        // SHOWTIME-FIRST: Start from showtimes
        // Upcoming = has future shows but NOT showing today
        $stmt = $pdo->prepare("
            SELECT 
                m.id,
                m.title,
                m.poster_url,
                m.backdrop_url,
                m.genre,
                m.rating,
                m.runtime,
                m.description,
                MIN(s.show_date) as first_show_date,
                COUNT(DISTINCT s.id) as scheduled_shows
            FROM showtimes s
            INNER JOIN theaters t ON s.theater_id = t.id
            INNER JOIN movies m ON s.movie_id = m.id
            WHERE t.city = ?
            AND t.is_active = 1
            AND s.show_date BETWEEN ? AND ?
            AND m.poster_url IS NOT NULL AND m.poster_url != ''
            AND m.id NOT IN (
                -- Exclude movies showing today
                SELECT DISTINCT s2.movie_id 
                FROM showtimes s2
                INNER JOIN theaters t2 ON s2.theater_id = t2.id
                WHERE t2.city = ? AND t2.is_active = 1 AND s2.show_date = ?
            )
            GROUP BY m.id
            ORDER BY first_show_date ASC
        ");
        $stmt->execute([$city, $tomorrow, $endDate, $city, $today]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("getUpcomingShowsInCity failed: " . $e->getMessage());
        return [];
    }
}

/**
 * Get trending movies in city based on actual bookings
 * SHOWTIME-FIRST: Only movies with confirmed bookings AND current shows
 * 
 * @param PDO $pdo Database connection
 * @param string $city City name (REQUIRED)
 * @param int $limit Max results
 * @return array Trending movies with booking counts
 */
function getTrendingInCity($pdo, $city, $limit = 6) {
    if (!$pdo || empty($city)) {
        return [];
    }
    
    $today = date('Y-m-d');
    $weekAgo = date('Y-m-d', strtotime('-7 days'));
    
    try {
        // SHOWTIME-FIRST: Start from bookings → showtimes → movies
        // Only movies with current shows AND recent bookings
        $stmt = $pdo->prepare("
            SELECT 
                m.id,
                m.title,
                m.poster_url,
                m.backdrop_url,
                m.genre,
                m.rating,
                m.runtime,
                m.description,
                COUNT(DISTINCT b.id) as booking_count,
                COUNT(DISTINCT s_current.id) as current_shows
            FROM bookings b
            INNER JOIN showtimes s_booked ON b.showtime_id = s_booked.id
            INNER JOIN theaters t_booked ON s_booked.theater_id = t_booked.id
            INNER JOIN movies m ON s_booked.movie_id = m.id
            -- Must also have CURRENT shows available
            INNER JOIN showtimes s_current ON m.id = s_current.movie_id
            INNER JOIN theaters t_current ON s_current.theater_id = t_current.id
            WHERE t_booked.city = ?
            AND b.created_at >= ?
            AND b.status = 'confirmed'
            AND t_current.city = ?
            AND t_current.is_active = 1
            AND s_current.show_date >= ?
            AND m.poster_url IS NOT NULL AND m.poster_url != ''
            GROUP BY m.id
            HAVING booking_count > 0 AND current_shows > 0
            ORDER BY booking_count DESC
            LIMIT ?
        ");
        $stmt->execute([$city, $weekAgo, $city, $today, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("getTrendingInCity failed: " . $e->getMessage());
        return [];
    }
}

/**
 * Get showtimes for a specific movie in a city on a date
 * 
 * @param PDO $pdo Database connection
 * @param int $movieId Movie ID
 * @param string $city City name
 * @param string|null $date Date (defaults to today)
 * @return array Showtimes grouped by theater
 */
function getMovieShowtimes($pdo, $movieId, $city, $date = null) {
    if (!$pdo || !$movieId || empty($city)) {
        return [];
    }
    
    $targetDate = $date ?? date('Y-m-d');
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                s.id as showtime_id,
                s.show_time,
                s.price,
                s.available_seats,
                t.id as theater_id,
                t.name as theater_name,
                t.location as theater_location
            FROM showtimes s
            INNER JOIN theaters t ON s.theater_id = t.id
            WHERE s.movie_id = ?
            AND t.city = ?
            AND t.is_active = 1
            AND s.show_date = ?
            ORDER BY t.name, s.show_time
        ");
        $stmt->execute([$movieId, $city, $targetDate]);
        $shows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group by theater
        $grouped = [];
        foreach ($shows as $show) {
            $tid = $show['theater_id'];
            if (!isset($grouped[$tid])) {
                $grouped[$tid] = [
                    'theater_id' => $tid,
                    'theater_name' => $show['theater_name'],
                    'theater_location' => $show['theater_location'],
                    'shows' => []
                ];
            }
            $grouped[$tid]['shows'][] = [
                'showtime_id' => $show['showtime_id'],
                'time' => $show['show_time'],
                'price' => $show['price'],
                'available_seats' => $show['available_seats']
            ];
        }
        
        return array_values($grouped);
        
    } catch (PDOException $e) {
        error_log("getMovieShowtimes failed: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if a movie is bookable in a city (has active shows)
 * 
 * @param PDO $pdo Database connection
 * @param int $movieId Movie ID
 * @param string $city City name
 * @return bool True if movie has shows in city
 */
function isMovieBookableInCity($pdo, $movieId, $city) {
    if (!$pdo || !$movieId || empty($city)) {
        return false;
    }
    
    $today = date('Y-m-d');
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM showtimes s
            INNER JOIN theaters t ON s.theater_id = t.id
            WHERE s.movie_id = ?
            AND t.city = ?
            AND t.is_active = 1
            AND s.show_date >= ?
        ");
        $stmt->execute([$movieId, $city, $today]);
        return $stmt->fetchColumn() > 0;
        
    } catch (PDOException $e) {
        error_log("isMovieBookableInCity failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get available cities (cities with active theaters)
 * 
 * @param PDO $pdo Database connection
 * @return array List of city names
 */
function getAvailableCities($pdo) {
    if (!$pdo) {
        return [];
    }
    
    try {
        $stmt = $pdo->query("
            SELECT DISTINCT city 
            FROM theaters 
            WHERE is_active = 1 
            ORDER BY city
        ");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
        
    } catch (PDOException $e) {
        error_log("getAvailableCities failed: " . $e->getMessage());
        return [];
    }
}

/**
 * Get booking stats for a city
 * 
 * @param PDO $pdo Database connection
 * @param string $city City name
 * @return array Stats array
 */
function getCityBookingStats($pdo, $city) {
    if (!$pdo || empty($city)) {
        return [
            'movies_showing' => 0,
            'theaters_active' => 0,
            'shows_today' => 0,
            'upcoming_movies' => 0
        ];
    }
    
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $nextWeek = date('Y-m-d', strtotime('+7 days'));
    
    try {
        // Movies showing today
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT s.movie_id) 
            FROM showtimes s 
            INNER JOIN theaters t ON s.theater_id = t.id 
            WHERE t.city = ? AND t.is_active = 1 AND s.show_date = ?
        ");
        $stmt->execute([$city, $today]);
        $moviesShowing = intval($stmt->fetchColumn());
        
        // Active theaters
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM theaters WHERE city = ? AND is_active = 1");
        $stmt->execute([$city]);
        $theatersActive = intval($stmt->fetchColumn());
        
        // Shows today
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM showtimes s 
            INNER JOIN theaters t ON s.theater_id = t.id 
            WHERE t.city = ? AND t.is_active = 1 AND s.show_date = ?
        ");
        $stmt->execute([$city, $today]);
        $showsToday = intval($stmt->fetchColumn());
        
        // Upcoming movies (not showing today)
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT s.movie_id) FROM showtimes s 
            INNER JOIN theaters t ON s.theater_id = t.id 
            WHERE t.city = ? AND t.is_active = 1 
            AND s.show_date BETWEEN ? AND ?
            AND s.movie_id NOT IN (
                SELECT DISTINCT s2.movie_id FROM showtimes s2 
                INNER JOIN theaters t2 ON s2.theater_id = t2.id 
                WHERE t2.city = ? AND t2.is_active = 1 AND s2.show_date = ?
            )
        ");
        $stmt->execute([$city, $tomorrow, $nextWeek, $city, $today]);
        $upcomingMovies = intval($stmt->fetchColumn());
        
        return [
            'movies_showing' => $moviesShowing,
            'theaters_active' => $theatersActive,
            'shows_today' => $showsToday,
            'upcoming_movies' => $upcomingMovies
        ];
        
    } catch (PDOException $e) {
        error_log("getCityBookingStats failed: " . $e->getMessage());
        return [
            'movies_showing' => 0,
            'theaters_active' => 0,
            'shows_today' => 0,
            'upcoming_movies' => 0
        ];
    }
}
