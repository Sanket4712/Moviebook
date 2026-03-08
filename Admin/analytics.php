<?php
/**
 * Admin Analytics
 * Shows real analytics data from database with proper empty states.
 */
require_once '../includes/admin_check.php';
require_once '../includes/db.php';

// Fetch real analytics data
$userGrowth = 0;
$revenueGrowth = 0;
$cityData = [];

if ($pdo) {
    try {
        // User growth: compare this month vs last month
        $thisMonthUsers = (int)($pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn() ?: 0);
        $lastMonthUsers = (int)($pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01') AND created_at < DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn() ?: 0);
        if ($lastMonthUsers > 0) {
            $userGrowth = round((($thisMonthUsers - $lastMonthUsers) / $lastMonthUsers) * 100, 1);
        }
        
        // Check if bookings table exists for revenue
        $stmt = $pdo->query("SHOW TABLES LIKE 'bookings'");
        if ($stmt->rowCount() > 0) {
            // Revenue growth: compare this month vs last month
            $thisMonthRevenue = (float)($pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE status = 'confirmed' AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn() ?: 0);
            $lastMonthRevenue = (float)($pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE status = 'confirmed' AND created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01') AND created_at < DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn() ?: 0);
            if ($lastMonthRevenue > 0) {
                $revenueGrowth = round((($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1);
            }
        }
        
        // Check if theaters table has city column
        $stmt = $pdo->query("SHOW TABLES LIKE 'theaters'");
        if ($stmt->rowCount() > 0) {
            try {
                $stmt = $pdo->query("
                    SELECT t.city, COUNT(DISTINCT t.id) as theaters,
                           COUNT(DISTINCT u.id) as users,
                           COALESCE(SUM(b.total_amount), 0) as revenue
                    FROM theaters t
                    LEFT JOIN users u ON 1=1
                    LEFT JOIN bookings b ON b.theater_id = t.id AND b.status = 'confirmed'
                    WHERE t.city IS NOT NULL AND t.city != ''
                    GROUP BY t.city
                    ORDER BY revenue DESC
                    LIMIT 5
                ");
                $cityData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                // City column might not exist, that's ok
            }
        }
        
    } catch (PDOException $e) {
        error_log("Admin analytics error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
                <a href="movies.php" class="nav-link">
                    <i class="bi bi-film"></i>
                    <span>Movies</span>
                </a>
                <a href="bookings.php" class="nav-link">
                    <i class="bi bi-ticket-perforated"></i>
                    <span>Bookings</span>
                </a>
                <a href="analytics.php" class="nav-link active">
                    <i class="bi bi-graph-up"></i>
                    <span>Analytics</span>
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
            <header class="admin-topbar">
                <div class="topbar-left">
                    <button class="menu-toggle" onclick="toggleSidebar()">
                        <i class="bi bi-list"></i>
                    </button>
                    <h1>Analytics</h1>
                </div>
                <div class="topbar-right">
                    <div class="admin-profile">
                        <img src="https://ui-avatars.com/api/?name=Admin&size=40&background=e50914&color=fff" alt="Admin">
                        <span><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></span>
                    </div>
                </div>
            </header>

            <div class="dashboard-content">
                <!-- Growth Metrics -->
                <section class="section-card">
                    <div class="card-header">
                        <h2><i class="bi bi-graph-up-arrow"></i> Growth Metrics</h2>
                        <span style="color: var(--admin-text-muted); font-size: 0.9rem;">Month over Month</span>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; padding: 20px;">
                        <div style="background: var(--admin-gray); padding: 24px; border-radius: 12px; text-align: center;">
                            <i class="bi bi-people" style="font-size: 2.5rem; color: var(--admin-success);"></i>
                            <h3 style="margin-top: 12px; font-size: 2rem;">
                                <?= $userGrowth >= 0 ? '+' : '' ?><?= $userGrowth ?>%
                            </h3>
                            <p style="color: var(--admin-text-muted);">User Growth</p>
                        </div>
                        <div style="background: var(--admin-gray); padding: 24px; border-radius: 12px; text-align: center;">
                            <i class="bi bi-cash-coin" style="font-size: 2.5rem; color: var(--admin-warning);"></i>
                            <h3 style="margin-top: 12px; font-size: 2rem;">
                                <?= $revenueGrowth >= 0 ? '+' : '' ?><?= $revenueGrowth ?>%
                            </h3>
                            <p style="color: var(--admin-text-muted);">Revenue Growth</p>
                        </div>
                    </div>
                </section>

                <!-- Geographic Distribution -->
                <section class="section-card">
                    <div class="card-header">
                        <h2><i class="bi bi-geo-alt"></i> Geographic Distribution</h2>
                    </div>
                    
                    <?php if (empty($cityData)): ?>
                    <div class="card-body" style="text-align: center; padding: 48px;">
                        <i class="bi bi-geo-alt" style="font-size: 3rem; color: var(--admin-text-muted); margin-bottom: 16px; display: block;"></i>
                        <h3>No Location Data</h3>
                        <p style="color: var(--admin-text-muted);">
                            Geographic data will appear here once theaters are registered with city information.
                        </p>
                    </div>
                    <?php else: ?>
                    <div class="top-movies">
                        <?php foreach ($cityData as $i => $city): 
                            $rankClass = $i === 0 ? 'gold' : ($i === 1 ? 'silver' : ($i === 2 ? 'bronze' : ''));
                        ?>
                        <div class="movie-rank-item">
                            <span class="rank <?= $rankClass ?>"><?= $i + 1 ?></span>
                            <div class="movie-rank-info">
                                <h4><?= htmlspecialchars($city['city']) ?></h4>
                                <p><?= number_format($city['theaters']) ?> theaters</p>
                            </div>
                            <span class="revenue-badge">₹<?= number_format($city['revenue']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            document.querySelector('.admin-sidebar').classList.toggle('active');
        }
    </script>
</body>
</html>
