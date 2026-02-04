<?php require_once '../includes/auth_check.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Collections - MovieBook</title>
    <link rel="stylesheet" href="../assets/css/home.css">
    <link rel="stylesheet" href="../assets/css/collections.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <img src="../logo.png" alt="MOVIEBOOK" class="logo-img">
            </div>
            <ul class="nav-menu">
                <li><a href="films.php">Films</a></li>
                <li><a href="home.php">Tickets</a></li>
                <li><a href="profile.php">My Profile</a></li>
            </ul>
            <div class="nav-right">
                <div class="location">
                    <i class="bi bi-geo-alt-fill"></i>
                    <span>Kolhapur</span>
                    <i class="bi bi-chevron-down"></i>
                </div>
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" placeholder="Search for Movies">
                </div>
            </div>
        </div>
    </nav>

    <!-- Bottom Navigation (Mobile) -->
    <nav class="bottom-nav">
        <a href="films.php" class="bottom-nav-item">
            <i class="bi bi-film"></i>
            <span>Films</span>
        </a>
        <a href="home.php" class="bottom-nav-item">
            <i class="bi bi-ticket-perforated"></i>
            <span>Tickets</span>
        </a>
        <a href="profile.php" class="bottom-nav-item">
            <i class="bi bi-person-circle"></i>
            <span>Profile</span>
        </a>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1>My Collections</h1>
            <button class="btn-create-collection" onclick="createNewCollection()">
                <i class="bi bi-plus-lg"></i> Create New Collection
            </button>
        </div>

        <!-- Watch Later Collection -->
        <section class="collection-row">
            <div class="collection-header">
                <div class="collection-title-box">
                    <i class="bi bi-clock-fill"></i>
                    <h2>Watch Later</h2>
                    <span class="movie-count">12 movies</span>
                </div>
                <a href="#" class="see-all">See All <i class="bi bi-chevron-right"></i></a>
            </div>
            <div class="movies-scroll">
                <div class="movie-card-small">
                    <img src="../Theater/images/image.png" alt="Movie">
                    <h4>Movie Title 1</h4>
                </div>
                <div class="movie-card-small">
                    <img src="../Theater/images/image.png" alt="Movie">
                    <h4>Movie Title 2</h4>
                </div>
                <div class="movie-card-small">
                    <img src="../Theater/images/image.png" alt="Movie">
                    <h4>Movie Title 3</h4>
                </div>
                <div class="movie-card-small">
                    <img src="../Theater/images/image.png" alt="Movie">
                    <h4>Movie Title 4</h4>
                </div>
                <div class="movie-card-small">
                    <img src="../Theater/images/image.png" alt="Movie">
                    <h4>Movie Title 5</h4>
                </div>
                <div class="movie-card-small">
                    <img src="../Theater/images/image.png" alt="Movie">
                    <h4>Movie Title 6</h4>
                </div>
            </div>
        </section>

        <!-- My Favorites Collection -->
        <section class="collection-row">
            <div class="collection-header">
                <div class="collection-title-box">
                    <i class="bi bi-heart-fill"></i>
                    <h2>My Favorites</h2>
                    <span class="movie-count">28 movies</span>
                </div>
                <a href="#" class="see-all">See All <i class="bi bi-chevron-right"></i></a>
            </div>
            <div class="movies-scroll">
                <div class="movie-card-small">
                    <img src="../Theater/images/image.png" alt="Movie">
                    <h4>Favorite 1</h4>
                </div>
                <div class="movie-card-small">
                    <img src="../Theater/images/image.png" alt="Movie">
                    <h4>Favorite 2</h4>
                </div>
                <div class="movie-card-small">
                    <img src="../Theater/images/image.png" alt="Movie">
                    <h4>Favorite 3</h4>
                </div>
                <div class="movie-card-small">
                    <img src="../Theater/images/image.png" alt="Movie">
                    <h4>Favorite 4</h4>
                </div>
                <div class="movie-card-small">
                    <img src="../Theater/images/image.png" alt="Movie">
                    <h4>Favorite 5</h4>
                </div>
                <div class="movie-card-small">
                    <img src="../Theater/images/image.png" alt="Movie">
                    <h4>Favorite 6</h4>
                </div>
            </div>
        </section>

        <!-- Watched Collection -->
        <section class="collection-row">
            <div class="collection-header">
                <div class="collection-title-box">
                    <i class="bi bi-check-circle-fill"></i>
                    <h2>Watched</h2>
                    <span class="movie-count">156 movies</span>
                </div>
                <a href="#" class="see-all">See All <i class="bi bi-chevron-right"></i></a>
            </div>
            <div class="movies-scroll">
                <div class="movie-card-small">
                    <img src="../Theater/images/image.png" alt="Movie">
                    <h4>Watched 1</h4>
                </div>
                <div class="movie-card-small">
                    <img src="../Theater/images/image.png" alt="Movie">
                    <h4>Watched 2</h4>
                </div>
                <div class="movie-card-small">
                    <img src="../Theater/images/image.png" alt="Movie">
                    <h4>Watched 3</h4>
                </div>
                <div class="movie-card-small">
                    <img src="../Theater/images/image.png" alt="Movie">
                    <h4>Watched 4</h4>
                </div>
                <div class="movie-card-small">
                    <img src="../Theater/images/image.png" alt="Movie">
                    <h4>Watched 5</h4>
                </div>
                <div class="movie-card-small">
                    <img src="../Theater/images/image.png" alt="Movie">
                    <h4>Watched 6</h4>
                </div>
            </div>
        </section>

        <!-- Action Classics Collection -->
        <!-- REMOVED -->

        <!-- Comedy Gold Collection -->
        <!-- REMOVED -->
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>🎬 MOVIEBOOK</h3>
                <p>Your one-stop destination for movie tickets and reviews</p>
            </div>
            <div class="footer-section">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="#">About Us</a></li>
                    <li><a href="#">Contact</a></li>
                    <li><a href="#">Terms & Conditions</a></li>
                    <li><a href="#">Privacy Policy</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h4>Follow Us</h4>
                <div class="social-icons">
                    <a href="#"><i class="bi bi-facebook"></i></a>
                    <a href="#"><i class="bi bi-twitter"></i></a>
                    <a href="#"><i class="bi bi-instagram"></i></a>
                    <a href="#"><i class="bi bi-youtube"></i></a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 MovieBook. All rights reserved.</p>
        </div>
    </footer>

    <script src="../assets/js/collections.js"></script>
</body>
</html>
