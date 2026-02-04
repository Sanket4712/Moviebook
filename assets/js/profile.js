/**
 * MovieBook Profile Interactions
 * Handles profile editing, favorites, lists, and real-time updates
 */

// ==================== PROFILE EDITING ====================

function openEditProfileModal() {
    const modal = document.createElement('div');
    modal.className = 'profile-modal';
    modal.id = 'editProfileModal';
    modal.innerHTML = `
        <div class="modal-overlay" onclick="closeEditProfileModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Profile</h3>
                <button class="modal-close" onclick="closeEditProfileModal()">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="edit-avatar-section">
                    <img id="avatarPreview" src="" alt="Avatar" class="avatar-preview">
                    <label class="btn-upload">
                        <i class="bi bi-camera"></i> Change Photo
                        <input type="file" id="avatarInput" accept="image/*" onchange="previewAvatar(this)">
                    </label>
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" id="editUsername" maxlength="50" placeholder="Your name">
                </div>
                <div class="form-group">
                    <label>Bio</label>
                    <textarea id="editBio" maxlength="200" rows="3" placeholder="Tell us about yourself..."></textarea>
                    <span class="char-count"><span id="bioCharCount">0</span>/200</span>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeEditProfileModal()">Cancel</button>
                <button class="btn-save" onclick="saveProfileChanges()">
                    <i class="bi bi-check"></i> Save
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);

    // Load current values
    const currentAvatar = document.querySelector('.profile-avatar')?.src || '';
    const currentName = document.querySelector('.profile-username')?.textContent || '';
    const currentBio = document.querySelector('.profile-bio')?.textContent || '';

    document.getElementById('avatarPreview').src = currentAvatar;
    document.getElementById('editUsername').value = currentName;
    document.getElementById('editBio').value = currentBio;
    updateBioCharCount();

    // Bio char counter
    document.getElementById('editBio').addEventListener('input', updateBioCharCount);

    setTimeout(() => modal.classList.add('open'), 10);
}

function closeEditProfileModal() {
    const modal = document.getElementById('editProfileModal');
    if (modal) {
        modal.classList.remove('open');
        setTimeout(() => modal.remove(), 300);
    }
}

function updateBioCharCount() {
    const bio = document.getElementById('editBio');
    const count = document.getElementById('bioCharCount');
    if (bio && count) {
        count.textContent = bio.value.length;
    }
}

function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = (e) => {
            document.getElementById('avatarPreview').src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

async function saveProfileChanges() {
    const btn = document.querySelector('.modal-footer .btn-save');
    btn.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Saving...';
    btn.disabled = true;

    const username = document.getElementById('editUsername').value.trim();
    const bio = document.getElementById('editBio').value.trim();
    const avatarInput = document.getElementById('avatarInput');

    try {
        // Update username
        if (username) {
            const res = await fetch('../api/profile.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=update_username&username=${encodeURIComponent(username)}`
            });
            const data = await res.json();
            if (data.success) {
                document.querySelector('.profile-username').textContent = username;
            } else {
                showNotification(data.error, 'error');
            }
        }

        // Update bio
        const bioRes = await fetch('../api/profile.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=update_bio&bio=${encodeURIComponent(bio)}`
        });
        const bioData = await bioRes.json();
        if (bioData.success) {
            document.querySelector('.profile-bio').textContent = bio;
        }

        // Upload avatar if changed
        if (avatarInput.files && avatarInput.files[0]) {
            const formData = new FormData();
            formData.append('action', 'upload_avatar');
            formData.append('avatar', avatarInput.files[0]);

            const avatarRes = await fetch('../api/profile.php', {
                method: 'POST',
                body: formData
            });
            const avatarData = await avatarRes.json();
            if (avatarData.success) {
                document.querySelector('.profile-avatar').src = avatarData.avatar_url;
            }
        }

        showNotification('Profile updated!', 'success');
        closeEditProfileModal();

    } catch (error) {
        showNotification('Failed to save changes', 'error');
    }

    btn.innerHTML = '<i class="bi bi-check"></i> Save';
    btn.disabled = false;
}

// ==================== LISTS MANAGEMENT ====================

function createNewList() {
    const modal = document.createElement('div');
    modal.className = 'profile-modal';
    modal.id = 'createListModal';
    modal.innerHTML = `
        <div class="modal-overlay" onclick="closeCreateListModal()"></div>
        <div class="modal-content modal-sm">
            <div class="modal-header">
                <h3>New List</h3>
                <button class="modal-close" onclick="closeCreateListModal()">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>List Name</label>
                    <input type="text" id="listTitle" maxlength="100" placeholder="e.g., Best 2024 Films">
                </div>
                <div class="form-group">
                    <label>Description (optional)</label>
                    <textarea id="listDescription" maxlength="500" rows="2" placeholder="What's this list about?"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeCreateListModal()">Cancel</button>
                <button class="btn-save" onclick="submitNewList()">
                    <i class="bi bi-plus"></i> Create
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    setTimeout(() => modal.classList.add('open'), 10);
    document.getElementById('listTitle').focus();
}

function closeCreateListModal() {
    const modal = document.getElementById('createListModal');
    if (modal) {
        modal.classList.remove('open');
        setTimeout(() => modal.remove(), 300);
    }
}

async function submitNewList() {
    const title = document.getElementById('listTitle').value.trim();
    const description = document.getElementById('listDescription').value.trim();

    if (!title) {
        showNotification('List name required', 'error');
        return;
    }

    try {
        const res = await fetch('../api/lists.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=create&title=${encodeURIComponent(title)}&description=${encodeURIComponent(description)}`
        });
        const data = await res.json();

        if (data.success) {
            showNotification('List created!', 'success');
            closeCreateListModal();
            // Reload lists section
            location.reload();
        } else {
            showNotification(data.error, 'error');
        }
    } catch (error) {
        showNotification('Failed to create list', 'error');
    }
}

async function deleteList(listId, element) {
    if (!confirm('Delete this list?')) return;

    try {
        const res = await fetch('../api/lists.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=delete&list_id=${listId}`
        });
        const data = await res.json();

        if (data.success) {
            element.closest('.list-card').remove();
            showNotification('List deleted', 'success');
            updateListCount(-1);
        }
    } catch (error) {
        showNotification('Failed to delete list', 'error');
    }
}

// ==================== WATCHLIST ====================

async function removeFromWatchlist(movieId, element) {
    try {
        const res = await fetch('../api/watchlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=remove&movie_id=${movieId}`
        });
        const data = await res.json();

        if (data.success) {
            element.style.transform = 'scale(0)';
            element.style.opacity = '0';
            setTimeout(() => element.remove(), 300);
            updateWatchlistCount(-1);
            showNotification('Removed from watchlist', 'success');
        }
    } catch (error) {
        showNotification('Failed to remove', 'error');
    }
}

// ==================== FAVORITES ====================

async function removeFromFavorites(movieId, element) {
    try {
        const res = await fetch('../api/favorites.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=remove&movie_id=${movieId}`
        });
        const data = await res.json();

        if (data.success) {
            element.style.transform = 'scale(0)';
            setTimeout(() => element.remove(), 300);
            showNotification('Removed from favorites', 'success');
        }
    } catch (error) {
        showNotification('Failed to remove', 'error');
    }
}

// ==================== STATS UPDATES ====================

function updateWatchlistCount(delta) {
    const el = document.querySelector('.stat-item[data-tab="watchlist"] .stat-value');
    if (el) {
        el.textContent = Math.max(0, parseInt(el.textContent) + delta);
    }
}

function updateListCount(delta) {
    const el = document.querySelector('.stat-item[data-tab="lists"] .stat-value');
    if (el) {
        el.textContent = Math.max(0, parseInt(el.textContent) + delta);
    }
}

// ==================== NOTIFICATIONS ====================

function showNotification(message, type = 'info') {
    document.querySelectorAll('.profile-notification').forEach(n => n.remove());

    const notif = document.createElement('div');
    notif.className = `profile-notification notif-${type}`;
    notif.innerHTML = `
        <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'error' ? 'x-circle' : 'info-circle'}"></i>
        <span>${message}</span>
    `;
    document.body.appendChild(notif);

    setTimeout(() => notif.classList.add('show'), 10);
    setTimeout(() => {
        notif.classList.remove('show');
        setTimeout(() => notif.remove(), 300);
    }, 3000);
}

// ==================== MODAL STYLES ====================

const modalStyles = document.createElement('style');
modalStyles.textContent = `
    .profile-modal {
        position: fixed;
        inset: 0;
        z-index: 1000;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }
    .profile-modal.open {
        opacity: 1;
        visibility: visible;
    }
    .modal-overlay {
        position: absolute;
        inset: 0;
        background: rgba(0,0,0,0.8);
    }
    .modal-content {
        position: relative;
        background: #1a1a1a;
        border-radius: 12px;
        width: 90%;
        max-width: 420px;
        max-height: 90vh;
        overflow: auto;
        transform: translateY(20px);
        transition: transform 0.3s ease;
    }
    .profile-modal.open .modal-content {
        transform: translateY(0);
    }
    .modal-sm { max-width: 360px; }
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 20px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    .modal-header h3 {
        margin: 0;
        font-size: 18px;
        color: #fff;
    }
    .modal-close {
        background: transparent;
        border: none;
        color: #666;
        font-size: 20px;
        cursor: pointer;
        padding: 4px;
    }
    .modal-close:hover { color: #fff; }
    .modal-body { padding: 20px; }
    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding: 16px 20px;
        border-top: 1px solid rgba(255,255,255,0.1);
    }
    .edit-avatar-section {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
    }
    .avatar-preview {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #e50914;
    }
    .btn-upload {
        background: rgba(255,255,255,0.1);
        color: #fff;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 13px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .btn-upload input { display: none; }
    .btn-upload:hover { background: rgba(255,255,255,0.15); }
    .form-group {
        margin-bottom: 16px;
    }
    .form-group label {
        display: block;
        font-size: 12px;
        color: #999;
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .form-group input,
    .form-group textarea {
        width: 100%;
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 8px;
        padding: 12px;
        color: #fff;
        font-size: 14px;
    }
    .form-group input:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #e50914;
    }
    .char-count {
        display: block;
        text-align: right;
        font-size: 11px;
        color: #666;
        margin-top: 4px;
    }
    .btn-cancel {
        background: transparent;
        border: 1px solid rgba(255,255,255,0.2);
        color: #999;
        padding: 10px 20px;
        border-radius: 8px;
        cursor: pointer;
    }
    .btn-cancel:hover { border-color: #fff; color: #fff; }
    .btn-save {
        background: #e50914;
        border: none;
        color: #fff;
        padding: 10px 24px;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 6px;
        font-weight: 500;
    }
    .btn-save:hover { background: #ff1a1a; }
    .btn-save:disabled { opacity: 0.5; cursor: not-allowed; }
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    .spin { animation: spin 1s linear infinite; }
    
    /* Notifications */
    .profile-notification {
        position: fixed;
        top: 80px;
        right: 20px;
        background: #1a1a1a;
        color: #fff;
        padding: 12px 20px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 14px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.4);
        transform: translateX(120%);
        transition: transform 0.3s ease;
        z-index: 2000;
    }
    .profile-notification.show { transform: translateX(0); }
    .notif-success { border-left: 3px solid #00c030; }
    .notif-error { border-left: 3px solid #e50914; }
    .notif-success i { color: #00c030; }
    .notif-error i { color: #e50914; }
`;
document.head.appendChild(modalStyles);

// ==================== INITIALIZATION ====================

document.addEventListener('DOMContentLoaded', () => {
    // Tab switching
    document.querySelectorAll('.profile-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            const tabId = tab.dataset.tab;
            document.querySelectorAll('.profile-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.getElementById('tab-' + tabId)?.classList.add('active');
        });
    });

    // Stat items click
    document.querySelectorAll('.stat-item').forEach(stat => {
        stat.addEventListener('click', () => {
            const tabId = stat.dataset.tab;
            document.querySelector(`.profile-tab[data-tab="${tabId}"]`)?.click();
        });
    });
});
