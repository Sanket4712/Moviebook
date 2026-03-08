// Movies Page JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Filter functionality
    const filterBtns = document.querySelectorAll('.filter-btn');
    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            filterBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            // Here you would filter the movies based on the selected category
        });
    });

    // Bookmark functionality
    const bookmarkBtns = document.querySelectorAll('.btn-bookmark');
    bookmarkBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const icon = this.querySelector('i');
            if (icon.classList.contains('far')) {
                icon.classList.remove('far');
                icon.classList.add('fas');
                showNotification('Added to watchlist', 'success');
            } else {
                icon.classList.remove('fas');
                icon.classList.add('far');
                showNotification('Removed from watchlist', 'success');
            }
        });
    });
});

// Sample movie data
const movies = [
    {
        id: 1,
        title: '12 Angry Men',
        genre: 'Drama',
        rating: 8.9,
        year: 1957,
        duration: '96 min'
    },
    {
        id: 2,
        title: 'Inception',
        genre: 'Sci-Fi',
        rating: 8.8,
        year: 2010,
        duration: '148 min'
    }
    // Add more movies as needed
];
