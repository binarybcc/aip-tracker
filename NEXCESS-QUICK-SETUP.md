# 🚀 AIP Tracker - Nexcess Quick Setup

## 5-Minute Deployment Guide

### Step 1: Upload Files (2 minutes)
```bash
# Upload entire /src directory contents to:
# public_html/ or yourdomain.com/ folder in Nexcess
```

### Step 2: Database Setup (1 minute)
1. **Create MySQL database** in Nexcess control panel
2. **Note down:** Database name, username, password, host

### Step 3: Configuration (1 minute)
Edit `config/config.php`:
```php
define('DB_HOST', 'localhost');                    // Usually localhost
define('DB_NAME', 'your_database_name');           // From Step 2
define('DB_USER', 'your_nexcess_username');        // From Step 2  
define('DB_PASS', 'your_nexcess_password');        // From Step 2
define('BASE_URL', 'https://yourdomain.com');      // Your domain
```

### Step 4: Automated Setup (1 minute)
Visit: `https://yourdomain.com/setup-database.php?setup=true`

✅ **Done!** Your AIP Tracker is now live.

---

## 🎯 What The Setup Script Does Automatically

- ✅ Creates 10 database tables
- ✅ Populates 30+ AIP-compliant foods  
- ✅ Verifies database connection
- ✅ Shows success confirmation
- ✅ Provides direct link to your app

## 🧪 Test Your Deployment

1. **Main App:** https://yourdomain.com
2. **Registration:** https://yourdomain.com/auth/register.php
3. **Login:** https://yourdomain.com/auth/login.php

## 🆘 Quick Troubleshooting

### Database Connection Error?
- Check credentials in `config/config.php`
- Verify database exists in Nexcess control panel
- Re-run setup script

### 500 Error?
- Check file permissions (644 for files, 755 for directories)
- Verify PHP 8.1+ is enabled in Nexcess

### Rate Limiting?
- Visit: `https://yourdomain.com/reset-rate-limit.php`

---

## 📱 Features Ready After Setup

- ✅ **User Registration/Login**
- ✅ **5-Step Health Interview** 
- ✅ **Food Logging** (30+ AIP foods loaded)
- ✅ **Symptom Tracking** (6 categories)
- ✅ **Water Intake Logging**
- ✅ **Progress Analytics & Charts**
- ✅ **PDF Export for Healthcare Providers**
- ✅ **Mobile-Responsive Design**
- ✅ **Security Features** (CSRF, rate limiting, etc.)

## 🔒 Post-Setup Security

1. **Delete setup file:** `rm setup-database.php`
2. **Enable SSL** in Nexcess control panel
3. **Test HTTPS** redirect works

---

## 📞 Support Files Available

- **Complete Guide:** `NEXCESS-DEPLOYMENT-GUIDE.md`
- **Project Summary:** `PROJECT-SUMMARY.md` 
- **Deployment Checklist:** `DEPLOYMENT-CHECKLIST.md`
- **Next Steps:** `docs/NEXT-STEPS.md`

**Total Setup Time: ~5 minutes** ⏱️  
**Status: Production Ready** ✅  
**Go Live: Immediately** 🚀

---

*AIP Tracker - Complete autoimmune protocol tracking solution*  
*Built with PHP 8.1+ | MySQL 8.0+ | Mobile-First Design*