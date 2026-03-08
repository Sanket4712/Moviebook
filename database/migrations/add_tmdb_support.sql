-- TMDB Integration - Database Migration
-- Run this to add TMDB support to the movies table

USE moviebook;

-- Add TMDB ID column if not exists
ALTER TABLE movies ADD COLUMN IF NOT EXISTS tmdb_id INT UNIQUE AFTER id;

-- Add additional movie metadata columns
ALTER TABLE movies ADD COLUMN IF NOT EXISTS vote_count INT DEFAULT 0 AFTER rating;
ALTER TABLE movies ADD COLUMN IF NOT EXISTS popularity DECIMAL(10,3) DEFAULT 0 AFTER vote_count;
ALTER TABLE movies ADD COLUMN IF NOT EXISTS original_language VARCHAR(10) DEFAULT 'en' AFTER popularity;

-- Create index for faster lookups
CREATE INDEX IF NOT EXISTS idx_movies_tmdb_id ON movies(tmdb_id);
CREATE INDEX IF NOT EXISTS idx_movies_rating ON movies(rating DESC);
CREATE INDEX IF NOT EXISTS idx_movies_release_date ON movies(release_date DESC);

-- Update existing movies to mark as local (non-TMDB)
UPDATE movies SET tmdb_id = NULL WHERE tmdb_id IS NULL OR tmdb_id = 0;
