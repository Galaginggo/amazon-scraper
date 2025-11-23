# Amazon Price Tracker - Complete Setup Guide

## 🎯 Overview
This guide will walk you through setting up the complete multi-user Amazon Price Tracker system with MySQL database.

---

## 📋 Prerequisites

### Required Software
- **PHP 7.4+** (with PDO MySQL extension)
- **MySQL 5.7+** or **MariaDB 10.2+**
- **Composer** (for dependencies)
- **Web Server** (Apache/Nginx) or PHP built-in server

### PHP Extensions Required
- `pdo_mysql`
- `mbstring`
- `curl`
- `json`

Check your PHP extensions:
```bash
php -m
```

---

## 🚀 Installation Steps

### Step 1: Install Dependencies

```bash
cd c:/xampp/htdocs/amazon-scraper
composer install
```

This will install:
- GuzzleHTTP (for web scraping)
- Symfony DomCrawler (for HTML parsing)

---

### Step 2: Create Database

#### Option A: Using MySQL Command Line
```bash
mysql -u root -p < migrations/001_initial_schema.sql
```

#### Option B: Using phpMyAdmin
1. Open phpMyAdmin (http://localhost/phpmyadmin)
2. Click "Import" tab
3. Choose file: `migrations/001_initial_schema.sql`
4. Click "Go"

#### Option C: Manual Creation
```sql
-- Run these commands in MySQL
CREATE DATABASE amazon_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE amazon_tracker;

-- Then copy and paste the contents of migrations/001_initial_schema.sql
```

---

### Step 3: Configure Database Connection

Edit `config/database.php` if needed:

```php
private $host = 'localhost';
private $dbname = 'amazon_tracker';
private $username = 'root';
private $password = '';  // Change if you have a password
```

---

### Step 4: Test Database Connection

Create a test file `test_db.php`:

```php
<?php
require_once 'config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    echo "✓ Database connection successful!\n";
    
    // Test query
    $stmt = $db->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    echo "✓ Users table accessible (count: {$result['count']})\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
```

Run it:
```bash
php test_db.php
```

---

### Step 5: Set Up Web Server

#### Option A: PHP Built-in Server (Development)
```bash
cd public
php -S localhost:8000
```

Access at: http://localhost:8000

#### Option B: Apache (XAMPP)
1. Place project in `c:/xampp/htdocs/amazon-scraper`
2. Access at: http://localhost/amazon-scraper/public/

#### Option C: Configure Virtual Host (Recommended)

**Apache (httpd-vhosts.conf):**
```apache
<VirtualHost *:80>
    ServerName amazon-tracker.local
    DocumentRoot "c:/xampp/htdocs/amazon-scraper/public"
    
    <Directory "c:/xampp/htdocs/amazon-scraper/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

**Add to hosts file (C:\Windows\System32\drivers\etc\hosts):**
```
127.0.0.1 amazon-tracker.local
```

Access at: http://amazon-tracker.local

---

## 👤 Creating Your First User

### Option 1: Via Registration Page
1. Go to http://localhost:8000/register.php
2. Fill in the form:
   - Username (min 3 characters)
   - Email
   - Password (min 8 characters)
3. Click "Create Account"
4. Login with your credentials

### Option 2: Via Database (Manual)
```sql
-- Generate password hash in PHP
-- php -r "echo password_hash('your_password', PASSWORD_ARGON2ID);"

INSERT INTO users (username, email, password_hash) VALUES 
('admin', 'admin@example.com', 'YOUR_HASHED_PASSWORD_HERE');
```

---

## 🧪 Testing the System

### 1. Test User Registration & Login
- [ ] Register a new user
- [ ] Login with credentials
- [ ] Verify redirect to dashboard
- [ ] Check session is maintained

### 2. Test Product Tracking
- [ ] Add an Amazon product URL
- [ ] Verify product is scraped immediately
- [ ] Check product appears in dashboard
- [ ] View price history

### 3. Test User Isolation
- [ ] Create second user account
- [ ] Add products to both accounts
- [ ] Verify User A cannot see User B's products
- [ ] Check database for proper user_id associations

### 4. Test Settings
- [ ] Go to Settings page
- [ ] Update exchange rate
- [ ] Enable auto-update
- [ ] Change update interval
- [ ] Update email
- [ ] Change password

### 5. Test Auto-Update (CLI)
```bash
php auto_update.php
```

Expected output:
```
=== Amazon Price Tracker - Auto Update ===
Started at: 2025-11-23 14:00:00

Updating products for user: your_username
  ✓ Updated 1/1 products

=== Update Summary ===
Total users with auto-update: 1
Users updated: 1
Total products checked: 1
Successful: 1
Failed: 0

Completed at: 2025-11-23 14:00:15
```

---

## ⚙️ Setting Up Scheduled Updates

### Windows Task Scheduler

1. **Open Task Scheduler**
   - Press `Win + R`
   - Type `taskschd.msc`
   - Press Enter

2. **Create Basic Task**
   - Click "Create Basic Task"
   - Name: "Amazon Price Tracker Auto Update"
   - Description: "Automatically updates product prices"

3. **Set Trigger**
   - Trigger: Daily
   - Start: Today
   - Recur every: 1 day
   - Check "Repeat task every: 30 minutes"
   - Duration: Indefinitely

4. **Set Action**
   - Action: Start a program
   - Program/script: `C:\xampp\php\php.exe`
   - Arguments: `C:\xampp\htdocs\amazon-scraper\auto_update.php`
   - Start in: `C:\xampp\htdocs\amazon-scraper`

5. **Finish**
   - Check "Open Properties dialog"
   - Under "General" tab, select "Run whether user is logged on or not"
   - Click OK

### Linux Cron Job

Edit crontab:
```bash
crontab -e
```

Add this line:
```bash
*/30 * * * * cd /var/www/amazon-scraper && php auto_update.php >> /var/log/amazon-tracker.log 2>&1
```

---

## 📁 File Structure

```
amazon-scraper/
├── config/
│   └── database.php              # Database connection
├── migrations/
│   └── 001_initial_schema.sql    # Database schema
├── public/                        # Web root
│   ├── index.php                 # Dashboard
│   ├── login.php                 # Login page
│   ├── register.php              # Registration
│   ├── logout.php                # Logout handler
│   ├── history.php               # Price history
│   └── settings.php              # User settings
├── src/
│   ├── Models/
│   │   ├── User.php              # User model
│   │   ├── Product.php           # Product model
│   │   ├── PriceHistory.php      # Price history model
│   │   └── UserSettings.php      # Settings model
│   └── Services/
│       └── PriceTracker.php      # Price tracking service
├── legacy/                        # Old CSV files (backup)
│   ├── price_history.csv
│   └── products.txt
├── amazon_scraper.php             # Scraper class
├── auto_update.php                # Auto-update script
├── composer.json                  # Dependencies
└── README.md                      # Documentation
```

---

## 🔧 Configuration Options

### Database Settings
File: `config/database.php`
```php
private $host = 'localhost';      // Database host
private $dbname = 'amazon_tracker'; // Database name
private $username = 'root';        // Database user
private $password = '';            // Database password
```

### Default User Settings
File: `src/Models/UserSettings.php`
```php
'auto_update_enabled' => false,
'update_interval_minutes' => 60,
'exchange_rate' => 59.00,
'timezone' => 'Asia/Manila',
'email_notifications' => false
```

---

## 🐛 Troubleshooting

### Database Connection Failed
**Error:** "Database connection failed"

**Solutions:**
1. Check MySQL is running: `mysql -u root -p`
2. Verify credentials in `config/database.php`
3. Ensure database exists: `SHOW DATABASES;`
4. Check PHP PDO extension: `php -m | grep pdo`

### Cannot Register User
**Error:** "Failed to create user"

**Solutions:**
1. Check database tables exist
2. Verify unique username/email
3. Check error logs: `tail -f /path/to/error.log`

### Products Not Scraping
**Error:** "Failed to scrape product"

**Solutions:**
1. Check internet connection
2. Verify Amazon URL is valid
3. Check Guzzle is installed: `composer show guzzlehttp/guzzle`
4. Review `debug.html` for response

### Auto-Update Not Running
**Solutions:**
1. Test manually: `php auto_update.php`
2. Check user has auto-update enabled
3. Verify Task Scheduler/Cron is configured
4. Check file permissions
5. Review error logs

### Session Issues
**Error:** "Not authenticated"

**Solutions:**
1. Check session is started: `session_start()`
2. Verify cookies are enabled
3. Check session directory permissions
4. Clear browser cookies

---

## 📊 Database Maintenance

### Backup Database
```bash
mysqldump -u root -p amazon_tracker > backup_$(date +%Y%m%d).sql
```

### Restore Database
```bash
mysql -u root -p amazon_tracker < backup_20251123.sql
```

### Clean Old Price History
```sql
-- Delete entries older than 90 days
DELETE FROM price_history 
WHERE checked_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

### View Statistics
```sql
-- User count
SELECT COUNT(*) as total_users FROM users WHERE is_active = 1;

-- Product count per user
SELECT u.username, COUNT(p.id) as product_count
FROM users u
LEFT JOIN products p ON u.id = p.user_id AND p.is_active = 1
GROUP BY u.id;

-- Total price checks
SELECT COUNT(*) as total_checks FROM price_history;
```

---

## 🔒 Security Recommendations

1. **Change Default Credentials**
   - Update database password
   - Use strong passwords for users

2. **Enable HTTPS**
   - Use SSL certificate
   - Force HTTPS in production

3. **Secure File Permissions**
   ```bash
   chmod 644 config/database.php
   chmod 755 public/
   ```

4. **Regular Updates**
   - Keep PHP updated
   - Update Composer dependencies
   - Apply security patches

5. **Backup Regularly**
   - Daily database backups
   - Store backups securely
   - Test restore procedures

---

## 📚 Additional Resources

- [MIGRATION_PLAN.md](MIGRATION_PLAN.md) - Complete migration strategy
- [PHASE3_IMPLEMENTATION.md](PHASE3_IMPLEMENTATION.md) - Phase 3 details
- [AUTO_UPDATE_DATABASE.md](AUTO_UPDATE_DATABASE.md) - Auto-update docs

---

## ✅ Setup Checklist

- [ ] PHP 7.4+ installed
- [ ] MySQL installed and running
- [ ] Composer dependencies installed
- [ ] Database created and schema loaded
- [ ] Database connection configured
- [ ] Web server configured
- [ ] First user account created
- [ ] Test product added and scraped
- [ ] Settings page accessible
- [ ] Auto-update tested manually
- [ ] Scheduled task configured
- [ ] Backup strategy in place

---

## 🎉 You're All Set!

Your Amazon Price Tracker is now fully configured and ready to use!

**Next Steps:**
1. Register your account
2. Add your first products
3. Configure your settings
4. Set up scheduled updates
5. Monitor your products!

**Need Help?**
- Check the troubleshooting section
- Review error logs
- Consult the documentation files

Happy tracking! 🛒📊
