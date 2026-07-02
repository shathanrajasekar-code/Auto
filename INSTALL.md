# Namma AutoParts - Installation & Setup Guide

This guide provides step-by-step instructions on what to install and how to run the **Namma AutoParts** application on your local machine.

---

## Technical Stack & Requirements
* **Backend**: PHP 8.0 or newer
* **Database**: MySQL 5.7+ or MariaDB 10.3+
* **Frontend**: HTML5, Vanilla CSS, Bootstrap 5, jQuery & AJAX
* **PHP Extensions Required**:
  * `pdo_mysql` (for database connectivity)
  * `fileinfo` (for product image validation)
  * `mbstring` (for multi-language UTF-8 support)
  * `openssl` (for secure operations)
  * `gd` (for processing uploaded images)

---

## Setup Option 1: Zero-Install Portable Server (Recommended & Easiest)
We have provided a fully self-contained portable development server inside the `bin/` directory. You do **not** need to install any external databases, Apache, or configure system PHP.

### Step 1: Run the Launcher
* **Via File Explorer**: 
  Double-click the [run.bat](file:///C:/Users/ragul/.gemini/antigravity-ide/scratch/namma-autoparts/run.bat) file located at the root of the project.
* **Via PowerShell**: 
  Open a PowerShell terminal in the project root folder and execute:
  ```powershell
  Powershell.exe -NoProfile -ExecutionPolicy Bypass -File bin/run-servers.ps1
  ```

### Step 2: Open the Application
Once the terminal screen indicates that the servers are running, open your web browser and navigate to:
**[http://localhost:8080/](http://localhost:8080/)**

To shut down the servers, close the launcher terminal window or press `Ctrl + C` in it.

---

## Setup Option 2: Manual Installation (XAMPP / WAMP / Laragon)
If you prefer to run the application using your own system-installed stack (such as XAMPP, WAMP, Laragon, or a custom Apache/PHP/MySQL installation):

### Step 1: Install XAMPP or PHP & MySQL
1. Download and install [XAMPP](https://www.apachefriends.org/) (with PHP 8.0+).
2. Move this project folder (`namma-autoparts`) into your server's public document root directory:
   * **XAMPP**: `C:\xampp\htdocs\namma-autoparts`
   * **WampServer**: `C:\wamp64\www\namma-autoparts`
   * **Laragon**: `C:\laragon\www\namma-autoparts`

### Step 2: Enable Required PHP Extensions
Ensure the necessary extensions are enabled in your active `php.ini` file:
1. Open the `php.ini` file (in XAMPP, click **Config** next to Apache -> **PHP (php.ini)**).
2. Find the following lines and ensure they do **not** start with a semicolon (`;`):
   ```ini
   extension=pdo_mysql
   extension=fileinfo
   extension=mbstring
   extension=openssl
   extension=gd
   ```
3. Update standard size limits in `php.ini` to support larger product image uploads:
   ```ini
   upload_max_filesize = 10M
   post_max_size = 20M
   ```
4. Save the file and restart Apache.

### Step 3: Create & Import the Database
1. Start the MySQL / MariaDB service from your XAMPP Control Panel.
2. Open **phpMyAdmin** (`http://localhost/phpmyadmin`) or your preferred MySQL client (e.g., HeidiSQL, DBeaver).
3. Create a new database named: **`namma_autoparts`** (set collation to `utf8mb4_general_ci`).
4. Click **Import** and choose the database schema file:
   * [schema.sql](file:///C:/Users/ragul/.gemini/antigravity-ide/scratch/namma-autoparts/database/schema.sql) (located in `database/schema.sql`).
5. Run/Execute the import to populate tables and demo seed data.

### Step 4: Configure Database Credentials
Open the file [config.php](file:///C:/Users/ragul/.gemini/antigravity-ide/scratch/namma-autoparts/includes/config.php) (located in `includes/config.php`) and edit the database connection constants if your local MySQL setup has a different user or password:
```php
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root'); // Your MySQL username
define('DB_PASS', '');     // Your MySQL password
define('DB_NAME', 'namma_autoparts');
```

### Step 5: View the App
Navigate to:
**`http://localhost/namma-autoparts/`**

---

## Seeded Demo Credentials (Password: `password123`)

| Role | Email | Status | Notes |
|---|---|---|---|
| **Admin** | `admin@namma.com` | Approved | Admin dashboard, approvals, fitment claims |
| **Vendor 1** | `coimbatore@vendor.com` | Approved | Coimbatore Auto Spares, product management |
| **Vendor 2** | `theni@vendor.com` | Approved | Theni Car Zone, order/refund tracking |
| **Customer** | `ramesh@customer.com` | Active | Retail buyer, loyalty points, reviews |
| **Customer (B2B)** | `senthil@garage.com` | Approved | Senthil Garages, ₹50,000 credit limit |
