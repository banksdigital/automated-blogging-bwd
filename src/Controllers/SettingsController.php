<?php

namespace App\Controllers;

use App\Helpers\Database;

class SettingsController
{
    private array $config;
    
    public function __construct(array $config)
    {
        $this->config = $config;
    }
    
    /**
     * Get all settings
     */
    public function index(): void
    {
        $settings = Database::query("SELECT setting_key, setting_value FROM app_settings");
        
        $result = [];
        foreach ($settings as $s) {
            $result[$s['setting_key']] = $s['setting_value'];
        }
        
        echo json_encode(['success' => true, 'data' => $result]);
    }
    
    /**
     * Update settings
     */
    public function update(array $input): void
    {
        foreach ($input as $key => $value) {
            $this->upsertSetting($key, $value);
        }
        
        echo json_encode(['success' => true, 'message' => 'Settings saved']);
    }
    
    /**
     * Get brand voice settings
     */
    public function brandVoice(): void
    {
        $settings = Database::query(
            "SELECT setting_key, setting_value FROM app_settings WHERE setting_key LIKE 'brand_voice_%'"
        );
        
        $result = [];
        foreach ($settings as $s) {
            $key = str_replace('brand_voice_', '', $s['setting_key']);
            $result[$key] = $s['setting_value'];
        }
        
        echo json_encode(['success' => true, 'data' => $result]);
    }
    
    /**
     * Update brand voice settings
     */
    public function updateBrandVoice(array $input): void
    {
        foreach ($input as $key => $value) {
            $this->upsertSetting('brand_voice_' . $key, $value);
        }
        
        echo json_encode(['success' => true, 'message' => 'Brand voice saved']);
    }
    
    /**
     * Get default settings
     */
    public function getDefaults(): void
    {
        $settings = Database::query(
            "SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ('default_author_id', 'default_category_id')"
        );
        
        $result = [
            'default_author_id' => null,
            'default_category_id' => null
        ];
        
        foreach ($settings as $s) {
            $result[$s['setting_key']] = $s['setting_value'];
        }
        
        echo json_encode(['success' => true, 'data' => $result]);
    }
    
    /**
     * Save default settings
     */
    public function saveDefaults(array $input): void
    {
        $authorId = $input['default_author_id'] ?? null;
        $categoryId = $input['default_category_id'] ?? null;
        
        $this->upsertSetting('default_author_id', $authorId);
        $this->upsertSetting('default_category_id', $categoryId);
        
        echo json_encode(['success' => true, 'message' => 'Default settings saved']);
    }
    
    /**
     * Upsert a setting
     */
    private function upsertSetting(string $key, ?string $value): void
    {
        Database::execute(
            "INSERT INTO app_settings (setting_key, setting_value) 
             VALUES (?, ?) 
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
            [$key, $value]
        );
    }
    
    /**
     * Get a single setting value (static helper)
     */
    public static function get(string $key): ?string
    {
        $result = Database::queryOne(
            "SELECT setting_value FROM app_settings WHERE setting_key = ?",
            [$key]
        );
        return $result['setting_value'] ?? null;
    }
}
