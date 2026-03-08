<?php 
/**
 * MovieBook - Movies & Series List
 * 
 * DATA HONESTY: This page displays only real movies from the database.
 * If no movies exist, an empty state is shown. No fake or placeholder data.
 */
require_once '../includes/auth_check.php';
require_once '../includes/db.php';

$movies = [];
$popularMovies = [];

if ($pdo) {
    try {
        // Fetch movies from database
        $stmt = $pdo->query("
            SELECT m.*, 
                   COALESCE((SELECT AVG(rating) FROM diary WHERE movie_id = m.id AND rating > 0), 0) as avg_rating,
                   (SELECT COUNT(*) FROM diary WHERE movie_id = m.id) as watch_count
            FROM movies m 
            ORDER BY m.created_at DESC 
            LIMIT 50
        ");
        $movies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get most watched movies for "Popular This Week" section
        $stmt = $pdo->query("
            SELECT m.*, 
                   COALESCE((SELECT AVG(rating) FROM diary WHERE movie_id = m.id AND rating > 0), 0) as avg_rating,
                   (SELECT COUNT(*) FROM diary WHERE movie_id = m.id AND watched_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)) as week_watches
            FROM movies m 
            HAVING week_watches > 0
            ORDER BY week_watches DESC 
            LIMIT 8
        ");
        $popularMovies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Movie list error: " . $e->getMessage());
    }
}

$hasMovies = !empty($movies);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movies & Series - MovieBook</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/user.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .page-header { padding: 100px 40px 40px; text-align: center; }
        .page-header h1 { font-size: 2.5rem; margin-bottom: 8px; }
        .page-header p { color: #888; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px 40px; }
        .filter-section { display: flex; gap: 12px; margin-bottom: 32px; flex-wrap: wrap; }
        .filter-btn { 
            padding: 10px 20px; 
            background: #1a1a1a; 
            border: 1px solid #333; 
            border-radius: 20px; 
            color: #888; 
            cursor: pointer; 
            transition: all 0.2s; 
        }
        .filter-btn.active, .filter-btn:hover { 
            background: #e50914; 
            border-color: #e50914; 
            color: white; 
        }
        .section-movies { margin-bottom: 48px; }
        .section-movies h2 { font-size: 1.5rem; margin-bottom: 20px; }
        .movie-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); 
            gap: 20px; 
        }
        .movie-card { 
            cursor: pointer; 
            transition: transform 0.3s; 
        }
        .movie-card:hover { transform: translateY(-8px); }
        .movie-poster { 
            position: relative; 
            aspect-ratio: 2/3; 
            border-radius: 12px; 
            overflow: hidden; 
            background: #1a1a1a;
        }
        .movie-poster img { 
            width: 100%; 
            height: 100%; 
            object-fit: cover; 
        }
        .movie-card h4 { 
            margin-top: 12px; 
            font-size: 14px; 
            white-space: nowrap; 
            overflow: hidden; 
            text-overflow: ellipsis; 
        }
        .movie-card p { 
            color: #888; 
            font-size: 13px; 
            display: flex; 
            align-items: center; 
            gap: 4px; 
        }
        .movie-card p i { color: #f5c518; font-size: 12px; }
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: #141414;
            border-radius: 16px;
            border: 1px dashed #333;
        }
        .empty-state i { font-size: 4rem; color: #333; margin-bottom: 16px; display: block; }
        .empty-state h3 { margin-bottom: 8px; }
        .empty-state p { color: #666; max-width: 400px; margin: 0 auto 24px; }
        .empty-state a { color: #e50914; text-decoration: none; }
        .empty-state a:hover { text-decoration: underline; }
        .no-image { 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            width: 100%; 
            height: 100%; 
            background: #1a1a1a; 
            color: #444; 
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <a href="../index.php">
                    <img src="../logo.png" alt="MOVIEBOOK" class="logo-img">
                </a>
            </div>
            <ul class="nav-menu">
                <li><a href="films.php">Films</a></li>
                <li><a href="home.php">Tickets</a></li>
                <li><a href="profile.php">My Profile</a></li>
            </ul>
            <div class="nav-right">
                <a href="../auth/logout.php" style="color: #888; text-decoration: none;">
                    <i class="bi bi-box-arrow-right"></i> Sign Out
                </a>
            </div>
        </div>
    </nav>

    <div class="page-header">
        <h1>Movies & Series</h1>
        <p>Discover your next favorite movie</p>
    </div>

    <div class="container">
        <?php if (!$hasMovies): ?>
        <!-- Empty State -->
        <div class="empty-state">
            <i class="bi bi-film"></i>
            <h3>No Movies Yet</h3>
            <p>
                The movie catalog is empty. Movies will appear here once they are added by administrators.
            </p>
            <a href="home.php">Browse available showtimes →</a>
        </div>
        <?php else: ?>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <button class="filter-btn active" onclick="filterMovies('all')">All</button>
            <button class="filter-btn" onclick="filterMovies('watched')">Watched</button>
            <button class="filter-btn" onclick="filterMovies('watchlist')">In Watchlist</button>
        </div>

        <?php if (!empty($popularMovies)): ?>
        <!-- Popular This Week -->
        <section class="section-movies">
            <h2>Popular This Week</h2>
            <div class="movie-grid">
                <?php foreach ($popularMovies as $movie): 
                    $rating = $movie['avg_rating'] > 0 ? number_format($movie['avg_rating'], 1) : '-';
                ?>
                <div class="movie-card" onclick="location.href='movie-details.php?id=<?= $movie['id'] ?>'">
                    <div class="movie-poster">
                        <?php if ($movie['poster_url']): ?>
                        <img src="<?= htmlspecialchars($movie['poster_url']) ?>" alt="<?= htmlspecialchars($movie['title']) ?>">
                        <?php else: ?>
                        <div class="no-image"><i class="bi bi-film"></i></div>
                        <?php endif; ?>
                    </div>
                    <h4><?= htmlspecialchars($movie['title']) ?></h4>
                    <p><i class="bi bi-star-fill"></i> <?= $rating ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- All Movies -->
        <section class="section-movies">
            <h2>All Movies</h2>
            <div class="movie-grid">
                <?php foreach ($movies as $movie): 
                    $rating = $movie['avg_rating'] > 0 ? number_format($movie['avg_rating'], 1) : '-';
                ?>
                <div class="movie-card" onclick="location.href='movie-details.php?id=<?= $movie['id'] ?>'">
                    <div class="movie-poster">
                        <?php if ($movie['poster_url']): ?>
                        <img src="<?= htmlspecialchars($movie['poster_url']) ?>" alt="<?= htmlspecialchars($movie['title']) ?>">
                        <?php else: ?>
                        <div class="no-image"><i class="bi bi-film"></i></div>
                        <?php endif; ?>
                    </div>
                    <h4><?= htmlspecialchars($movie['title']) ?></h4>
                    <p><i class="bi bi-star-fill"></i> <?= $rating ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; 2026 MovieBook. All rights reserved.</p>
        </div>
    </footer>

    <script>
        function filterMovies(type) {
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            // Filter logic would require additional data attributes on cards
        }
    </script>
</body>
</html>
