<?php 
require_once '../includes/auth_check.php';
require_once '../includes/db.php';
require_once '../includes/movie_contract.php';

$userId = $_SESSION['user_id'];

/**
 * FILMS PAGE - SINGLE SOURCE OF TRUTH
 * 
 * DUPLICATION FIX EXPLANATION:
 * Previously, this page ran 4 separate queries (popular, top-rated, recent, all)
 * that returned overlapping movie sets, causing the same movies to appear 2-4 times
 * across different sections.
 * 
 * NEW APPROACH:
 * - Single query fetches ALL valid movies exactly once
 * - Single grid renders each movie with unique ID
 * - Client-side sorting/filtering operates on the same dataset
 * - Zero duplication guaranteed by design
 */

// Single source of truth: fetch ALL movies once
$movies = [];
$genres = [];

// Get user's watchlist and favorites for button states
$userWatchlist = [];
$userFavorites = [];

if ($pdo) {
    try {
        // ALL movies - single query, no LIMIT, filtered through contract
        $stmt = $pdo->query("SELECT * FROM movies ORDER BY rating DESC");
        $movies = filterValidMovies($stmt->fetchAll(PDO::FETCH_ASSOC));
        
        // Extract unique genres for filter
        $genreSet = [];
        foreach ($movies as $movie) {
            if (!empty($movie['genre'])) {
                foreach (explode(',', $movie['genre']) as $g) {
                    $g = trim($g);
                    if ($g) $genreSet[$g] = true;
                }
            }
        }
        $genres = array_keys($genreSet);
        sort($genres);
        
        // User's watchlist (for button states)
        $stmt = $pdo->prepare("SELECT movie_id FROM watchlist WHERE user_id = ?");
        $stmt->execute([$userId]);
        $userWatchlist = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // User's favorites (for button states)
        $stmt = $pdo->prepare("SELECT movie_id FROM favorites WHERE user_id = ?");
        $stmt->execute([$userId]);
        $userFavorites = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
    } catch (PDOException $e) {
        error_log("Films page error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Films - MovieBook</title>
    <link rel="stylesheet" href="../assets/css/home.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --bg-dark: #0d0d0d;
            --bg-card: #141414;
            --bg-elevated: #1a1a1a;
            --accent: #e50914;
            --text: #ffffff;
            --text-muted: #888;
            --rating: #f5c518;
        }
        
        body {
            background: var(--bg-dark);
            color: var(--text);
        }
        
        .films-page {
            max-width: 1400px;
            margin: 0 auto;
            padding: 100px 20px 40px;
        }
        
        /* Controls Bar */
        .controls-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: center;
            margin-bottom: 32px;
            padding: 20px;
            background: var(--bg-card);
            border-radius: 12px;
        }
        
        .search-wrapper {
            flex: 1;
            min-width: 200px;
            max-width: 400px;
            position: relative;
        }
        
        .search-wrapper i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }
        
        .search-input {
            width: 100%;
            padding: 12px 16px 12px 42px;
            background: var(--bg-elevated);
            border: 1px solid #333;
            border-radius: 8px;
            color: white;
            font-size: 14px;
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--accent);
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-label {
            font-size: 12px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .filter-select {
            padding: 10px 14px;
            background: var(--bg-elevated);
            border: 1px solid #333;
            border-radius: 8px;
            color: white;
            font-size: 14px;
            cursor: pointer;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: var(--accent);
        }
        
        /* Genre Pills */
        .genre-filters {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }
        
        .genre-pill {
            padding: 8px 16px;
            background: var(--bg-card);
            border: 1px solid #333;
            border-radius: 20px;
            color: var(--text-muted);
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .genre-pill:hover {
            border-color: var(--accent);
            color: var(--text);
        }
        
        .genre-pill.active {
            background: var(--accent);
            border-color: var(--accent);
            color: white;
        }
        
        /* Results Info */
        .results-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            color: var(--text-muted);
            font-size: 14px;
        }
        
        /* Movie Grid - Single unified grid */
        .movie-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 20px;
        }
        
        .movie-card {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            background: var(--bg-card);
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
        }
        
        .movie-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.5);
        }
        
        .movie-poster {
            aspect-ratio: 2/3;
            position: relative;
            overflow: hidden;
        }
        
        .movie-poster img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .movie-rating {
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(0,0,0,0.8);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .movie-rating i { color: var(--rating); }
        
        .movie-info {
            padding: 12px;
        }
        
        .movie-title {
            font-size: 14px;
            font-weight: 500;
            margin: 0 0 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .movie-year {
            font-size: 12px;
            color: var(--text-muted);
        }
        
        .movie-genre {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Action Buttons */
        .movie-actions {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 10px;
            background: linear-gradient(transparent, rgba(0,0,0,0.9));
            display: flex;
            gap: 8px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .movie-card:hover .movie-actions {
            opacity: 1;
        }
        
        .action-btn {
            flex: 1;
            padding: 8px;
            background: rgba(255,255,255,0.1);
            border: none;
            border-radius: 4px;
            color: white;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.2s, transform 0.2s;
        }
        
        .action-btn:hover {
            background: var(--accent);
            transform: scale(1.1);
        }
        
        .action-btn.active {
            color: var(--accent);
        }
        
        .action-btn.active.heart { color: #ff4757; }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.3;
        }
        
        .empty-state a {
            color: var(--accent);
            margin-top: 12px;
            display: inline-block;
        }
        
        /* Notification */
        .notification {
            position: fixed;
            top: 90px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 8px;
            color: white;
            font-size: 14px;
            z-index: 1000;
            transform: translateX(120%);
            transition: transform 0.3s;
        }
        
        .notification.show { transform: translateX(0); }
        .notification.success { background: #00c853; }
        .notification.error { background: #e50914; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .controls-bar {
                flex-direction: column;
                align-items: stretch;
            }
            .search-wrapper {
                max-width: none;
            }
            .filter-group {
                justify-content: space-between;
            }
        }
        
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
    </style>
</head>
<body>

<!-- Navigation -->
<nav class="navbar">
    <div class="nav-container">
        <div class="logo"><a href="../index.php"><img src="../logo.png" alt="MOVIEBOOK" class="logo-img"></a></div>
        <ul class="nav-menu">
            <li><a href="films.php" class="active">Films</a></li>
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
    <a href="films.php" class="bottom-nav-item active"><i class="bi bi-film"></i><span>Films</span></a>
    <a href="home.php" class="bottom-nav-item"><i class="bi bi-ticket-perforated"></i><span>Tickets</span></a>
    <a href="profile.php" class="bottom-nav-item"><i class="bi bi-person-circle"></i><span>Profile</span></a>
</nav>

<main class="films-page">
    <?php if (empty($movies)): ?>
    <div class="empty-state">
        <i class="bi bi-film"></i>
        <h2>No Movies Yet</h2>
        <p>Movies will appear here once an admin adds them.</p>
        <p>Check back later!</p>
    </div>
    <?php else: ?>
    
    <!-- Controls Bar: Search + Sort -->
    <div class="controls-bar">
        <div class="search-wrapper">
            <i class="bi bi-search"></i>
            <input type="text" class="search-input" id="movieSearch" placeholder="Search films...">
        </div>
        
        <div class="filter-group">
            <span class="filter-label">Sort by</span>
            <select class="filter-select" id="sortSelect">
                <option value="rating">Rating (High to Low)</option>
                <option value="rating-asc">Rating (Low to High)</option>
                <option value="year-desc">Year (Newest)</option>
                <option value="year-asc">Year (Oldest)</option>
                <option value="title-asc">Title (A-Z)</option>
                <option value="title-desc">Title (Z-A)</option>
            </select>
        </div>
        
        <div class="filter-group">
            <span class="filter-label">Year</span>
            <select class="filter-select" id="yearFilter">
                <option value="">All Years</option>
                <?php 
                $currentYear = date('Y');
                for ($y = $currentYear; $y >= 1950; $y--): ?>
                <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                <?php endfor; ?>
            </select>
        </div>
    </div>
    
    <!-- Genre Filter Pills -->
    <?php if (!empty($genres)): ?>
    <div class="genre-filters">
        <button class="genre-pill active" data-genre="">All Genres</button>
        <?php foreach ($genres as $genre): ?>
        <button class="genre-pill" data-genre="<?php echo htmlspecialchars($genre); ?>">
            <?php echo htmlspecialchars($genre); ?>
        </button>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- Results Info -->
    <div class="results-info">
        <span id="resultsCount"><?php echo count($movies); ?> films</span>
    </div>
    
    <!-- Single Unified Movie Grid -->
    <div class="movie-grid" id="movieGrid">
        <?php foreach ($movies as $movie): 
            $id = $movie['id'];
            $inWatchlist = in_array($id, $userWatchlist);
            $inFavorites = in_array($id, $userFavorites);
            $poster = $movie['poster_url'] ? htmlspecialchars($movie['poster_url']) : '';
            $title = htmlspecialchars($movie['title']);
            $year = $movie['release_date'] ? date('Y', strtotime($movie['release_date'])) : '';
            $rating = number_format($movie['rating'] ?? 0, 1);
            $genre = htmlspecialchars($movie['genre'] ?? '');
            $watchlistIcon = $inWatchlist ? 'bookmark-fill' : 'bookmark';
            $watchlistClass = $inWatchlist ? 'active' : '';
            $heartIcon = $inFavorites ? 'heart-fill' : 'heart';
            $heartClass = $inFavorites ? 'active' : '';
        ?>
        <div class="movie-card" 
             data-movie-id="<?php echo $id; ?>"
             data-title="<?php echo strtolower($title); ?>"
             data-year="<?php echo $year; ?>"
             data-rating="<?php echo $movie['rating'] ?? 0; ?>"
             data-genre="<?php echo strtolower($genre); ?>"
             onclick="goToMovie(<?php echo $id; ?>)">
            <div class="movie-poster">
                <img src="<?php echo $poster; ?>" alt="<?php echo $title; ?>" loading="lazy">
                <span class="movie-rating"><i class="bi bi-star-fill"></i> <?php echo $rating; ?></span>
                <div class="movie-actions" onclick="event.stopPropagation()">
                    <button class="action-btn <?php echo $watchlistClass; ?>" onclick="toggleWatchlist(<?php echo $id; ?>, this)" title="Watchlist">
                        <i class="bi bi-<?php echo $watchlistIcon; ?>"></i>
                    </button>
                    <button class="action-btn heart <?php echo $heartClass; ?>" onclick="toggleFavorite(<?php echo $id; ?>, this)" title="Favorite">
                        <i class="bi bi-<?php echo $heartIcon; ?>"></i>
                    </button>
                </div>
            </div>
            <div class="movie-info">
                <h3 class="movie-title"><?php echo $title; ?></h3>
                <span class="movie-year"><?php echo $year; ?></span>
                <?php if ($genre): ?>
                <div class="movie-genre"><?php echo $genre; ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <?php endif; ?>
</main>

<script>
const API_BASE = '../api';

// Store original order for reset
const movieGrid = document.getElementById('movieGrid');
const allCards = movieGrid ? Array.from(movieGrid.children) : [];

function goToMovie(id) {
    location.href = `movie-details.php?id=${id}`;
}

// ===== SORTING =====
document.getElementById('sortSelect')?.addEventListener('change', applyFilters);

// ===== YEAR FILTER =====
document.getElementById('yearFilter')?.addEventListener('change', applyFilters);

// ===== GENRE FILTER =====
document.querySelectorAll('.genre-pill').forEach(pill => {
    pill.addEventListener('click', function() {
        document.querySelectorAll('.genre-pill').forEach(p => p.classList.remove('active'));
        this.classList.add('active');
        applyFilters();
    });
});

// ===== SEARCH =====
document.getElementById('movieSearch')?.addEventListener('input', debounce(applyFilters, 200));

function applyFilters() {
    const searchQuery = document.getElementById('movieSearch')?.value.toLowerCase().trim() || '';
    const sortValue = document.getElementById('sortSelect')?.value || 'rating';
    const yearFilter = document.getElementById('yearFilter')?.value || '';
    const genreFilter = document.querySelector('.genre-pill.active')?.dataset.genre?.toLowerCase() || '';
    
    // Filter cards
    let visibleCards = allCards.filter(card => {
        const title = card.dataset.title || '';
        const year = card.dataset.year || '';
        const genre = card.dataset.genre || '';
        
        // Search filter
        if (searchQuery && !title.includes(searchQuery)) return false;
        
        // Year filter
        if (yearFilter && year !== yearFilter) return false;
        
        // Genre filter
        if (genreFilter && !genre.includes(genreFilter.toLowerCase())) return false;
        
        return true;
    });
    
    // Sort cards
    visibleCards.sort((a, b) => {
        const aRating = parseFloat(a.dataset.rating) || 0;
        const bRating = parseFloat(b.dataset.rating) || 0;
        const aYear = parseInt(a.dataset.year) || 0;
        const bYear = parseInt(b.dataset.year) || 0;
        const aTitle = a.dataset.title || '';
        const bTitle = b.dataset.title || '';
        
        switch (sortValue) {
            case 'rating': return bRating - aRating;
            case 'rating-asc': return aRating - bRating;
            case 'year-desc': return bYear - aYear;
            case 'year-asc': return aYear - bYear;
            case 'title-asc': return aTitle.localeCompare(bTitle);
            case 'title-desc': return bTitle.localeCompare(aTitle);
            default: return bRating - aRating;
        }
    });
    
    // Update DOM
    allCards.forEach(card => card.style.display = 'none');
    visibleCards.forEach(card => {
        card.style.display = '';
        movieGrid.appendChild(card); // Reorder in DOM
    });
    
    // Update count
    document.getElementById('resultsCount').textContent = `${visibleCards.length} films`;
}

// ===== WATCHLIST TOGGLE =====
async function toggleWatchlist(movieId, btn) {
    btn.disabled = true;
    btn.style.opacity = '0.5';
    
    try {
        const res = await fetch(`${API_BASE}/watchlist.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=toggle&movie_id=${movieId}`
        });
        const data = await res.json();
        
        if (data.success) {
            const icon = btn.querySelector('i');
            if (data.inWatchlist) {
                icon.className = 'bi bi-bookmark-fill';
                btn.classList.add('active');
                showNotification('Added to watchlist', 'success');
            } else {
                icon.className = 'bi bi-bookmark';
                btn.classList.remove('active');
                showNotification('Removed from watchlist', 'success');
            }
        } else {
            showNotification(data.error || 'Failed to update', 'error');
        }
    } catch (error) {
        console.error('Watchlist error:', error);
        showNotification('Network error', 'error');
    } finally {
        btn.disabled = false;
        btn.style.opacity = '1';
    }
}

// ===== FAVORITES TOGGLE =====
async function toggleFavorite(movieId, btn) {
    btn.disabled = true;
    btn.style.opacity = '0.5';
    
    try {
        const res = await fetch(`${API_BASE}/favorites.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=toggle&movie_id=${movieId}`
        });
        const data = await res.json();
        
        if (data.success) {
            const icon = btn.querySelector('i');
            if (data.liked) {
                icon.className = 'bi bi-heart-fill';
                btn.classList.add('active');
                showNotification('Added to favorites', 'success');
            } else {
                icon.className = 'bi bi-heart';
                btn.classList.remove('active');
                showNotification('Removed from favorites', 'success');
            }
        } else {
            showNotification(data.error || 'Max 4 favorites allowed', 'error');
        }
    } catch (error) {
        console.error('Favorites error:', error);
        showNotification('Network error', 'error');
    } finally {
        btn.disabled = false;
        btn.style.opacity = '1';
    }
}

// ===== UTILITIES =====
function debounce(func, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

function showNotification(message, type) {
    document.querySelectorAll('.notification').forEach(n => n.remove());
    
    const notif = document.createElement('div');
    notif.className = `notification ${type}`;
    notif.textContent = message;
    document.body.appendChild(notif);
    
    setTimeout(() => notif.classList.add('show'), 10);
    setTimeout(() => {
        notif.classList.remove('show');
        setTimeout(() => notif.remove(), 300);
    }, 2500);
}
</script>

</body>
</html>
