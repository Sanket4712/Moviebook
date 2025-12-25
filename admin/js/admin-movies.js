const API_BASE = '../api';
const TMDB_IMAGE_BASE = 'https://image.tmdb.org/t/p';
let selectedMovie = null;

// Check authentication
async function checkAuth() {
    try {
        const response = await fetch(`${API_BASE}/admin.php?action=check_auth`);
        const data = await response.json();
        if (!data.authenticated) window.location.href = 'login.html';
    } catch (error) {
        window.location.href = 'login.html';
    }
}

// Logout
document.getElementById('logoutBtn').addEventListener('click', async (e) => {
    e.preventDefault();
    await fetch(`${API_BASE}/admin.php?action=logout`, { method: 'POST' });
    window.location.href = 'login.html';
});

// Search TMDB
async function searchTMDB() {
    const query = document.getElementById('tmdbSearch').value.trim();
    const resultsDiv = document.getElementById('searchResults');
    
    if (query.length < 2) {
        alert('Please enter at least 2 characters');
        return;
    }
    
    resultsDiv.innerHTML = '<div class="loading">Searching...</div>';
    
    try {
        const response = await fetch(`${API_BASE}/movies.php?action=search&query=${encodeURIComponent(query)}`);
        const data = await response.json();
        
        if (data.results && data.results.length > 0) {
            resultsDiv.innerHTML = '';
            
            data.results.slice(0, 12).forEach(movie => {
                const card = document.createElement('div');
                card.className = 'movie-card';
                card.style.cursor = 'pointer';
                
                const posterUrl = movie.poster_path ? `${TMDB_IMAGE_BASE}/w500${movie.poster_path}` : 'https://via.placeholder.com/500x750';
                
                card.innerHTML = `
                    <img src="${posterUrl}" alt="${movie.title}" class="movie-poster">
                    <div class="movie-details">
                        <h3 class="movie-title">${movie.title}</h3>
                        <p style="color: #888; font-size: 0.9rem;">${movie.release_date || 'N/A'}</p>
                        <button class="btn-primary btn-small" onclick='selectMovieForAdding(${JSON.stringify(movie).replace(/'/g, "&apos;")})'>
                            <i class="fas fa-plus"></i> Add
                        </button>
                    </div>
                `;
                
                resultsDiv.appendChild(card);
            });
        } else {
            resultsDiv.innerHTML = '<p class="loading">No results found</p>';
        }
    } catch (error) {
        console.error('Search error:', error);
        resultsDiv.innerHTML = '<p class="loading">Error searching</p>';
    }
}

// Select movie for adding
function selectMovieForAdding(movie) {
    selectedMovie = movie;
    
    document.getElementById('movieTmdbId').value = movie.id;
    document.getElementById('movieTitle').value = movie.title;
    document.getElementById('movieOverview').value = movie.overview || '';
    document.getElementById('moviePoster').value = movie.poster_path || '';
    document.getElementById('movieBackdrop').value = movie.backdrop_path || '';
    document.getElementById('movieReleaseDate').value = movie.release_date || '';
    document.getElementById('movieRuntime').value = movie.runtime || 120;
    document.getElementById('movieRating').value = movie.vote_average || 0;
    
    const posterUrl = movie.poster_path ? `${TMDB_IMAGE_BASE}/w200${movie.poster_path}` : '';
    document.getElementById('moviePreview').innerHTML = `
        <img src="${posterUrl}" alt="${movie.title}">
        <div>
            <h3>${movie.title}</h3>
            <p>${movie.release_date || 'N/A'}</p>
            <p>Rating: ${movie.vote_average || 'N/A'}/10</p>
        </div>
    `;
    
    document.getElementById('addMovieModal').style.display = 'block';
}

// Show add movie modal
function showAddMovieModal() {
    alert('Please search for a movie from TMDB first');
}

// Close modal
function closeModal() {
    document.getElementById('addMovieModal').style.display = 'none';
    selectedMovie = null;
}

// Add movie form submit
document.getElementById('addMovieForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const movieData = {
        tmdb_id: document.getElementById('movieTmdbId').value,
        title: document.getElementById('movieTitle').value,
        overview: document.getElementById('movieOverview').value,
        poster_path: document.getElementById('moviePoster').value,
        backdrop_path: document.getElementById('movieBackdrop').value,
        release_date: document.getElementById('movieReleaseDate').value,
        runtime: document.getElementById('movieRuntime').value,
        rating: document.getElementById('movieRating').value,
        is_showing: document.getElementById('isShowing').value,
        show_start_date: document.getElementById('showStartDate').value || null,
        show_end_date: document.getElementById('showEndDate').value || null
    };
    
    try {
        const response = await fetch(`${API_BASE}/admin.php?action=add_movie`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(movieData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Movie added successfully!');
            closeModal();
            loadMovies();
            document.getElementById('searchResults').innerHTML = '';
            document.getElementById('tmdbSearch').value = '';
        } else {
            alert('Error: ' + (data.error || 'Failed to add movie'));
        }
    } catch (error) {
        console.error('Error adding movie:', error);
        alert('Error adding movie');
    }
});

// Load movies from database
async function loadMovies() {
    try {
        const response = await fetch(`${API_BASE}/admin.php?action=movies`);
        const data = await response.json();
        
        const tbody = document.getElementById('moviesTable');
        
        if (data.success && data.movies.length > 0) {
            tbody.innerHTML = '';
            
            data.movies.forEach(movie => {
                const posterUrl = movie.poster_path ? `${TMDB_IMAGE_BASE}/w92${movie.poster_path}` : 'https://via.placeholder.com/50x75';
                
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><img src="${posterUrl}" class="table-poster"></td>
                    <td>${movie.title}</td>
                    <td>${movie.rating || 'N/A'}/10</td>
                    <td><span class="status-badge ${movie.is_showing ? 'status-active' : 'status-inactive'}">
                        ${movie.is_showing ? 'Showing' : 'Not Showing'}
                    </span></td>
                    <td>${movie.show_start_date || 'N/A'} - ${movie.show_end_date || 'N/A'}</td>
                    <td>${movie.showtime_count}</td>
                    <td>
                        <button class="btn-primary btn-small" onclick="window.location.href='manage-showtimes.html?movie_id=${movie.id}'">
                            <i class="fas fa-clock"></i> Showtimes
                        </button>
                        <button class="btn-danger btn-small" onclick="deleteMovie(${movie.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center">No movies in database</td></tr>';
        }
    } catch (error) {
        console.error('Error loading movies:', error);
    }
}

// Delete movie
async function deleteMovie(id) {
    if (!confirm('Are you sure you want to delete this movie?')) return;
    
    try {
        const response = await fetch(`${API_BASE}/admin.php?action=delete_movie&id=${id}`, {
            method: 'DELETE'
        });
        const data = await response.json();
        
        if (data.success) {
            alert('Movie deleted successfully');
            loadMovies();
        } else {
            alert('Error deleting movie');
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    checkAuth();
    loadMovies();
    
    document.getElementById('tmdbSearch').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') searchTMDB();
    });
});
