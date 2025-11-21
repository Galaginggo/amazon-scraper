# Amazon Price Tracker

A comprehensive PHP-based web application that automatically tracks Amazon product prices, converts currencies to Philippine Pesos, monitors price changes over time, and displays detailed price history with percentage indicators.

## ✨ Features

✅ **Instant Product Scraping** - Add products and see details immediately (no manual commands needed)  
✅ **Automatic Currency Conversion** - USD to PHP conversion with configurable exchange rate  
✅ **Price Change Tracking** - Visual indicators showing price increases/decreases with percentages  
✅ **Detailed Price History** - View complete price history with statistics per product  
✅ **Product Images** - Automatic extraction and display of product thumbnails  
✅ **Automated Updates** - Schedule automatic price checks at your preferred interval  
✅ **Philippine Time** - All timestamps in Asia/Manila timezone (UTC+8)  
✅ **Web Dashboard** - Clean, modern, easy-to-use interface  

## 🚀 Quick Start

### 1. Install Dependencies
```bash
composer install
```

### 2. Open the Dashboard
Open `index.php` in your browser:
```
http://localhost/amazon-scraper/index.php
```

### 3. Add Your First Product
1. Copy any Amazon product URL
2. Paste it in the "Add New Product to Track" form
3. Click **"Add Product"**
4. Product details (title, price, image) appear instantly! ✨

## ⚙️ Automatic Updates Setup

### Option 1: One-Click Setup (Recommended for Windows)

1. **Right-click** `setup_scheduler.bat`
2. Select **"Run as Administrator"**
3. Done! Updates will run automatically every 5 minutes ✅

### Option 2: Manual Setup via Task Scheduler

1. Open **Task Scheduler** (search in Windows Start menu)
2. Click **"Create Basic Task"**
3. Name: `AmazonPriceTracker`
4. Trigger: **Daily**, check "Repeat task every" → **5 minutes**
5. Action: **Start a program**
   - Program/script: `C:\xampp\php\php.exe`
   - Add arguments: `C:\xampp\htdocs\amazon-scraper\auto_update.php`
   - Start in: `C:\xampp\htdocs\amazon-scraper`
6. Click **Finish**

### Configure Update Settings

1. Open `index.php` in your browser
2. Scroll to **"⚙️ Automatic Price Updates"** section
3. Configure:
   - ✅ Check **"Enable Automatic Updates"**
   - ⏱️ Set **Update Interval** (e.g., 60 minutes = hourly updates)
   - 💱 Set **Exchange Rate** (USD to PHP, default: 59.0)
4. Click **"Save Settings"**

## 📊 How It Works

### Automatic Update Flow

```
Windows Task Scheduler (runs every 5 minutes)
    ↓
Calls: auto_update.php
    ↓
Checks: Is auto-update enabled? Has interval passed?
    ↓
If YES: Runs amazon_scraper.php with your exchange rate
    ↓
Scrapes: Product title, price, image from Amazon
    ↓
Converts: USD prices to PHP automatically
    ↓
Saves: Updates price_history.csv with Philippine time
    ↓
Dashboard: Shows updated prices with change indicators
```

**Smart Scheduling:** The task runs every 5 minutes but only updates when YOUR configured interval has passed.

**Example:**
- You set interval to **60 minutes**
- Task checks at: 1:00, 1:05, 1:10, 1:15... (every 5 min)
- But updates only at: 1:00, 2:00, 3:00... (every 60 min)

This gives you flexibility to change update frequency without modifying Task Scheduler!

## 🎯 Key Features Explained

### 1. Instant Product Addition
When you add a product URL:
- ✅ Validates the URL
- ✅ Immediately scrapes product data
- ✅ Extracts title, price, and image
- ✅ Converts USD to PHP
- ✅ Saves to database
- ✅ Displays on dashboard instantly

### 2. Price Change Indicators
- 🟢 **Green ↓ -12.3%** - Price decreased (good deal!)
- 🔴 **Red ↑ +5.8%** - Price increased
- **No badge** - First check or no change

### 3. Price History Page
Click **"📊 History"** button to see:
- **Statistics Dashboard:**
  - Current Price
  - Lowest Price (all-time best deal)
  - Highest Price (all-time peak)
  - Average Price
  - Total number of checks
- **Complete History Table:**
  - All price checks with timestamps
  - Price changes with percentages
  - Color-coded indicators

### 4. Currency Conversion
- Automatically detects USD ($) or PHP prices
- Converts USD to PHP using your exchange rate
- Displays all prices in PHP format
- Updates exchange rate anytime in settings

### 5. Philippine Time
- All timestamps in Asia/Manila timezone (UTC+8)
- Matches your local time perfectly
- No timezone confusion!

## 🔧 Manual Operations

### Update Prices Manually

**Via Web Interface:**
Click **"▶️ Run Update Now"** button in the dashboard

**Via Command Line:**
```bash
# Update all products
php amazon_scraper.php --track-daily

# Update with custom exchange rate
php amazon_scraper.php --track-daily --rate=58.5

# Test single product
php amazon_scraper.php "https://www.amazon.com/product-url"
```

## 📁 Project Structure

```
amazon-scraper/
├── index.php              # Main dashboard (add/view products)
├── history.php            # Detailed price history per product
├── amazon_scraper.php     # Core scraping engine
├── auto_update.php        # Automated update handler
├── setup_scheduler.bat    # One-click Windows setup
├── README.md              # This file
├── composer.json          # PHP dependencies
├── products.txt           # List of tracked URLs
├── price_history.csv      # Historical price data
└── update_config.json     # Auto-update settings
```

## 🐛 Troubleshooting

### Automatic Updates Not Working

**Check Task Scheduler:**
1. Open Task Scheduler (`taskschd.msc`)
2. Look for "AmazonPriceTracker" task
3. Right-click → Run to test manually
4. Check "Last Run Result" (should be 0x0 for success)

**Check Web Settings:**
1. Open `index.php`
2. Verify "Enable Automatic Updates" is checked
3. Check "Last update" timestamp
4. Try "Run Update Now" button

**Test Manually:**
```bash
php auto_update.php
```

### Products Not Showing Details

**Immediate Fix:**
1. Click **"▶️ Run Update Now"** in dashboard
2. Wait for success message
3. Page will auto-refresh

**Manual Fix:**
```bash
php amazon_scraper.php --track-daily
```

**Debug:**
- Check `debug.html` for last scraped page
- Verify Amazon didn't block the request
- Try different product URL

### Wrong Currency or Exchange Rate

1. Go to **"⚙️ Automatic Price Updates"** section
2. Update **"Exchange Rate (USD to PHP)"** field
3. Click **"Save Settings"**
4. Click **"Run Update Now"** to apply new rate

### Timezone Issues

All timestamps should now be in Philippine Time (UTC+8). If you see wrong times:
1. Run a new update to generate fresh timestamps
2. Old data will keep original timezone
3. New data will use Philippine time

### Permission Errors

**Task Scheduler:**
- Run `setup_scheduler.bat` as Administrator
- Right-click → "Run as Administrator"

**File Permissions:**
- Ensure PHP can write to project directory
- Check `price_history.csv` is writable

## 💡 Tips & Best Practices

### Optimal Update Intervals
- **Hourly (60 min)** - Good for frequently changing prices
- **Every 4 hours (240 min)** - Balanced approach
- **Daily (1440 min)** - For stable products

### Exchange Rate Updates
- Check current USD to PHP rate: [Google Finance](https://www.google.com/finance/quote/USD-PHP)
- Update in settings when rate changes significantly
- Run update to apply new rate to future checks

### Managing Products
- Remove products you're no longer tracking
- History data is preserved in CSV
- Can re-add products anytime

### Viewing History
- Click "📊 History" to see trends
- Look for lowest price to know best deal
- Check if current price is above/below average

## 🔍 Understanding the Data

### Price History CSV Format
```
timestamp,title,price,raw_price,image_url,url
2025-11-21 13:30:00,Product Name,10865.20,PHP10865.20,https://image.jpg,https://amazon.com/...
```

### Update Config JSON Format
```json
{
  "enabled": true,
  "interval_minutes": 60,
  "exchange_rate": 59.0,
  "last_run": "2025-11-21 13:30:00"
}
```

## ❓ FAQ

**Q: What is cron?**  
A: Cron is a Linux/Unix job scheduler. Since you're on Windows, we use **Windows Task Scheduler** instead - it does the same thing.

**Q: Why does the task run every 5 minutes if my interval is 60 minutes?**  
A: The task checks every 5 minutes, but only updates when your interval has passed. This allows flexible interval changes without modifying Task Scheduler.

**Q: Can I track products from different Amazon regions?**  
A: Yes, but currency conversion assumes USD. Adjust exchange rate accordingly.

**Q: How many products can I track?**  
A: No hard limit, but more products = longer update times. Recommended: 10-50 products.

**Q: Will Amazon block my scraper?**  
A: The scraper uses browser-like headers to minimize detection. Don't set very short intervals (< 30 min).

**Q: Can I export price history?**  
A: Yes! `price_history.csv` can be opened in Excel or Google Sheets.

## 🛠️ System Requirements

- **PHP**: 7.4 or higher
- **Composer**: For dependency management
- **Web Server**: XAMPP, WAMP, or similar
- **Operating System**: Windows (for Task Scheduler setup)
- **Internet**: Required for scraping Amazon

## 📦 Dependencies

- **guzzlehttp/guzzle** (^7.10) - HTTP client for web requests
- **symfony/dom-crawler** (^7.3) - HTML parsing
- **symfony/css-selector** (^7.3) - CSS selector support

## 🤝 Support

If you encounter issues:

1. **Check debug.html** - Last scraped page content
2. **Check Task Scheduler logs** - Task execution history
3. **Check PHP error logs** - In XAMPP control panel
4. **Test manually** - Run commands to isolate issue

## 📝 Version History

**Current Version: 2.0**
- ✅ Instant product scraping on add
- ✅ Automatic currency conversion (USD to PHP)
- ✅ Price change tracking with percentages
- ✅ Detailed price history page with statistics
- ✅ Product image extraction and display
- ✅ Automated updates with web configuration
- ✅ Philippine timezone support (UTC+8)
- ✅ One-click Windows setup script
- ✅ Modern, responsive web interface

## 📄 License

This project is for personal use. Please respect Amazon's Terms of Service and robots.txt when scraping.

---

**Made with ❤️ for tracking Amazon deals in the Philippines** 🇵🇭#   a m a z o n - s c r a p e r  
 #   a m a z o n - s c r a p e r  
 #   a m a z o n - s c r a p e r  
 #   a m a z o n - s c r a p e r  
 #   a m a z o n - s c r a p e r  
 #   a m a z o n - s c r a p e r  
 