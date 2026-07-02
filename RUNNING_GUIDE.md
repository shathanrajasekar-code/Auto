# Namma AutoParts - running & Architecture Guide

This document describes how to install, run, and navigate the Namma AutoParts Marketplace locally.

---

## 1. Prerequisites & Installation (What to Install)

This project has been designed as a **self-contained portable web application**. You do NOT need to install heavy application managers (like XAMPP/WAMP) or global database instances (like MySQL Installer). 

### Prerequisites:
- **Operating System**: Windows (10 or 11).
- **Console Shell**: Windows PowerShell (comes standard with Windows).
- **Network**: Internet connection (only if you need to re-download PHP/MariaDB binaries).

### One-Time Setup / Dependency Download:
The portable binaries for **PHP 8.2** and **MariaDB 10.11** are already pre-configured and extracted inside the `bin/` directory. 
If the binaries are missing or if you need to set them up fresh from clean zips, run this command in PowerShell from the project root:
```powershell
Powershell.exe -File bin/download-servers.ps1
```
*This downloads the official portable releases of PHP 8.2 & MariaDB 10.11, configures `php.ini` extensions (PDO, MySQL, Openssl, etc.), and prepares the folders.*

---

## 2. Running the Servers (How to Run)

To launch the database server and the PHP web server, follow these steps:

1. Open **PowerShell** (no Administrator privileges required).
2. Use the terminal to navigate to your project root folder (`C:\Users\ragul\.gemini\antigravity-ide\scratch\namma-autoparts`).
3. Run the startup script:
   ```powershell
   Powershell.exe -File bin/run-servers.ps1
   ```
4. Keep this PowerShell window open while browsing the app. To shut down the servers, press `Ctrl+C` or close the PowerShell window.

### What the Runner Script Does:
- Scans and halts conflicting background `php.exe` or `mysqld.exe` instances to free up ports.
- Initializes the MariaDB data directory at [bin/mariadb/data](file:///c:/Users/ragul/.gemini/antigravity-ide/scratch/namma-autoparts/bin/mariadb/data).
- Starts MariaDB database on port `3306`.
- Auto-imports the catalog schema and test seeds from [database/schema.sql](file:///c:/Users/ragul/.gemini/antigravity-ide/scratch/namma-autoparts/database/schema.sql) if not already initialized.
- Launches the PHP developer server at **http://localhost:8080/**.

---

## 3. Directory Map (Where to Go in the System)

Use this directory guide to locate specific frontend, backend, configuration, and data folders:

| Folder / File Path | Description & Purpose |
|---|---|
| 🌐 **Root Directory** | Contains core shopping pages: [index.php](file:///c:/Users/ragul/.gemini/antigravity-ide/scratch/namma-autoparts/index.php), [shop.php](file:///c:/Users/ragul/.gemini/antigravity-ide/scratch/namma-autoparts/shop.php), [cart.php](file:///c:/Users/ragul/.gemini/antigravity-ide/scratch/namma-autoparts/cart.php), [checkout.php](file:///c:/Users/ragul/.gemini/antigravity-ide/scratch/namma-autoparts/checkout.php), [diagram.php](file:///c:/Users/ragul/.gemini/antigravity-ide/scratch/namma-autoparts/diagram.php). |
| 📁 [includes/](file:///c:/Users/ragul/.gemini/antigravity-ide/scratch/namma-autoparts/includes/) | **Core Config & Logic**: Contains [config.php](file:///c:/Users/ragul/.gemini/antigravity-ide/scratch/namma-autoparts/includes/config.php) (database and timezone definitions), [functions.php](file:///c:/Users/ragul/.gemini/antigravity-ide/scratch/namma-autoparts/includes/functions.php) (SQL prepared executors & security sanitizers), and navbar templates. |
| 📁 [admin/](file:///c:/Users/ragul/.gemini/antigravity-ide/scratch/namma-autoparts/admin/) | **Admin Dashboard Panel**: Management pages for approving new vendors, reviewing fitment refund claims, and adjusting inventory. |
| 📁 [vendor-panel/](file:///c:/Users/ragul/.gemini/antigravity-ide/scratch/namma-autoparts/vendor-panel/) | **Vendor Store Panel**: Products CRUD, order shipping tracking, and core refund inspection forms. |
| 📁 [assets/uploads/](file:///c:/Users/ragul/.gemini/antigravity-ide/scratch/namma-autoparts/assets/uploads/) | **Product Catalog Images**: Stores all product image files (such as brake rotors, shock absorbers, headlights, etc.). |
| 📁 [assets/css/](file:///c:/Users/ragul/.gemini/antigravity-ide/scratch/namma-autoparts/assets/css/) | **Stylesheets**: Contains [style.css](file:///c:/Users/ragul/.gemini/antigravity-ide/scratch/namma-autoparts/assets/css/style.css) containing the custom dark glassmorphic styling system overrides. |
| 📁 [api/](file:///c:/Users/ragul/.gemini/antigravity-ide/scratch/namma-autoparts/api/) | **JSON Endpoints**: Handler files for AJAX operations (e.g. live search, fitment finder selector, reviews, cart CRUD). |
| 📁 [database/](file:///c:/Users/ragul/.gemini/antigravity-ide/scratch/namma-autoparts/database/) | **SQL Schema**: Contains [schema.sql](file:///c:/Users/ragul/.gemini/antigravity-ide/scratch/namma-autoparts/database/schema.sql) defining the structure and demo user seeds. |
| 📁 [bin/](file:///c:/Users/ragul/.gemini/antigravity-ide/scratch/namma-autoparts/bin/) | **Portable Servers**: Holds portable PHP and MariaDB program directories and startup scripts. |

---

## 4. Technology Stack Used

- **Back-End Language**: PHP 8.2.15 (configured with PDO for security).
- **Relational Database**: MariaDB 10.11.2 (fully seeded with products, transactions, and users).
- **Front-End Styling**: Bootstrap 5.3.0 + custom glassmorphic overrides.
- **Icons**: FontAwesome 6.4.0.
- **JavaScript Core**: jQuery 3.6.0 + custom fitment managers ([fitment.js](file:///c:/Users/ragul/.gemini/antigravity-ide/scratch/namma-autoparts/assets/js/fitment.js)).
- **Exploded Views**: Interactive Vector SVG blueprints with absolute path coordinate hotspots.

---

## 5. Demo Accounts

All accounts share the password: `password123`

| Role | Email | Status | Features / Permissions |
|---|---|---|---|
| **Admin** | `admin@namma.com` | Approved | Manage vendor approvals, fitment claims, and B2B credit limits |
| **Vendor 1** | `coimbatore@vendor.com` | Approved | Coimbatore Auto Spares, product management, shipping CRUD |
| **Vendor 2** | `theni@vendor.com` | Approved | Theni Car Zone, product management, shipping CRUD |
| **Vendor 3** | `salem@vendor.com` | Pending | Requires admin approval to log in |
| **Customer 1** | `ramesh@customer.com` | Active | Retail buyer, loyalty points, cart checkout |
| **Customer 2 (B2B)** | `senthil@garage.com` | Approved | Senthil Garages, ₹50,000 credit limit (Pay Later terms) |
