// Authentication JavaScript

document.addEventListener('DOMContentLoaded', function () {
    // Login Form
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }

    // Signup Form
    const signupForm = document.getElementById('signupForm');
    if (signupForm) {
        signupForm.addEventListener('submit', handleSignup);

        // Password strength checker
        const passwordInput = document.getElementById('signupPassword');
        if (passwordInput) {
            passwordInput.addEventListener('input', checkPasswordStrength);
        }

        // Confirm password validation
        const confirmPassword = document.getElementById('confirmPassword');
        if (confirmPassword) {
            confirmPassword.addEventListener('input', validatePasswordMatch);
        }
    }

    // Toggle Password Visibility
    const toggleButtons = document.querySelectorAll('.toggle-password');
    toggleButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            const input = this.previousElementSibling;
            const icon = this.querySelector('i');

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });

    // Social Login Buttons
    const socialButtons = document.querySelectorAll('.btn-social');
    socialButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            const provider = this.classList.contains('google') ? 'Google' : 'Facebook';
            showNotification(`${provider} login coming soon!`, 'success');
        });
    });
});

// Handle Login - Now submits to PHP backend
function handleLogin(e) {
    // Allow form to submit to PHP - just do basic validation
    const formData = new FormData(e.target);
    const email = formData.get('email');
    const password = formData.get('password');

    // Basic client-side validation
    if (!email || !password) {
        e.preventDefault();
        showNotification('Please fill in all fields', 'error');
        return false;
    }

    // Show loading message
    showNotification('Logging in...', 'success');

    // Let the form submit to process_login.php
    return true;
}

// Handle Signup - Now submits to PHP backend
function handleSignup(e) {
    const formData = new FormData(e.target);
    const fullName = formData.get('fullName');
    const email = formData.get('email');
    const phone = formData.get('phone');
    const password = formData.get('password');
    const confirmPassword = formData.get('confirmPassword');
    const termsAccepted = formData.get('terms');

    // Basic client-side validation
    if (!fullName || !email || !phone || !password || !confirmPassword) {
        e.preventDefault();
        showNotification('Please fill in all required fields', 'error');
        return false;
    }

    if (!termsAccepted) {
        e.preventDefault();
        showNotification('Please accept the Terms & Conditions', 'error');
        return false;
    }

    if (password !== confirmPassword) {
        e.preventDefault();
        showNotification('Passwords do not match', 'error');
        return false;
    }

    if (password.length < 8) {
        e.preventDefault();
        showNotification('Password must be at least 8 characters long', 'error');
        return false;
    }

    // Email validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        e.preventDefault();
        showNotification('Please enter a valid email address', 'error');
        return false;
    }

    // Show loading message
    showNotification('Creating your account...', 'success');

    // Let the form submit to process_signup.php
    return true;
}

// Password Strength Checker
function checkPasswordStrength(e) {
    const password = e.target.value;
    const strengthBar = document.querySelector('.strength-bar');

    if (!strengthBar) return;

    let strength = 0;

    // Length
    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;

    // Contains lowercase and uppercase
    if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;

    // Contains numbers
    if (password.match(/[0-9]/)) strength++;

    // Contains special characters
    if (password.match(/[^a-zA-Z0-9]/)) strength++;

    // Update strength bar
    strengthBar.classList.remove('weak', 'medium', 'strong');

    if (strength <= 2) {
        strengthBar.classList.add('weak');
    } else if (strength <= 4) {
        strengthBar.classList.add('medium');
    } else {
        strengthBar.classList.add('strong');
    }
}

// Validate Password Match
function validatePasswordMatch(e) {
    const password = document.getElementById('signupPassword').value;
    const confirmPassword = e.target.value;

    if (confirmPassword && password !== confirmPassword) {
        e.target.setCustomValidity('Passwords do not match');
        e.target.style.borderColor = '#e53935';
    } else {
        e.target.setCustomValidity('');
        e.target.style.borderColor = '';
    }
}

// Check if user is already logged in
function checkAuthStatus() {
    const user = localStorage.getItem('moviebook_user') || sessionStorage.getItem('moviebook_user');

    if (user) {
        const userData = JSON.parse(user);
        return userData;
    }

    return null;
}

// Logout function
function logout() {
    localStorage.removeItem('moviebook_user');
    sessionStorage.removeItem('moviebook_user');
    showNotification('Logged out successfully', 'success');

    setTimeout(() => {
        window.location.href = '../auth/login.php';
    }, 1000);
}

// Show notification function
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;

    // Style the notification
    notification.style.cssText = `
        position: fixed;
        top: 80px;
        right: 20px;
        background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#17a2b8'};
        color: white;
        padding: 15px 25px;
        border-radius: 4px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        z-index: 10000;
        animation: slideIn 0.3s ease;
        font-size: 14px;
        font-weight: 500;
    `;

    document.body.appendChild(notification);

    // Remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

// Add CSS animations for notifications
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
