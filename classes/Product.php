<?php
// classes/Product.php

require_once __DIR__ . '/../config/database.php';

class Product {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getAll(bool $activeOnly = false): array {
        $sql = '
            SELECT p.*, c.name AS category_name,
                   CASE WHEN p.stock_quantity <= p.low_stock_threshold THEN 1 ELSE 0 END AS is_low_stock
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
        ';
        if ($activeOnly) $sql .= ' WHERE p.is_active = 1';
        $sql .= ' ORDER BY p.name ASC';
        return $this->db->query($sql)->fetchAll();
    }

    public function getById(int $id): array|false {
        $stmt = $this->db->prepare('
            SELECT p.*, c.name AS category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.id = ?
        ');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getLowStock(): array {
        return $this->db->query('
            SELECT p.*, c.name AS category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.stock_quantity <= p.low_stock_threshold AND p.is_active = 1
            ORDER BY p.stock_quantity ASC
        ')->fetchAll();
    }

    public function create(array $data): array {
        $stmt = $this->db->prepare('SELECT id FROM products WHERE sku = ?');
        $stmt->execute([$data['sku']]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'SKU already exists.'];
        }

        $stmt = $this->db->prepare('
            INSERT INTO products (category_id, sku, name, description, unit, price, stock_quantity, low_stock_threshold)
            VALUES (?,?,?,?,?,?,?,?)
        ');
        $stmt->execute([
            $data['category_id'], $data['sku'],   $data['name'],
            $data['description'], $data['unit'],  $data['price'],
            $data['stock_quantity'], $data['low_stock_threshold']
        ]);
        return ['success' => true, 'id' => $this->db->lastInsertId()];
    }

    public function update(int $id, array $data): array {
        $stmt = $this->db->prepare('SELECT id FROM products WHERE sku = ? AND id != ?');
        $stmt->execute([$data['sku'], $id]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'SKU already in use.'];
        }

        $stmt = $this->db->prepare('
            UPDATE products SET
                category_id=?, sku=?, name=?, description=?, unit=?,
                price=?, stock_quantity=?, low_stock_threshold=?
            WHERE id=?
        ');
        $stmt->execute([
            $data['category_id'], $data['sku'],   $data['name'],
            $data['description'], $data['unit'],  $data['price'],
            $data['stock_quantity'], $data['low_stock_threshold'], $id
        ]);
        return ['success' => true];
    }

    public function adjustStock(int $id, int $adjustment): bool {
        $stmt = $this->db->prepare(
            'UPDATE products SET stock_quantity = GREATEST(0, stock_quantity + ?) WHERE id = ?'
        );
        $stmt->execute([$adjustment, $id]);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare('UPDATE products SET is_active = 0 WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function getStats(): array {
        return $this->db->query('
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN stock_quantity <= low_stock_threshold THEN 1 ELSE 0 END) AS low_stock_count,
                SUM(stock_quantity * price) AS total_value,
                SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) AS out_of_stock
            FROM products WHERE is_active = 1
        ')->fetch();
    }

    public function getCategories(): array {
        return $this->db->query('SELECT * FROM categories ORDER BY name')->fetchAll();
    }

    public function createCategory(string $name, string $desc = ''): bool {
        $stmt = $this->db->prepare('INSERT INTO categories (name, description) VALUES (?,?)');
        $stmt->execute([$name, $desc]);
        return true;
    }
}
