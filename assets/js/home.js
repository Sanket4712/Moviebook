// Movie Booking Home Page JavaScript

document.addEventListener('DOMContentLoaded', function () {
    // Check if user is logged in
    checkUserLogin();

    // Add event listeners to all book ticket buttons (EXCLUDE watchlist buttons)
    const bookButtons = document.querySelectorAll('.btn-book-ticket:not(.btn-watchlist)');
    bookButtons.forEach(btn => {
        btn.addEventListener('click', handleBookTicket);
    });

    // Add event listeners to notify buttons
    const notifyButtons = document.querySelectorAll('.btn-notify');
    notifyButtons.forEach(btn => {
        btn.addEventListener('click', handleNotify);
    });

    // Location selector
    const locationSelector = document.querySelector('.location');
    if (locationSelector) {
        locationSelector.addEventListener('click', showLocationModal);
    }

    // Search functionality
    const searchInput = document.querySelector('.search-box input');
    if (searchInput) {
        searchInput.addEventListener('input', handleSearch);
    }
});

// Check if user is logged in
function checkUserLogin() {
    const user = JSON.parse(localStorage.getItem('moviebook_user') || sessionStorage.getItem('moviebook_user') || 'null');

    if (!user || !user.loggedIn) {
        // User not logged in - session is managed by PHP
        // Skip client-side redirect as PHP handles auth
        return;
    }

    console.log('User logged in:', user.name || user.email);
}

// Handle book ticket button click
function handleBookTicket(e) {
    const movieCard = e.target.closest('.movie-card');
    const movieTitle = movieCard.querySelector('h3').textContent;

    // Store selected movie and redirect to booking page
    sessionStorage.setItem('selectedMovie', movieTitle);
    window.location.href = 'booking.php';
}

// Handle notify button click
function handleNotify(e) {
    const movieCard = e.target.closest('.movie-card');
    const movieTitle = movieCard.querySelector('h3').textContent;

    showNotification(`You will be notified when ${movieTitle} is released!`, 'success');
}

// Show location selection modal
function showLocationModal() {
    // For demo, just show alert
    const locations = ['Kolhapur', 'Delhi', 'Bangalore', 'Pune', 'Hyderabad', 'Chennai'];
    const selected = prompt('Select your city:\n\n' + locations.join('\n'));

    if (selected && locations.includes(selected)) {
        document.querySelector('.location span').textContent = selected;
        localStorage.setItem('moviebook_location', selected);
        showNotification(`Location changed to ${selected}`, 'success');
    }
}

// Handle search
function handleSearch(e) {
    const query = e.target.value.toLowerCase().trim();

    if (query.length < 2) {
        return;
    }

    // Simple search implementation
    const movieCards = document.querySelectorAll('.movie-card');
    movieCards.forEach(card => {
        const title = card.querySelector('h3').textContent.toLowerCase();
        const genre = card.querySelector('.genre')?.textContent.toLowerCase() || '';

        if (title.includes(query) || genre.includes(query)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

// Show notification
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;

    // Style the notification
    notification.style.cssText = `
        position: fixed;
        top: 80px;
        right: 20px;
        background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#17a2b8'};
        color: white;
        padding: 15px 25px;
        border-radius: 4px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        z-index: 10000;
        animation: slideIn 0.3s ease;
        font-size: 14px;
        font-weight: 500;
    `;

    document.body.appendChild(notification);

    // Remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
