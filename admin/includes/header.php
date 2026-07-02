<?php
/**
 * Namma AutoParts - Admin Panel Header
 */
require_once __DIR__ . '/auth-check.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - VeloParts</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0b1d33;
            --accent-color: #f75d00;
            --sidebar-width: 260px;
        }
        body {
            background-color: #f1f5f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .admin-sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background-color: var(--primary-color);
            color: #fff;
            padding-top: 20px;
            z-index: 100;
        }
        .admin-main {
            margin-left: var(--sidebar-width);
            padding: 30px;
            min-height: 100vh;
        }
        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #94a3b8;
            text-decoration: none;
            transition: all 0.2s;
            font-weight: 500;
        }
        .sidebar-link:hover, .sidebar-link.active {
            background-color: rgba(255,255,255,0.06);
            color: #fff;
            border-left: 4px solid var(--accent-color);
        }
        .sidebar-link i {
            width: 25px;
            font-size: 16px;
        }
        .navbar-admin {
            background-color: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            padding: 15px 30px;
            margin-bottom: 30px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .btn-accent {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
            color: white;
            font-weight: 600;
        }
        .btn-accent:hover {
            background-color: #d65000;
            border-color: #d65000;
            color: white;
        }
    </style>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

    <!-- Admin Sidebar -->
    <div class="admin-sidebar">
        <div class="px-4 mb-4 text-center">
            <h4 class="fw-bold text-orange mb-0"><i class="fa fa-cogs me-1"></i>Velo Admin</h4>
            <small class="text-white-50">Marketplace Operator</small>
        </div>
        <hr class="border-secondary mx-3 mb-4">
        
        <a href="<?php echo SITE_URL; ?>/admin/dashboard.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fa fa-chart-line me-2"></i>Dashboard
        </a>
        <a href="<?php echo SITE_URL; ?>/admin/vendors.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) === 'vendors.php' ? 'active' : ''; ?>">
            <i class="fa fa-store-alt me-2"></i>Vendor Registrations
        </a>
        <a href="<?php echo SITE_URL; ?>/admin/customers.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) === 'customers.php' ? 'active' : ''; ?>">
            <i class="fa fa-warehouse me-2"></i>B2B Garage Accounts
        </a>
        <a href="<?php echo SITE_URL; ?>/admin/inventory.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) === 'inventory.php' ? 'active' : ''; ?>">
            <i class="fa fa-barcode me-2"></i>QR Inventory Adjuster
        </a>
        <a href="<?php echo SITE_URL; ?>/admin/claims.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) === 'claims.php' ? 'active' : ''; ?>">
            <i class="fa fa-shield-alt me-2"></i>Fitment Claims
        </a>

        
        <div class="position-absolute bottom-0 w-100 pb-3">
            <hr class="border-secondary mx-3">
            <a href="<?php echo SITE_URL; ?>/index.php" class="sidebar-link">
                <i class="fa fa-arrow-left me-2"></i>View Storefront
            </a>
            <a href="<?php echo SITE_URL; ?>/logout.php" class="sidebar-link text-danger">
                <i class="fa fa-sign-out-alt me-2"></i>Logout
            </a>
        </div>
    </div>

    <!-- Main Content Frame -->
    <div class="admin-main">
        <!-- Top navbar inside panel -->
        <div class="navbar-admin d-flex justify-content-between align-items-center">
            <h4 class="fw-bold text-dark mb-0">System Operations Control</h4>
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-danger p-2"><i class="fa fa-shield-alt me-1"></i>Administrator</span>
                <span class="text-secondary small"><i class="fa fa-user-circle me-1"></i>Signed: <?php echo esc($_SESSION['user_name']); ?></span>
            </div>
        </div>
