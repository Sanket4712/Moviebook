const API_BASE = '../api';

async function checkAuth() {
    try {
        const response = await fetch(`${API_BASE}/admin.php?action=check_auth`);
        const data = await response.json();
        if (!data.authenticated) window.location.href = 'login.html';
    } catch (error) {
        window.location.href = 'login.html';
    }
}

document.getElementById('logoutBtn').addEventListener('click', async (e) => {
    e.preventDefault();
    await fetch(`${API_BASE}/admin.php?action=logout`, { method: 'POST' });
    window.location.href = 'login.html';
});

async function loadShowtimes() {
    try {
        const response = await fetch(`${API_BASE}/admin.php?action=showtimes`);
        const data = await response.json();
        
        const tbody = document.getElementById('showtimesTable');
        
        if (data.success && data.showtimes.length > 0) {
            tbody.innerHTML = '';
            
            data.showtimes.forEach(showtime => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${showtime.title || 'N/A'}</td>
                    <td>${showtime.show_date}</td>
                    <td>${formatTime(showtime.show_time)}</td>
                    <td>Screen ${showtime.screen_number}</td>
                    <td>$${parseFloat(showtime.price).toFixed(2)}</td>
                    <td>${showtime.available_seats}/${showtime.total_seats}</td>
                    <td>
                        <button class="btn-warning btn-small" onclick="editPrice(${showtime.id}, ${showtime.price})">
                            <i class="fas fa-edit"></i> Edit Price
                        </button>
                        <button class="btn-danger btn-small" onclick="deleteShowtime(${showtime.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center">No showtimes</td></tr>';
        }
    } catch (error) {
        console.error('Error loading showtimes:', error);
    }
}

async function loadMoviesForDropdown() {
    try {
        const response = await fetch(`${API_BASE}/admin.php?action=movies`);
        const data = await response.json();
        
        const select = document.getElementById('showtimeMovieId');
        select.innerHTML = '<option value="">Select a movie</option>';
        
        if (data.success && data.movies.length > 0) {
            data.movies.filter(m => m.is_showing).forEach(movie => {
                const option = document.createElement('option');
                option.value = movie.id;
                option.textContent = movie.title;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading movies:', error);
    }
}

function showAddShowtimeModal() {
    document.getElementById('addShowtimeModal').style.display = 'block';
    loadMoviesForDropdown();
}

function closeModal() {
    document.getElementById('addShowtimeModal').style.display = 'none';
}

function closeEditModal() {
    document.getElementById('editPriceModal').style.display = 'none';
}

document.getElementById('addShowtimeForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const showtimeData = {
        movie_id: document.getElementById('showtimeMovieId').value,
        show_date: document.getElementById('showtimeDate').value,
        show_time: document.getElementById('showtimeTime').value,
        screen_number: document.getElementById('screenNumber').value,
        price: document.getElementById('ticketPrice').value
    };
    
    try {
        const response = await fetch(`${API_BASE}/admin.php?action=add_showtime`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(showtimeData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Showtime added successfully!');
            closeModal();
            loadShowtimes();
            e.target.reset();
        } else {
            alert('Error: ' + (data.error || 'Failed to add showtime'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error adding showtime');
    }
});

function editPrice(showtimeId, currentPrice) {
    document.getElementById('editShowtimeId').value = showtimeId;
    document.getElementById('editPrice').value = currentPrice;
    document.getElementById('editPriceModal').style.display = 'block';
}

document.getElementById('editPriceForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const updateData = {
        id: document.getElementById('editShowtimeId').value,
        price: document.getElementById('editPrice').value
    };
    
    try {
        const response = await fetch(`${API_BASE}/admin.php?action=update_showtime`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(updateData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Price updated successfully!');
            closeEditModal();
            loadShowtimes();
        } else {
            alert('Error updating price');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error updating price');
    }
});

async function deleteShowtime(id) {
    if (!confirm('Are you sure you want to delete this showtime?')) return;
    
    try {
        const response = await fetch(`${API_BASE}/admin.php?action=delete_showtime&id=${id}`, {
            method: 'DELETE'
        });
        const data = await response.json();
        
        if (data.success) {
            alert('Showtime deleted successfully');
            loadShowtimes();
        } else {
            alert('Error deleting showtime');
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

function formatTime(timeString) {
    const [hours, minutes] = timeString.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;
    return `${displayHour}:${minutes} ${ampm}`;
}

document.addEventListener('DOMContentLoaded', () => {
    checkAuth();
    loadShowtimes();
});
