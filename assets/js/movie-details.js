// Movie Details JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Get movie ID from URL
    const urlParams = new URLSearchParams(window.location.search);
    const movieId = urlParams.get('id');
    
    // Watchlist button
    const watchlistBtn = document.querySelector('.btn-watchlist');
    if (watchlistBtn) {
        watchlistBtn.addEventListener('click', function() {
            const icon = this.querySelector('i');
            if (icon.classList.contains('far')) {
                icon.classList.remove('far');
                icon.classList.add('fas');
                this.innerHTML = '<i class="fas fa-bookmark"></i> In Watchlist';
                showNotification('Added to watchlist', 'success');
            } else {
                icon.classList.remove('fas');
                icon.classList.add('far');
                this.innerHTML = '<i class="far fa-bookmark"></i> Watchlist';
                showNotification('Removed from watchlist', 'success');
            }
        });
    }

    // Rating button
    const rateBtn = document.querySelector('.btn-rate');
    if (rateBtn) {
        rateBtn.addEventListener('click', function() {
            showNotification('Rating feature coming soon!', 'success');
        });
    }

    // Share button
    const shareBtn = document.querySelector('.btn-share');
    if (shareBtn) {
        shareBtn.addEventListener('click', function() {
            if (navigator.share) {
                navigator.share({
                    title: document.querySelector('.movie-details h1').textContent,
                    text: 'Check out this movie!',
                    url: window.location.href
                });
            } else {
                showNotification('Link copied to clipboard', 'success');
            }
        });
    }

    // Review helpful button
    const helpfulBtns = document.querySelectorAll('.btn-review-action');
    helpfulBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const icon = this.querySelector('i');
            if (icon.classList.contains('far')) {
                icon.classList.remove('far');
                icon.classList.add('fas');
                showNotification('Thank you for your feedback!', 'success');
            }
        });
    });
});
