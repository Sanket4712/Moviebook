// Admin Panel JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Initialize charts if chart library is available
    const salesChart = document.getElementById('salesChart');
    if (salesChart) {
        // Placeholder for chart initialization
        // You would use a library like Chart.js here
    }
});

// Modal functions
function openAddModal() {
    const modal = document.getElementById('movieModal');
    if (modal) {
        modal.classList.add('active');
        modal.style.display = 'flex';
    }
}

function closeModal() {
    const modal = document.getElementById('movieModal');
    if (modal) {
        modal.classList.remove('active');
        modal.style.display = 'none';
    }
}

// Movie form submission
const movieForm = document.getElementById('movieForm');
if (movieForm) {
    movieForm.addEventListener('submit', function(e) {
        e.preventDefault();
        showNotification('Movie added successfully!', 'success');
        closeModal();
    });
}

// Toggle visibility
document.querySelectorAll('.btn-toggle').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        this.classList.toggle('active');
        const icon = this.querySelector('i');
        if (this.classList.contains('active')) {
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        } else {
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        }
    });
});

// Delete confirmation
document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        if (confirm('Are you sure you want to delete this item?')) {
            const card = this.closest('.admin-movie-card, tr');
            if (card) {
                card.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => {
                    card.remove();
                    showNotification('Item deleted successfully', 'success');
                }, 300);
            }
        }
    });
});

// User actions
document.querySelectorAll('.btn-action').forEach(btn => {
    btn.addEventListener('click', function() {
        const action = this.title.toLowerCase();
        showNotification(`${action} action performed`, 'success');
    });
});

// Pagination
document.querySelectorAll('.btn-page:not(:disabled)').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.btn-page').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        // Here you would load the respective page data
    });
});

// Search functionality
const searchInput = document.querySelector('.search-box input');
if (searchInput) {
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        // Here you would filter the table rows based on search term
    });
}

// Close modal when clicking outside
window.addEventListener('click', function(e) {
    const modal = document.getElementById('movieModal');
    if (modal && e.target === modal) {
        closeModal();
    }
});
