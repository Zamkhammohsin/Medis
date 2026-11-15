-- ============================================================
-- Medis Pharmacy Management System - Database Schema
-- ============================================================

DROP DATABASE IF EXISTS medis_db;
CREATE DATABASE medis_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE medis_db;

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- Table: branches
-- ============================================================
CREATE TABLE branches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  address TEXT,
  phone VARCHAR(20),
  manager_id INT NULL,
  status ENUM('active','inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Table: users
-- ============================================================
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','manager','cashier') NOT NULL,
  branch_id INT NULL,
  status ENUM('active','inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- After users, add FK to branches for manager_id
ALTER TABLE branches ADD CONSTRAINT fk_branch_manager FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL;

-- ============================================================
-- Table: categories
-- ============================================================
CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Table: medicines
-- ============================================================
CREATE TABLE medicines (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  generic_name VARCHAR(150),
  category_id INT,
  manufacturer VARCHAR(150),
  barcode VARCHAR(50) UNIQUE,
  description TEXT,
  purchase_price DECIMAL(10,2) DEFAULT 0,
  selling_price DECIMAL(10,2) DEFAULT 0,
  min_stock_level INT DEFAULT 10,
  units_per_pack INT DEFAULT 1,
  status ENUM('active','inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  INDEX idx_barcode (barcode),
  INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Table: batches
-- ============================================================
CREATE TABLE batches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  medicine_id INT NOT NULL,
  branch_id INT NOT NULL,
  batch_number VARCHAR(50) NOT NULL,
  quantity INT DEFAULT 0,
  purchase_price DECIMAL(10,2) DEFAULT 0,
  expiry_date DATE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
  FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
  INDEX idx_expiry (expiry_date),
  INDEX idx_medicine_branch (medicine_id, branch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Table: suppliers
-- ============================================================
CREATE TABLE suppliers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  contact_person VARCHAR(100),
  phone VARCHAR(20),
  email VARCHAR(100),
  address TEXT,
  company VARCHAR(150),
  status ENUM('active','inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Table: purchases
-- ============================================================
CREATE TABLE purchases (
  id INT AUTO_INCREMENT PRIMARY KEY,
  supplier_id INT,
  branch_id INT NOT NULL,
  user_id INT,
  invoice_number VARCHAR(50) UNIQUE NOT NULL,
  total_amount DECIMAL(12,2) DEFAULT 0,
  status ENUM('pending','received','cancelled') DEFAULT 'pending',
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
  FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Table: purchase_items
-- ============================================================
CREATE TABLE purchase_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  purchase_id INT NOT NULL,
  medicine_id INT NOT NULL,
  batch_number VARCHAR(50),
  quantity INT DEFAULT 0,
  purchase_price DECIMAL(10,2) DEFAULT 0,
  expiry_date DATE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
  FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Table: sales
-- ============================================================
CREATE TABLE sales (
  id INT AUTO_INCREMENT PRIMARY KEY,
  branch_id INT NOT NULL,
  user_id INT,
  invoice_number VARCHAR(50) UNIQUE NOT NULL,
  subtotal DECIMAL(12,2) DEFAULT 0,
  discount DECIMAL(10,2) DEFAULT 0,
  total_amount DECIMAL(12,2) DEFAULT 0,
  payment_method ENUM('cash','card','online') DEFAULT 'cash',
  amount_paid DECIMAL(12,2) DEFAULT 0,
  change_amount DECIMAL(12,2) DEFAULT 0,
  status ENUM('completed','refunded','voided') DEFAULT 'completed',
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_branch_date (branch_id, created_at),
  INDEX idx_invoice (invoice_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Table: sale_items
-- ============================================================
CREATE TABLE sale_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sale_id INT NOT NULL,
  medicine_id INT NOT NULL,
  batch_id INT NOT NULL,
  quantity INT DEFAULT 0,
  unit_price DECIMAL(10,2) DEFAULT 0,
  subtotal DECIMAL(12,2) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
  FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
  FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Table: payments
-- ============================================================
CREATE TABLE payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sale_id INT NOT NULL,
  amount DECIMAL(12,2) DEFAULT 0,
  payment_method ENUM('cash','card','online') DEFAULT 'cash',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Table: stock_movements
-- ============================================================
CREATE TABLE stock_movements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  medicine_id INT NOT NULL,
  branch_id INT NOT NULL,
  batch_id INT NULL,
  type ENUM('purchase','sale','adjustment') NOT NULL,
  quantity INT NOT NULL COMMENT 'positive=in, negative=out',
  reference_id INT NULL,
  reference_type VARCHAR(50) NULL,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
  FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Branches (insert first)
INSERT INTO branches (id, name, address, phone, status) VALUES
(1, 'Main Branch', '123 Mall Road, Lahore', '042-35761234', 'active'),
(2, 'North Branch', '45 Blue Area, Islamabad', '051-28834567', 'active'),
(3, 'South Branch', '78 Clifton, Karachi', '021-35890012', 'active');

-- Users (passwords: Admin@123 / Manager@123 / Cashier@123 - bcrypt hashes)
INSERT INTO users (id, name, email, password, role, branch_id, status) VALUES
(1, 'Super Admin', 'admin@medis.pk', '$2y$12$H.RQxOvZJgHGnvr1Pb6K3uyMB6fJNKvtSjAu3bHFDKDFEzj6NKZXK', 'admin', NULL, 'active'),
(2, 'Ahmed Khan', 'manager.lahore@medis.pk', '$2y$12$H.RQxOvZJgHGnvr1Pb6K3uyMB6fJNKvtSjAu3bHFDKDFEzj6NKZXK', 'manager', 1, 'active'),
(3, 'Sara Ali', 'manager.islamabad@medis.pk', '$2y$12$H.RQxOvZJgHGnvr1Pb6K3uyMB6fJNKvtSjAu3bHFDKDFEzj6NKZXK', 'manager', 2, 'active'),
(4, 'Bilal Hussain', 'cashier1@medis.pk', '$2y$12$H.RQxOvZJgHGnvr1Pb6K3uyMB6fJNKvtSjAu3bHFDKDFEzj6NKZXK', 'cashier', 1, 'active'),
(5, 'Fatima Zahra', 'cashier2@medis.pk', '$2y$12$H.RQxOvZJgHGnvr1Pb6K3uyMB6fJNKvtSjAu3bHFDKDFEzj6NKZXK', 'cashier', 2, 'active'),
(6, 'Usman Malik', 'cashier3@medis.pk', '$2y$12$H.RQxOvZJgHGnvr1Pb6K3uyMB6fJNKvtSjAu3bHFDKDFEzj6NKZXK', 'cashier', 3, 'active');

-- Update branch managers
UPDATE branches SET manager_id = 2 WHERE id = 1;
UPDATE branches SET manager_id = 3 WHERE id = 2;

-- Categories
INSERT INTO categories (id, name, description) VALUES
(1, 'Antibiotics', 'Medicines used to treat bacterial infections'),
(2, 'Analgesics', 'Pain relievers and anti-inflammatory drugs'),
(3, 'Antacids', 'Medicines for acid reflux and stomach issues'),
(4, 'Vitamins & Supplements', 'Vitamins, minerals and nutritional supplements'),
(5, 'Antidiabetics', 'Medicines for managing diabetes and blood sugar'),
(6, 'Cardiovascular', 'Heart and blood pressure medications'),
(7, 'Antihistamines', 'Allergy and antihistamine medications'),
(8, 'Dermatology', 'Skin care and dermatological treatments');

-- Medicines (20 Pakistani pharma brands)
INSERT INTO medicines (id, name, generic_name, category_id, manufacturer, barcode, description, purchase_price, selling_price, min_stock_level, units_per_pack, status) VALUES
(1,  'Amoxil 500mg', 'Amoxicillin 500mg', 1, 'GSK Pakistan', 'PKR-AMX-500', 'Broad-spectrum antibiotic for bacterial infections', 85.00, 110.00, 20, 10, 'active'),
(2,  'Panadol 500mg', 'Paracetamol 500mg', 2, 'GSK Pakistan', 'PKR-PAN-500', 'Common pain reliever and fever reducer', 45.00, 60.00, 20, 10, 'active'),
(3,  'Risek 20mg', 'Omeprazole 20mg', 3, 'Getz Pharma', 'PKR-RSK-020', 'Proton pump inhibitor for acid reflux', 95.00, 125.00, 20, 14, 'active'),
(4,  'Glucophage 500mg', 'Metformin 500mg', 5, 'Merck Pakistan', 'PKR-GLU-500', 'First-line medication for type 2 diabetes', 120.00, 155.00, 20, 10, 'active'),
(5,  'Tenormin 50mg', 'Atenolol 50mg', 6, 'AstraZeneca Pakistan', 'PKR-TEN-050', 'Beta-blocker for hypertension and angina', 75.00, 100.00, 20, 10, 'active'),
(6,  'Zyrtec 10mg', 'Cetirizine 10mg', 7, 'UCB Pakistan', 'PKR-ZYR-010', 'Antihistamine for allergy relief', 55.00, 75.00, 20, 10, 'active'),
(7,  'Zithromax 500mg', 'Azithromycin 500mg', 1, 'Pfizer Pakistan', 'PKR-ZTH-500', 'Macrolide antibiotic for respiratory infections', 180.00, 235.00, 20, 3, 'active'),
(8,  'Brufen 400mg', 'Ibuprofen 400mg', 2, 'Abbott Pakistan', 'PKR-BRF-400', 'NSAID for pain, fever and inflammation', 65.00, 85.00, 20, 10, 'active'),
(9,  'Zantac 150mg', 'Ranitidine 150mg', 3, 'GSK Pakistan', 'PKR-ZNT-150', 'H2 blocker for acid peptic disease', 60.00, 80.00, 20, 10, 'active'),
(10, 'Redoxon 500mg', 'Vitamin C 500mg', 4, 'Bayer Pakistan', 'PKR-RDX-500', 'Effervescent Vitamin C supplement', 40.00, 55.00, 20, 10, 'active'),
(11, 'Vitamin D3 1000IU', 'Cholecalciferol 1000IU', 4, 'Highnoon Laboratories', 'PKR-VD3-001', 'Vitamin D3 supplement for bone health', 50.00, 70.00, 20, 60, 'active'),
(12, 'Canesten Cream', 'Clotrimazole 1%', 8, 'Bayer Pakistan', 'PKR-CNS-001', 'Antifungal cream for skin infections', 110.00, 145.00, 20, 1, 'active'),
(13, 'Flagyl 400mg', 'Metronidazole 400mg', 1, 'Sanofi Pakistan', 'PKR-FLG-400', 'Antibiotic for anaerobic bacterial infections', 70.00, 92.00, 20, 10, 'active'),
(14, 'Disprin 75mg', 'Aspirin 75mg', 6, 'Reckitt Pakistan', 'PKR-DSP-075', 'Low-dose aspirin for cardiovascular protection', 30.00, 42.00, 20, 14, 'active'),
(15, 'Cozaar 50mg', 'Losartan 50mg', 6, 'MSD Pakistan', 'PKR-COZ-050', 'ARB for hypertension and kidney protection', 145.00, 190.00, 20, 14, 'active'),
(16, 'Dexamethasone 4mg', 'Dexamethasone 4mg', 2, 'Ferozsons Laboratories', 'PKR-DEX-004', 'Corticosteroid for inflammation and allergies', 55.00, 75.00, 20, 10, 'active'),
(17, 'Cipro 500mg', 'Ciprofloxacin 500mg', 1, 'Bayer Pakistan', 'PKR-CPR-500', 'Fluoroquinolone antibiotic for UTI and other infections', 95.00, 125.00, 20, 10, 'active'),
(18, 'Pantor 40mg', 'Pantoprazole 40mg', 3, 'Getz Pharma', 'PKR-PNT-040', 'PPI for GERD and peptic ulcer', 100.00, 132.00, 20, 14, 'active'),
(19, 'Calcium 500mg + D3', 'Calcium Carbonate + Vitamin D3', 4, 'Highnoon Laboratories', 'PKR-CAL-D3', 'Calcium and Vitamin D3 supplement', 85.00, 112.00, 20, 60, 'active'),
(20, 'Glibenclamide 5mg', 'Glibenclamide 5mg', 5, 'Sanofi Pakistan', 'PKR-GLB-005', 'Sulfonylurea for type 2 diabetes management', 65.00, 85.00, 20, 20, 'active');

-- Suppliers (5 Pakistani pharma suppliers)
INSERT INTO suppliers (id, name, contact_person, phone, email, address, company, status) VALUES
(1, 'MedSource Pakistan', 'Tariq Mehmood', '0300-4521234', 'tariq@medsource.pk', 'Hall 3, Pharma Market, Lahore', 'MedSource Distributors Pvt. Ltd.', 'active'),
(2, 'PharmaLink Karachi', 'Nadia Siddiqui', '0321-9876543', 'nadia@pharmalink.pk', 'Block B, SITE, Karachi', 'PharmaLink Trading Co.', 'active'),
(3, 'Capital Pharma', 'Zahid Iqbal', '0333-5678901', 'zahid@capitalpharma.pk', 'G-10 Markaz, Islamabad', 'Capital Pharma Distributors', 'active'),
(4, 'GSK Distributor North', 'Asim Raza', '0345-1122334', 'asim@gskdist.pk', 'Gulberg III, Lahore', 'GlaxoSmithKline Official Distributor', 'active'),
(5, 'AlShifa Wholesale', 'Hina Baig', '0311-7654321', 'hina@alshifawholesale.pk', 'MA Jinnah Road, Karachi', 'AlShifa Wholesale Pharmacy', 'active');

-- Batches: each medicine in each branch, varied quantities & expiry dates
-- Medicine 1 (Amoxil 500mg)
INSERT INTO batches (medicine_id, branch_id, batch_number, quantity, purchase_price, expiry_date) VALUES
(1, 1, 'AMX-2024-001', 150, 85.00, '2026-06-30'),
(1, 1, 'AMX-2024-002', 5,   85.00, '2025-03-31'),
(1, 2, 'AMX-2024-003', 120, 85.00, '2026-09-30'),
(1, 3, 'AMX-2024-004', 80,  85.00, '2026-12-31');

-- Medicine 2 (Panadol)
INSERT INTO batches (medicine_id, branch_id, batch_number, quantity, purchase_price, expiry_date) VALUES
(2, 1, 'PAN-2024-001', 200, 45.00, '2027-01-31'),
(2, 1, 'PAN-2024-002', 8,   45.00, '2025-02-28'),
(2, 2, 'PAN-2024-003', 180, 45.00, '2026-11-30'),
(2, 3, 'PAN-2024-004', 150, 45.00, '2026-10-31');

-- Medicine 3 (Risek)
INSERT INTO batches (medicine_id, branch_id, batch_number, quantity, purchase_price, expiry_date) VALUES
(3, 1, 'RSK-2024-001', 100, 95.00, '2026-08-31'),
(3, 2, 'RSK-2024-002', 90,  95.00, '2026-07-31'),
(3, 3, 'RSK-2024-003', 7,   95.00, '2025-04-30');

-- Medicine 4 (Glucophage)
INSERT INTO batches (medicine_id, branch_id, batch_number, quantity, purchase_price, expiry_date) VALUES
(4, 1, 'GLU-2024-001', 130, 120.00, '2026-05-31'),
(4, 2, 'GLU-2024-002', 110, 120.00, '2027-02-28'),
(4, 3, 'GLU-2024-003', 6,   120.00, '2025-01-31');

-- Medicine 5 (Tenormin)
INSERT INTO batches (medicine_id, branch_id, batch_number, quantity, purchase_price, expiry_date) VALUES
(5, 1, 'TEN-2024-001', 90,  75.00, '2026-04-30'),
(5, 2, 'TEN-2024-002', 80,  75.00, '2026-03-31'),
(5, 3, 'TEN-2024-003', 70,  75.00, '2026-06-30');

-- Medicine 6 (Zyrtec)
INSERT INTO batches (medicine_id, branch_id, batch_number, quantity, purchase_price, expiry_date) VALUES
(6, 1, 'ZYR-2024-001', 110, 55.00, '2026-11-30'),
(6, 2, 'ZYR-2024-002', 100, 55.00, '2027-01-31'),
(6, 3, 'ZYR-2024-003', 4,   55.00, '2025-05-31');

-- Medicine 7 (Zithromax)
INSERT INTO batches (medicine_id, branch_id, batch_number, quantity, purchase_price, expiry_date) VALUES
(7, 1, 'ZTH-2024-001', 60,  180.00, '2026-07-31'),
(7, 2, 'ZTH-2024-002', 50,  180.00, '2026-09-30'),
(7, 3, 'ZTH-2024-003', 40,  180.00, '2026-10-31');

-- Medicine 8 (Brufen)
INSERT INTO batches (medicine_id, branch_id, batch_number, quantity, purchase_price, expiry_date) VALUES
(8, 1, 'BRF-2024-001', 140, 65.00, '2026-12-31'),
(8, 2, 'BRF-2024-002', 120, 65.00, '2027-03-31'),
(8, 3, 'BRF-2024-003', 9,   65.00, '2025-06-30');

-- Medicine 9 (Zantac)
INSERT INTO batches (medicine_id, branch_id, batch_number, quantity, purchase_price, expiry_date) VALUES
(9, 1, 'ZNT-2024-001', 100, 60.00, '2026-08-31'),
(9, 2, 'ZNT-2024-002', 90,  60.00, '2026-05-31'),
(9, 3, 'ZNT-2024-003', 80,  60.00, '2026-04-30');

-- Medicine 10 (Redoxon)
INSERT INTO batches (medicine_id, branch_id, batch_number, quantity, purchase_price, expiry_date) VALUES
(10, 1, 'RDX-2024-001', 200, 40.00, '2026-06-30'),
(10, 2, 'RDX-2024-002', 180, 40.00, '2026-09-30'),
(10, 3, 'RDX-2024-003', 7,   40.00, '2025-07-31');

-- Medicine 11 (Vitamin D3)
INSERT INTO batches (medicine_id, branch_id, batch_number, quantity, purchase_price, expiry_date) VALUES
(11, 1, 'VD3-2024-001', 80,  50.00, '2027-01-31'),
(11, 2, 'VD3-2024-002', 70,  50.00, '2026-11-30'),
(11, 3, 'VD3-2024-003', 60,  50.00, '2026-08-31');

-- Medicine 12 (Canesten)
INSERT INTO batches (medicine_id, branch_id, batch_number, quantity, purchase_price, expiry_date) VALUES
(12, 1, 'CNS-2024-001', 50,  110.00, '2026-10-31'),
(12, 2, 'CNS-2024-002', 45,  110.00, '2026-12-31'),
(12, 3, 'CNS-2024-003', 5,   110.00, '2025-08-31');

-- Medicine 13 (Flagyl)
INSERT INTO batches (medicine_id, branch_id, batch_number, quantity, purchase_price, expiry_date) VALUES
(13, 1, 'FLG-2024-001', 120, 70.00, '2026-07-31'),
(13, 2, 'FLG-2024-002', 100, 70.00, '2026-05-31'),
(13, 3, 'FLG-2024-003', 8,   70.00, '2025-09-30');

-- Medicine 14 (Disprin)
INSERT INTO batches (medicine_id, branch_id, batch_number, quantity, purchase_price, expiry_date) VALUES
(14, 1, 'DSP-2024-001', 160, 30.00, '2027-02-28'),
(14, 2, 'DSP-2024-002', 140, 30.00, '2026-11-30'),
(14, 3, 'DSP-2024-003', 130, 30.00, '2026-10-31');

-- Medicine 15 (Cozaar)
INSERT INTO batches (medicine_id, branch_id, batch_number, quantity, purchase_price, expiry_date) VALUES
(15, 1, 'COZ-2024-001', 70,  145.00, '2026-09-30'),
(15, 2, 'COZ-2024-002', 60,  145.00, '2026-06-30'),
(15, 3, 'COZ-2024-003', 6,   145.00, '2025-10-31');

-- Medicine 16 (Dexamethasone)
INSERT INTO batches (medicine_id, branch_id, batch_number, quantity, purchase_price, expiry_date) VALUES
(16, 1, 'DEX-2024-001', 90,  55.00, '2026-04-30'),
(16, 2, 'DEX-2024-002', 80,  55.00, '2026-07-31'),
(16, 3, 'DEX-2024-003', 70,  55.00, '2026-08-31');

-- Medicine 17 (Cipro)
INSERT INTO batches (medicine_id, branch_id, batch_number, quantity, purchase_price, expiry_date) VALUES
(17, 1, 'CPR-2024-001', 100, 95.00, '2026-11-30'),
(17, 2, 'CPR-2024-002', 90,  95.00, '2027-01-31'),
(17, 3, 'CPR-2024-003', 7,   95.00, '2025-11-30');

-- Medicine 18 (Pantor)
INSERT INTO batches (medicine_id, branch_id, batch_number, quantity, purchase_price, expiry_date) VALUES
(18, 1, 'PNT-2024-001', 110, 100.00, '2026-08-31'),
(18, 2, 'PNT-2024-002', 100, 100.00, '2026-06-30'),
(18, 3, 'PNT-2024-003', 90,  100.00, '2026-09-30');

-- Medicine 19 (Calcium + D3)
INSERT INTO batches (medicine_id, branch_id, batch_number, quantity, purchase_price, expiry_date) VALUES
(19, 1, 'CAL-2024-001', 60,  85.00, '2027-03-31'),
(19, 2, 'CAL-2024-002', 55,  85.00, '2027-01-31'),
(19, 3, 'CAL-2024-003', 4,   85.00, '2025-12-31');

-- Medicine 20 (Glibenclamide)
INSERT INTO batches (medicine_id, branch_id, batch_number, quantity, purchase_price, expiry_date) VALUES
(20, 1, 'GLB-2024-001', 80,  65.00, '2026-05-31'),
(20, 2, 'GLB-2024-002', 70,  65.00, '2026-03-31'),
(20, 3, 'GLB-2024-003', 60,  65.00, '2026-04-30');

-- Sample Purchases
INSERT INTO purchases (id, supplier_id, branch_id, user_id, invoice_number, total_amount, status) VALUES
(1, 1, 1, 2, 'PO-2026-0001', 45500.00, 'received'),
(2, 2, 2, 3, 'PO-2026-0002', 38200.00, 'received'),
(3, 3, 3, 1, 'PO-2026-0003', 29800.00, 'received'),
(4, 4, 1, 2, 'PO-2026-0004', 55000.00, 'pending'),
(5, 5, 2, 3, 'PO-2026-0005', 42100.00, 'received');

-- Purchase Items
INSERT INTO purchase_items (purchase_id, medicine_id, batch_number, quantity, purchase_price, expiry_date) VALUES
(1, 1, 'AMX-2024-001', 100, 85.00, '2026-06-30'),
(1, 2, 'PAN-2024-001', 150, 45.00, '2027-01-31'),
(1, 4, 'GLU-2024-001', 80,  120.00, '2026-05-31'),
(2, 5, 'TEN-2024-002', 60,  75.00, '2026-03-31'),
(2, 6, 'ZYR-2024-002', 80,  55.00, '2027-01-31'),
(2, 7, 'ZTH-2024-002', 40,  180.00, '2026-09-30'),
(3, 10, 'RDX-2024-003', 100, 40.00, '2026-09-30'),
(3, 11, 'VD3-2024-003', 50,  50.00, '2026-08-31'),
(4, 15, 'COZ-2024-001', 50,  145.00, '2026-09-30'),
(5, 18, 'PNT-2024-002', 70,  100.00, '2026-06-30');

-- Sample Sales (today and recent)
INSERT INTO sales (id, branch_id, user_id, invoice_number, subtotal, discount, total_amount, payment_method, amount_paid, change_amount, status) VALUES
(1, 1, 4, 'INV-2026-0001', 1250.00, 50.00, 1200.00, 'cash', 1500.00, 300.00, 'completed'),
(2, 1, 4, 'INV-2026-0002', 850.00, 0.00, 850.00, 'card', 850.00, 0.00, 'completed'),
(3, 2, 5, 'INV-2026-0003', 2100.00, 100.00, 2000.00, 'cash', 2000.00, 0.00, 'completed'),
(4, 1, 4, 'INV-2026-0004', 450.00, 0.00, 450.00, 'online', 450.00, 0.00, 'completed'),
(5, 3, 6, 'INV-2026-0005', 1800.00, 90.00, 1710.00, 'cash', 2000.00, 290.00, 'completed');

-- Sale Items
INSERT INTO sale_items (sale_id, medicine_id, batch_id, quantity, unit_price, subtotal) VALUES
(1, 1, 1, 5, 110.00, 550.00),
(1, 2, 5, 10, 60.00, 600.00),
(1, 3, 9, 1, 125.00, 125.00),
(2, 7, 23, 2, 235.00, 470.00),
(2, 10, 37, 7, 55.00, 385.00),
(3, 4, 13, 10, 155.00, 1550.00),
(3, 5, 17, 7, 100.00, 700.00),
(4, 6, 21, 6, 75.00, 450.00),
(5, 15, 55, 3, 190.00, 570.00),
(5, 18, 61, 10, 132.00, 1320.00);

-- Payments
INSERT INTO payments (sale_id, amount, payment_method) VALUES
(1, 1200.00, 'cash'),
(2, 850.00, 'card'),
(3, 2000.00, 'cash'),
(4, 450.00, 'online'),
(5, 1710.00, 'cash');

-- Stock Movements
INSERT INTO stock_movements (medicine_id, branch_id, batch_id, type, quantity, reference_id, reference_type, notes) VALUES
(1, 1, 1, 'purchase', 100, 1, 'purchase', 'Initial stock purchase'),
(2, 1, 5, 'purchase', 150, 1, 'purchase', 'Initial stock purchase'),
(4, 1, 13, 'purchase', 80, 1, 'purchase', 'Initial stock purchase'),
(1, 1, 1, 'sale', -5, 1, 'sale', 'Sale transaction INV-2026-0001'),
(2, 1, 5, 'sale', -10, 1, 'sale', 'Sale transaction INV-2026-0001'),
(3, 1, 9, 'sale', -1, 1, 'sale', 'Sale transaction INV-2026-0001'),
(7, 2, 23, 'sale', -2, 2, 'sale', 'Sale transaction INV-2026-0002'),
(4, 2, 13, 'sale', -10, 3, 'sale', 'Sale transaction INV-2026-0003');
