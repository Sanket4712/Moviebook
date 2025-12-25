-- Add Our Picks feature to existing database

-- Our Picks Table (Admin curated movies)
CREATE TABLE IF NOT EXISTS our_picks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tmdb_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    poster_path VARCHAR(255),
    backdrop_path VARCHAR(255),
    vote_average DECIMAL(3,1),
    release_date DATE,
    overview TEXT,
    display_order INT DEFAULT 0,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create index for better performance
CREATE INDEX idx_display_order ON our_picks(display_order);
