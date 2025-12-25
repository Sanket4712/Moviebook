// API Base URL
const API_BASE = './api';
const TMDB_IMAGE_BASE = 'https://image.tmdb.org/t/p';

// Load trending movies for hero slider
async function loadHeroMovies() {
    try {
        const response = await fetch(`${API_BASE}/movies.php?action=trending`);
        const data = await response.json();
        
        if (data.results && data.results.length > 0) {
            const heroMovie = data.results[0];
            const heroSlider = document.getElementById('heroSlider');
            heroSlider.style.backgroundImage = `linear-gradient(to bottom, rgba(0,0,0,0.5), rgba(0,0,0,0.8)), url('${TMDB_IMAGE_BASE}/original${heroMovie.backdrop_path}')`;
        }
    } catch (error) {
        console.error('Error loading hero movies:', error);
    }
}

// Load now showing movies
async function loadNowShowing() {
    const grid = document.getElementById('nowShowingGrid');
    
    try {
        const response = await fetch(`${API_BASE}/movies.php?action=now_showing`);
        const data = await response.json();
        
        if (data.results && data.results.length > 0) {
            grid.innerHTML = '';
            
            data.results.slice(0, 6).forEach(movie => {
                const card = createMovieCard(movie, true);
                grid.appendChild(card);
            });
        } else {
            grid.innerHTML = '<p class="loading">No movies currently showing</p>';
        }
    } catch (error) {
        console.error('Error loading now showing:', error);
        grid.innerHTML = '<p class="loading">Error loading movies</p>';
    }
}

// Load trending movies
async function loadTrending() {
    const slider = document.getElementById('trendingSlider');
    
    try {
        const response = await fetch(`${API_BASE}/movies.php?action=trending`);
        const data = await response.json();
        
        if (data.results && data.results.length > 0) {
            slider.innerHTML = '';
            const moviesGrid = document.createElement('div');
            moviesGrid.className = 'movies-grid';
            
            data.results.slice(0, 6).forEach(movie => {
                const card = createMovieCard(movie, false);
                moviesGrid.appendChild(card);
            });
            
            slider.appendChild(moviesGrid);
        }
    } catch (error) {
        console.error('Error loading trending:', error);
    }
}

// Create movie card
function createMovieCard(movie, isInTheater = false) {
    const card = document.createElement('div');
    card.className = 'movie-card';
    
    const posterPath = movie.poster_path || movie.poster_path;
    const posterUrl = posterPath ? `${TMDB_IMAGE_BASE}/w500${posterPath}` : 'https://via.placeholder.com/500x750?text=No+Poster';
    
    const rating = movie.rating || movie.vote_average || 0;
    const price = movie.min_price || 'N/A';
    
    card.innerHTML = `
        <img src="${posterUrl}" alt="${movie.title}" class="movie-poster">
        <div class="movie-details">
            <h3 class="movie-title">${movie.title}</h3>
            <div class="movie-meta">
                <span class="rating">
                    <i class="fas fa-star"></i>
                    ${rating.toFixed(1)}
                </span>
                ${isInTheater ? `<span class="movie-price">From $${price}</span>` : ''}
            </div>
            ${isInTheater ? `<button class="book-btn" onclick="bookMovie(${movie.id})">Book Now</button>` : ''}
        </div>
    `;
    
    return card;
}

// Book movie function
function bookMovie(movieId) {
    window.location.href = `booking.html?movie_id=${movieId}`;
}

// Search functionality
document.getElementById('searchInput')?.addEventListener('input', async (e) => {
    const query = e.target.value.trim();
    
    if (query.length > 2) {
        // Implement search functionality
        console.log('Searching for:', query);
    }
});

// Initialize page
document.addEventListener('DOMContentLoaded', () => {
    loadHeroMovies();
    loadNowShowing();
    loadTrending();
});
