<?php
/**
 * Namma AutoParts - Vendor Panel Header
 */
require_once __DIR__ . '/auth-check.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Portal - <?php echo esc($vendor_info['shop_name']); ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0b1d33;
            --accent-color: #f75d00;
            --sidebar-width: 250px;
        }
        body {
            background-color: #f8fafc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .vendor-sidebar {
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
        .vendor-main {
            margin-left: var(--sidebar-width);
            padding: 30px;
            min-height: 100vh;
        }
        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #cbd5e1;
            text-decoration: none;
            transition: all 0.2s;
            font-weight: 500;
        }
        .sidebar-link:hover, .sidebar-link.active {
            background-color: rgba(255,255,255,0.08);
            color: #fff;
            border-left: 4px solid var(--accent-color);
        }
        .sidebar-link i {
            width: 25px;
            font-size: 16px;
        }
        .navbar-vendor {
            background-color: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            padding: 15px 30px;
            margin-bottom: 30px;
            border-radius: 8px;
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

    <!-- Sidebar -->
    <div class="vendor-sidebar">
        <div class="px-4 mb-4 text-center">
            <h5 class="fw-bold text-orange mb-0"><i class="fa fa-cogs me-1"></i>Velo Vendor</h5>
            <small class="text-white-50">Seller Portal</small>
        </div>
        <hr class="border-secondary mx-3 mb-4">
        
        <a href="<?php echo SITE_URL; ?>/vendor-panel/dashboard.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fa fa-chart-line me-2"></i>Dashboard
        </a>
        <a href="<?php echo SITE_URL; ?>/vendor-panel/products.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) === 'products.php' ? 'active' : ''; ?>">
            <i class="fa fa-tools me-2"></i>My Inventory
        </a>
        <a href="<?php echo SITE_URL; ?>/vendor-panel/orders.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) === 'orders.php' ? 'active' : ''; ?>">
            <i class="fa fa-box me-2"></i>Orders Log
        </a>
        
        <div class="position-absolute bottom-0 w-100 pb-3">
            <hr class="border-secondary mx-3">
            <a href="<?php echo SITE_URL; ?>/index.php" class="sidebar-link">
                <i class="fa fa-arrow-left me-2"></i>View Shop
            </a>
            <a href="<?php echo SITE_URL; ?>/logout.php" class="sidebar-link text-danger">
                <i class="fa fa-sign-out-alt me-2"></i>Logout
            </a>
        </div>
    </div>

    <!-- Main Content Frame -->
    <div class="vendor-main">
        <!-- Top navbar inside panel -->
        <div class="navbar-vendor d-flex justify-content-between align-items-center">
            <h4 class="fw-bold text-dark mb-0"><?php echo esc($vendor_info['shop_name']); ?></h4>
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-success p-2">Commission: <?php echo $vendor_info['commission_rate']; ?>%</span>
                <span class="text-secondary small"><i class="fa fa-user-circle me-1"></i>Owner: <?php echo esc($vendor_info['owner_name']); ?></span>
            </div>
        </div>
