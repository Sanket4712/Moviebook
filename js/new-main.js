// API Base URL
const API_BASE = './api';
const TMDB_IMAGE_BASE = 'https://image.tmdb.org/t/p';

// User State
let currentUser = null;
let isLoggedIn = false;

// Local Storage Keys (Fallback for non-logged in users)
const STORAGE_KEYS = {
    WATCHLIST: 'moviebook_watchlist',
    FAVORITES: 'moviebook_favorites',
    INTERESTED: 'moviebook_interested'
};

// Initialize page
document.addEventListener('DOMContentLoaded', () => {
    checkUserSession();
    initializeAuth();
    initializeNavigation();
    loadHeroMovies();
    loadStreamingContent();
    loadTheaterMovies();
    loadLibraryContent();
    initializeSearch();
    initializeModal();
});

// Authentication System
async function checkUserSession() {
    try {
        const response = await fetch(`${API_BASE}/auth.php?action=check_session`);
        const data = await response.json();
        
        if (data.success && data.logged_in) {
            currentUser = data.user;
            isLoggedIn = true;
            updateUIForLoggedInUser();
        } else {
            updateUIForGuest();
        }
    } catch (error) {
        console.error('Session check error:', error);
        updateUIForGuest();
    }
}

function updateUIForLoggedInUser() {
    document.getElementById('loginBtn').style.display = 'none';
    document.getElementById('userMenu').style.display = 'block';
    document.getElementById('usernameDisplay').textContent = currentUser.username;
}

function updateUIForGuest() {
    document.getElementById('loginBtn').style.display = 'block';
    document.getElementById('userMenu').style.display = 'none';
    isLoggedIn = false;
    currentUser = null;
}

function initializeAuth() {
    // Login button
    document.getElementById('loginBtn').addEventListener('click', (e) => {
        e.preventDefault();
        openModal('loginModal');
    });
    
    // Logout button
    document.getElementById('logoutBtn').addEventListener('click', async (e) => {
        e.preventDefault();
        await logoutUser();
    });
    
    // Profile button
    document.getElementById('profileBtn').addEventListener('click', (e) => {
        e.preventDefault();
        showProfile();
    });
    
    // Login form
    document.getElementById('loginForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        await handleLogin();
    });
    
    // Signup form
    document.getElementById('signupForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        await handleSignup();
    });
    
    // Switch between login/signup
    document.getElementById('showSignup').addEventListener('click', (e) => {
        e.preventDefault();
        closeModal('loginModal');
        openModal('signupModal');
    });
    
    document.getElementById('showLogin').addEventListener('click', (e) => {
        e.preventDefault();
        closeModal('signupModal');
        openModal('loginModal');
    });
    
    // Modal close buttons
    document.querySelectorAll('.modal-close').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.modal').style.display = 'none';
        });
    });
    
    // Close modal on outside click
    window.addEventListener('click', (e) => {
        if (e.target.classList.contains('modal')) {
            e.target.style.display = 'none';
        }
    });
}

async function handleLogin() {
    const email = document.getElementById('loginEmail').value;
    const password = document.getElementById('loginPassword').value;
    
    try {
        const response = await fetch(`${API_BASE}/auth.php?action=login`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });
        
        const data = await response.json();
        
        if (data.success) {
            currentUser = data.user;
            isLoggedIn = true;
            updateUIForLoggedInUser();
            closeModal('loginModal');
            showNotification('Welcome back, ' + currentUser.username + '!', 'success');
            loadLibraryContent(); // Reload library from database
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        console.error('Login error:', error);
        showNotification('Login failed. Please try again.', 'error');
    }
}

async function handleSignup() {
    const username = document.getElementById('signupUsername').value;
    const email = document.getElementById('signupEmail').value;
    const phone = document.getElementById('signupPhone').value;
    const password = document.getElementById('signupPassword').value;
    const confirmPassword = document.getElementById('signupConfirmPassword').value;
    
    if (password !== confirmPassword) {
        showNotification('Passwords do not match', 'error');
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}/auth.php?action=register`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, email, phone, password })
        });
        
        const data = await response.json();
        
        if (data.success) {
            currentUser = data.user;
            isLoggedIn = true;
            updateUIForLoggedInUser();
            closeModal('signupModal');
            showNotification('Account created successfully!', 'success');
            loadLibraryContent();
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        console.error('Signup error:', error);
        showNotification('Signup failed. Please try again.', 'error');
    }
}

async function logoutUser() {
    try {
        await fetch(`${API_BASE}/auth.php?action=logout`);
        updateUIForGuest();
        showNotification('Logged out successfully', 'success');
        loadLibraryContent(); // Clear library
    } catch (error) {
        console.error('Logout error:', error);
    }
}

async function showProfile() {
    if (!isLoggedIn) {
        openModal('loginModal');
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}/auth.php?action=get_profile`);
        const data = await response.json();
        
        if (data.success) {
            const user = data.user;
            
            // Get library counts
            const watchlistCount = await getLibraryCount('watchlist');
            const favoritesCount = await getLibraryCount('favorites');
            const interestedCount = await getLibraryCount('interest');
            
            document.getElementById('profileContent').innerHTML = `
                <div class="profile-info">
                    <div class="profile-field">
                        <label>Username</label>
                        <span>${user.username}</span>
                    </div>
                    <div class="profile-field">
                        <label>Email</label>
                        <span>${user.email}</span>
                    </div>
                    <div class="profile-field">
                        <label>Phone</label>
                        <span>${user.phone}</span>
                    </div>
                    <div class="profile-field">
                        <label>Member Since</label>
                        <span>${new Date(user.created_at).toLocaleDateString()}</span>
                    </div>
                </div>
                
                <div class="profile-stats">
                    <div class="stat-card">
                        <h3>${watchlistCount}</h3>
                        <p>Watchlist</p>
                    </div>
                    <div class="stat-card">
                        <h3>${favoritesCount}</h3>
                        <p>Favorites</p>
                    </div>
                    <div class="stat-card">
                        <h3>${interestedCount}</h3>
                        <p>Interest</p>
                    </div>
                </div>
            `;
            
            openModal('profileModal');
        }
    } catch (error) {
        console.error('Profile error:', error);
        showNotification('Failed to load profile', 'error');
    }
}

async function getLibraryCount(type) {
    if (!isLoggedIn) return 0;
    
    try {
        const response = await fetch(`${API_BASE}/library.php?action=get_library&type=${type}`);
        const data = await response.json();
        return data.success ? data.items.length : 0;
    } catch (error) {
        return 0;
    }
}

function openModal(modalId) {
    document.getElementById(modalId).style.display = 'flex';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        <span>${message}</span>
    `;
    
    document.body.appendChild(notification);
    
    // Show notification
    setTimeout(() => notification.classList.add('show'), 100);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Navigation System
function initializeNavigation() {
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            if (link.classList.contains('admin-link')) return;
            
            e.preventDefault();
            const section = link.dataset.section;
            switchSection(section);
            
            // Update active nav link
            navLinks.forEach(l => l.classList.remove('active'));
            link.classList.add('active');
        });
    });
    
    // Library tabs
    const libraryTabs = document.querySelectorAll('.library-tab');
    libraryTabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const tabName = tab.dataset.tab;
            switchLibraryTab(tabName);
            
            libraryTabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
        });
    });
}

function switchSection(sectionName) {
    const sections = document.querySelectorAll('.content-section');
    sections.forEach(section => section.classList.remove('active'));
    
    const targetSection = document.getElementById(`${sectionName}-section`);
    if (targetSection) {
        targetSection.classList.add('active');
    }
}

function switchLibraryTab(tabName) {
    const contents = document.querySelectorAll('.library-content');
    contents.forEach(content => content.classList.remove('active'));
    
    const targetContent = document.getElementById(`${tabName}-content`);
    if (targetContent) {
        targetContent.classList.add('active');
    }
}

// Load Hero Movies
async function loadHeroMovies() {
    try {
        const response = await fetch(`${API_BASE}/movies.php?action=trending`);
        const text = await response.text();
        const data = text ? JSON.parse(text) : { results: [] };
        
        if (data.results && data.results.length > 0) {
            const heroMovie = data.results[0];
            const heroSlider = document.getElementById('heroSlider');
            if (heroMovie.backdrop_path) {
                heroSlider.style.backgroundImage = `linear-gradient(135deg, rgba(102, 126, 234, 0.4), rgba(118, 75, 162, 0.4)), url('${TMDB_IMAGE_BASE}/original${heroMovie.backdrop_path}')`;
            }
        }
    } catch (error) {
        console.error('Error loading hero movies:', error);
    }
}

// Load Streaming Content
async function loadStreamingContent() {
    await Promise.all([
        loadOurPicks(),
        loadNewOnStreaming(),
        loadTrendingMovies()
    ]);
}

async function loadOurPicks() {
    const grid = document.getElementById('ourPicksMovies');
    
    try {
        const response = await fetch(`${API_BASE}/movies.php?action=our_picks`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const text = await response.text();
        console.log('Our Picks Response:', text);
        
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('JSON Parse Error:', e, 'Response:', text);
            grid.innerHTML = '<p class="loading">No picks available yet</p>';
            return;
        }
        
        if (data.results && data.results.length > 0) {
            grid.innerHTML = '';
            data.results.forEach(movie => {
                const card = createSimpleStreamingCard(movie);
                grid.appendChild(card);
            });
        } else {
            grid.innerHTML = '<p class="loading">No picks available yet</p>';
        }
    } catch (error) {
        console.error('Error loading our picks:', error);
        grid.innerHTML = '<p class="loading">No picks available yet</p>';
    }
}

async function loadNewOnStreaming() {
    const grid = document.getElementById('newOnStreamingMovies');
    
    try {
        const response = await fetch(`${API_BASE}/movies.php?action=now_playing`);
        const text = await response.text();
        const data = text ? JSON.parse(text) : { results: [] };
        
        if (data.results && data.results.length > 0) {
            grid.innerHTML = '';
            data.results.slice(0, 12).forEach(movie => {
                const card = createSimpleStreamingCard(movie);
                grid.appendChild(card);
            });
        } else {
            grid.innerHTML = '<p class="loading">No new releases available</p>';
        }
    } catch (error) {
        console.error('Error loading new on streaming:', error);
        grid.innerHTML = '<p class="loading">No new releases available</p>';
    }
}

async function loadTrendingMovies() {
    const grid = document.getElementById('trendingMovies');
    
    try {
        const response = await fetch(`${API_BASE}/movies.php?action=trending`);
        const text = await response.text();
        const data = text ? JSON.parse(text) : { results: [] };
        
        if (data.results && data.results.length > 0) {
            grid.innerHTML = '';
            data.results.slice(0, 12).forEach(movie => {
                const card = createSimpleStreamingCard(movie);
                grid.appendChild(card);
            });
        } else {
            grid.innerHTML = '<p class="loading">No trending movies available</p>';
        }
    } catch (error) {
        console.error('Error loading trending:', error);
        grid.innerHTML = '<p class="loading">No trending movies available</p>';
    }
}

async function loadPopularMovies() {
    const grid = document.getElementById('popularMovies');
    
    try {
        const response = await fetch(`${API_BASE}/movies.php?action=popular`);
        const data = await response.json();
        
        if (data.results && data.results.length > 0) {
            grid.innerHTML = '';
            data.results.slice(0, 6).forEach(movie => {
                const card = createStreamingCard(movie);
                grid.appendChild(card);
            });
        } else {
            grid.innerHTML = '<p class="loading">No popular movies available</p>';
        }
    } catch (error) {
        console.error('Error loading popular:', error);
        grid.innerHTML = '<p class="loading">Error loading movies</p>';
    }
}

async function loadTopRatedMovies() {
    const grid = document.getElementById('topRatedMovies');
    
    try {
        const response = await fetch(`${API_BASE}/movies.php?action=top_rated`);
        const data = await response.json();
        
        if (data.results && data.results.length > 0) {
            grid.innerHTML = '';
            data.results.slice(0, 6).forEach(movie => {
                const card = createStreamingCard(movie);
                grid.appendChild(card);
            });
        } else {
            grid.innerHTML = '<p class="loading">No top rated movies available</p>';
        }
    } catch (error) {
        console.error('Error loading top rated:', error);
        grid.innerHTML = '<p class="loading">Error loading movies</p>';
    }
}

// Load Theater Movies
async function loadTheaterMovies() {
    await Promise.all([
        loadNowShowing(),
        loadComingSoon()
    ]);
}

async function loadNowShowing() {
    const grid = document.getElementById('nowShowingGrid');
    
    try {
        const response = await fetch(`${API_BASE}/movies.php?action=now_showing`);
        const data = await response.json();
        
        if (data.results && data.results.length > 0) {
            grid.innerHTML = '';
            data.results.forEach(movie => {
                const card = createTheaterCard(movie);
                grid.appendChild(card);
            });
        } else {
            grid.innerHTML = '<p class="loading">No movies currently showing in theaters</p>';
        }
    } catch (error) {
        console.error('Error loading now showing:', error);
        grid.innerHTML = '<p class="loading">Error loading theater movies</p>';
    }
}

async function loadComingSoon() {
    const grid = document.getElementById('comingSoonGrid');
    
    try {
        const response = await fetch(`${API_BASE}/movies.php?action=upcoming`);
        const text = await response.text();
        const data = text ? JSON.parse(text) : { results: [] };
        
        if (data.results && data.results.length > 0) {
            grid.innerHTML = '';
            data.results.slice(0, 6).forEach(movie => {
                const card = createComingSoonCard(movie);
                grid.appendChild(card);
            });
        } else {
            grid.innerHTML = '<p class="loading">No upcoming movies</p>';
        }
    } catch (error) {
        console.error('Error loading coming soon:', error);
        grid.innerHTML = '<p class="loading">No upcoming movies</p>';
    }
}

// Create Simple Streaming Movie Card (Only Poster & Title)
function createSimpleStreamingCard(movie) {
    const card = document.createElement('div');
    card.className = 'movie-card simple-card';
    
    const posterPath = movie.poster_path;
    const posterUrl = posterPath ? `${TMDB_IMAGE_BASE}/w500${posterPath}` : 'https://via.placeholder.com/500x750?text=No+Poster';
    
    card.innerHTML = `
        <div class="simple-card-poster">
            <img src="${posterUrl}" alt="${escapeHtml(movie.title)}" class="movie-poster">
            <div class="simple-card-overlay">
                <button class="quick-add-btn" onclick="event.stopPropagation(); addToWatchlist(${movie.id || movie.tmdb_id}, '${escapeHtml(movie.title)}', '${posterPath}', ${movie.vote_average || movie.rating || 0})" title="Add to Watchlist">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
        </div>
        <div class="simple-card-title">
            <h3>${escapeHtml(movie.title)}</h3>
        </div>
    `;
    
    card.addEventListener('click', (e) => {
        if (!e.target.closest('.quick-add-btn')) {
            showMovieModal(movie, getRandomPlatforms());
        }
    });
    
    return card;
}

// Create Streaming Movie Card
function createStreamingCard(movie) {
    const card = document.createElement('div');
    card.className = 'movie-card';
    
    const posterPath = movie.poster_path;
    const posterUrl = posterPath ? `${TMDB_IMAGE_BASE}/w500${posterPath}` : 'https://via.placeholder.com/500x750?text=No+Poster';
    
    const rating = movie.vote_average || movie.rating || 0;
    const platforms = getRandomPlatforms();
    
    card.innerHTML = `
        <button class="add-to-library" onclick="addToWatchlist(${movie.id}, '${escapeHtml(movie.title)}', '${posterPath}', ${rating})" title="Add to Watchlist">
            <i class="fas fa-plus"></i>
        </button>
        <img src="${posterUrl}" alt="${escapeHtml(movie.title)}" class="movie-poster">
        <div class="movie-details">
            <h3 class="movie-title">${escapeHtml(movie.title)}</h3>
            <div class="movie-meta">
                <span class="rating">
                    <i class="fas fa-star"></i>
                    ${rating.toFixed(1)}
                </span>
                <span class="movie-platform">${platforms[0]}</span>
            </div>
        </div>
    `;
    
    card.addEventListener('click', (e) => {
        if (!e.target.closest('.add-to-library')) {
            showMovieModal(movie, platforms);
        }
    });
    
    return card;
}

// Create Theater Movie Card
function createTheaterCard(movie) {
    const card = document.createElement('div');
    card.className = 'movie-card';
    
    const posterPath = movie.poster_path;
    const posterUrl = posterPath ? `${TMDB_IMAGE_BASE}/w500${posterPath}` : 'https://via.placeholder.com/500x750?text=No+Poster';
    
    const rating = movie.rating || movie.vote_average || 0;
    const price = movie.min_price || '12.99';
    
    card.innerHTML = `
        <img src="${posterUrl}" alt="${escapeHtml(movie.title)}" class="movie-poster">
        <div class="movie-details">
            <h3 class="movie-title">${escapeHtml(movie.title)}</h3>
            <div class="movie-meta">
                <span class="rating">
                    <i class="fas fa-star"></i>
                    ${rating.toFixed(1)}
                </span>
                <span class="movie-price">$${price}</span>
            </div>
            <button class="book-btn" onclick="bookMovie(${movie.id})">
                <i class="fas fa-ticket-alt"></i> Book Tickets
            </button>
        </div>
    `;
    
    return card;
}

// Create Coming Soon Card
function createComingSoonCard(movie) {
    const card = document.createElement('div');
    card.className = 'movie-card';
    
    const posterPath = movie.poster_path;
    const posterUrl = posterPath ? `${TMDB_IMAGE_BASE}/w500${posterPath}` : 'https://via.placeholder.com/500x750?text=No+Poster';
    
    const rating = movie.vote_average || 0;
    const releaseDate = movie.release_date ? new Date(movie.release_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'TBA';
    
    card.innerHTML = `
        <button class="add-to-library" onclick="addToInterested(${movie.id}, '${escapeHtml(movie.title)}', '${posterPath}', ${rating}, '${releaseDate}')" title="I'm Interested">
            <i class="fas fa-bell"></i>
        </button>
        <img src="${posterUrl}" alt="${escapeHtml(movie.title)}" class="movie-poster">
        <div class="movie-details">
            <h3 class="movie-title">${escapeHtml(movie.title)}</h3>
            <div class="movie-meta">
                <span class="rating">
                    <i class="fas fa-star"></i>
                    ${rating.toFixed(1)}
                </span>
            </div>
            <p style="color: #888; font-size: 0.9rem; margin-bottom: 0.5rem;">
                <i class="fas fa-calendar"></i> ${releaseDate}
            </p>
        </div>
    `;
    
    return card;
}

// Movie Modal
function showMovieModal(movie, platforms) {
    const modal = document.getElementById('movieModal');
    const modalBody = document.getElementById('modalBody');
    
    const backdropUrl = movie.backdrop_path ? `${TMDB_IMAGE_BASE}/original${movie.backdrop_path}` : '';
    const tmdbRating = movie.vote_average || movie.rating || 0;
    const releaseYear = movie.release_date ? new Date(movie.release_date).getFullYear() : 'N/A';
    const runtime = movie.runtime || 120;
    const movieId = movie.id || movie.tmdb_id;
    
    modalBody.innerHTML = `
        <div class="modal-backdrop" style="background-image: linear-gradient(to bottom, rgba(0,0,0,0.3), rgba(26,26,26,1)), url('${backdropUrl}')"></div>
        <div class="modal-info">
            <h2 class="modal-title">${escapeHtml(movie.title)}</h2>
            <div class="modal-meta">
                <div class="modal-meta-item">
                    <i class="fas fa-star" style="color: #ffd700;"></i>
                    <span>TMDB: ${tmdbRating.toFixed(1)}/10</span>
                </div>
                <div class="modal-meta-item" id="userRatingDisplay">
                    <i class="fas fa-users" style="color: #00d4ff;"></i>
                    <span>Loading user rating...</span>
                </div>
                <div class="modal-meta-item">
                    <i class="fas fa-calendar"></i>
                    <span>${releaseYear}</span>
                </div>
                <div class="modal-meta-item">
                    <i class="fas fa-clock"></i>
                    <span>${runtime} min</span>
                </div>
            </div>
            <p class="modal-overview">${movie.overview || 'No description available.'}</p>
            <div class="modal-platforms">
                <h3><i class="fas fa-tv"></i> Available on</h3>
                <div class="platforms-list">
                    ${platforms.map(platform => `<span class="platform-badge">${platform}</span>`).join('')}
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn-primary" onclick="addToWatchlist(${movieId}, '${escapeHtml(movie.title)}', '${movie.poster_path}', ${tmdbRating})">
                    <i class="fas fa-plus"></i> Add to Watchlist
                </button>
                <button class="btn-secondary" onclick="addToFavorites(${movieId}, '${escapeHtml(movie.title)}', '${movie.poster_path}', ${tmdbRating})">
                    <i class="fas fa-heart"></i> Add to Favorites
                </button>
            </div>

            <!-- Reviews Section -->
            <div class="reviews-section">
                <h3><i class="fas fa-comment-dots"></i> User Reviews</h3>
                
                <!-- Add Review Form -->
                <div class="add-review-form" id="addReviewForm">
                    <h4>${isLoggedIn ? 'Write a Review' : 'Login to Write a Review'}</h4>
                    ${isLoggedIn ? `
                        <div class="rating-input">
                            <label>Your Rating:</label>
                            <div class="star-rating" id="starRating">
                                <i class="far fa-star" data-rating="1"></i>
                                <i class="far fa-star" data-rating="2"></i>
                                <i class="far fa-star" data-rating="3"></i>
                                <i class="far fa-star" data-rating="4"></i>
                                <i class="far fa-star" data-rating="5"></i>
                            </div>
                            <input type="hidden" id="selectedRating" value="0">
                        </div>
                        <textarea id="reviewText" placeholder="Write your review here..." rows="4"></textarea>
                        <button class="btn-primary" onclick="submitReview(${movieId}, '${escapeHtml(movie.title)}')">
                            <i class="fas fa-paper-plane"></i> Submit Review
                        </button>
                    ` : `
                        <p style="text-align: center; color: rgba(255,255,255,0.6); margin: 1rem 0;">
                            You must be logged in to submit a review
                        </p>
                        <button class="btn-primary" onclick="openModal('loginModal')">
                            <i class="fas fa-sign-in-alt"></i> Login to Review
                        </button>
                    `}
                </div>

                <!-- Reviews List -->
                <div class="reviews-list" id="reviewsList">
                    <div class="loading">Loading reviews...</div>
                </div>
            </div>
        </div>
    `;
    
    modal.style.display = 'block';
    
    // Initialize star rating
    initializeStarRating();
    
    // Load reviews
    loadReviews(movieId);
    loadAverageRating(movieId);
}

function initializeModal() {
    const modal = document.getElementById('movieModal');
    const closeBtn = document.querySelector('.modal-close');
    
    closeBtn.onclick = () => modal.style.display = 'none';
    
    window.onclick = (e) => {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    };
}

// Library Functions
function loadLibraryContent() {
    loadWatchlist();
    loadFavorites();
    loadInterested();
}

async function loadWatchlist() {
    const grid = document.getElementById('watchlistGrid');
    
    if (!isLoggedIn) {
        grid.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-bookmark"></i>
                <p>Please login to view your watchlist</p>
                <button class="btn-primary" onclick="openModal('loginModal')">Login</button>
            </div>
        `;
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}/library.php?action=get_library&type=watchlist`);
        const data = await response.json();
        
        if (data.success && data.items.length > 0) {
            grid.innerHTML = '';
            data.items.forEach(item => {
                const card = createLibraryCard(item, 'watchlist');
                grid.appendChild(card);
            });
        } else {
            grid.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-bookmark"></i>
                    <p>Your watchlist is empty</p>
                    <button class="btn-primary" onclick="switchSection('streaming')">Explore Movies</button>
                </div>
            `;
        }
    } catch (error) {
        console.error('Load watchlist error:', error);
        grid.innerHTML = `<div class="empty-state"><p>Failed to load watchlist</p></div>`;
    }
}

async function loadFavorites() {
    const grid = document.getElementById('favoritesGrid');
    
    if (!isLoggedIn) {
        grid.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-heart"></i>
                <p>Please login to view your favorites</p>
                <button class="btn-primary" onclick="openModal('loginModal')">Login</button>
            </div>
        `;
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}/library.php?action=get_library&type=favorites`);
        const data = await response.json();
        
        if (data.success && data.items.length > 0) {
            grid.innerHTML = '';
            data.items.forEach(item => {
                const card = createLibraryCard(item, 'favorites');
                grid.appendChild(card);
            });
        } else {
            grid.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-heart"></i>
                    <p>No favorites yet</p>
                    <button class="btn-primary" onclick="switchSection('streaming')">Explore Movies</button>
                </div>
            `;
        }
    } catch (error) {
        console.error('Load favorites error:', error);
        grid.innerHTML = `<div class="empty-state"><p>Failed to load favorites</p></div>`;
    }
}

async function loadInterested() {
    const grid = document.getElementById('interestedGrid');
    
    if (!isLoggedIn) {
        grid.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-bell"></i>
                <p>Please login to track upcoming movies</p>
                <button class="btn-primary" onclick="openModal('loginModal')">Login</button>
            </div>
        `;
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}/library.php?action=get_library&type=interest`);
        const data = await response.json();
        
        if (data.success && data.items.length > 0) {
            grid.innerHTML = '';
            data.items.forEach(item => {
                const card = createLibraryCard(item, 'interest');
                grid.appendChild(card);
            });
        } else {
            grid.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-bell"></i>
                    <p>No movies in your interest list</p>
                    <button class="btn-primary" onclick="switchSection('tickets')">Check Coming Soon</button>
                </div>
            `;
        }
    } catch (error) {
        console.error('Load interested error:', error);
        grid.innerHTML = `<div class="empty-state"><p>Failed to load interest list</p></div>`;
    }
}

function createLibraryCard(item, type) {
    
    if (favorites.length > 0) {
        grid.innerHTML = '';
        favorites.forEach(movie => {
            const card = createLibraryCard(movie, 'favorites');
            grid.appendChild(card);
        });
    }
}

function loadInterested() {
    const grid = document.getElementById('interestedGrid');
    const interested = JSON.parse(localStorage.getItem(STORAGE_KEYS.INTERESTED) || '[]');
    
    if (interested.length > 0) {
        grid.innerHTML = '';
        interested.forEach(movie => {
            const card = createLibraryCard(movie, 'interested');
            grid.appendChild(card);
        });
    }
}

function createLibraryCard(item, type) {
    const card = document.createElement('div');
    card.className = 'movie-card';
    
    const posterUrl = item.movie_poster ? `${TMDB_IMAGE_BASE}/w500${item.movie_poster}` : 'https://via.placeholder.com/500x750?text=No+Poster';
    
    card.innerHTML = `
        <img src="${posterUrl}" alt="${escapeHtml(item.movie_title)}" class="movie-poster">
        <div class="movie-details">
            <h3 class="movie-title">${escapeHtml(item.movie_title)}</h3>
            <div class="movie-meta">
                ${item.movie_year ? `<span class="year">${item.movie_year}</span>` : ''}
            </div>
            <p style="color: #888; font-size: 0.85rem;">Added: ${new Date(item.added_at).toLocaleDateString()}</p>
            <button class="book-btn" onclick="removeFromLibrary(${item.tmdb_id}, '${type}')">
                <i class="fas fa-trash"></i> Remove
            </button>
        </div>
    `;
    
    return card;
}

// Library Management
async function addToWatchlist(id, title, poster, rating, year = '') {
    if (!isLoggedIn) {
        showNotification('Please login to use library features', 'error');
        openModal('loginModal');
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}/library.php?action=add_to_library`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                tmdb_id: id,
                movie_title: title,
                movie_poster: poster,
                movie_year: year,
                library_type: 'watchlist'
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(data.message, 'success');
            loadWatchlist();
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        console.error('Add to watchlist error:', error);
        showNotification('Failed to add to watchlist', 'error');
    }
}

async function addToFavorites(id, title, poster, rating, year = '') {
    if (!isLoggedIn) {
        showNotification('Please login to use library features', 'error');
        openModal('loginModal');
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}/library.php?action=add_to_library`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                tmdb_id: id,
                movie_title: title,
                movie_poster: poster,
                movie_year: year,
                library_type: 'favorites'
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(data.message, 'success');
            loadFavorites();
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        console.error('Add to favorites error:', error);
        showNotification('Failed to add to favorites', 'error');
    }
}

async function addToInterested(id, title, poster, rating, releaseDate) {
    if (!isLoggedIn) {
        showNotification('Please login to use library features', 'error');
        openModal('loginModal');
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}/library.php?action=add_to_library`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                tmdb_id: id,
                movie_title: title,
                movie_poster: poster,
                movie_year: releaseDate ? releaseDate.split('-')[0] : '',
                library_type: 'interest'
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(data.message, 'success');
            loadInterested();
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        console.error('Add to interested error:', error);
        showNotification('Failed to add to interest list', 'error');
    }
}

async function removeFromLibrary(id, type) {
    if (!isLoggedIn) {
        showNotification('Please login to manage library', 'error');
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}/library.php?action=remove_from_library`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                tmdb_id: id,
                library_type: type
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(data.message, 'success');
            
            // Reload appropriate library
            if (type === 'watchlist') loadWatchlist();
            else if (type === 'favorites') loadFavorites();
            else if (type === 'interest') loadInterested();
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        console.error('Remove from library error:', error);
        showNotification('Failed to remove from library', 'error');
    }
}

// Book Movie Function
function bookMovie(movieId) {
    window.location.href = `booking.html?movie_id=${movieId}`;
}

// Search Functionality
function initializeSearch() {
    const searchInput = document.getElementById('searchInput');
    let searchTimeout;
    
    searchInput.addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        const query = e.target.value.trim();
        
        if (query.length > 2) {
            searchTimeout = setTimeout(() => performSearch(query), 500);
        } else if (query.length === 0) {
            hideSearchResults();
        }
    });
    
    // Close search results when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.search-box')) {
            hideSearchResults();
        }
    });
}

async function performSearch(query) {
    try {
        const response = await fetch(`${API_BASE}/movies.php?action=search&query=${encodeURIComponent(query)}`);
        const data = await response.json();
        
        if (data.results && data.results.length > 0) {
            displaySearchResults(data.results);
        } else {
            displayNoResults(query);
        }
    } catch (error) {
        console.error('Search error:', error);
        displaySearchError();
    }
}

function displaySearchResults(results) {
    let searchDropdown = document.getElementById('searchDropdown');
    
    if (!searchDropdown) {
        searchDropdown = document.createElement('div');
        searchDropdown.id = 'searchDropdown';
        searchDropdown.className = 'search-dropdown';
        document.querySelector('.search-box').appendChild(searchDropdown);
    }
    
    searchDropdown.innerHTML = `
        <div class="search-header">
            <span>Search Results (${results.length})</span>
            <button onclick="viewAllResults()" class="view-all-btn">View All</button>
        </div>
        <div class="search-results-list">
            ${results.slice(0, 6).map(movie => `
                <div class="search-result-item" onclick="showMovieModal({
                    id: ${movie.id},
                    title: '${escapeHtml(movie.title)}',
                    poster_path: '${movie.poster_path || ''}',
                    backdrop_path: '${movie.backdrop_path || ''}',
                    vote_average: ${movie.vote_average || 0},
                    release_date: '${movie.release_date || ''}',
                    overview: '${escapeHtml(movie.overview || '')}',
                    runtime: ${movie.runtime || 120}
                }, ['Netflix', 'Prime Video']); hideSearchResults();">
                    <img src="${movie.poster_path ? TMDB_IMAGE_BASE + '/w92' + movie.poster_path : 'https://via.placeholder.com/92x138?text=No+Image'}" 
                         alt="${escapeHtml(movie.title)}"
                         class="search-result-poster">
                    <div class="search-result-info">
                        <h4>${escapeHtml(movie.title)}</h4>
                        <div class="search-result-meta">
                            <span class="search-rating">
                                <i class="fas fa-star"></i>
                                ${(movie.vote_average || 0).toFixed(1)}
                            </span>
                            <span class="search-year">
                                ${movie.release_date ? new Date(movie.release_date).getFullYear() : 'N/A'}
                            </span>
                        </div>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
    
    searchDropdown.style.display = 'block';
    
    // Store full results for "View All"
    window.searchResults = results;
}

function displayNoResults(query) {
    let searchDropdown = document.getElementById('searchDropdown');
    
    if (!searchDropdown) {
        searchDropdown = document.createElement('div');
        searchDropdown.id = 'searchDropdown';
        searchDropdown.className = 'search-dropdown';
        document.querySelector('.search-box').appendChild(searchDropdown);
    }
    
    searchDropdown.innerHTML = `
        <div class="search-no-results">
            <i class="fas fa-search"></i>
            <p>No results found for "${escapeHtml(query)}"</p>
        </div>
    `;
    
    searchDropdown.style.display = 'block';
}

function displaySearchError() {
    let searchDropdown = document.getElementById('searchDropdown');
    
    if (!searchDropdown) {
        searchDropdown = document.createElement('div');
        searchDropdown.id = 'searchDropdown';
        searchDropdown.className = 'search-dropdown';
        document.querySelector('.search-box').appendChild(searchDropdown);
    }
    
    searchDropdown.innerHTML = `
        <div class="search-no-results">
            <i class="fas fa-exclamation-circle"></i>
            <p>Error loading search results</p>
        </div>
    `;
    
    searchDropdown.style.display = 'block';
}

function hideSearchResults() {
    const searchDropdown = document.getElementById('searchDropdown');
    if (searchDropdown) {
        searchDropdown.style.display = 'none';
    }
}

function viewAllResults() {
    if (window.searchResults) {
        // Switch to streaming section and display all results
        switchSection('streaming');
        
        // Update the streaming section to show search results
        const trendingGrid = document.getElementById('trendingMovies');
        trendingGrid.parentElement.parentElement.querySelector('h2').innerHTML = '<i class="fas fa-search"></i> Search Results';
        
        trendingGrid.innerHTML = '';
        window.searchResults.forEach(movie => {
            const card = createStreamingCard(movie);
            trendingGrid.appendChild(card);
        });
        
        // Hide other sections in streaming
        document.getElementById('popularMovies').parentElement.style.display = 'none';
        document.getElementById('topRatedMovies').parentElement.style.display = 'none';
        
        hideSearchResults();
        document.getElementById('searchInput').value = '';
    }
}

// Helper Functions
function getRandomPlatforms() {
    const platforms = ['Netflix', 'Prime Video', 'Disney+', 'Hulu', 'HBO Max', 'Apple TV+', 'Paramount+'];
    const count = Math.floor(Math.random() * 2) + 1;
    const shuffled = platforms.sort(() => 0.5 - Math.random());
    return shuffled.slice(0, count);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Star Rating System
function initializeStarRating() {
    const stars = document.querySelectorAll('#starRating i');
    const selectedRatingInput = document.getElementById('selectedRating');
    
    if (!stars.length || !selectedRatingInput) {
        return; // Not logged in, no rating form displayed
    }
    
    stars.forEach(star => {
        star.addEventListener('click', () => {
            const rating = parseInt(star.dataset.rating);
            selectedRatingInput.value = rating;
            
            // Update star display
            stars.forEach((s, index) => {
                if (index < rating) {
                    s.classList.remove('far');
                    s.classList.add('fas');
                } else {
                    s.classList.remove('fas');
                    s.classList.add('far');
                }
            });
        });
        
        // Hover effect
        star.addEventListener('mouseenter', () => {
            const rating = parseInt(star.dataset.rating);
            stars.forEach((s, index) => {
                if (index < rating) {
                    s.style.color = '#ffd700';
                } else {
                    s.style.color = '#666';
                }
            });
        });
    });
    
    // Reset hover on leave
    document.getElementById('starRating')?.addEventListener('mouseleave', () => {
        const currentRating = parseInt(selectedRatingInput.value);
        stars.forEach((s, index) => {
            if (index < currentRating) {
                s.style.color = '#ffd700';
            } else {
                s.style.color = '#666';
            }
        });
    });
}

// Load Reviews
async function loadReviews(tmdbId) {
    const reviewsList = document.getElementById('reviewsList');
    
    try {
        const response = await fetch(`${API_BASE}/reviews.php?action=get_reviews&tmdb_id=${tmdbId}`);
        const data = await response.json();
        
        if (data.success && data.reviews.length > 0) {
            reviewsList.innerHTML = '';
            data.reviews.forEach(review => {
                const reviewCard = createReviewCard(review);
                reviewsList.appendChild(reviewCard);
            });
        } else {
            reviewsList.innerHTML = '<div class="no-reviews"><i class="fas fa-comment-slash"></i><p>No reviews yet. Be the first to review!</p></div>';
        }
    } catch (error) {
        console.error('Error loading reviews:', error);
        reviewsList.innerHTML = '<div class="error">Error loading reviews</div>';
    }
}

// Load Average Rating
async function loadAverageRating(tmdbId) {
    try {
        const response = await fetch(`${API_BASE}/reviews.php?action=get_average_rating&tmdb_id=${tmdbId}`);
        const data = await response.json();
        
        if (data.success) {
            const display = document.getElementById('userRatingDisplay');
            if (display) {
                const stars = '⭐'.repeat(Math.round(data.average));
                display.innerHTML = `
                    <i class="fas fa-users" style="color: #00d4ff;"></i>
                    <span>Users: ${data.average}/5 ${stars} (${data.count} reviews)</span>
                `;
            }
        }
    } catch (error) {
        console.error('Error loading average rating:', error);
    }
}

// Create Review Card
function createReviewCard(review) {
    const div = document.createElement('div');
    div.className = 'review-card';
    
    const stars = '⭐'.repeat(review.rating);
    const date = new Date(review.created_at).toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric', 
        year: 'numeric' 
    });
    
    div.innerHTML = `
        <div class="review-header">
            <div class="review-user">
                <div class="user-avatar">${review.user_name.charAt(0).toUpperCase()}</div>
                <div>
                    <h4>${escapeHtml(review.user_name)}</h4>
                    <div class="review-rating">${stars}</div>
                </div>
            </div>
            <span class="review-date">${date}</span>
        </div>
        <p class="review-text">${escapeHtml(review.review_text || 'No comment provided.')}</p>
    `;
    
    return div;
}

// Submit Review
async function submitReview(tmdbId, movieTitle) {
    if (!isLoggedIn) {
        showNotification('Please login to submit a review', 'error');
        openModal('loginModal');
        return;
    }
    
    const rating = document.getElementById('selectedRating').value;
    const reviewText = document.getElementById('reviewText').value.trim();
    
    // Validate
    if (!rating || rating === '0') {
        showNotification('Please select a rating', 'error');
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('tmdb_id', tmdbId);
        formData.append('movie_title', movieTitle);
        formData.append('rating', rating);
        formData.append('review_text', reviewText);
        
        const response = await fetch(`${API_BASE}/reviews.php?action=add_review`, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Review submitted successfully!', 'success');
            
            // Reset form
            document.getElementById('selectedRating').value = '0';
            document.getElementById('reviewText').value = '';
            
            // Reset stars
            document.querySelectorAll('#starRating i').forEach(star => {
                star.classList.remove('fas');
                star.classList.add('far');
                star.style.color = '#666';
            });
            
            // Reload reviews
            loadReviews(tmdbId);
            loadAverageRating(tmdbId);
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        console.error('Submit review error:', error);
        showNotification('Failed to submit review', 'error');
    }
}

// Load reviews for a movie