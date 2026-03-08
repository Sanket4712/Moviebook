<?php 
require_once '../includes/auth_check.php';
require_once '../includes/db.php';
require_once '../includes/movie_contract.php';

$user = getCurrentUser();
$userName = $user['name'] ?? 'User';
$userBio = $user['bio'] ?? '';
$userAvatar = $user['profile_pic'] ?? '';
$userId = $_SESSION['user_id'];

// Fetch user stats and data - all activity-driven from real user interactions
$stats = ['films' => 0, 'thisYear' => 0, 'lists' => 0, 'watchlist' => 0, 'likes' => 0];
$watchlist = [];
$diary = [];
$favorites = [];
$lists = [];
$allMovies = [];
$ratingDist = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

if ($pdo) {
    try {
        /**
         * STAT SEMANTICS (enforced rule):
         * - 'films' = UNIQUE movies logged (not total diary entries)
         * - Multiple diary entries for the same movie count as 1 film
         * - 'diaryEntries' = total diary entry count (for internal use)
         */
        
        // Films = unique movies logged (COUNT DISTINCT movie_id)
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT movie_id) FROM diary WHERE user_id = ?");
        $stmt->execute([$userId]);
        $stats['films'] = $stmt->fetchColumn();
        
        // Total diary entries (for JS to determine if stat needs update on delete)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM diary WHERE user_id = ?");
        $stmt->execute([$userId]);
        $stats['diaryEntries'] = $stmt->fetchColumn();
        
        // This year = unique movies watched this year
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT movie_id) FROM diary WHERE user_id = ? AND YEAR(watched_date) = ?");
        $stmt->execute([$userId, date('Y')]);
        $stats['thisYear'] = $stmt->fetchColumn();
        
        // Rating distribution for graph - FLOOR to group by star rating (1-5)
        // Rating in diary is 0.5-5.0 float, map to 1-5 stars
        $stmt = $pdo->prepare("
            SELECT FLOOR(rating) as star_rating, COUNT(*) as count 
            FROM diary 
            WHERE user_id = ? AND rating IS NOT NULL AND rating > 0 
            GROUP BY FLOOR(rating)
        ");
        $stmt->execute([$userId]);
        while ($row = $stmt->fetch()) {
            $star = max(1, min(5, (int)$row['star_rating'])); // Clamp to 1-5
            $ratingDist[$star] = (int)$row['count'];
        }
        
        // Watchlist
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM watchlist WHERE user_id = ?");
        $stmt->execute([$userId]);
        $stats['watchlist'] = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("
            SELECT w.movie_id, m.title, m.poster_url, m.release_date
            FROM watchlist w JOIN movies m ON w.movie_id = m.id
            WHERE w.user_id = ? ORDER BY w.added_at DESC LIMIT 24
        ");
        $stmt->execute([$userId]);
        $watchlist = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Favorites
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ?");
        $stmt->execute([$userId]);
        $stats['likes'] = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("
            SELECT f.movie_id, m.title, m.poster_url
            FROM favorites f JOIN movies m ON f.movie_id = m.id
            WHERE f.user_id = ? ORDER BY f.added_at DESC LIMIT 4
        ");
        $stmt->execute([$userId]);
        $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Diary entries
        $stmt = $pdo->prepare("
            SELECT d.id, d.movie_id, d.watched_date, d.rating, d.liked, d.review,
                   m.title, m.poster_url, m.release_date
            FROM diary d JOIN movies m ON d.movie_id = m.id
            WHERE d.user_id = ? ORDER BY d.watched_date DESC LIMIT 50
        ");
        $stmt->execute([$userId]);
        $diary = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // User lists with posters
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_lists WHERE user_id = ?");
        $stmt->execute([$userId]);
        $stats['lists'] = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("
            SELECT l.id, l.title, l.description
            FROM user_lists l WHERE l.user_id = ? ORDER BY l.created_at DESC
        ");
        $stmt->execute([$userId]);
        $lists = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($lists as &$list) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM list_items WHERE list_id = ?");
            $stmt->execute([$list['id']]);
            $list['film_count'] = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("
                SELECT m.poster_url FROM list_items li
                JOIN movies m ON li.movie_id = m.id
                WHERE li.list_id = ? ORDER BY li.position LIMIT 4
            ");
            $stmt->execute([$list['id']]);
            $list['posters'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        
        // All movies for search modal
        $stmt = $pdo->query("SELECT id, title, poster_url, release_date FROM movies ORDER BY title LIMIT 100");
        $allMovies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Profile error: " . $e->getMessage());
    }
}

// Group diary by month
$diaryGrouped = [];
foreach ($diary as $entry) {
    $month = date('F Y', strtotime($entry['watched_date']));
    $diaryGrouped[$month][] = $entry;
}

// Avatar URL
$avatarUrl = $userAvatar ?: 'https://ui-avatars.com/api/?name=' . urlencode($userName) . '&size=150&background=e50914&color=fff&bold=true';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($userName); ?> – MovieBook</title>
    <link rel="stylesheet" href="../assets/css/home.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --bg-dark: #0d0d0d;
            --bg-card: #141414;
            --bg-elevated: #1a1a1a;
            --accent: #e50914;
            --accent-hover: #ff1a1a;
            --text: #fff;
            --text-secondary: #b3b3b3;
            --text-muted: #666;
            --border: #2a2a2a;
            --rating: #f5c518;
        }
        
        * { box-sizing: border-box; }
        
        body {
            background: var(--bg-dark);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            min-height: 100vh;
        }
        
        /* ========== PROFILE CONTAINER ========== */
        .profile-page {
            max-width: 900px;
            margin: 0 auto;
            padding: 100px 20px 40px;
        }
        
        /* ========== PROFILE HEADER ========== */
        .profile-header {
            display: flex;
            align-items: center;
            gap: 24px;
            margin-bottom: 32px;
            padding: 24px;
            background: var(--bg-card);
            border-radius: 16px;
            position: relative;
        }
        
        .avatar-container {
            position: relative;
            flex-shrink: 0;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--accent);
        }
        
        .btn-edit-profile {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 32px;
            height: 32px;
            background: var(--accent);
            border: 2px solid var(--bg-card);
            border-radius: 50%;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            transition: transform 0.2s, background 0.2s;
        }
        
        .btn-edit-profile:hover {
            background: var(--accent-hover);
            transform: scale(1.1);
        }
        
        .profile-info {
            flex: 1;
        }
        
        .profile-username {
            font-size: 28px;
            font-weight: 700;
            margin: 0 0 4px;
        }
        
        .profile-bio {
            color: var(--text-secondary);
            margin: 0;
            font-size: 14px;
        }
        
        .profile-stats {
            display: flex;
            gap: 24px;
            margin-top: 16px;
        }
        
        .stat-box {
            text-align: center;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .stat-box:hover { transform: translateY(-2px); }
        
        .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: var(--text);
            display: block;
        }
        
        .stat-label {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* ========== TABS ========== */
        .tab-nav {
            display: flex;
            gap: 4px;
            background: var(--bg-elevated);
            padding: 4px;
            border-radius: 12px;
            margin-bottom: 24px;
            position: sticky;
            top: 80px;
            z-index: 100;
        }
        
        .tab-btn {
            flex: 1;
            padding: 12px 16px;
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .tab-btn:hover { color: var(--text); }
        
        .tab-btn.active {
            background: var(--accent);
            color: white;
        }
        
        .tab-panel {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        
        .tab-panel.active { display: block; }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* ========== SECTION STYLING ========== */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
        }
        
        .section-card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }
        
        /* ========== FAVORITES ========== */
        .favorites-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }
        
        .fav-slot {
            aspect-ratio: 2/3;
            border-radius: 8px;
            overflow: hidden;
            position: relative;
            background: var(--bg-elevated);
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .fav-slot:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.4);
        }
        
        .fav-slot img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .fav-slot.empty {
            border: 2px dashed var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            font-size: 28px;
        }
        
        .fav-slot.empty:hover {
            border-color: var(--accent);
            color: var(--accent);
        }
        
        .fav-remove {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 24px;
            height: 24px;
            background: rgba(0,0,0,0.8);
            border: none;
            border-radius: 50%;
            color: white;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }
        
        .fav-slot:hover .fav-remove { opacity: 1; }
        .fav-remove:hover { background: var(--accent); }
        
        /* ========== RATING GRAPH ========== */
        .rating-graph {
            display: flex;
            align-items: flex-end;
            gap: 8px;
            height: 100px;
            padding: 20px 0;
        }
        
        .rating-bar-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }
        
        .rating-bar {
            width: 100%;
            background: var(--accent);
            border-radius: 4px 4px 0 0;
            min-height: 4px;
            transition: height 0.6s ease;
        }
        
        .rating-label {
            font-size: 12px;
            color: var(--text-muted);
        }
        
        .rating-count {
            font-size: 11px;
            color: var(--text-secondary);
        }
        
        /* ========== STATS LIST ========== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        
        .stat-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 16px;
            background: var(--bg-elevated);
            border-radius: 8px;
        }
        
        .stat-item span:first-child { color: var(--text-secondary); }
        .stat-item span:last-child { font-weight: 600; }
        
        /* ========== DIARY ========== */
        .diary-month {
            margin-bottom: 24px;
        }
        
        .diary-month-title {
            font-size: 14px;
            color: var(--text-muted);
            margin: 0 0 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border);
        }
        
        .diary-entries {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .diary-entry {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: var(--bg-card);
            border-radius: 8px;
            transition: background 0.2s;
            cursor: pointer;
        }
        
        .diary-entry:hover { background: var(--bg-elevated); }
        
        .diary-poster {
            width: 45px;
            height: 68px;
            border-radius: 4px;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .diary-poster img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .diary-details {
            flex: 1;
            min-width: 0;
        }
        
        .diary-title {
            font-weight: 500;
            margin: 0 0 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .diary-year {
            font-size: 13px;
            color: var(--text-muted);
        }
        
        .diary-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }
        
        .diary-stars {
            display: flex;
            gap: 2px;
        }
        
        .diary-stars i {
            font-size: 12px;
            color: var(--rating);
        }
        
        .diary-heart { color: #ff4757; font-size: 14px; }
        
        .diary-date {
            font-size: 12px;
            color: var(--text-muted);
        }
        
        .diary-delete {
            width: 28px;
            height: 28px;
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.2s;
        }
        
        .diary-entry:hover .diary-delete { opacity: 1; }
        .diary-delete:hover { color: var(--accent); }
        
        /* ========== LISTS ========== */
        .lists-container {
            display: grid;
            gap: 16px;
        }
        
        .list-card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 16px;
            display: flex;
            gap: 16px;
            transition: transform 0.2s;
            cursor: pointer;
        }
        
        .list-card:hover { transform: translateX(4px); }
        
        .list-posters {
            display: flex;
            gap: 4px;
            flex-shrink: 0;
        }
        
        .list-poster-thumb {
            width: 50px;
            height: 75px;
            background: var(--bg-elevated);
            border-radius: 4px;
            overflow: hidden;
        }
        
        .list-poster-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .list-info { flex: 1; }
        
        .list-title {
            font-weight: 600;
            margin: 0 0 4px;
        }
        
        .list-count {
            font-size: 13px;
            color: var(--text-muted);
        }
        
        .list-actions {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
            opacity: 0;
            transition: opacity 0.2s;
        }
        
        .list-card:hover .list-actions { opacity: 1; }
        
        .list-btn {
            width: 32px;
            height: 32px;
            background: var(--bg-elevated);
            border: none;
            border-radius: 6px;
            color: var(--text-muted);
            cursor: pointer;
        }
        
        .list-btn:hover {
            background: var(--accent);
            color: white;
        }
        
        /* ========== WATCHLIST ========== */
        .watchlist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 12px;
        }
        
        .watchlist-item {
            aspect-ratio: 2/3;
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .watchlist-item:hover { transform: scale(1.05); }
        
        .watchlist-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .watchlist-remove {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 26px;
            height: 26px;
            background: rgba(0,0,0,0.8);
            border: none;
            border-radius: 50%;
            color: white;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.2s;
        }
        
        .watchlist-item:hover .watchlist-remove { opacity: 1; }
        .watchlist-remove:hover { background: var(--accent); }
        
        /* ========== CREATE BUTTON ========== */
        .btn-create {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: var(--accent);
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .btn-create:hover { background: var(--accent-hover); }
        
        /* ========== EMPTY STATES ========== */
        .empty-state {
            text-align: center;
            padding: 48px 20px;
            color: var(--text-muted);
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 12px;
            opacity: 0.3;
        }
        
        /* ========== MODAL ========== */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.85);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
            padding: 20px;
        }
        
        .modal-backdrop.show {
            opacity: 1;
            visibility: visible;
        }
        
        .modal-box {
            background: var(--bg-card);
            border-radius: 16px;
            width: 100%;
            max-width: 500px;
            max-height: 80vh;
            overflow: hidden;
            transform: translateY(20px);
            transition: transform 0.3s;
        }
        
        .modal-backdrop.show .modal-box { transform: translateY(0); }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
        }
        
        .modal-header h3 { margin: 0; font-size: 18px; }
        
        .modal-close {
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-size: 20px;
            cursor: pointer;
        }
        
        .modal-close:hover { color: var(--text); }
        
        .modal-body {
            padding: 20px;
            max-height: 60vh;
            overflow-y: auto;
        }
        
        .modal-footer {
            padding: 16px 20px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        /* ========== FORM INPUTS ========== */
        .form-group { margin-bottom: 16px; }
        
        .form-group label {
            display: block;
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 6px;
            text-transform: uppercase;
        }
        
        .form-input {
            width: 100%;
            padding: 12px;
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            font-size: 14px;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--accent);
        }
        
        /* ========== MOVIE SEARCH ========== */
        .movie-search-input {
            width: 100%;
            padding: 12px 16px 12px 40px;
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text);
            font-size: 15px;
            margin-bottom: 16px;
        }
        
        .movie-search-input:focus {
            outline: none;
            border-color: var(--accent);
        }
        
        .movie-results {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .movie-result {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .movie-result:hover { background: var(--bg-elevated); }
        
        .movie-result img {
            width: 40px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .movie-result-title { font-weight: 500; }
        .movie-result-year { color: var(--text-muted); font-size: 13px; }
        
        /* ========== AVATAR EDIT ========== */
        .avatar-edit {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
        }
        
        .avatar-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--accent);
        }
        
        .btn-upload-avatar {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            cursor: pointer;
        }
        
        .btn-upload-avatar:hover {
            border-color: var(--accent);
        }
        
        .btn-upload-avatar input { display: none; }
        
        /* ========== BUTTONS ========== */
        .btn-cancel {
            padding: 10px 20px;
            background: transparent;
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-secondary);
            cursor: pointer;
        }
        
        .btn-cancel:hover {
            border-color: var(--text);
            color: var(--text);
        }
        
        .btn-primary {
            padding: 10px 24px;
            background: var(--accent);
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            cursor: pointer;
        }
        
        .btn-primary:hover { background: var(--accent-hover); }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
        
        /* ========== RESPONSIVE ========== */
        @media (max-width: 600px) {
            .profile-header { flex-direction: column; text-align: center; }
            .profile-stats { justify-content: center; }
            .favorites-grid { grid-template-columns: repeat(4, 1fr); }
            .tab-btn { font-size: 12px; padding: 10px 8px; }
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
            <li><a href="films.php">Films</a></li>
            <li><a href="home.php">Tickets</a></li>
            <li><a href="profile.php" class="active">Profile</a></li>
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
    <a href="profile.php" class="bottom-nav-item active"><i class="bi bi-person-circle"></i><span>Profile</span></a>
</nav>

<main class="profile-page">
    <!-- Profile Header -->
    <header class="profile-header">
        <div class="avatar-container">
            <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="Avatar" class="profile-avatar" id="headerAvatar">
            <button class="btn-edit-profile" onclick="openEditModal()"><i class="bi bi-pencil"></i></button>
        </div>
        <div class="profile-info">
            <h1 class="profile-username" id="headerUsername"><?php echo htmlspecialchars($userName); ?></h1>
            <p class="profile-bio" id="headerBio"><?php echo htmlspecialchars($userBio ?: 'Film enthusiast'); ?></p>
            <div class="profile-stats">
                <div class="stat-box" onclick="switchTab('profile')">
                    <span class="stat-number" id="statFilms"><?php echo $stats['films']; ?></span>
                    <span class="stat-label">Films</span>
                </div>
                <div class="stat-box" onclick="switchTab('diary')">
                    <span class="stat-number"><?php echo $stats['thisYear']; ?></span>
                    <span class="stat-label">This Year</span>
                </div>
                <div class="stat-box" onclick="switchTab('lists')">
                    <span class="stat-number" id="statLists"><?php echo $stats['lists']; ?></span>
                    <span class="stat-label">Lists</span>
                </div>
                <div class="stat-box" onclick="switchTab('watchlist')">
                    <span class="stat-number" id="statWatchlist"><?php echo $stats['watchlist']; ?></span>
                    <span class="stat-label">Watchlist</span>
                </div>
            </div>
        </div>
    </header>

    <!-- Tabs -->
    <nav class="tab-nav">
        <button class="tab-btn active" data-tab="profile">Profile</button>
        <button class="tab-btn" data-tab="diary">Diary</button>
        <button class="tab-btn" data-tab="lists">Lists</button>
        <button class="tab-btn" data-tab="watchlist">Watchlist</button>
    </nav>

    <!-- PROFILE TAB -->
    <div class="tab-panel active" id="panel-profile">
        <!-- Favorites -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title">Favorite Films</h2>
            </div>
            <div class="favorites-grid" id="favoritesGrid">
                <?php for ($i = 0; $i < 4; $i++): ?>
                    <?php if (isset($favorites[$i])): $fav = $favorites[$i]; ?>
                        <div class="fav-slot" data-movie-id="<?php echo $fav['movie_id']; ?>">
                            <a href="movie-details.php?id=<?php echo $fav['movie_id']; ?>">
                                <img src="<?php echo htmlspecialchars($fav['poster_url']); ?>" alt="<?php echo htmlspecialchars($fav['title']); ?>">
                            </a>
                            <button class="fav-remove" onclick="event.preventDefault(); removeFavorite(<?php echo $fav['movie_id']; ?>, this)"><i class="bi bi-x"></i></button>
                        </div>
                    <?php else: ?>
                        <div class="fav-slot empty" onclick="openMovieSelector('favorite')"><i class="bi bi-plus"></i></div>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Rating Distribution -->
        <div class="section-card">
            <h2 class="section-title">Ratings</h2>
            <div class="rating-graph">
                <?php 
                $maxRating = max($ratingDist) ?: 1;
                for ($i = 1; $i <= 5; $i++): 
                    $height = ($ratingDist[$i] / $maxRating) * 80;
                ?>
                <div class="rating-bar-container">
                    <span class="rating-count"><?php echo $ratingDist[$i]; ?></span>
                    <div class="rating-bar" style="height: <?php echo max(4, $height); ?>px;"></div>
                    <span class="rating-label"><?php echo str_repeat('★', $i); ?></span>
                </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Stats -->
        <div class="section-card">
            <h2 class="section-title">Stats</h2>
            <div class="stats-grid">
                <div class="stat-item"><span>Films Watched</span><span><?php echo $stats['films']; ?></span></div>
                <div class="stat-item"><span>This Year</span><span><?php echo $stats['thisYear']; ?></span></div>
                <div class="stat-item"><span>Lists</span><span><?php echo $stats['lists']; ?></span></div>
                <div class="stat-item"><span>Watchlist</span><span><?php echo $stats['watchlist']; ?></span></div>
                <div class="stat-item"><span>Favorites</span><span><?php echo $stats['likes']; ?></span></div>
            </div>
        </div>
    </div>

    <!-- DIARY TAB -->
    <div class="tab-panel" id="panel-diary">
        <?php if (empty($diaryGrouped)): ?>
            <div class="empty-state">
                <i class="bi bi-journal-text"></i>
                <p>No diary entries yet</p>
            </div>
        <?php else: ?>
            <?php foreach ($diaryGrouped as $month => $entries): ?>
            <div class="diary-month">
                <h3 class="diary-month-title"><?php echo $month; ?></h3>
                <div class="diary-entries">
                    <?php foreach ($entries as $entry): ?>
                    <!-- data-movie-id for unique film check, data-rating for rating distribution refresh -->
                    <div class="diary-entry" 
                         data-movie-id="<?php echo $entry['movie_id']; ?>"
                         data-rating="<?php echo intval($entry['rating'] ?? 0); ?>"
                         onclick="location.href='movie-details.php?id=<?php echo $entry['movie_id']; ?>'">
                        <div class="diary-poster">
                            <img src="<?php echo htmlspecialchars($entry['poster_url']); ?>" alt="">
                        </div>
                        <div class="diary-details">
                            <h4 class="diary-title"><?php echo htmlspecialchars($entry['title']); ?></h4>
                            <span class="diary-year"><?php echo date('Y', strtotime($entry['release_date'] ?? '')); ?></span>
                        </div>
                        <div class="diary-meta">
                            <div class="diary-stars">
                                <?php for ($s = 1; $s <= 5; $s++): ?>
                                <i class="bi bi-star-fill" style="opacity: <?php echo $s <= ($entry['rating'] ?? 0) ? 1 : 0.2; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <?php if ($entry['liked']): ?><i class="bi bi-heart-fill diary-heart"></i><?php endif; ?>
                            <span class="diary-date"><?php echo date('M j', strtotime($entry['watched_date'])); ?></span>
                            <button class="diary-delete" onclick="event.stopPropagation(); deleteDiaryEntry(<?php echo $entry['id']; ?>, this)"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- LISTS TAB -->
    <div class="tab-panel" id="panel-lists">
        <div class="section-header" style="margin-bottom: 20px;">
            <h2 class="section-title">Your Lists</h2>
            <button class="btn-create" onclick="openListModal()"><i class="bi bi-plus"></i> New List</button>
        </div>
        <?php if (empty($lists)): ?>
            <div class="empty-state">
                <i class="bi bi-collection"></i>
                <p>No lists yet. Create your first one!</p>
            </div>
        <?php else: ?>
            <div class="lists-container">
                <?php foreach ($lists as $list): ?>
                <div class="list-card">
                    <div class="list-posters">
                        <?php for ($p = 0; $p < 3; $p++): ?>
                        <div class="list-poster-thumb">
                            <?php if (isset($list['posters'][$p])): ?>
                            <img src="<?php echo htmlspecialchars($list['posters'][$p]); ?>" alt="">
                            <?php endif; ?>
                        </div>
                        <?php endfor; ?>
                    </div>
                    <div class="list-info">
                        <h3 class="list-title"><?php echo htmlspecialchars($list['title']); ?></h3>
                        <span class="list-count"><?php echo $list['film_count']; ?> films</span>
                    </div>
                    <div class="list-actions">
                        <button class="list-btn" onclick="editList(<?php echo $list['id']; ?>, '<?php echo addslashes($list['title']); ?>', '<?php echo addslashes($list['description'] ?? ''); ?>')"><i class="bi bi-pencil"></i></button>
                        <button class="list-btn" onclick="deleteList(<?php echo $list['id']; ?>, this)"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- WATCHLIST TAB -->
    <div class="tab-panel" id="panel-watchlist">
        <?php if (empty($watchlist)): ?>
            <div class="empty-state">
                <i class="bi bi-bookmark"></i>
                <p>Your watchlist is empty</p>
            </div>
        <?php else: ?>
            <div class="watchlist-grid">
                <?php foreach ($watchlist as $item): ?>
                <div class="watchlist-item" data-movie-id="<?php echo $item['movie_id']; ?>">
                    <a href="movie-details.php?id=<?php echo $item['movie_id']; ?>">
                        <img src="<?php echo htmlspecialchars($item['poster_url']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>">
                    </a>
                    <button class="watchlist-remove" onclick="event.preventDefault(); removeFromWatchlist(<?php echo $item['movie_id']; ?>, this)"><i class="bi bi-x"></i></button>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Edit Profile Modal -->
<div class="modal-backdrop" id="editModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Edit Profile</h3>
            <button class="modal-close" onclick="closeModal('editModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <div class="avatar-edit">
                <img id="avatarPreview" src="<?php echo htmlspecialchars($avatarUrl); ?>" class="avatar-preview">
                <label class="btn-upload-avatar">
                    <i class="bi bi-camera"></i> Change Photo
                    <input type="file" id="avatarInput" accept="image/*" onchange="previewAvatar(this)">
                </label>
            </div>
            <div class="form-group">
                <label>Username</label>
                <input type="text" class="form-input" id="editUsername" value="<?php echo htmlspecialchars($userName); ?>">
            </div>
            <div class="form-group">
                <label>Bio</label>
                <textarea class="form-input" id="editBio" rows="3"><?php echo htmlspecialchars($userBio); ?></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="closeModal('editModal')">Cancel</button>
            <button class="btn-primary" onclick="saveProfile()">Save Changes</button>
        </div>
    </div>
</div>

<!-- Movie Selector Modal -->
<div class="modal-backdrop" id="movieSelectorModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Select a Film</h3>
            <button class="modal-close" onclick="closeModal('movieSelectorModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <input type="text" class="movie-search-input" id="movieSearch" placeholder="Search for a film..." oninput="filterMovies(this.value)">
            <div class="movie-results" id="movieResults">
                <?php foreach ($allMovies as $movie): ?>
                <div class="movie-result" data-movie-id="<?php echo $movie['id']; ?>" onclick="selectMovie(<?php echo $movie['id']; ?>, '<?php echo addslashes($movie['title']); ?>', '<?php echo addslashes($movie['poster_url'] ?? ''); ?>')">
                    <img src="<?php echo htmlspecialchars($movie['poster_url'] ?? ''); ?>" alt="">
                    <div>
                        <div class="movie-result-title"><?php echo htmlspecialchars($movie['title']); ?></div>
                        <div class="movie-result-year"><?php echo date('Y', strtotime($movie['release_date'] ?? '')); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- List Modal -->
<div class="modal-backdrop" id="listModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="listModalTitle">Create New List</h3>
            <button class="modal-close" onclick="closeModal('listModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="listId">
            <div class="form-group">
                <label>List Name</label>
                <input type="text" class="form-input" id="listTitle" placeholder="My favorite films...">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea class="form-input" id="listDescription" rows="2" placeholder="Optional description..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="closeModal('listModal')">Cancel</button>
            <button class="btn-primary" onclick="saveList()">Save</button>
        </div>
    </div>
</div>

<script>
const API_BASE = '../api';
let currentAction = null;

// ==================== TAB SWITCHING ====================
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const tab = btn.dataset.tab;
        switchTab(tab);
    });
});

function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    
    document.querySelector(`.tab-btn[data-tab="${tab}"]`)?.classList.add('active');
    document.getElementById('panel-' + tab)?.classList.add('active');
}

// ==================== MODALS ====================
function openModal(id) {
    document.getElementById(id).classList.add('show');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}

function openEditModal() {
    openModal('editModal');
}

function openMovieSelector(action) {
    currentAction = action;
    document.getElementById('movieSearch').value = '';
    filterMovies('');
    openModal('movieSelectorModal');
}

function openListModal(listId = null, title = '', description = '') {
    document.getElementById('listId').value = listId || '';
    document.getElementById('listTitle').value = title;
    document.getElementById('listDescription').value = description;
    document.getElementById('listModalTitle').textContent = listId ? 'Edit List' : 'Create New List';
    openModal('listModal');
}

function editList(id, title, description) {
    openListModal(id, title, description);
}

// Close on backdrop click
document.querySelectorAll('.modal-backdrop').forEach(modal => {
    modal.addEventListener('click', e => {
        if (e.target === modal) closeModal(modal.id);
    });
});

// ==================== PROFILE EDIT ====================
function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => document.getElementById('avatarPreview').src = e.target.result;
        reader.readAsDataURL(input.files[0]);
    }
}

async function saveProfile() {
    const username = document.getElementById('editUsername').value.trim();
    const bio = document.getElementById('editBio').value.trim();
    const avatarInput = document.getElementById('avatarInput');
    
    try {
        // Upload avatar if changed
        if (avatarInput.files && avatarInput.files[0]) {
            const formData = new FormData();
            formData.append('action', 'upload_avatar');
            formData.append('avatar', avatarInput.files[0]);
            const res = await fetch(`${API_BASE}/profile.php`, { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) document.getElementById('headerAvatar').src = data.avatar_url;
        }
        
        // Update username and bio
        const res = await fetch(`${API_BASE}/profile.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=update_all&username=${encodeURIComponent(username)}&bio=${encodeURIComponent(bio)}`
        });
        const data = await res.json();
        
        if (data.success) {
            document.getElementById('headerUsername').textContent = data.username;
            document.getElementById('headerBio').textContent = data.bio || 'Film enthusiast';
            closeModal('editModal');
            showNotification('Profile updated!', 'success');
        } else {
            showNotification(data.error || 'Failed to save', 'error');
        }
    } catch (error) {
        showNotification('Network error', 'error');
    }
}

// ==================== FAVORITES ====================
function filterMovies(query) {
    const results = document.querySelectorAll('.movie-result');
    const q = query.toLowerCase();
    results.forEach(r => {
        const title = r.querySelector('.movie-result-title').textContent.toLowerCase();
        r.style.display = title.includes(q) ? 'flex' : 'none';
    });
}

async function selectMovie(movieId, title, posterUrl) {
    if (currentAction === 'favorite') {
        try {
            const res = await fetch(`${API_BASE}/favorites.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=add&movie_id=${movieId}`
            });
            const data = await res.json();
            
            if (data.success) {
                closeModal('movieSelectorModal');
                showNotification('Added to favorites!', 'success');
                location.reload();
            } else {
                showNotification(data.error || 'Failed to add', 'error');
            }
        } catch (error) {
            showNotification('Network error', 'error');
        }
    }
}

async function removeFavorite(movieId, btn) {
    try {
        const res = await fetch(`${API_BASE}/favorites.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=remove&movie_id=${movieId}`
        });
        const data = await res.json();
        
        if (data.success) {
            const slot = btn.closest('.fav-slot');
            slot.innerHTML = '<i class="bi bi-plus"></i>';
            slot.className = 'fav-slot empty';
            slot.onclick = () => openMovieSelector('favorite');
            showNotification('Removed from favorites', 'success');
        }
    } catch (error) {
        showNotification('Failed to remove', 'error');
    }
}

// ==================== WATCHLIST ====================
async function removeFromWatchlist(movieId, btn) {
    try {
        const res = await fetch(`${API_BASE}/watchlist.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=remove&movie_id=${movieId}`
        });
        const data = await res.json();
        
        if (data.success) {
            const item = btn.closest('.watchlist-item');
            item.style.transform = 'scale(0)';
            item.style.opacity = '0';
            setTimeout(() => item.remove(), 300);
            document.getElementById('statWatchlist').textContent = parseInt(document.getElementById('statWatchlist').textContent) - 1;
            showNotification('Removed from watchlist', 'success');
        }
    } catch (error) {
        showNotification('Failed to remove', 'error');
    }
}

// ==================== DIARY ====================
/**
 * Delete a diary entry with proper stat synchronization.
 * 
 * STAT SEMANTICS (enforced by user-approved rule):
 * - Films stat = UNIQUE movies logged, not total entries
 * - We only decrement Films stat if this was the LAST entry for that movie
 * - Rating distribution must refresh (not left stale)
 * 
 * NOTE: Client-side stat updates are UI sync optimizations, not source of truth.
 * The server remains authoritative. These updates prevent visual staleness.
 */
async function deleteDiaryEntry(entryId, btn) {
    if (!confirm('Delete this diary entry?')) return;
    
    const entry = btn.closest('.diary-entry');
    const movieId = entry.dataset.movieId;
    const rating = parseInt(entry.dataset.rating) || 0;
    
    try {
        const res = await fetch(`${API_BASE}/diary.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=delete&entry_id=${entryId}`
        });
        const data = await res.json();
        
        if (data.success) {
            // Animate removal
            entry.style.opacity = '0';
            entry.style.transform = 'translateX(-20px)';
            setTimeout(() => entry.remove(), 300);
            
            // Check if there are other entries for this movie
            // (Films stat only decrements if this was the last entry for this movie)
            const remainingEntriesForMovie = document.querySelectorAll(`.diary-entry[data-movie-id="${movieId}"]`).length - 1;
            
            if (remainingEntriesForMovie <= 0) {
                // This was the last entry for this movie - decrement unique films count
                const filmsEl = document.getElementById('statFilms');
                if (filmsEl) {
                    filmsEl.textContent = Math.max(0, parseInt(filmsEl.textContent) - 1);
                }
            }
            
            // Rating distribution refresh: if this entry had a rating, 
            // we must reload the ratings section to prevent stale UI
            if (rating > 0) {
                refreshRatingSection();
            }
            
            showNotification('Diary entry deleted', 'success');
        }
    } catch (error) {
        showNotification('Failed to delete', 'error');
    }
}

/**
 * Refresh the rating distribution section by fetching fresh data from server.
 * This ensures ratings graph is never left stale after diary entry deletion.
 */
async function refreshRatingSection() {
    try {
        // Fetch the profile page and extract just the rating section
        const res = await fetch(window.location.href);
        const html = await res.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        
        const newRatingGraph = doc.querySelector('.rating-graph');
        const currentRatingGraph = document.querySelector('.rating-graph');
        
        if (newRatingGraph && currentRatingGraph) {
            currentRatingGraph.innerHTML = newRatingGraph.innerHTML;
            // Re-animate the bars
            currentRatingGraph.querySelectorAll('.rating-bar').forEach(bar => {
                const h = bar.style.height;
                bar.style.height = '4px';
                setTimeout(() => bar.style.height = h, 50);
            });
        }
    } catch (error) {
        console.log('Rating refresh failed, will sync on next page load');
    }
}

// ==================== LISTS ====================
async function saveList() {
    const listId = document.getElementById('listId').value;
    const title = document.getElementById('listTitle').value.trim();
    const description = document.getElementById('listDescription').value.trim();
    
    if (!title) {
        showNotification('List name required', 'error');
        return;
    }
    
    try {
        const action = listId ? 'update' : 'create';
        let body = `action=${action}&title=${encodeURIComponent(title)}&description=${encodeURIComponent(description)}`;
        if (listId) body += `&list_id=${listId}`;
        
        const res = await fetch(`${API_BASE}/lists.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        });
        const data = await res.json();
        
        if (data.success) {
            closeModal('listModal');
            showNotification(listId ? 'List updated!' : 'List created!', 'success');
            location.reload();
        } else {
            showNotification(data.error || 'Failed to save', 'error');
        }
    } catch (error) {
        showNotification('Network error', 'error');
    }
}

async function deleteList(listId, btn) {
    if (!confirm('Delete this list?')) return;
    
    try {
        const res = await fetch(`${API_BASE}/lists.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=delete&list_id=${listId}`
        });
        const data = await res.json();
        
        if (data.success) {
            const card = btn.closest('.list-card');
            card.style.opacity = '0';
            card.style.transform = 'translateX(-20px)';
            setTimeout(() => card.remove(), 300);
            document.getElementById('statLists').textContent = parseInt(document.getElementById('statLists').textContent) - 1;
            showNotification('List deleted', 'success');
        }
    } catch (error) {
        showNotification('Failed to delete', 'error');
    }
}

// ==================== NOTIFICATIONS ====================
function showNotification(message, type = 'info') {
    document.querySelectorAll('.notification').forEach(n => n.remove());
    
    const notif = document.createElement('div');
    notif.className = 'notification';
    notif.style.cssText = `
        position: fixed;
        top: 90px;
        right: 20px;
        background: ${type === 'success' ? '#00c030' : type === 'error' ? '#e50914' : '#333'};
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        font-size: 14px;
        z-index: 2000;
        transform: translateX(120%);
        transition: transform 0.3s ease;
    `;
    notif.textContent = message;
    document.body.appendChild(notif);
    
    setTimeout(() => notif.style.transform = 'translateX(0)', 10);
    setTimeout(() => {
        notif.style.transform = 'translateX(120%)';
        setTimeout(() => notif.remove(), 300);
    }, 3000);
}

// Init: Animate rating bars on load
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.rating-bar').forEach(bar => {
        const h = bar.style.height;
        bar.style.height = '4px';
        setTimeout(() => bar.style.height = h, 100);
    });
});
</script>

</body>
</html>
