<?php

namespace App\Controllers;

use App\Helpers\Database;

class SettingsController
{
    public function __construct(array $config) {}

    public function index(): void
    {
        $settings = Database::query("SELECT setting_key, setting_value FROM settings");
        $data = [];
        foreach ($settings as $s) {
            $data[$s['setting_key']] = json_decode($s['setting_value'], true);
        }
        echo json_encode(['success' => true, 'data' => $data]);
    }

    public function update(array $input): void
    {
        foreach ($input as $key => $value) {
            Database::execute(
                "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = ?",
                [$key, json_encode($value), json_encode($value)]
            );
        }
        echo json_encode(['success' => true, 'data' => ['message' => 'Settings updated']]);
    }

    public function brandVoice(): void
    {
        $voice = Database::query("SELECT * FROM brand_voice ORDER BY weight DESC");
        echo json_encode(['success' => true, 'data' => $voice]);
    }

    public function updateBrandVoice(array $input): void
    {
        if (isset($input['items']) && is_array($input['items'])) {
            foreach ($input['items'] as $item) {
                if (!empty($item['id'])) {
                    Database::execute(
                        "UPDATE brand_voice SET attribute = ?, description = ?, examples = ?, weight = ?, is_active = ? WHERE id = ?",
                        [
                            $item['attribute'],
                            $item['description'],
                            json_encode($item['examples'] ?? []),
                            $item['weight'] ?? 5,
                            $item['is_active'] ?? true,
                            $item['id']
                        ]
                    );
                } else {
                    Database::insert(
                        "INSERT INTO brand_voice (attribute, description, examples, weight, is_active) VALUES (?, ?, ?, ?, ?)",
                        [
                            $item['attribute'],
                            $item['description'],
                            json_encode($item['examples'] ?? []),
                            $item['weight'] ?? 5,
                            $item['is_active'] ?? true
                        ]
                    );
                }
            }
        }
        echo json_encode(['success' => true, 'data' => ['message' => 'Brand voice updated']]);
    }
}
