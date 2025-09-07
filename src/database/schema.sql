-- AIP Tracker Database Schema
-- Optimized for MySQL 8.0 on Nexcess hosting

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Users table
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `timezone` varchar(50) DEFAULT 'America/New_York',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `login_attempts` int(3) DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_active` (`is_active`),
  KEY `idx_last_login` (`last_login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User profiles and preferences
CREATE TABLE `user_profiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `current_phase` enum('setup','elimination','reintroduction','maintenance') DEFAULT 'setup',
  `start_date` date NOT NULL,
  `target_elimination_days` int(3) DEFAULT 42,
  `health_goals` text,
  `baseline_symptoms` json,
  `motivation_style` enum('achievement','progress','social','data') DEFAULT 'achievement',
  `reminder_preferences` json,
  `water_goal_ml` int(4) DEFAULT 2000,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_phase` (`current_phase`),
  CONSTRAINT `fk_profile_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AIP-compliant food database
CREATE TABLE `food_database` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `category` enum('protein','vegetables','fruits','fats','herbs_spices','other') NOT NULL,
  `subcategory` varchar(100),
  `elimination_allowed` tinyint(1) DEFAULT 1,
  `reintroduction_order` int(2) DEFAULT NULL,
  `common_portions` json,
  `nutritional_notes` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category`),
  KEY `idx_elimination` (`elimination_allowed`),
  KEY `idx_reintro_order` (`reintroduction_order`),
  FULLTEXT KEY `idx_name_search` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Daily food logging
CREATE TABLE `food_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `food_id` int(11) NOT NULL,
  `meal_type` enum('breakfast','lunch','dinner','snack') NOT NULL,
  `portion_size` varchar(100),
  `log_date` date NOT NULL,
  `log_time` time NOT NULL,
  `notes` text,
  `photo_path` varchar(255),
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `food_id` (`food_id`),
  KEY `idx_log_date` (`log_date`),
  KEY `idx_meal_type` (`meal_type`),
  KEY `idx_user_date` (`user_id`, `log_date`),
  CONSTRAINT `fk_foodlog_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_foodlog_food` FOREIGN KEY (`food_id`) REFERENCES `food_database` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Symptom tracking
CREATE TABLE `symptom_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `log_date` date NOT NULL,
  `log_time` time NOT NULL,
  `symptom_type` enum('digestive','systemic','skin','mood','sleep','energy') NOT NULL,
  `symptom_name` varchar(100) NOT NULL,
  `severity` int(1) CHECK (`severity` BETWEEN 1 AND 10),
  `duration_hours` decimal(3,1),
  `notes` text,
  `triggers_suspected` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_log_date` (`log_date`),
  KEY `idx_symptom_type` (`symptom_type`),
  KEY `idx_severity` (`severity`),
  KEY `idx_user_date` (`user_id`, `log_date`),
  CONSTRAINT `fk_symptomlog_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Water intake tracking
CREATE TABLE `water_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `log_date` date NOT NULL,
  `log_time` time NOT NULL,
  `amount_ml` int(4) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_log_date` (`log_date`),
  KEY `idx_user_date` (`user_id`, `log_date`),
  CONSTRAINT `fk_waterlog_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reintroduction testing
CREATE TABLE `reintroduction_tests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `food_id` int(11) NOT NULL,
  `test_start_date` date NOT NULL,
  `test_end_date` date,
  `test_status` enum('planned','active','completed','failed') DEFAULT 'planned',
  `day1_amount` varchar(100),
  `day1_reaction` text,
  `day1_severity` int(1) CHECK (`day1_severity` BETWEEN 1 AND 10),
  `final_result` enum('tolerated','not_tolerated','inconclusive') DEFAULT NULL,
  `notes` text,
  `next_test_date` date,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `food_id` (`food_id`),
  KEY `idx_test_status` (`test_status`),
  KEY `idx_start_date` (`test_start_date`),
  CONSTRAINT `fk_reintro_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reintro_food` FOREIGN KEY (`food_id`) REFERENCES `food_database` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User achievements and streaks
CREATE TABLE `user_achievements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `achievement_type` enum('logging_streak','symptom_improvement','phase_completion','water_goal','milestone') NOT NULL,
  `achievement_name` varchar(255) NOT NULL,
  `achievement_date` date NOT NULL,
  `current_streak` int(4) DEFAULT 0,
  `best_streak` int(4) DEFAULT 0,
  `points_earned` int(4) DEFAULT 0,
  `metadata` json,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_achievement_type` (`achievement_type`),
  KEY `idx_achievement_date` (`achievement_date`),
  CONSTRAINT `fk_achievement_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System notifications and reminders
CREATE TABLE `user_reminders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `reminder_type` enum('meal_log','symptom_check','water_intake','reintroduction','motivation') NOT NULL,
  `reminder_time` time NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `message_template` text,
  `frequency_days` text, -- JSON array of days [0-6] (Sunday=0)
  `last_sent` timestamp NULL DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_reminder_type` (`reminder_type`),
  KEY `idx_is_active` (`is_active`),
  CONSTRAINT `fk_reminder_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Session management
CREATE TABLE `user_sessions` (
  `session_id` varchar(128) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255),
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `last_activity` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `expires_at` timestamp NOT NULL,
  PRIMARY KEY (`session_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_expires` (`expires_at`),
  KEY `idx_last_activity` (`last_activity`),
  CONSTRAINT `fk_session_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert initial AIP-compliant foods
INSERT INTO `food_database` (`name`, `category`, `subcategory`, `elimination_allowed`, `reintroduction_order`, `common_portions`, `nutritional_notes`) VALUES
-- Proteins
('Grass-fed Beef', 'protein', 'red_meat', 1, NULL, '["3 oz", "4 oz", "6 oz"]', 'Rich in iron and B12'),
('Wild-caught Salmon', 'protein', 'fish', 1, NULL, '["3 oz", "4 oz", "6 oz"]', 'High in omega-3 fatty acids'),
('Free-range Chicken', 'protein', 'poultry', 1, NULL, '["3 oz", "4 oz", "6 oz"]', 'Lean protein source'),
('Organ Meats (Liver)', 'protein', 'organ_meat', 1, NULL, '["2 oz", "3 oz"]', 'Nutrient dense, high in vitamins'),

-- Vegetables
('Broccoli', 'vegetables', 'cruciferous', 1, NULL, '["1 cup", "1.5 cups"]', 'High in vitamin C and fiber'),
('Spinach', 'vegetables', 'leafy_greens', 1, NULL, '["1 cup", "2 cups"]', 'Rich in iron and folate'),
('Sweet Potato', 'vegetables', 'starchy', 1, NULL, '["1 medium", "1 cup cubed"]', 'Good source of beta-carotene'),
('Carrots', 'vegetables', 'root_vegetable', 1, NULL, '["1 medium", "1 cup sliced"]', 'High in beta-carotene'),
('Cucumber', 'vegetables', 'other', 1, NULL, '["1 medium", "1 cup sliced"]', 'Hydrating, low calorie'),
('Zucchini', 'vegetables', 'squash', 1, NULL, '["1 medium", "1 cup sliced"]', 'Versatile, mild flavor'),

-- Fruits
('Blueberries', 'fruits', 'berries', 1, NULL, '["1/2 cup", "1 cup"]', 'High in antioxidants'),
('Apple', 'fruits', 'tree_fruit', 1, NULL, '["1 medium", "1 cup sliced"]', 'Good source of fiber'),
('Banana', 'fruits', 'tropical', 1, NULL, '["1 medium", "1/2 cup sliced"]', 'Rich in potassium'),
('Avocado', 'fruits', 'other', 1, NULL, '["1/2 medium", "1/4 cup"]', 'Healthy monounsaturated fats'),

-- Fats
('Olive Oil', 'fats', 'oil', 1, NULL, '["1 tbsp", "2 tbsp"]', 'Heart-healthy monounsaturated fats'),
('Coconut Oil', 'fats', 'oil', 1, NULL, '["1 tbsp", "2 tbsp"]', 'Medium-chain triglycerides'),
('Avocado Oil', 'fats', 'oil', 1, NULL, '["1 tbsp", "2 tbsp"]', 'High smoke point for cooking'),

-- Herbs and Spices
('Sea Salt', 'herbs_spices', 'seasoning', 1, NULL, '["pinch", "1/4 tsp", "1/2 tsp"]', 'Natural mineral content'),
('Fresh Herbs (Basil)', 'herbs_spices', 'fresh_herbs', 1, NULL, '["1 tbsp", "2 tbsp"]', 'Antioxidant properties'),
('Turmeric', 'herbs_spices', 'spice', 1, NULL, '["1/4 tsp", "1/2 tsp"]', 'Anti-inflammatory properties'),
('Ginger', 'herbs_spices', 'spice', 1, NULL, '["1/2 tsp", "1 tsp"]', 'Digestive support');

COMMIT;