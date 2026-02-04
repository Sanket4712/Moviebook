<?php 
/**
 * MovieBook - Tickets Page (SHOWTIME-FIRST ARCHITECTURE)
 * 
 * HARD RULES:
 * 1. City is REQUIRED - all data is city-filtered
 * 2. Movies appear ONLY if they have active showtimes
 * 3. All queries start from showtimes table, not movies
 * 4. No movie metadata (release_date, status) affects display
 */

require_once '../includes/auth_check.php';
require_once '../includes/db.php';
require_once '../includes/movie_contract.php';
require_once '../includes/showtime_data.php';  // SHOWTIME-FIRST layer

$user = getCurrentUser();

// City is REQUIRED - get from session or use first available city
$selectedCity = $_SESSION['user_city'] ?? null;
$availableCities = [];

// Initialize empty arrays
$trending = [];
$nowShowing = [];
$upcoming = [];
$cityStats = [];
$heroMovie = null;
$heroReason = '';

if ($pdo) {
    // Get available cities
    $availableCities = getAvailableCities($pdo);
    
    // If no city selected or selected city has no theaters, pick first available
    if (empty($selectedCity) || !in_array($selectedCity, $availableCities)) {
        $selectedCity = !empty($availableCities) ? $availableCities[0] : null;
        if ($selectedCity) {
            $_SESSION['user_city'] = $selectedCity;
        }
    }
    
    // Only load data if we have a valid city
    if ($selectedCity) {
        try {
            // Get city stats
            $cityStats = getCityBookingStats($pdo, $selectedCity);
            
            // TRENDING: Movies with actual bookings AND current shows in this city
            $trending = getTrendingInCity($pdo, $selectedCity, 6);
            
            // NOW SHOWING: Movies playing TODAY in this city
            // Exclude trending movies to avoid duplication
            $trendingIds = array_column($trending, 'id');
            $allShowingToday = getShowingMoviesInCity($pdo, $selectedCity);
            $nowShowing = array_filter($allShowingToday, function($m) use ($trendingIds) {
                return !in_array($m['id'], $trendingIds);
            });
            $nowShowing = array_values($nowShowing); // Re-index
            
            // UPCOMING: Movies with FUTURE shows but NOT showing today
            $upcoming = getUpcomingShowsInCity($pdo, $selectedCity, 7);
            
            // Hero movie: first trending, or first now showing
            if (!empty($trending)) {
                $heroMovie = $trending[0];
                $bookings = $heroMovie['booking_count'] ?? 0;
                if ($bookings > 10) {
                    $heroReason = "🔥 Most Booked This Week • {$bookings} tickets";
                } elseif ($bookings > 0) {
                    $heroReason = "📈 Trending in {$selectedCity}";
                } else {
                    $heroReason = "⭐ Popular in {$selectedCity}";
                }
            } elseif (!empty($nowShowing)) {
                $heroMovie = $nowShowing[0];
                $heroReason = "🎬 Now Showing in {$selectedCity}";
            }
            
        } catch (PDOException $e) {
            error_log("Tickets page error: " . $e->getMessage());
        }
    }
}

// Get backdrop for hero
$heroBackdrop = $heroMovie ? ($heroMovie['backdrop_url'] ?? $heroMovie['poster_url'] ?? '') : '';

// Flag for empty state
$hasNoShows = empty($trending) && empty($nowShowing) && empty($upcoming);
$hasNoCity = empty($selectedCity);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Movie Tickets - MovieBook</title>
    <meta name="description" content="Book movie tickets online. See what's trending, now showing, and upcoming movies.">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0a0a0a;
            --bg-card: #141414;
            --bg-elevated: #1c1c1c;
            --primary: #e50914;
            --primary-dark: #b8070f;
            --gold: #f5c518;
            --text: #ffffff;
            --text-secondary: #a0a0a0;
            --text-muted: #666;
            --border: #2a2a2a;
            --gradient-dark: linear-gradient(180deg, transparent 0%, rgba(0,0,0,0.8) 60%, #0a0a0a 100%);
            --shadow-lg: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
            --shadow-glow: 0 0 40px rgba(229, 9, 20, 0.3);
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            line-height: 1.5;
            overflow-x: hidden;
        }
        
        /* ========== NAVBAR ========== */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 70px;
            background: rgba(10, 10, 10, 0.9);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255,255,255,0.05);
            z-index: 1000;
            display: flex;
            align-items: center;
            padding: 0 40px;
        }
        
        .nav-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .logo-img { height: 32px; }
        
        .nav-menu {
            display: flex;
            gap: 32px;
            list-style: none;
        }
        
        .nav-menu a {
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: color 0.2s;
            padding: 8px 0;
            position: relative;
        }
        
        .nav-menu a:hover, .nav-menu a.active { color: var(--text); }
        
        .nav-menu a.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--primary);
            border-radius: 2px;
        }
        
        .nav-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .location {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--text-secondary);
            font-size: 14px;
            cursor: pointer;
            padding: 8px 12px;
            background: var(--bg-elevated);
            border-radius: 8px;
            transition: all 0.2s;
        }
        
        .location:hover { background: var(--bg-card); color: var(--text); }
        .location i:first-child { color: var(--primary); }
        
        .search-box {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--bg-elevated);
            padding: 10px 16px;
            border-radius: 10px;
            border: 1px solid var(--border);
            transition: all 0.2s;
        }
        
        .search-box:focus-within { border-color: var(--primary); }
        
        .search-box input {
            background: transparent;
            border: none;
            color: var(--text);
            font-size: 14px;
            outline: none;
            width: 200px;
        }
        
        .search-box i { color: var(--text-muted); }
        
        /* ========== HERO BANNER ========== */
        .hero {
            position: relative;
            height: 85vh;
            min-height: 600px;
            margin-top: 70px;
            overflow: hidden;
        }
        
        .hero-backdrop {
            position: absolute;
            inset: 0;
        }
        
        .hero-backdrop img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center 20%;
        }
        
        .hero-overlay {
            position: absolute;
            inset: 0;
            background: 
                linear-gradient(90deg, rgba(10,10,10,0.95) 0%, rgba(10,10,10,0.4) 50%, rgba(10,10,10,0.95) 100%),
                linear-gradient(180deg, transparent 40%, rgba(10,10,10,0.9) 80%, #0a0a0a 100%);
        }
        
        .hero-content {
            position: relative;
            height: 100%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 40px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding-bottom: 80px;
        }
        
        .hero-reason {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, rgba(229,9,20,0.2) 0%, rgba(229,9,20,0.05) 100%);
            border: 1px solid rgba(229,9,20,0.4);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            color: #ff6b6b;
            margin-bottom: 16px;
            width: fit-content;
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .hero h1 {
            font-size: 4rem;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 16px;
            max-width: 700px;
            text-shadow: 0 4px 30px rgba(0,0,0,0.5);
        }
        
        .hero-meta {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 15px;
            color: var(--text-secondary);
            margin-bottom: 12px;
        }
        
        .hero-meta .rating {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--gold);
            font-weight: 600;
        }
        
        .hero-meta .separator { color: var(--text-muted); }
        
        .hero-description {
            font-size: 16px;
            color: var(--text-secondary);
            max-width: 500px;
            line-height: 1.7;
            margin-bottom: 28px;
        }
        
        .hero-actions {
            display: flex;
            gap: 14px;
        }
        
        .btn-hero {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 16px 32px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-hero-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            box-shadow: 0 8px 30px rgba(229, 9, 20, 0.4);
        }
        
        .btn-hero-primary:hover {
            transform: translateY(-4px) scale(1.02);
            box-shadow: 0 12px 40px rgba(229, 9, 20, 0.5);
        }
        
        .btn-hero-secondary {
            background: rgba(255,255,255,0.1);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
        }
        
        .btn-hero-secondary:hover {
            background: rgba(255,255,255,0.15);
            border-color: rgba(255,255,255,0.4);
            transform: translateY(-2px);
        }
        
        /* ========== SECTIONS ========== */
        .section {
            padding: 60px 40px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }
        
        .section-title {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .section-title i {
            color: var(--primary);
            font-size: 1.3rem;
        }
        
        .section-subtitle {
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 400;
            margin-left: 16px;
        }
        
        .see-all {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .see-all:hover { color: var(--primary); gap: 10px; }
        
        /* ========== TRENDING SECTION ========== */
        .section-trending {
            background: linear-gradient(180deg, transparent 0%, rgba(229,9,20,0.03) 50%, transparent 100%);
            border-top: 1px solid rgba(229,9,20,0.1);
        }
        
        .trending-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 24px;
        }
        
        .trending-card {
            position: relative;
            border-radius: 16px;
            overflow: hidden;
            background: var(--bg-card);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }
        
        .trending-card:hover {
            transform: translateY(-12px) scale(1.02);
            box-shadow: var(--shadow-lg), var(--shadow-glow);
            z-index: 10;
        }
        
        .trending-poster {
            position: relative;
            aspect-ratio: 2/3;
            overflow: hidden;
        }
        
        .trending-poster img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .trending-card:hover .trending-poster img {
            transform: scale(1.1);
        }
        
        .trending-rank {
            position: absolute;
            top: 12px;
            left: 12px;
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 800;
            color: white;
            box-shadow: 0 4px 15px rgba(229,9,20,0.5);
        }
        
        .trending-rating {
            position: absolute;
            top: 12px;
            right: 12px;
            display: flex;
            align-items: center;
            gap: 4px;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(10px);
            padding: 6px 10px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .trending-rating i { color: var(--gold); font-size: 12px; }
        
        .trending-info {
            padding: 16px;
            background: linear-gradient(180deg, transparent 0%, rgba(0,0,0,0.9) 100%);
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
        }
        
        .trending-info h3 {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .trending-info h3 a {
            color: white;
            text-decoration: none;
        }
        
        .trending-info .genre {
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 12px;
        }
        
        .trending-bookings {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            color: var(--text-secondary);
            margin-bottom: 10px;
        }
        
        .trending-bookings i { color: var(--gold); }
        
        .btn-book-trending {
            width: 100%;
            padding: 10px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-book-trending:hover {
            box-shadow: 0 6px 20px rgba(229,9,20,0.5);
            transform: translateY(-2px);
        }
        
        /* ========== NOW SHOWING ========== */
        .section-now-showing {
            background: var(--bg);
        }
        
        .now-showing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 20px;
        }
        
        .now-card {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            background: var(--bg-card);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .now-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        }
        
        .now-poster {
            position: relative;
            aspect-ratio: 2/3;
        }
        
        .now-poster img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .now-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(0, 200, 83, 0.9);
            color: white;
            font-size: 10px;
            font-weight: 700;
            padding: 4px 8px;
            border-radius: 4px;
            text-transform: uppercase;
        }
        
        .now-rating {
            position: absolute;
            bottom: 10px;
            right: 10px;
            display: flex;
            align-items: center;
            gap: 4px;
            background: rgba(0,0,0,0.8);
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .now-rating i { color: var(--gold); font-size: 11px; }
        
        .now-info {
            padding: 14px;
        }
        
        .now-info h4 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .now-info .genre {
            font-size: 11px;
            color: var(--text-muted);
            margin-bottom: 12px;
        }
        
        .btn-book-now {
            width: 100%;
            padding: 10px;
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        
        .btn-book-now:hover {
            background: var(--primary);
            border-color: var(--primary);
        }
        
        /* ========== UPCOMING ========== */
        .section-upcoming {
            background: linear-gradient(180deg, var(--bg) 0%, rgba(0,188,212,0.03) 100%);
        }
        
        .upcoming-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 20px;
        }
        
        .upcoming-card {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            background: var(--bg-card);
            transition: all 0.3s ease;
            cursor: pointer;
            opacity: 0.9;
        }
        
        .upcoming-card:hover {
            transform: translateY(-6px);
            opacity: 1;
        }
        
        .upcoming-poster {
            position: relative;
            aspect-ratio: 2/3;
        }
        
        .upcoming-poster img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: brightness(0.8);
        }
        
        .upcoming-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, transparent 50%, rgba(0,0,0,0.9) 100%);
            display: flex;
            align-items: flex-end;
            justify-content: center;
            padding-bottom: 16px;
        }
        
        .coming-tag {
            background: rgba(0, 188, 212, 0.9);
            color: white;
            font-size: 10px;
            font-weight: 700;
            padding: 6px 12px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .release-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(10px);
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 10px;
            color: #00bcd4;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .upcoming-info {
            padding: 14px;
        }
        
        .upcoming-info h4 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .upcoming-info .genre {
            font-size: 11px;
            color: var(--text-muted);
            margin-bottom: 12px;
        }
        
        .btn-notify {
            width: 100%;
            padding: 10px;
            background: transparent;
            border: 1px solid rgba(0, 188, 212, 0.5);
            border-radius: 8px;
            color: #00bcd4;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        
        .btn-notify:hover {
            background: rgba(0, 188, 212, 0.1);
            border-color: #00bcd4;
        }
        
        /* ========== EMPTY STATE ========== */
        .empty-state {
            text-align: center;
            padding: 100px 40px;
        }
        
        .empty-state i {
            font-size: 80px;
            color: var(--text-muted);
            opacity: 0.3;
            margin-bottom: 24px;
        }
        
        .empty-state h2 {
            font-size: 24px;
            margin-bottom: 12px;
        }
        
        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 24px;
        }
        
        .empty-state a {
            color: var(--primary);
            font-weight: 600;
        }
        
        /* ========== FOOTER ========== */
        .footer {
            background: var(--bg-card);
            padding: 60px 40px 30px;
            border-top: 1px solid var(--border);
        }
        
        .footer-content {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 60px;
            margin-bottom: 40px;
        }
        
        .footer h3, .footer h4 { margin-bottom: 16px; }
        .footer p, .footer li { color: var(--text-secondary); font-size: 14px; }
        .footer ul { list-style: none; }
        .footer li { margin-bottom: 10px; }
        .footer a { color: var(--text-secondary); text-decoration: none; transition: color 0.2s; }
        .footer a:hover { color: var(--primary); }
        
        .social-icons { display: flex; gap: 16px; }
        .social-icons a { font-size: 20px; }
        
        .footer-bottom {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            color: var(--text-muted);
            font-size: 13px;
        }
        
        /* ========== BOTTOM NAV (MOBILE) ========== */
        .bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 70px;
            background: rgba(20, 20, 20, 0.95);
            backdrop-filter: blur(20px);
            border-top: 1px solid var(--border);
            z-index: 1000;
        }
        
        /* ========== ANIMATIONS ========== */
        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            animation: fadeInUp 0.6s ease forwards;
        }
        
        @keyframes fadeInUp {
            to { opacity: 1; transform: translateY(0); }
        }
        
        .section:nth-child(2) { animation-delay: 0.1s; }
        .section:nth-child(3) { animation-delay: 0.2s; }
        .section:nth-child(4) { animation-delay: 0.3s; }
        
        /* ========== SIGN OUT BUTTON ========== */
        .btn-signout {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            color: #999;
            font-size: 13px;
            font-weight: 500;
            letter-spacing: 0.2px;
            cursor: pointer;
            transition: all 0.28s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }
        
        .btn-signout::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(229, 9, 20, 0) 0%, rgba(229, 9, 20, 0.15) 100%);
            opacity: 0;
            transition: opacity 0.28s ease;
        }
        
        .btn-signout:hover {
            background: rgba(229, 9, 20, 0.08);
            border-color: rgba(229, 9, 20, 0.4);
            color: #e50914;
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(229, 9, 20, 0.15);
        }
        
        .btn-signout:hover::before {
            opacity: 1;
        }
        
        .btn-signout:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(229, 9, 20, 0.1);
        }
        
        .btn-signout i {
            font-size: 15px;
            transition: transform 0.28s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            z-index: 1;
        }
        
        .btn-signout:hover i {
            transform: translateX(3px);
        }
        
        /* ========== RESPONSIVE ========== */
        @media (max-width: 1024px) {
            .hero h1 { font-size: 3rem; }
            .section { padding: 40px 24px; }
        }
        
        @media (max-width: 768px) {
            .navbar { padding: 0 20px; }
            .nav-menu, .search-box { display: none; }
            .hero { height: 70vh; min-height: 500px; }
            .hero h1 { font-size: 2rem; }
            .hero-content { padding-bottom: 60px; }
            .bottom-nav { display: flex; }
            .footer-content { grid-template-columns: 1fr; gap: 30px; }
            body { padding-bottom: 70px; }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <a href="../index.php">
                    <img src="../logo.png" alt="MOVIEBOOK" class="logo-img">
                </a>
            </div>
            <ul class="nav-menu">
                <li><a href="films.php">Films</a></li>
                <li><a href="home.php" class="active">Tickets</a></li>
                <li><a href="profile.php">Profile</a></li>
            </ul>
            <div class="nav-right">
                <div class="location" id="cityPicker" onclick="showCityPicker()">
                    <i class="bi bi-geo-alt-fill"></i>
                    <span id="selectedCityDisplay"><?php echo htmlspecialchars($selectedCity); ?></span>
                    <i class="bi bi-chevron-down"></i>
                </div>
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" placeholder="Search movies...">
                </div>
                <button class="btn-signout" onclick="location.href='../auth/logout.php'">
                    <i class="bi bi-box-arrow-right"></i> Sign Out
                </button>
            </div>
        </div>
    </nav>

    <!-- Bottom Navigation (Mobile) -->
    <nav class="bottom-nav">
        <a href="films.php" class="bottom-nav-item"><i class="bi bi-film"></i><span>Films</span></a>
        <a href="home.php" class="bottom-nav-item active"><i class="bi bi-ticket-perforated"></i><span>Tickets</span></a>
        <a href="profile.php" class="bottom-nav-item"><i class="bi bi-person-circle"></i><span>Profile</span></a>
    </nav>

    <!-- Hero Banner -->
    <?php if ($heroMovie): ?>
    <section class="hero">
        <div class="hero-backdrop">
            <img src="<?php echo htmlspecialchars($heroBackdrop); ?>" alt="<?php echo htmlspecialchars($heroMovie['title']); ?>">
            <div class="hero-overlay"></div>
        </div>
        <div class="hero-content">
            <div class="hero-reason"><?php echo $heroReason; ?></div>
            <h1><?php echo htmlspecialchars($heroMovie['title']); ?></h1>
            <div class="hero-meta">
                <span class="rating"><i class="bi bi-star-fill"></i> <?php echo number_format($heroMovie['rating'] ?? 0, 1); ?></span>
                <span class="separator">•</span>
                <span><?php echo htmlspecialchars($heroMovie['genre'] ?? 'Drama'); ?></span>
                <?php if (!empty($heroMovie['runtime'])): ?>
                <span class="separator">•</span>
                <span><?php echo floor($heroMovie['runtime']/60); ?>h <?php echo $heroMovie['runtime']%60; ?>m</span>
                <?php endif; ?>
            </div>
            <p class="hero-description"><?php echo htmlspecialchars(substr($heroMovie['description'] ?? '', 0, 180)); ?>...</p>
            <div class="hero-actions">
                <button class="btn-hero btn-hero-primary" onclick="location.href='booking.php?movie_id=<?php echo $heroMovie['id']; ?>'">
                    <i class="bi bi-ticket-perforated-fill"></i> Book Tickets
                </button>
                <button class="btn-hero btn-hero-secondary" onclick="location.href='movie-details.php?id=<?php echo $heroMovie['id']; ?>'">
                    <i class="bi bi-info-circle"></i> Details
                </button>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Trending This Week -->
    <?php if (!empty($trending)): ?>
    <section class="section section-trending fade-in">
        <div class="section-header">
            <h2 class="section-title">
                <i class="bi bi-fire"></i> Trending in <?php echo htmlspecialchars($selectedCity); ?>
                <span class="section-subtitle">Based on bookings</span>
            </h2>
            <a href="films.php" class="see-all">See All <i class="bi bi-chevron-right"></i></a>
        </div>
        <div class="trending-grid">
            <?php foreach ($trending as $index => $movie): 
                $bookings = $movie['booking_count'] ?? 0;
            ?>
            <div class="trending-card" onclick="location.href='movie-details.php?id=<?php echo $movie['id']; ?>'">
                <div class="trending-poster">
                    <img src="<?php echo htmlspecialchars($movie['poster_url']); ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>">
                    <div class="trending-rank"><?php echo $index + 1; ?></div>
                    <div class="trending-rating"><i class="bi bi-star-fill"></i> <?php echo number_format($movie['rating'] ?? 0, 1); ?></div>
                </div>
                <div class="trending-info">
                    <h3><a href="movie-details.php?id=<?php echo $movie['id']; ?>"><?php echo htmlspecialchars($movie['title']); ?></a></h3>
                    <p class="genre"><?php echo htmlspecialchars($movie['genre'] ?? 'Drama'); ?></p>
                    <?php if ($bookings > 0): ?>
                    <div class="trending-bookings"><i class="bi bi-ticket-fill"></i> <?php echo $bookings; ?> booked this week</div>
                    <?php endif; ?>
                    <button class="btn-book-trending" onclick="event.stopPropagation(); location.href='booking.php?movie_id=<?php echo $movie['id']; ?>'">
                        <i class="bi bi-ticket-perforated"></i> Book Now
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Now Showing -->
    <?php if (!empty($nowShowing)): ?>
    <section class="section section-now-showing fade-in">
        <div class="section-header">
            <h2 class="section-title">
                <i class="bi bi-play-circle-fill"></i> Now Showing
                <span class="section-subtitle"><?php echo $cityStats['shows_today'] ?? 0; ?> shows today in <?php echo htmlspecialchars($selectedCity); ?></span>
            </h2>
            <a href="films.php" class="see-all">See All <i class="bi bi-chevron-right"></i></a>
        </div>
        <div class="now-showing-grid">
            <?php foreach ($nowShowing as $movie): ?>
            <div class="now-card" onclick="location.href='movie-details.php?id=<?php echo $movie['id']; ?>'">
                <div class="now-poster">
                    <img src="<?php echo htmlspecialchars($movie['poster_url']); ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>">
                    <div class="now-badge">Playing</div>
                    <div class="now-rating"><i class="bi bi-star-fill"></i> <?php echo number_format($movie['rating'] ?? 0, 1); ?></div>
                </div>
                <div class="now-info">
                    <h4><?php echo htmlspecialchars($movie['title']); ?></h4>
                    <p class="genre"><?php echo htmlspecialchars($movie['genre'] ?? 'Drama'); ?></p>
                    <button class="btn-book-now" onclick="event.stopPropagation(); location.href='booking.php?movie_id=<?php echo $movie['id']; ?>'">
                        <i class="bi bi-ticket-perforated"></i> Book
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Upcoming Movies -->
    <?php if (!empty($upcoming)): ?>
    <section class="section section-upcoming fade-in">
        <div class="section-header">
            <h2 class="section-title">
                <i class="bi bi-calendar-event"></i> Upcoming
                <span class="section-subtitle">Coming soon to theaters</span>
            </h2>
            <a href="films.php" class="see-all">See All <i class="bi bi-chevron-right"></i></a>
        </div>
        <div class="upcoming-grid">
            <?php foreach ($upcoming as $movie): 
                // Use first_show_date from showtime query (NOT release_date metadata)
                $firstShowDate = !empty($movie['first_show_date']) ? date('M d', strtotime($movie['first_show_date'])) : 'TBA';
            ?>
            <div class="upcoming-card" onclick="location.href='movie-details.php?id=<?php echo $movie['id']; ?>'">
                <div class="upcoming-poster">
                    <img src="<?php echo htmlspecialchars($movie['poster_url']); ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>">
                    <div class="upcoming-overlay">
                        <span class="coming-tag">Coming Soon</span>
                    </div>
                    <div class="release-badge"><i class="bi bi-calendar3"></i> <?php echo $firstShowDate; ?></div>
                </div>
                <div class="upcoming-info">
                    <h4><?php echo htmlspecialchars($movie['title']); ?></h4>
                    <p class="genre"><?php echo htmlspecialchars($movie['genre'] ?? 'Drama'); ?></p>
                    <button class="btn-notify" onclick="event.stopPropagation(); notifyMe(<?php echo $movie['id']; ?>)">
                        <i class="bi bi-bell"></i> Notify Me
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
    
    <!-- Empty State -->
    <?php if ($hasNoCity): ?>
    <section class="section">
        <div class="empty-state">
            <i class="bi bi-geo-alt"></i>
            <h2>No Theaters Available</h2>
            <p>There are no active theaters in the system. Please contact support.</p>
        </div>
    </section>
    <?php elseif ($hasNoShows): ?>
    <section class="section">
        <div class="empty-state">
            <i class="bi bi-calendar-x"></i>
            <h2>No Shows in <?php echo htmlspecialchars($selectedCity); ?></h2>
            <p>There are no movies currently showing in <?php echo htmlspecialchars($selectedCity); ?>.</p>
            <p style="color: var(--text-muted); margin-top: 12px;">Try selecting a different city, or check back later.</p>
            <button class="btn-hero btn-hero-secondary" style="margin-top: 20px;" onclick="showCityPicker()">
                <i class="bi bi-geo-alt"></i> Change City
            </button>
        </div>
    </section>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>🎬 MOVIEBOOK</h3>
                <p>Your one-stop destination for movie tickets and reviews. Experience cinema like never before.</p>
            </div>
            <div class="footer-section">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="films.php">Browse Films</a></li>
                    <li><a href="profile.php">My Profile</a></li>
                    <li><a href="#">Terms &amp; Conditions</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h4>Follow Us</h4>
                <div class="social-icons">
                    <a href="#"><i class="bi bi-facebook"></i></a>
                    <a href="#"><i class="bi bi-twitter-x"></i></a>
                    <a href="#"><i class="bi bi-instagram"></i></a>
                    <a href="#"><i class="bi bi-youtube"></i></a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 MovieBook. All rights reserved.</p>
        </div>
    </footer>

    <script>
        function notifyMe(movieId) {
            alert('You will be notified when this movie is available for booking!');
        }
        
        // Fade in animation on scroll
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animationPlayState = 'running';
                }
            });
        }, { threshold: 0.1 });
        
        document.querySelectorAll('.fade-in').forEach(el => {
            el.style.animationPlayState = 'paused';
            observer.observe(el);
        });
        
        // City Picker Functionality
        function showCityPicker() {
            // Available cities (could be fetched from API)
            const cities = ['Kolhapur', 'Mumbai', 'Pune', 'Delhi', 'Bengaluru', 'Chennai'];
            const currentCity = document.getElementById('selectedCityDisplay').textContent;
            
            // Create modal
            const modal = document.createElement('div');
            modal.id = 'cityModal';
            modal.style.cssText = `
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,0.8);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10000;
            `;
            
            modal.innerHTML = `
                <div style="background: var(--bg-card); border-radius: 16px; padding: 24px; max-width: 400px; width: 90%;">
                    <h3 style="margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center;">
                        Select Your City
                        <button onclick="closeCityModal()" style="background: none; border: none; color: var(--text-muted); font-size: 24px; cursor: pointer;">&times;</button>
                    </h3>
                    <div style="display: grid; gap: 8px;">
                        ${cities.map(city => `
                            <button onclick="selectCity('${city}')" style="
                                padding: 14px 20px;
                                background: ${city === currentCity ? 'var(--primary)' : 'var(--bg-elevated)'};
                                border: 1px solid ${city === currentCity ? 'var(--primary)' : 'var(--border)'};
                                border-radius: 10px;
                                color: var(--text);
                                font-size: 15px;
                                cursor: pointer;
                                text-align: left;
                                transition: all 0.2s;
                            ">
                                <i class="bi bi-geo-alt${city === currentCity ? '-fill' : ''}" style="margin-right: 10px;"></i>
                                ${city}
                                ${city === currentCity ? '<i class="bi bi-check2" style="float: right;"></i>' : ''}
                            </button>
                        `).join('')}
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            modal.addEventListener('click', (e) => {
                if (e.target === modal) closeCityModal();
            });
        }
        
        function closeCityModal() {
            const modal = document.getElementById('cityModal');
            if (modal) modal.remove();
        }
        
        function selectCity(city) {
            // Save city via API
            fetch('../api/set_city.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'city=' + encodeURIComponent(city)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Reload page to show city-filtered results
                    window.location.reload();
                } else {
                    alert('Failed to set city');
                }
            })
            .catch(err => {
                console.error('City selection failed:', err);
                alert('Failed to set city');
            });
        }
    </script>
</body>
</html>
