// Theater Panel JavaScript

let selectedMovieId = null;
let selectedMovieName = null;

document.addEventListener('DOMContentLoaded', function() {
    // Load scheduled shows on page load
    loadScheduledShows();
    setMinDate();
    updateMovieCardsWithSchedules();
    
    // Filter buttons
    const filterBtns = document.querySelectorAll('.filter-btn');
    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            filterBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
        });
    });

    // Edit show
    document.querySelectorAll('.btn-edit-show').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            showNotification('Edit show feature coming soon!', 'success');
        });
    });

    // Delete show
    document.querySelectorAll('.btn-delete-show').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (confirm('Are you sure you want to delete this show?')) {
                this.closest('.show-time-chip').remove();
                showNotification('Show deleted successfully', 'success');
            }
        });
    });

    // Add show
    document.querySelectorAll('.btn-add-show').forEach(btn => {
        btn.addEventListener('click', function() {
            openScheduleModal();
        });
    });

    // Sell ticket
    document.querySelectorAll('.btn-sell-ticket:not(:disabled)').forEach(btn => {
        btn.addEventListener('click', function() {
            openSellModal();
        });
    });

    // Booking actions
    document.querySelectorAll('.btn-confirm').forEach(btn => {
        btn.addEventListener('click', function() {
            const card = this.closest('.booking-card');
            card.classList.remove('pending');
            card.classList.add('confirmed');
            card.querySelector('.status-badge').textContent = 'Confirmed';
            card.querySelector('.status-badge').classList.remove('yellow');
            card.querySelector('.status-badge').classList.add('green');
            this.parentElement.innerHTML = `
                <button class="btn-view"><i class="fas fa-eye"></i> View</button>
                <button class="btn-print"><i class="fas fa-print"></i> Print</button>
            `;
            showNotification('Booking confirmed successfully', 'success');
        });
    });

    document.querySelectorAll('.btn-cancel').forEach(btn => {
        btn.addEventListener('click', function() {
            if (confirm('Are you sure you want to cancel this booking?')) {
                const card = this.closest('.booking-card');
                card.classList.remove('pending');
                card.classList.add('cancelled');
                card.querySelector('.status-badge').textContent = 'Cancelled';
                card.querySelector('.status-badge').classList.remove('yellow');
                card.querySelector('.status-badge').classList.add('red');
                showNotification('Booking cancelled', 'success');
            }
        });
    });

    // View and print
    document.querySelectorAll('.btn-view').forEach(btn => {
        btn.addEventListener('click', function() {
            showNotification('View details feature coming soon!', 'success');
        });
    });

    document.querySelectorAll('.btn-print').forEach(btn => {
        btn.addEventListener('click', function() {
            showNotification('Printing ticket...', 'success');
        });
    });
});

// Schedule Modal
function openScheduleModal() {
    selectedMovieId = null;
    selectedMovieName = null;
    const modal = document.getElementById('scheduleModal');
    if (modal) {
        modal.classList.add('active');
        modal.style.display = 'flex';
        // Reset search
        const searchInput = document.getElementById('modalMovieSearch');
        if (searchInput) searchInput.value = '';
        searchMoviesInModal();
    }
}

function openScheduleModalWithMovie(movieId, movieName) {
    // Directly open the schedule details modal with pre-selected movie
    selectMovieForSchedule(movieId, movieName);
}

function closeScheduleModal() {
    const modal = document.getElementById('scheduleModal');
    if (modal) {
        modal.classList.remove('active');
        modal.style.display = 'none';
    }
}

function closeScheduleDetailsModal() {
    const modal = document.getElementById('scheduleDetailsModal');
    if (modal) {
        modal.classList.remove('active');
        modal.style.display = 'none';
        // Reset form
        const form = document.getElementById('scheduleForm');
        if (form) form.reset();
    }
}

// Search movies in modal
function searchMoviesInModal() {
    const searchInput = document.getElementById('modalMovieSearch');
    const searchValue = searchInput.value.toLowerCase().trim();
    const movieItems = document.querySelectorAll('.modal-movie-item');
    
    let visibleCount = 0;
    
    movieItems.forEach(item => {
        const movieName = item.dataset.movieName?.toLowerCase() || '';
        const genre = item.dataset.genre?.toLowerCase() || '';
        
        if (movieName.includes(searchValue) || genre.includes(searchValue)) {
            item.style.display = 'block';
            visibleCount++;
        } else {
            item.style.display = 'none';
        }
    });
}

// Select movie and open schedule details modal
function selectMovieForSchedule(movieId, movieName) {
    selectedMovieId = movieId;
    selectedMovieName = movieName;
    
    // Close movie selection modal
    closeScheduleModal();
    
    // Open schedule details modal
    const detailsModal = document.getElementById('scheduleDetailsModal');
    if (detailsModal) {
        detailsModal.classList.add('active');
        detailsModal.style.display = 'flex';
        
        // Set movie title
        const titleElement = document.getElementById('selectedMovieTitle');
        if (titleElement) titleElement.textContent = movieName;
        
        // Set hidden movie select value
        const movieSelect = document.getElementById('movieSelect');
        if (movieSelect) movieSelect.value = movieId;
        
        // Set min date
        setMinDate();
    }
}

// Schedule form submission
function handleScheduleSubmit(event) {
    event.preventDefault();
    
    const formData = {
        movie: document.getElementById('movieSelect').value,
        screen: document.getElementById('screenSelect').value,
        date: document.getElementById('showDate').value,
        time: document.getElementById('showTime').value,
        price: document.getElementById('ticketPrice').value,
        seats: document.getElementById('totalSeats').value,
        type: document.getElementById('showType').value,
        language: document.getElementById('language').value
    };
    
    // Validate date is not in the past
    const selectedDate = new Date(formData.date + 'T' + formData.time);
    const now = new Date();
    
    if (selectedDate < now) {
        showNotification('Cannot schedule shows in the past!', 'error');
        return;
    }
    
    // Store in localStorage (in real app, this would be sent to server)
    let scheduledShows = JSON.parse(localStorage.getItem('scheduledShows') || '[]');
    
    const newShow = {
        id: Date.now(),
        movieName: document.getElementById('movieSelect').selectedOptions[0].text,
        screenName: document.getElementById('screenSelect').selectedOptions[0].text,
        ...formData,
        bookedSeats: 0,
        createdAt: new Date().toISOString()
    };
    
    scheduledShows.push(newShow);
    localStorage.setItem('scheduledShows', JSON.stringify(scheduledShows));
    
    // Add show to the list
    addShowToList(newShow);
    
    // Update movie card with schedule
    updateMovieCardSchedule(formData.movie, newShow);
    
    showNotification('Show scheduled successfully!', 'success');
    closeScheduleModal();
}

// Add show to the scheduled shows list
function addShowToList(show) {
    const showsList = document.getElementById('scheduledShowsList');
    if (!showsList) return;
    
    const showCard = document.createElement('div');
    showCard.className = 'scheduled-show-card';
    showCard.dataset.showId = show.id;
    
    const formatDate = new Date(show.date).toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric', 
        year: 'numeric' 
    });
    
    const formatTime = new Date('2000-01-01T' + show.time).toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        hour12: true 
    });
    
    const availableSeats = parseInt(show.seats) - parseInt(show.bookedSeats || 0);
    const revenue = (parseInt(show.bookedSeats || 0) * parseFloat(show.price)).toFixed(2);
    
    showCard.innerHTML = `
        <div class="show-movie-info">
            <img src="../../Theater/images/image.png" alt="${show.movieName}">
            <div class="show-details">
                <h4>${show.movieName}</h4>
                <p class="show-meta">
                    <span><i class="bi bi-calendar3"></i> ${formatDate}</span>
                    <span><i class="bi bi-clock"></i> ${formatTime}</span>
                    <span><i class="bi bi-display"></i> ${show.screenName.split(' ')[0] + ' ' + show.screenName.split(' ')[1]}</span>
                </p>
                <p class="show-meta">
                    <span class="badge">${show.type.toUpperCase()}</span>
                    <span>${show.language.charAt(0).toUpperCase() + show.language.slice(1)}</span>
                    <span class="price">$${show.price}</span>
                </p>
            </div>
        </div>
        <div class="show-stats">
            <div class="stat-item">
                <span class="stat-label">Seats Available</span>
                <span class="stat-value">${availableSeats}/${show.seats}</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Revenue</span>
                <span class="stat-value">$${revenue}</span>
            </div>
        </div>
        <div class="show-actions">
            <button class="btn-icon" title="Edit Show" onclick="editShow(${show.id})">
                <i class="bi bi-pencil"></i>
            </button>
            <button class="btn-icon danger" title="Cancel Show" onclick="cancelShow(this, ${show.id})">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;
    
    showsList.insertBefore(showCard, showsList.firstChild);
}

// Load scheduled shows on page load
document.addEventListener('DOMContentLoaded', function() {
    loadScheduledShows();
    setMinDate();
    updateMovieCardsWithSchedules();
});

// Movie Search Functionality
function searchMovies() {
    const searchInput = document.getElementById('movieSearchInput');
    const clearBtn = document.getElementById('clearSearch');
    const searchValue = searchInput.value.toLowerCase().trim();
    const movieCards = document.querySelectorAll('.movie-card');
    
    // Show/hide clear button
    if (searchValue) {
        clearBtn.style.display = 'flex';
    } else {
        clearBtn.style.display = 'none';
    }
    
    let visibleCount = 0;
    
    movieCards.forEach(card => {
        const movieName = card.dataset.movieName?.toLowerCase() || '';
        const genre = card.dataset.genre?.toLowerCase() || '';
        const rating = card.dataset.rating || '';
        
        if (movieName.includes(searchValue) || 
            genre.includes(searchValue) || 
            rating.includes(searchValue)) {
            card.style.display = 'block';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
    
    // Show no results message
    let noResults = document.getElementById('noResultsMessage');
    if (visibleCount === 0 && searchValue) {
        if (!noResults) {
            noResults = document.createElement('div');
            noResults.id = 'noResultsMessage';
            noResults.className = 'no-results-message';
            noResults.innerHTML = `
                <i class="bi bi-film"></i>
                <h3>No movies found</h3>
                <p>Try searching with different keywords</p>
            `;
            document.getElementById('movieGrid').appendChild(noResults);
        }
        noResults.style.display = 'flex';
    } else if (noResults) {
        noResults.style.display = 'none';
    }
}

function clearSearch() {
    const searchInput = document.getElementById('movieSearchInput');
    const clearBtn = document.getElementById('clearSearch');
    searchInput.value = '';
    clearBtn.style.display = 'none';
    searchMovies();
}

// Update movie cards with scheduled shows
function updateMovieCardsWithSchedules() {
    const scheduledShows = JSON.parse(localStorage.getItem('scheduledShows') || '[]');
    
    // Clear all movie card schedules first
    document.querySelectorAll('.scheduled-shows').forEach(container => {
        container.innerHTML = '';
    });
    
    // Group shows by movie
    const showsByMovie = {};
    scheduledShows.forEach(show => {
        if (!showsByMovie[show.movie]) {
            showsByMovie[show.movie] = [];
        }
        showsByMovie[show.movie].push(show);
    });
    
    // Update each movie card
    Object.keys(showsByMovie).forEach(movieId => {
        const shows = showsByMovie[movieId];
        shows.forEach(show => {
            updateMovieCardSchedule(movieId, show);
        });
    });
}

// Update individual movie card with schedule
function updateMovieCardSchedule(movieId, show) {
    const container = document.querySelector(`.scheduled-shows[data-movie-id="${movieId}"]`);
    if (!container) return;
    
    const formatDate = new Date(show.date).toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric'
    });
    
    const formatTime = new Date('2000-01-01T' + show.time).toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        hour12: true 
    });
    
    // Check if this show already exists in the card
    const existingShow = container.querySelector(`[data-show-id="${show.id}"]`);
    if (existingShow) return;
    
    const showChip = document.createElement('div');
    showChip.className = 'show-chip';
    showChip.dataset.showId = show.id;
    showChip.innerHTML = `
        <span class="show-datetime">
            <i class="bi bi-calendar3"></i> ${formatDate} • ${formatTime}
        </span>
        <span class="show-screen">${show.screenName.split(' ')[0]} ${show.screenName.split(' ')[1]}</span>
        <button class="btn-remove-show" onclick="removeShowFromCard(${show.id}, '${movieId}')" title="Cancel this show">
            <i class="bi bi-x"></i>
        </button>
    `;
    
    container.appendChild(showChip);
    
    // Container is always visible now, no need to toggle display
}

// Remove show from movie card
function removeShowFromCard(showId, movieId) {
    if (confirm('Are you sure you want to cancel this show?')) {
        // Remove from localStorage
        let scheduledShows = JSON.parse(localStorage.getItem('scheduledShows') || '[]');
        scheduledShows = scheduledShows.filter(show => show.id !== showId);
        localStorage.setItem('scheduledShows', JSON.stringify(scheduledShows));
        
        // Remove from card UI
        const container = document.querySelector(`.scheduled-shows[data-movie-id="${movieId}"]`);
        if (container) {
            const showChip = container.querySelector(`[data-show-id="${showId}"]`);
            if (showChip) {
                showChip.style.animation = 'fadeOut 0.3s ease';
                setTimeout(() => {
                    showChip.remove();
                }, 300);
            }
        }
        
        // Remove from scheduled shows list
        const showCard = document.querySelector(`.scheduled-show-card[data-show-id="${showId}"]`);
        if (showCard) {
            showCard.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => showCard.remove(), 300);
        }
        
        showNotification('Show cancelled successfully', 'success');
    }
}

function loadScheduledShows() {
    const scheduledShows = JSON.parse(localStorage.getItem('scheduledShows') || '[]');
    const showsList = document.getElementById('scheduledShowsList');
    
    if (showsList && scheduledShows.length > 0) {
        // Clear sample shows except the first one (keep as example)
        const sampleShow = showsList.querySelector('.scheduled-show-card');
        
        scheduledShows.reverse().forEach(show => {
            addShowToList(show);
        });
    }
}

// Set minimum date to today
function setMinDate() {
    const dateInput = document.getElementById('showDate');
    if (dateInput) {
        const today = new Date().toISOString().split('T')[0];
        dateInput.min = today;
        dateInput.value = today;
    }
}

// Edit show function
function editShow(showId) {
    showNotification('Edit functionality coming soon!', 'success');
    // TODO: Implement edit functionality
}

// Cancel show function
function cancelShow(button, showId) {
    if (confirm('Are you sure you want to cancel this show? This action cannot be undone.')) {
        if (showId) {
            // Remove from localStorage
            let scheduledShows = JSON.parse(localStorage.getItem('scheduledShows') || '[]');
            scheduledShows = scheduledShows.filter(show => show.id !== showId);
            localStorage.setItem('scheduledShows', JSON.stringify(scheduledShows));
        }
        
        // Remove from UI
        const showCard = button.closest('.scheduled-show-card');
        showCard.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => showCard.remove(), 300);
        
        showNotification('Show cancelled successfully', 'success');
    }
}

// Filter shows
document.addEventListener('DOMContentLoaded', function() {
    const filterBtns = document.querySelectorAll('.filter-btn');
    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            filterBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            const filter = this.dataset.filter;
            filterShows(filter);
        });
    });
});

function filterShows(filter) {
    const showCards = document.querySelectorAll('.scheduled-show-card');
    const today = new Date().toISOString().split('T')[0];
    
    showCards.forEach(card => {
        const dateText = card.querySelector('.show-meta span:first-child').textContent;
        // For now, show all. In production, implement proper date filtering
        card.style.display = 'flex';
    });
}

// Schedule form submission (legacy support)
const scheduleForm = document.getElementById('scheduleForm');
if (scheduleForm && !scheduleForm.hasAttribute('onsubmit')) {
    scheduleForm.addEventListener('submit', function(e) {
        e.preventDefault();
        showNotification('Show scheduled successfully!', 'success');
        closeScheduleModal();
    });
}

// Sell Ticket Modal
function openSellModal() {
    const modal = document.getElementById('sellTicketModal');
    if (modal) {
        modal.classList.add('active');
        // Disable background scroll
        document.body.classList.add('modal-open');
    }
}

function closeSellModal() {
    const modal = document.getElementById('sellTicketModal');
    if (modal) {
        modal.classList.remove('active');
        // Re-enable background scroll
        document.body.classList.remove('modal-open');
    }
}

// Sell ticket form
const sellTicketForm = document.getElementById('sellTicketForm');
if (sellTicketForm) {
    sellTicketForm.addEventListener('submit', function(e) {
        e.preventDefault();
        showNotification('Ticket sold successfully!', 'success');
        closeSellModal();
    });

    // Update total on quantity change
    const quantityInput = sellTicketForm.querySelector('input[type="number"]');
    if (quantityInput) {
        quantityInput.addEventListener('input', function() {
            const quantity = parseInt(this.value) || 0;
            const price = 15.00;
            const total = quantity * price;
            sellTicketForm.querySelector('.price-row:nth-child(2) span:last-child').textContent = quantity;
            sellTicketForm.querySelector('.price-row.total span:last-child').textContent = '$' + total.toFixed(2);
        });
    }
}

// Close modals when clicking outside
window.addEventListener('click', function(e) {
    const scheduleModal = document.getElementById('scheduleModal');
    const sellModal = document.getElementById('sellTicketModal');
    
    if (scheduleModal && e.target === scheduleModal) {
        closeScheduleModal();
    }
    if (sellModal && e.target === sellModal) {
        closeSellModal();
    }
});

// Close modal with Escape key
window.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const sellModal = document.getElementById('sellTicketModal');
        if (sellModal && sellModal.classList.contains('active')) {
            closeSellModal();
        }
    }
});
