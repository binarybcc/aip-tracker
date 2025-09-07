<?php
/**
 * AIP Tracker - Food Constants Trait (PHP 8.2+)
 * 
 * Utilizes PHP 8.2 constants in traits feature for better organization
 * of food categories, elimination status, and AIP protocol constants
 */

/**
 * Food constants trait with PHP 8.2+ constants in traits support
 */
trait FoodConstants
{
    // Elimination status constants (PHP 8.2+ constants in traits)
    public const ELIMINATION_ALLOWED = 1;
    public const REINTRODUCTION_ONLY = 0;
    public const NEVER_ALLOWED = -1;
    
    // AIP food categories
    public const CATEGORIES = [
        'protein' => 'Protein Sources',
        'vegetables' => 'Vegetables', 
        'fats' => 'Healthy Fats',
        'carbohydrates' => 'Carbohydrates',
        'fruits' => 'Fruits',
        'herbs' => 'Herbs & Seasonings',
        'beverages' => 'Beverages'
    ];
    
    // AIP elimination phase - foods to avoid
    public const ELIMINATION_AVOID = [
        'grains' => 'All grains (wheat, rice, oats, etc.)',
        'legumes' => 'Beans, lentils, peas, peanuts',
        'dairy' => 'All dairy products',
        'eggs' => 'Chicken eggs and egg products',
        'nuts_seeds' => 'All nuts and seeds',
        'nightshades' => 'Tomatoes, peppers, potatoes, eggplant',
        'refined_sugars' => 'Processed sugars and sweeteners',
        'processed_foods' => 'Packaged and processed foods',
        'alcohol' => 'All alcoholic beverages',
        'nsaids' => 'Non-food: NSAIDs and certain medications'
    ];
    
    // AIP reintroduction categories (order matters)
    public const REINTRODUCTION_ORDER = [
        1 => 'egg_yolks',
        2 => 'seed_spices', 
        3 => 'nuts_seeds',
        4 => 'egg_whites',
        5 => 'nightshades',
        6 => 'dairy',
        7 => 'legumes',
        8 => 'grains'
    ];
    
    // Symptom tracking categories
    public const SYMPTOM_CATEGORIES = [
        'digestive' => 'Digestive Issues',
        'systemic' => 'Systemic Inflammation', 
        'skin' => 'Skin Problems',
        'mood' => 'Mood & Mental Health',
        'sleep' => 'Sleep Quality',
        'energy' => 'Energy Levels',
        'joint' => 'Joint Pain',
        'other' => 'Other Symptoms'
    ];
    
    // Symptom severity levels
    public const SEVERITY_LEVELS = [
        0 => 'None',
        1 => 'Mild',
        2 => 'Moderate', 
        3 => 'Severe',
        4 => 'Very Severe'
    ];
    
    /**
     * Get elimination status text
     */
    public function getEliminationStatusText(int $status): string
    {
        return match($status) {
            self::ELIMINATION_ALLOWED => 'Elimination Phase OK',
            self::REINTRODUCTION_ONLY => 'Reintroduction Phase Only',
            self::NEVER_ALLOWED => 'Never Allowed on AIP',
            default => 'Unknown Status'
        };
    }
    
    /**
     * Get category display name
     */
    public function getCategoryName(string $categoryKey): string
    {
        return self::CATEGORIES[$categoryKey] ?? 'Unknown Category';
    }
    
    /**
     * Check if food is allowed during elimination phase
     */
    public function isEliminationAllowed(int $status): bool
    {
        return $status === self::ELIMINATION_ALLOWED;
    }
    
    /**
     * Get reintroduction phase order
     */
    public function getReintroductionPhase(string $foodCategory): ?int
    {
        return array_search($foodCategory, self::REINTRODUCTION_ORDER) ?: null;
    }
    
    /**
     * Get symptom category name
     */
    public function getSymptomCategoryName(string $categoryKey): string
    {
        return self::SYMPTOM_CATEGORIES[$categoryKey] ?? 'Other';
    }
    
    /**
     * Get severity level text
     */
    public function getSeverityText(int $level): string
    {
        return self::SEVERITY_LEVELS[$level] ?? 'Unknown';
    }
    
    /**
     * Validate food category
     */
    public function isValidCategory(string $category): bool
    {
        return array_key_exists($category, self::CATEGORIES);
    }
    
    /**
     * Validate symptom category
     */
    public function isValidSymptomCategory(string $category): bool
    {
        return array_key_exists($category, self::SYMPTOM_CATEGORIES);
    }
}