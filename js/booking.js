// API Base URL
const API_BASE = './api';
const TMDB_IMAGE_BASE = 'https://image.tmdb.org/t/p';

let selectedShowtime = null;
let selectedSeats = [];
let movieData = null;
let showtimeData = null;

// Get URL parameters
function getUrlParameter(name) {
    const params = new URLSearchParams(window.location.search);
    return params.get(name);
}

// Load movie details
async function loadMovieDetails() {
    const movieId = getUrlParameter('movie_id');
    
    if (!movieId) {
        alert('No movie selected');
        window.location.href = 'movies.html';
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}/movies.php?action=movie_details&id=${movieId}`);
        const movie = await response.json();
        
        if (movie.error) {
            alert('Movie not found');
            window.location.href = 'movies.html';
            return;
        }
        
        movieData = movie;
        displayMovieInfo(movie);
        loadShowtimes(movieId);
    } catch (error) {
        console.error('Error loading movie:', error);
        alert('Error loading movie details');
    }
}

// Display movie info
function displayMovieInfo(movie) {
    const movieInfo = document.getElementById('movieInfo');
    const backdropUrl = movie.backdrop_path ? `${TMDB_IMAGE_BASE}/original${movie.backdrop_path}` : '';
    const posterUrl = movie.poster_path ? `${TMDB_IMAGE_BASE}/w500${movie.poster_path}` : 'https://via.placeholder.com/300x450';
    
    movieInfo.style.backgroundImage = `url('${backdropUrl}')`;
    
    movieInfo.innerHTML = `
        <div class="movie-info-content">
            <img src="${posterUrl}" alt="${movie.title}" class="movie-poster-large">
            <div class="movie-info-details">
                <h1>${movie.title}</h1>
                <div class="movie-meta">
                    <span><i class="fas fa-star"></i> ${movie.rating || 'N/A'}/10</span>
                    <span><i class="fas fa-clock"></i> ${movie.runtime || 'N/A'} min</span>
                    <span><i class="fas fa-calendar"></i> ${movie.release_date || 'N/A'}</span>
                </div>
                <p style="margin-top: 1rem; line-height: 1.8;">${movie.overview || 'No description available'}</p>
            </div>
        </div>
    `;
}

// Load showtimes
async function loadShowtimes(movieId) {
    const container = document.getElementById('showtimesContainer');
    
    try {
        const response = await fetch(`${API_BASE}/showtimes.php?action=get_showtimes&movie_id=${movieId}`);
        const data = await response.json();
        
        if (data.success && data.showtimes.length > 0) {
            displayShowtimes(data.showtimes);
        } else {
            container.innerHTML = '<p class="loading">No showtimes available for this movie</p>';
        }
    } catch (error) {
        console.error('Error loading showtimes:', error);
        container.innerHTML = '<p class="loading">Error loading showtimes</p>';
    }
}

// Display showtimes grouped by date
function displayShowtimes(showtimes) {
    const container = document.getElementById('showtimesContainer');
    container.innerHTML = '';
    
    // Group by date
    const grouped = {};
    showtimes.forEach(showtime => {
        if (!grouped[showtime.show_date]) {
            grouped[showtime.show_date] = [];
        }
        grouped[showtime.show_date].push(showtime);
    });
    
    // Display each date group
    Object.keys(grouped).forEach(date => {
        const dateSection = document.createElement('div');
        dateSection.className = 'showtime-date';
        
        const dateObj = new Date(date);
        const formattedDate = dateObj.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        
        dateSection.innerHTML = `<h3>${formattedDate}</h3>`;
        
        const slotsContainer = document.createElement('div');
        slotsContainer.className = 'showtime-slots';
        
        grouped[date].forEach(showtime => {
            const slot = document.createElement('div');
            slot.className = 'showtime-slot';
            slot.innerHTML = `
                <div style="font-size: 1.2rem; font-weight: bold; margin-bottom: 0.5rem;">
                    ${formatTime(showtime.show_time)}
                </div>
                <div style="color: #888; font-size: 0.9rem;">
                    Screen ${showtime.screen_number}
                </div>
                <div style="color: #888; font-size: 0.9rem;">
                    ${showtime.available_seats}/${showtime.total_seats} seats
                </div>
                <div style="color: var(--primary-color); font-weight: bold; margin-top: 0.5rem;">
                    $${parseFloat(showtime.price).toFixed(2)}
                </div>
            `;
            
            slot.addEventListener('click', () => selectShowtime(showtime, slot));
            slotsContainer.appendChild(slot);
        });
        
        dateSection.appendChild(slotsContainer);
        container.appendChild(dateSection);
    });
}

// Format time
function formatTime(timeString) {
    const [hours, minutes] = timeString.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;
    return `${displayHour}:${minutes} ${ampm}`;
}

// Select showtime
function selectShowtime(showtime, element) {
    // Remove previous selection
    document.querySelectorAll('.showtime-slot').forEach(slot => {
        slot.classList.remove('selected');
    });
    
    // Mark new selection
    element.classList.add('selected');
    selectedShowtime = showtime;
    showtimeData = showtime;
    
    // Load seats
    loadSeats(showtime.id);
    
    // Show seat selection section
    document.getElementById('seatSelection').style.display = 'block';
    document.getElementById('seatSelection').scrollIntoView({ behavior: 'smooth' });
}

// Load seats
async function loadSeats(showtimeId) {
    const container = document.getElementById('seatsContainer');
    container.innerHTML = '<div class="loading">Loading seats...</div>';
    
    try {
        const response = await fetch(`${API_BASE}/showtimes.php?action=get_seats&showtime_id=${showtimeId}`);
        const data = await response.json();
        
        if (data.success) {
            displaySeats(data.seats);
        }
    } catch (error) {
        console.error('Error loading seats:', error);
        container.innerHTML = '<p class="loading">Error loading seats</p>';
    }
}

// Display seats
function displaySeats(seats) {
    const container = document.getElementById('seatsContainer');
    container.innerHTML = '';
    selectedSeats = [];
    updateSummary();
    
    // Group seats by row
    const rows = {};
    seats.forEach(seat => {
        if (!rows[seat.seat_row]) {
            rows[seat.seat_row] = [];
        }
        rows[seat.seat_row].push(seat);
    });
    
    // Display each row
    Object.keys(rows).sort().forEach(rowLetter => {
        const rowDiv = document.createElement('div');
        rowDiv.className = 'seat-row';
        
        // Row label
        const label = document.createElement('div');
        label.className = 'seat-label';
        label.textContent = rowLetter;
        rowDiv.appendChild(label);
        
        // Seats
        rows[rowLetter].sort((a, b) => a.seat_number - b.seat_number).forEach(seat => {
            const seatDiv = document.createElement('div');
            seatDiv.className = 'seat';
            
            if (seat.is_booked) {
                seatDiv.classList.add('booked');
            } else {
                seatDiv.classList.add('available');
                seatDiv.addEventListener('click', () => toggleSeat(seat, seatDiv));
            }
            
            rowDiv.appendChild(seatDiv);
        });
        
        container.appendChild(rowDiv);
    });
}

// Toggle seat selection
function toggleSeat(seat, element) {
    const seatId = `${seat.seat_row}${seat.seat_number}`;
    const index = selectedSeats.indexOf(seatId);
    
    if (index > -1) {
        // Deselect
        selectedSeats.splice(index, 1);
        element.classList.remove('selected');
    } else {
        // Select (max 10 seats)
        if (selectedSeats.length >= 10) {
            alert('Maximum 10 seats can be selected');
            return;
        }
        selectedSeats.push(seatId);
        element.classList.add('selected');
    }
    
    updateSummary();
}

// Update booking summary
function updateSummary() {
    const seatsDisplay = document.getElementById('selectedSeatsDisplay');
    const totalDisplay = document.getElementById('totalAmount');
    const proceedBtn = document.getElementById('proceedToPayment');
    
    if (selectedSeats.length > 0) {
        seatsDisplay.textContent = selectedSeats.join(', ');
        const total = selectedSeats.length * parseFloat(showtimeData.price);
        totalDisplay.textContent = total.toFixed(2);
        proceedBtn.disabled = false;
    } else {
        seatsDisplay.textContent = 'None';
        totalDisplay.textContent = '0.00';
        proceedBtn.disabled = true;
    }
}

// Proceed to payment
document.getElementById('proceedToPayment')?.addEventListener('click', () => {
    // Show booking modal
    const modal = document.getElementById('bookingModal');
    modal.style.display = 'block';
    
    // Fill modal details
    document.getElementById('modalMovieTitle').textContent = movieData.title;
    document.getElementById('modalShowtime').textContent = `${showtimeData.show_date} at ${formatTime(showtimeData.show_time)}`;
    document.getElementById('modalSeats').textContent = selectedSeats.join(', ');
    const total = selectedSeats.length * parseFloat(showtimeData.price);
    document.getElementById('modalTotal').textContent = total.toFixed(2);
});

// Close modal
document.querySelector('.close')?.addEventListener('click', () => {
    document.getElementById('bookingModal').style.display = 'none';
});

// Submit booking
document.getElementById('bookingForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const bookingData = {
        showtime_id: selectedShowtime.id,
        customer_name: document.getElementById('customerName').value,
        customer_email: document.getElementById('customerEmail').value,
        customer_phone: document.getElementById('customerPhone').value,
        selected_seats: selectedSeats
    };
    
    try {
        const response = await fetch(`${API_BASE}/bookings.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(bookingData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Hide booking modal
            document.getElementById('bookingModal').style.display = 'none';
            
            // Show success modal
            document.getElementById('bookingReference').textContent = data.booking_reference;
            document.getElementById('successModal').style.display = 'block';
        } else {
            alert('Booking failed: ' + (data.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error submitting booking:', error);
        alert('Error submitting booking');
    }
});

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    loadMovieDetails();
});
