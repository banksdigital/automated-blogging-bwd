<?php

namespace App\Controllers;

use App\Helpers\Database;

class SetupController
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Check setup status
     */
    public function check(): void
    {
        try {
            $result = Database::queryOne(
                "SELECT setting_value FROM settings WHERE setting_key = 'setup_complete'"
            );
            
            $complete = $result && json_decode($result['setting_value']) === true;
            
            echo json_encode([
                'success' => true,
                'data' => ['setup_complete' => $complete]
            ]);
        } catch (\Exception $e) {
            echo json_encode([
                'success' => true,
                'data' => ['setup_complete' => false, 'error' => 'Database not initialized']
            ]);
        }
    }

    /**
     * Create admin account
     */
    public function createAdmin(array $input): void
    {
        // Check if setup already complete
        try {
            $result = Database::queryOne(
                "SELECT setting_value FROM settings WHERE setting_key = 'setup_complete'"
            );
            if ($result && json_decode($result['setting_value']) === true) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => ['code' => 'SETUP_COMPLETE', 'message' => 'Setup already completed']
                ]);
                return;
            }
        } catch (\Exception $e) {
            // Continue with setup
        }

        // Validate input
        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $confirmPassword = $input['confirm_password'] ?? '';

        $errors = [];
        if (empty($name)) {
            $errors['name'] = 'Name is required';
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Valid email is required';
        }
        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        }
        if ($password !== $confirmPassword) {
            $errors['confirm_password'] = 'Passwords do not match';
        }

        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Validation failed', 'details' => $errors]
            ]);
            return;
        }

        try {
            Database::beginTransaction();

            // Create user
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            
            $userId = Database::insert(
                "INSERT INTO users (email, password, name, role) VALUES (?, ?, ?, 'super_admin')",
                [$email, $hashedPassword, $name]
            );

            // Mark setup as complete
            Database::execute(
                "UPDATE settings SET setting_value = 'true' WHERE setting_key = 'setup_complete'"
            );

            // Store notification email
            Database::execute(
                "INSERT INTO settings (setting_key, setting_value) VALUES ('notification_email', ?) 
                 ON DUPLICATE KEY UPDATE setting_value = ?",
                [json_encode($email), json_encode($email)]
            );

            Database::commit();

            // Auto-login
            $_SESSION['user_id'] = $userId;
            $_SESSION['user'] = [
                'id' => $userId,
                'email' => $email,
                'name' => $name,
                'role' => 'super_admin'
            ];
            session_regenerate_id(true);

            echo json_encode([
                'success' => true,
                'data' => [
                    'message' => 'Setup complete',
                    'user' => $_SESSION['user'],
                    'csrf_token' => $_SESSION['csrf_token']
                ]
            ]);

        } catch (\Exception $e) {
            Database::rollback();
            error_log("Setup error: " . $e->getMessage());
            
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'SETUP_ERROR', 'message' => 'Failed to complete setup']
            ]);
        }
    }
}
