const API_BASE = '../api';

// Check authentication
async function checkAuth() {
    try {
        const response = await fetch(`${API_BASE}/admin.php?action=check_auth`);
        const data = await response.json();
        
        if (!data.authenticated) {
            window.location.href = 'login.html';
        }
    } catch (error) {
        console.error('Auth check failed:', error);
        window.location.href = 'login.html';
    }
}

// Logout
document.getElementById('logoutBtn').addEventListener('click', async (e) => {
    e.preventDefault();
    
    try {
        await fetch(`${API_BASE}/admin.php?action=logout`, { method: 'POST' });
        window.location.href = 'login.html';
    } catch (error) {
        console.error('Logout error:', error);
    }
});

// Load dashboard stats
async function loadDashboardStats() {
    try {
        const response = await fetch(`${API_BASE}/admin.php?action=dashboard_stats`);
        const data = await response.json();
        
        if (data.success) {
            const stats = data.stats;
            document.getElementById('activeMovies').textContent = stats.active_movies;
            document.getElementById('bookingsToday').textContent = stats.bookings_today;
            document.getElementById('revenueToday').textContent = '$' + parseFloat(stats.revenue_today).toFixed(2);
            document.getElementById('revenueMonth').textContent = '$' + parseFloat(stats.revenue_month).toFixed(2);
        }
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

// Load recent bookings
async function loadRecentBookings() {
    try {
        const response = await fetch(`${API_BASE}/admin.php?action=bookings`);
        const data = await response.json();
        
        const tbody = document.getElementById('recentBookings');
        
        if (data.success && data.bookings.length > 0) {
            tbody.innerHTML = '';
            
            data.bookings.slice(0, 10).forEach(booking => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${booking.booking_reference}</td>
                    <td>${booking.customer_name}</td>
                    <td>${booking.title}</td>
                    <td>${booking.show_date} ${formatTime(booking.show_time)}</td>
                    <td>${booking.seats_booked.join(', ')}</td>
                    <td>$${parseFloat(booking.total_amount).toFixed(2)}</td>
                `;
                tbody.appendChild(row);
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center">No bookings yet</td></tr>';
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

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    checkAuth();
    loadDashboardStats();
    loadRecentBookings();
});
