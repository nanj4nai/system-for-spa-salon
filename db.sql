-- ======================================
-- Wellness Spa Management System
-- Fresh Schema (Users + Employees Split)
-- ======================================

-- =====================
-- STAFF ROLES (JOB TITLES)
-- =====================
CREATE TABLE staff_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================
-- EMPLOYEES (REAL PEOPLE)
-- =====================
CREATE TABLE employees (
    id INT AUTO_INCREMENT PRIMARY KEY,

    staff_role_id INT NOT NULL,

    full_name VARCHAR(150) NOT NULL,
    contact_number VARCHAR(50),
    email VARCHAR(100),
    address TEXT,

    hire_date DATE,
    is_active TINYINT DEFAULT 1,
    inactive_remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (staff_role_id)
        REFERENCES staff_roles(id)
        ON DELETE RESTRICT
);

-- =====================
-- USERS (SYSTEM ACCESS)
-- =====================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,

    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,

    -- SYSTEM PERMISSION ROLE
    role ENUM('admin', 'cashier') DEFAULT 'cashier',

    -- OPTIONAL LINK TO EMPLOYEE
    employee_id INT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (employee_id)
        REFERENCES employees(id)
        ON DELETE SET NULL
);

-- =====================
-- CLIENTS
-- =====================
CREATE TABLE clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    contact_number VARCHAR(50),
    email VARCHAR(100),
    address VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================
-- SERVICE CATEGORIES
-- =====================
CREATE TABLE service_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================
-- SERVICES
-- =====================
CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    category_id INT,
    base_price DECIMAL(10,2) NOT NULL,
    default_commission_percent DECIMAL(5,2) DEFAULT 0.00,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (category_id)
        REFERENCES service_categories(id)
);

-- =====================
-- SERVICE VARIANTS
-- =====================
CREATE TABLE service_variants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    duration_minutes INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (service_id)
        REFERENCES services(id)
        ON DELETE CASCADE
);

-- =====================
-- PRODUCTS
-- =====================
CREATE TABLE product_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    category_id INT NULL,

    -- stock now means "remaining usable unit"
    stock DECIMAL(10,2) DEFAULT 0,

    price DECIMAL(10,2) NOT NULL,
    image VARCHAR(255),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (category_id)
        REFERENCES product_categories(id)
        ON DELETE SET NULL
);

-- =====================
-- SERVICE ↔ PRODUCTS
-- =====================
CREATE TABLE service_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    product_id INT NOT NULL,

    -- amount used per service
    quantity DECIMAL(10,2) NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (service_id)
        REFERENCES services(id)
        ON DELETE CASCADE,

    FOREIGN KEY (product_id)
        REFERENCES products(id)
        ON DELETE CASCADE,

    UNIQUE (service_id, product_id)
);


-- =====================
-- STAFF COMMISSIONS
-- =====================
CREATE TABLE staff_commissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    service_id INT NOT NULL,
    commission_percent DECIMAL(5,2) NOT NULL,

    FOREIGN KEY (employee_id)
        REFERENCES employees(id),

    FOREIGN KEY (service_id)
        REFERENCES services(id),

    UNIQUE (employee_id, service_id)
);

-- =====================
-- APPOINTMENTS
-- =====================
CREATE TABLE appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,

    status ENUM('pending','confirmed','completed','cancelled','no_show')
        DEFAULT 'pending',

    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (client_id)
        REFERENCES clients(id)
);

CREATE TABLE appointment_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    service_id INT NOT NULL,
    employee_id INT NOT NULL,
    variant_id INT NULL,

    FOREIGN KEY (appointment_id)
        REFERENCES appointments(id)
        ON DELETE CASCADE,

    FOREIGN KEY (service_id)
        REFERENCES services(id),

    FOREIGN KEY (employee_id)
        REFERENCES employees(id),

    FOREIGN KEY (variant_id)
        REFERENCES service_variants(id)
);

-- =====================
-- TRANSACTIONS
-- =====================
CREATE TABLE spa_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_number VARCHAR(50) UNIQUE,
    client_id INT NOT NULL,
    total_amount DECIMAL(10,2) DEFAULT 0.00,
    discount DECIMAL(10,2) DEFAULT 0.00,
    amount_paid DECIMAL(10,2) DEFAULT 0.00,
    balance DECIMAL(10,2) DEFAULT 0.00,
    payment_status ENUM('unpaid','partial','paid') DEFAULT 'unpaid',
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (client_id)
        REFERENCES clients(id)
);

CREATE TABLE spa_transaction_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    service_id INT NOT NULL,
    employee_id INT NOT NULL,
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    commission_amount DECIMAL(10,2) DEFAULT 0.00,

    FOREIGN KEY (transaction_id)
        REFERENCES spa_transactions(id)
        ON DELETE CASCADE,

    FOREIGN KEY (service_id)
        REFERENCES services(id),

    FOREIGN KEY (employee_id)
        REFERENCES employees(id)
);

-- =====================
-- PRODUCT SALES
-- =====================
CREATE TABLE product_sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,

    FOREIGN KEY (transaction_id)
        REFERENCES spa_transactions(id)
        ON DELETE SET NULL,

    FOREIGN KEY (product_id)
        REFERENCES products(id)
);

-- =====================
-- PAYMENTS
-- =====================
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash','gcash','card','other') DEFAULT 'cash',
    receipt_number VARCHAR(50) UNIQUE,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (transaction_id)
        REFERENCES spa_transactions(id)
        ON DELETE CASCADE
);

-- =====================
-- ACTIVITY LOGS
-- =====================
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id)
        REFERENCES users(id)
);

-- =====================
-- SETTINGS
-- =====================
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    spa_name VARCHAR(150) DEFAULT 'My Wellness Spa',
    address VARCHAR(255),
    contact_number VARCHAR(100),
    invoice_prefix VARCHAR(10) DEFAULT 'SPA',
    vat_rate DECIMAL(5,2) DEFAULT 12.00,
    logo_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


-- updates
ALTER TABLE products
ADD COLUMN product_type ENUM('consumable', 'reusable', 'one_time') NOT NULL DEFAULT 'consumable',
ADD COLUMN unit ENUM('ml', 'mg', 'pcs') NOT NULL DEFAULT 'pcs',
ADD COLUMN unit_per_item INT DEFAULT NULL;

ALTER TABLE service_products
MODIFY quantity DECIMAL(10,2) NOT NULL;


-- updatess 
CREATE TABLE cashier_shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,          -- cashier (users table)
    opened_at DATETIME NOT NULL,
    closed_at DATETIME NULL,

    opening_cash DECIMAL(10,2) DEFAULT 0.00,
    closing_cash DECIMAL(10,2) DEFAULT 0.00,

    status ENUM('open','closed') DEFAULT 'open',

    remarks TEXT,

    FOREIGN KEY (user_id)
        REFERENCES users(id)
);

-- Link Transactions to Shift
ALTER TABLE spa_transactions
ADD COLUMN shift_id INT NULL,
ADD FOREIGN KEY (shift_id)
    REFERENCES cashier_shifts(id)
    ON DELETE SET NULL;

-- Appointment → Transaction Bridge
ALTER TABLE spa_transactions
ADD COLUMN appointment_id INT NULL,
ADD FOREIGN KEY (appointment_id)
    REFERENCES appointments(id)
    ON DELETE SET NULL;

-- Ensure only one open shift per user
ALTER TABLE cashier_shifts
ADD COLUMN is_open TINYINT GENERATED ALWAYS AS (status = 'open') STORED,
ADD UNIQUE KEY uniq_one_open_shift (is_open);




-- update to log the check-in time and staff
ALTER TABLE appointments
ADD checked_in_at DATETIME NULL,
ADD checked_in_by INT NULL;
-- Update status enum
ALTER TABLE appointments
MODIFY status ENUM(
  'pending',
  'confirmed',
  'checked_in',
  'completed',
  'no_show',
  'cancelled'
) NOT NULL DEFAULT 'pending';
-- Add status update tracking
ALTER TABLE appointments
ADD COLUMN status_updated_at DATETIME NULL,
ADD COLUMN status_updated_by INT NULL;
-- Transaction Type
ALTER TABLE spa_transactions
ADD transaction_type ENUM(
  'booking_payment',
  'walkin',
  'adjustment'
) NOT NULL DEFAULT 'walkin';
-- Source of Appointment
ALTER TABLE appointments
ADD COLUMN source ENUM('online','admin','cashier') NOT NULL DEFAULT 'online';



-- actual usage of products per appointment service
CREATE TABLE appointment_service_products (
    id INT AUTO_INCREMENT PRIMARY KEY,

    appointment_service_id INT NOT NULL,
    product_id INT NOT NULL,

    quantity_used DECIMAL(10,2) NOT NULL,
    unit ENUM('ml','mg','pcs') NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (appointment_service_id)
        REFERENCES appointment_services(id)
        ON DELETE CASCADE,

    FOREIGN KEY (product_id)
        REFERENCES products(id)
);
-- quantity for appointment services 
ALTER TABLE appointment_services
ADD COLUMN quantity INT NOT NULL DEFAULT 1;
-- link transaction services to appointment services
ALTER TABLE spa_transaction_services
ADD COLUMN appointment_service_id INT NULL,
ADD INDEX (appointment_service_id);
-- APPOINTMENT EXTRA PRODUCTS
CREATE TABLE appointment_extra_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    unit VARCHAR(20),
    unit_price DECIMAL(10,2),
    total_price DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (appointment_id) REFERENCES appointments(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);
-- status for transactions
ALTER TABLE spa_transactions
ADD COLUMN status ENUM('editing','locked','paid','cancelled')
DEFAULT 'editing';


