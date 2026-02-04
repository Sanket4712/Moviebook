/**
 * MovieBook - Premium Booking Experience
 * Refined BookMyShow-style Interactions
 */

// State Management
const state = {
    selectedDate: null,
    selectedTheater: null,
    selectedTime: null,
    selectedSeats: [],
    ticketPrice: 250,
    seatPrices: {
        premium: 350,
        executive: 280,
        regular: 180
    },
    currentStep: 1
};

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    generateDynamicDates();
    initializeDateCards();
    initializeTimeSlots();
    initializeSeats();
    updateUI();
});

// ==================== DATE GENERATION ====================

function generateDynamicDates() {
    const container = document.querySelector('.date-cards');
    if (!container) return;

    container.innerHTML = '';
    const days = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'];
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    for (let i = 0; i < 7; i++) {
        const date = new Date();
        date.setDate(date.getDate() + i);

        const card = document.createElement('div');
        card.className = 'date-card' + (i === 0 ? ' active' : '');
        card.dataset.date = date.toISOString().split('T')[0];
        card.dataset.display = `${months[date.getMonth()]} ${date.getDate()}`;
        card.innerHTML = `
            <span class="day">${days[date.getDay()]}</span>
            <span class="date">${date.getDate()} ${months[date.getMonth()]}</span>
        `;
        container.appendChild(card);

        if (i === 0) {
            state.selectedDate = card.dataset.display;
        }
    }

    initializeDateCards();
    updateUI();
}

// ==================== DATE SELECTION ====================

function initializeDateCards() {
    const cards = document.querySelectorAll('.date-card');
    cards.forEach(card => {
        card.addEventListener('click', () => {
            cards.forEach(c => c.classList.remove('active'));
            card.classList.add('active');
            state.selectedDate = card.dataset.display;

            // Visual feedback
            animateElement(card);
            updateUI();
        });
    });
}

// ==================== TIME SLOT SELECTION ====================

function initializeTimeSlots() {
    const slots = document.querySelectorAll('.time-slot');
    slots.forEach(slot => {
        slot.addEventListener('click', () => {
            const theater = slot.closest('.theater-card');
            const theaterName = theater.querySelector('h4').textContent;

            // Remove active from all slots
            slots.forEach(s => s.classList.remove('active'));
            slot.classList.add('active');

            state.selectedTheater = theaterName;
            state.selectedTime = slot.textContent.trim();
            state.ticketPrice = parseInt(slot.dataset.price) || 250;

            // Update seat prices based on slot price
            updateSeatPrices(state.ticketPrice);

            // Show seat selection
            showSeatSelection();

            // Update step
            updateStep(2);
            animateElement(slot);
            updateUI();
        });
    });
}

function updateSeatPrices(basePrice) {
    state.seatPrices = {
        premium: basePrice + 100,
        executive: basePrice + 30,
        regular: basePrice - 70
    };

    // Update price labels
    const premiumLabel = document.getElementById('premiumPrice');
    const executiveLabel = document.getElementById('executivePrice');
    const regularLabel = document.getElementById('regularPrice');

    if (premiumLabel) premiumLabel.textContent = `₹${state.seatPrices.premium}`;
    if (executiveLabel) executiveLabel.textContent = `₹${state.seatPrices.executive}`;
    if (regularLabel) regularLabel.textContent = `₹${state.seatPrices.regular}`;
}

function showSeatSelection() {
    const seatSection = document.querySelector('.seat-selection');
    if (!seatSection) return;

    seatSection.style.display = 'block';

    // Smooth scroll after animation starts
    setTimeout(() => {
        seatSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 100);
}

// ==================== SEAT SELECTION ====================

function initializeSeats() {
    const seats = document.querySelectorAll('.seat:not(.occupied):not(.small)');
    seats.forEach(seat => {
        seat.addEventListener('click', () => toggleSeat(seat));
    });
}

function toggleSeat(seat) {
    if (seat.classList.contains('occupied')) return;

    const index = state.selectedSeats.indexOf(seat);

    if (index > -1) {
        // Deselect
        seat.classList.remove('selected');
        state.selectedSeats.splice(index, 1);
    } else {
        // Select (max 10)
        if (state.selectedSeats.length >= 10) {
            showNotification('Maximum 10 seats allowed', 'error');
            shakElement(seat);
            return;
        }
        seat.classList.add('selected');
        state.selectedSeats.push(seat);
    }

    updateUI();
}

function getSeatLabel(seat) {
    const row = seat.closest('.seat-row');
    if (!row) return '';
    const rowLabel = row.querySelector('.row-label')?.textContent || '';
    const seatIndex = Array.from(row.querySelectorAll('.seat')).indexOf(seat) + 1;
    return rowLabel + seatIndex;
}

function calculateTotal() {
    let total = 0;
    state.selectedSeats.forEach(seat => {
        const category = seat.dataset.category || 'regular';
        total += state.seatPrices[category] || state.ticketPrice;
    });
    return total;
}

// ==================== STEP INDICATOR ====================

function updateStep(stepNumber) {
    const steps = document.querySelectorAll('.step');

    steps.forEach((step, index) => {
        const num = index + 1;
        step.classList.remove('active', 'completed');

        if (num < stepNumber) {
            step.classList.add('completed');
        } else if (num === stepNumber) {
            step.classList.add('active');
        }
    });

    state.currentStep = stepNumber;
}

// ==================== UI UPDATES ====================

function updateUI() {
    // Update summary
    updateElement('selectedDate', state.selectedDate || '-');
    updateElement('selectedTheater', state.selectedTheater || '-');
    updateElement('selectedTime', state.selectedTime || '-');

    // Seats
    const seatLabels = state.selectedSeats.map(s => getSeatLabel(s)).join(', ');
    updateElement('selectedSeats', seatLabels || '-');

    // Total
    const total = calculateTotal();
    const totalEl = document.getElementById('totalAmount');
    if (totalEl) {
        totalEl.textContent = `₹${total}`;
        if (total > 0) {
            totalEl.style.animation = 'none';
            totalEl.offsetHeight;
            totalEl.style.animation = 'valueFlash 0.3s ease';
        }
    }

    // Proceed button
    updateProceedButton();
}

function updateElement(id, value) {
    const el = document.getElementById(id);
    if (el && el.textContent !== value) {
        el.textContent = value;
        el.classList.add('summary-value-update');
        setTimeout(() => el.classList.remove('summary-value-update'), 300);
    }
}

function updateProceedButton() {
    const btn = document.querySelector('.btn-proceed');
    const textEl = document.getElementById('proceedText');
    if (!btn || !textEl) return;

    const canProceed = state.selectedSeats.length > 0 && state.selectedTime && state.selectedDate;
    btn.disabled = !canProceed;

    if (!state.selectedTime) {
        textEl.textContent = 'Select show to continue';
    } else if (state.selectedSeats.length === 0) {
        textEl.textContent = 'Select seats to continue';
    } else {
        textEl.textContent = `Pay ₹${calculateTotal()}`;
    }
}

// ==================== PAYMENT ====================

function proceedToPayment() {
    if (state.selectedSeats.length === 0) {
        showNotification('Please select at least one seat', 'error');
        return;
    }

    const btn = document.querySelector('.btn-proceed');
    const textEl = document.getElementById('proceedText');

    btn.classList.add('loading');
    textEl.textContent = 'Processing...';
    btn.disabled = true;

    updateStep(3);

    // Simulate payment redirect
    setTimeout(() => {
        const bookingData = {
            movie: '12 Angry Men',
            date: state.selectedDate,
            time: state.selectedTime,
            theater: state.selectedTheater,
            seats: state.selectedSeats.map(s => getSeatLabel(s)),
            total: calculateTotal()
        };

        sessionStorage.setItem('moviebook_booking', JSON.stringify(bookingData));

        showNotification('Booking confirmed! Redirecting...', 'success');

        // For demo - show success
        setTimeout(() => {
            btn.classList.remove('loading');
            textEl.textContent = '✓ Booking Complete';
            btn.style.background = '#1db954';
        }, 1500);

    }, 1500);
}

// ==================== ANIMATIONS & UTILITIES ====================

function animateElement(el) {
    el.style.transform = 'scale(0.95)';
    setTimeout(() => {
        el.style.transform = '';
    }, 100);
}

function shakElement(el) {
    el.style.animation = 'shake 0.3s ease';
    setTimeout(() => {
        el.style.animation = '';
    }, 300);
}

function showNotification(message, type = 'info') {
    // Remove existing
    document.querySelectorAll('.booking-notification').forEach(n => n.remove());

    const notification = document.createElement('div');
    notification.className = `booking-notification notification-${type}`;

    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        info: 'fa-info-circle'
    };

    const colors = {
        success: '#1db954',
        error: '#e50914',
        info: '#3b82f6'
    };

    notification.innerHTML = `
        <i class="fas ${icons[type]}"></i>
        <span>${message}</span>
    `;

    Object.assign(notification.style, {
        position: 'fixed',
        top: '100px',
        right: '20px',
        background: colors[type],
        color: 'white',
        padding: '0.85rem 1.25rem',
        borderRadius: '10px',
        boxShadow: '0 8px 24px rgba(0,0,0,0.3)',
        zIndex: '10000',
        display: 'flex',
        alignItems: 'center',
        gap: '0.6rem',
        fontSize: '0.9rem',
        fontWeight: '500',
        animation: 'slideInRight 0.3s ease'
    });

    document.body.appendChild(notification);

    // Add keyframes if not exist
    if (!document.getElementById('notif-keyframes')) {
        const style = document.createElement('style');
        style.id = 'notif-keyframes';
        style.textContent = `
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOutRight {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
            @keyframes shake {
                0%, 100% { transform: translateX(0); }
                25% { transform: translateX(-4px); }
                75% { transform: translateX(4px); }
            }
            @keyframes valueFlash {
                0% { transform: scale(1); }
                50% { transform: scale(1.08); color: #e50914; }
                100% { transform: scale(1); }
            }
        `;
        document.head.appendChild(style);
    }

    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease forwards';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}
