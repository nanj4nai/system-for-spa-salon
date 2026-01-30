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

    -- cashier user
    user_id INT NOT NULL,

    -- shift timing
    opened_at DATETIME NOT NULL,
    closed_at DATETIME NULL,

    -- cash tracking
    opening_cash DECIMAL(10,2) DEFAULT 0.00,
    closing_cash DECIMAL(10,2) DEFAULT 0.00,

    -- shift lifecycle
    status ENUM(
        'open',
        'pending_close',
        'closed'
    ) DEFAULT 'open',

    -- approval workflow
    approval_status ENUM(
        'pending',
        'approved',
        'rejected'
    ) DEFAULT 'approved',

    approved_by INT NULL,
    approved_at DATETIME NULL,

    remarks TEXT,

    -- GENERATED: only one active shift per cashier
    is_active TINYINT
        GENERATED ALWAYS AS (
            status IN ('open', 'pending_close')
        ) STORED,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- relations
    CONSTRAINT fk_cashier_shift_user
        FOREIGN KEY (user_id)
        REFERENCES users(id),

    -- enforce single active shift per cashier
    UNIQUE KEY uniq_one_active_shift_per_user (user_id, is_active)
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


ALTER TABLE cashier_shifts
ADD COLUMN active_user_id INT(11) NULL AFTER is_active;
CREATE INDEX idx_cashier_shifts_active_user
ON cashier_shifts(active_user_id);

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

ALTER TABLE payments
ADD COLUMN source ENUM('online', 'cashier')
NOT NULL DEFAULT 'cashier'
AFTER payment_method;


ALTER TABLE spa_transactions
ADD COLUMN subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER has_receivable,
ADD COLUMN vat_rate DECIMAL(5,2)  NOT NULL DEFAULT 0.00 AFTER subtotal,
ADD COLUMN vat_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER vat_rate;


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
MODIFY status ENUM(
  'pending_verification',
  'editing',
  'finalized',
  'cancelled'
) NOT NULL DEFAULT 'editing';

-- include vat flag
ALTER TABLE spa_transactions
ADD include_vat TINYINT(1) DEFAULT 1;



-- payment method update
ALTER TABLE payments
MODIFY payment_method ENUM(
  'cash',
  'gcash',
  'card',
  'other',
  'receivable'
) DEFAULT 'cash';
-- add is_receivable flag
ALTER TABLE spa_transactions
ADD COLUMN is_receivable TINYINT(1) DEFAULT 0;
-- approval fields for cashier shifts
ALTER TABLE cashier_shifts
ADD COLUMN approval_status ENUM(
  'pending',
  'approved',
  'rejected'
) DEFAULT 'approved',
ADD COLUMN approved_by INT NULL,
ADD COLUMN approved_at DATETIME NULL;
-- modify status enum
ALTER TABLE cashier_shifts
MODIFY status ENUM(
  'pending_open',
  'open',
  'pending_close',
  'closed'
) DEFAULT 'open';

--
ALTER TABLE appointments
ADD COLUMN payment_reference VARCHAR(100) NULL,
ADD COLUMN payment_proof VARCHAR(255) NULL,
ADD COLUMN payment_verified TINYINT DEFAULT 0,
ADD COLUMN payment_verified_by INT NULL,
ADD COLUMN payment_verified_at DATETIME NULL;
--
ALTER TABLE spa_transactions
ADD balance_due DECIMAL(10,2) NOT NULL DEFAULT 0;
--
ALTER TABLE spa_transactions
ADD has_receivable TINYINT(1) NOT NULL DEFAULT 0;


--
CREATE TABLE accounts_receivable (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    transaction_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    balance DECIMAL(10,2) NOT NULL,
    status ENUM('open','paid') DEFAULT 'open',
    remarks TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (transaction_id) REFERENCES spa_transactions(id)
);
--
CREATE TABLE ar_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receivable_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    remarks TEXT,

    FOREIGN KEY (receivable_id) REFERENCES accounts_receivable(id)
);
ALTER TABLE accounts_receivable
ADD UNIQUE KEY uniq_ar_transaction (transaction_id);

ALTER TABLE payments
ADD UNIQUE KEY uniq_receipt_number (receipt_number);
ALTER TABLE payments
ADD COLUMN remarks TEXT NULL AFTER receipt_number;
ALTER TABLE payments
ADD COLUMN reference_number VARCHAR(100) NULL;


ALTER TABLE spa_transactions
ADD UNIQUE KEY uniq_transaction_per_appointment (appointment_id);

CREATE TABLE booking_price_snapshots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,

    subtotal DECIMAL(10,2),
    discount DECIMAL(10,2),
    vat DECIMAL(10,2),
    total DECIMAL(10,2),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (appointment_id)
        REFERENCES appointments(id)
        ON DELETE CASCADE
);
ALTER TABLE appointment_services
MODIFY employee_id INT NULL;

ALTER TABLE spa_transaction_services
MODIFY employee_id INT NULL;

ALTER TABLE settings
ADD COLUMN gcash_number VARCHAR(50) NULL,
ADD COLUMN gcash_qr_path VARCHAR(255) NULL;
ALTER TABLE settings
ADD COLUMN email VARCHAR(255) AFTER contact_number;
ALTER TABLE appointments
ADD COLUMN payment_rejection_reason TEXT NULL,
ADD COLUMN payment_rejected_at DATETIME NULL,
ADD COLUMN payment_rejected_by INT NULL;

CREATE INDEX idx_clients_email
ON clients (email);

-- can get error if two clients have same contact number or null
CREATE UNIQUE INDEX uq_clients_contact
ON clients (contact_number);

ALTER TABLE appointments
ADD last_email_sent_at DATETIME NULL,
ADD last_email_type ENUM('approved','rejected') NULL;

ALTER TABLE spa_transactions
ADD UNIQUE KEY uniq_appointment_transaction (appointment_id);


ALTER TABLE cashier_shifts ADD COLUMN opened_by INT NULL AFTER user_id, ADD FOREIGN KEY (opened_by) REFERENCES users(id);
ALTER TABLE accounts_receivable
ADD COLUMN ar_type ENUM('pay_later', 'online_tracking') DEFAULT 'pay_later';

