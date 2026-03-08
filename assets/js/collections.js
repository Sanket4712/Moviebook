// Collections Page JavaScript

document.addEventListener('DOMContentLoaded', function () {
    // Check if user is logged in
    checkUserLogin();

    // Load user collections
    loadCollections();
});

// Check if user is logged in
function checkUserLogin() {
    // Session is managed by PHP - skip client-side check
    return;
}

// Load collections from localStorage
function loadCollections() {
    const collections = JSON.parse(localStorage.getItem('moviebook_collections') || '{}');
    console.log('User collections:', collections);
}

// Create new collection
function createNewCollection() {
    const collectionName = prompt('Enter collection name:');

    if (!collectionName || collectionName.trim() === '') {
        return;
    }

    // Get existing collections
    const collections = JSON.parse(localStorage.getItem('moviebook_collections') || '{}');

    // Create new collection
    const collectionId = collectionName.toLowerCase().replace(/\s+/g, '-');

    if (collections[collectionId]) {
        showNotification('Collection already exists!', 'error');
        return;
    }

    collections[collectionId] = {
        name: collectionName,
        movies: [],
        createdAt: new Date().toISOString()
    };

    // Save to localStorage
    localStorage.setItem('moviebook_collections', JSON.stringify(collections));

    showNotification('Collection created successfully!', 'success');

    // Reload page
    setTimeout(() => {
        location.reload();
    }, 1500);
}

// Add movie to collection
function addToCollection(movieId, collectionId) {
    const collections = JSON.parse(localStorage.getItem('moviebook_collections') || '{}');

    if (!collections[collectionId]) {
        collections[collectionId] = {
            name: collectionId,
            movies: [],
            createdAt: new Date().toISOString()
        };
    }

    if (!collections[collectionId].movies.includes(movieId)) {
        collections[collectionId].movies.push(movieId);
        localStorage.setItem('moviebook_collections', JSON.stringify(collections));
        showNotification('Added to collection!', 'success');
    } else {
        showNotification('Already in collection!', 'info');
    }
}

// Show notification
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;

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

    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}
