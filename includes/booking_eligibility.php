<?php
/**
 * MovieBook - Booking Eligibility (STRICT VERSION)
 * 
 * HARD RULES FOR TICKETS PAGE:
 * 1. EVERY movie shown MUST have actual showtime data in the database
 * 2. NO fallbacks, NO synthetic injection, NO status-based logic
 * 3. If no data exists, return empty array (fail silently)
 * 
 * SECTIONS:
 * - TRENDING: Movies with confirmed bookings in last 7 days, ranked by count
 * - NOW SHOWING: Movies with showtimes today or later
 * - UPCOMING: Movies with future showtimes only (not today), no current shows
 * 
 * Classic films without showtimes will NEVER appear in Tickets.
 */

/**
 * Check if movie has active (current or future) showtimes
 */
function hasActiveShowtimes($pdo, $movieId, $city = null) {
    if (!$pdo || !$movieId) {
        return false;
    }
    
    $today = date('Y-m-d');
    
    try {
        $sql = "
            SELECT COUNT(*) FROM showtimes s
            INNER JOIN theaters t ON s.theater_id = t.id
            WHERE s.movie_id = ? 
            AND s.show_date >= ?
            AND t.is_active = 1
        ";
        $params = [$movieId, $today];
        
        // Add city filter if provided
        if ($city !== null) {
            $sql .= " AND t.city = ?";
            $params[] = $city;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Booking eligibility check failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get TRENDING movies - STRICT RULES:
 * - ONLY movies with confirmed bookings in last 7 days
 * - MUST also have active showtimes (bookable now)
 * - NO FALLBACK - returns empty if no booking data
 * 
 * @param PDO $pdo Database connection
 * @param int $limit Max movies to return
 * @return array Movies with booking activity (empty if none)
 */
function getTrendingMovies($pdo, $limit = 6, $city = null) {
    if (!$pdo) {
        return ['movies' => [], 'is_fallback' => false];
    }
    
    $today = date('Y-m-d');
    $weekAgo = date('Y-m-d', strtotime('-7 days'));
    
    try {
        // STRICT: Only movies with REAL confirmed bookings in last 7 days
        // AND must have active showtimes (still bookable)
        // City filter ensures only local bookings count
        $sql = "
            SELECT m.*, COUNT(b.id) as booking_count
            FROM movies m
            INNER JOIN showtimes s ON m.id = s.movie_id
            INNER JOIN theaters t ON s.theater_id = t.id
            INNER JOIN bookings b ON s.id = b.showtime_id
            WHERE b.created_at >= ?
            AND b.status = 'confirmed'
            AND s.show_date >= ?
            AND t.is_active = 1
            AND m.poster_url IS NOT NULL AND m.poster_url != ''
        ";
        $params = [$weekAgo, $today];
        
        // Add city filter if provided
        if ($city !== null) {
            $sql .= " AND t.city = ?";
            $params[] = $city;
        }
        
        $sql .= "
            GROUP BY m.id
            HAVING booking_count > 0
            ORDER BY booking_count DESC
            LIMIT ?
        ";
        $params[] = $limit;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $trending = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // NO FALLBACK - return empty if no real booking data
        return ['movies' => $trending, 'is_fallback' => false];
        
    } catch (PDOException $e) {
        error_log("Get trending movies failed: " . $e->getMessage());
        return ['movies' => [], 'is_fallback' => false];
    }
}

/**
 * Get NOW SHOWING movies - STRICT RULES:
 * - ONLY movies with showtimes today or later
 * - MUST have active theater
 * - Excludes movies already in trending
 * 
 * @param PDO $pdo Database connection
 * @param array $excludeIds Movie IDs to exclude (trending)
 * @param int $limit Max movies to return
 * @return array Now showing movies (empty if none)
 */
function getNowShowingMovies($pdo, $excludeIds = [], $limit = 10, $city = null) {
    if (!$pdo) {
        return [];
    }
    
    $today = date('Y-m-d');
    $exclude = empty($excludeIds) ? [0] : array_map('intval', $excludeIds);
    $excludePlaceholders = implode(',', array_fill(0, count($exclude), '?'));
    
    try {
        // STRICT: INNER JOIN to showtimes - NO showtime = NO display
        // City filter ensures only movies showing in selected city
        $sql = "
            SELECT DISTINCT m.* 
            FROM movies m
            INNER JOIN showtimes s ON m.id = s.movie_id
            INNER JOIN theaters t ON s.theater_id = t.id
            WHERE s.show_date >= ?
            AND t.is_active = 1
            AND m.id NOT IN ({$excludePlaceholders})
            AND m.poster_url IS NOT NULL AND m.poster_url != ''
        ";
        $params = array_merge([$today], $exclude);
        
        // Add city filter if provided
        if ($city !== null) {
            $sql .= " AND t.city = ?";
            $params[] = $city;
        }
        
        $sql .= " ORDER BY m.rating DESC, s.show_date ASC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Get now showing movies failed: " . $e->getMessage());
        return [];
    }
}

/**
 * Get UPCOMING movies - STRICT RULES:
 * - ONLY movies with scheduled showtimes in the FUTURE (not today)
 * - Movies that are NOT currently showing (no showtimes today)
 * - NO status-based logic, NO release_date fallback
 * 
 * @param PDO $pdo Database connection
 * @param int $limit Max movies to return
 * @return array Upcoming movies with future showtimes (empty if none)
 */
function getUpcomingMovies($pdo, $limit = 10, $city = null) {
    if (!$pdo) {
        return [];
    }
    
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    
    try {
        // STRICT: Must have future showtimes AND no current showtimes
        // This finds movies scheduled for future but not showing today
        // City filter ensures only movies coming to selected city
        
        // Build city condition for reuse
        $cityCondition = $city !== null ? " AND t.city = ?" : "";
        $cityCondition2 = $city !== null ? " AND t2.city = ?" : "";
        
        $sql = "
            SELECT DISTINCT m.*, MIN(s.show_date) as first_showtime
            FROM movies m
            INNER JOIN showtimes s ON m.id = s.movie_id
            INNER JOIN theaters t ON s.theater_id = t.id
            WHERE s.show_date >= ?
            AND t.is_active = 1
            AND m.poster_url IS NOT NULL AND m.poster_url != ''
            {$cityCondition}
            AND m.id NOT IN (
                SELECT DISTINCT s2.movie_id 
                FROM showtimes s2
                INNER JOIN theaters t2 ON s2.theater_id = t2.id
                WHERE s2.show_date = ? AND t2.is_active = 1
                {$cityCondition2}
            )
            GROUP BY m.id
            ORDER BY first_showtime ASC
            LIMIT ?
        ";
        
        $params = [$tomorrow];
        if ($city !== null) $params[] = $city;
        $params[] = $today;
        if ($city !== null) $params[] = $city;
        $params[] = $limit;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Get upcoming movies failed: " . $e->getMessage());
        return [];
    }
}

/**
 * Get ALL bookable movies - STRICT RULES:
 * - ONLY movies with active showtimes
 * 
 * @param PDO $pdo Database connection
 * @param int $limit Max movies to return
 * @return array Movies with active showtimes
 */
function getBookableMovies($pdo, $limit = 20, $city = null) {
    if (!$pdo) {
        return [];
    }
    
    $today = date('Y-m-d');
    
    try {
        $sql = "
            SELECT DISTINCT m.* 
            FROM movies m
            INNER JOIN showtimes s ON m.id = s.movie_id
            INNER JOIN theaters t ON s.theater_id = t.id
            WHERE s.show_date >= ?
            AND t.is_active = 1
            AND m.poster_url IS NOT NULL AND m.poster_url != ''
        ";
        $params = [$today];
        
        // Add city filter if provided
        if ($city !== null) {
            $sql .= " AND t.city = ?";
            $params[] = $city;
        }
        
        $sql .= " ORDER BY m.rating DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get bookable movies failed: " . $e->getMessage());
        return [];
    }
}

/**
 * Determine booking badge - STRICT RULES:
 * - 'bookable': Has showtimes today or later
 * - 'coming_soon': Has future showtimes only (not today)
 * - 'classic': No showtimes at all (NOT for Tickets page)
 * 
 * NO status-based fallbacks!
 */
function getBookingBadge($pdo, $movieId, $movie = null, $city = null) {
    if (!$pdo || !$movieId) {
        return 'classic';
    }
    
    $today = date('Y-m-d');
    
    try {
        // Check for today or future showtimes
        $sql = "
            SELECT MIN(s.show_date) as first_showtime
            FROM showtimes s
            INNER JOIN theaters t ON s.theater_id = t.id
            WHERE s.movie_id = ? 
            AND s.show_date >= ?
            AND t.is_active = 1
        ";
        $params = [$movieId, $today];
        
        if ($city !== null) {
            $sql .= " AND t.city = ?";
            $params[] = $city;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result || !$result['first_showtime']) {
            return 'classic'; // No showtimes
        }
        
        if ($result['first_showtime'] === $today) {
            return 'bookable'; // Has shows today
        }
        
        return 'coming_soon'; // Future shows only
        
    } catch (PDOException $e) {
        error_log("Booking badge check failed: " . $e->getMessage());
        return 'classic';
    }
}

/**
 * Check if movie has upcoming showtimes (future dates only, not today)
 */
function hasUpcomingShowtimes($pdo, $movieId, $city = null) {
    if (!$pdo || !$movieId) {
        return false;
    }
    
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    
    try {
        $sql = "
            SELECT COUNT(*) FROM showtimes s
            INNER JOIN theaters t ON s.theater_id = t.id
            WHERE s.movie_id = ? 
            AND s.show_date >= ?
            AND t.is_active = 1
        ";
        $params = [$movieId, $tomorrow];
        
        if ($city !== null) {
            $sql .= " AND t.city = ?";
            $params[] = $city;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Upcoming showtimes check failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get showtime count for a movie
 */
function getUpcomingShowtimeCount($pdo, $movieId, $city = null) {
    if (!$pdo || !$movieId) {
        return 0;
    }
    
    $today = date('Y-m-d');
    
    try {
        $sql = "
            SELECT COUNT(*) FROM showtimes s
            INNER JOIN theaters t ON s.theater_id = t.id
            WHERE s.movie_id = ? 
            AND s.show_date >= ?
            AND t.is_active = 1
        ";
        $params = [$movieId, $today];
        
        if ($city !== null) {
            $sql .= " AND t.city = ?";
            $params[] = $city;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return intval($stmt->fetchColumn());
    } catch (PDOException $e) {
        error_log("Showtime count failed: " . $e->getMessage());
        return 0;
    }
}
