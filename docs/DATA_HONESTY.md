# MovieBook Data Honesty Invariant

> **This document defines a permanent, non-negotiable rule for the MovieBook codebase.**

## Core Principle

**Every visible number, row, chart, or label on the MovieBook website must be explainable by a real database record and a real action taken by a real user or admin. If such an explanation cannot be given, the data MUST NOT be displayed.**

---

## Prohibited Practices

The following are permanently forbidden anywhere in the MovieBook codebase:

### 1. Fake Data Injection
- ❌ Hardcoded arrays with sample movies, users, bookings, or theaters
- ❌ Static HTML tables with demo entries
- ❌ Placeholder text that implies activity (e.g., "12 Angry Men" as a fake movie)
- ❌ Commented demo blocks that could be re-enabled
- ❌ "Temporary" arrays for visual purposes

### 2. Fallback Data
- ❌ Substituting fake content when a database query returns zero rows
- ❌ Default statistics that imply activity (e.g., "Total Revenue: ₹45,67,890" when none exists)
- ❌ Growth percentages that are not calculated from real data
- ❌ Geographic distribution that is invented

### 3. Seeding Scripts
- ❌ Scripts that auto-insert movies, users, bookings, or theaters
- ❌ Demo password reset utilities
- ❌ Quick setup scripts that populate data

### 4. Placeholder Images as Content Substitute
- ❌ Using `via.placeholder.com` or similar to show fake movie posters
- ✅ ALLOWED: Empty state icons or "no image" indicators for truly missing images

---

## Required Behaviors

### Empty States
When a database query returns zero results, the UI MUST display:
- A clear empty state message
- Zero values for counts and statistics
- An icon or illustration indicating "no data"
- Optional: A call-to-action explaining how to add data

Example:
```html
<div class="empty-state">
    <i class="bi bi-inbox"></i>
    <h3>No Bookings Yet</h3>
    <p>Bookings will appear here when customers make reservations.</p>
</div>
```

### Statistics and Metrics
All statistics MUST be:
- Calculated from real database records
- Show "0" or "-" when no data exists
- Never fabricated for visual appeal

### Joins and Aggregations
- LEFT JOINs returning NULL must display as empty, not as meaningful defaults
- COUNT() returning 0 must display as 0
- SUM() returning NULL must display as ₹0 or equivalent

---

## Audit Checklist

Before any PR is merged, verify:

- [ ] No hardcoded movie titles, user names, or theater names
- [ ] No static HTML tables with sample data
- [ ] No placeholder image URLs used as content (only for truly missing assets)
- [ ] All statistics are calculated from database queries
- [ ] All empty states show "no data" messaging, not fake entries
- [ ] No seed or demo scripts remain active
- [ ] Growth metrics are real calculations, not hardcoded percentages

---

## Rationale

MovieBook must behave like a real production system at all times. Demo data:
- Creates false impressions for administrators
- Masks bugs that only appear with empty datasets
- Makes it impossible to verify if features work correctly
- Violates trust by displaying information that does not exist

---

## Enforcement

This invariant applies to:
- All PHP files
- All JavaScript files  
- All HTML templates
- All CSS (no background images that imply content)
- All database migrations
- All API responses

**This policy is permanent and may not be overridden for "demos," "presentations," or "visual testing."**

---

## Blocked Scripts Reference

The following scripts have been permanently disabled:

| Script | Reason |
|--------|--------|
| `/scripts/seed_booking_data.php` | Auto-inserts fake theaters |
| `/scripts/seed_showtimes.php` | Auto-inserts fake showtimes |
| `/scripts/seed_current_theatrical.php` | Auto-inserts TMDB movies |
| `/scripts/import_movies.php` | Auto-imports from TMDB |
| `/scripts/quick_setup.php` | All-in-one fake data setup |
| `/auth/reset_demo_passwords.php` | Demo user manipulation |

---

*Document created: 2026-01-30*  
*Status: PERMANENT INVARIANT*
