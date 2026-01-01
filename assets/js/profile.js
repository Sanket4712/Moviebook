// Profile Page JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Tab switching functionality
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            
            // Remove active class from all tabs
            tabBtns.forEach(b => b.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            
            // Add active class to clicked tab
            this.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        });
    });

    // Remove from watchlist
    const removeBtns = document.querySelectorAll('.btn-remove');
    removeBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const card = this.closest('.movie-card');
            card.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => {
                card.remove();
                showNotification('Removed from watchlist', 'success');
            }, 300);
        });
    });

    // Edit review
    const editBtns = document.querySelectorAll('.btn-edit');
    editBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            showNotification('Edit review feature coming soon!', 'success');
        });
    });

    // Delete review
    const deleteBtns = document.querySelectorAll('.btn-delete');
    deleteBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            if (confirm('Are you sure you want to delete this review?')) {
                const reviewCard = this.closest('.user-review-card');
                reviewCard.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => {
                    reviewCard.remove();
                    showNotification('Review deleted', 'success');
                }, 300);
            }
        });
    });

    // Edit avatar
    const editAvatarBtn = document.querySelector('.btn-edit-avatar');
    if (editAvatarBtn) {
        editAvatarBtn.addEventListener('click', function() {
            showNotification('Avatar upload feature coming soon!', 'success');
        });
    }
});
