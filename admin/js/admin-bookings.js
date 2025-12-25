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

async function loadBookings() {
    try {
        const response = await fetch(`${API_BASE}/admin.php?action=bookings`);
        const data = await response.json();
        
        const tbody = document.getElementById('bookingsTable');
        
        if (data.success && data.bookings.length > 0) {
            tbody.innerHTML = '';
            
            data.bookings.forEach(booking => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><strong>${booking.booking_reference}</strong></td>
                    <td>${booking.customer_name}</td>
                    <td>${booking.customer_email}</td>
                    <td>${booking.customer_phone}</td>
                    <td>${booking.title}</td>
                    <td>${booking.show_date}</td>
                    <td>${formatTime(booking.show_time)}</td>
                    <td>${booking.seats_booked.join(', ')}</td>
                    <td><strong>$${parseFloat(booking.total_amount).toFixed(2)}</strong></td>
                    <td>${new Date(booking.booking_date).toLocaleString()}</td>
                `;
                tbody.appendChild(row);
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center">No bookings yet</td></tr>';
        }
    } catch (error) {
        console.error('Error loading bookings:', error);
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
    loadBookings();
});
