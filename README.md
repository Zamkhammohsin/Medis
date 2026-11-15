# 💊 Medis — The Cure for Pharmacy Chaos! 🩺✨

[![Pure PHP](https://img.shields.io/badge/PHP-8.x%20%28No%20Frameworks!%29-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL PDO](https://img.shields.io/badge/MySQL-InnoDB%20PDO-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Pure Vanilla JS](https://img.shields.io/badge/Vanilla-JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)](https://developer.mozilla.org/)
[![License](https://img.shields.io/badge/License-MIT-success?style=for-the-badge)](LICENSE)

> *"Running a multi-branch pharmacy shouldn't give you a headache!"* 🧠💥  
> **Medis** is an all-in-one, ultra-fast Pharmacy Management System and smart Point-of-Sale (POS) terminal built strictly from the ground up without bloated frameworks or 300MB `node_modules` black holes. Just clean, blazingly fast PHP & MySQL goodness.

---

## 🎯 What in the World is Medis?

Imagine you run three pharmacy stores. Patients are waiting, medicines are expiring, stock counts are a mystery, and cashiers are doing math on sticky notes. **Total nightmare.**

Enter **Medis**! 🦸‍♂️  
It keeps every branch connected, knows exactly which pills will expire first so you sell them in time (**FIFO power!**), lets cashiers scan barcodes and print receipts in milliseconds, and gives managers high-level executive graphs of daily profits.

---

## ⚡ Superpowers & Cool Features

### 🛒 1. Speed-Demon POS (Point of Sale)
* **Barcode beep-boop ready**: Plug in any barcode scanner or start typing medicine names — instant live search!
* **Smart Cart**: Automatically respects stock limits, applies fixed or percentage discounts, and calculates taxes without breaking a sweat.
* **Cash, Card & Online**: Calculates customer change automatically so cashiers don't have to do mental math on a busy Monday morning.
* **Thermal Receipt Printing**: Beautiful printable receipt popup ready for 80mm thermal printers or PDF export.

### ⏳ 2. FIFO Batch Tracking (First In, First Out)
* **Never sell expired meds**: Batches are strictly tracked with expiry dates and costs.
* **Auto-prioritization**: Medis automatically sells the oldest batch first so medicines don't sit on shelves and turn into dust.
* **Safety Lock**: If a batch has expired, it is automatically locked from the checkout register.

### 🏥 3. Multi-Branch Empire Management
* **Central Command**: Manage multiple outlets (e.g. Lahore, Islamabad, Karachi) from one administrative dashboard.
* **Branch Isolation**: Cashiers and local managers only see their store's inventory, purchases, and sales. No cross-branch confusion!

### 📊 4. Executive Eye-Candy (Analytics & Reports)
* **Interactive Chart.js Visuals**: Track daily sales, hourly rush-hour turnover, and monthly trends.
* **Branch Showdown**: Side-by-side revenue comparisons and market share doughnut charts.
* **Low Stock Radar**: Alerts you before you run out of essential life-saving medicines.

### 🚚 5. Supplier & Procurement Pipeline
* Manage vendors and suppliers seamlessly.
* Create purchase orders and receive shipments with automated batch registration and stock logs.

### 👥 6. Role-Based Access Control (RBAC)
* 👑 **Administrator**: Supreme ruler of all branches, users, and global reports.
* 👔 **Branch Manager**: Controls store-specific stock, orders, and branch metrics.
* 💳 **Cashier**: Focused solely on the lightning-fast POS register.

### 🛡️ 7. Built Like a Tank (Security)
* **100% Prepared PDO Statements**: SQL Injection attackers will cry.
* **Bcrypt Password Vault**: Industry-standard cryptographic hashing.
* **CSRF & XSS Shields**: Token validation on state-changing requests and full output escaping.

---

## 🛠️ The Under-the-Hood Stack

| Layer | Weapon of Choice | Why? |
|---|---|---|
| **Backend** | Core PHP 7.4 / 8.x | Lightweight, zero build-steps, blazing fast execution |
| **Database** | MySQL / MariaDB (InnoDB) | Rock-solid atomic ACID transactions |
| **Driver** | PHP Data Objects (PDO) | Secure, parameterized database queries |
| **Frontend** | HTML5, Modern CSS3 & Vanilla JS | Clean UI, zero framework fatigue |
| **Charts** | Chart.js 4 (CDN) | Interactive revenue & performance graphs |
| **Icons** | Font Awesome 6 (CDN) | Crisp, modern medical UI iconography |

---

## 🚀 3-Minute Quick Start (XAMPP Setup)

### 1️⃣ Clone or Copy
Place the `Medis` folder inside your local web server root:
```bash
# Example for XAMPP on Windows:
C:\xampp\htdocs\Medis
```

### 2️⃣ Fire Up MySQL & Apache
Open your **XAMPP Control Panel** and hit **Start** on both **Apache** and **MySQL**.

### 3️⃣ Setup the Database
1. Open **phpMyAdmin** in your browser (`http://localhost/phpmyadmin/`).
2. Create a new database named **`medis_db`** with collation `utf8mb4_unicode_ci`.
3. Import the file:
   ```text
   database/database.sql
   ```
4. *(Optional & Recommended)* Seed with rich sample data:
   Navigate to:
   ```text
   http://localhost/Medis/database/seed.php
   ```
   *Boom! Pre-populated branches, suppliers, 20+ medicines, and mock sales are ready to test.*

### 4️⃣ Open & Enjoy!
Head over to:
👉 **`http://localhost/Medis`**

---

## 🔑 Demo Login Credentials

Grab any of these pre-configured accounts to take Medis for a test spin:

| Role | Email | Password | Scope |
|---|---|---|---|
| 👑 **Administrator** | `admin@medis.pk` | `Admin@123` | Master Central Control |
| 👔 **Branch Manager** | `manager.lahore@medis.pk` | `Manager@123` | Lahore Branch |
| 👔 **Branch Manager** | `manager.islamabad@medis.pk` | `Manager@123` | Islamabad Branch |
| 💳 **Cashier (POS)** | `cashier1@medis.pk` | `Cashier@123` | Lahore POS Terminal |
| 💳 **Cashier (POS)** | `cashier2@medis.pk` | `Cashier@123` | Islamabad POS Terminal |

> 💡 **Pro-Tip**: The login screen features handy **1-Click Quick Demo** buttons so you don't even have to type passwords!

---

## 📂 Project Architecture

```text
Medis/
├── assets/                   # CSS, Vanilla JS, and images
│   ├── css/style.css         # Modern, responsive pharmacy dashboard theme
│   └── js/
│       ├── app.js            # UI interactions, toasts, and modals
│       └── pos.js            # Real-time POS cart calculations & barcode engine
├── config/
│   └── database.php          # Database credentials & PDO singleton
├── database/
│   ├── database.sql          # Core relational schema & tables
│   └── seed.php              # Automated test data generator
├── modules/
│   ├── pos/                  # Point of Sale terminal & receipt generator
│   ├── inventory/            # Batch tracking, stock adjustments & movements
│   ├── medicines/            # Catalog, generic names, barcodes & pricing
│   ├── purchases/            # Purchase orders & vendor shipments
│   ├── suppliers/            # Vendor directory & ledger
│   ├── branches/             # Store locations & manager assignments
│   ├── reports/              # Daily, monthly, and comparative analytics
│   ├── sales/                # Invoice history & reprints
│   └── users/                # Access control & role permissions
├── videos/                   # Demo walkthrough recordings
├── dashboard.php             # Unified analytics home
├── index.php                 # Smart route redirector
└── README.md                 # You are here! 🎉
```

---

## 🎥 Video Tour
Check out `videos/Medis_System_Walkthrough.mp4` inside the repository for an interactive video walkthrough of the POS, reports, and inventory management in action!

---

## 📜 License
Distributed under the **MIT License**. Free to use, adapt, and build upon.

Made with ❤️ and plenty of coffee for modern, stress-free pharmacy operations.
