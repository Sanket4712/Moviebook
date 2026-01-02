// Main JavaScript for MovieBook Platform

// Search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch(this.value);
            }
        });
    }
    
    // FAQ Accordion
    const faqQuestions = document.querySelectorAll('.faq-question');
    faqQuestions.forEach(question => {
        question.addEventListener('click', function() {
            const faqItem = this.parentElement;
            const isActive = faqItem.classList.contains('active');
            
            // Close all FAQ items
            document.querySelectorAll('.faq-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Open clicked item if it wasn't active
            if (!isActive) {
                faqItem.classList.add('active');
            }
        });
    });
});

function performSearch(query) {
    if (query.trim()) {
        window.location.href = `User/movie-list.html?search=${encodeURIComponent(query)}`;
    }
}

// Smooth scrolling
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth'
            });
        }
    });
});

// Session management
function checkUserSession() {
    const user = localStorage.getItem('currentUser');
    return user ? JSON.parse(user) : null;
}

function setUserSession(user) {
    localStorage.setItem('currentUser', JSON.stringify(user));
}

function clearUserSession() {
    localStorage.removeItem('currentUser');
}

// Utility functions
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 25px;
        background-color: ${type === 'success' ? '#28a745' : '#dc3545'};
        color: white;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        z-index: 10000;
        animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Add animations
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

// Subplatform Authentication
const SUBPLATFORM_CREDENTIALS = {
    admin: {
        email: 'sanket@gmail.com',
        password: '123456789',
        redirectUrl: 'Admin/dashboard.html'
    },
    theater: {
        email: 'demo@gmail.com',
        password: '123456789',
        redirectUrl: 'Theater/dashboard.html'
    }
};

let currentPlatform = null;

function openLoginModal(platform) {
    currentPlatform = platform;
    const modal = document.getElementById('subplatformModal');
    const modalIcon = document.getElementById('modalIcon');
    const modalTitle = document.getElementById('modalTitle');
    const modalSubtitle = document.getElementById('modalSubtitle');
    const loginError = document.getElementById('loginError');
    
    // Reset form and error
    document.getElementById('subplatformLoginForm').reset();
    loginError.classList.remove('show');
    loginError.textContent = '';
    
    // Set modal content based on platform
    if (platform === 'admin') {
        modalIcon.className = 'bi bi-shield-lock-fill';
        modalTitle.textContent = 'Admin Portal';
        modalSubtitle.textContent = 'Sign in to access admin dashboard';
    } else {
        modalIcon.className = 'bi bi-building';
        modalTitle.textContent = 'Theater Portal';
        modalSubtitle.textContent = 'Sign in to manage your theater';
    }
    
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeLoginModal() {
    const modal = document.getElementById('subplatformModal');
    modal.classList.remove('active');
    document.body.style.overflow = 'auto';
    currentPlatform = null;
}

function handleSubplatformLogin(event) {
    event.preventDefault();
    
    const email = document.getElementById('platformEmail').value;
    const password = document.getElementById('platformPassword').value;
    const loginError = document.getElementById('loginError');
    
    const credentials = SUBPLATFORM_CREDENTIALS[currentPlatform];
    
    if (email === credentials.email && password === credentials.password) {
        // Store session
        localStorage.setItem(`${currentPlatform}Session`, JSON.stringify({
            email: email,
            platform: currentPlatform,
            loginTime: new Date().toISOString()
        }));
        
        showNotification(`Welcome to ${currentPlatform === 'admin' ? 'Admin' : 'Theater'} Portal!`, 'success');
        
        // Redirect after short delay
        setTimeout(() => {
            window.location.href = credentials.redirectUrl;
        }, 1000);
    } else {
        loginError.textContent = 'Invalid email or password. Please try again.';
        loginError.classList.add('show');
    }
    
    return false;
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('subplatformModal');
    if (event.target === modal) {
        closeLoginModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeLoginModal();
    }
});
