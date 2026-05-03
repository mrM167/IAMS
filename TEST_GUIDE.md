## 📄 File 3: `TEST_GUIDE.md`

```markdown
# IAMS — Test Guide

## Purpose

This document provides step-by-step test procedures for verifying every feature in the IAMS system. Use it after initial deployment, after major changes, or before demonstrations.

---

## Table of Contents

1. [Test Accounts](#test-accounts)
2. [Phase 1: Authentication & Registration](#phase-1-authentication--registration)
3. [Phase 2: Student Features](#phase-2-student-features)
4. [Phase 3: Admin/Coordinator Features](#phase-3-admincoordinator-features)
5. [Phase 4: Logbooks & Assessments](#phase-4-logbooks--assessments)
6. [Phase 5: Reports & Reminders](#phase-5-reports--reminders)
7. [Edge Cases & Error Handling](#edge-cases--error-handling)
8. [How to Reset Test Data](#how-to-reset-test-data)

---

## Test Accounts

| Role | Email | Password | Notes |
|------|-------|----------|-------|
| Admin | `admin@ub.ac.bw` | `Admin@1234` | Full access |
| Coordinator | `coordinator@ub.ac.bw` | `Coord@1234` | All admin except user management |
| Student A | `202200960@ub.ac.bw` | `Saitama@777` | Has application, profile, documents |
| Student B | *Create fresh via registration* | — | For testing application flow from scratch |
| Organisation | `org@email.com` | `Saitama@777` | Has job posts, matched students |

> If any account doesn't work, see [How to Reset Admin Password](#how-to-reset-admin-password) in the Developer Guide.

---

## Phase 1: Authentication & Registration

### Test 1.1 — Student Registration

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Go to `http://tomdon.infinityfreeapp.com/register.php` | Registration form loads |
| 2 | Submit empty form | Validation errors appear for all required fields |
| 3 | Enter a weak password (e.g., `abc`) | Password strength meter stays red; error message lists missing characters |
| 4 | Enter mismatched passwords | "Passwords do not match" error appears |
| 5 | Enter a valid student number but existing email (`admin@ub.ac.bw`) | "Email or student number is already registered" |
| 6 | Fill all fields correctly with a new email | "Registration successful! Login now →" appears |
| 7 | Click "Login now →" | Redirected to `/login.php` |
| 8 | Log in with newly created credentials | Redirected to student dashboard |

### Test 1.2 — Organisation Registration

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Go to `http://tomdon.infinityfreeapp.com/register_org.php` | Registration form loads |
| 2 | Submit empty form | Validation errors for required fields |
| 3 | Fill all fields with capacity = 0 | "Capacity must be at least 1" |
| 4 | Fill correctly with a new email | "Registration successful!" |
| 5 | Log in with new org credentials | Redirected to org dashboard at `/org/dashboard.php` |

### Test 1.3 — Login & Brute-Force Protection

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Go to `/login.php` | Login form loads |
| 2 | Enter wrong email/password | "Invalid email or password. 4 attempt(s) remaining." |
| 3 | Repeat 4 more times (5 total failures) | "Account locked for 15 minutes. Try again later." |
| 4 | Wait 15 minutes (or clear `login_attempts` table in phpMyAdmin) | Can attempt login again |
| 5 | Enter correct credentials | Redirected to role-specific dashboard |

### Test 1.4 — Session Timeout

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Log in as any user | Dashboard loads |
| 2 | Wait 30+ minutes without clicking anything | Session expires |
| 3 | Click any link or refresh | Redirected to `/login.php?timeout=1` with "Session expired due to inactivity" message |

### Test 1.5 — Password Reset

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Go to `/forgot_password.php` | Form loads |
| 2 | Enter a registered email | "If your email is registered, a reset link has been sent." |
| 3 | If `MAIL_ENABLED=false`: a demo link appears on screen | Copy the link |
| 4 | Visit the reset link | Form for new password appears |
| 5 | Enter weak password | Validation errors |
| 6 | Enter strong matching passwords | "Password changed! You can now log in." |
| 7 | Log in with new password | Success |
| 8 | Try the old password | "Invalid email or password" |

### Test 1.6 — Logout & No Back-Button

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Log in → click Logout | Redirected to `/login.php?msg=logged_out` |
| 2 | Press browser Back button | Should NOT show dashboard — page expired or redirects to login |

---

## Phase 2: Student Features

### Test 2.1 — Student Dashboard

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Log in as Student A | Dashboard loads with stats (Application, Documents, Logbooks, Job Interests) |
| 2 | Check all tabs visible | Home, Apply, Docs, Jobs, Profile, Logbook, Report, Notifications |
| 3 | Click each tab | Each loads without errors |

### Test 2.2 — Profile Update

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Go to Profile tab | Form loads with any existing data |
| 2 | Fill in LinkedIn, GitHub, Skills | All fields editable |
| 3 | Click "Save Profile" | "Profile saved!" message |
| 4 | Refresh — data persists | Values are retained |

### Test 2.3 — Application Submission

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create a new student (or use one without an application) | No application exists |
| 2 | Go to Apply tab | Application form loads |
| 3 | Submit empty form | Validation errors |
| 4 | Fill all required fields | Form submits |
| 5 | Check "My Application" on Home tab | Status shows "Pending" |
| 6 | Try to submit another application | "You already have an active application" |

### Test 2.4 — Document Upload

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Go to Documents tab | Upload form + existing documents list |
| 2 | Try uploading a `.exe` file | "Invalid file type" error |
| 3 | Try uploading a file >10MB | "File too large" error |
| 4 | Upload a valid PDF (<10MB) | "Document uploaded!" — file appears in list |
| 5 | Click Download button | File downloads correctly |

### Test 2.5 — Job Browsing & Interest

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Go to Jobs tab | List of active job posts appears |
| 2 | Click "Express Interest" on a job | Button changes to "✓ Interest Expressed" |
| 3 | Click again on a different job | Both show "Interest Expressed" |
| 4 | Go to public home page (`/index.php`) | Job listings visible |

---

## Phase 3: Admin/Coordinator Features

### Test 3.1 — Admin Dashboard

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Log in as Admin | `/admin/index.php` loads |
| 2 | Check stats grid | Shows: Total Students, Organisations, Pending Review, Matched, Rejected, etc. |
| 3 | Check recent applications table | Lists recent submissions |
| 4 | Check organisations table | Lists registered orgs |

### Test 3.2 — Application Review

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Go to Applications | All applications listed |
| 2 | Filter by status "Pending" | Only pending apps shown |
| 3 | Search by student name | Matching results |
| 4 | Click "Review" on an application | Detail view loads — shows student info, skills, documents |
| 5 | Change status to "Under Review" | Status updates, notification sent to student |
| 6 | Add review notes | Notes appear in student's dashboard |
| 7 | Change status to "Accepted" | Student notified with "congratulations" message |

### Test 3.3 — Job Post Management

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Go to Jobs | Existing job posts listed |
| 2 | Click "Create Job Post" | Create form appears |
| 3 | Submit empty form | "Job title is required" |
| 4 | Fill and submit | "Job post created!" — appears in list |
| 5 | Click "Hide" on a job | Job status changes to "HIDDEN" — removed from student view |
| 6 | Click "Show" | Job visible again |
| 7 | Click "Edit" | Pre-filled form, can update fields |
| 8 | Click "Del" → Confirm | Job deleted |

### Test 3.4 — Student Management

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Go to Students | All students listed |
| 2 | Search by name/email/student number | Filters correctly |
| 3 | Filter by application status | Only matching students shown |
| 4 | Click "Disable" on a student | Status changes to "DISABLED" — student cannot log in |
| 5 | Click "Enable" | Status changes back to "ACTIVE" |

### Test 3.5 — Organisation Management

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Go to Organisations | All orgs listed with capacity, matched students, jobs |
| 2 | Click "View" on an org | Detail view — shows org info, job posts, matched students |
| 3 | Click "Disable" | Org status toggled |
| 4 | Search by name/location | Filters correctly |

### Test 3.6 — Matching Algorithm

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Ensure there are unmatched applications and active orgs with capacity | — |
| 2 | Go to Matching | Page loads with tabs |
| 3 | Click "Run Auto-Matching" | Processing occurs, suggestions generated |
| 4 | Check Suggestions tab | Each suggestion shows student → org with match score % |
| 5 | Click "Confirm" on a suggestion | Match confirmed, application status → "matched", student notified |
| 6 | Click "Decline" on another | Suggestion removed |
| 7 | Go to "Unmatched" tab | Declined student appears back in unmatched list |
| 8 | Go to "Manual Match" tab | Select any student + any org → click "Confirm Manual Match" |
| 9 | Verify manual match | Match recorded with 100% score, status "confirmed" |

### Test 3.7 — Internal User Management (Admin Only)

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Log in as **Admin** | "Users" link visible in nav |
| 2 | Go to Users page | All internal users listed |
| 3 | Fill create form (new coordinator details) | "Coordinator account created" |
| 4 | Click "Edit" on the new user | Pre-filled form — change role to "Admin" |
| 5 | Update | "Account updated" |
| 6 | Click "Disable" → "Enable" | Status toggles correctly |
| 7 | Try to delete yourself | Should NOT allow self-delete |
| 8 | Delete another user | Account removed |
| 9 | Log in as **Coordinator** | "Users" link NOT visible in nav |
| 10 | Manually navigate to `/admin/users.php` | Access denied or read-only warning |

### Test 3.7b — Admin Registration Page (NEW)

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Without logging in, visit `/admin/register.php` | "Access Denied" — links to login or home |
| 2 | Login as Coordinator, visit `/admin/register.php` | "Access Denied" — coordinators cannot create users |
| 3 | Login as Admin, visit `/admin/register.php` | Registration form loads |
| 4 | Submit empty form | Validation errors for required fields |
| 5 | Enter weak password | Password strength error messages |
| 6 | Enter existing email (e.g., `admin@ub.ac.bw`) | "Email already registered" |
| 7 | Fill correctly: Name, new email, role=Admin, strong password | "Admin account created!" success message |
| 8 | Click "Go to Login" → login with new credentials | Dashboard loads with full admin access |
| 9 | Login as new admin → visit `/admin/users.php` | Can manage other users (confirming admin role) |
| 10 | Create a Coordinator account | "Coordinator account created!" — verify coordinator cannot access `/admin/register.php` |

---

## Phase 4: Logbooks & Assessments

### Test 4.1 — Student Logbook Submission

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Log in as Student A (must have matched application) | Dashboard loads |
| 2 | Go to Logbook tab or `/logbook.php` | Logbook page loads |
| 3 | If no placement: "You need a confirmed placement" message | — |
| 4 | Fill Week 1 logbook (activities, learning outcomes, challenges) | Form accepts input |
| 5 | Click "Save Draft" | "Draft saved" — logbook appears in history with "DRAFT" status |
| 6 | Click "Edit" on the draft | Pre-filled form — can modify |
| 7 | Click "Submit Logbook" | Status changes to "SUBMITTED" |
| 8 | Submit another logbook for same week | "You already have a logbook entry for Week 1" |
| 9 | Submit Week 2 logbook (past deadline) | Status shows "LATE" |

### Test 4.2 — Coordinator Logbook Review

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Log in as Admin/Coordinator | — |
| 2 | Go to Logbooks | All submitted logbooks listed |
| 3 | Filter by "Submitted" | Only submitted logbooks shown |
| 4 | Click "Review" on a logbook | Detail view — shows activities, learning outcomes, challenges |
| 5 | Add supervisor comment | Textarea accepts input |
| 6 | Change status to "Reviewed" | "Logbook reviewed and feedback saved" |
| 7 | Log in as the student | Notification received: "Your Week X logbook has been reviewed" |

### Test 4.3 — Student Final Report

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Log in as Student A | — |
| 2 | Go to Report tab or `/student_report.php` | Report form loads |
| 3 | Fill title, executive summary, body, conclusion | Form accepts input |
| 4 | Click "Save Draft" | "Draft saved" |
| 5 | Click "Submit Final Report" (with body empty) | "Report body cannot be empty when submitting" |
| 6 | Fill body and submit | "Final report submitted successfully!" |
| 7 | Check notifications | Coordinators notified |
| 8 | Login as Admin → Reports → Final Reports tab | Report appears with status "SUBMITTED" |
| 9 | Click "Grade" button | Grade dialog appears |
| 10 | Enter grade (e.g., "A") and feedback | "Report graded and feedback saved" |
| 11 | Login as student → check Report tab | Grade and feedback visible |

### Test 4.4 — Supervisor Report (Organisation)

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Log in as Organisation | Dashboard loads with matched students |
| 2 | Go to Supervisor Report page | Student list appears |
| 3 | Click "Write Report" on a student | Rating form loads |
| 4 | Rate all 5 categories (1-5) | Radio buttons selectable |
| 5 | Add comments & recommendation | — |
| 6 | Click "Save Draft" | "Draft saved" |
| 7 | Click "Submit Report" | "Performance report submitted!" |
| 8 | Login as Admin → Reports → Supervisor Reports tab | Report visible with all ratings |
| 9 | Login as the student | Notification: "Your industrial supervisor has submitted your end-of-attachment performance report" |

### Test 4.5 — Site Visit Assessments

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Log in as Admin/Coordinator | — |
| 2 | Go to Assessments | Placed students listed with visit status |
| 3 | Click a student | Assessment panel loads |
| 4 | Record Visit 1 (date, scores, comments) | "Visit 1 assessment recorded!" |
| 5 | Check student notifications | Student notified |
| 6 | Record Visit 2 | Both visits recorded |
| 7 | Try to record Visit 3 | Should not allow (max 2 visits) |
| 8 | Check Admin Reports → Overview | Site Assessments count incremented |

---

## Phase 5: Reports & Reminders

### Test 5.1 — Admin Reports & Analytics

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Log in as Admin → Reports | Overview tab loads with stats |
| 2 | Check "Placement by Programme" | Table with bars showing placement rates |
| 3 | Check "Capacity by Organisation" | Available vs placed counts |
| 4 | Click "Student Ranking" tab | Students ranked by GPA + visit scores |
| 5 | Click "Supervisor Reports" tab | All submitted reports listed |
| 6 | Click "Final Reports" tab | All student reports listed with grades |
| 7 | Click "Export Placements CSV" | CSV file downloads with correct headers |
| 8 | Click "Export Applications CSV" | CSV file downloads |
| 9 | Click "Export Students CSV" | CSV file downloads |

### Test 5.2 — Bulk Reminders

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Log in as Admin/Coordinator → Reminders | Page loads with stats and forms |
| 2 | Enter week number and click "Send Logbook Reminders" | Reminders sent to relevant students |
| 3 | Check recipient list displayed | Names/emails shown |
| 4 | Check student notifications | Deadline notification received |
| 5 | Click "Send Document Reminders" | Reminders to students with pending apps and no documents |
| 6 | Click "Send Report Reminders" | Reminders to orgs with pending supervisor reports |
| 7 | Fill custom notification form | Title + message |
| 8 | Select "All Students" as target | — |
| 9 | Check "Also send as email" | — |
| 10 | Click "Send Notification" | "Notification sent to X user(s)" — all target students receive in-app notification |

---

## Edge Cases & Error Handling

### Test E1 — Invalid URL Access

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Without logging in, visit `/admin/index.php` | Redirected to `/login.php` |
| 2 | Without logging in, visit `/dashboard.php` | Redirected to `/login.php` |
| 3 | Log in as Student, visit `/admin/index.php` | Redirected to `/dashboard.php` |
| 4 | Log in as Student, visit `/org/dashboard.php` | Redirected to `/dashboard.php` |
| 5 | Log in as Organisation, visit `/admin/index.php` | Redirected to `/org/dashboard.php` |
| 6 | Log in as Organisation, visit `/dashboard.php` | Redirected to `/org/dashboard.php` |

### Test E2 — CSRF Protection

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Open a form (login, register, etc.) | Hidden `_csrf` field present in HTML |
| 2 | Submit form normally | Works |
| 3 | Remove `_csrf` field (via browser dev tools) and submit | "CSRF validation failed" error |

### Test E3 — File Upload Security

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Upload a `.php` file as a document | "Invalid file type" |
| 2 | Upload a `.exe` file as a document | "Invalid file type" |
| 3 | Try accessing `/uploads/` directly in browser | Blocked by `.htaccess` or 403 error |
| 4 | As a student, try downloading another student's document (manipulate `?id=` parameter) | "Document not found or access denied" |

### Test E4 — XSS Prevention

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Register with `<script>alert('xss')</script>` in full name field | The script tag is displayed as text, not executed |
| 2 | Submit application with HTML in skills field | HTML is escaped |

### Test E5 — SQL Injection Prevention

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | In login form, enter `' OR 1=1 --` as email | "Invalid email or password" (no bypass) |
| 2 | In search fields, enter `'; DROP TABLE users; --` | No damage — query is parameterized |

---

## How to Reset Test Data

### Option 1: phpMyAdmin (Selective)

1. Go to phpMyAdmin → your database
2. Click **SQL** tab
3. Run:
```sql
DELETE FROM applications WHERE user_id NOT IN (SELECT user_id FROM users WHERE role IN ('admin','coordinator'));
DELETE FROM documents;
DELETE FROM job_interests;
DELETE FROM logbooks;
DELETE FROM matches;
DELETE FROM student_reports;
DELETE FROM supervisor_reports;
DELETE FROM site_visit_assessments;
DELETE FROM notifications;
DELETE FROM wishlist;
DELETE FROM users WHERE role NOT IN ('admin','coordinator');
TRUNCATE TABLE login_attempts;
TRUNCATE TABLE password_resets;
```
This keeps only admin and coordinator accounts, plus sample job posts.

### Option 2: Drop and Re-import (Complete Reset)

1. Drop all tables
2. Re-import `database_schema.sql`
3. Re-run `setup.php` to create admin/coordinator
4. **Delete `setup.php`**

---

## Test Checklist (Quick Reference)

| # | Test | ✓ |
|---|------|---|
| 1.1 | Student registration | ☐ |
| 1.2 | Organisation registration | ☐ |
| 1.3 | Login & brute-force | ☐ |
| 1.4 | Session timeout | ☐ |
| 1.5 | Password reset | ☐ |
| 1.6 | Logout & no back-button | ☐ |
| 2.1 | Student dashboard | ☐ |
| 2.2 | Profile update | ☐ |
| 2.3 | Application submission | ☐ |
| 2.4 | Document upload | ☐ |
| 2.5 | Job browsing & interest | ☐ |
| 3.1 | Admin dashboard | ☐ |
| 3.2 | Application review | ☐ |
| 3.3 | Job post management | ☐ |
| 3.4 | Student management | ☐ |
| 3.5 | Organisation management | ☐ |
| 3.6 | Matching algorithm | ☐ |
| 3.7 | User management (admin only) | ☐ |
| 3.7b | Admin registration page | ☐ |
| 4.1 | Student logbook | ☐ |
| 4.2 | Coordinator logbook review | ☐ |
| 4.3 | Student final report | ☐ |
| 4.4 | Supervisor report | ☐ |
| 4.5 | Site visit assessments | ☐ |
| 5.1 | Reports & analytics | ☐ |
| 5.2 | Bulk reminders | ☐ |
| E1 | Invalid URL access | ☐ |
| E2 | CSRF protection | ☐ |
| E3 | File upload security | ☐ |
| E4 | XSS prevention | ☐ |
| E5 | SQL injection prevention | ☐ |

---

*Last updated: May 2026*
```

---

All three documents are complete:

| Document | Purpose |
|----------|---------|
| **README.md** | Client/supervisor overview — features, installation, demo accounts |
| **DEVELOPER_GUIDE.md** | Technical reference — architecture, decisions, gotchas, common tasks |
| **TEST_GUIDE.md** | Step-by-step test procedures — every feature, edge cases, reset instructions |

