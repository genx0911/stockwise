<?php
// classes/Auth.php

require_once __DIR__ . '/../config/database.php';

class Auth {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function login(string $email, string $password): array {
        $stmt = $this->db->prepare(
            'SELECT id, name, email, password, role, is_active FROM users WHERE email = ?'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Invalid email or password.'];
        }
        if (!$user['is_active']) {
            return ['success' => false, 'message' => 'Your account has been deactivated.'];
        }

        // Start session
        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['logged_in'] = true;

        $this->log($user['id'], 'login', 'User logged in');

        return ['success' => true, 'role' => $user['role']];
    }

    public function logout(): void {
        if (isset($_SESSION['user_id'])) {
            $this->log($_SESSION['user_id'], 'logout', 'User logged out');
        }
        session_unset();
        session_destroy();
    }

    public static function check(): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['logged_in'])) {
            header('Location: ' . APP_URL . '/index.php');
            exit;
        }
    }

    public static function requireRole(string $role): void {
        self::check();
        if ($_SESSION['user_role'] !== $role) {
            header('Location: ' . APP_URL . '/index.php?error=unauthorized');
            exit;
        }
    }

    public static function user(): array {
        return [
            'id'   => $_SESSION['user_id']   ?? null,
            'name' => $_SESSION['user_name'] ?? 'Guest',
            'role' => $_SESSION['user_role'] ?? null,
        ];
    }

    public static function isAdmin(): bool {
        return ($_SESSION['user_role'] ?? '') === 'admin';
    }

    public function log(int $userId, string $action, string $description = ''): void {
        $stmt = $this->db->prepare(
            'INSERT INTO activity_log (user_id, action, description, ip_address) VALUES (?,?,?,?)'
        );
        $stmt->execute([$userId, $action, $description, $_SERVER['REMOTE_ADDR'] ?? '']);
    }
}
