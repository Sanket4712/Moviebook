/**
 * MovieBook - Watchlist, Diary & Favorites JavaScript
 * 
 * Handles AJAX operations for Letterboxd-style features.
 * No page redirects - everything happens via API calls.
 */

// API Base URL
const API_BASE = '../api';

// ==================== WATCHLIST ====================

/**
 * Toggle movie in watchlist (add if not present, remove if present)
 * @param {number} movieId - Movie ID
 * @param {HTMLElement} button - The button element clicked
 */
async function toggleWatchlist(movieId, button) {
    try {
        button.disabled = true;
        button.classList.add('loading');

        const response = await fetch(`${API_BASE}/watchlist.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=toggle&movie_id=${movieId}`
        });

        const data = await response.json();

        if (data.success) {
            updateWatchlistButton(button, data.inWatchlist);
            showNotification(data.message, 'success');
        } else {
            showNotification(data.error || 'Failed to update watchlist', 'error');
        }
    } catch (error) {
        console.error('Watchlist error:', error);
        showNotification('Network error. Please try again.', 'error');
    } finally {
        button.disabled = false;
        button.classList.remove('loading');
    }
}

/**
 * Update watchlist button appearance
 */
function updateWatchlistButton(button, inWatchlist) {
    if (inWatchlist) {
        button.classList.add('in-watchlist');
        button.innerHTML = '<i class="bi bi-bookmark-check-fill"></i> In Watchlist';
    } else {
        button.classList.remove('in-watchlist');
        button.innerHTML = '<i class="bi bi-bookmark-plus"></i> Add to Watchlist';
    }

    // Add pulse animation
    button.classList.add('pulse');
    setTimeout(() => button.classList.remove('pulse'), 300);
}

/**
 * Remove from watchlist (used in profile page)
 */
async function removeFromWatchlist(movieId, element) {
    try {
        const response = await fetch(`${API_BASE}/watchlist.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=remove&movie_id=${movieId}`
        });

        const data = await response.json();

        if (data.success) {
            // Animate removal
            if (element) {
                element.style.animation = 'fadeOut 0.3s ease forwards';
                setTimeout(() => element.remove(), 300);
            }
            showNotification('Removed from watchlist', 'success');
        }
    } catch (error) {
        showNotification('Failed to remove', 'error');
    }
}

// ==================== DIARY ====================

/**
 * Mark movie as watched (add to diary)
 * @param {number} movieId - Movie ID
 * @param {object} options - { rating, liked, review, watchedDate }
 */
async function markAsWatched(movieId, options = {}) {
    try {
        const params = new URLSearchParams({
            action: 'add',
            movie_id: movieId,
            watched_date: options.watchedDate || new Date().toISOString().split('T')[0],
            rating: options.rating || '',
            liked: options.liked ? '1' : '0',
            review: options.review || ''
        });

        const response = await fetch(`${API_BASE}/diary.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params.toString()
        });

        const data = await response.json();

        if (data.success) {
            showNotification('Added to your diary!', 'success');
            return data;
        } else {
            showNotification(data.error || 'Failed to add to diary', 'error');
            return null;
        }
    } catch (error) {
        showNotification('Network error. Please try again.', 'error');
        return null;
    }
}

/**
 * Open diary entry modal
 */
function openDiaryModal(movieId, movieTitle) {
    // Create modal if it doesn't exist
    let modal = document.getElementById('diaryModal');
    if (!modal) {
        modal = createDiaryModal();
        document.body.appendChild(modal);
    }

    // Set movie info
    document.getElementById('diaryMovieId').value = movieId;
    document.getElementById('diaryMovieTitle').textContent = movieTitle;
    document.getElementById('diaryDate').value = new Date().toISOString().split('T')[0];

    // Reset form
    document.querySelectorAll('.star-rating i').forEach(star => star.classList.remove('active'));
    document.getElementById('diaryLiked').checked = false;
    document.getElementById('diaryReview').value = '';

    modal.classList.add('active');
}

function closeDiaryModal() {
    const modal = document.getElementById('diaryModal');
    if (modal) modal.classList.remove('active');
}

function createDiaryModal() {
    const modal = document.createElement('div');
    modal.id = 'diaryModal';
    modal.className = 'modal-overlay';
    modal.innerHTML = `
        <div class="modal-content diary-modal">
            <button class="modal-close" onclick="closeDiaryModal()">
                <i class="bi bi-x-lg"></i>
            </button>
            <h2>I watched...</h2>
            <h3 id="diaryMovieTitle"></h3>
            <input type="hidden" id="diaryMovieId">
            
            <div class="diary-form">
                <div class="form-group">
                    <label>Date Watched</label>
                    <input type="date" id="diaryDate">
                </div>
                
                <div class="form-group">
                    <label>Rating</label>
                    <div class="star-rating" id="starRating">
                        <i class="bi bi-star" data-rating="1"></i>
                        <i class="bi bi-star" data-rating="2"></i>
                        <i class="bi bi-star" data-rating="3"></i>
                        <i class="bi bi-star" data-rating="4"></i>
                        <i class="bi bi-star" data-rating="5"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="diaryLiked">
                        <i class="bi bi-heart"></i> I liked this film
                    </label>
                </div>
                
                <div class="form-group">
                    <label>Review (optional)</label>
                    <textarea id="diaryReview" rows="3" placeholder="Write your thoughts..."></textarea>
                </div>
                
                <button class="btn-submit" onclick="submitDiaryEntry()">
                    <i class="bi bi-check-lg"></i> Save
                </button>
            </div>
        </div>
    `;

    // Star rating click handler
    modal.querySelectorAll('.star-rating i').forEach(star => {
        star.addEventListener('click', function () {
            const rating = parseInt(this.dataset.rating);
            modal.querySelectorAll('.star-rating i').forEach((s, index) => {
                if (index < rating) {
                    s.classList.remove('bi-star');
                    s.classList.add('bi-star-fill', 'active');
                } else {
                    s.classList.remove('bi-star-fill', 'active');
                    s.classList.add('bi-star');
                }
            });
        });
    });

    return modal;
}

async function submitDiaryEntry() {
    const movieId = document.getElementById('diaryMovieId').value;
    const watchedDate = document.getElementById('diaryDate').value;
    const liked = document.getElementById('diaryLiked').checked;
    const review = document.getElementById('diaryReview').value;

    // Count active stars
    const rating = document.querySelectorAll('#starRating i.active').length;

    const result = await markAsWatched(movieId, { watchedDate, rating, liked, review });

    if (result && result.success) {
        closeDiaryModal();
        // Update any watched buttons on page
        document.querySelectorAll(`[data-movie-id="${movieId}"].btn-watched`).forEach(btn => {
            btn.classList.add('watched');
            btn.innerHTML = '<i class="bi bi-eye-fill"></i> Watched';
        });
    }
}

// ==================== FAVORITES/LIKES ====================

/**
 * Toggle favorite/like on a movie
 */
async function toggleFavorite(movieId, button) {
    try {
        button.disabled = true;

        const response = await fetch(`${API_BASE}/favorites.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=toggle&movie_id=${movieId}`
        });

        const data = await response.json();

        if (data.success) {
            const icon = button.querySelector('i');
            if (data.liked) {
                icon.classList.remove('bi-heart');
                icon.classList.add('bi-heart-fill');
                button.classList.add('liked');
            } else {
                icon.classList.remove('bi-heart-fill');
                icon.classList.add('bi-heart');
                button.classList.remove('liked');
            }

            // Pulse animation
            button.classList.add('pulse');
            setTimeout(() => button.classList.remove('pulse'), 300);
        }
    } catch (error) {
        showNotification('Failed to update', 'error');
    } finally {
        button.disabled = false;
    }
}

// ==================== NOTIFICATIONS ====================

function showNotification(message, type = 'info') {
    // Remove existing notification
    const existing = document.querySelector('.notification');
    if (existing) existing.remove();

    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <i class="bi ${type === 'success' ? 'bi-check-circle-fill' : type === 'error' ? 'bi-x-circle-fill' : 'bi-info-circle-fill'}"></i>
        <span>${message}</span>
    `;

    notification.style.cssText = `
        position: fixed;
        top: 80px;
        right: 20px;
        background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#17a2b8'};
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        z-index: 10000;
        display: flex;
        align-items: center;
        gap: 10px;
        animation: slideIn 0.3s ease;
    `;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease forwards';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// ==================== INITIALIZATION ====================

document.addEventListener('DOMContentLoaded', function () {
    // Initialize watchlist buttons
    document.querySelectorAll('.btn-watchlist, .btn-add-watchlist').forEach(btn => {
        const movieId = btn.dataset.movieId;
        if (movieId) {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                toggleWatchlist(movieId, btn);
            });
        }
    });

    // Initialize like/favorite buttons  
    document.querySelectorAll('.btn-like, .btn-favorite').forEach(btn => {
        const movieId = btn.dataset.movieId;
        if (movieId) {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                toggleFavorite(movieId, btn);
            });
        }
    });

    // Initialize watched/diary buttons
    document.querySelectorAll('.btn-watched, .btn-diary').forEach(btn => {
        const movieId = btn.dataset.movieId;
        const movieTitle = btn.dataset.movieTitle || 'This Film';
        if (movieId) {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                openDiaryModal(movieId, movieTitle);
            });
        }
    });
});

// Add CSS animations
const animationStyles = document.createElement('style');
animationStyles.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    @keyframes fadeOut {
        from { opacity: 1; transform: scale(1); }
        to { opacity: 0; transform: scale(0.8); }
    }
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }
    .pulse { animation: pulse 0.3s ease; }
    .loading { opacity: 0.7; cursor: wait; }
    
    .btn-watchlist.in-watchlist,
    .btn-add-watchlist.in-watchlist {
        background: #e50914 !important;
        border-color: #e50914 !important;
    }
    
    .btn-like.liked i,
    .btn-favorite.liked i {
        color: #e50914;
    }
    
    /* Diary Modal Styles */
    .diary-modal {
        max-width: 400px;
    }
    .diary-modal h2 { margin-bottom: 5px; color: #888; font-size: 14px; }
    .diary-modal h3 { margin-bottom: 20px; color: #fff; }
    .diary-form .form-group { margin-bottom: 15px; }
    .diary-form label { display: block; margin-bottom: 5px; color: #ccc; }
    .diary-form input[type="date"],
    .diary-form textarea {
        width: 100%;
        padding: 10px;
        background: #1a1a1a;
        border: 1px solid #333;
        border-radius: 6px;
        color: #fff;
    }
    .star-rating { display: flex; gap: 8px; }
    .star-rating i {
        font-size: 24px;
        color: #555;
        cursor: pointer;
        transition: color 0.2s;
    }
    .star-rating i:hover,
    .star-rating i.active { color: #ffc107; }
    .btn-submit {
        width: 100%;
        padding: 12px;
        background: #e50914;
        border: none;
        border-radius: 6px;
        color: white;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    .btn-submit:hover { background: #b8070f; }
`;
document.head.appendChild(animationStyles);
