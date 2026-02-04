<?php
/**
 * Admin Dashboard
 * Requires admin authentication. Handles empty/missing tables gracefully.
 */
require_once '../includes/admin_check.php';
require_once '../includes/db.php';

// Safe stat fetching - handle missing tables or connection issues
$userCount = 0;
$movieCount = 0;
$theaterCount = 0;
$bookingCount = 0;

if ($pdo) {
    try {
        $userCount = (int)($pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?: 0);
    } catch (PDOException $e) {
        error_log("Dashboard: users query failed - " . $e->getMessage());
    }
    
    try {
        $movieCount = (int)($pdo->query("SELECT COUNT(*) FROM movies")->fetchColumn() ?: 0);
    } catch (PDOException $e) {
        error_log("Dashboard: movies query failed - " . $e->getMessage());
    }
    
    try {
        $theaterCount = (int)($pdo->query("SELECT COUNT(*) FROM theaters WHERE status = 'active'")->fetchColumn() ?: 0);
    } catch (PDOException $e) {
        // theaters table may not exist
        $theaterCount = 0;
    }
    
    try {
        $bookingCount = (int)($pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn() ?: 0);
    } catch (PDOException $e) {
        // bookings table may not exist
        $bookingCount = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin Panel</title>
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
                <a href="dashboard.php" class="nav-link active">
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
                    <h1>Dashboard</h1>
                </div>
                <div class="topbar-right">
                    <div class="admin-profile">
                        <img src="https://ui-avatars.com/api/?name=Admin&size=40&background=e50914&color=fff" alt="Admin">
                        <span><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></span>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <div class="dashboard-content">
                <!-- Stats -->
                <section class="stats-section">
                    <div class="stat-card primary">
                        <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
                        <div class="stat-info">
                            <h3>Total Users</h3>
                            <p class="stat-number"><?= number_format($userCount) ?></p>
                        </div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-icon"><i class="bi bi-film"></i></div>
                        <div class="stat-info">
                            <h3>Movies</h3>
                            <p class="stat-number"><?= number_format($movieCount) ?></p>
                        </div>
                    </div>
                    <div class="stat-card warning">
                        <div class="stat-icon"><i class="bi bi-building"></i></div>
                        <div class="stat-info">
                            <h3>Theaters</h3>
                            <p class="stat-number"><?= number_format($theaterCount) ?></p>
                        </div>
                    </div>
                    <div class="stat-card danger">
                        <div class="stat-icon"><i class="bi bi-ticket-perforated-fill"></i></div>
                        <div class="stat-info">
                            <h3>Bookings</h3>
                            <p class="stat-number"><?= number_format($bookingCount) ?></p>
                        </div>
                    </div>
                </section>

                <!-- Two Column Layout -->
                <div class="two-column-section">
                    <!-- Quick Actions -->
                    <section class="section-card">
                        <div class="card-header">
                            <h2><i class="bi bi-lightning"></i> Quick Actions</h2>
                        </div>
                        <div class="quick-actions">
                            <a href="movies.php" class="action-btn">
                                <i class="bi bi-film"></i>
                                <span>Add Movie</span>
                            </a>
                            <a href="theaters.php" class="action-btn">
                                <i class="bi bi-building-add"></i>
                                <span>Theaters</span>
                            </a>
                            <a href="users.php" class="action-btn">
                                <i class="bi bi-person-gear"></i>
                                <span>Users</span>
                            </a>
                            <a href="settings.php" class="action-btn">
                                <i class="bi bi-gear"></i>
                                <span>Settings</span>
                            </a>
                        </div>
                    </section>

                    <!-- System Status -->
                    <section class="section-card">
                        <div class="card-header">
                            <h2><i class="bi bi-check-circle"></i> System Status</h2>
                        </div>
                        <div class="card-body">
                            <div class="activity-list">
                                <div class="activity-item">
                                    <div class="activity-icon <?= $pdo ? 'user' : 'warning' ?>">
                                        <i class="bi bi-database<?= $pdo ? '-check' : '-x' ?>"></i>
                                    </div>
                                    <div class="activity-details">
                                        <p><strong>Database</strong></p>
                                        <span><?= $pdo ? 'Connected' : 'Not connected' ?></span>
                                    </div>
                                </div>
                                <div class="activity-item">
                                    <div class="activity-icon booking">
                                        <i class="bi bi-shield-check"></i>
                                    </div>
                                    <div class="activity-details">
                                        <p><strong>Security</strong></p>
                                        <span>Admin authenticated</span>
                                    </div>
                                </div>
                                <div class="activity-item">
                                    <div class="activity-icon theater">
                                        <i class="bi bi-film"></i>
                                    </div>
                                    <div class="activity-details">
                                        <p><strong>Movies</strong></p>
                                        <span><?= $movieCount > 0 ? $movieCount . ' in database' : 'Ready to add' ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Empty State Note -->
                <?php if ($movieCount == 0): ?>
                <section class="section-card">
                    <div class="card-body" style="text-align: center; padding: 48px;">
                        <i class="bi bi-film" style="font-size: 3rem; color: var(--admin-text-muted); margin-bottom: 16px; display: block;"></i>
                        <h3 style="margin-bottom: 8px;">No Movies Yet</h3>
                        <p style="color: var(--admin-text-muted); margin-bottom: 24px;">Get started by adding your first movie to the database.</p>
                        <a href="movies.php" class="btn-approve" style="text-decoration: none;">
                            <i class="bi bi-plus-lg"></i> Add Your First Movie
                        </a>
                    </div>
                </section>
                <?php endif; ?>
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
