## 📄 File 2: `DEVELOPER_GUIDE.md`

```markdown
# IAMS — Developer Guide

## Purpose

This document is for any developer who will maintain, extend, or redeploy this PHP application. It explains **what** was built, **how** it works, and **why** certain decisions were made — especially the fixes applied during the InfinityFree deployment.

---

## Table of Contents

1. [Tech Stack & Version Lock](#tech-stack--version-lock)
2. [Project Structure](#project-structure)
3. [Core Architecture](#core-architecture)
4. [Session & Authentication System](#session--authentication-system)
5. [Role-Based Access Control](#role-based-access-control)
6. [Matching Algorithm](#matching-algorithm)
7. [Notification System](#notification-system)
8. [Email / SMTP Integration](#email--smtp-integration)
9. [Database Schema Decisions](#database-schema-decisions)
10. [PHP 7.4 Compatibility Fixes](#php-74-compatibility-fixes)
11. [InfinityFree Deployment Notes](#infinityfree-deployment-notes)
12. [Common Tasks](#common-tasks)
13. [Known Issues & Gotchas](#known-issues--gotchas)
14. [Quick Reference — Key Files](#quick-reference--key-files)

---

## Tech Stack & Version Lock

| Component | Version / Detail | Why Locked |
|-----------|------------------|------------|
| PHP | **7.4** (InfinityFree constraint) | All code rewritten from PHP 8+ to 7.4. Do NOT use `match()`, `str_contains()`, union types (`string\|array`), or `never` return type. |
| MySQL | 5.7+ / MariaDB 10.x | `ENUM` columns, `TIMESTAMP` defaults, `utf8mb4` charset. |
| Authentication | Bcrypt, cost 12 | `password_hash()` / `password_verify()`. Hash MUST be generated on the production server for compatibility. |
| Sessions | PHP native, 30-min timeout | `session_set_cookie_params()` with `httponly=true`, `samesite=Lax`. |
| Email | PHP `mail()` fallback + SMTP config | `MAIL_ENABLED = false` by default. Configure `mailer.php` for production. |
| Hosting | InfinityFree (free tier) | No SSH, no Composer, no cron jobs. All dependencies are inline. |

---

## Project Structure

```
iams/
├── admin/                    # Admin & Coordinator panel
│   ├── index.php            # Dashboard — reads stats from DB, no caching
│   ├── applications.php     # App review — search, filter by status/daterange, status update with notifications
│   ├── students.php         # Student list — search by name/email/student number, toggle active
│   ├── organisations.php    # Org list — capacity tracking, matched students, toggle active
│   ├── matching.php         # ⭐ Core: matching algorithm + suggestion management + manual override
│   ├── jobs.php             # Job post CRUD — create/edit/toggle/delete, interest count display
│   ├── documents.php        # All student documents — search by type/student
│   ├── logbooks.php         # Logbook review — filter by status, add supervisor comments, mark reviewed
│   ├── assessments.php      # Site visit assessments — 2 visits per student, slider-based scoring
│   ├── reports.php          # Analytics — placement stats, student ranking, CSV exports, grade dialog
│   ├── reminders.php        # Bulk reminders — logbook deadlines, missing docs, supervisor reports, custom
│   └── users.php            # Internal user management — admin only, CRUD for admin/coordinator accounts
│   ├── register.php         # Admin/Coordinator registration (admin-only access)
│
├── config/                   # Configuration & shared classes
│   ├── database.php         # PDO singleton — credentials from constants, graceful error pages
│   ├── database.example.php # Template — safe to commit to Git
│   ├── session.php          # Session config — timeout, CSRF tokens, auth helpers (requireLogin, requireRole, requireAdmin)
│   ├── auth.php             # Auth class — hashPassword, verifyPassword, login, logout, brute-force check
│   └── mailer.php           # Mailer class — static methods for each email type, MAIL_ENABLED flag
│
├── includes/
│   └── header.php           # Shared nav — role-based menu, notification badge, IAMS branding
│
├── org/
│   └── dashboard.php        # Organisation portal — tabs for matched students, job posts, profile
│
├── uploads/                  # User documents — .htaccess prevents direct script execution
│
├── index.php                 # Public landing page — shows job listings, no redirect for logged-in users
├── login.php                 # Login — brute-force countdown, redirects by role
├── logout.php                # Full session destroy + cookie clear
├── register.php              # Student registration — live password strength meter
├── register_org.php          # Organisation registration with capacity & skill requirements
├── dashboard.php             # Student dashboard — tabs: Home, Apply, Docs, Jobs, Profile, Logbook, Report, Notifs
├── logbook.php               # Student logbook — week number, activities, learning outcomes, challenges
├── student_report.php        # Student final report — title, executive summary, body, conclusion, file upload
├── supervisor_report.php     # Org supervisor report — 5-category rating + recommendation
├── notifications.php         # Notification centre — filter by type, mark read, delete
├── forgot_password.php       # Password reset — token-based, 1-hour expiry, demo link fallback
├── download.php              # Secure file serving — role-checked, MIME-type mapping
└── database_schema.sql       # Full schema — 17 tables, `IF NOT EXISTS`, sample job posts
```

---

## Core Architecture

### Custom Auth System (Not a Framework)

The entire system is built on **plain PHP** with a custom MVC-lite pattern:
- **No framework** — every route is a standalone `.php` file
- **No autoloading** — all dependencies included via `require_once`
- **No Composer** — all third-party code is inline
- **No ORM** — all queries use PDO prepared statements

**Why:** InfinityFree does not support Composer or SSH. The system must be fully self-contained and uploadable via FTP.

### Page Flow Pattern

Every page follows this structure:
```php
<?php
// 1. Require config files
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/auth.php';

// 2. Access control
requireLogin();      // or requireRole('admin'), requireAdmin()

// 3. Get current user
$user = getCurrentUser();

// 4. Get database instance
$db = Database::getInstance();

// 5. Handle POST requests (form submissions)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    // process form...
}

// 6. Load data for display
// ...queries...

// 7. Set page title (used by header.php)
$pageTitle = 'Page Name';

// 8. Include shared header
include __DIR__ . '/includes/header.php';

// 9. HTML output
// ...

// 10. Footer
?>
</body></html>
```

### Database Singleton

`config/database.php` implements a PDO singleton:
```php
class Database {
    private static $instance = null;
    
    public static function getInstance(): PDO {
        if (self::$instance === null) {
            // Check if credentials are still placeholders → show setup page
            // Connect with PDO::ATTR_ERRMODE => EXCEPTION
            // On failure → show friendly error page
        }
        return self::$instance;
    }
}
```

**Why singleton:** Avoids multiple database connections per request in a file-based architecture where each page independently requires the config.

---

## Session & Authentication System

### Session Configuration (`config/session.php`)

- **Timeout:** 30 minutes (`SESSION_TIMEOUT = 1800`)
- **Cookie params:** `httponly=true`, `samesite=Lax`, `secure` when HTTPS
- **Session fixation prevention:** Regenerates ID every 5 minutes
- **Cache control headers:** Prevent back-button after logout

### Auth Class (`config/auth.php`)

Key methods:
| Method | Purpose |
|--------|---------|
| `hashPassword($pw)` | `password_hash($pw, PASSWORD_BCRYPT, ['cost'=>12])` |
| `verifyPassword($pw, $hash)` | `password_verify($pw, $hash)` |
| `login($email, $pw)` | Full login flow — lockout check, verify, session setup, `last_login` update |
| `logout()` | Destroy session + clear cookie |
| `isLockedOut($email, $ip)` | Check brute-force status (uses `NOW() - INTERVAL` — timezone-safe) |
| `recordFailedAttempt($email, $ip)` | Insert into `login_attempts` |
| `clearAttempts($email, $ip)` | Delete attempts on successful login |
| `remainingAttempts($email, $ip)` | Count remaining attempts before lockout |

### Brute-Force Protection

- **Threshold:** 5 failed attempts per email/IP
- **Lockout window:** 15 minutes
- **Time calculation:** Uses MySQL `NOW() - INTERVAL` instead of PHP's `date()` to avoid timezone mismatch (see deployment notes)

### CSRF Protection (`config/session.php`)

- Token stored in `$_SESSION['_csrf_token']`
- `csrf_field()` outputs `<input type="hidden" name="_csrf" value="...">`
- `csrf_check()` validates on every POST request
- `csrf_verify()` uses `hash_equals()` for timing-safe comparison

---

## Role-Based Access Control

Four roles defined in `users.role` (ENUM):
- `student` — can only access own data and student pages
- `organisation` — can only access org dashboard and matched students
- `coordinator` — can access all admin pages **except** user management
- `admin` — full access including user management

### Helper Functions (`config/session.php`)

```php
requireLogin()       // Redirect to /login.php if not logged in
requireRole($roles)  // Accepts string or array — checks $_SESSION['role']
requireAdmin()       // Allows both 'admin' and 'coordinator' roles
```

### Admin vs Coordinator Differentiation

In `admin/users.php`:
```php
$isAdmin = ($user['role'] === 'admin');
```
- Only `$isAdmin` can create/edit/disable users
- Coordinator sees read-only list

In `includes/header.php`:
```php
<?php if ($isAdmin): ?><a href="/admin/users.php">Users</a><?php endif; ?>
```

---

## Matching Algorithm

Located in `admin/matching.php`. The core function:

```php
function computeMatchScore(array $app, array $org, array $orgJobIds, array $studentJobInterests): float {
    $score = 0;
    
    // 1. Skill overlap (max 50 pts)
    // Compares student skills vs org required_skills using substring matching
    
    // 2. Location preference (max 30 pts)
    // Exact match = 30 pts, substring match = 30 pts, mismatch = 5 pts, no data = 15 pts
    
    // 3. Capacity available (10 pts)
    // If org has unfilled slots, +10 pts
    
    // 4. Job interest bonus (10 pts)
    // If student expressed interest in org's job posts, +10 pts
    
    return min(100.0, $score);
}
```

### Match Flow

1. Fetch unmatched applications (`status IN ('pending','under_review')` with no `matched_org_id`)
2. Fetch active organisations with `available = capacity - confirmed_matches`
3. For each application, iterate all orgs → compute score → track best
4. If best score ≥ 20%, insert `suggested` match into `matches` table
5. Coordinator reviews suggestions → **Confirm** or **Decline**
6. On confirm: application status → `matched`, notification sent to student

### Manual Override

The "Manual Match" tab allows direct assignment without algorithm scoring. Sets match score to 100%.

---

## Notification System

### Database Table

`notifications` table: `notif_id, user_id, title, message, type (info/warning/success/deadline), is_read, link, created_at`

### Trigger Points

Notifications are created at these events:
- Application submitted → notifies all coordinators/admins
- Application status updated → notifies the student
- Match confirmed → notifies the student
- Logbook submitted → notifies coordinators
- Logbook reviewed → notifies student
- Supervisor report submitted → notifies student + coordinators
- Final report graded → notifies student
- Site visit recorded → notifies student
- Bulk reminders sent → notifies targeted users

### Display

- `includes/header.php` shows bell icon with unread count badge
- `/notifications.php` — full notification centre with filter tabs (All, Unread, Read)
- Student `dashboard.php?tab=notifs` — recent 20 notifications with mark-all-read

---

## Email / SMTP Integration

### Architecture

`config/mailer.php` defines a `Mailer` class with static methods for each email type:
```php
Mailer::send($to, $toName, $subject, $body)
Mailer::sendPasswordReset($to, $toName, $resetLink)
Mailer::sendApplicationUpdate($to, $toName, $status, $notes)
Mailer::sendLogbookReminder($to, $toName, $week)
Mailer::sendReportGraded($to, $toName, $grade, $feedback)
```

### Configuration

```php
define('MAIL_ENABLED', false);  // Set to true for production
define('MAIL_SMTP_HOST', 'smtp.brevo.com');
define('MAIL_SMTP_PORT', 587);
define('MAIL_SMTP_USER', 'your-email');
define('MAIL_SMTP_PASS', 'your-key');
define('MAIL_FROM', 'noreply@ub.ac.bw');
```

**When `MAIL_ENABLED = false`:** All email functions silently skip and the password reset page shows a demo link on-screen instead.

### Recommended SMTP Provider

**Brevo (formerly Sendinblue)** — 300 free emails/day, no credit card required initially.

---

## Database Schema Decisions

### `ENUM` Columns

Used extensively for fixed-choice fields (`role`, `status`, `type`). **Why:** Ensures data integrity at the database level — impossible to insert invalid values.

### `utf8mb4` Charset

All tables use `utf8mb4_unicode_ci`. **Why:** Full Unicode support including emoji and Setswana characters.

### Foreign Keys with CASCADE/SET NULL

- `ON DELETE CASCADE` — deleting a user removes their applications, documents, logbooks, etc.
- `ON DELETE SET NULL` — deleting an organisation sets `matched_org_id` to NULL in applications, preserving the application record.

### `TIMESTAMP` vs `DATETIME`

All date columns use `TIMESTAMP` for automatic timezone conversion. Botswana is UTC+2 (`SET time_zone = "+02:00"`).

### Missing Table Additions (During Deployment)

The original schema was imported partially. These tables/columns were added later:
- `password_resets` — added via manual SQL during password reset debugging
- `users.last_login` — added via `ALTER TABLE` when login failed silently
- `job_posts.slots` — present in final schema but was missing in an earlier import

---

## PHP 7.4 Compatibility Fixes

The original codebase was written for PHP 8.x but deployed to InfinityFree running PHP 7.4. These changes were applied across all files:

| PHP 8+ Feature | Fix Applied | Files Affected |
|----------------|-------------|----------------|
| `match()` expression | Replaced with `if/elseif` chains | `login.php`, `index.php`, `admin/applications.php`, `admin/matching.php` |
| `str_contains()` | Replaced with `strpos($haystack, $needle) !== false` | `login.php`, `admin/matching.php` |
| `str_starts_with()` | Replaced with `strpos($haystack, $needle) === 0` | `login.php` |
| `never` return type | Changed to `: void` + `exit()` | `config/database.php` |
| `string\|array` union type | Removed type hint entirely | `config/session.php` (`requireRole()`) |

**Rule for future changes:** Do NOT introduce any PHP 8+ syntax. Always check against PHP 7.4 compatibility.

---

## InfinityFree Deployment Notes

### Hosting Constraints

| Constraint | Impact |
|------------|--------|
| PHP 7.4 only | All code must be 7.4 compatible |
| No SSH / Composer | No external libraries, no `composer install` |
| No cron jobs | Automated reminders must be triggered manually |
| `mail()` unreliable | SMTP integration via Brevo recommended |
| 10MB file upload limit | Enforced in `upload_document.php` and `dashboard.php` |
| Ephemeral file system | Store persistent assets on Cloudinary or similar (not configured) |
| DNS propagation delay | Up to 72 hours for new domains |

### Deployment Checklist

1. Import `database_schema.sql` via phpMyAdmin
2. Verify **all 17 tables** are created (not just 8 — partial imports are silent)
3. Upload all files to `htdocs/` (not a subfolder)
4. Edit `config/database.php` with InfinityFree MySQL credentials
5. Visit `setup.php` to create admin + coordinator accounts
6. **Delete `setup.php`**
7. Delete any debug files (`health.php`, `test.php`, `fixpass.php`, etc.)
8. If login fails with password mismatch, **generate hash on the server** using a temporary script (bcrypt hashes created elsewhere may not verify on InfinityFree's PHP build)

### Known InfinityFree Errors

| Error | Cause | Fix |
|-------|-------|-----|
| `Class "Database" not found` | `database.php` uploaded without the class definition (only defines) | Re-upload complete file |
| `Table '...' doesn't exist` | Partial schema import | Drop all tables, re-import full schema |
| Password always rejected | bcrypt hash incompatible | Generate hash on server with `password_hash()` |

---

## Common Tasks

### Adding a New Admin Page

1. Create the PHP file in `admin/`
2. Follow the page flow pattern (config requires, access control, header include)
3. Set `$pageTitle` for the `<title>` tag
4. Add the nav link in `includes/header.php` under the admin/coordinator section
5. If admin-only, wrap in `<?php if ($isAdmin): ?>`

### Adding a New Student Page

1. Create the PHP file in the root `iams/` folder
2. Use `requireLogin()` and `requireRole('student')`
3. Add the nav link in `includes/header.php` under the student section

### Adding a New Notification Type

1. Add a new trigger in the relevant POST handler:
   ```php
   $db->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)")
      ->execute([$targetUserId, 'Title', 'Message', 'info', '/link.php']);
   ```
2. Available types: `info`, `warning`, `success`, `deadline`

### Resetting Admin Password

### Creating Additional Admin Accounts

1. Login as an existing admin
2. Visit `/admin/register.php`
3. Fill in the form (name, email, role, password)
4. Click "Create Account"
5. The new admin/coordinator can now log in immediately

**Security:** This page checks:
- If no admins exist at all (first-run scenario) → allows access
- If logged in user is an admin → allows access  
- Otherwise → returns 403 Access Denied

**Note:** This replaces the old `setup.php` approach for adding users after initial deployment.

If locked out of admin:
1. Create a file `resetpw.php` in `htdocs/`:
   ```php
   <?php
   require_once __DIR__ . '/config/database.php';
   $db = Database::getInstance();
   $hash = password_hash('NewPassword123!', PASSWORD_BCRYPT, ['cost'=>12]);
   $db->prepare("UPDATE users SET password_hash=? WHERE email='admin@ub.ac.bw'")->execute([$hash]);
   echo "Done. Delete this file.";
   ```
2. Visit the URL, then **delete the file immediately**.

---

## Known Issues & Gotchas

1. **Password hash incompatibility:** Bcrypt hashes generated on one PHP build may not verify on another. Always generate hashes on the production server for admin accounts.

2. **MySQL timezone mismatch:** PHP and MySQL may have different timezones. All time-sensitive queries (brute-force lockout, password reset expiry) use `NOW() - INTERVAL` to avoid this.

3. **Partial schema imports:** The "Import has been successfully finished" message in phpMyAdmin doesn't mean all tables were created. Always verify table count after import (should be 17).

4. **Email disabled by default:** Password reset works in demo mode (shows link on-screen). Enable `MAIL_ENABLED` and configure SMTP for production.

5. **No automatic cron jobs:** Reminders must be triggered manually via the `/admin/reminders.php` page. There's no server-side scheduler on free hosting.

6. **File permissions:** On InfinityFree, uploaded files sometimes get incorrect permissions. If uploads fail, try FTP upload instead of the web File Manager.

7. **`.htaccess` conflicts:** InfinityFree has a default `.htaccess` that may conflict with custom rules. If 500 errors appear, temporarily rename `.htaccess` to test.

---

## Quick Reference — Key Files

| File | Lines (approx.) | What It Does |
|------|-----------------|--------------|
| `config/auth.php` | ~115 | Authentication class — hash, verify, login, logout, brute-force |
| `config/session.php` | ~95 | Session config, CSRF, auth helpers |
| `config/database.php` | ~50 | PDO singleton with graceful error pages |
| `config/mailer.php` | ~90 | Email class with static methods per email type |
| `admin/matching.php` | ~350 | Matching algorithm + suggestion management + manual override |
| `admin/reports.php` | ~400 | Stats, student ranking, CSV exports, grade dialog |
| `admin/reminders.php` | ~180 | Bulk reminders and custom notifications |
| `admin/users.php` | ~200 | Internal user management (admin only) |
| `admin/applications.php` | ~250 | Application review — detail view, status update |
| `dashboard.php` (student) | ~400 | Student dashboard — 8 tabs |
| `includes/header.php` | ~200 | Shared nav, styles, notification badge |
| `index.php` | ~250 | Public landing page |
| `logbook.php` | ~150 | Student weekly logbook |
| `supervisor_report.php` | ~250 | Organisation supervisor report |
| `forgot_password.php` | ~140 | Password reset — token generation, email/demo |
| `register.php` | ~150 | Student registration with password strength meter |

---

*Last updated: May 2026*
```

---

