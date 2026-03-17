-- Daily Food Inventory System Database
-- Created: February 3, 2026

CREATE DATABASE IF NOT EXISTS penongs_inventory;
USE penongs_inventory;

-- Users Table (Admin and Manager)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    role ENUM('admin', 'manager') NOT NULL,
    branch_id INT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    profile_picture VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Branches Table
CREATE TABLE branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_name VARCHAR(100) NOT NULL,
    branch_code VARCHAR(20) UNIQUE NOT NULL,
    address TEXT,
    contact_number VARCHAR(20),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Categories Table
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(50) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Items Table
CREATE TABLE items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(100) NOT NULL,
    category_id INT NOT NULL,
    unit VARCHAR(20) NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

-- Daily Inventory Table
CREATE TABLE daily_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inventory_date DATE NOT NULL,
    branch_id INT NOT NULL,
    item_id INT NOT NULL,
    beginning_inventory DECIMAL(10,2) DEFAULT 0,
    added_stock DECIMAL(10,2) DEFAULT 0,
    total_stock DECIMAL(10,2) DEFAULT 0,
    daily_sales DECIMAL(10,2) DEFAULT 0,
    ending_inventory DECIMAL(10,2) DEFAULT 0,
    remarks TEXT,
    prepared_by INT,
    reviewed_by INT,
    status ENUM('draft', 'finalized') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (item_id) REFERENCES items(id),
    FOREIGN KEY (prepared_by) REFERENCES users(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id),
    UNIQUE KEY unique_daily_inventory (inventory_date, branch_id, item_id)
);

-- Activity Logs Table
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    old_value TEXT,
    new_value TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- System Settings Table
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert Default Admin
INSERT INTO users (username, password, full_name, email, role, status) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@penongs.com', 'admin', 'active');
-- Default password: password

-- Insert Default Branch
INSERT INTO branches (branch_name, branch_code, address, contact_number, status) 
VALUES ('Main Branch', 'MAIN', '123 Main Street', '09123456789', 'active');

-- Insert Default Categories
INSERT INTO categories (category_name, description) VALUES
('Chicken', 'All chicken products'),
('Beef', 'All beef products'),
('Pork', 'All pork products'),
('Seafoods', 'All seafood products'),
('Others', 'Other food items');

-- Insert Sample Items
INSERT INTO items (item_name, category_id, unit, description, status) VALUES
('Chicken Wings', 1, 'kg', 'Fresh chicken wings', 'active'),
('Chicken Breast', 1, 'kg', 'Fresh chicken breast', 'active'),
('Beef Sirloin', 2, 'kg', 'Premium beef sirloin', 'active'),
('Ground Beef', 2, 'kg', 'Ground beef', 'active'),
('Pork Chop', 3, 'kg', 'Fresh pork chop', 'active'),
('Pork Belly', 3, 'kg', 'Fresh pork belly', 'active'),
('Shrimp', 4, 'kg', 'Fresh shrimp', 'active'),
('Fish Fillet', 4, 'kg', 'Fresh fish fillet', 'active');

-- Insert System Settings
INSERT INTO system_settings (setting_key, setting_value) VALUES
('low_stock_threshold', '10'),
('system_name', 'Penongs Daily Food Inventory System');

-- Settings Table (for dynamic settings)
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert Default Settings
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('company_name', 'Penongs'),
('admin_email', 'admin@penongs.com'),
('items_per_page', '20');
