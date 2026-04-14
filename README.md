# IAMS — Deployment Guide
## University of Botswana · Internship & Attachment Management System

---

## ⚠️ Why Not Netlify?

Netlify only hosts **static websites** (HTML, CSS, JavaScript). IAMS uses **PHP and MySQL**, which require a proper web hosting server. The best **free** options for PHP+MySQL are listed below.

---

## 🌐 Recommended Free Hosting: InfinityFree

**URL:** https://infinityfree.com  
**Why:** Free PHP 8+, MySQL, cPanel, 5GB storage, no ads injected into your site.

---

## 📋 Step-by-Step Deployment

### Step 1 — Create a Free Account

1. Go to **https://infinityfree.com** and click **Sign Up**
2. Verify your email
3. Click **Create Account** → choose a free subdomain like `iams-ub.rf.gd` or use your own domain

---

### Step 2 — Create a MySQL Database

1. In your InfinityFree control panel, go to **MySQL Databases**
2. Create a new database — note the database name (e.g. `epiz_12345678_iams`)
3. Create a database user and set a strong password
4. Note down:
   - **Host:** `sql305.infinityfree.com` (shown in your panel)
   - **Database name:** e.g. `epiz_12345678_iams`
   - **Username:** e.g. `epiz_12345678`
   - **Password:** the password you set

---

### Step 3 — Import the Database Schema

1. In cPanel, open **phpMyAdmin**
2. Select your new database on the left
3. Click the **Import** tab
4. Upload the file: `database_schema.sql`
5. Click **Go** — all tables will be created with sample data

---

### Step 4 — Update Database Credentials

Open `config/database.php` and update these lines:

```php
private $host     = "sql305.infinityfree.com";  // from your panel
private $db_name  = "epiz_12345678_iams";        // your DB name
private $username = "epiz_12345678";             // your DB user
private $password = "your_strong_password";      // your password
```

---

### Step 5 — Upload Files

1. In cPanel, open **File Manager**
2. Navigate to `htdocs/` (this is your website root)
3. Click **Upload** and upload all files from the `iams/` folder
4. Make sure the folder structure looks like this in `htdocs/`:

```
htdocs/
├── index.php
├── login.php
├── register.php
├── dashboard.php
├── logout.php
├── forgot_password.php
├── download.php
├── submit_application.php
├── update_profile.php
├── upload_document.php
├── .htaccess
├── database_schema.sql
├── config/
│   ├── database.php
│   └── session.php
└── uploads/
    └── .htaccess
```

**Tip:** You can also upload the ZIP file and extract it in File Manager.

---

### Step 6 — Test Your Site

Visit your subdomain: `https://iams-ub.rf.gd` (or whatever you chose)

Test these pages:
- ✅ `index.php` — Landing page with job listings
- ✅ `register.php` — Student registration
- ✅ `login.php` — Login (use admin@ub.ac.bw / password)
- ✅ `dashboard.php` — Student dashboard (requires login)

---

## 🔐 Default Admin Login

After importing the database:
- **Email:** `admin@ub.ac.bw`
- **Password:** `password`

⚠️ **Change this immediately** after first login!

---

## 🔒 Security Checklist

- [ ] Change the default admin password
- [ ] Enable HTTPS in `.htaccess` (uncomment the HTTPS redirect lines)
- [ ] Make sure `config/` folder is not publicly accessible
- [ ] Set a strong MySQL password

---

## 🆓 Other Free Hosting Alternatives

| Provider | URL | Notes |
|---|---|---|
| **InfinityFree** | infinityfree.com | Best free option, no ads |
| **000WebHost** | 000webhost.com | By Hostinger, reliable |
| **AwardSpace** | awardspace.com | 1GB free, MySQL included |
| **Byethost** | byethost.com | Unlimited subdomains |

---

## 💡 For Production / Real Launch

When ready to launch properly, consider paid hosting:

- **Hostinger** — ~$3/month, excellent PHP/MySQL support
- **Namecheap Shared** — ~$2/month, good for Botswana domains
- **Get a `.co.bw` domain** from BOCRA: https://www.bocra.org.bw

---

## 📁 File Structure Reference

```
iams/
├── config/
│   ├── database.php     ← DB credentials (update before upload)
│   └── session.php      ← Session helpers
├── uploads/             ← Student-uploaded documents stored here
│   └── .htaccess        ← Prevents uploaded files from executing
├── index.php            ← Public landing page
├── login.php            ← Login page
├── register.php         ← Student registration
├── dashboard.php        ← Student dashboard (protected)
├── logout.php           ← Ends session
├── forgot_password.php  ← Password reset info
├── download.php         ← Secure document download
├── submit_application.php ← Handles application form POST
├── update_profile.php   ← Handles profile update POST
├── upload_document.php  ← Handles document upload POST
├── .htaccess            ← Security rules
└── database_schema.sql  ← Run this in phpMyAdmin once
```

---

*IAMS — "We give the pathway to future leaders"*  
*University of Botswana · Ministry of Labour and Home Affairs*
"  ## ?? Database Setup 1. Copy \`config/database.example.php\` to \`config/database.php\` 2. Edit with your MySQL credentials 3. Import \`database_schema.sql\` to create tables " 
