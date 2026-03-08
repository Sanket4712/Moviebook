<?php require_once '../includes/admin_check.php'; ?>
<?php require_once '../includes/db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movies - Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* TMDB Search Dropdown */
        .tmdb-search-container {
            position: relative;
        }
        .tmdb-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--admin-dark);
            border: 1px solid var(--admin-border);
            border-radius: 8px;
            max-height: 320px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
        }
        .tmdb-dropdown.active { display: block; }
        .tmdb-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            cursor: pointer;
            border-bottom: 1px solid var(--admin-border);
            transition: background 0.2s;
        }
        .tmdb-item:hover { background: var(--admin-gray); }
        .tmdb-item:last-child { border-bottom: none; }
        .tmdb-item img {
            width: 40px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
            background: var(--admin-gray);
        }
        .tmdb-item-info { flex: 1; }
        .tmdb-item-title { font-weight: 600; font-size: 14px; }
        .tmdb-item-year { color: var(--admin-text-muted); font-size: 12px; }
        .tmdb-item-overview { 
            color: var(--admin-text-muted); 
            font-size: 11px; 
            margin-top: 4px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .tmdb-loading {
            padding: 20px;
            text-align: center;
            color: var(--admin-text-muted);
        }
        .tmdb-no-results {
            padding: 20px;
            text-align: center;
            color: var(--admin-text-muted);
        }
        
        /* TMDB Attribution Banner */
        .tmdb-attribution {
            display: none;
            background: linear-gradient(135deg, rgba(1,180,228,0.15) 0%, rgba(1,180,228,0.05) 100%);
            border: 1px solid rgba(1,180,228,0.4);
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 20px;
            align-items: center;
            gap: 12px;
        }
        .tmdb-attribution.active { display: flex; }
        .tmdb-attribution i { color: #01b4e4; font-size: 1.5rem; }
        .tmdb-attribution-text { flex: 1; }
        .tmdb-attribution-text strong { color: #01b4e4; }
        .tmdb-attribution-text p { font-size: 12px; color: var(--admin-text-muted); margin-top: 2px; }
        .tmdb-clear-btn {
            background: transparent;
            border: 1px solid rgba(255,255,255,0.2);
            color: var(--admin-text-muted);
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
        }
        .tmdb-clear-btn:hover { border-color: rgba(255,255,255,0.4); color: white; }
        
        /* Auto-filled field indicator */
        .form-group.auto-filled input,
        .form-group.auto-filled textarea,
        .form-group.auto-filled select {
            border-color: rgba(1,180,228,0.5);
            background: rgba(1,180,228,0.05);
        }
        .form-group.auto-filled label::after {
            content: ' (auto-filled)';
            color: #01b4e4;
            font-size: 10px;
            font-weight: normal;
        }
        
        /* Letterboxd Import Styles */
        .btn-import {
            background: #ff8000;
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-import:hover { background: #e67300; }
        
        .import-upload-zone {
            border: 2px dashed var(--admin-border);
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .import-upload-zone:hover,
        .import-upload-zone.dragover {
            border-color: #ff8000;
            background: rgba(255,128,0,0.05);
        }
        .import-upload-zone i {
            font-size: 3rem;
            color: #ff8000;
            margin-bottom: 16px;
            display: block;
        }
        .import-upload-zone p { color: var(--admin-text-muted); margin-top: 8px; }
        
        .import-queue {
            max-height: 400px;
            overflow-y: auto;
            margin-top: 20px;
        }
        .import-queue-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: var(--admin-gray);
            border-radius: 8px;
            margin-bottom: 8px;
        }
        .import-queue-item.matched { border-left: 3px solid #00c853; }
        .import-queue-item.matched_multi { border-left: 3px solid #ffab00; }
        .import-queue-item.not-found { border-left: 3px solid #ff5252; }
        .import-queue-item.not_found { border-left: 3px solid #ff5252; }
        .import-queue-item.api_error { border-left: 3px solid #ff8000; background: rgba(255,128,0,0.05); }
        .import-queue-item.pending { border-left: 3px solid #888; }
        .import-queue-item.added { border-left: 3px solid #01b4e4; opacity: 0.6; }
        .import-queue-item img {
            width: 40px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
            background: var(--admin-dark);
        }
        .import-queue-info { flex: 1; }
        .import-queue-title { font-weight: 600; }
        .import-queue-status {
            font-size: 12px;
            color: var(--admin-text-muted);
            margin-top: 2px;
        }
        .import-queue-status.success { color: #00c853; }
        .import-queue-status.error { color: #ff5252; }
        .import-queue-actions { display: flex; gap: 8px; }
        .import-queue-actions button {
            padding: 6px 12px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 12px;
        }
        .btn-confirm { background: #00c853; color: white; }
        .btn-confirm:hover { background: #00a843; }
        .btn-skip { background: var(--admin-border); color: var(--admin-text-muted); }
        .btn-skip:hover { background: var(--admin-gray); }
        
        .import-progress {
            background: var(--admin-gray);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
        }
        .import-progress-bar {
            height: 8px;
            background: var(--admin-dark);
            border-radius: 4px;
            overflow: hidden;
            margin-top: 8px;
        }
        .import-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #ff8000, #ffab00);
            transition: width 0.3s;
        }
        .import-stats {
            display: flex;
            gap: 20px;
            margin-top: 12px;
            font-size: 13px;
        }
        .import-stats span { color: var(--admin-text-muted); }
        .import-stats .matched { color: #00c853; }
        .import-stats .skipped { color: #ff5252; }
        .import-stats .added { color: #01b4e4; }
        
        .letterboxd-notice {
            background: rgba(255,128,0,0.1);
            border: 1px solid rgba(255,128,0,0.3);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 16px;
            font-size: 13px;
        }
        .letterboxd-notice i { color: #ff8000; margin-right: 8px; }
        
        /* ===========================================
           PREMIUM MOVIE CARDS - Cinematic Hero Style
           =========================================== */
        .movie-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
            padding: 8px 0;
        }
        
        .movie-card {
            background: var(--admin-dark);
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid var(--admin-border);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        
        .movie-card:hover {
            transform: translateY(-4px);
            border-color: rgba(229, 9, 20, 0.4);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5), 0 0 40px rgba(229, 9, 20, 0.1);
        }
        
        /* Backdrop Hero Section */
        .movie-backdrop {
            position: relative;
            width: 100%;
            height: 180px;
            overflow: hidden;
        }
        
        .movie-backdrop-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .movie-backdrop-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: var(--admin-text-muted);
            text-align: center;
            padding: 24px;
            font-weight: 700;
            letter-spacing: 1px;
        }
        
        /* Gradient fallbacks for generated backgrounds */
        .movie-backdrop-placeholder.gradient-1 {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
        }
        .movie-backdrop-placeholder.gradient-2 {
            background: linear-gradient(135deg, #2d132c 0%, #1a1a2e 50%, #16213e 100%);
        }
        .movie-backdrop-placeholder.gradient-3 {
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 50%, #141e30 100%);
        }
        .movie-backdrop-placeholder.gradient-4 {
            background: linear-gradient(135deg, #1f1c2c 0%, #1a1a2e 50%, #16213e 100%);
        }
        
        .movie-card:hover .movie-backdrop-img {
            transform: scale(1.08);
        }
        
        /* Dark gradient overlay */
        .movie-backdrop::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 100%;
            background: linear-gradient(
                to top,
                rgba(20, 20, 20, 1) 0%,
                rgba(20, 20, 20, 0.8) 30%,
                rgba(20, 20, 20, 0.2) 70%,
                rgba(20, 20, 20, 0) 100%
            );
            pointer-events: none;
        }
        
        /* Movie poster thumbnail overlay */
        .movie-poster-thumb {
            position: absolute;
            bottom: -40px;
            left: 20px;
            width: 80px;
            height: 120px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.6);
            border: 3px solid var(--admin-dark);
            z-index: 10;
            background: var(--admin-gray);
        }
        
        .movie-poster-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .movie-poster-thumb i {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--admin-text-muted);
            font-size: 1.5rem;
        }
        
        /* Movie Info Section */
        .movie-info {
            padding: 50px 20px 20px 20px;
            margin-left: 90px;
        }
        
        .movie-info h4 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--admin-text);
            margin-bottom: 4px;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .movie-year {
            font-size: 0.9rem;
            color: var(--admin-text-secondary);
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        .movie-meta-line {
            font-size: 0.8rem;
            color: var(--admin-text-muted);
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .movie-meta-line i {
            font-size: 0.7rem;
            opacity: 0.7;
        }
        
        .movie-meta-line.director {
            color: var(--admin-text-secondary);
        }
        
        .movie-meta-line.cast {
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        /* Rating badge with stars */
        .movie-rating-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(8px);
            padding: 6px 10px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 2px;
            font-size: 0.8rem;
            z-index: 5;
        }
        
        .movie-rating-badge i {
            color: #ffab00;
        }
        
        .movie-rating-badge i.bi-star {
            color: rgba(255, 171, 0, 0.3);
        }
        
        /* Needs Polish indicator */
        .needs-polish-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            background: linear-gradient(135deg, rgba(255, 128, 0, 0.95) 0%, rgba(255, 152, 0, 0.9) 100%);
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            z-index: 5;
            box-shadow: 0 4px 12px rgba(255, 128, 0, 0.3);
            display: flex;
            align-items: center;
            gap: 4px;
            cursor: help;
            transition: all 0.2s ease;
        }
        
        .needs-polish-badge:hover {
            background: linear-gradient(135deg, rgba(255, 128, 0, 1) 0%, rgba(255, 152, 0, 0.95) 100%);
            box-shadow: 0 6px 16px rgba(255, 128, 0, 0.4);
            transform: scale(1.05);
        }
        
        /* Movie actions */
        .movie-actions {
            display: flex;
            gap: 8px;
            padding: 16px 20px;
            border-top: 1px solid var(--admin-border);
            background: var(--admin-gray);
        }
        
        .movie-actions button {
            flex: 1;
            padding: 10px;
            border-radius: 8px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        
        /* Genre/Runtime row */
        .movie-genre-runtime {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        
        .movie-genre-tag {
            background: rgba(229, 9, 20, 0.15);
            color: var(--admin-red-light);
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        .movie-runtime-tag {
            background: rgba(255, 255, 255, 0.08);
            color: var(--admin-text-muted);
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        /* Success animation */
        @keyframes pulse {
            0% { transform: scale(0.8); opacity: 0; }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <img src="../logo.png" alt="MOVIEBOOK" class="admin-logo">
                <h2>Admin Panel</h2>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-link">
                    <i class="bi bi-grid-1x2"></i>
                    <span>Dashboard</span>
                </a>
                <a href="users.php" class="nav-link">
                    <i class="bi bi-people"></i>
                    <span>Users</span>
                </a>
                <a href="theaters.php" class="nav-link">
                    <i class="bi bi-building"></i>
                    <span>Theaters</span>
                </a>
                <a href="movies.php" class="nav-link active">
                    <i class="bi bi-film"></i>
                    <span>Movies</span>
                </a>
                <a href="bookings.php" class="nav-link">
                    <i class="bi bi-ticket-perforated"></i>
                    <span>Bookings</span>
                </a>
                <a href="settings.php" class="nav-link">
                    <i class="bi bi-gear"></i>
                    <span>Settings</span>
                </a>
            </nav>
            <div class="sidebar-footer">
                <a href="../auth/logout.php" class="nav-link logout">
                    <i class="bi bi-box-arrow-left"></i>
                    <span>Sign Out</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <!-- Top Bar -->
            <header class="admin-topbar">
                <div class="topbar-left">
                    <button class="menu-toggle" onclick="toggleSidebar()">
                        <i class="bi bi-list"></i>
                    </button>
                    <h1>Movies</h1>
                </div>
                <div class="topbar-right">
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" id="searchInput" placeholder="Search movies...">
                    </div>
                    <div class="admin-profile">
                        <img src="https://ui-avatars.com/api/?name=Admin&size=40&background=e50914&color=fff" alt="Admin">
                        <span>Admin</span>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <div class="dashboard-content">
                <!-- Stats -->
                <section class="stats-section">
                    <div class="stat-card primary">
                        <div class="stat-icon"><i class="bi bi-film"></i></div>
                        <div class="stat-info">
                            <h3>Total Movies</h3>
                            <p class="stat-number" id="statTotal">0</p>
                        </div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-icon"><i class="bi bi-play-circle"></i></div>
                        <div class="stat-info">
                            <h3>Now Showing</h3>
                            <p class="stat-number" id="statNowShowing">0</p>
                        </div>
                    </div>
                    <div class="stat-card warning">
                        <div class="stat-icon"><i class="bi bi-calendar"></i></div>
                        <div class="stat-info">
                            <h3>Coming Soon</h3>
                            <p class="stat-number" id="statComingSoon">0</p>
                        </div>
                    </div>
                    <div class="stat-card danger">
                        <div class="stat-icon"><i class="bi bi-plus-lg"></i></div>
                        <div class="stat-info">
                            <h3>This Month</h3>
                            <p class="stat-number" id="statAddedMonth">0</p>
                        </div>
                    </div>
                </section>

                <!-- Alert Area -->
                <div id="alertArea"></div>

                <!-- Actions Bar -->
                <section class="section-card">
                    <div class="card-header">
                        <h2><i class="bi bi-collection-play"></i> Movie Library</h2>
                        <div style="display: flex; gap: 12px; align-items: center;">
                            <select class="time-filter" id="statusFilter">
                                <option value="" selected>All Movies</option>
                                <option value="now_showing">Now Showing</option>
                                <option value="coming_soon">Coming Soon</option>
                                <option value="ended">Ended</option>
                            </select>
                            <button class="btn-import" id="openImportModal">
                                <i class="bi bi-file-earmark-arrow-up"></i> Import CSV
                            </button>
                            <button class="btn-approve" id="openAddModal">
                                <i class="bi bi-plus-lg"></i> Add Movie
                            </button>
                        </div>
                    </div>
                    <div class="movie-grid" id="movieGrid">
                        <div class="empty-state">
                            <i class="bi bi-film"></i>
                            <p>No movies yet. Add your first movie!</p>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <!-- Add Movie Modal -->
    <div class="modal-overlay" id="addMovieModal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h2><i class="bi bi-film"></i> Add Movie</h2>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form id="addMovieForm">
                <div class="modal-body">
                    <!-- TMDB Attribution Banner -->
                    <div class="tmdb-attribution" id="tmdbAttribution">
                        <i class="bi bi-cloud-download"></i>
                        <div class="tmdb-attribution-text">
                            <strong>Auto-filled from TMDB</strong>
                            <p>Review and edit fields as needed before saving</p>
                        </div>
                        <button type="button" class="tmdb-clear-btn" onclick="clearAutoFill()">
                            <i class="bi bi-x-lg"></i> Clear
                        </button>
                    </div>
                    
                    <div class="form-grid">
                        <!-- Title with TMDB Search -->
                        <div class="form-group full-width tmdb-search-container">
                            <label>Title <span class="required">*</span></label>
                            <input type="text" name="title" id="titleInput" placeholder="Start typing to search TMDB..." required autocomplete="off">
                            <div class="tmdb-dropdown" id="tmdbDropdown"></div>
                        </div>
                        
                        <div class="form-group">
                            <label>Release Year <span class="required">*</span></label>
                            <input type="number" name="release_year" min="1900" max="2030" placeholder="2024" required>
                        </div>
                        <div class="form-group">
                            <label>Language <span class="required">*</span></label>
                            <input type="text" name="language" placeholder="English" required>
                        </div>
                        <div class="form-group">
                            <label>Runtime (min) <span class="required">*</span></label>
                            <input type="number" name="runtime" min="1" max="600" placeholder="120" required>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="now_showing">Now Showing</option>
                                <option value="coming_soon">Coming Soon</option>
                                <option value="ended">Ended</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Rating (0-10)</label>
                            <input type="number" name="rating" min="0" max="10" step="0.1" value="0">
                        </div>
                        <div class="form-group">
                            <label>Country <span class="required">*</span></label>
                            <input type="text" name="country" placeholder="USA" required>
                        </div>
                        <div class="form-group full-width">
                            <label>Poster URL <span class="required">*</span></label>
                            <input type="url" name="poster_url" placeholder="https://image.tmdb.org/..." required>
                        </div>
                        <div class="form-group full-width">
                            <label>Backdrop URL</label>
                            <input type="url" name="backdrop_url" placeholder="https://...">
                        </div>
                        <div class="form-group full-width">
                            <label>Description <span class="required">*</span></label>
                            <textarea name="description" placeholder="Movie synopsis..." required></textarea>
                        </div>
                        <div class="form-group full-width">
                            <label>Genres <span class="required">*</span></label>
                            <input type="text" name="genre" placeholder="Action, Drama, Thriller" required>
                            <span class="hint">Comma-separated</span>
                        </div>
                        <div class="form-group">
                            <label>Director <span class="required">*</span></label>
                            <input type="text" name="director" placeholder="Director name" required>
                        </div>
                        <div class="form-group full-width">
                            <label>Cast <span class="required">*</span></label>
                            <input type="text" name="cast" placeholder="Actor 1, Actor 2, Actor 3" required>
                            <span class="hint">Comma-separated</span>
                        </div>
                        <div class="form-group">
                            <label>Release Date</label>
                            <input type="date" name="release_date">
                        </div>
                        <div class="form-group">
                            <label>TMDB ID</label>
                            <input type="text" name="tmdb_id" placeholder="Auto-filled" readonly>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-approve" id="submitBtn">
                        <i class="bi bi-plus-lg"></i> Add Movie
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <!-- Letterboxd Import Modal -->
    <div class="modal-overlay" id="importModal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h2><i class="bi bi-file-earmark-arrow-up" style="color: #ff8000;"></i> Import from Letterboxd</h2>
                <button class="modal-close" onclick="closeImportModal()">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Step 1: Upload -->
                <div id="importStep1">
                    <div class="letterboxd-notice" style="background: rgba(0,200,83,0.1); border-color: rgba(0,200,83,0.3);">
                        <i class="bi bi-lightning-charge" style="color: #00c853;"></i>
                        <strong>Instant Import:</strong> Movies are created directly in your database with placeholder metadata.
                        Polish them later using the Edit button on each movie card.
                    </div>
                    
                    <div class="import-upload-zone" id="uploadZone">
                        <i class="bi bi-file-earmark-arrow-up"></i>
                        <h3>Drop your Letterboxd CSV here</h3>
                        <p>or click to browse files</p>
                        <p style="font-size: 12px; margin-top: 16px;">
                            Export from Letterboxd: Profile → Settings → Import & Export → Export Your Data
                        </p>
                        <input type="file" id="csvFileInput" accept=".csv" style="display: none;">
                    </div>
                </div>
                
                <!-- Step 2: Preview & Import -->
                <div id="importStep2" style="display: none;">
                    <div class="letterboxd-notice" style="background: rgba(0,200,83,0.1); border-color: rgba(0,200,83,0.3);">
                        <i class="bi bi-check-circle" style="color: #00c853;"></i>
                        <strong id="previewSummary">Ready to import 0 movies</strong>
                        <span id="previewRatings" style="margin-left: 12px; color: var(--admin-text-muted);"></span>
                    </div>
                    
                    <div class="import-progress" id="importProgress" style="display: none;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span id="importStatusText">Importing movies...</span>
                            <span id="progressText">0 / 0</span>
                        </div>
                        <div class="import-progress-bar">
                            <div class="import-progress-fill" id="progressFill" style="width: 0%;"></div>
                        </div>
                    </div>
                    
                    <!-- Preview list (scrollable) -->
                    <div class="import-preview-list" id="importPreviewList" style="max-height: 300px; overflow-y: auto; margin-top: 16px;">
                        <!-- Movie preview items will be rendered here -->
                    </div>
                    
                    <!-- Success message -->
                    <div id="importSuccess" style="display: none; text-align: center; padding: 40px;">
                        <i class="bi bi-check-circle" style="font-size: 64px; color: #00c853; animation: pulse 0.5s ease-in-out;"></i>
                        <h3 style="margin-top: 16px; margin-bottom: 8px;" id="successTitle">Import Complete!</h3>
                        <p style="color: var(--admin-text-muted); margin: 0;" id="successDetails"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeImportModal()">Close</button>
                <button type="button" class="btn-import" id="importAllBtn" style="display: none; background: #00c853;" onclick="importAllMovies()">
                    <i class="bi bi-lightning-charge"></i> Import All Now
                </button>
            </div>
        </div>
    </div>


    <script>
        const API_URL = '../api/admin_movies.php';
        const TMDB_API = '../api/tmdb_lookup.php';
        const modal = document.getElementById('addMovieModal');
        const form = document.getElementById('addMovieForm');
        const movieGrid = document.getElementById('movieGrid');
        const alertArea = document.getElementById('alertArea');
        const loadingOverlay = document.getElementById('loadingOverlay');
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const titleInput = document.getElementById('titleInput');
        const tmdbDropdown = document.getElementById('tmdbDropdown');
        const tmdbAttribution = document.getElementById('tmdbAttribution');

        // Modal Controls
        document.getElementById('openAddModal').onclick = () => {
            modal.classList.add('active');
            form.reset();
            clearAutoFill();
            titleInput.focus();
        };

        function closeModal() {
            modal.classList.remove('active');
            tmdbDropdown.classList.remove('active');
        }

        modal.onclick = (e) => {
            if (e.target === modal) closeModal();
        };

        // Utility Functions
        function showAlert(message, type = 'success') {
            alertArea.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
            setTimeout(() => alertArea.innerHTML = '', 4000);
        }

        function showLoading() { loadingOverlay.classList.add('active'); }
        function hideLoading() { loadingOverlay.classList.remove('active'); }

        // ===========================================
        // TMDB AUTO-SEARCH & AUTO-FILL
        // ===========================================
        
        let tmdbSearchTimeout;
        let selectedTmdbId = null;
        
        titleInput.addEventListener('input', () => {
            clearTimeout(tmdbSearchTimeout);
            const query = titleInput.value.trim();
            
            if (query.length < 2) {
                tmdbDropdown.classList.remove('active');
                return;
            }
            
            // Show loading state
            tmdbDropdown.innerHTML = '<div class="tmdb-loading"><i class="bi bi-hourglass-split"></i> Searching TMDB...</div>';
            tmdbDropdown.classList.add('active');
            
            tmdbSearchTimeout = setTimeout(() => searchTMDB(query), 400);
        });
        
        titleInput.addEventListener('focus', () => {
            if (titleInput.value.length >= 2 && tmdbDropdown.innerHTML) {
                tmdbDropdown.classList.add('active');
            }
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.tmdb-search-container')) {
                tmdbDropdown.classList.remove('active');
            }
        });
        
        async function searchTMDB(query) {
            try {
                const res = await fetch(`${TMDB_API}?action=search&q=${encodeURIComponent(query)}`);
                const data = await res.json();
                
                if (data.success && data.movies.length > 0) {
                    tmdbDropdown.innerHTML = data.movies.map(movie => `
                        <div class="tmdb-item" onclick="selectTMDBMovie(${movie.tmdb_id})">
                            <img src="${movie.poster_url || ''}" alt="" onerror="this.style.display='none'">
                            <div class="tmdb-item-info">
                                <div class="tmdb-item-title">${escapeHtml(movie.title)}</div>
                                <div class="tmdb-item-year">${movie.year || 'Unknown year'}</div>
                                <div class="tmdb-item-overview">${escapeHtml(movie.overview || '')}</div>
                            </div>
                        </div>
                    `).join('');
                } else {
                    tmdbDropdown.innerHTML = `
                        <div class="tmdb-no-results">
                            <i class="bi bi-search"></i>
                            <p>No movies found. You can still enter details manually.</p>
                        </div>
                    `;
                }
            } catch (e) {
                console.error('TMDB search error:', e);
                tmdbDropdown.innerHTML = `
                    <div class="tmdb-no-results">
                        <i class="bi bi-exclamation-triangle"></i>
                        <p>Search failed. Enter details manually.</p>
                    </div>
                `;
            }
        }
        
        async function selectTMDBMovie(tmdbId) {
            tmdbDropdown.classList.remove('active');
            showLoading();
            
            try {
                const res = await fetch(`${TMDB_API}?action=details&tmdb_id=${tmdbId}`);
                const data = await res.json();
                
                if (data.success && data.form_data) {
                    fillFormFromTMDB(data.form_data);
                    selectedTmdbId = tmdbId;
                    showAlert('Form auto-filled from TMDB. Review and click Add Movie to save.', 'success');
                } else {
                    showAlert('Could not fetch movie details', 'error');
                }
            } catch (e) {
                console.error('TMDB details error:', e);
                showAlert('Failed to fetch movie details', 'error');
            } finally {
                hideLoading();
            }
        }
        
        function fillFormFromTMDB(data) {
            // Map TMDB data to form fields
            const fieldMapping = {
                'title': 'title',
                'release_year': 'release_year',
                'language': 'language',
                'runtime': 'runtime',
                'rating': 'rating',
                'poster_url': 'poster_url',
                'backdrop_url': 'backdrop_url',
                'description': 'description',
                'genre': 'genre',
                'director': 'director',
                'country': 'country',
                'cast': 'cast',
                'release_date': 'release_date',
                'tmdb_id': 'tmdb_id'
            };
            
            // Clear previous auto-fill indicators
            form.querySelectorAll('.form-group').forEach(g => g.classList.remove('auto-filled'));
            
            Object.entries(fieldMapping).forEach(([tmdbKey, formKey]) => {
                const input = form.querySelector(`[name="${formKey}"]`);
                if (input && data[tmdbKey] !== undefined && data[tmdbKey] !== null && data[tmdbKey] !== '') {
                    input.value = data[tmdbKey];
                    // Mark as auto-filled
                    input.closest('.form-group')?.classList.add('auto-filled');
                }
            });
            
            // Show attribution banner
            tmdbAttribution.classList.add('active');
        }
        
        function clearAutoFill() {
            // Clear attribution
            tmdbAttribution.classList.remove('active');
            selectedTmdbId = null;
            
            // Remove auto-filled indicators
            form.querySelectorAll('.form-group').forEach(g => g.classList.remove('auto-filled'));
            
            // Clear TMDB ID
            form.querySelector('[name="tmdb_id"]').value = '';
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        /**
         * Convert 0-10 rating to 5-star display with half-star support
         * @param {number} rating10 - Rating on 0-10 scale
         * @returns {string} HTML string with star icons
         */
        function renderStarRating(rating10) {
            if (!rating10 || rating10 <= 0) return '';
            
            // Convert to 5-star scale and round to nearest 0.5
            const rating5 = Math.round((rating10 / 2) * 2) / 2;
            
            let starsHtml = '';
            for (let i = 1; i <= 5; i++) {
                if (rating5 >= i) {
                    // Full star
                    starsHtml += '<i class="bi bi-star-fill"></i>';
                } else if (rating5 >= i - 0.5) {
                    // Half star
                    starsHtml += '<i class="bi bi-star-half"></i>';
                } else {
                    // Empty star
                    starsHtml += '<i class="bi bi-star"></i>';
                }
            }
            return starsHtml;
        }

        // ===========================================
        // EXISTING FUNCTIONALITY
        // ===========================================
        
        // Load Stats
        async function loadStats() {
            try {
                const res = await fetch(`${API_URL}?action=stats`);
                const data = await res.json();
                if (data.success) {
                    document.getElementById('statTotal').textContent = data.stats.total || 0;
                    document.getElementById('statNowShowing').textContent = data.stats.now_showing || 0;
                    document.getElementById('statComingSoon').textContent = data.stats.coming_soon || 0;
                    document.getElementById('statAddedMonth').textContent = data.stats.added_this_month || 0;
                }
            } catch (e) {
                console.error('Stats error:', e);
            }
        }

        // Load Movies
        async function loadMovies() {
            try {
                const search = searchInput.value;
                const status = statusFilter.value || ''; // Ensure empty string for 'All Status'
                const url = `${API_URL}?action=list&search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}`;
                
                const res = await fetch(url);
                const data = await res.json();
                
                if (data.success && data.movies.length > 0) {
                    movieGrid.innerHTML = data.movies.map(movie => {
                        // Determine backdrop source
                        const hasBackdrop = movie.backdrop_url && movie.backdrop_url.length > 5;
                        const hasPoster = movie.poster_url && !movie.poster_url.includes('placeholder.com');
                        const gradientClass = `gradient-${(movie.id % 4) + 1}`;
                        
                        // Check if needs polish (quick-imported movie)
                        const needsPolish = 
                            movie.description === 'No description available.' || 
                            movie.director === 'Unknown' || 
                            movie.genre === 'Unclassified' ||
                            !hasPoster;
                        
                        // Build backdrop HTML
                        let backdropHtml = '';
                        if (hasBackdrop) {
                            backdropHtml = `<img class="movie-backdrop-img" src="${movie.backdrop_url}" alt="">`;
                        } else if (hasPoster) {
                            // Use blurred poster as fallback
                            backdropHtml = `<img class="movie-backdrop-img" src="${movie.poster_url}" alt="" style="filter: blur(3px) brightness(0.6); transform: scale(1.1);">`;
                        } else {
                            // Generated placeholder with title
                            const shortTitle = movie.title.length > 15 ? movie.title.substring(0, 15) + '...' : movie.title;
                            backdropHtml = `<div class="movie-backdrop-placeholder ${gradientClass}">${escapeHtml(shortTitle)}</div>`;
                        }
                        
                        // Get release year
                        const year = movie.release_date ? new Date(movie.release_date).getFullYear() : '';
                        
                        // Build director line (only if valid)
                        const showDirector = movie.director && movie.director !== 'Unknown' && movie.director.trim() !== '';
                        const directorHtml = showDirector 
                            ? `<div class="movie-meta-line director"><i class="bi bi-camera-reels"></i> ${escapeHtml(movie.director)}</div>` 
                            : '';
                        
                        // Build cast line (only if present, show first 3)
                        let castHtml = '';
                        if (movie.cast && movie.cast.trim() !== '') {
                            const castList = movie.cast.split(',').slice(0, 3).map(c => c.trim()).join(', ');
                            castHtml = `<div class="movie-meta-line cast"><i class="bi bi-people"></i> ${escapeHtml(castList)}</div>`;
                        }
                        
                        // Build genre/runtime tags (only if valid)
                        let tagsHtml = '<div class="movie-genre-runtime">';
                        if (movie.genre && movie.genre !== 'Unclassified') {
                            const mainGenre = movie.genre.split(',')[0].trim();
                            tagsHtml += `<span class="movie-genre-tag">${escapeHtml(mainGenre)}</span>`;
                        }
                        if (movie.runtime && movie.runtime > 0) {
                            tagsHtml += `<span class="movie-runtime-tag">${movie.runtime}m</span>`;
                        }
                        tagsHtml += '</div>';
                        
                        // Rating badge with 5-star display (only if > 0)
                        const rating = parseFloat(movie.rating || 0);
                        const starsHtml = renderStarRating(rating);
                        const ratingHtml = starsHtml 
                            ? `<div class="movie-rating-badge">${starsHtml}</div>` 
                            : '';
                        
                        // Needs polish badge
                        const polishHtml = needsPolish 
                            ? `<div class="needs-polish-badge" title="Click Edit to add poster, description & metadata"><i class="bi bi-info-circle"></i> Needs Polish</div>` 
                            : '';
                        
                        return `
                            <div class="movie-card" data-id="${movie.id}">
                                <div class="movie-backdrop">
                                    ${backdropHtml}
                                    ${ratingHtml}
                                    ${polishHtml}
                                    <div class="movie-poster-thumb">
                                        ${hasPoster 
                                            ? `<img src="${movie.poster_url}" alt="">`
                                            : `<i class="bi bi-film"></i>`
                                        }
                                    </div>
                                </div>
                                <div class="movie-info">
                                    <h4 title="${escapeHtml(movie.title)}">${escapeHtml(movie.title)}</h4>
                                    ${year ? `<div class="movie-year">${year}</div>` : ''}
                                    ${directorHtml}
                                    ${castHtml}
                                    ${tagsHtml}
                                </div>
                                <div class="movie-actions">
                                    <button class="btn-approve" onclick="editMovie(${movie.id})"><i class="bi bi-pencil"></i> Edit</button>
                                    <button class="btn-danger" onclick="deleteMovie(${movie.id}, '${escapeHtml(movie.title).replace(/'/g, "\\'")}')"><i class="bi bi-trash"></i> Delete</button>
                                </div>
                            </div>
                        `;
                    }).join('');
                } else {
                    movieGrid.innerHTML = `
                        <div class="empty-state">
                            <i class="bi bi-film"></i>
                            <p>No movies found</p>
                        </div>
                    `;
                }
            } catch (e) {
                console.error('Load movies error:', e);
                movieGrid.innerHTML = `<div class="empty-state"><i class="bi bi-exclamation-triangle"></i><p>Failed to load</p></div>`;
            }
        }

        // Add Movie
        form.onsubmit = async (e) => {
            e.preventDefault();
            const formData = new FormData(form);
            const data = Object.fromEntries(formData);
            
            if (!data.release_date && data.release_year) {
                data.release_date = `${data.release_year}-01-01`;
            }

            showLoading();
            try {
                const res = await fetch(`${API_URL}?action=add`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await res.json();
                
                if (result.success) {
                    closeModal();
                    form.reset();
                    clearAutoFill();
                    showAlert('Movie added successfully!', 'success');
                    loadMovies();
                    loadStats();
                } else {
                    showAlert(result.errors ? result.errors.join(', ') : result.error, 'error');
                }
            } catch (e) {
                showAlert('Failed to add movie', 'error');
            } finally {
                hideLoading();
            }
        };

        // Delete Movie
        async function deleteMovie(id, title) {
            if (!confirm(`Delete "${title}"?`)) return;
            
            showLoading();
            try {
                const res = await fetch(`${API_URL}?action=delete&id=${id}`, { method: 'DELETE' });
                const result = await res.json();
                
                if (result.success) {
                    showAlert('Movie deleted', 'success');
                    loadMovies();
                    loadStats();
                } else if (result.requires_confirmation) {
                    if (confirm(`This movie has dependencies: ${result.dependencies.join(', ')}. Delete anyway?`)) {
                        const forceRes = await fetch(`${API_URL}?action=delete&id=${id}&force=true`, { method: 'DELETE' });
                        const forceResult = await forceRes.json();
                        if (forceResult.success) {
                            showAlert('Movie and dependencies deleted', 'success');
                            loadMovies();
                            loadStats();
                        } else {
                            showAlert(forceResult.error, 'error');
                        }
                    }
                } else {
                    showAlert(result.error, 'error');
                }
            } catch (e) {
                showAlert('Delete failed', 'error');
            } finally {
                hideLoading();
            }
        }

        // Note: editMovie function is implemented in the bulk import section

        // Search
        let searchTimeout;
        searchInput.oninput = () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(loadMovies, 300);
        };
        statusFilter.onchange = loadMovies;

        // Sidebar Toggle
        function toggleSidebar() {
            document.querySelector('.admin-sidebar').classList.toggle('active');
        }

        // Init
        loadStats();
        loadMovies();

        // ===========================================
        // LETTERBOXD CSV IMPORT - INSTANT BULK IMPORT
        // ===========================================
        
        const IMPORT_API = '../api/letterboxd_import.php';
        const importModal = document.getElementById('importModal');
        const uploadZone = document.getElementById('uploadZone');
        const csvFileInput = document.getElementById('csvFileInput');
        const importStep1 = document.getElementById('importStep1');
        const importStep2 = document.getElementById('importStep2');
        const importAllBtn = document.getElementById('importAllBtn');
        const importPreviewList = document.getElementById('importPreviewList');
        const importProgress = document.getElementById('importProgress');
        const importSuccess = document.getElementById('importSuccess');
        
        let parsedMovies = [];
        let importResult = { created: 0, duplicates: 0 };
        
        // Open Import Modal
        document.getElementById('openImportModal').onclick = () => {
            importModal.classList.add('active');
            resetImport();
        };
        
        function closeImportModal() {
            importModal.classList.remove('active');
            // Always refresh to ensure consistency, even if viewing success screen
            console.log('Closing import modal, refreshing grid...');
            statusFilter.value = '';  // Reset to show all movies
            searchInput.value = '';
            loadMovies();
            loadStats();
        }
        
        importModal.onclick = (e) => {
            if (e.target === importModal) closeImportModal();
        };
        
        function resetImport() {
            importStep1.style.display = 'block';
            importStep2.style.display = 'none';
            importAllBtn.style.display = 'none';
            importPreviewList.innerHTML = '';
            importProgress.style.display = 'none';
            importSuccess.style.display = 'none';
            parsedMovies = [];
            importResult = { created: 0, duplicates: 0 };
        }
        
        // Upload Zone Events
        uploadZone.onclick = () => csvFileInput.click();
        
        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });
        
        uploadZone.addEventListener('dragleave', () => {
            uploadZone.classList.remove('dragover');
        });
        
        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
            const file = e.dataTransfer.files[0];
            if (file && file.name.endsWith('.csv')) {
                handleCSVUpload(file);
            } else {
                showAlert('Please upload a CSV file', 'error');
            }
        });
        
        csvFileInput.onchange = (e) => {
            const file = e.target.files[0];
            if (file) handleCSVUpload(file);
        };
        
        async function handleCSVUpload(file) {
            showLoading();
            console.log('Uploading CSV file:', file.name);
            
            const formData = new FormData();
            formData.append('csv', file);
            
            try {
                const res = await fetch(`${IMPORT_API}?action=parse`, {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                console.log('CSV parse response:', data);
                
                if (data.success && data.movies && data.movies.length > 0) {
                    parsedMovies = data.movies;
                    showImportPreview(data);
                } else if (data.success) {
                    showAlert(data.error || 'No valid movies found in CSV', 'error');
                } else {
                    showAlert(data.error || 'Failed to parse CSV', 'error');
                }
            } catch (e) {
                console.error('CSV upload error:', e);
                showAlert('Failed to upload CSV. Check browser console for details.', 'error');
            } finally {
                hideLoading();
            }
        }
        
        function showImportPreview(data) {
            console.log('Showing import preview with', data.movies.length, 'movies');
            importStep1.style.display = 'none';
            importStep2.style.display = 'block';
            importAllBtn.style.display = 'flex';
            importProgress.style.display = 'none';
            importSuccess.style.display = 'none';
            
            // Update summary
            const totalMovies = data.movies ? data.movies.length : data.total || 0;
            document.getElementById('previewSummary').textContent = `Ready to import ${totalMovies} movies`;
            
            // Show ratings info if available
            const ratingsSpan = document.getElementById('previewRatings');
            if (data.has_ratings && data.ratings_count > 0) {
                ratingsSpan.innerHTML = `<i class="bi bi-star-fill" style="color: #ffab00;"></i> ${data.ratings_count} with ratings`;
            } else {
                ratingsSpan.textContent = '';
            }
            
            // Render preview list (show first 50 for performance)
            const moviesToShow = data.movies || parsedMovies;
            const previewCount = Math.min(moviesToShow.length, 50);
            importPreviewList.innerHTML = moviesToShow.slice(0, previewCount).map((movie, i) => {
                // Build star rating for preview using same renderStarRating as final display
                const ratingStars = renderStarRating(movie.rating ? movie.rating * 2 : 0);
                const ratingDisplay = ratingStars ? `<span style="display: inline-flex; align-items: center; gap: 4px;">${ratingStars}</span>` : '';
                
                return `
                <div class="import-preview-item" style="display: flex; gap: 12px; padding: 10px; border-bottom: 1px solid var(--admin-border); align-items: center;">
                    <div style="flex: 1;">
                        <div style="font-weight: 500; margin-bottom: 4px;">${escapeHtml(movie.title)}</div>
                        <div style="font-size: 12px; color: var(--admin-text-muted); display: flex; align-items: center; gap: 12px;">
                            <span>${movie.release_year || 'Unknown Year'}</span>
                            ${ratingDisplay}
                        </div>
                    </div>
                </div>
            `}).join('');
            
            // Show "and X more" if truncated
            if (moviesToShow.length > 50) {
                importPreviewList.innerHTML += `
                    <div style="padding: 12px; text-align: center; color: var(--admin-text-muted); font-size: 13px;">
                        ...and ${moviesToShow.length - 50} more movies
                    </div>
                `;
            }
        }
        
        async function importAllMovies() {
            importAllBtn.disabled = true;
            importAllBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Importing...';
            importProgress.style.display = 'block';
            importPreviewList.style.display = 'none';
            
            console.log('Starting import of', parsedMovies.length, 'movies');
            
            // Animate progress (fake progress since bulk insert is fast)
            let progress = 0;
            const progressInterval = setInterval(() => {
                progress = Math.min(progress + 15, 90);
                document.getElementById('progressFill').style.width = progress + '%';
                document.getElementById('progressText').textContent = `${Math.floor(progress)}%`;
            }, 100);
            
            try {
                const res = await fetch(`${IMPORT_API}?action=bulk_import`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ movies: parsedMovies })
                });
                const data = await res.json();
                console.log('Import response:', data);
                
                clearInterval(progressInterval);
                document.getElementById('progressFill').style.width = '100%';
                document.getElementById('progressText').textContent = '100%';
                
                if (data.success) {
                    importResult = { created: data.created, duplicates: data.duplicates };
                    
                    // Show success state
                    setTimeout(() => {
                        importProgress.style.display = 'none';
                        importAllBtn.style.display = 'none';
                        importSuccess.style.display = 'block';
                        
                        const titleText = data.created > 0 
                            ? `✓ Successfully Imported ${data.created} Movie${data.created !== 1 ? 's' : ''}!`
                            : '✓ Import Complete';
                        document.getElementById('successTitle').textContent = titleText;
                        
                        let details = [];
                        if (data.created > 0) details.push(`<strong>${data.created}</strong> new movies added to your library`);
                        if (data.duplicates > 0) details.push(`<strong>${data.duplicates}</strong> already in library (skipped)`);
                        if (data.errors > 0) details.push(`<strong>${data.errors}</strong> entries couldn't be parsed`);
                        
                        const statsHtml = details.length > 0 ? details.join('<br>') : 'No new movies to import';
                        const nextStepsHtml = data.created > 0 ? `
                            <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid rgba(255,255,255,0.1);">
                                <p style="margin: 8px 0; font-size: 13px; color: #4CAF50;">✓ Movies are now in your library with placeholder metadata</p>
                                <p style="margin: 8px 0; font-size: 13px;">→ Click <strong>Edit</strong> on any movie card to add posters, descriptions & ratings</p>
                                <p style="margin: 8px 0; font-size: 13px;">💡 Look for the <strong>"Needs Polish"</strong> badge on incomplete movies</p>
                            </div>
                        ` : '';
                        
                        document.getElementById('successDetails').innerHTML = statsHtml + nextStepsHtml;
                        
                        showAlert(`${data.created} movie${data.created !== 1 ? 's' : ''} imported! Check your Movies list.`, 'success');
                        
                        // Refresh the movie grid to show newly imported movies
                        // Reset filters to "All Movies" to ensure newly imported (ended status) movies are visible
                        statusFilter.value = '';
                        searchInput.value = '';
                        
                        // Force immediate reload with logging
                        console.log('Refreshing movie grid after import with', data.created, 'new movies');
                        loadMovies();
                        loadStats();
                    }, 500);
                } else {
                    clearInterval(progressInterval);
                    console.error('Import failed:', data.error);
                    showAlert(data.error || 'Import failed', 'error');
                    importAllBtn.disabled = false;
                    importAllBtn.innerHTML = '<i class="bi bi-lightning-charge"></i> Import All Now';
                    importPreviewList.style.display = 'block';
                    importProgress.style.display = 'none';
                }
            } catch (e) {
                clearInterval(progressInterval);
                console.error('Import error:', e);
                showAlert('Failed to import movies: ' + e.message, 'error');
                importAllBtn.disabled = false;
                importAllBtn.innerHTML = '<i class="bi bi-lightning-charge"></i> Import All Now';
                importPreviewList.style.display = 'block';
                importProgress.style.display = 'none';
            }
        }
        
        // ===========================================
        // EDIT MOVIE FUNCTIONALITY
        // ===========================================
        
        let editingMovieId = null;
        
        async function editMovie(id) {
            showLoading();
            editingMovieId = id;
            
            try {
                const res = await fetch(`${API_URL}?action=get&id=${id}`);
                const data = await res.json();
                
                if (data.success && data.movie) {
                    populateEditForm(data.movie);
                    modal.classList.add('active');
                } else {
                    showAlert(data.error || 'Movie not found', 'error');
                }
            } catch (e) {
                console.error('Edit movie error:', e);
                showAlert('Failed to load movie', 'error');
            } finally {
                hideLoading();
            }
        }
        
        function populateEditForm(movie) {
            // Update modal header
            document.querySelector('#addMovieModal .modal-header h2').innerHTML = 
                '<i class="bi bi-pencil"></i> Edit Movie';
            document.getElementById('submitBtn').innerHTML = 
                '<i class="bi bi-check-lg"></i> Save Changes';
            
            // Populate form fields
            form.querySelector('[name="title"]').value = movie.title || '';
            form.querySelector('[name="release_year"]').value = movie.release_date ? new Date(movie.release_date).getFullYear() : '';
            form.querySelector('[name="release_date"]').value = movie.release_date || '';
            form.querySelector('[name="language"]').value = 'English'; // Default since not stored
            form.querySelector('[name="runtime"]').value = movie.runtime || 0;
            form.querySelector('[name="rating"]').value = movie.rating || 0;
            form.querySelector('[name="status"]').value = movie.status || 'ended';
            form.querySelector('[name="country"]').value = 'USA'; // Default since not stored
            form.querySelector('[name="poster_url"]').value = movie.poster_url || '';
            form.querySelector('[name="backdrop_url"]').value = movie.backdrop_url || '';
            form.querySelector('[name="description"]').value = movie.description || '';
            form.querySelector('[name="genre"]').value = movie.genre || '';
            form.querySelector('[name="director"]').value = movie.director || '';
            form.querySelector('[name="cast"]').value = ''; // Cast not stored in current schema
            form.querySelector('[name="tmdb_id"]').value = '';
            
            // Show/hide attribution based on whether this is a quick-imported movie
            const isQuickImport = movie.description === 'No description available.' || 
                                  movie.director === 'Unknown' ||
                                  movie.genre === 'Unclassified';
            if (isQuickImport) {
                tmdbAttribution.innerHTML = `
                    <i class="bi bi-pencil-square" style="color: #ff8000;"></i>
                    <div class="tmdb-attribution-text">
                        <strong style="color: #ff8000;">Needs Polish</strong>
                        <p>This movie was quick-imported. Add details below to complete it.</p>
                    </div>
                `;
                tmdbAttribution.classList.add('active');
            } else {
                tmdbAttribution.classList.remove('active');
            }
        }
        
        // Override form submit to handle both add and edit
        form.onsubmit = async (e) => {
            e.preventDefault();
            const formData = new FormData(form);
            const data = Object.fromEntries(formData);
            
            if (!data.release_date && data.release_year) {
                data.release_date = `${data.release_year}-01-01`;
            }

            showLoading();
            
            const isEdit = editingMovieId !== null;
            const action = isEdit ? 'update' : 'add';
            
            if (isEdit) {
                data.id = editingMovieId;
            }
            
            try {
                const res = await fetch(`${API_URL}?action=${action}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await res.json();
                
                if (result.success) {
                    closeModal();
                    form.reset();
                    clearAutoFill();
                    editingMovieId = null;
                    showAlert(isEdit ? 'Movie updated successfully!' : 'Movie added successfully!', 'success');
                    // Reset status filter to show all movies
                    statusFilter.value = '';
                    searchInput.value = '';
                    loadMovies();
                    loadStats();
                    
                    // Reset modal for next use
                    document.querySelector('#addMovieModal .modal-header h2').innerHTML = 
                        '<i class="bi bi-film"></i> Add Movie';
                    document.getElementById('submitBtn').innerHTML = 
                        '<i class="bi bi-plus-lg"></i> Add Movie';
                } else {
                    showAlert(result.errors ? result.errors.join(', ') : result.error, 'error');
                }
            } catch (e) {
                showAlert(isEdit ? 'Failed to update movie' : 'Failed to add movie', 'error');
            } finally {
                hideLoading();
            }
        };
        
        // Reset editing state when opening Add modal
        const origOpenAddModal = document.getElementById('openAddModal').onclick;
        document.getElementById('openAddModal').onclick = () => {
            editingMovieId = null;
            document.querySelector('#addMovieModal .modal-header h2').innerHTML = 
                '<i class="bi bi-film"></i> Add Movie';
            document.getElementById('submitBtn').innerHTML = 
                '<i class="bi bi-plus-lg"></i> Add Movie';
            modal.classList.add('active');
            form.reset();
            clearAutoFill();
            titleInput.focus();
        };
    </script>

</body>
</html>
