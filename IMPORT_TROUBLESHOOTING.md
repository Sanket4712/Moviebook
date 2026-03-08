# Letterboxd Import Troubleshooting Guide

## Overview
This guide helps diagnose why movies aren't importing from Letterboxd CSV files.

## Critical Bugs Fixed

### 1. ✅ Database Connection Issue (FIXED)
**Problem:** The `letterboxd_import.php` API wasn't properly connecting to the database when performing bulk imports.

**Root Cause:** 
- `db.php` was only included conditionally inside the `bulkImportMovies()` function
- The `$pdo` variable wasn't properly scoped as a global

**Solution Applied:**
- Added `require_once '../includes/db.php';` at the top of `letterboxd_import.php`
- Added `global $pdo;` declaration at the start of `bulkImportMovies()`
- Added validation check: `if (!$pdo) return error`

### 2. ✅ Missing Year Extraction Function (FIXED)
**Problem:** CSV parser was calling `extractYearFromTitle()` but function didn't exist.

**Root Cause:**
- Function was referenced on line 156 but never defined
- This caused a fatal PHP error, stopping the import

**Solution Applied:**
- Added `extractYearFromTitle()` function:
```php
function extractYearFromTitle($title) {
    if (preg_match('/\((\d{4})\)$/', trim($title), $matches)) {
        $year = intval($matches[1]);
        if ($year >= 1800 && $year <= 2100) {
            return $year;
        }
    }
    return null;
}
```
- Now properly extracts years from titles like "Movie Title (2024)"

### 3. ✅ Flexible CSV Header Detection (IMPROVED)
**Problem:** CSV headers weren't being detected properly for all variations of column names.

**Root Cause:**
- Header detection only looked for exact matches
- Different CSV exports use different column naming conventions

**Solution Applied:**
- Enhanced header detection to include more variations:
  - Title: `['name', 'title', 'film', 'movie', 'movie name']`
  - Year: `['year', 'release year', 'release_year', 'year released', 'releasedate']`
  - Rating: `['rating', 'your rating', 'my rating', 'your rating (10)', 'rating10']`

---

## How to Test the Import

### Step 1: Check Database Connection
Open [http://localhost/Moviebook/test_import.php](http://localhost/Moviebook/test_import.php) to see:
- ✓ Database connection status
- ✓ Current movie count
- ✓ Recent movies
- ✓ Recent import logs

### Step 2: Prepare CSV File
Your Letterboxd export should have at least these columns:
- **Name** (or Title, Film, Movie) - Movie title
- **Year** (or Release Year) - Release year
- **Rating** (optional) - Your rating 0-5 stars

Example format:
```
Name,Year,Rating
The Shawshank Redemption,1994,5.0
The Dark Knight,2008,4.5
Inception,2010,4.0
```

### Step 3: Upload and Import
1. Go to [http://localhost/Moviebook/Admin/movies.php](http://localhost/Moviebook/Admin/movies.php)
2. Click **"Import from Letterboxd CSV"** button
3. Drag and drop or select your CSV file
4. Review the preview (shows first 50 movies)
5. Click **"Import All Now"**
6. Watch the progress bar
7. See success message
8. Movies appear in the grid with "Needs Polish" badge

---

## Debugging Steps

### If CSV Upload Fails

**Error: "File must be a CSV (got .txt)"**
- Ensure file is actually CSV, not TXT or other format
- Export from Letterboxd again and save as .csv

**Error: "File too large (max 5MB)"**
- Your export file is > 5MB
- Try splitting into smaller batches or remove columns you don't need

**Error: "No CSV file uploaded"**
- JavaScript isn't working
- Check browser console (F12 → Console tab)
- Try a different browser

**Error: "CSV must have a 'Name' or 'Title' column"**
- Column headers aren't being detected
- Check your CSV headers (first row must contain: Name or Title)
- Use [test_import.php](test_import.php) to see detected headers

### If Preview Shows But Import Fails

**Error: "Import failed"**
- Check browser console for details (F12 → Network tab → bulk_import request → Response)
- Check PHP error log at `/logs/php_errors.log`
- Possible causes:
  - Database connection issue
  - Invalid movie data
  - Permission issue

**No movies appear after import completes**
1. Check if movies were actually created:
   - Go to [test_import.php](test_import.php)
   - Look for "Movies with 'ended' status" section
2. If movies exist but don't show:
   - Go to Admin Movies
   - Check Status filter is set to "All Movies"
   - Click refresh or reload page
3. If movies don't exist:
   - Check error logs at `/logs/php_errors.log`
   - Look for lines containing "Letterboxd" or "bulk import"

### If Preview Shows Wrong Data

**Ratings show as 0 or incorrect**
- Check CSV has Rating column
- Rating should be 0-5 scale (Letterboxd format)
- Ratings are converted to 0-10 scale internally: 3.5 stars → 7.0

**Years show as 0 or blank**
- Ensure year column exists and has 4-digit years
- If title contains year like "Movie (2024)", it should extract automatically
- If not, add a Year column to your CSV

**Movies show as "Unknown Year"**
- CSV either missing year or has invalid format
- Add YEAR column with values like: 1994, 2024, etc.

---

## Understanding the Import Flow

### Frontend (Browser)
```
1. User selects CSV file
   ↓
2. handleCSVUpload() sends file to API
   ↓
3. API parses CSV and returns preview
   ↓
4. User sees list of ~50 movies with ratings
   ↓
5. User clicks "Import All Now"
   ↓
6. importAllMovies() sends all movies to bulk_import API
   ↓
7. Progress bar animates while importing
   ↓
8. Success message shows count and stats
   ↓
9. Grid refreshes automatically
   ↓
10. User sees new movies with "Needs Polish" badge
```

### Backend (Server)
```
CSV Upload:
1. parseCSV() reads file
2. Detects column headers
3. Extracts title, year, rating
4. Validates each movie
5. Returns list of ~50 movies for preview

Bulk Import:
1. bulkImportMovies() receives all movies
2. Checks for duplicates (title + year)
3. Creates movies with status='ended'
4. Sets rating on 0-10 scale
5. Sets placeholder poster
6. Returns count of created/duplicates
```

---

## Log File Analysis

Check `/logs/php_errors.log` for debugging:

### Successful Import
```
Letterboxd CSV parse request
CSV file received: watchlist.csv
CSV file opened successfully, parsing...
Parsed movie: The Shawshank Redemption (1994) Rating: 5
Parsed movie: The Dark Knight (2008) Rating: 4.5
CSV parsing complete: found 247 valid movies
Movies with ratings: 200
Starting bulk import of 247 movies
Created movie ID 1024: The Shawshank Redemption (1994) Rating: 10
Created movie ID 1025: The Dark Knight (2008) Rating: 9
Letterboxd bulk import: Created=247, Duplicates=0
```

### Failed Import - Missing Year
```
CSV parsing complete: found 0 valid movies
[No movies found error]
```

### Failed Import - No Title Column
```
CSV must have a "Name" or "Title" column. Found columns: letterboxd uri, watched date, rating
```

### Failed Import - Database Error
```
Database connection failed: SQLSTATE[HY000]
```

---

## Quick Checklist

Before importing, verify:
- [ ] CSV file is less than 5MB
- [ ] First row contains: Name (or Title) and Year columns
- [ ] CSV has actual movie data (not just headers)
- [ ] No special characters breaking the format
- [ ] Administrator account is logged in
- [ ] Database is running (test_import.php shows connection OK)

After importing, verify:
- [ ] Success message shows count > 0
- [ ] New movies appear in grid
- [ ] Movies have "Needs Polish" badge
- [ ] Movie cards show star ratings
- [ ] Status filter shows "All Movies"
- [ ] Refresh page to confirm persistence

---

## Report an Issue

If import still fails, please provide:
1. Browser console error (F12 → Console)
2. Network request error (F12 → Network → bulk_import response)
3. PHP error log entries (from `/logs/php_errors.log`)
4. Sample CSV file (first 5 rows)
5. Number of movies trying to import
6. Expected vs actual results

---

## Technical Details

### Database Schema
```sql
CREATE TABLE movies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    release_date DATE,
    rating DECIMAL(3,1) DEFAULT 0.0,  -- 0-10 scale
    status ENUM('now_showing', 'coming_soon', 'ended') DEFAULT 'now_showing'
)
```

### Letterboxd Rating Conversion
```
Letterboxd: 0.5 stars → Database: 1.0 (0-10 scale)
Letterboxd: 1.0 stars → Database: 2.0
Letterboxd: 2.5 stars → Database: 5.0
Letterboxd: 5.0 stars → Database: 10.0
```

### Movie Status Meanings
- `now_showing` - Currently in theaters or available
- `coming_soon` - Future release
- `ended` - Quick-imported from Letterboxd (marked as incomplete)

Movies imported from Letterboxd are created with status='ended' to indicate they need metadata polish (poster, description, etc.).

---

## Need Help?

1. **Check Logs**: [test_import.php](test_import.php) shows recent activity
2. **Test Connection**: Verify database works
3. **Try Sample CSV**: Create a 3-movie test file first
4. **Check Headers**: Ensure column names match expected format
5. **Review Errors**: Always check browser console and PHP error log
