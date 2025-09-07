# AIP Tracker - Deployment Guide for Nexcess Hosting

## 🚀 Quick Deployment Steps

### 1. Upload Files to Nexcess
1. **Upload the entire `src/` folder contents** to your domain's `public_html` directory
2. **File structure should be:**
   ```
   public_html/
   ├── index.php
   ├── dashboard.php
   ├── auth/
   ├── assets/
   ├── config/
   ├── includes/
   └── [other folders]
   ```

### 2. Database Setup
1. **Create MySQL database** in Nexcess control panel
2. **Note your database credentials:**
   - Database name
   - Database username  
   - Database password
   - Host (usually `localhost`)

3. **Import database schema:**
   - Upload `deploy/install.sql` to your server
   - Run: `mysql -u username -p database_name < install.sql`
   - Or use phpMyAdmin to import the file

### 3. Configuration
1. **Copy configuration file:**
   ```bash
   cp deploy/nexcess-config.php config/config.php
   ```

2. **Update config/config.php with your details:**
   ```php
   define('BASE_URL', 'https://yourdomain.com/');
   define('DB_NAME', 'your_actual_db_name');
   define('DB_USER', 'your_actual_db_user'); 
   define('DB_PASS', 'your_actual_db_pass');
   ```

3. **Update database credentials in config/database.php**

### 4. Set File Permissions
```bash
chmod 755 auth/
chmod 755 assets/
chmod 644 *.php
chmod 644 assets/css/*.css
chmod 644 assets/js/*.js
```

### 5. Test Installation
1. Visit `https://yourdomain.com/`
2. Should redirect to login page
3. Register a new account
4. Complete the setup interview
5. Test food logging, symptom tracking, and dashboard

## 🔧 Troubleshooting

### Common Issues:

**"Database connection failed"**
- Check database credentials in `config/config.php`
- Verify database exists and user has permissions
- Confirm host is correct (usually `localhost` on Nexcess)

**"Permission denied" errors**
- Set proper file permissions: `chmod 644 *.php`
- Ensure folders are readable: `chmod 755 directory_name`

**"Session errors"**
- Check that `session_start()` isn't called before headers
- Verify session directory is writable

**"CSRF token errors"**
- Clear browser cache and cookies
- Check that sessions are working properly

### Performance Optimization:

1. **Enable PHP OPcache** (usually available on Nexcess)
2. **Use HTTPS** for security (free SSL available on Nexcess)
3. **Enable Gzip compression** in .htaccess:
   ```apache
   <IfModule mod_deflate.c>
     AddOutputFilterByType DEFLATE text/html text/css text/javascript application/javascript
   </IfModule>
   ```

## 📊 Database Information

**Total Tables:** 8
- `users` - User accounts
- `user_profiles` - User preferences and settings
- `food_database` - AIP foods (40+ foods included)
- `food_logs` - Daily food tracking
- `symptom_logs` - Symptom monitoring
- `water_logs` - Water intake tracking
- `reintroduction_tests` - Food reintroduction testing
- `user_achievements` - Gamification and motivation

**Storage Requirements:**
- Initial database: ~500KB
- Per active user: ~2-5MB/month (depending on usage)
- Estimated for 100 users: ~500MB/month

## 🔐 Security Features Included

- **Password hashing** with PHP's `password_hash()`
- **CSRF protection** on all forms
- **Input sanitization** and validation
- **Rate limiting** on login attempts
- **Session security** with proper cookie settings
- **SQL injection protection** with prepared statements

## 📱 Mobile Optimization

- **Responsive design** works on all devices
- **Touch-friendly** buttons (44px minimum)
- **PWA-ready** architecture
- **Fast loading** optimized for mobile networks

## 🎯 Features Included

### ✅ Completed Features:
- User registration/login system
- 5-step onboarding interview
- Food logging with AIP-compliant database
- Comprehensive symptom tracking (6 categories)
- Water intake tracking with gamification
- Motivational dashboard with progress tracking
- Reintroduction phase scheduler
- Achievement system with points and badges
- Mobile-responsive design

### 📈 Usage Analytics Ready:
The database structure supports tracking:
- Daily active users
- Feature utilization rates
- Symptom improvement trends
- Food tolerance patterns
- User engagement metrics

## 💡 Tips for Success

1. **Start with elimination phase** - Most users begin here
2. **Encourage daily logging** - Consistency is key for pattern recognition
3. **Monitor reintroduction carefully** - 5-7 day testing cycles are crucial
4. **Regular backups** - Set up automatic database backups
5. **User education** - Include links to AIP resources and guidelines

## 🆘 Support

For technical issues:
1. Check server error logs
2. Verify all file uploads completed
3. Test database connection manually
4. Ensure PHP version 7.4+ is enabled
5. Contact Nexcess support if needed

---

**Deployment Checklist:**
- [ ] Files uploaded to public_html
- [ ] Database created and imported
- [ ] Configuration updated with real credentials
- [ ] File permissions set correctly
- [ ] Test registration and login working
- [ ] Food logging functional
- [ ] Symptom tracking operational
- [ ] Dashboard displaying correctly
- [ ] Mobile responsiveness verified

**🎉 Your AIP Tracker is ready to help users on their healing journey!**