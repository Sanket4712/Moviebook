<?php
/**
 * Theater Schedule Movies
 * Shows real movies from database for scheduling showtimes.
 */
require_once '../includes/theater_check.php';
require_once '../includes/db.php';

$theaterId = $_SESSION['theater_id'] ?? null;
$theaterName = 'Theater Panel';
$movies = [];
$screens = [];
$scheduledShows = [];

if ($pdo && $theaterId) {
    try {
        // Get theater name
        $stmt = $pdo->prepare("SELECT name FROM theaters WHERE id = ?");
        $stmt->execute([$theaterId]);
        $theater = $stmt->fetch();
        $theaterName = $theater['name'] ?? 'Theater Panel';
        
        // Get all movies from database
        $stmt = $pdo->query("SELECT id, title, poster_url, genre, runtime FROM movies ORDER BY title LIMIT 100");
        $movies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get screens for this theater
        $stmt = $pdo->prepare("SELECT id, name, total_seats FROM screens WHERE theater_id = ?");
        $stmt->execute([$theaterId]);
        $screens = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get scheduled shows for this theater
        $stmt = $pdo->prepare("
            SELECT s.*, m.title as movie_title, m.poster_url, sc.name as screen_name
            FROM showtimes s
            JOIN movies m ON s.movie_id = m.id
            LEFT JOIN screens sc ON s.screen_id = sc.id
            WHERE s.theater_id = ? AND s.showtime >= NOW()
            ORDER BY s.showtime
            LIMIT 20
        ");
        $stmt->execute([$theaterId]);
        $scheduledShows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Theater schedule error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Movies - Theater Panel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/theater.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="theater-layout">
        <!-- Sidebar -->
        <aside class="theater-sidebar">
            <div class="sidebar-header">
                <h2>Theater Panel</h2>
                <p class="theater-name"><?= htmlspecialchars($theaterName) ?></p>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-link">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="schedule.php" class="nav-link active">
                    <i class="bi bi-calendar-event"></i> Schedule Movies
                </a>
                <a href="ticket-sell.php" class="nav-link">
                    <i class="bi bi-ticket-perforated"></i> Ticket Sales
                </a>
                <a href="bookings.php" class="nav-link">
                    <i class="bi bi-list-check"></i> See Bookings
                </a>
                <a href="../index.php" class="nav-link">
                    <i class="bi bi-arrow-left"></i> Back to Site
                </a>
                <a href="../auth/logout.php" class="nav-link logout">
                    <i class="bi bi-box-arrow-left"></i> Sign Out
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="theater-main">
            <header class="theater-header">
                <h1>Schedule Movies</h1>
                <button class="btn-primary" onclick="openScheduleModal()">
                    <i class="bi bi-plus-circle"></i> Add New Show
                </button>
            </header>

            <div class="theater-content">
                <?php if (empty($movies)): ?>
                <!-- Empty State: No Movies -->
                <div class="empty-state" style="text-align: center; padding: 64px; background: rgba(255,255,255,0.05); border-radius: 12px;">
                    <i class="bi bi-film" style="font-size: 4rem; color: var(--text-muted); margin-bottom: 20px; display: block;"></i>
                    <h2>No Movies Available</h2>
                    <p style="color: var(--text-muted); max-width: 400px; margin: 16px auto;">
                        Movies will appear here once an admin adds them to the system.
                        Contact your administrator to add movies to the catalog.
                    </p>
                </div>
                <?php else: ?>
                <!-- Search Bar -->
                <div class="search-section">
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" id="movieSearchInput" placeholder="Search movies by title or genre..." oninput="searchMovies()">
                    </div>
                </div>

                <!-- Movie Schedule Grid -->
                <div class="movie-schedule-grid" id="movieGrid">
                    <?php foreach ($movies as $movie): 
                        $posterUrl = $movie['poster_url'] ?: '';
                    ?>
                    <div class="movie-card" data-movie-id="<?= $movie['id'] ?>" data-movie-name="<?= htmlspecialchars($movie['title']) ?>" data-genre="<?= htmlspecialchars($movie['genre'] ?? '') ?>">
                        <div class="movie-poster">
                            <img src="<?= htmlspecialchars($posterUrl) ?>" alt="<?= htmlspecialchars($movie['title']) ?>">
                            <div class="movie-overlay">
                                <button class="btn-schedule" onclick="openScheduleModalWithMovie(<?= $movie['id'] ?>, '<?= htmlspecialchars(addslashes($movie['title'])) ?>')">
                                    <i class="bi bi-calendar-plus"></i> Schedule Show
                                </button>
                            </div>
                        </div>
                        <div class="movie-info">
                            <h3><?= htmlspecialchars($movie['title']) ?></h3>
                            <div class="movie-meta">
                                <span class="genre"><?= htmlspecialchars($movie['genre'] ?? 'Movie') ?></span>
                                <span class="duration"><?= $movie['runtime'] ?? '?' ?> min</span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Scheduled Shows Section -->
                <div class="scheduled-shows-section" style="margin-top: 3rem;">
                    <div class="section-header">
                        <h2>Scheduled Shows</h2>
                    </div>
                    
                    <?php if (empty($scheduledShows)): ?>
                    <div class="empty-state" style="text-align: center; padding: 48px; background: rgba(255,255,255,0.05); border-radius: 12px;">
                        <i class="bi bi-calendar-x" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 16px; display: block;"></i>
                        <h3>No Shows Scheduled</h3>
                        <p style="color: var(--text-muted);">Click on a movie above to schedule your first show.</p>
                    </div>
                    <?php else: ?>
                    <div id="scheduledShowsList" class="scheduled-shows-list">
                        <?php foreach ($scheduledShows as $show): 
                            $posterUrl = $show['poster_url'] ?: '';
                        ?>
                        <div class="scheduled-show-card">
                            <div class="show-movie-info">
                                <img src="<?= htmlspecialchars($posterUrl) ?>" alt="<?= htmlspecialchars($show['movie_title']) ?>">
                                <div class="show-details">
                                    <h4><?= htmlspecialchars($show['movie_title']) ?></h4>
                                    <p class="show-meta">
                                        <span><i class="bi bi-calendar3"></i> <?= date('M j, Y', strtotime($show['showtime'])) ?></span>
                                        <span><i class="bi bi-clock"></i> <?= date('g:i A', strtotime($show['showtime'])) ?></span>
                                        <span><i class="bi bi-display"></i> <?= htmlspecialchars($show['screen_name'] ?? 'Screen') ?></span>
                                    </p>
                                    <p class="show-meta">
                                        <span class="price">₹<?= number_format($show['price'] ?? 0) ?></span>
                                    </p>
                                </div>
                            </div>
                            <div class="show-actions">
                                <button class="btn-icon danger" title="Cancel Show" onclick="cancelShow(<?= $show['id'] ?>)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Schedule Modal -->
    <div id="scheduleModal" class="modal">
        <div class="modal-content modal-content-large">
            <div class="modal-header">
                <h2>Add New Show</h2>
                <button class="btn-close" onclick="closeScheduleModal()">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body">
                <!-- Movies Grid in Modal -->
                <div class="modal-search-section">
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" id="modalMovieSearch" placeholder="Search movies to schedule..." oninput="searchMoviesInModal()">
                    </div>
                </div>
                
                <div class="modal-movies-grid" id="modalMoviesGrid">
                    <?php foreach ($movies as $movie): 
                        $posterUrl = $movie['poster_url'] ?: '';
                    ?>
                    <div class="modal-movie-item" data-movie-id="<?= $movie['id'] ?>" data-movie-name="<?= htmlspecialchars($movie['title']) ?>" data-genre="<?= htmlspecialchars($movie['genre'] ?? '') ?>" onclick="selectMovieForSchedule(<?= $movie['id'] ?>, '<?= htmlspecialchars(addslashes($movie['title'])) ?>')">
                        <img src="<?= htmlspecialchars($posterUrl) ?>" alt="<?= htmlspecialchars($movie['title']) ?>">
                        <div class="modal-movie-info">
                            <h4><?= htmlspecialchars($movie['title']) ?></h4>
                            <p><?= htmlspecialchars($movie['genre'] ?? 'Movie') ?> • <?= $movie['runtime'] ?? '?' ?> min</p>
                        </div>
                        <div class="select-overlay">
                            <i class="bi bi-check-circle-fill"></i>
                            <span>Select to Schedule</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Schedule Details Modal -->
    <div id="scheduleDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Schedule Show - <span id="selectedMovieTitle"></span></h2>
                <button class="btn-close" onclick="closeScheduleDetailsModal()">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="scheduleForm" onsubmit="handleScheduleSubmit(event)">
                    <input type="hidden" id="movieSelect">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="screenSelect">
                                <i class="bi bi-display"></i> Select Screen/Hall
                            </label>
                            <select id="screenSelect" required>
                                <option value="">Choose a screen...</option>
                                <?php foreach ($screens as $screen): ?>
                                <option value="<?= $screen['id'] ?>"><?= htmlspecialchars($screen['name']) ?> (<?= $screen['total_seats'] ?> seats)</option>
                                <?php endforeach; ?>
                                <?php if (empty($screens)): ?>
                                <option value="" disabled>No screens configured</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="showDate">
                                <i class="bi bi-calendar3"></i> Show Date
                            </label>
                            <input type="date" id="showDate" required min="<?= date('Y-m-d') ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="showTime">
                                <i class="bi bi-clock"></i> Show Time
                            </label>
                            <input type="time" id="showTime" required>
                        </div>

                        <div class="form-group">
                            <label for="ticketPrice">
                                <i class="bi bi-currency-rupee"></i> Ticket Price
                            </label>
                            <input type="number" id="ticketPrice" min="0" step="1" placeholder="150" required>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="closeScheduleDetailsModal()">
                            Cancel
                        </button>
                        <button type="submit" class="btn-primary">
                            <i class="bi bi-calendar-check"></i> Schedule Show
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/theater.js"></script>
    <script>
        // Client-side search
        function searchMovies() {
            const query = document.getElementById('movieSearchInput').value.toLowerCase();
            const cards = document.querySelectorAll('#movieGrid .movie-card');
            cards.forEach(card => {
                const name = card.dataset.movieName.toLowerCase();
                const genre = card.dataset.genre.toLowerCase();
                card.style.display = (name.includes(query) || genre.includes(query)) ? '' : 'none';
            });
        }
        
        function searchMoviesInModal() {
            const query = document.getElementById('modalMovieSearch').value.toLowerCase();
            const items = document.querySelectorAll('#modalMoviesGrid .modal-movie-item');
            items.forEach(item => {
                const name = item.dataset.movieName.toLowerCase();
                const genre = item.dataset.genre.toLowerCase();
                item.style.display = (name.includes(query) || genre.includes(query)) ? '' : 'none';
            });
        }
    </script>
</body>
</html>
