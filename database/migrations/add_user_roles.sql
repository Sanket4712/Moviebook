-- MovieBook: User Roles Migration
-- Idempotent: safe to re-run

CREATE TABLE IF NOT EXISTS user_roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    role ENUM('user', 'admin', 'theater') NOT NULL,
    theater_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (theater_id) REFERENCES theaters(id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_role_theater (user_id, role, theater_id)
) ENGINE=InnoDB;

-- Migration: Theater roles only with valid theater_id
INSERT IGNORE INTO user_roles (user_id, role, theater_id)
SELECT u.id, 'theater', t.id
FROM users u
INNER JOIN theaters t ON t.owner_id = u.id
WHERE u.role = 'theater';

-- All users get 'user' role
INSERT IGNORE INTO user_roles (user_id, role, theater_id)
SELECT id, 'user', NULL FROM users;

-- Admin roles
INSERT IGNORE INTO user_roles (user_id, role, theater_id)
SELECT id, 'admin', NULL FROM users WHERE role = 'admin';
