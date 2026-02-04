# Code Changes Reference

## File: Admin/movies.php

### Change 1: Status Filter Dropdown (Line 604)
**Location:** Status filter select element in the "Movie Library" section

```html
<!-- Before -->
<option value="">All Status</option>

<!-- After -->
<option value="" selected>All Movies</option>
```

**Why:** Makes the default filter label clearer and explicitly sets it as selected by default to ensure all movies show on page load.

---

### Change 2: Load Movies Function (Lines 1040-1044)
**Location:** loadMovies() function - URL parameter construction

```javascript
// Before
const status = statusFilter.value;
const url = `${API_URL}?action=list&search=${encodeURIComponent(search)}&status=${status}`;

// After
const status = statusFilter.value || ''; // Ensure empty string for 'All Status'
const url = `${API_URL}?action=list&search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}`;
```

**Why:** 
- Explicitly ensures empty string is used when filter is not selected
- Properly URL-encodes the status parameter
- Confirms API receives empty string for "show all" behavior

---

### Change 3: Import Success Handler (Lines 1438-1447)
**Location:** importAllMovies() function - After import completes and shows success state

```javascript
// Added code AFTER showing success state:
// Refresh the movie grid to show newly imported movies
// Reset filters to "All Movies" to ensure newly imported (ended status) movies are visible
statusFilter.value = '';
searchInput.value = '';
loadMovies();
loadStats();
```

**Why:** 
- Ensures newly imported movies are visible immediately
- Resets any active filters that might hide the new content
- Refreshes statistics to show updated count

---

### Change 4: Form Submission Success (Lines 1566-1570)
**Location:** Form submission handler - After movie is successfully added or updated

```javascript
// Before
if (result.success) {
    closeModal();
    form.reset();
    clearAutoFill();
    editingMovieId = null;
    showAlert(isEdit ? 'Movie updated successfully!' : 'Movie added successfully!', 'success');
    loadMovies();
    loadStats();

// After
if (result.success) {
    closeModal();
    form.reset();
    clearAutoFill();
    editingMovieId = null;
    showAlert(isEdit ? 'Movie updated successfully!' : 'Movie added successfully!', 'success');
    // Reset status filter to show all movies
    statusFilter.value = '';
    searchInput.value = '';
    loadMovies();
    loadStats();
```

**Why:** Ensures that when users manually add or edit movies, filters don't hide the newly created content.

---

## API Behavior (No Changes Needed)

The [api/admin_movies.php](api/admin_movies.php) file already has the correct behavior:

```php
function listMovies() {
    global $pdo;
    
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(10, intval($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';  // Empty string by default
    
    // Build query
    $where = [];
    $params = [];
    
    if ($search) {
        $where[] = "(title LIKE ? OR director LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    // IMPORTANT: Only add status filter if status is explicitly provided
    if ($status && in_array($status, ['now_showing', 'coming_soon', 'ended'])) {
        $where[] = "status = ?";
        $params[] = $status;
    }
    // If $status is empty, no WHERE clause for status is added
    // This means ALL movies are returned regardless of status
    
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    // ... rest of function
}
```

---

## Import Behavior (No Changes Needed)

The [api/letterboxd_import.php](api/letterboxd_import.php) correctly creates movies with `status = 'ended'`:

```php
$status = 'ended'; // Intentional - marks bulk-imported movies

$insertStmt->execute([
    $title, $description, $posterUrl, $backdropUrl, $director, $genre,
    $runtime, $rating, $releaseDate, $status  // Set to 'ended'
]);
```

This is by design to:
1. Distinguish imported movies from manually added ones
2. Trigger the "Needs Polish" visual indicator
3. Prompt admins to add proper metadata before making them "live"

---

## Data Flow After Import

1. User uploads Letterboxd CSV
2. Frontend calls `POST /api/letterboxd_import.php?action=bulk_import`
3. API creates movies with `status = 'ended'`
4. API returns `success: true, created: N, created_movies: [...]`
5. **Frontend now (with fix):**
   - Shows success message
   - Resets `statusFilter.value = ''`
   - Resets `searchInput.value = ''`
   - Calls `loadMovies()` which fetches with `status=` (empty)
   - API returns ALL movies including the newly created 'ended' ones
   - Grid renders with new movies visible
   - Statistics update

6. User sees the new movies immediately without manual filter changes

---

## Status Filter Options

The select dropdown now offers:

```
☑ All Movies (value="", default)
  ○ Now Showing (value="now_showing")
  ○ Coming Soon (value="coming_soon")
  ○ Ended (value="ended")
```

When "All Movies" is selected, the API receives an empty string and returns all movies regardless of status.

---

## Testing Workflow

### Test 1: Page Load
```
1. Navigate to Admin → Movies
2. Verify "All Movies" dropdown shows selected
3. Verify all movies display (or "No movies" if empty database)
```

### Test 2: Manual Addition
```
1. Click "Add Movie"
2. Fill form and submit
3. Verify movie appears in grid
4. Verify "All Movies" dropdown is still selected
```

### Test 3: Letterboxd Import (Main Test)
```
1. Click "Import CSV"
2. Upload Letterboxd CSV
3. Click "Import All Now"
4. VERIFY: Success message appears
5. VERIFY: Grid refreshes and shows new movies
6. VERIFY: "All Movies" dropdown is selected
7. VERIFY: Stats updated with new count
8. Change dropdown to "Ended" - should see imported movies
9. Change dropdown to "All Movies" - should see everything
```

### Test 4: Filtering
```
1. Select "Now Showing" - grid shows only active movies
2. Select "Coming Soon" - grid shows upcoming
3. Select "Ended" - grid shows past/imported
4. Select "All Movies" - grid shows everything
```

---

## Rollback Instructions (If Needed)

If you need to revert these changes:

1. In [Admin/movies.php](Admin/movies.php) line 604:
   - Remove `selected` attribute from "All Movies" option

2. In [Admin/movies.php](Admin/movies.php) lines 1040-1044:
   - Restore to: `const status = statusFilter.value;`
   - Restore to: `const url = \`${API_URL}?action=list&search=${encodeURIComponent(search)}&status=${status}\`;`

3. In [Admin/movies.php](Admin/movies.php) lines 1438-1447:
   - Remove the filter reset code

4. In [Admin/movies.php](Admin/movies.php) lines 1566-1570:
   - Remove the filter reset code

The API and import functionality will remain unchanged and still work correctly.
