// Check authentication
if (!localStorage.getItem('admin_token')) {
    window.location.href = 'login.html';
}

const API_BASE = '../api';
const TMDB_IMAGE_BASE = 'https://image.tmdb.org/t/p';

// Load current picks
async function loadCurrentPicks() {
    const container = document.getElementById('currentPicks');
    
    try {
        const response = await fetch(`${API_BASE}/admin.php?action=get_our_picks`);
        const data = await response.json();
        
        if (data.success && data.picks.length > 0) {
            container.innerHTML = '';
            data.picks.forEach(pick => {
                const card = createPickCard(pick);
                container.appendChild(card);
            });
        } else {
            container.innerHTML = '<div class="empty-picks"><i class="fas fa-crown"></i><p>No picks yet. Start adding movies!</p></div>';
        }
    } catch (error) {
        console.error('Error loading picks:', error);
        container.innerHTML = '<div class="error">Error loading picks</div>';
    }
}

function createPickCard(pick) {
    const div = document.createElement('div');
    div.className = 'pick-card';
    div.dataset.id = pick.id;
    
    const posterUrl = pick.poster_path ? `${TMDB_IMAGE_BASE}/w185${pick.poster_path}` : 'https://via.placeholder.com/185x278?text=No+Poster';
    
    div.innerHTML = `
        <div class="pick-drag-handle">
            <i class="fas fa-grip-vertical"></i>
        </div>
        <img src="${posterUrl}" alt="${pick.title}" class="pick-poster">
        <div class="pick-info">
            <h3>${pick.title}</h3>
            <p class="pick-meta">
                <span><i class="fas fa-star"></i> ${(pick.vote_average || 0).toFixed(1)}</span>
                <span><i class="fas fa-calendar"></i> ${pick.release_date || 'N/A'}</span>
            </p>
        </div>
        <button class="pick-delete" onclick="removePick(${pick.id}, '${pick.title}')" title="Remove">
            <i class="fas fa-trash"></i>
        </button>
    `;
    
    return div;
}

// Modal functions
function openAddModal() {
    document.getElementById('addMovieModal').style.display = 'block';
    document.getElementById('movieSearchInput').focus();
}

function closeAddModal() {
    document.getElementById('addMovieModal').style.display = 'none';
    document.getElementById('searchResults').innerHTML = '';
    document.getElementById('movieSearchInput').value = '';
}

// Search movies
async function searchMovies() {
    const query = document.getElementById('movieSearchInput').value.trim();
    const resultsContainer = document.getElementById('searchResults');
    
    if (query.length < 2) {
        alert('Please enter at least 2 characters');
        return;
    }
    
    resultsContainer.innerHTML = '<div class="loading">Searching...</div>';
    
    try {
        const response = await fetch(`${API_BASE}/movies.php?action=search&query=${encodeURIComponent(query)}`);
        const data = await response.json();
        
        if (data.results && data.results.length > 0) {
            resultsContainer.innerHTML = '';
            data.results.slice(0, 12).forEach(movie => {
                const card = createSearchResultCard(movie);
                resultsContainer.appendChild(card);
            });
        } else {
            resultsContainer.innerHTML = '<div class="no-results">No movies found</div>';
        }
    } catch (error) {
        console.error('Search error:', error);
        resultsContainer.innerHTML = '<div class="error">Error searching movies</div>';
    }
}

function createSearchResultCard(movie) {
    const div = document.createElement('div');
    div.className = 'search-result-card';
    
    const posterUrl = movie.poster_path ? `${TMDB_IMAGE_BASE}/w185${movie.poster_path}` : 'https://via.placeholder.com/185x278?text=No+Poster';
    
    div.innerHTML = `
        <img src="${posterUrl}" alt="${movie.title}">
        <div class="search-card-info">
            <h4>${movie.title}</h4>
            <p><i class="fas fa-star"></i> ${(movie.vote_average || 0).toFixed(1)}</p>
        </div>
        <button class="btn-add" onclick="addToPicks(${movie.id}, '${escapeHtml(movie.title)}', '${movie.poster_path}', '${movie.backdrop_path || ''}', ${movie.vote_average || 0}, '${movie.release_date || ''}', '${escapeHtml(movie.overview || '')}')">
            <i class="fas fa-plus"></i> Add
        </button>
    `;
    
    return div;
}

// Add movie to picks
async function addToPicks(tmdbId, title, posterPath, backdropPath, voteAverage, releaseDate, overview) {
    try {
        const formData = new FormData();
        formData.append('action', 'add_to_picks');
        formData.append('tmdb_id', tmdbId);
        formData.append('title', title);
        formData.append('poster_path', posterPath);
        formData.append('backdrop_path', backdropPath);
        formData.append('vote_average', voteAverage);
        formData.append('release_date', releaseDate);
        formData.append('overview', overview);
        
        const response = await fetch(`${API_BASE}/admin.php`, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Movie added to Our Picks!');
            closeAddModal();
            loadCurrentPicks();
        } else {
            alert(data.message || 'Error adding movie');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error adding movie to picks');
    }
}

// Remove pick
async function removePick(id, title) {
    if (!confirm(`Remove "${title}" from Our Picks?`)) {
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('action', 'remove_from_picks');
        formData.append('id', id);
        
        const response = await fetch(`${API_BASE}/admin.php`, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            loadCurrentPicks();
        } else {
            alert(data.message || 'Error removing movie');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error removing movie');
    }
}

// Search on Enter key
document.addEventListener('DOMContentLoaded', () => {
    loadCurrentPicks();
    
    document.getElementById('movieSearchInput')?.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            searchMovies();
        }
    });
});

// Logout
document.getElementById('logoutBtn')?.addEventListener('click', (e) => {
    e.preventDefault();
    localStorage.removeItem('admin_token');
    window.location.href = 'login.html';
});

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
