-- AIP Tracker Complete Database Installation
-- Run this file to set up the complete database

-- Create database (uncomment if needed)
-- CREATE DATABASE aip_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE aip_tracker;

-- Load main schema
SOURCE database/schema.sql;

-- Load reintroduction foods
SOURCE database/reintroduction_foods.sql;

-- Create database admin user (update credentials)
-- CREATE USER 'aip_user'@'localhost' IDENTIFIED BY 'secure_password_here';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON aip_tracker.* TO 'aip_user'@'localhost';
-- FLUSH PRIVILEGES;

-- Verify installation
SELECT 
    'Users table' as table_name, COUNT(*) as record_count 
FROM users
UNION ALL
SELECT 
    'Food database' as table_name, COUNT(*) as record_count 
FROM food_database
UNION ALL
SELECT 
    'All tables created' as status, COUNT(*) as table_count
FROM information_schema.tables 
WHERE table_schema = DATABASE();

-- Show sample of foods available
SELECT 
    elimination_allowed,
    reintroduction_order,
    COUNT(*) as food_count,
    GROUP_CONCAT(DISTINCT category) as categories
FROM food_database 
GROUP BY elimination_allowed, reintroduction_order 
ORDER BY elimination_allowed DESC, reintroduction_order ASC;

SELECT 'Database installation complete!' as status;