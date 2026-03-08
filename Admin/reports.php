<?php
/**
 * Admin Reports
 * Report generation interface - no fake data, just action buttons.
 */
require_once '../includes/admin_check.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Admin Panel</title>
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
                <a href="analytics.php" class="nav-link">
                    <i class="bi bi-graph-up"></i>
                    <span>Analytics</span>
                </a>
                <a href="reports.php" class="nav-link active">
                    <i class="bi bi-file-earmark-text"></i>
                    <span>Reports</span>
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
                    <h1>Reports</h1>
                </div>
                <div class="topbar-right">
                    <div class="admin-profile">
                        <img src="https://ui-avatars.com/api/?name=Admin&size=40&background=e50914&color=fff" alt="Admin">
                        <span><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></span>
                    </div>
                </div>
            </header>

            <div class="dashboard-content">
                <!-- Generate Reports -->
                <section class="section-card">
                    <div class="card-header">
                        <h2><i class="bi bi-download"></i> Generate Reports</h2>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; padding: 20px;">
                        <button class="action-btn" onclick="alert('Report generation not yet implemented')">
                            <i class="bi bi-people"></i>
                            <span>User Report</span>
                        </button>
                        <button class="action-btn" onclick="alert('Report generation not yet implemented')">
                            <i class="bi bi-ticket"></i>
                            <span>Booking Report</span>
                        </button>
                        <button class="action-btn" onclick="alert('Report generation not yet implemented')">
                            <i class="bi bi-cash"></i>
                            <span>Revenue Report</span>
                        </button>
                        <button class="action-btn" onclick="alert('Report generation not yet implemented')">
                            <i class="bi bi-building"></i>
                            <span>Theater Report</span>
                        </button>
                        <button class="action-btn" onclick="alert('Report generation not yet implemented')">
                            <i class="bi bi-film"></i>
                            <span>Movie Report</span>
                        </button>
                    </div>
                </section>

                <!-- Generated Reports -->
                <section class="section-card">
                    <div class="card-header">
                        <h2><i class="bi bi-clock-history"></i> Generated Reports</h2>
                    </div>
                    <div class="card-body" style="text-align: center; padding: 48px;">
                        <i class="bi bi-file-earmark-text" style="font-size: 3rem; color: var(--admin-text-muted); margin-bottom: 16px; display: block;"></i>
                        <h3>No Reports Generated</h3>
                        <p style="color: var(--admin-text-muted);">
                            Click on a report type above to generate a new report.
                            Generated reports will appear here for download.
                        </p>
                    </div>
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
