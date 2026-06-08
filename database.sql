-- ============================================================
-- StockWise · Inventory & Order Management System
-- Database Schema
-- Import via phpMyAdmin or: mysql -u root -p < database.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS stockwise CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE stockwise;

-- ── Users ─────────────────────────────────────────────────
CREATE TABLE users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    email       VARCHAR(150) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ── Categories ─────────────────────────────────────────────
CREATE TABLE categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ── Products ───────────────────────────────────────────────
CREATE TABLE products (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    category_id     INT,
    sku             VARCHAR(50) NOT NULL UNIQUE,
    name            VARCHAR(150) NOT NULL,
    description     TEXT,
    unit            VARCHAR(30) NOT NULL DEFAULT 'pcs',
    price           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    stock_quantity  INT NOT NULL DEFAULT 0,
    low_stock_threshold INT NOT NULL DEFAULT 10,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- ── Orders ─────────────────────────────────────────────────
CREATE TABLE orders (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    order_no    VARCHAR(30) NOT NULL UNIQUE,
    staff_id    INT NOT NULL,
    status      ENUM('pending','approved','rejected','fulfilled') NOT NULL DEFAULT 'pending',
    notes       TEXT,
    reviewed_by INT,
    reviewed_at DATETIME,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id)    REFERENCES users(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
);

-- ── Order Items ────────────────────────────────────────────
CREATE TABLE order_items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    order_id    INT NOT NULL,
    product_id  INT NOT NULL,
    quantity    INT NOT NULL,
    unit_price  DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id)   REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- ── Activity Log ───────────────────────────────────────────
CREATE TABLE activity_log (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT,
    action      VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address  VARCHAR(45),
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ── Notifications ─────────────────────────────────────────────────────────
CREATE TABLE notifications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    order_id   INT,
    type       VARCHAR(30) NOT NULL,
    message    TEXT NOT NULL,
    is_read    TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
);

-- ── Seed Data ──────────────────────────────────────────────
-- Default admin: admin@stockwise.com / admin123
-- Default staff: staff@stockwise.com / staff123
INSERT INTO users (name, email, password, role) VALUES
('Admin User',  'admin@stockwise.com', '$2y$12$S96ZrviOdM.WQ7OTS5IxqO74zWB.2ZTDqEte8PZt4nqFSLCPc8glK', 'admin'),
('Staff User',  'staff@stockwise.com', '$2y$12$S96ZrviOdM.WQ7OTS5IxqO74zWB.2ZTDqEte8PZt4nqFSLCPc8glK', 'staff');

INSERT INTO categories (name, description) VALUES
('Books',        'All book titles and publications'),
('Stationery',   'Pens, pencils, notebooks, and school supplies'),
('Art Supplies', 'Drawing, painting, and craft materials'),
('Office',       'Office supplies and accessories');

INSERT INTO products (category_id, sku, name, unit, price, stock_quantity, low_stock_threshold) VALUES
(1, 'BK-001', 'Noli Me Tangere (Tagalog)',   'pcs', 299.00, 45, 10),
(1, 'BK-002', 'El Filibusterismo',            'pcs', 299.00, 32, 10),
(1, 'BK-003', 'English Grammar Workbook',     'pcs', 185.00, 8,  10),
(2, 'ST-001', 'Mongol Pencil #2 (12pcs)',     'box', 55.00,  60, 15),
(2, 'ST-002', 'Ballpoint Pen Black (12pcs)',  'box', 75.00,  5,  15),
(2, 'ST-003', 'Composition Notebook 80lvs',   'pcs', 45.00,  120,20),
(3, 'AR-001', 'Watercolor Set 12 colors',     'set', 149.00, 18, 8),
(3, 'AR-002', 'Sketch Pad A4',                'pcs', 89.00,  7,  8),
(4, 'OF-001', 'Stapler Heavy Duty',           'pcs', 195.00, 22, 5),
(4, 'OF-002', 'Correction Tape',              'pcs', 35.00,  3,  10);
