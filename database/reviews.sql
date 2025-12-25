-- User Reviews Table
USE moviebook;

CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tmdb_id INT NOT NULL,
    movie_title VARCHAR(255) NOT NULL,
    user_name VARCHAR(100) NOT NULL,
    user_email VARCHAR(100),
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    review_text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tmdb_id (tmdb_id),
    INDEX idx_created_at (created_at)
);
