-- Movie Table Unique Constraint Migration
-- Enforces title + release year uniqueness at DATABASE level
-- 
-- SAFETY INVARIANT: This prevents duplicate movies even if PHP validation fails

-- ============================================================================
-- CASE SENSITIVITY NOTE
-- ============================================================================
-- MySQL default collations (utf8mb4_general_ci, utf8mb4_unicode_ci) are 
-- case-insensitive, so "Movie" and "movie" are treated as duplicates.
-- 
-- If your database uses a case-sensitive collation (e.g., utf8mb4_bin),
-- uncomment the normalized_title approach below instead.
-- ============================================================================

-- Add release_year as a generated column (derived from release_date)
ALTER TABLE movies ADD COLUMN IF NOT EXISTS release_year INT 
    GENERATED ALWAYS AS (YEAR(release_date)) STORED;

-- Create unique index on title + year
-- This relies on case-insensitive collation (default in most MySQL installs)
CREATE UNIQUE INDEX IF NOT EXISTS idx_movies_title_year 
    ON movies (title(255), release_year);

-- ============================================================================
-- ALTERNATIVE: Explicit case-insensitive approach (if collation is binary)
-- ============================================================================
-- ALTER TABLE movies ADD COLUMN IF NOT EXISTS title_normalized VARCHAR(255)
--     GENERATED ALWAYS AS (LOWER(title)) STORED;
-- CREATE UNIQUE INDEX IF NOT EXISTS idx_movies_title_year 
--     ON movies (title_normalized, release_year);
-- ============================================================================

-- To check your collation: SHOW VARIABLES LIKE 'collation_database';
-- If it ends in '_ci', you're already case-insensitive.

-- To find existing duplicates before applying:
-- SELECT title, YEAR(release_date) as year, COUNT(*) as cnt 
-- FROM movies GROUP BY LOWER(title), YEAR(release_date) HAVING cnt > 1;
