<?php
/**
 * Medis - Database Seeder Script
 * Run via browser: http://localhost/Medis/database/seed.php
 * Generates real bcrypt hashes and inserts all seed data.
 */

$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'medis_db';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("<h2 style='color:red'>DB Connection Failed: " . htmlspecialchars($e->getMessage()) . "</h2>
         <p>Make sure medis_db exists. Import <code>database.sql</code> first.</p>");
}

$log = [];

function runSQL($pdo, $sql, $params = [], &$log = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return true;
    } catch (PDOException $e) {
        $log[] = "<span style='color:red'>ERROR: " . htmlspecialchars($e->getMessage()) . "</span>";
        return false;
    }
}

// Generate real bcrypt hashes
$adminHash   = password_hash('Admin@123',   PASSWORD_BCRYPT, ['cost' => 12]);
$managerHash = password_hash('Manager@123', PASSWORD_BCRYPT, ['cost' => 12]);
$cashierHash = password_hash('Cashier@123', PASSWORD_BCRYPT, ['cost' => 12]);

$log[] = "Generated bcrypt hashes for Admin@123, Manager@123, Cashier@123";

// Clear existing data (order matters for FK)
$pdo->exec("SET FOREIGN_KEY_CHECKS=0");
foreach (['stock_movements','payments','sale_items','sales','purchase_items','purchases','batches','medicines','categories','suppliers','users','branches'] as $t) {
    $pdo->exec("TRUNCATE TABLE `$t`");
    $log[] = "Truncated table: $t";
}
$pdo->exec("SET FOREIGN_KEY_CHECKS=1");

// Branches
$branches = [
    [1, 'Main Branch',  '123 Mall Road, Lahore',     '042-35761234', null, 'active'],
    [2, 'North Branch', '45 Blue Area, Islamabad',   '051-28834567', null, 'active'],
    [3, 'South Branch', '78 Clifton, Karachi',        '021-35890012', null, 'active'],
];
foreach ($branches as $b) {
    runSQL($pdo, "INSERT INTO branches (id,name,address,phone,manager_id,status) VALUES (?,?,?,?,?,?)", $b, $log);
}
$log[] = "✔ Inserted " . count($branches) . " branches";

// Users
$users = [
    [1, 'Super Admin',    'admin@medis.pk',              $adminHash,   'admin',   null, 'active'],
    [2, 'Ahmed Khan',     'manager.lahore@medis.pk',     $managerHash, 'manager', 1,    'active'],
    [3, 'Sara Ali',       'manager.islamabad@medis.pk',  $managerHash, 'manager', 2,    'active'],
    [4, 'Bilal Hussain',  'cashier1@medis.pk',           $cashierHash, 'cashier', 1,    'active'],
    [5, 'Fatima Zahra',   'cashier2@medis.pk',           $cashierHash, 'cashier', 2,    'active'],
    [6, 'Usman Malik',    'cashier3@medis.pk',           $cashierHash, 'cashier', 3,    'active'],
];
foreach ($users as $u) {
    runSQL($pdo, "INSERT INTO users (id,name,email,password,role,branch_id,status) VALUES (?,?,?,?,?,?,?)", $u, $log);
}
$log[] = "✔ Inserted " . count($users) . " users";

// Update branch managers
runSQL($pdo, "UPDATE branches SET manager_id=2 WHERE id=1", [], $log);
runSQL($pdo, "UPDATE branches SET manager_id=3 WHERE id=2", [], $log);
$log[] = "✔ Updated branch managers";

// Categories
$categories = [
    [1, 'Antibiotics',           'Medicines used to treat bacterial infections'],
    [2, 'Analgesics',            'Pain relievers and anti-inflammatory drugs'],
    [3, 'Antacids',              'Medicines for acid reflux and stomach issues'],
    [4, 'Vitamins & Supplements','Vitamins, minerals and nutritional supplements'],
    [5, 'Antidiabetics',         'Medicines for managing diabetes and blood sugar'],
    [6, 'Cardiovascular',        'Heart and blood pressure medications'],
    [7, 'Antihistamines',        'Allergy and antihistamine medications'],
    [8, 'Dermatology',           'Skin care and dermatological treatments'],
];
foreach ($categories as $c) {
    runSQL($pdo, "INSERT INTO categories (id,name,description) VALUES (?,?,?)", $c, $log);
}
$log[] = "✔ Inserted " . count($categories) . " categories";

// Medicines
$medicines = [
    [1,  'Amoxil 500mg',        'Amoxicillin 500mg',               1, 'GSK Pakistan',              'PKR-AMX-500', 'Broad-spectrum antibiotic for bacterial infections',   85.00,  110.00, 20, 10, 'active'],
    [2,  'Panadol 500mg',       'Paracetamol 500mg',               2, 'GSK Pakistan',              'PKR-PAN-500', 'Common pain reliever and fever reducer',               45.00,   60.00, 20, 10, 'active'],
    [3,  'Risek 20mg',          'Omeprazole 20mg',                 3, 'Getz Pharma',               'PKR-RSK-020', 'Proton pump inhibitor for acid reflux',                95.00,  125.00, 20, 14, 'active'],
    [4,  'Glucophage 500mg',    'Metformin 500mg',                 5, 'Merck Pakistan',            'PKR-GLU-500', 'First-line medication for type 2 diabetes',           120.00,  155.00, 20, 10, 'active'],
    [5,  'Tenormin 50mg',       'Atenolol 50mg',                   6, 'AstraZeneca Pakistan',      'PKR-TEN-050', 'Beta-blocker for hypertension and angina',             75.00,  100.00, 20, 10, 'active'],
    [6,  'Zyrtec 10mg',         'Cetirizine 10mg',                 7, 'UCB Pakistan',              'PKR-ZYR-010', 'Antihistamine for allergy relief',                     55.00,   75.00, 20, 10, 'active'],
    [7,  'Zithromax 500mg',     'Azithromycin 500mg',              1, 'Pfizer Pakistan',           'PKR-ZTH-500', 'Macrolide antibiotic for respiratory infections',     180.00,  235.00, 20,  3, 'active'],
    [8,  'Brufen 400mg',        'Ibuprofen 400mg',                 2, 'Abbott Pakistan',           'PKR-BRF-400', 'NSAID for pain, fever and inflammation',               65.00,   85.00, 20, 10, 'active'],
    [9,  'Zantac 150mg',        'Ranitidine 150mg',                3, 'GSK Pakistan',              'PKR-ZNT-150', 'H2 blocker for acid peptic disease',                   60.00,   80.00, 20, 10, 'active'],
    [10, 'Redoxon 500mg',       'Vitamin C 500mg',                 4, 'Bayer Pakistan',            'PKR-RDX-500', 'Effervescent Vitamin C supplement',                    40.00,   55.00, 20, 10, 'active'],
    [11, 'Vitamin D3 1000IU',   'Cholecalciferol 1000IU',          4, 'Highnoon Laboratories',     'PKR-VD3-001', 'Vitamin D3 supplement for bone health',                50.00,   70.00, 20, 60, 'active'],
    [12, 'Canesten Cream',      'Clotrimazole 1%',                 8, 'Bayer Pakistan',            'PKR-CNS-001', 'Antifungal cream for skin infections',                110.00,  145.00, 20,  1, 'active'],
    [13, 'Flagyl 400mg',        'Metronidazole 400mg',             1, 'Sanofi Pakistan',           'PKR-FLG-400', 'Antibiotic for anaerobic bacterial infections',        70.00,   92.00, 20, 10, 'active'],
    [14, 'Disprin 75mg',        'Aspirin 75mg',                    6, 'Reckitt Pakistan',          'PKR-DSP-075', 'Low-dose aspirin for cardiovascular protection',       30.00,   42.00, 20, 14, 'active'],
    [15, 'Cozaar 50mg',         'Losartan 50mg',                   6, 'MSD Pakistan',              'PKR-COZ-050', 'ARB for hypertension and kidney protection',          145.00,  190.00, 20, 14, 'active'],
    [16, 'Dexamethasone 4mg',   'Dexamethasone 4mg',               2, 'Ferozsons Laboratories',    'PKR-DEX-004', 'Corticosteroid for inflammation and allergies',        55.00,   75.00, 20, 10, 'active'],
    [17, 'Cipro 500mg',         'Ciprofloxacin 500mg',             1, 'Bayer Pakistan',            'PKR-CPR-500', 'Fluoroquinolone antibiotic for UTI and other infections', 95.00, 125.00, 20, 10, 'active'],
    [18, 'Pantor 40mg',         'Pantoprazole 40mg',               3, 'Getz Pharma',               'PKR-PNT-040', 'PPI for GERD and peptic ulcer',                      100.00,  132.00, 20, 14, 'active'],
    [19, 'Calcium 500mg + D3',  'Calcium Carbonate + Vitamin D3',  4, 'Highnoon Laboratories',     'PKR-CAL-D3',  'Calcium and Vitamin D3 supplement',                    85.00,  112.00, 20, 60, 'active'],
    [20, 'Glibenclamide 5mg',   'Glibenclamide 5mg',               5, 'Sanofi Pakistan',           'PKR-GLB-005', 'Sulfonylurea for type 2 diabetes management',          65.00,   85.00, 20, 20, 'active'],
];
foreach ($medicines as $m) {
    runSQL($pdo, "INSERT INTO medicines (id,name,generic_name,category_id,manufacturer,barcode,description,purchase_price,selling_price,min_stock_level,units_per_pack,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)", $m, $log);
}
$log[] = "✔ Inserted " . count($medicines) . " medicines";

// Suppliers
$suppliers = [
    [1, 'MedSource Pakistan',     'Tariq Mehmood',  '0300-4521234', 'tariq@medsource.pk',      'Hall 3, Pharma Market, Lahore', 'MedSource Distributors Pvt. Ltd.', 'active'],
    [2, 'PharmaLink Karachi',     'Nadia Siddiqui', '0321-9876543', 'nadia@pharmalink.pk',     'Block B, SITE, Karachi',        'PharmaLink Trading Co.',           'active'],
    [3, 'Capital Pharma',         'Zahid Iqbal',    '0333-5678901', 'zahid@capitalpharma.pk',  'G-10 Markaz, Islamabad',        'Capital Pharma Distributors',      'active'],
    [4, 'GSK Distributor North',  'Asim Raza',      '0345-1122334', 'asim@gskdist.pk',         'Gulberg III, Lahore',           'GlaxoSmithKline Official Distributor', 'active'],
    [5, 'AlShifa Wholesale',      'Hina Baig',      '0311-7654321', 'hina@alshifawholesale.pk','MA Jinnah Road, Karachi',       'AlShifa Wholesale Pharmacy',       'active'],
];
foreach ($suppliers as $s) {
    runSQL($pdo, "INSERT INTO suppliers (id,name,contact_person,phone,email,address,company,status) VALUES (?,?,?,?,?,?,?,?)", $s, $log);
}
$log[] = "✔ Inserted " . count($suppliers) . " suppliers";

// Batches
$batches = [
    // Medicine 1 - Amoxil
    [1, 1, 'AMX-2024-001', 150, 85.00, '2026-06-30'],
    [1, 1, 'AMX-2024-002', 5,   85.00, '2025-03-31'],
    [1, 2, 'AMX-2024-003', 120, 85.00, '2026-09-30'],
    [1, 3, 'AMX-2024-004', 80,  85.00, '2026-12-31'],
    // Medicine 2 - Panadol
    [2, 1, 'PAN-2024-001', 200, 45.00, '2027-01-31'],
    [2, 1, 'PAN-2024-002', 8,   45.00, '2025-02-28'],
    [2, 2, 'PAN-2024-003', 180, 45.00, '2026-11-30'],
    [2, 3, 'PAN-2024-004', 150, 45.00, '2026-10-31'],
    // Medicine 3 - Risek
    [3, 1, 'RSK-2024-001', 100, 95.00, '2026-08-31'],
    [3, 2, 'RSK-2024-002', 90,  95.00, '2026-07-31'],
    [3, 3, 'RSK-2024-003', 7,   95.00, '2025-04-30'],
    // Medicine 4 - Glucophage
    [4, 1, 'GLU-2024-001', 130, 120.00, '2026-05-31'],
    [4, 2, 'GLU-2024-002', 110, 120.00, '2027-02-28'],
    [4, 3, 'GLU-2024-003', 6,   120.00, '2025-01-31'],
    // Medicine 5 - Tenormin
    [5, 1, 'TEN-2024-001', 90,  75.00, '2026-04-30'],
    [5, 2, 'TEN-2024-002', 80,  75.00, '2026-03-31'],
    [5, 3, 'TEN-2024-003', 70,  75.00, '2026-06-30'],
    // Medicine 6 - Zyrtec
    [6, 1, 'ZYR-2024-001', 110, 55.00, '2026-11-30'],
    [6, 2, 'ZYR-2024-002', 100, 55.00, '2027-01-31'],
    [6, 3, 'ZYR-2024-003', 4,   55.00, '2025-05-31'],
    // Medicine 7 - Zithromax
    [7, 1, 'ZTH-2024-001', 60,  180.00, '2026-07-31'],
    [7, 2, 'ZTH-2024-002', 50,  180.00, '2026-09-30'],
    [7, 3, 'ZTH-2024-003', 40,  180.00, '2026-10-31'],
    // Medicine 8 - Brufen
    [8, 1, 'BRF-2024-001', 140, 65.00, '2026-12-31'],
    [8, 2, 'BRF-2024-002', 120, 65.00, '2027-03-31'],
    [8, 3, 'BRF-2024-003', 9,   65.00, '2025-06-30'],
    // Medicine 9 - Zantac
    [9, 1, 'ZNT-2024-001', 100, 60.00, '2026-08-31'],
    [9, 2, 'ZNT-2024-002', 90,  60.00, '2026-05-31'],
    [9, 3, 'ZNT-2024-003', 80,  60.00, '2026-04-30'],
    // Medicine 10 - Redoxon
    [10, 1, 'RDX-2024-001', 200, 40.00, '2026-06-30'],
    [10, 2, 'RDX-2024-002', 180, 40.00, '2026-09-30'],
    [10, 3, 'RDX-2024-003', 7,   40.00, '2025-07-31'],
    // Medicine 11 - Vitamin D3
    [11, 1, 'VD3-2024-001', 80,  50.00, '2027-01-31'],
    [11, 2, 'VD3-2024-002', 70,  50.00, '2026-11-30'],
    [11, 3, 'VD3-2024-003', 60,  50.00, '2026-08-31'],
    // Medicine 12 - Canesten
    [12, 1, 'CNS-2024-001', 50,  110.00, '2026-10-31'],
    [12, 2, 'CNS-2024-002', 45,  110.00, '2026-12-31'],
    [12, 3, 'CNS-2024-003', 5,   110.00, '2025-08-31'],
    // Medicine 13 - Flagyl
    [13, 1, 'FLG-2024-001', 120, 70.00, '2026-07-31'],
    [13, 2, 'FLG-2024-002', 100, 70.00, '2026-05-31'],
    [13, 3, 'FLG-2024-003', 8,   70.00, '2025-09-30'],
    // Medicine 14 - Disprin
    [14, 1, 'DSP-2024-001', 160, 30.00, '2027-02-28'],
    [14, 2, 'DSP-2024-002', 140, 30.00, '2026-11-30'],
    [14, 3, 'DSP-2024-003', 130, 30.00, '2026-10-31'],
    // Medicine 15 - Cozaar
    [15, 1, 'COZ-2024-001', 70,  145.00, '2026-09-30'],
    [15, 2, 'COZ-2024-002', 60,  145.00, '2026-06-30'],
    [15, 3, 'COZ-2024-003', 6,   145.00, '2025-10-31'],
    // Medicine 16 - Dexamethasone
    [16, 1, 'DEX-2024-001', 90,  55.00, '2026-04-30'],
    [16, 2, 'DEX-2024-002', 80,  55.00, '2026-07-31'],
    [16, 3, 'DEX-2024-003', 70,  55.00, '2026-08-31'],
    // Medicine 17 - Cipro
    [17, 1, 'CPR-2024-001', 100, 95.00, '2026-11-30'],
    [17, 2, 'CPR-2024-002', 90,  95.00, '2027-01-31'],
    [17, 3, 'CPR-2024-003', 7,   95.00, '2025-11-30'],
    // Medicine 18 - Pantor
    [18, 1, 'PNT-2024-001', 110, 100.00, '2026-08-31'],
    [18, 2, 'PNT-2024-002', 100, 100.00, '2026-06-30'],
    [18, 3, 'PNT-2024-003', 90,  100.00, '2026-09-30'],
    // Medicine 19 - Calcium+D3
    [19, 1, 'CAL-2024-001', 60,  85.00, '2027-03-31'],
    [19, 2, 'CAL-2024-002', 55,  85.00, '2027-01-31'],
    [19, 3, 'CAL-2024-003', 4,   85.00, '2025-12-31'],
    // Medicine 20 - Glibenclamide
    [20, 1, 'GLB-2024-001', 80,  65.00, '2026-05-31'],
    [20, 2, 'GLB-2024-002', 70,  65.00, '2026-03-31'],
    [20, 3, 'GLB-2024-003', 60,  65.00, '2026-04-30'],
];
foreach ($batches as $b) {
    runSQL($pdo, "INSERT INTO batches (medicine_id,branch_id,batch_number,quantity,purchase_price,expiry_date) VALUES (?,?,?,?,?,?)", $b, $log);
}
$log[] = "✔ Inserted " . count($batches) . " batches";

// Purchases
$purchases = [
    [1, 1, 1, 2, 'PO-2026-0001', 45500.00, 'received', null],
    [2, 2, 2, 3, 'PO-2026-0002', 38200.00, 'received', null],
    [3, 3, 3, 1, 'PO-2026-0003', 29800.00, 'received', null],
    [4, 4, 1, 2, 'PO-2026-0004', 55000.00, 'pending',  null],
    [5, 5, 2, 3, 'PO-2026-0005', 42100.00, 'received', null],
];
foreach ($purchases as $p) {
    runSQL($pdo, "INSERT INTO purchases (id,supplier_id,branch_id,user_id,invoice_number,total_amount,status,notes) VALUES (?,?,?,?,?,?,?,?)", $p, $log);
}
$log[] = "✔ Inserted " . count($purchases) . " purchases";

// Purchase items
$purchaseItems = [
    [1, 1,  'AMX-2024-001', 100, 85.00,  '2026-06-30'],
    [1, 2,  'PAN-2024-001', 150, 45.00,  '2027-01-31'],
    [1, 4,  'GLU-2024-001', 80,  120.00, '2026-05-31'],
    [2, 5,  'TEN-2024-002', 60,  75.00,  '2026-03-31'],
    [2, 6,  'ZYR-2024-002', 80,  55.00,  '2027-01-31'],
    [2, 7,  'ZTH-2024-002', 40,  180.00, '2026-09-30'],
    [3, 10, 'RDX-2024-003', 100, 40.00,  '2026-09-30'],
    [3, 11, 'VD3-2024-003', 50,  50.00,  '2026-08-31'],
    [4, 15, 'COZ-2024-001', 50,  145.00, '2026-09-30'],
    [5, 18, 'PNT-2024-002', 70,  100.00, '2026-06-30'],
];
foreach ($purchaseItems as $pi) {
    runSQL($pdo, "INSERT INTO purchase_items (purchase_id,medicine_id,batch_number,quantity,purchase_price,expiry_date) VALUES (?,?,?,?,?,?)", $pi, $log);
}
$log[] = "✔ Inserted " . count($purchaseItems) . " purchase items";

// Sales
$sales = [
    [1, 1, 4, 'INV-2026-0001', 1250.00, 50.00,  1200.00, 'cash',   1500.00, 300.00, 'completed', null],
    [2, 1, 4, 'INV-2026-0002', 850.00,  0.00,   850.00,  'card',   850.00,  0.00,   'completed', null],
    [3, 2, 5, 'INV-2026-0003', 2100.00, 100.00, 2000.00, 'cash',   2000.00, 0.00,   'completed', null],
    [4, 1, 4, 'INV-2026-0004', 450.00,  0.00,   450.00,  'online', 450.00,  0.00,   'completed', null],
    [5, 3, 6, 'INV-2026-0005', 1800.00, 90.00,  1710.00, 'cash',   2000.00, 290.00, 'completed', null],
];
foreach ($sales as $s) {
    runSQL($pdo, "INSERT INTO sales (id,branch_id,user_id,invoice_number,subtotal,discount,total_amount,payment_method,amount_paid,change_amount,status,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)", $s, $log);
}
$log[] = "✔ Inserted " . count($sales) . " sales";

// Get batch IDs for sale_items
$batchIds = [];
$stmt = $pdo->query("SELECT id, batch_number FROM batches");
foreach ($stmt->fetchAll() as $row) {
    $batchIds[$row['batch_number']] = $row['id'];
}

// Sale items (using dynamic batch IDs)
$saleItems = [
    [1, 1,  'AMX-2024-001', 5,  110.00, 550.00],
    [1, 2,  'PAN-2024-001', 10, 60.00,  600.00],
    [1, 3,  'RSK-2024-001', 1,  125.00, 125.00],
    [2, 7,  'ZTH-2024-001', 2,  235.00, 470.00],
    [2, 10, 'RDX-2024-001', 7,  55.00,  385.00],
    [3, 4,  'GLU-2024-001', 10, 155.00, 1550.00],
    [3, 5,  'TEN-2024-001', 7,  100.00, 700.00],
    [4, 6,  'ZYR-2024-001', 6,  75.00,  450.00],
    [5, 15, 'COZ-2024-001', 3,  190.00, 570.00],
    [5, 18, 'PNT-2024-001', 10, 132.00, 1320.00],
];
foreach ($saleItems as $si) {
    $batchId = $batchIds[$si[2]] ?? 1;
    runSQL($pdo, "INSERT INTO sale_items (sale_id,medicine_id,batch_id,quantity,unit_price,subtotal) VALUES (?,?,?,?,?,?)",
        [$si[0], $si[1], $batchId, $si[3], $si[4], $si[5]], $log);
}
$log[] = "✔ Inserted " . count($saleItems) . " sale items";

// Payments
$payments = [
    [1, 1200.00, 'cash'],
    [2, 850.00,  'card'],
    [3, 2000.00, 'cash'],
    [4, 450.00,  'online'],
    [5, 1710.00, 'cash'],
];
foreach ($payments as $p) {
    runSQL($pdo, "INSERT INTO payments (sale_id,amount,payment_method) VALUES (?,?,?)", $p, $log);
}
$log[] = "✔ Inserted " . count($payments) . " payments";

// Stock movements
$movements = [
    [1,  1, 'AMX-2024-001', 'purchase', 150, 1, 'purchase', 'Initial stock purchase'],
    [2,  1, 'PAN-2024-001', 'purchase', 200, 1, 'purchase', 'Initial stock purchase'],
    [4,  1, 'GLU-2024-001', 'purchase', 130, 1, 'purchase', 'Initial stock purchase'],
    [1,  1, 'AMX-2024-001', 'sale', -5,  1, 'sale', 'INV-2026-0001'],
    [2,  1, 'PAN-2024-001', 'sale', -10, 1, 'sale', 'INV-2026-0001'],
    [3,  1, 'RSK-2024-001', 'sale', -1,  1, 'sale', 'INV-2026-0001'],
    [7,  1, 'ZTH-2024-001', 'sale', -2,  2, 'sale', 'INV-2026-0002'],
    [4,  2, 'GLU-2024-001', 'sale', -10, 3, 'sale', 'INV-2026-0003'],
];
foreach ($movements as $mv) {
    $batchId = $batchIds[$mv[2]] ?? null;
    runSQL($pdo, "INSERT INTO stock_movements (medicine_id,branch_id,batch_id,type,quantity,reference_id,reference_type,notes) VALUES (?,?,?,?,?,?,?,?)",
        [$mv[0], $mv[1], $batchId, $mv[3], $mv[4], $mv[5], $mv[6], $mv[7]], $log);
}
$log[] = "✔ Inserted stock movements";

// Output results
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Medis - Database Seed</title>
<style>
  body { font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
  h1 { color: #1e3a5f; }
  .log { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; }
  .log p { margin: 4px 0; font-size: 14px; }
  .credentials { background: #e8f5e9; border: 1px solid #4caf50; border-radius: 8px; padding: 20px; margin-top: 20px; }
  table { border-collapse: collapse; width: 100%; }
  th, td { text-align: left; padding: 8px 12px; border-bottom: 1px solid #ddd; }
  th { background: #1e3a5f; color: white; }
  .success { color: green; font-weight: bold; font-size: 20px; }
</style>
</head>
<body>
<h1>🏥 Medis Pharmacy - Database Seeder</h1>
<div class="log">
  <?php foreach ($log as $l): ?>
    <p><?= $l ?></p>
  <?php endforeach; ?>
</div>
<p class="success">✅ Database seeded successfully!</p>
<div class="credentials">
  <h2>Login Credentials</h2>
  <table>
    <tr><th>Role</th><th>Email</th><th>Password</th></tr>
    <tr><td>Admin</td><td>admin@medis.pk</td><td>Admin@123</td></tr>
    <tr><td>Manager (Lahore)</td><td>manager.lahore@medis.pk</td><td>Manager@123</td></tr>
    <tr><td>Manager (Islamabad)</td><td>manager.islamabad@medis.pk</td><td>Manager@123</td></tr>
    <tr><td>Cashier 1</td><td>cashier1@medis.pk</td><td>Cashier@123</td></tr>
    <tr><td>Cashier 2</td><td>cashier2@medis.pk</td><td>Cashier@123</td></tr>
    <tr><td>Cashier 3</td><td>cashier3@medis.pk</td><td>Cashier@123</td></tr>
  </table>
  <p><a href="/Medis/login.php" style="color:#1e3a5f;font-weight:bold;">→ Go to Login Page</a></p>
</div>
</body>
</html>
