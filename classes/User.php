<?php
// classes/User.php

require_once __DIR__ . '/../config/database.php';

class User {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getAll(): array {
        return $this->db->query(
            'SELECT id, name, email, role, is_active, created_at FROM users ORDER BY created_at DESC'
        )->fetchAll();
    }

    public function getById(int $id): array|false {
        $stmt = $this->db->prepare(
            'SELECT id, name, email, role, is_active, created_at FROM users WHERE id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function create(string $name, string $email, string $password, string $role): array {
        // Check duplicate email
        $stmt = $this->db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Email already exists.'];
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $this->db->prepare(
            'INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)'
        );
        $stmt->execute([$name, $email, $hash, $role]);
        return ['success' => true, 'id' => $this->db->lastInsertId()];
    }

    public function update(int $id, string $name, string $email, string $role, ?string $password = null): array {
        // Check duplicate email (excluding self)
        $stmt = $this->db->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $stmt->execute([$email, $id]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Email already in use.'];
        }

        if ($password) {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $this->db->prepare(
                'UPDATE users SET name=?, email=?, role=?, password=? WHERE id=?'
            );
            $stmt->execute([$name, $email, $role, $hash, $id]);
        } else {
            $stmt = $this->db->prepare(
                'UPDATE users SET name=?, email=?, role=? WHERE id=?'
            );
            $stmt->execute([$name, $email, $role, $id]);
        }
        return ['success' => true];
    }

    public function toggleActive(int $id): bool {
        $stmt = $this->db->prepare(
            'UPDATE users SET is_active = NOT is_active WHERE id = ? AND id != 1'
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): array {
        if ($id === 1) {
            return ['success' => false, 'message' => 'Cannot delete the main admin.'];
        }
        $stmt = $this->db->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return ['success' => true];
    }

    public function countByRole(): array {
        return $this->db->query(
            'SELECT role, COUNT(*) as count FROM users GROUP BY role'
        )->fetchAll();
    }
}
