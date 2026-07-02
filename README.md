# Namma AutoParts - Online Car Parts & Accessories Marketplace

Namma AutoParts is a production-grade, fully working Online Car Parts & Accessories Marketplace built in PHP (PDO) and MySQL, utilizing Bootstrap 5 and jQuery AJAX for seamless user experiences.

---

## Folder Structure

The project has been structured as follows:
- `admin/`: Admin panels (Dashboard, Vendor approvals, B2B approvals, QR Stock adjustments, Reports).
- `api/`: JSON endpoint APIs (AJAX autocomplete, fitment helper cascades, filters, cart, reviews).
- `assets/`: Styling (style.css), JS (fitment.js), and upload directory.
- `database/`: Database schema and seed data SQL.
- `includes/`: Configuration (config.php), translations, helpers (functions.php), headers, footers.
- `vendor-panel/`: Vendor panel (Dashboard, Product CRUD, Order Shipping, Core refund decisions).

---

## Setup Instructions (Local Portable Servers - Easiest)

We have provided a fully self-contained local environment inside the `bin/` directory. No database installation, administrative configuration, or external web server is required.

### 1. Launch local PHP & MariaDB Servers
In PowerShell (as user, no administrator rights required), execute the startup script from the project root:
```powershell
Powershell.exe -File bin/run-servers.ps1
```
This script automatically:
- Initializes the local MariaDB data directories.
- Kills any previous conflicting background mysqld/php processes.
- Boots the local MariaDB server.
- Creates and imports the database schema and seed data from `database/schema.sql`.
- Starts the PHP development server.

### 2. View the App
Open your browser and navigate to:
**[http://localhost:8080/](http://localhost:8080/)**

---

## Unique Industry Features Implemented

1. **Interactive Parts Blueprint (`diagram.php`)**:
   - An interactive, exploded OEM CAD diagram of key vehicle systems (Engine, Braking).
   - Clickable hotspots directly link to exact spare parts catalog categories, preventing buyers from selecting incorrect parts.
2. **Fitment Guarantee Claims (`claims.php`, `admin/claims.php`)**:
   - Customers who had active garage profiles can lodge return claims if parts did not fit.
   - Admin panel manages reviews, process automated returns, and compensates users with 100 loyalty points.
3. **Core Charge Cashback**:
   - Dynamic tracking of core charge deposits for used/refurbished items. Vendors inspect and approve core refunds, which are issued directly back to customers as cash-convertible loyalty points.

---

## Demo Credentials (Seeded)

All accounts share the password: `password123`

| Role | Email | Status | Notes |
|---|---|---|---|
| **Admin** | `admin@namma.com` | Approved | Operational dashboard, registration approvals |
| **Vendor 1** | `coimbatore@vendor.com` | Approved | Coimbatore Auto Spares, WhatsApp inquiry button |
| **Vendor 2** | `theni@vendor.com` | Approved | Theni Car Zone, WhatsApp inquiry button |
| **Vendor 3** | `salem@vendor.com` | Pending | Salem Spares (Requires Admin approval to login) |
| **Customer 1** | `ramesh@customer.com` | Active | Retail buyer, shares referral codes |
| **Customer 2 (B2B)** | `senthil@garage.com` | Approved | Senthil Garages, ₹50,000 credit limit (Pay Later terms) |

---

## Security Protocols Implemented
1. **PDO Prepared Statements**: Zero raw SQL injections allowed.
2. **CSRF Tokens**: Form validations using session security tokens.
3. **Output escaping**: Wrap variables with `esc()` (`htmlspecialchars`).
4. **Login lockout rate-limiting**: Locks accounts for 15 minutes after 5 unsuccessful attempts.
5. **Role-Based Access Control**: Pages protected using auth checks inside sub-folders.

# AutoSpares

# Auto
