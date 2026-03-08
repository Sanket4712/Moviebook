// Theater Panel JavaScript

document.addEventListener('DOMContentLoaded', function() {
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
    const modal = document.getElementById('scheduleModal');
    if (modal) {
        modal.classList.add('active');
        modal.style.display = 'flex';
    }
}

function closeScheduleModal() {
    const modal = document.getElementById('scheduleModal');
    if (modal) {
        modal.classList.remove('active');
        modal.style.display = 'none';
    }
}

// Schedule form submission
const scheduleForm = document.getElementById('scheduleForm');
if (scheduleForm) {
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
