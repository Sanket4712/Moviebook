<?php 
require_once '../includes/auth_check.php';
require_once '../includes/db.php';
require_once '../includes/movie_contract.php';
require_once '../includes/booking_eligibility.php';

// Get movie ID from URL
$movieId = intval($_GET['id'] ?? 0);
$movie = null;
$userStatus = [
    'inWatchlist' => false,
    'isFavorite' => false,
    'diaryEntry' => null
];
$userLists = [];

if ($movieId > 0 && $pdo) {
    try {
        // Fetch movie details
        $stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ?");
        $stmt->execute([$movieId]);
        $movie = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($movie) {
            $userId = $_SESSION['user_id'];
            
            // Check watchlist status
            $stmt = $pdo->prepare("SELECT id FROM watchlist WHERE user_id = ? AND movie_id = ?");
            $stmt->execute([$userId, $movieId]);
            $userStatus['inWatchlist'] = $stmt->fetch() ? true : false;
            
            // Check favorite status
            $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND movie_id = ?");
            $stmt->execute([$userId, $movieId]);
            $userStatus['isFavorite'] = $stmt->fetch() ? true : false;
            
            // Check diary entry
            $stmt = $pdo->prepare("SELECT id, rating, liked FROM diary WHERE user_id = ? AND movie_id = ? ORDER BY watched_date DESC LIMIT 1");
            $stmt->execute([$userId, $movieId]);
            $userStatus['diaryEntry'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get user's lists
            $stmt = $pdo->prepare("SELECT id, title FROM user_lists WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$userId]);
            $userLists = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Movie details error: " . $e->getMessage());
    }
}

// If no movie found, show error
if (!$movie) {
    header("Location: films.php");
    exit;
}

$year = $movie['release_date'] ? date('Y', strtotime($movie['release_date'])) : '';
$runtime = $movie['runtime'] ? $movie['runtime'] . ' min' : '';
$genres = $movie['genre'] ? explode(',', $movie['genre']) : [];

/**
 * Convert 0-10 rating to 5-star HTML with half-star support
 */
function renderStarRating($rating10) {
    if (!$rating10 || $rating10 <= 0) return '';
    
    // Convert to 5-star scale and round to nearest 0.5
    $rating5 = round(($rating10 / 2) * 2) / 2;
    
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($rating5 >= $i) {
            $html .= '<i class="bi bi-star-fill"></i>';
        } elseif ($rating5 >= $i - 0.5) {
            $html .= '<i class="bi bi-star-half"></i>';
        } else {
            $html .= '<i class="bi bi-star"></i>';
        }
    }
    return $html;
}

// BOOKING ELIGIBILITY: Based on actual showtimes, NOT year heuristics
// Returns mutually exclusive badge: 'bookable', 'coming_soon', or 'classic'
$bookingBadge = getBookingBadge($pdo, $movieId, $movie);
$isBookable = ($bookingBadge === 'bookable');
$isComingSoon = ($bookingBadge === 'coming_soon');
$isClassic = ($bookingBadge === 'classic');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($movie['title']); ?> - MovieBook</title>
    <link rel="stylesheet" href="../assets/css/home.css">
    <link rel="stylesheet" href="../assets/css/profile-redesign.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --mb-bg: #0d0d0d;
            --mb-bg-card: #141414;
            --mb-bg-elevated: #1a1a1a;
            --mb-primary: #e50914;
            --mb-primary-dark: #b8070f;
            --mb-text: #ffffff;
            --mb-text-secondary: #b3b3b3;
            --mb-text-muted: #666666;
            --mb-border: #2a2a2a;
            --mb-gold: #ffc107;
            --mb-heart: #ff4757;
            --mb-success: #00c853;
        }
        
        body {
            background: var(--mb-bg);
            color: var(--mb-text);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        
        .movie-detail-page {
            min-height: 100vh;
            padding-top: 80px;
        }
        
        /* Hero Banner with Backdrop */
        .movie-banner {
            position: relative;
            min-height: 500px;
            background: linear-gradient(to bottom, 
                rgba(13,13,13,0) 0%, 
                rgba(13,13,13,0.7) 60%,
                rgba(13,13,13,1) 100%),
                url('<?php echo htmlspecialchars($movie['backdrop_url'] ?? $movie['poster_url']); ?>');
            background-size: cover;
            background-position: center center;
            padding: 80px 0 60px;
        }
        .movie-banner::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(90deg, rgba(0,0,0,0.5) 0%, rgba(0,0,0,0.2) 50%, rgba(0,0,0,0.5) 100%);
        }
        
        /* Main Content Layout */
        .movie-detail-content {
            position: relative;
            display: flex;
            gap: 48px;
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 32px;
        }
        
        /* Poster Card */
        .movie-poster-large {
            flex-shrink: 0;
            width: 280px;
        }
        .movie-poster-large img {
            width: 100%;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.6), 0 0 0 1px rgba(255,255,255,0.05);
            transition: transform 0.3s ease;
        }
        .movie-poster-large img:hover {
            transform: scale(1.02);
        }
        
        /* Movie Info Section */
        .movie-detail-content .movie-info,
        .movie-info {
            flex: 1;
            padding-top: 40px;
            /* Override conflicting styles from home.css movie cards */
            position: relative !important;
            opacity: 1 !important;
            background: transparent !important;
            bottom: auto !important;
            left: auto !important;
            right: auto !important;
            z-index: 1;
        }
        .movie-info h1 {
            font-size: 2.8rem;
            font-weight: 700;
            margin: 0 0 8px;
            color: #fff;
            letter-spacing: -0.5px;
            line-height: 1.1;
        }
        .movie-year-badge {
            display: inline-block;
            font-size: 1.2rem;
            color: var(--mb-text-muted);
            margin-left: 12px;
            font-weight: 400;
        }
        
        /* Meta Info Row */
        .movie-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 16px;
            margin: 16px 0 20px;
            color: var(--mb-text-secondary);
            font-size: 15px;
        }
        .movie-meta .separator {
            color: var(--mb-text-muted);
            opacity: 0.5;
        }
        .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .meta-item i {
            color: var(--mb-primary);
            font-size: 14px;
        }
        
        /* Genre Pills */
        .genre-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 20px;
        }
        .genre-tag {
            background: linear-gradient(135deg, rgba(229,9,20,0.15) 0%, rgba(229,9,20,0.05) 100%);
            border: 1px solid rgba(229,9,20,0.3);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            color: var(--mb-text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.2s ease;
        }
        .genre-tag:hover {
            background: rgba(229,9,20,0.25);
            border-color: var(--mb-primary);
            color: var(--mb-text);
        }
        
        /* Rating Display - 5-Star Format */
        .movie-rating {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(255,193,7,0.1);
            padding: 10px 18px;
            border-radius: 12px;
            margin-bottom: 24px;
            border: 1px solid rgba(255,193,7,0.2);
        }
        .movie-rating i {
            color: var(--mb-gold);
            font-size: 22px;
        }
        .movie-rating i.bi-star {
            color: rgba(255,193,7,0.25);
        }
        
        /* Director */
        .movie-director {
            color: var(--mb-text-muted);
            font-size: 15px;
            margin-bottom: 20px;
        }
        .movie-director strong {
            color: var(--mb-text);
            font-weight: 500;
        }
        
        /* Synopsis */
        .movie-description {
            color: var(--mb-text-secondary);
            line-height: 1.8;
            font-size: 15px;
            margin-bottom: 32px;
            max-width: 600px;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 14px 24px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.25s ease;
            border: none;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .btn-action i {
            font-size: 18px;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--mb-primary) 0%, var(--mb-primary-dark) 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(229, 9, 20, 0.3);
        }
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(229, 9, 20, 0.5);
        }
        .btn-secondary {
            background: var(--mb-bg-elevated);
            color: var(--mb-text);
            border: 1px solid var(--mb-border);
        }
        .btn-secondary:hover {
            background: var(--mb-bg-card);
            border-color: var(--mb-text-muted);
            transform: translateY(-2px);
        }
        .btn-secondary.active {
            background: linear-gradient(135deg, var(--mb-primary) 0%, var(--mb-primary-dark) 100%);
            border-color: var(--mb-primary);
            color: white;
        }
        .btn-like {
            width: 52px;
            padding: 14px;
        }
        .btn-like.active {
            background: rgba(255,71,87,0.15);
            border-color: var(--mb-heart);
        }
        .btn-like.active i {
            color: var(--mb-heart);
        }
        
        /* Classic Film Badge */
        .classic-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: linear-gradient(135deg, rgba(255,193,7,0.15) 0%, rgba(255,193,7,0.05) 100%);
            border: 1px solid rgba(255,193,7,0.3);
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            color: var(--mb-gold);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .classic-badge i {
            font-size: 16px;
        }
        
        /* Coming Soon Badge */
        .coming-soon-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: linear-gradient(135deg, rgba(0,188,212,0.15) 0%, rgba(0,188,212,0.05) 100%);
            border: 1px solid rgba(0,188,212,0.3);
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            color: #00bcd4;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .coming-soon-badge i {
            font-size: 16px;
        }
        
        /* User Watch Status */
        .user-status {
            margin-top: 28px;
            padding-top: 24px;
            border-top: 1px solid var(--mb-border);
        }
        .user-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            background: linear-gradient(135deg, rgba(0,200,83,0.1) 0%, rgba(0,200,83,0.05) 100%);
            border: 1px solid rgba(0,200,83,0.2);
            padding: 12px 20px;
            border-radius: 12px;
            font-size: 14px;
            color: var(--mb-text-secondary);
        }
        .user-status-badge > i:first-child {
            color: var(--mb-success);
            font-size: 18px;
        }
        .user-rating-display {
            display: flex;
            gap: 3px;
            margin-left: 8px;
        }
        .user-rating-display i {
            color: var(--mb-gold);
            font-size: 14px;
        }
        .user-rating-display i.empty {
            color: #333;
        }
        
        /* Dropdown Styles */
        .list-dropdown {
            position: relative;
        }
        .list-dropdown-content {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            background: var(--mb-bg-card);
            border: 1px solid var(--mb-border);
            border-radius: 12px;
            min-width: 220px;
            max-height: 280px;
            overflow-y: auto;
            display: none;
            z-index: 100;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
        }
        .list-dropdown.open .list-dropdown-content {
            display: block;
            animation: dropdownSlide 0.2s ease;
        }
        @keyframes dropdownSlide {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .list-dropdown-item {
            padding: 14px 18px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--mb-text);
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            font-size: 14px;
        }
        .list-dropdown-item:hover {
            background: var(--mb-bg-elevated);
            color: var(--mb-primary);
        }
        .list-dropdown-item i {
            color: var(--mb-text-muted);
            font-size: 16px;
        }
        
        /* ===== DIARY/RATING MODAL ===== */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.85);
            backdrop-filter: blur(8px);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            visibility: hidden;
            opacity: 0;
            transition: visibility 0s linear 0.3s, opacity 0.3s ease;
        }
        .modal-overlay.show {
            visibility: visible;
            opacity: 1;
            transition: visibility 0s linear 0s, opacity 0.3s ease;
        }
        .diary-modal {
            background: linear-gradient(180deg, #1a1a1a 0%, #141414 100%);
            border-radius: 16px;
            width: 100%;
            max-width: 440px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 80px rgba(0,0,0,0.6), 0 0 0 1px rgba(255,255,255,0.05);
            animation: modalSlide 0.3s ease;
        }
        @keyframes modalSlide {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 24px;
            border-bottom: 1px solid var(--mb-border);
        }
        .modal-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: var(--mb-text);
        }
        .modal-close {
            background: none;
            border: none;
            color: var(--mb-text-muted);
            font-size: 24px;
            cursor: pointer;
            padding: 4px;
            line-height: 1;
            transition: color 0.2s;
        }
        .modal-close:hover {
            color: var(--mb-text);
        }
        .modal-body {
            padding: 24px;
        }
        .modal-movie-info {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--mb-border);
        }
        .modal-movie-poster {
            width: 60px;
            height: 90px;
            border-radius: 6px;
            object-fit: cover;
        }
        .modal-movie-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--mb-text);
            margin: 0 0 4px;
        }
        .modal-movie-year {
            font-size: 13px;
            color: var(--mb-text-muted);
        }
        
        /* Form Fields */
        .form-group {
            margin-bottom: 24px;
        }
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--mb-text-muted);
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-input {
            width: 100%;
            padding: 14px 16px;
            background: var(--mb-bg);
            border: 1px solid var(--mb-border);
            border-radius: 10px;
            color: var(--mb-text);
            font-size: 15px;
            transition: border-color 0.2s;
        }
        .form-input:focus {
            outline: none;
            border-color: var(--mb-primary);
        }
        .form-textarea {
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
        }
        
        /* Star Rating Input */
        .star-rating-input {
            display: flex;
            gap: 8px;
        }
        .star-rating-input .star {
            font-size: 32px;
            color: #333;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .star-rating-input .star:hover,
        .star-rating-input .star.active {
            color: var(--mb-gold);
            transform: scale(1.1);
        }
        .star-rating-input .star.half {
            background: linear-gradient(90deg, var(--mb-gold) 50%, #333 50%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        /* Like Toggle in Modal */
        .like-toggle {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .like-toggle-btn {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--mb-bg);
            border: 1px solid var(--mb-border);
            color: var(--mb-text-muted);
            font-size: 22px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .like-toggle-btn:hover {
            border-color: var(--mb-heart);
            color: var(--mb-heart);
        }
        .like-toggle-btn.active {
            background: rgba(255,71,87,0.15);
            border-color: var(--mb-heart);
            color: var(--mb-heart);
        }
        .like-toggle span {
            font-size: 14px;
            color: var(--mb-text-secondary);
        }
        
        /* Modal Footer */
        .modal-footer {
            padding: 20px 24px;
            border-top: 1px solid var(--mb-border);
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        .btn-modal {
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }
        .btn-modal-cancel {
            background: var(--mb-bg-elevated);
            color: var(--mb-text-secondary);
        }
        .btn-modal-cancel:hover {
            background: var(--mb-bg);
            color: var(--mb-text);
        }
        .btn-modal-save {
            background: linear-gradient(135deg, var(--mb-primary) 0%, var(--mb-primary-dark) 100%);
            color: white;
        }
        .btn-modal-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(229, 9, 20, 0.4);
        }
        .btn-modal-save:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        /* Notification Toast */
        .notification {
            position: fixed;
            top: 90px;
            right: 20px;
            padding: 14px 24px;
            border-radius: 10px;
            color: white;
            font-size: 14px;
            font-weight: 500;
            z-index: 1100;
            transform: translateX(120%);
            transition: transform 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }
        .notification.show { transform: translateX(0); }
        .notification.success { background: linear-gradient(135deg, #00c853 0%, #00a844 100%); }
        .notification.error { background: linear-gradient(135deg, #e50914 0%, #b8070f 100%); }
        
        /* Responsive */
        @media (max-width: 768px) {
            .movie-banner {
                min-height: auto;
                padding: 40px 0;
            }
            .movie-detail-content {
                flex-direction: column;
                align-items: center;
                text-align: center;
                gap: 24px;
                padding: 0 20px;
            }
            .movie-poster-large {
                width: 200px;
            }
            .movie-info {
                padding-top: 0;
            }
            .movie-info h1 {
                font-size: 1.8rem;
            }
            .movie-meta {
                justify-content: center;
            }
            .genre-container {
                justify-content: center;
            }
            .action-buttons {
                justify-content: center;
            }
            .movie-description {
                max-width: none;
            }
            .diary-modal {
                margin: 16px;
                max-width: calc(100% - 32px);
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <a href="../index.php"><img src="../logo.png" alt="MOVIEBOOK" class="logo-img"></a>
            </div>
            <ul class="nav-menu">
                <li><a href="films.php">Films</a></li>
                <li><a href="home.php">Tickets</a></li>
                <li><a href="profile.php">Profile</a></li>
            </ul>
            <div class="nav-right">
                <button class="btn-signout" onclick="location.href='../auth/logout.php'">
                    <i class="bi bi-box-arrow-right"></i> Sign Out
                </button>
            </div>
        </div>
    </nav>

    <nav class="bottom-nav">
        <a href="films.php" class="bottom-nav-item"><i class="bi bi-film"></i><span>Films</span></a>
        <a href="home.php" class="bottom-nav-item"><i class="bi bi-ticket-perforated"></i><span>Tickets</span></a>
        <a href="profile.php" class="bottom-nav-item"><i class="bi bi-person-circle"></i><span>Profile</span></a>
    </nav>

    <div class="movie-detail-page">
        <div class="movie-banner">
            <div class="movie-detail-content">
                <div class="movie-poster-large">
                    <img src="<?php echo htmlspecialchars($movie['poster_url'] ?? ''); ?>" 
                         alt="<?php echo htmlspecialchars($movie['title']); ?>">
                </div>
                <div class="movie-info">
                    <h1><?php echo htmlspecialchars($movie['title']); ?></h1>
                    
                    <div class="movie-meta">
                        <?php if ($year): ?>
                            <span><?php echo $year; ?></span>
                            <span class="separator">•</span>
                        <?php endif; ?>
                        <?php if ($runtime): ?>
                            <span><?php echo $runtime; ?></span>
                            <span class="separator">•</span>
                        <?php endif; ?>
                        <?php foreach ($genres as $genre): ?>
                            <span class="genre-tag"><?php echo htmlspecialchars(trim($genre)); ?></span>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php $starsHtml = renderStarRating($movie['rating']); ?>
                    <?php if ($starsHtml): ?>
                    <div class="movie-rating">
                        <?php echo $starsHtml; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($movie['director']): ?>
                    <div class="movie-director">
                        Directed by <strong><?php echo htmlspecialchars($movie['director']); ?></strong>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($movie['description']): ?>
                    <p class="movie-description">
                        <?php echo htmlspecialchars($movie['description']); ?>
                    </p>
                    <?php endif; ?>
                    
                    <div class="action-buttons">
                        <?php if ($isBookable): ?>
                        <button class="btn-action btn-primary" onclick="location.href='booking.php?movie_id=<?php echo $movieId; ?>'">
                            <i class="bi bi-ticket-perforated"></i> Book Tickets
                        </button>
                        <?php elseif ($isComingSoon): ?>
                        <span class="coming-soon-badge">
                            <i class="bi bi-calendar-event"></i> Coming to Theaters
                        </span>
                        <?php else: ?>
                        <span class="classic-badge">
                            <i class="bi bi-film"></i> Classic Film
                        </span>
                        <?php endif; ?>
                        
                        <button class="btn-action btn-secondary <?php echo $userStatus['inWatchlist'] ? 'active' : ''; ?>" 
                                id="watchlistBtn" onclick="toggleWatchlistMovie()">
                            <i class="bi <?php echo $userStatus['inWatchlist'] ? 'bi-bookmark-fill' : 'bi-bookmark'; ?>"></i>
                            <span><?php echo $userStatus['inWatchlist'] ? 'In Watchlist' : 'Watchlist'; ?></span>
                        </button>
                        
                        <button class="btn-action btn-secondary btn-like <?php echo $userStatus['isFavorite'] ? 'active' : ''; ?>" 
                                id="favoriteBtn" onclick="toggleFavoriteMovie()">
                            <i class="bi <?php echo $userStatus['isFavorite'] ? 'bi-heart-fill' : 'bi-heart'; ?>"></i>
                        </button>
                        
                        <button class="btn-action btn-secondary" id="logBtn" onclick="openDiaryModal(<?php echo $movieId; ?>, '<?php echo addslashes($movie['title']); ?>')">
                            <i class="bi <?php echo $userStatus['diaryEntry'] ? 'bi-eye-fill' : 'bi-eye'; ?>"></i>
                            <span><?php echo $userStatus['diaryEntry'] ? 'Logged' : 'Log'; ?></span>
                        </button>
                        
                        <?php if (!empty($userLists)): ?>
                        <div class="list-dropdown" id="listDropdown">
                            <button class="btn-action btn-secondary" onclick="toggleListDropdown()">
                                <i class="bi bi-collection"></i> Add to List
                            </button>
                            <div class="list-dropdown-content">
                                <?php foreach ($userLists as $list): ?>
                                <button class="list-dropdown-item" onclick="addToList(<?php echo $list['id']; ?>, '<?php echo addslashes($list['title']); ?>')">
                                    <i class="bi bi-folder"></i> <?php echo htmlspecialchars($list['title']); ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($userStatus['diaryEntry']): ?>
                    <div class="user-status">
                        <div class="user-status-badge">
                            <i class="bi bi-check-circle-fill"></i>
                            Watched
                            <?php if ($userStatus['diaryEntry']['rating']): ?>
                            <div class="user-rating-display">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="bi bi-star-fill" style="color: <?php echo $i <= $userStatus['diaryEntry']['rating'] ? '#ffc107' : '#333'; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($userStatus['diaryEntry']['liked']): ?>
                            <i class="bi bi-heart-fill" style="color: #ff4757;"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Diary/Rating Modal -->
    <div class="modal-overlay" id="diaryModal">
        <div class="diary-modal">
            <div class="modal-header">
                <h3>Log This Film</h3>
                <button class="modal-close" onclick="closeDiaryModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="modal-movie-info">
                    <img src="<?php echo htmlspecialchars($movie['poster_url'] ?? ''); ?>" 
                         alt="<?php echo htmlspecialchars($movie['title']); ?>" class="modal-movie-poster">
                    <div>
                        <h4 class="modal-movie-title"><?php echo htmlspecialchars($movie['title']); ?></h4>
                        <span class="modal-movie-year"><?php echo $year; ?></span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Date Watched</label>
                    <input type="date" id="watchedDate" class="form-input" value="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Your Rating</label>
                    <div class="star-rating-input" id="starRating">
                        <i class="bi bi-star-fill star" data-rating="1"></i>
                        <i class="bi bi-star-fill star" data-rating="2"></i>
                        <i class="bi bi-star-fill star" data-rating="3"></i>
                        <i class="bi bi-star-fill star" data-rating="4"></i>
                        <i class="bi bi-star-fill star" data-rating="5"></i>
                    </div>
                    <input type="hidden" id="ratingValue" value="0">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Like This Entry?</label>
                    <div class="like-toggle">
                        <button class="like-toggle-btn" id="likeToggleBtn" type="button">
                            <i class="bi bi-heart"></i>
                        </button>
                        <!-- SEMANTIC RULE: Diary 'liked' is entry-specific ONLY.
                             This does NOT modify the favorites table (max 4 profile favorites).
                             Keep these concepts separated per user-approved design. -->
                        <span id="likeText">Mark as liked</span>
                    </div>
                    <input type="hidden" id="likedValue" value="0">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Review (Optional)</label>
                    <textarea id="reviewText" class="form-input form-textarea" placeholder="Add your thoughts about this film..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-modal btn-modal-cancel" onclick="closeDiaryModal()">Cancel</button>
                <button class="btn-modal btn-modal-save" id="saveDiaryBtn" onclick="saveDiaryEntry()">Save Entry</button>
            </div>
        </div>
    </div>

    <script>
        const API_BASE = '../api';
        const MOVIE_ID = <?php echo $movieId; ?>;
        const MOVIE_TITLE = '<?php echo addslashes($movie['title']); ?>';
        const MOVIE_POSTER = '<?php echo addslashes($movie['poster_url'] ?? ''); ?>';
        const MOVIE_YEAR = '<?php echo $year; ?>';
        
        // ===== DIARY MODAL FUNCTIONS =====
        
        function openDiaryModal() {
            const modal = document.getElementById('diaryModal');
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
            
            // Reset form
            document.getElementById('watchedDate').value = new Date().toISOString().split('T')[0];
            document.getElementById('ratingValue').value = '0';
            document.getElementById('likedValue').value = '0';
            document.getElementById('reviewText').value = '';
            
            // Reset stars
            document.querySelectorAll('#starRating .star').forEach(s => s.classList.remove('active'));
            
            // Reset like button
            const likeBtn = document.getElementById('likeToggleBtn');
            likeBtn.classList.remove('active');
            likeBtn.querySelector('i').className = 'bi bi-heart';
            document.getElementById('likeText').textContent = 'Click to add to favorites';
        }
        
        function closeDiaryModal() {
            const modal = document.getElementById('diaryModal');
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
        
        // Star Rating Interaction
        document.getElementById('starRating').addEventListener('click', (e) => {
            const star = e.target.closest('.star');
            if (!star) return;
            
            const rating = parseInt(star.dataset.rating);
            document.getElementById('ratingValue').value = rating;
            
            // Update star visuals
            document.querySelectorAll('#starRating .star').forEach((s, index) => {
                if (index < rating) {
                    s.classList.add('active');
                } else {
                    s.classList.remove('active');
                }
            });
        });
        
        // Star hover effect
        document.getElementById('starRating').addEventListener('mouseover', (e) => {
            const star = e.target.closest('.star');
            if (!star) return;
            
            const rating = parseInt(star.dataset.rating);
            document.querySelectorAll('#starRating .star').forEach((s, index) => {
                s.style.color = index < rating ? '#ffc107' : '#333';
            });
        });
        
        document.getElementById('starRating').addEventListener('mouseout', () => {
            const currentRating = parseInt(document.getElementById('ratingValue').value);
            document.querySelectorAll('#starRating .star').forEach((s, index) => {
                s.style.color = '';
                if (index < currentRating) {
                    s.classList.add('active');
                }
            });
        });
        
        // Like Toggle
        /**
         * SEMANTIC RULE: Diary 'liked' is entry-specific ONLY.
         * This toggle sets diary.liked column for THIS viewing entry.
         * It does NOT touch the favorites table (profile favorites, max 4).
         * This separation is intentional and enforced by design.
         */
        document.getElementById('likeToggleBtn').addEventListener('click', function() {
            const isLiked = this.classList.toggle('active');
            document.getElementById('likedValue').value = isLiked ? '1' : '0';
            
            const icon = this.querySelector('i');
            icon.className = isLiked ? 'bi bi-heart-fill' : 'bi bi-heart';
            document.getElementById('likeText').textContent = isLiked ? 'Liked!' : 'Mark as liked';
        });
        
        // Save Diary Entry
        async function saveDiaryEntry() {
            const btn = document.getElementById('saveDiaryBtn');
            btn.disabled = true;
            btn.textContent = 'Saving...';
            
            const data = {
                action: 'add',
                movie_id: MOVIE_ID,
                watched_date: document.getElementById('watchedDate').value,
                rating: document.getElementById('ratingValue').value,
                liked: document.getElementById('likedValue').value,
                review: document.getElementById('reviewText').value
            };
            
            try {
                const response = await fetch(`${API_BASE}/diary.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams(data).toString()
                });
                const result = await response.json();
                
                if (result.success) {
                    showNotification('Entry added to diary!', 'success');
                    closeDiaryModal();
                    
                    // Update the Log button to show "Logged"
                    const logBtn = document.getElementById('logBtn');
                    if (logBtn) {
                        logBtn.querySelector('i').className = 'bi bi-eye-fill';
                        logBtn.querySelector('span').textContent = 'Logged';
                    }
                    
                    // Sync watchlist button if diary API removed from watchlist
                    // (watching a film = no longer on "want to watch" list)
                    if (result.removedFromWatchlist) {
                        const watchlistBtn = document.getElementById('watchlistBtn');
                        if (watchlistBtn) {
                            watchlistBtn.classList.remove('active');
                            const icon = watchlistBtn.querySelector('i');
                            const text = watchlistBtn.querySelector('span');
                            if (icon) icon.className = 'bi bi-bookmark';
                            if (text) text.textContent = 'Watchlist';
                        }
                    }
                    
                    // No page reload needed - UI is now synced
                } else {
                    showNotification(result.error || 'Failed to save', 'error');
                }
            } catch (error) {
                console.error('Diary save error:', error);
                showNotification('Network error', 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Save Entry';
            }
        }
        
        // Close modal on overlay click
        document.getElementById('diaryModal').addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-overlay')) {
                closeDiaryModal();
            }
        });
        
        // Close modal on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeDiaryModal();
            }
        });
        
        // ===== WATCHLIST/FAVORITES FUNCTIONS =====
        
        async function toggleWatchlistMovie() {
            const btn = document.getElementById('watchlistBtn');
            btn.disabled = true;
            
            try {
                const response = await fetch(`${API_BASE}/watchlist.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=toggle&movie_id=${MOVIE_ID}`
                });
                const data = await response.json();
                
                if (data.success) {
                    const icon = btn.querySelector('i');
                    const text = btn.querySelector('span');
                    
                    if (data.inWatchlist) {
                        btn.classList.add('active');
                        icon.classList.replace('bi-bookmark', 'bi-bookmark-fill');
                        text.textContent = 'In Watchlist';
                    } else {
                        btn.classList.remove('active');
                        icon.classList.replace('bi-bookmark-fill', 'bi-bookmark');
                        text.textContent = 'Watchlist';
                    }
                    showNotification(data.message, 'success');
                } else {
                    showNotification(data.error, 'error');
                }
            } catch (error) {
                showNotification('Network error', 'error');
            } finally {
                btn.disabled = false;
            }
        }
        
        async function toggleFavoriteMovie() {
            const btn = document.getElementById('favoriteBtn');
            btn.disabled = true;
            
            try {
                const response = await fetch(`${API_BASE}/favorites.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=toggle&movie_id=${MOVIE_ID}`
                });
                const data = await response.json();
                
                if (data.success) {
                    const icon = btn.querySelector('i');
                    
                    if (data.liked) {
                        btn.classList.add('active');
                        icon.classList.replace('bi-heart', 'bi-heart-fill');
                    } else {
                        btn.classList.remove('active');
                        icon.classList.replace('bi-heart-fill', 'bi-heart');
                    }
                    showNotification(data.message, 'success');
                } else {
                    showNotification(data.error, 'error');
                }
            } catch (error) {
                showNotification('Network error', 'error');
            } finally {
                btn.disabled = false;
            }
        }
        
        // List dropdown
        function toggleListDropdown() {
            document.getElementById('listDropdown')?.classList.toggle('open');
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            const dropdown = document.getElementById('listDropdown');
            if (dropdown && !dropdown.contains(e.target)) {
                dropdown.classList.remove('open');
            }
        });
        
        // Add to list
        async function addToList(listId, listTitle) {
            try {
                const response = await fetch(`${API_BASE}/lists.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=add_film&list_id=${listId}&movie_id=${MOVIE_ID}`
                });
                const data = await response.json();
                
                if (data.success) {
                    showNotification(`Added to "${listTitle}"`, 'success');
                } else {
                    showNotification(data.error || 'Failed to add', 'error');
                }
            } catch (error) {
                showNotification('Network error', 'error');
            }
            
            document.getElementById('listDropdown')?.classList.remove('open');
        }
        
        // ===== NOTIFICATION =====
        
        function showNotification(message, type = 'info') {
            // Remove existing notifications
            document.querySelectorAll('.notification').forEach(n => n.remove());
            
            const notif = document.createElement('div');
            notif.className = `notification ${type}`;
            notif.textContent = message;
            document.body.appendChild(notif);
            
            // Trigger animation
            requestAnimationFrame(() => {
                notif.classList.add('show');
            });
            
            // Auto-remove after 3 seconds
            setTimeout(() => {
                notif.classList.remove('show');
                setTimeout(() => notif.remove(), 300);
            }, 3000);
        }
    </script>
</body>
</html>
