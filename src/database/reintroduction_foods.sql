-- Additional reintroduction foods for AIP Protocol
-- These foods are NOT allowed during elimination phase

INSERT INTO `food_database` (`name`, `category`, `subcategory`, `elimination_allowed`, `reintroduction_order`, `common_portions`, `nutritional_notes`) VALUES

-- Stage 1: Egg Yolks & Seed Oils (Order 1)
('Egg Yolk (separated)', 'protein', 'eggs', 0, 1, '["1 yolk", "2 yolks", "3 yolks"]', 'Start with just the yolk, no whites'),
('Ghee (clarified butter)', 'fats', 'dairy_derived', 0, 1, '["1 tsp", "1 tbsp", "2 tbsp"]', 'Lactose and casein removed'),

-- Stage 2: Herbs & Spices (Order 2)
('Black Pepper', 'herbs_spices', 'spice', 0, 2, '["pinch", "1/4 tsp", "1/2 tsp"]', 'Common allergen for some'),
('Vanilla Extract', 'herbs_spices', 'extract', 0, 2, '["1/4 tsp", "1/2 tsp", "1 tsp"]', 'Ensure alcohol-free or vanilla bean'),
('Fresh Dill', 'herbs_spices', 'fresh_herbs', 0, 2, '["1 tsp", "1 tbsp", "2 tbsp"]', 'Mild reintroduction herb'),
('Fresh Cilantro', 'herbs_spices', 'fresh_herbs', 0, 2, '["1 tsp", "1 tbsp", "2 tbsp"]', 'Detoxifying properties'),

-- Stage 3: Nuts & Seeds (Order 3)
('Almonds', 'other', 'tree_nuts', 0, 3, '["5 nuts", "10 nuts", "1/4 cup"]', 'High in omega-6, start small'),
('Walnuts', 'other', 'tree_nuts', 0, 3, '["3 halves", "6 halves", "1/4 cup"]', 'Good omega-3 source'),
('Sunflower Seeds', 'other', 'seeds', 0, 3, '["1 tsp", "1 tbsp", "2 tbsp"]', 'High in vitamin E'),
('Pumpkin Seeds', 'other', 'seeds', 0, 3, '["1 tsp", "1 tbsp", "2 tbsp"]', 'Rich in zinc and magnesium'),
('Cashews', 'other', 'tree_nuts', 0, 3, '["5 nuts", "10 nuts", "1/4 cup"]', 'Lower fiber than other nuts'),

-- Stage 4: Nightshade Spices (Order 4)
('Paprika', 'herbs_spices', 'nightshade_spice', 0, 4, '["pinch", "1/4 tsp", "1/2 tsp"]', 'Derived from peppers - test carefully'),
('Chili Powder', 'herbs_spices', 'nightshade_spice', 0, 4, '["pinch", "1/4 tsp"]', 'Contains multiple nightshades'),
('Cayenne Pepper', 'herbs_spices', 'nightshade_spice', 0, 4, '["tiny pinch", "pinch"]', 'Very potent, start extremely small'),

-- Stage 5: Whole Eggs (Order 5)
('Whole Eggs (with whites)', 'protein', 'eggs', 0, 5, '["1 egg", "2 eggs", "3 eggs"]', 'Egg whites are more allergenic than yolks'),
('Duck Eggs', 'protein', 'eggs', 0, 5, '["1 egg", "2 eggs"]', 'Alternative to chicken eggs'),

-- Stage 6: Nightshade Vegetables (Order 6)
('Tomatoes', 'vegetables', 'nightshade', 0, 6, '["1 cherry tomato", "1/4 cup", "1/2 cup"]', 'High in lectins and alkaloids'),
('White Potatoes', 'vegetables', 'nightshade', 0, 6, '["1 small potato", "1/2 medium", "1 medium"]', 'High glycemic, test when peeled first'),
('Sweet Bell Peppers', 'vegetables', 'nightshade', 0, 6, '["1/4 pepper", "1/2 pepper", "1 pepper"]', 'Start with sweeter varieties'),
('Eggplant', 'vegetables', 'nightshade', 0, 6, '["2 tbsp cooked", "1/4 cup", "1/2 cup"]', 'High in solanine, peel before testing'),
('Hot Peppers (Jalapeño)', 'vegetables', 'nightshade', 0, 6, '["tiny piece", "small piece"]', 'Very inflammatory for many'),

-- Stage 7: Dairy Products (Order 7)
('Grass-fed Butter', 'fats', 'dairy', 0, 7, '["1/2 tsp", "1 tsp", "1 tbsp"]', 'Contains casein and lactose'),
('Heavy Cream (grass-fed)', 'fats', 'dairy', 0, 7, '["1 tsp", "1 tbsp", "2 tbsp"]', 'Lower lactose than milk'),
('Full-fat Yogurt (grass-fed)', 'other', 'dairy', 0, 7, '["1 tsp", "1 tbsp", "1/4 cup"]', 'Fermented, may be better tolerated'),
('Hard Aged Cheese', 'protein', 'dairy', 0, 7, '["1 small piece", "1 oz", "2 oz"]', 'Lower lactose due to aging'),

-- Stage 8: Grains & Pseudo-grains (Order 8)
('White Rice', 'other', 'grain', 0, 8, '["1/4 cup cooked", "1/2 cup", "1 cup"]', 'Least inflammatory grain option'),
('Quinoa', 'other', 'pseudo_grain', 0, 8, '["1/4 cup cooked", "1/2 cup", "1 cup"]', 'Complete protein, rinse well'),
('Buckwheat', 'other', 'pseudo_grain', 0, 8, '["1/4 cup cooked", "1/2 cup"]', 'Despite name, not related to wheat'),
('Oats (gluten-free)', 'other', 'grain', 0, 8, '["1/4 cup cooked", "1/2 cup"]', 'Ensure certified gluten-free'),

-- Stage 9: Legumes (Order 9)
('Green Beans', 'vegetables', 'legume', 0, 9, '["1/4 cup", "1/2 cup", "1 cup"]', 'Less problematic legume, eat pods'),
('Green Peas', 'vegetables', 'legume', 0, 9, '["1/4 cup", "1/2 cup", "1 cup"]', 'Moderate lectin content'),
('Lima Beans', 'other', 'legume', 0, 9, '["1/4 cup cooked", "1/2 cup"]', 'Soak and cook thoroughly'),
('Black Beans', 'other', 'legume', 0, 9, '["1/4 cup cooked", "1/2 cup"]', 'High fiber, soak overnight'),
('Chickpeas', 'other', 'legume', 0, 9, '["1/4 cup cooked", "1/2 cup"]', 'Also known as garbanzo beans'),
('Lentils (red)', 'other', 'legume', 0, 9, '["1/4 cup cooked", "1/2 cup"]', 'Red lentils may be better tolerated'),

-- Stage 10: Gluten-containing Grains (Order 10) - Test last and most carefully
('Wheat Products', 'other', 'gluten_grain', 0, 10, '["1 small piece bread", "1/4 cup pasta"]', 'Highly inflammatory - test very carefully'),
('Rye', 'other', 'gluten_grain', 0, 10, '["1 small piece", "1/4 cup"]', 'Contains gluten, test after wheat'),
('Barley', 'other', 'gluten_grain', 0, 10, '["1/4 cup cooked", "1/2 cup"]', 'Contains gluten, often in soups');

-- Update existing AIP foods to ensure proper elimination_allowed flag
UPDATE `food_database` SET `elimination_allowed` = 1 WHERE `elimination_allowed` IS NULL;

COMMIT;