// API Base URL
const API_BASE = './api';
const TMDB_IMAGE_BASE = 'https://image.tmdb.org/t/p';

let currentFilter = 'all';

// Load movies based on filter
async function loadMovies(filter = 'all') {
    const grid = document.getElementById('moviesGrid');
    grid.innerHTML = '<div class="loading">Loading movies...</div>';
    
    try {
        let url;
        if (filter === 'showing') {
            url = `${API_BASE}/movies.php?action=now_showing`;
        } else {
            url = `${API_BASE}/movies.php?action=trending`;
        }
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.results && data.results.length > 0) {
            grid.innerHTML = '';
            
            data.results.forEach(movie => {
                const card = createMovieCard(movie, filter === 'showing');
                grid.appendChild(card);
            });
        } else {
            grid.innerHTML = '<p class="loading">No movies found</p>';
        }
    } catch (error) {
        console.error('Error loading movies:', error);
        grid.innerHTML = '<p class="loading">Error loading movies</p>';
    }
}

// Create movie card
function createMovieCard(movie, isInTheater = false) {
    const card = document.createElement('div');
    card.className = 'movie-card';
    
    const posterPath = movie.poster_path;
    const posterUrl = posterPath ? `${TMDB_IMAGE_BASE}/w500${posterPath}` : 'https://via.placeholder.com/500x750?text=No+Poster';
    
    const rating = movie.rating || movie.vote_average || 0;
    const price = movie.min_price || '12.99';
    
    card.innerHTML = `
        <img src="${posterUrl}" alt="${movie.title}" class="movie-poster" onerror="this.src='https://via.placeholder.com/500x750?text=No+Poster'">
        <div class="movie-details">
            <h3 class="movie-title">${movie.title}</h3>
            <div class="movie-meta">
                <span class="rating">
                    <i class="fas fa-star"></i>
                    ${rating.toFixed(1)}
                </span>
                ${isInTheater ? `<span class="movie-price">From $${price}</span>` : ''}
            </div>
            <p style="color: #888; font-size: 0.9rem; margin: 0.5rem 0;">${movie.release_date || 'Coming Soon'}</p>
            ${isInTheater ? `<button class="book-btn" onclick="bookMovie(${movie.id})">Book Now</button>` : `<button class="book-btn" style="background-color: #666;" disabled>Not Available</button>`}
        </div>
    `;
    
    return card;
}

// Book movie
function bookMovie(movieId) {
    window.location.href = `booking.html?movie_id=${movieId}`;
}

// Filter buttons
document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        // Update active state
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        
        // Load movies based on filter
        const filter = btn.dataset.filter;
        currentFilter = filter;
        loadMovies(filter);
    });
});

// Search functionality
document.getElementById('searchInput')?.addEventListener('input', async (e) => {
    const query = e.target.value.trim();
    const grid = document.getElementById('moviesGrid');
    
    if (query.length > 2) {
        try {
            const response = await fetch(`${API_BASE}/movies.php?action=search&query=${encodeURIComponent(query)}`);
            const data = await response.json();
            
            if (data.results && data.results.length > 0) {
                grid.innerHTML = '';
                
                data.results.forEach(movie => {
                    const card = createMovieCard(movie, false);
                    grid.appendChild(card);
                });
            } else {
                grid.innerHTML = '<p class="loading">No movies found</p>';
            }
        } catch (error) {
            console.error('Error searching:', error);
        }
    } else if (query.length === 0) {
        loadMovies(currentFilter);
    }
});

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    loadMovies('showing');
});
