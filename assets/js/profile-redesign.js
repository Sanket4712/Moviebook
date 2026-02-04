// Profile Page Redesign - JavaScript
// Tab navigation and interactions

// Handle sign out
function handleSignOut() {
    window.location.href = '../auth/logout.php';
}

document.addEventListener('DOMContentLoaded', function () {
    // Tab switching functionality
    const profileTabs = document.querySelectorAll('.profile-tab');
    const tabContents = document.querySelectorAll('.tab-content');

    profileTabs.forEach(tab => {
        tab.addEventListener('click', function () {
            const targetTab = this.dataset.tab;

            // Remove active states from all
            profileTabs.forEach(t => t.classList.remove('active'));
            tabContents.forEach(c => {
                c.classList.remove('active');
                c.style.animation = 'none';
            });

            // Add active state to clicked tab
            this.classList.add('active');

            // Show target content with animation
            const targetContent = document.getElementById(targetTab);
            if (targetContent) {
                // Trigger reflow to restart animation
                targetContent.offsetHeight;
                targetContent.style.animation = null;
                targetContent.classList.add('active');
            }
        });
    });

    // Like button toggle
    const actionBtns = document.querySelectorAll('.action-btn');
    actionBtns.forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            const icon = this.querySelector('i');

            if (icon.classList.contains('bi-heart')) {
                icon.classList.remove('bi-heart');
                icon.classList.add('bi-heart-fill');
                this.classList.add('liked');
            } else if (icon.classList.contains('bi-heart-fill')) {
                icon.classList.remove('bi-heart-fill');
                icon.classList.add('bi-heart');
                this.classList.remove('liked');
            }
        });
    });

    // Poster card click handler
    const posterCards = document.querySelectorAll('.poster-card');
    posterCards.forEach(card => {
        card.addEventListener('click', function () {
            // Could navigate to film details page
            console.log('Opening film details...');
        });
    });

    // Activity item click handler
    const activityItems = document.querySelectorAll('.activity-item');
    activityItems.forEach(item => {
        item.addEventListener('click', function () {
            console.log('Opening activity details...');
        });
    });

    // Diary entry click handler
    const diaryEntries = document.querySelectorAll('.diary-entry');
    diaryEntries.forEach(entry => {
        entry.addEventListener('click', function () {
            console.log('Opening diary entry...');
        });
    });

    // List block click handler
    const listBlocks = document.querySelectorAll('.list-block');
    listBlocks.forEach(block => {
        block.addEventListener('click', function () {
            console.log('Opening list...');
        });
    });

    // New list button
    const newListBtn = document.querySelector('.btn-new-list');
    if (newListBtn) {
        newListBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            alert('Create new list feature coming soon!');
        });
    }

    // Intersection Observer for scroll animations
    const observerOptions = {
        root: null,
        rootMargin: '0px',
        threshold: 0.1
    };

    const animateOnScroll = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    // Observe content sections
    document.querySelectorAll('.content-section, .diary-month, .list-block').forEach(el => {
        animateOnScroll.observe(el);
    });
});
