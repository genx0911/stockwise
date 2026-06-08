<?php
// classes/Order.php

require_once __DIR__ . '/../config/database.php';

class Order {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    private function generateOrderNo(): string {
        return 'ORD-' . strtoupper(date('Ymd')) . '-' . strtoupper(substr(uniqid(), -4));
    }

    public function create(int $staffId, array $items, string $notes = ''): array {
        if (empty($items)) {
            return ['success' => false, 'message' => 'Order must have at least one item.'];
        }

        $this->db->beginTransaction();
        try {
            $orderNo = $this->generateOrderNo();
            $stmt = $this->db->prepare(
                'INSERT INTO orders (order_no, staff_id, notes) VALUES (?,?,?)'
            );
            $stmt->execute([$orderNo, $staffId, $notes]);
            $orderId = $this->db->lastInsertId();

            foreach ($items as $item) {
                // Fetch current price
                $pStmt = $this->db->prepare('SELECT price FROM products WHERE id = ? AND is_active = 1');
                $pStmt->execute([$item['product_id']]);
                $product = $pStmt->fetch();
                if (!$product) throw new Exception("Product ID {$item['product_id']} not found.");

                $stmt = $this->db->prepare(
                    'INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?,?,?,?)'
                );
                $stmt->execute([$orderId, $item['product_id'], $item['quantity'], $product['price']]);
            }

            $this->db->commit();
            return ['success' => true, 'order_id' => $orderId, 'order_no' => $orderNo];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getAll(?string $status = null): array {
        $sql = '
            SELECT o.*, u.name AS staff_name,
                   a.name AS reviewer_name,
                   COUNT(oi.id) AS item_count,
                   SUM(oi.quantity * oi.unit_price) AS total_amount
            FROM orders o
            JOIN users u ON o.staff_id = u.id
            LEFT JOIN users a ON o.reviewed_by = a.id
            LEFT JOIN order_items oi ON o.id = oi.order_id
        ';
        $params = [];
        if ($status) {
            $sql .= ' WHERE o.status = ?';
            $params[] = $status;
        }
        $sql .= ' GROUP BY o.id ORDER BY o.created_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getByStaff(int $staffId): array {
        $stmt = $this->db->prepare('
            SELECT o.*,
                   a.name AS reviewer_name,
                   COUNT(oi.id) AS item_count,
                   SUM(oi.quantity * oi.unit_price) AS total_amount
            FROM orders o
            LEFT JOIN users a ON o.reviewed_by = a.id
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE o.staff_id = ?
            GROUP BY o.id
            ORDER BY o.created_at DESC
        ');
        $stmt->execute([$staffId]);
        return $stmt->fetchAll();
    }

    public function getById(int $id): array|false {
        $stmt = $this->db->prepare('
            SELECT o.*, u.name AS staff_name, a.name AS reviewer_name,
                   SUM(oi.quantity * oi.unit_price) AS total_amount
            FROM orders o
            JOIN users u ON o.staff_id = u.id
            LEFT JOIN users a ON o.reviewed_by = a.id
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE o.id = ?
            GROUP BY o.id
        ');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getItems(int $orderId): array {
        $stmt = $this->db->prepare('
            SELECT oi.*, p.name AS product_name, p.sku, p.unit
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ');
        $stmt->execute([$orderId]);
        return $stmt->fetchAll();
    }

    public function updateStatus(int $orderId, string $status, int $adminId): array {
        $allowed = ['approved', 'rejected', 'fulfilled'];
        if (!in_array($status, $allowed)) {
            return ['success' => false, 'message' => 'Invalid status.'];
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('
                UPDATE orders SET status=?, reviewed_by=?, reviewed_at=NOW()
                WHERE id=? AND status=\'pending\'
            ');

            if ($status === 'fulfilled') {
                $stmt = $this->db->prepare('
                    UPDATE orders SET status=?, reviewed_by=?, reviewed_at=NOW()
                    WHERE id=? AND status=\'approved\'
                ');
                $stmt->execute([$status, $adminId, $orderId]);

                $items = $this->getItems($orderId);
                foreach ($items as $item) {
                    $upd = $this->db->prepare(
                        'UPDATE products SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE id = ?'
                    );
                    $upd->execute([$item['quantity'], $item['product_id']]);
                }
            } else {
                $stmt->execute([$status, $adminId, $orderId]);
            }

            // Notify the staff member
            $order = $this->getById($orderId);
            if ($order) {
                $messages = [
                    'approved'  => "Your order {$order['order_no']} has been approved.",
                    'rejected'  => "Your order {$order['order_no']} has been rejected.",
                    'fulfilled' => "Your order {$order['order_no']} has been fulfilled.",
                ];
                $n = $this->db->prepare(
                    'INSERT INTO notifications (user_id, order_id, type, message) VALUES (?,?,?,?)'
                );
                $n->execute([$order['staff_id'], $orderId, $status, $messages[$status]]);
            }

            $this->db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function cancel(int $orderId, int $staffId): array {
        $stmt = $this->db->prepare(
            'UPDATE orders SET status="rejected" WHERE id=? AND staff_id=? AND status="pending"'
        );
        $stmt->execute([$orderId, $staffId]);
        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Order cannot be cancelled (not pending or not yours).'];
        }
        return ['success' => true];
    }

    public function getStats(): array {
        return $this->db->query('
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status="pending"   THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status="approved"  THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN status="fulfilled" THEN 1 ELSE 0 END) AS fulfilled,
                SUM(CASE WHEN status="rejected"  THEN 1 ELSE 0 END) AS rejected
            FROM orders
        ')->fetch();
    }
}
