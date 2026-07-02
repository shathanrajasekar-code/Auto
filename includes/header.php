<?php
/**
 * Namma AutoParts - Global Header
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Calculate cart count dynamically if logged in, otherwise from session
$cart_count = 0;
if (is_logged_in()) {
    $stmt = $pdo->prepare("SELECT SUM(qty) as count FROM cart WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $res = $stmt->fetch();
    $cart_count = $res['count'] ?? 0;
} else {
    // Guest cart count
    $cart_count = 0;
    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            $cart_count += $item['qty'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang'] ?? 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . " - " . SITE_NAME : SITE_NAME . " | " . __('tagline'); ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?php echo SITE_URL; ?>/assets/css/style.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

    <!-- Header Navigation -->
    <header class="bg-navy text-white sticky-top py-2 border-bottom border-secondary">
        <div class="container">
            <div class="row align-items-center">
                <!-- Branding -->
                <div class="col-lg-3 col-6">
                    <a class="navbar-brand fs-3 fw-bold text-white d-flex align-items-center text-decoration-none" href="<?php echo SITE_URL; ?>/index.php">
                        <span class="text-orange me-1"><i class="fa fa-cogs me-1"></i>Velo</span>Parts
                    </a>
                </div>
                
                <!-- Fitment Badge / My Garage Panel (Desktop) -->
                <div class="col-lg-5 d-none d-lg-block">
                    <div class="bg-dark-blue p-2 rounded d-flex align-items-center justify-content-between border border-secondary">
                        <div class="d-flex align-items-center">
                            <i class="fa fa-car text-orange fa-lg me-2"></i>
                            <div>
                                <small class="text-white-50 d-block" style="font-size: 10px;"><?php echo __('my_garage'); ?></small>
                                <span class="fw-bold text-white" style="font-size: 13px;">
                                    <?php 
                                    if (isset($_SESSION['active_vehicle'])) {
                                        $v = $_SESSION['active_vehicle'];
                                        echo esc($v['make'] . ' ' . $v['model'] . ' (' . $v['year_from'] . ')');
                                    } else {
                                        echo "No Active Vehicle Selected";
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>
                        <?php if (isset($_SESSION['active_vehicle'])): ?>
                            <a href="<?php echo SITE_URL; ?>/api/garage.php?action=clear" class="btn btn-sm btn-outline-danger py-0 px-2" style="font-size: 11px;"><i class="fa fa-times me-1"></i>Clear</a>
                        <?php else: ?>
                            <a href="<?php echo SITE_URL; ?>/index.php#fitment-widget" class="btn btn-sm btn-accent py-0 px-2" style="font-size: 11px;"><i class="fa fa-plus me-1"></i>Setup</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Utilities (Search, Cart, Auth, Language) -->
                <div class="col-lg-4 col-6 d-flex align-items-center justify-content-end gap-3">
                    <!-- Language Toggle -->
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-globe me-1"></i><?php echo $lang === 'ta' ? 'தமிழ்' : 'English'; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-menu-item dropdown-item <?php echo $lang === 'en' ? 'active' : ''; ?>" href="?lang=en">English</a></li>
                            <li><a class="dropdown-menu-item dropdown-item <?php echo $lang === 'ta' ? 'active' : ''; ?>" href="?lang=ta">தமிழ்</a></li>
                        </ul>
                    </div>

                    <!-- Cart -->
                    <a href="<?php echo SITE_URL; ?>/cart.php" class="btn btn-outline-light position-relative btn-sm">
                        <i class="fa fa-shopping-cart fa-lg"></i>
                        <?php if ($cart_count > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?php echo $cart_count; ?>
                            </span>
                        <?php endif; ?>
                    </a>

                    <!-- Account / Auth -->
                    <?php if (is_logged_in()): ?>
                        <div class="dropdown">
                            <button class="btn btn-accent btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa fa-user me-1"></i><?php echo esc($_SESSION['user_name']); ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/my-account.php"><i class="fa fa-id-card me-2"></i><?php echo __('my_account'); ?></a></li>
                                <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/claims.php"><i class="fa fa-shield-alt me-2"></i>Fitment Claims</a></li>
                                <?php if (has_role('admin')): ?>
                                    <li><a class="dropdown-item text-primary fw-bold" href="<?php echo SITE_URL; ?>/admin/dashboard.php"><i class="fa fa-chart-line me-2"></i><?php echo __('admin_panel'); ?></a></li>
                                <?php elseif (has_role('vendor')): ?>
                                    <li><a class="dropdown-item text-success fw-bold" href="<?php echo SITE_URL; ?>/vendor-panel/dashboard.php"><i class="fa fa-store me-2"></i><?php echo __('vendor_panel'); ?></a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="<?php echo SITE_URL; ?>/logout.php"><i class="fa fa-sign-out-alt me-2"></i><?php echo __('logout'); ?></a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="<?php echo SITE_URL; ?>/login.php" class="btn btn-accent btn-sm d-none d-md-inline-block"><i class="fa fa-sign-in-alt me-1"></i><?php echo __('login'); ?></a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Mobile Fitment Badge -->
            <div class="row d-lg-none mt-2">
                <div class="col-12">
                    <div class="bg-dark-blue p-2 rounded d-flex align-items-center justify-content-between border border-secondary" style="font-size: 12px;">
                        <span class="text-white">
                            <i class="fa fa-car text-orange me-1"></i>
                            <?php 
                            if (isset($_SESSION['active_vehicle'])) {
                                $v = $_SESSION['active_vehicle'];
                                echo esc($v['make'] . ' ' . $v['model'] . ' (' . $v['year_from'] . ')');
                            } else {
                                echo "No Active Vehicle Selected";
                            }
                            ?>
                        </span>
                        <?php if (isset($_SESSION['active_vehicle'])): ?>
                            <a href="<?php echo SITE_URL; ?>/api/garage.php?action=clear" class="text-danger text-decoration-none fw-bold"><i class="fa fa-times"></i></a>
                        <?php else: ?>
                            <a href="<?php echo SITE_URL; ?>/index.php#fitment-widget" class="text-orange text-decoration-none fw-bold">Set up</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Subnavigation bar -->
    <nav class="navbar navbar-expand-md navbar-dark bg-dark py-1">
        <div class="container">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#subNavbar" aria-controls="subNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="subNavbar">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 gap-md-3">
                    <li class="nav-item">
                        <a class="nav-link text-white py-2" href="<?php echo SITE_URL; ?>/index.php"><i class="fa fa-home me-1"></i><?php echo __('home'); ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white py-2" href="<?php echo SITE_URL; ?>/shop.php"><i class="fa fa-th-list me-1"></i><?php echo __('shop'); ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white py-2" href="<?php echo SITE_URL; ?>/shop.php?condition=used"><i class="fa fa-recycle me-1"></i>Used Parts</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white py-2" href="<?php echo SITE_URL; ?>/shop.php?condition=refurbished"><i class="fa fa-tools me-1"></i>Refurbished</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white py-2" href="<?php echo SITE_URL; ?>/diagram.php"><i class="fa fa-images me-1"></i>Interactive Blueprint</a>
                    </li>
                </ul>
                
                <!-- Live Autocomplete Search Widget -->
                <form class="d-flex position-relative col-md-5 col-12 my-2 my-md-0" action="<?php echo SITE_URL; ?>/shop.php" method="GET" id="navSearchForm">
                    <div class="input-group">
                        <input class="form-control bg-dark text-white border-secondary" type="search" name="search" id="liveSearchInput" placeholder="<?php echo __('search_placeholder'); ?>" aria-label="Search" autocomplete="off">
                        <button class="btn btn-orange" type="submit"><i class="fa fa-search text-white"></i></button>
                    </div>
                    <!-- Live Search Results Dropdown -->
                    <div id="liveSearchResults" class="list-group position-absolute w-100 z-3 shadow-lg" style="display: none; top: 40px; max-height: 350px; overflow-y: auto;">
                        <!-- dynamic items go here -->
                    </div>
                </form>
            </div>
        </div>
    </nav>

    <!-- Main Content Wrapper -->
    <main class="py-4">
