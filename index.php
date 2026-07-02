<?php
/**
 * Namma AutoParts - Homepage
 */
$page_title = "Online Car Parts & Accessories Marketplace";
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';

// Fetch main categories
try {
    $cat_stmt = $pdo->query("SELECT * FROM categories WHERE parent_id IS NULL LIMIT 5");
    $main_categories = $cat_stmt->fetchAll();
} catch (PDOException $e) {
    $main_categories = [];
}

// Fetch featured products
try {
    $prod_stmt = $pdo->query("SELECT p.*, c.name as category_name 
                              FROM products p 
                              JOIN categories c ON p.category_id = c.id 
                              ORDER BY p.created_at DESC LIMIT 8");
    $featured_products = $prod_stmt->fetchAll();
} catch (PDOException $e) {
    $featured_products = [];
}

// Check vehicle compatibility map if active vehicle is set
$compatible_parts = [];
if (isset($_SESSION['active_vehicle'])) {
    $vehicle_id = $_SESSION['active_vehicle']['id'];
    $fit_stmt = $pdo->prepare("SELECT product_id FROM product_vehicle_fitment WHERE vehicle_id = ?");
    $fit_stmt->execute([$vehicle_id]);
    $compatible_parts = $fit_stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>

<!-- Hero Banner & Fitment Finder Section -->
<section class="py-5 bg-navy text-white position-relative" style="background: linear-gradient(135deg, #0b1d33 0%, #1e293b 100%);">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-6">
                <h1 class="display-5 fw-bold mb-3">Genuine & Refurbished <span class="text-orange">Car Parts</span></h1>
                <p class="lead text-white-50 mb-4">India's leading marketplace for guaranteed fitment spare parts. Earn loyalty points, access local garages, and refund core charges easily.</p>
                <div class="d-flex gap-3">
                    <a href="shop.php" class="btn btn-accent btn-lg"><i class="fa fa-shopping-bag me-2"></i>Browse Catalog</a>
                    <a href="register.php" class="btn btn-outline-light btn-lg"><i class="fa fa-user-plus me-2"></i>Join as Seller</a>
                </div>
            </div>
            
            <!-- Fitment Widget -->
            <div class="col-lg-6" id="fitment-widget">
                <div class="fitment-widget-card card p-4 border border-secondary shadow-lg">
                    <h3 class="mb-3 text-white"><i class="fa fa-car text-orange me-2"></i><?php echo __('fitment_finder_title'); ?></h3>
                    <p class="text-white-50 small">Select your vehicle details below to guarantee part compatibility.</p>
                    
                    <form method="POST" action="api/garage.php">
                        <input type="hidden" name="action" value="set_active_session">
                        <input type="hidden" name="vehicle_id" id="selectedVehicleId" value="">
                        <input type="hidden" name="year" id="selectedYear" value="">
                        <input type="hidden" name="redirect" value="../shop.php">
                        
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label text-white-50 small"><?php echo __('select_make'); ?></label>
                                <select class="form-select bg-dark text-white border-secondary" id="fitmentMake" required>
                                    <option value=""><?php echo __('select_make'); ?></option>
                                    <!-- AJAX loaded -->
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label text-white-50 small"><?php echo __('select_model'); ?></label>
                                <select class="form-select bg-dark text-white border-secondary" id="fitmentModel" disabled required>
                                    <option value="">Select Model</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label text-white-50 small"><?php echo __('select_year'); ?></label>
                                <select class="form-select bg-dark text-white border-secondary" id="fitmentYear" disabled required>
                                    <option value="">Select Year</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label text-white-50 small"><?php echo __('select_engine'); ?></label>
                                <select class="form-select bg-dark text-white border-secondary" id="fitmentEngine" disabled required>
                                    <option value="">Select Variant</option>
                                </select>
                            </div>
                            <div class="col-12 mt-4">
                                <button type="submit" class="btn btn-accent w-100 py-2 fw-bold" id="fitmentSubmitBtn" disabled>
                                    <i class="fa fa-search me-2"></i><?php echo __('find_parts'); ?>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Main Features Badges -->
<section class="py-4 border-bottom" style="background: rgba(255,255,255,0.02);">
    <div class="container">
        <div class="row g-3 text-center">
            <div class="col-md-3 col-6">
                <div class="p-3">
                    <i class="fa fa-award fa-2x text-orange mb-2"></i>
                    <h6 class="fw-bold mb-1">Guaranteed Fitment</h6>
                    <p class="text-muted small mb-0">100% money back check</p>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3">
                    <i class="fa fa-undo fa-2x text-orange mb-2"></i>
                    <h6 class="fw-bold mb-1">Core Returns</h6>
                    <p class="text-muted small mb-0">Refundable deposits on cores</p>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3">
                    <i class="fa fa-wrench fa-2x text-orange mb-2"></i>
                    <h6 class="fw-bold mb-1">Garage Installations</h6>
                    <p class="text-muted small mb-0">Partnered local workshops</p>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3">
                    <i class="fa fa-coins fa-2x text-orange mb-2"></i>
                    <h6 class="fw-bold mb-1">Loyalty Rewards</h6>
                    <p class="text-muted small mb-0">Redeem points for discounts</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Categories Section -->
<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold mb-0">Browse by Category</h3>
            <a href="shop.php" class="text-orange text-decoration-none fw-bold">View All <i class="fa fa-arrow-right ms-1"></i></a>
        </div>
        <div class="row g-3">
            <?php foreach ($main_categories as $cat): ?>
                <div class="col-md-2 col-6">
                    <a href="shop.php?category=<?php echo $cat['slug']; ?>" class="category-card p-4 text-center d-block text-decoration-none text-dark shadow-sm">
                        <i class="fa fa-tools text-orange fa-2x mb-3 d-block"></i>
                        <span class="fw-bold small d-block"><?php echo esc($cat['name']); ?></span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Featured Products -->
<section class="py-5 bg-light">
    <div class="container">
        <h3 class="fw-bold mb-4">New Arrivals & Featured Parts</h3>
        
        <div class="row g-4">
            <?php if (!empty($featured_products)): ?>
                <?php foreach ($featured_products as $prod): ?>
                    <?php 
                    $is_compatible = false;
                    if (isset($_SESSION['active_vehicle'])) {
                        $is_compatible = in_array($prod['id'], $compatible_parts);
                    }
                    
                    // Parse image JSON
                    $imgs = json_decode($prod['images'] ?? '[]');
                    $img = (is_array($imgs) && count($imgs) > 0) ? $imgs[0] : 'no-image.jpg';
                    ?>
                    <div class="col-lg-3 col-md-4 col-sm-6">
                        <div class="product-card card h-100">
                            <!-- Image Container -->
                            <div class="card-img-container position-relative">
                                <img src="assets/uploads/<?php echo $img; ?>" alt="<?php echo esc($prod['name']); ?>" onerror="this.src='https://placehold.co/200x200?text=Auto+Part'">
                                
                                <!-- Condition Badge -->
                                <?php if ($prod['condition'] === 'new'): ?>
                                    <span class="badge bg-success badge-condition"><?php echo __('condition_new'); ?></span>
                                <?php elseif ($prod['condition'] === 'used'): ?>
                                    <span class="badge bg-warning text-dark badge-condition"><?php echo __('condition_used'); ?></span>
                                <?php else: ?>
                                    <span class="badge bg-info badge-condition"><?php echo __('condition_refurbished'); ?></span>
                                <?php endif; ?>

                                <!-- Core charge badge -->
                                <?php if ($prod['core_charge'] > 0): ?>
                                    <span class="badge bg-danger position-absolute top-10 end-10" style="font-size: 10px;" title="Refundable Core Charge">Core: +<?php echo format_price($prod['core_charge']); ?></span>
                                <?php endif; ?>

                                <!-- Compatibility Check Badge -->
                                <?php if (isset($_SESSION['active_vehicle'])): ?>
                                    <?php if ($is_compatible): ?>
                                        <span class="badge bg-success badge-compatibility"><i class="fa fa-check-circle me-1"></i><?php echo __('fits_your_car'); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-danger badge-compatibility"><i class="fa fa-times-circle me-1"></i><?php echo __('does_not_fit'); ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Card Body -->
                            <div class="card-body d-flex flex-column p-3">
                                <small class="text-muted mb-1"><?php echo esc($prod['brand']); ?> | <?php echo esc($prod['category_name']); ?></small>
                                <h6 class="card-title fw-bold text-truncate-2 mb-2" style="font-size: 14px; height: 38px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
                                    <a href="product-details.php?slug=<?php echo $prod['slug']; ?>" class="text-decoration-none text-dark hover-orange"><?php echo esc($prod['name']); ?></a>
                                </h6>
                                
                                <!-- Price and Discount -->
                                <div class="mt-auto d-flex align-items-center gap-2 mb-3">
                                    <?php if ($prod['discount_price'] > 0): ?>
                                        <span class="fs-5 fw-bold text-orange"><?php echo format_price($prod['discount_price']); ?></span>
                                        <span class="text-xs text-muted text-decoration-line-through"><?php echo format_price($prod['price']); ?></span>
                                    <?php else: ?>
                                        <span class="fs-5 fw-bold text-orange"><?php echo format_price($prod['price']); ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="row g-2">
                                    <div class="col-8">
                                        <!-- AJAX Buy / Detail Link -->
                                        <a href="product-details.php?slug=<?php echo $prod['slug']; ?>" class="btn btn-accent btn-sm w-100">
                                            <i class="fa fa-eye me-1"></i>Details
                                        </a>
                                    </div>
                                    <div class="col-4">
                                        <a href="https://wa.me/<?php echo $prod['whatsapp_number'] ?? '919876543210'; ?>?text=Inquiry%20about%20<?php echo urlencode($prod['name']); ?>%20(SKU:%20<?php echo $prod['sku']; ?>)" class="btn btn-success btn-sm w-100" target="_blank" title="Ask on WhatsApp">
                                            <i class="fab fa-whatsapp"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5">
                    <p class="text-muted">No featured parts available.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Promo / How it works Section -->
<section class="py-5 text-white bg-navy border-top border-secondary">
    <div class="container">
        <h3 class="fw-bold text-center mb-5">VeloParts Special Features</h3>
        <div class="row g-4 text-center">
            <div class="col-md-4">
                <div class="p-4 border border-secondary rounded h-100">
                    <i class="fa fa-recycle text-orange fa-3x mb-3"></i>
                    <h5 class="fw-bold text-white mb-2">Core Return System</h5>
                    <p class="small text-white-50 mb-0">Help preserve the environment and save money. Buy refurbished parts, pay a minor deposit, and get it fully refunded when you ship back your core.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-4 border border-secondary rounded h-100">
                    <i class="fa fa-wrench text-orange fa-3x mb-3"></i>
                    <h5 class="fw-bold text-white mb-2">Partnered Garages</h5>
                    <p class="small text-white-50 mb-0">Select installation at checkout. Choose a partnered local garage in Coimbatore or Theni, and schedule a professional fitting directly on our portal.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-4 border border-secondary rounded h-100">
                    <i class="fa fa-handshake text-orange fa-3x mb-3"></i>
                    <h5 class="fw-bold text-white mb-2">B2B Garage Credit</h5>
                    <p class="small text-white-50 mb-0">Are you a garage owner? Register as a B2B Account to unlock tiered bulk pricing, invoice credit lines, and pay-later invoicing options.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Load fitment scripts -->
<script src="assets/js/fitment.js"></script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
