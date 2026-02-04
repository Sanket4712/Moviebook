/**
 * MovieBook - Tickets Page JavaScript
 * Premium animations and interactions
 */

document.addEventListener('DOMContentLoaded', function () {
    initScrollAnimations();
    initSearch();
    initLocationSelector();
});

/**
 * Initialize scroll-based fade-in animations
 */
function initScrollAnimations() {
    const sections = document.querySelectorAll('.fade-in-section');

    if (!sections.length) return;

    const observerOptions = {
        root: null,
        rootMargin: '0px 0px -100px 0px',
        threshold: 0.1
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                // Stop observing once visible
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    sections.forEach(section => {
        observer.observe(section);
    });

    // Fallback: make visible after a short delay if observer doesn't trigger
    setTimeout(() => {
        sections.forEach(section => {
            if (!section.classList.contains('visible')) {
                section.classList.add('visible');
            }
        });
    }, 1500);
}

/**
 * Initialize movie search functionality
 */
function initSearch() {
    const searchInput = document.getElementById('movieSearch');
    if (!searchInput) return;

    let debounceTimer;

    searchInput.addEventListener('input', function (e) {
        clearTimeout(debounceTimer);
        const query = e.target.value.toLowerCase().trim();

        debounceTimer = setTimeout(() => {
            filterMovies(query);
        }, 300);
    });

    // Clear search on escape
    searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            searchInput.value = '';
            filterMovies('');
        }
    });
}

/**
 * Filter movies across all sections
 */
function filterMovies(query) {
    const allCards = document.querySelectorAll('.trending-card, .movie-card');

    if (!query) {
        // Show all cards when query is empty
        allCards.forEach(card => {
            card.style.display = '';
            card.style.opacity = '1';
        });
        return;
    }

    allCards.forEach(card => {
        const title = card.querySelector('h3')?.textContent.toLowerCase() || '';
        const genre = card.querySelector('.genre')?.textContent.toLowerCase() || '';

        if (title.includes(query) || genre.includes(query)) {
            card.style.display = '';
            card.style.opacity = '1';
        } else {
            card.style.opacity = '0.3';
        }
    });
}

/**
 * Initialize location selector
 */
function initLocationSelector() {
    const locationSelector = document.querySelector('.location');
    if (!locationSelector) return;

    locationSelector.addEventListener('click', function () {
        const cities = ['Kolhapur', 'Mumbai', 'Delhi', 'Bangalore', 'Pune', 'Hyderabad', 'Chennai'];
        const currentCity = locationSelector.querySelector('span').textContent;

        const selected = prompt(
            `Select your city:\n\nCurrent: ${currentCity}\n\nAvailable:\n${cities.join('\n')}`
        );

        if (selected && cities.map(c => c.toLowerCase()).includes(selected.toLowerCase())) {
            const matchedCity = cities.find(c => c.toLowerCase() === selected.toLowerCase());
            locationSelector.querySelector('span').textContent = matchedCity;
            localStorage.setItem('moviebook_location', matchedCity);
            showNotification(`Location changed to ${matchedCity}`, 'success');
        }
    });

    // Restore saved location
    const savedLocation = localStorage.getItem('moviebook_location');
    if (savedLocation) {
        const locationSpan = locationSelector.querySelector('span');
        if (locationSpan) {
            locationSpan.textContent = savedLocation;
        }
    }
}

/**
 * Show notification toast
 */
function showNotification(message, type = 'success') {
    // Remove existing notifications
    const existing = document.querySelector('.notification-toast');
    if (existing) {
        existing.remove();
    }

    const toast = document.createElement('div');
    toast.className = `notification-toast ${type}`;
    toast.textContent = message;

    document.body.appendChild(toast);

    // Auto-remove after 3 seconds
    setTimeout(() => {
        toast.style.animation = 'toastOut 0.4s ease forwards';
        setTimeout(() => {
            toast.remove();
        }, 400);
    }, 3000);
}

/**
 * Notify user about upcoming movie
 */
function notifyMe(movieId) {
    // In a real app, this would call an API
    showNotification('You will be notified when this movie is available!', 'success');

    // Store notification preference
    const notifications = JSON.parse(localStorage.getItem('moviebook_notifications') || '[]');
    if (!notifications.includes(movieId)) {
        notifications.push(movieId);
        localStorage.setItem('moviebook_notifications', JSON.stringify(notifications));
    }
}

// Make showNotification available globally
window.showNotification = showNotification;
window.notifyMe = notifyMe;
