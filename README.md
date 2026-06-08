# 📦 StockWise — Inventory & Order Management System

A clean, role-based inventory and order management system built in PHP/MySQL with OOP principles.

## 👤 Roles

| Role  | Can Do |
|-------|--------|
| **Admin** | Manage products, categories, users · Approve/reject/fulfil orders · View activity log |
| **Staff** | Browse inventory · Place orders · Track their own order status |

## 🚀 Setup (XAMPP)

### 1. Copy the project
```
C:\xampp\htdocs\inventory_system\
```

### 2. Import the database
- Open **phpMyAdmin** → `http://localhost/phpmyadmin`
- Click **Import** → choose `database.sql`
- Click **Go**

### 3. Configure DB (if needed)
Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');       // your MySQL password
define('DB_NAME', 'stockwise');
define('APP_URL', 'http://localhost/inventory_system');
```

### 4. Open the app
```
http://localhost/inventory_system/
```

## 🔑 Demo Accounts

| Role  | Email | Password |
|-------|-------|----------|
| Admin | admin@stockwise.com | admin123 |
| Staff | staff@stockwise.com | staff123 |

## 📁 Project Structure

```
inventory_system/
├── config/
│   └── database.php          # DB connection (PDO singleton)
├── classes/
│   ├── Auth.php               # Login, session, RBAC
│   ├── User.php               # User CRUD
│   ├── Product.php            # Inventory management
│   └── Order.php              # Order lifecycle
├── includes/
│   ├── header.php             # Sidebar + shared CSS
│   └── footer.php             # JS + Bootstrap
├── admin/
│   ├── dashboard.php          # KPIs + low-stock alert
│   ├── products.php           # AJAX product management
│   ├── orders.php             # Approve / reject / fulfil
│   └── users.php              # AJAX user management
├── staff/
│   ├── dashboard.php          # Personal summary
│   ├── new_order.php          # Live cart → submit order
│   └── orders.php             # My order history
├── index.php                  # Login page
├── logout.php
└── database.sql               # Full schema + seed data
```

## ✨ Key Features

- **OOP PHP** — Each entity (Auth, User, Product, Order) is a clean class
- **PDO with prepared statements** — No SQL injection
- **RBAC** — `Auth::requireRole('admin')` guards every admin page
- **AJAX** — Add/edit/delete products and users without page refreshes
- **Live cart** — Staff can build orders dynamically with quantity controls
- **Order lifecycle** — pending → approved → fulfilled (with stock deduction)
- **Low-stock alerts** — Highlighted on both dashboards
- **Activity log** — All logins recorded in DB

## 🔒 Security Notes

- Passwords hashed with `password_hash()` (bcrypt, cost 12)
- All DB queries use PDO prepared statements
- Session regenerated on login (`session_regenerate_id`)
- Role checked on every protected page server-side

## 🔧 Adding More Features

This base makes it easy to add:
- PDF invoice generation (use `dompdf` or `FPDF`)
- Email notifications for low stock (PHPMailer)
- Export to Excel (PhpSpreadsheet)
- Pagination on large tables
