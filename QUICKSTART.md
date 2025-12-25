# 🚀 Quick Start Guide - MovieBook v2.0

## Step-by-Step Setup (5 Minutes)

### 1. Start XAMPP Services
```
✅ Open XAMPP Control Panel
✅ Click "Start" for Apache
✅ Click "Start" for MySQL
✅ Both should show green "Running" status
```

### 2. Import Database Tables
Open Command Prompt and run:
```bash
cd C:\xampp\mysql\bin

mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS moviebook;"
mysql.exe -u root moviebook < C:/xampp/htdocs/Moviebook/database/schema.sql
mysql.exe -u root moviebook < C:/xampp/htdocs/Moviebook/database/users.sql
mysql.exe -u root moviebook < C:/xampp/htdocs/Moviebook/database/our_picks.sql
mysql.exe -u root moviebook < C:/xampp/htdocs/Moviebook/database/reviews.sql
```

**Alternative:** Use phpMyAdmin
```
1. Visit: http://localhost/phpmyadmin
2. Create database "moviebook"
3. Import all SQL files from database/ folder in order
```

### 3. Verify Installation
Visit: **http://localhost/Moviebook/test_db.php**

You should see:
```
✓ Connected successfully to database: moviebook
✓ users - EXISTS
✓ user_library - EXISTS
✓ tmdb_cache - EXISTS
✓ reviews - EXISTS
...all other tables...
✓ Database: OK
```

### 4. Start Using MovieBook!
Main Application: **http://localhost/Moviebook/index.html**

## First Time User Flow

### Create Your Account
1. Open http://localhost/Moviebook/index.html
2. Click **"Login"** button (top right)
3. Click **"Sign up"** link
4. Fill in:
   - Username: `yourname`
   - Email: `your@email.com`
   - Phone: `1234567890`
   - Password: `yourpassword` (min 6 chars)
   - Confirm Password: (same as above)
5. Click **"Create Account"**
6. ✅ You're automatically logged in!

### Explore Movies
1. Browse **Streaming** section (default view)
2. Scroll through:
   - Our Picks (admin curated)
   - New on Streaming
   - Trending Now
3. Click any movie poster to see details

### Build Your Library
1. Click on a movie
2. In the popup, click:
   - **"Add to Watchlist"** - Movies you want to watch
   - **"Add to Favorites"** - Movies you love
3. See green success notification
4. Go to **Library** tab (navigation)
5. Your movies are saved!

### Leave a Review
1. Click on any movie
2. Scroll to **"Write a Review"** section
3. Click stars to rate (1-5)
4. Write your review (optional)
5. Click **"Submit Review"**
6. ✅ Review posted!

### Coming Soon Movies
1. Click **Tickets** tab
2. Scroll to **"Coming Soon"** section
3. Click bell icon (🔔) on any upcoming movie
4. Adds to your **Pre-Release Interest** list
5. View in Library → Interest tab

## Admin Panel

### Access Admin
URL: **http://localhost/Moviebook/admin/login.html**

**Credentials:**
- Username: `admin`
- Password: `admin123`

### Admin Features
- **Dashboard** - View statistics
- **Our Picks** - Add featured movies
- **Manage Movies** - Add theater movies
- **Showtimes** - Schedule screenings
- **Bookings** - View reservations

### Add Movie to "Our Picks"
1. Login to admin
2. Go to **"Our Picks"** page
3. Search for movie name
4. Click **"Add to Picks"**
5. Movie now appears on main page!

## Testing Features

### Test Authentication
Visit: **http://localhost/Moviebook/test_auth.html**

Features to test:
1. **Registration** - Create test account
2. **Login** - Test credentials
3. **Session Check** - Verify logged in
4. **Library Operations** - Add/remove movies
5. **Reviews** - Submit test review
6. **Cache** - Check API caching

## Common Tasks

### View Your Profile
1. Click your username (top right)
2. Click **"Profile"**
3. See:
   - Account information
   - Library statistics
   - Member since date

### Logout
1. Click your username
2. Click **"Logout"**
3. ✅ Logged out

### Search Movies
1. Click search box (top right)
2. Type movie name
3. Results appear instantly
4. Click result to view details

## Troubleshooting

### "Database connection failed"
**Fix:**
1. Make sure MySQL is running in XAMPP
2. Check green light next to MySQL
3. Import database files if not done

### "Please login to..."
**Fix:**
1. Click "Login" button
2. Sign up or login
3. Feature requires authentication

### Movies not loading
**Fix:**
1. Check internet connection (TMDB API needs internet)
2. Wait a few seconds
3. Refresh page
4. Check browser console for errors

### Can't add to library
**Fix:**
1. Make sure you're logged in
2. Check for green success notification
3. If red error, read the message
4. May already be in library

## Performance Tips

### First Load
- Initial page load fetches from TMDB API
- Takes 2-3 seconds
- Data is cached automatically

### Subsequent Loads
- Most data loaded from cache
- Much faster (instant)
- Cache expires after 6-12 hours

### Clear Cache
If needed, run in phpMyAdmin:
```sql
TRUNCATE TABLE tmdb_cache;
```

## File Locations

### Main Pages
- Homepage: `index.html`
- Admin: `admin/login.html`
- Test: `test_auth.html`

### APIs
- Auth: `api/auth.php`
- Library: `api/library.php`
- Movies: `api/movies.php`
- Reviews: `api/reviews.php`

### Configuration
- Database: `config.php`
- TMDB API Key: In `config.php`

## Next Steps

### Customize
- Add movies to "Our Picks" (admin panel)
- Update TMDB API key if needed
- Customize styles in `css/new-style.css`

### Expand
- Add more admin users
- Create movie collections
- Build booking history

### Share
- Share on local network (use IP instead of localhost)
- Deploy to web server
- Add domain name

## Success Checklist

✅ XAMPP running (Apache + MySQL)
✅ Database imported (test_db.php shows green)
✅ Can access main page
✅ Can create account
✅ Can login/logout
✅ Can add movies to library
✅ Can submit reviews
✅ Admin panel accessible
✅ Search works
✅ All sections load (Streaming/Tickets/Library)

## Getting Help

### Check These First
1. **Database Status:** http://localhost/Moviebook/test_db.php
2. **Browser Console:** F12 → Console tab (check for errors)
3. **XAMPP Logs:** C:\xampp\apache\logs\error.log

### Documentation
- Full README: `README.md`
- Auth Guide: `USER_AUTH_GUIDE.md`
- What's New: `WHATS_NEW.md`

## Quick Reference

### URLs
```
Main Site:   http://localhost/Moviebook/index.html
Admin:       http://localhost/Moviebook/admin/login.html
DB Test:     http://localhost/Moviebook/test_db.php
Auth Test:   http://localhost/Moviebook/test_auth.html
phpMyAdmin:  http://localhost/phpmyadmin
```

### Default Logins
```
Admin:
  Username: admin
  Password: admin123

User:
  Create your own!
```

### Database Tables
```
✅ users          - User accounts
✅ user_library   - Personal libraries
✅ tmdb_cache     - API cache
✅ reviews        - Movie reviews
✅ our_picks      - Featured movies
✅ movies         - Theater movies
✅ showtimes      - Schedules
✅ bookings       - Reservations
✅ seats          - Seat availability
✅ admin_users    - Admin accounts
```

---

**🎬 You're all set! Start exploring MovieBook! 🍿**

**Need help?** Check the full documentation in `README.md` or `USER_AUTH_GUIDE.md`
