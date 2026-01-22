<?php

namespace App\Controllers;

use App\Helpers\Database;

class AuthController
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Handle login
     */
    public function login(array $input): void
    {
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if (empty($email) || empty($password)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Email and password are required']
            ]);
            return;
        }

        // Find user
        $user = Database::queryOne(
            "SELECT id, email, password, name, role FROM users WHERE email = ?",
            [$email]
        );

        if (!$user || !password_verify($password, $user['password'])) {
            // Log failed attempt
            $this->logActivity(null, 'login_failed', 'user', null, ['email' => $email]);
            
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'INVALID_CREDENTIALS', 'message' => 'Invalid email or password']
            ]);
            return;
        }

        // Update last login
        Database::execute(
            "UPDATE users SET last_login = NOW() WHERE id = ?",
            [$user['id']]
        );

        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user'] = [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'role' => $user['role']
        ];

        // Regenerate session ID for security
        session_regenerate_id(true);

        // Log successful login
        $this->logActivity($user['id'], 'login_success', 'user', $user['id']);

        echo json_encode([
            'success' => true,
            'data' => [
                'user' => $_SESSION['user'],
                'csrf_token' => $_SESSION['csrf_token']
            ]
        ]);
    }

    /**
     * Handle logout
     */
    public function logout(): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        
        if ($userId) {
            $this->logActivity($userId, 'logout', 'user', $userId);
        }

        // Clear session
        $_SESSION = [];
        session_destroy();

        echo json_encode([
            'success' => true,
            'data' => ['message' => 'Logged out successfully']
        ]);
    }

    /**
     * Get current session
     */
    public function session(): void
    {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode([
                'success' => true,
                'data' => [
                    'authenticated' => false,
                    'csrf_token' => $_SESSION['csrf_token']
                ]
            ]);
            return;
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'authenticated' => true,
                'user' => $_SESSION['user'],
                'csrf_token' => $_SESSION['csrf_token']
            ]
        ]);
    }

    /**
     * Log activity
     */
    private function logActivity(?int $userId, string $action, string $entityType, ?int $entityId, array $details = []): void
    {
        try {
            Database::insert(
                "INSERT INTO activity_log (user_id, action, entity_type, entity_id, details_json, ip_address) VALUES (?, ?, ?, ?, ?, ?)",
                [
                    $userId,
                    $action,
                    $entityType,
                    $entityId,
                    json_encode($details),
                    $_SERVER['REMOTE_ADDR'] ?? null
                ]
            );
        } catch (\Exception $e) {
            error_log("Failed to log activity: " . $e->getMessage());
        }
    }
}
