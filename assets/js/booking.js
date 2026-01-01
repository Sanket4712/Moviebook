// Booking Page JavaScript

let selectedSeats = [];
let ticketPrice = 15.00;

document.addEventListener('DOMContentLoaded', function() {
    // Show seat selection on time slot selection
    const timeSlots = document.querySelectorAll('.time-slot');
    timeSlots.forEach(slot => {
        slot.addEventListener('click', function() {
            timeSlots.forEach(s => s.classList.remove('active'));
            this.classList.add('active');
            
            // Show seat selection section
            document.querySelector('.seat-selection').style.display = 'block';
            document.querySelector('.seat-selection').scrollIntoView({ behavior: 'smooth' });
        });
    });

    // Seat selection functionality
    const seats = document.querySelectorAll('.seat:not(.occupied)');
    seats.forEach(seat => {
        seat.addEventListener('click', function() {
            if (this.classList.contains('selected')) {
                this.classList.remove('selected');
                const index = selectedSeats.indexOf(this);
                if (index > -1) {
                    selectedSeats.splice(index, 1);
                }
            } else {
                if (selectedSeats.length < 10) {
                    this.classList.add('selected');
                    selectedSeats.push(this);
                } else {
                    showNotification('Maximum 10 seats can be selected', 'error');
                }
            }
            updateBookingSummary();
        });
    });

    // Date selection
    const dateCards = document.querySelectorAll('.date-card');
    dateCards.forEach(card => {
        card.addEventListener('click', function() {
            dateCards.forEach(c => c.classList.remove('active'));
            this.classList.add('active');
        });
    });
});

function updateBookingSummary() {
    const seatLabels = selectedSeats.map(seat => {
        const row = seat.closest('.seat-row');
        const rowLabel = row.querySelector('.row-label').textContent;
        const seatIndex = Array.from(row.querySelectorAll('.seat')).indexOf(seat) + 1;
        return rowLabel + seatIndex;
    });
    
    document.getElementById('selectedSeats').textContent = 
        seatLabels.length > 0 ? seatLabels.join(', ') : 'None';
    
    const totalAmount = selectedSeats.length * ticketPrice;
    document.getElementById('totalAmount').textContent = '$' + totalAmount.toFixed(2);
}

function proceedToPayment() {
    if (selectedSeats.length === 0) {
        showNotification('Please select at least one seat', 'error');
        return;
    }
    
    showNotification('Proceeding to payment...', 'success');
    // Here you would redirect to payment page
    setTimeout(() => {
        window.location.href = '../payment.html';
    }, 1500);
}
