<?php
/**
 * Namma AutoParts - Product Details & Fitment Checker Page
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$slug = isset($_GET['slug']) ? clean_input($_GET['slug']) : '';

if (empty($slug)) {
    redirect('shop.php');
}

try {
    // Fetch product details
    $stmt = $pdo->prepare("SELECT p.*, c.name as category_name, c.slug as category_slug, 
                                  v.shop_name, v.whatsapp_number, v.location as vendor_location 
                           FROM products p 
                           JOIN categories c ON p.category_id = c.id 
                           LEFT JOIN vendors v ON p.vendor_id = v.id 
                           WHERE p.slug = ?");
    $stmt->execute([$slug]);
    $product = $stmt->fetch();

    if (!$product) {
        redirect('shop.php');
    }

    $product_id = $product['id'];

    // Compatibility check for active vehicle
    $is_compatible = false;
    if (isset($_SESSION['active_vehicle'])) {
        $v_id = $_SESSION['active_vehicle']['id'];
        $fit_stmt = $pdo->prepare("SELECT COUNT(*) FROM product_vehicle_fitment WHERE product_id = ? AND vehicle_id = ?");
        $fit_stmt->execute([$product_id, $v_id]);
        $is_compatible = $fit_stmt->fetchColumn() > 0;
    }

    // Fetch Partner Garages for installation bookings
    $garages_stmt = $pdo->query("SELECT * FROM garages ORDER BY rating DESC");
    $garages = $garages_stmt->fetchAll();

    // Smart Cross-Sell Engine (Co-purchase SQL)
    $cross_stmt = $pdo->prepare("SELECT DISTINCT p.* 
                                 FROM order_items oi1 
                                 JOIN order_items oi2 ON oi1.order_id = oi2.order_id AND oi1.product_id != oi2.product_id 
                                 JOIN products p ON oi2.product_id = p.id 
                                 WHERE oi1.product_id = ? AND p.stock_qty > 0 
                                 LIMIT 4");
    $cross_stmt->execute([$product_id]);
    $cross_sells = $cross_stmt->fetchAll();

    // Fallback: Same Category
    if (empty($cross_sells)) {
        $fallback_stmt = $pdo->prepare("SELECT p.* 
                                        FROM products p 
                                        WHERE p.category_id = ? AND p.id != ? AND p.stock_qty > 0 
                                        LIMIT 4");
        $fallback_stmt->execute([$product['category_id'], $product_id]);
        $cross_sells = $fallback_stmt->fetchAll();
    }

    // Fetch reviews
    $rev_stmt = $pdo->prepare("SELECT r.*, u.name 
                               FROM reviews r 
                               JOIN users u ON r.user_id = u.id 
                               WHERE r.product_id = ? 
                               ORDER BY r.created_at DESC");
    $rev_stmt->execute([$product_id]);
    $reviews = $rev_stmt->fetchAll();

    // Calculate average rating
    $avg_rating = 0;
    if (count($reviews) > 0) {
        $total_stars = array_sum(array_column($reviews, 'rating'));
        $avg_rating = round($total_stars / count($reviews), 1);
    }

    // Check if current user is verified buyer
    $is_verified_buyer = false;
    if (is_logged_in()) {
        $user_id = $_SESSION['user_id'];
        $verify_sql = "SELECT COUNT(oi.id) 
                       FROM order_items oi 
                       JOIN orders o ON oi.order_id = o.id 
                       WHERE o.user_id = ? AND oi.product_id = ? AND o.status = 'Delivered'";
        $v_stmt = $pdo->prepare($verify_sql);
        $v_stmt->execute([$user_id, $product_id]);
        $is_verified_buyer = $v_stmt->fetchColumn() > 0;
    }

} catch (PDOException $e) {
    die("Database Error: " . htmlspecialchars($e->getMessage()));
}

// Images Parser
$imgs = json_decode($product['images'] ?? '[]');
$primary_img = (is_array($imgs) && count($imgs) > 0) ? $imgs[0] : 'no-image.jpg';

$page_title = $product['name'];
require_once __DIR__ . '/includes/header.php';
?>

<!-- Toast Container for Notifications -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
    <div id="cartToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body">
                <i class="fa fa-check-circle me-2"></i>Item added to cart successfully!
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<div class="container my-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item"><a href="shop.php" class="text-decoration-none">Catalog</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo esc($product['name']); ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <!-- Left Column: Image Gallery -->
        <div class="col-md-6">
            <div class="bg-white p-3 border rounded shadow-sm">
                <!-- Large Image Display -->
                <div class="text-center mb-3 bg-light p-4 rounded" style="height: 350px; display: flex; align-items: center; justify-content: center;">
                    <img id="mainProductImage" src="assets/uploads/<?php echo $primary_img; ?>" alt="<?php echo esc($product['name']); ?>" class="img-fluid" style="max-height: 100%; object-fit: contain;" onerror="this.src='https://placehold.co/400x400?text=Auto+Part'">
                </div>
                
                <!-- Thumbnails -->
                <?php if (is_array($imgs) && count($imgs) > 1): ?>
                    <div class="row g-2 justify-content-center">
                        <?php foreach ($imgs as $index => $img): ?>
                            <div class="col-3 col-sm-2">
                                <div class="border rounded overflow-hidden cursor-pointer p-1 bg-light thumbnail-container" style="height: 60px; display: flex; align-items: center; justify-content: center; cursor: pointer;">
                                    <img src="assets/uploads/<?php echo $img; ?>" alt="thumbnail" class="img-fluid gallery-thumb" style="max-height: 100%; object-fit: contain;" data-src="assets/uploads/<?php echo $img; ?>">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column: Product Actions & Description -->
        <div class="col-md-6">
            <div class="bg-white p-4 border rounded shadow-sm">
                
                <!-- Title & Brand -->
                <small class="text-muted text-uppercase fw-bold"><?php echo esc($product['brand']); ?></small>
                <h1 class="h2 fw-bold text-dark mt-1"><?php echo esc($product['name']); ?></h1>
                
                <!-- Rating Summary -->
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="text-warning">
                        <?php for($i=1; $i<=5; $i++): ?>
                            <i class="fa<?php echo ($i <= $avg_rating) ? 's' : 'r'; ?> fa-star"></i>
                        <?php endfor; ?>
                    </span>
                    <span class="fw-bold small text-dark mt-1"><?php echo $avg_rating; ?> stars</span>
                    <span class="text-muted small mt-1">(<?php echo count($reviews); ?> reviews)</span>
                </div>

                <hr class="my-3">

                <!-- Fitment Compatibility Alert Banner -->
                <?php if (isset($_SESSION['active_vehicle'])): ?>
                    <?php if ($is_compatible): ?>
                        <div class="alert alert-success d-flex align-items-center gap-2">
                            <i class="fa fa-check-circle fa-lg"></i>
                            <div>
                                <strong>Compatible Part!</strong> Fits your <strong><?php echo esc($_SESSION['active_vehicle']['make'] . ' ' . $_SESSION['active_vehicle']['model'] . ' (' . $_SESSION['active_vehicle']['selected_year'] . ')'); ?></strong>.
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger d-flex align-items-center gap-2">
                            <i class="fa fa-times-circle fa-lg"></i>
                            <div>
                                <strong>Incompatible Part!</strong> Does NOT fit your <strong><?php echo esc($_SESSION['active_vehicle']['make'] . ' ' . $_SESSION['active_vehicle']['model'] . ' (' . $_SESSION['active_vehicle']['selected_year'] . ')'); ?></strong>.
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- Collapsed Fitment Finder Widget -->
                    <div class="card bg-light border border-dashed mb-3 p-3">
                        <h6 class="fw-bold mb-2"><i class="fa fa-car text-orange me-2"></i>Will this fit my car?</h6>
                        <form method="POST" action="api/garage.php">
                            <input type="hidden" name="action" value="set_active_session">
                            <input type="hidden" name="vehicle_id" id="selectedVehicleId" value="">
                            <input type="hidden" name="year" id="selectedYear" value="">
                            <input type="hidden" name="redirect" value="../product-details.php?slug=<?php echo $slug; ?>">
                            
                            <div class="row g-2">
                                <div class="col-6">
                                    <select class="form-select form-select-sm" id="fitmentMake" required>
                                        <option value="">Select Make</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <select class="form-select form-select-sm" id="fitmentModel" disabled required>
                                        <option value="">Select Model</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <select class="form-select form-select-sm" id="fitmentYear" disabled required>
                                        <option value="">Select Year</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <select class="form-select form-select-sm" id="fitmentEngine" disabled required>
                                        <option value="">Select Engine</option>
                                    </select>
                                </div>
                                <div class="col-12 mt-2 text-end">
                                    <button type="submit" class="btn btn-accent btn-sm py-1 px-3" id="fitmentSubmitBtn" disabled>Check Fitment</button>
                                </div>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Condition and Warranty Badges -->
                <div class="d-flex gap-2 mb-3">
                    <?php if ($product['condition'] === 'new'): ?>
                        <span class="badge bg-success py-2 px-3 fw-bold" style="font-size:12px;">NEW</span>
                    <?php elseif ($product['condition'] === 'used'): ?>
                        <span class="badge bg-warning text-dark py-2 px-3 fw-bold" style="font-size:12px;">USED PARTS</span>
                    <?php else: ?>
                        <span class="badge bg-info py-2 px-3 fw-bold" style="font-size:12px;">REFURBISHED</span>
                    <?php endif; ?>
                    
                    <span class="badge bg-secondary py-2 px-3" style="font-size:12px;"><i class="fa fa-shield-alt me-1"></i><?php echo $product['warranty_months']; ?> Months Warranty</span>
                </div>

                <!-- Price Area -->
                <div class="bg-light p-3 rounded mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <?php if ($product['discount_price'] > 0): ?>
                            <span class="display-6 fw-bold text-orange"><?php echo format_price($product['discount_price']); ?></span>
                            <span class="text-decoration-line-through text-muted"><?php echo format_price($product['price']); ?></span>
                        <?php else: ?>
                            <span class="display-6 fw-bold text-orange"><?php echo format_price($product['price']); ?></span>
                        <?php endif; ?>
                        
                        <span class="badge bg-success">In Stock (<?php echo $product['stock_qty']; ?>)</span>
                    </div>
                    
                    <!-- Core Charge Notice -->
                    <?php if ($product['core_charge'] > 0): ?>
                        <div class="mt-3 p-2 bg-white rounded border border-danger text-danger small">
                            <i class="fa fa-info-circle me-1"></i>
                            <strong>Refundable Core Charge: +<?php echo format_price($product['core_charge']); ?></strong>.
                            You will pay this deposit at checkout. It will be 100% refunded when you ship back your old, replaced part to the vendor.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Product Add Form -->
                <form id="addToCartForm" class="mb-4">
                    <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                    
                    <!-- Garage Service Booking Option -->
                    <div class="card mb-4 border border-dashed p-3">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="toggleInstallation">
                            <label class="form-check-label fw-bold text-primary" for="toggleInstallation">
                                <i class="fa fa-wrench me-1"></i>Add Professional Garage Installation
                            </label>
                        </div>
                        <div id="installationDetails" style="display: none;" class="mt-2">
                            <div class="row g-2">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label text-muted small mb-1">Select Garage</label>
                                    <select class="form-select form-select-sm" name="garage_id" id="garageSelect">
                                        <option value="">Choose partner garage...</option>
                                        <?php foreach ($garages as $g): ?>
                                            <option value="<?php echo $g['id']; ?>"><?php echo esc($g['name']); ?> (★<?php echo $g['rating']; ?>) - <?php echo esc($g['location']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label text-muted small mb-1">Preferred Date & Time</label>
                                    <input type="datetime-local" class="form-select form-select-sm" name="booking_date" id="bookingDateInput">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-2 align-items-center">
                        <div class="col-md-3 col-4">
                            <input type="number" name="qty" class="form-control" value="1" min="1" max="<?php echo $product['stock_qty']; ?>" required>
                        </div>
                        <div class="col-md-6 col-8">
                            <button type="submit" class="btn btn-accent w-100 py-2"><i class="fa fa-shopping-cart me-2"></i>Add to Cart</button>
                        </div>
                        <div class="col-md-3 col-12 mt-2 mt-md-0">
                            <!-- WhatsApp Inquiry Button -->
                            <a href="https://wa.me/<?php echo $product['whatsapp_number'] ? $product['whatsapp_number'] : '919876543210'; ?>?text=Hi!%20I%20am%20interested%20in%20buying%20<?php echo urlencode($product['name']); ?>%20(SKU:%20<?php echo $product['sku']; ?>)%20from%20Namma%20AutoParts.%20Please%20confirm%20availability." class="btn btn-success w-100 py-2" target="_blank">
                                <i class="fab fa-whatsapp me-1"></i>Inquire
                            </a>
                        </div>
                    </div>
                </form>

                <!-- Vendor details info -->
                <div class="text-muted small mt-2">
                    <p class="mb-1"><i class="fa fa-store me-1"></i>Sold by: <strong><?php echo esc($product['shop_name'] ?? 'VeloParts Direct'); ?></strong></p>
                    <p class="mb-0"><i class="fa fa-map-marker-alt me-1"></i>Seller Location: <?php echo esc($product['vendor_location'] ?? 'Coimbatore Warehouse'); ?></p>
                </div>

            </div>
        </div>
    </div>

    <!-- Product Description -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="bg-white p-4 border rounded shadow-sm">
                <h4 class="fw-bold mb-3">Product Specifications</h4>
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-bordered table-striped small">
                            <tbody>
                                <tr><th>SKU</th><td><?php echo esc($product['sku']); ?></td></tr>
                                <tr><th>Brand</th><td><?php echo esc($product['brand']); ?></td></tr>
                                <tr><th>Category</th><td><?php echo esc($product['category_name']); ?></td></tr>
                                <tr><th>HSN Code</th><td><?php echo esc($product['hsn_code']); ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-bordered table-striped small">
                            <tbody>
                                <tr><th>Condition</th><td><?php echo ucfirst($product['condition']); ?></td></tr>
                                <tr><th>Warranty</th><td><?php echo $product['warranty_months']; ?> Months</td></tr>
                                <tr><th>Replacement Deposit</th><td><?php echo format_price($product['core_charge']); ?> (Refundable)</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <h5 class="fw-bold mt-4 mb-2">Description</h5>
                <p class="text-secondary"><?php echo nl2br(esc($product['description'])); ?></p>
            </div>
        </div>
    </div>

    <!-- Smart Cross-Sells Widget -->
    <div class="row mt-5">
        <div class="col-12">
            <h4 class="fw-bold mb-3"><i class="fa fa-gift text-orange me-2"></i>Complete the Repair / Recommended Products</h4>
            <div class="row g-3">
                <?php foreach ($cross_sells as $cross): ?>
                    <?php 
                    $c_imgs = json_decode($cross['images'] ?? '[]');
                    $c_img = (is_array($c_imgs) && count($c_imgs) > 0) ? $c_imgs[0] : 'no-image.jpg';
                    ?>
                    <div class="col-lg-3 col-md-4 col-6">
                        <div class="card h-100 border shadow-sm product-card">
                            <div class="card-img-container" style="height:120px;">
                                <img src="assets/uploads/<?php echo $c_img; ?>" alt="<?php echo esc($cross['name']); ?>" onerror="this.src='https://placehold.co/150x150?text=Auto+Part'">
                            </div>
                            <div class="card-body p-2 d-flex flex-column">
                                <h6 class="fw-bold text-dark text-truncate mb-1" style="font-size:13px;"><?php echo esc($cross['name']); ?></h6>
                                <div class="text-orange fw-bold mb-2 small"><?php echo format_price($cross['price']); ?></div>
                                <a href="product-details.php?slug=<?php echo $cross['slug']; ?>" class="btn btn-accent btn-xs w-100 mt-auto py-1" style="font-size: 11px;">View Part</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Star Ratings & Customer Reviews Section -->
    <div class="row mt-5">
        <div class="col-md-8">
            <div class="bg-white p-4 border rounded shadow-sm">
                <h4 class="fw-bold mb-4">Customer Reviews</h4>
                
                <!-- Add Review Form (AJAX submission) -->
                <?php if (is_logged_in()): ?>
                    <?php if ($is_verified_buyer): ?>
                        <div class="card bg-light p-3 mb-4">
                            <h5 class="fw-bold mb-2 text-primary">Write a Review</h5>
                            <form id="reviewSubmitForm">
                                <?php echo csrf_input(); ?>
                                <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Star Rating *</label>
                                    <div class="fs-4 text-warning cursor-pointer">
                                        <i class="far fa-star rating-star" data-rating="1"></i>
                                        <i class="far fa-star rating-star" data-rating="2"></i>
                                        <i class="far fa-star rating-star" data-rating="3"></i>
                                        <i class="far fa-star rating-star" data-rating="4"></i>
                                        <i class="far fa-star rating-star" data-rating="5"></i>
                                    </div>
                                    <input type="hidden" name="rating" id="reviewRatingInput" value="" required>
                                </div>
                                <div class="mb-3">
                                    <label for="reviewComment" class="form-label small fw-bold">Comment</label>
                                    <textarea class="form-control form-control-sm" id="reviewComment" name="comment" rows="3" placeholder="Tell us about the fitment quality, shipping times, etc."></textarea>
                                </div>
                                <button type="submit" class="btn btn-accent btn-sm px-4">Submit Review</button>
                            </form>
                            <div id="reviewFeedback" class="mt-2 small" style="display:none;"></div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-light border small text-muted mb-4">
                            <i class="fa fa-info-circle me-1"></i>Only verified purchasers who have had this part delivered can write a review.
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-light border small text-muted mb-4">
                        <i class="fa fa-info-circle me-1"></i>Please <a href="login.php">log in</a> to write a review.
                    </div>
                <?php endif; ?>

                <!-- Reviews List -->
                <div id="reviewsList" class="d-flex flex-column gap-3">
                    <?php if (count($reviews) > 0): ?>
                        <?php foreach ($reviews as $rev): ?>
                            <div class="border-bottom pb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="fw-bold mb-0 text-dark"><?php echo esc($rev['name']); ?></h6>
                                    <small class="text-muted"><?php echo date('d M Y', strtotime($rev['created_at'])); ?></small>
                                </div>
                                <div class="text-warning my-1 small">
                                    <?php for($i=1; $i<=5; $i++): ?>
                                        <i class="fa<?php echo ($i <= $rev['rating']) ? 's' : 'r'; ?> fa-star"></i>
                                    <?php endfor; ?>
                                    <?php if ($rev['verified_purchase']): ?>
                                        <span class="badge bg-success ms-2 text-xs" style="font-size:10px;"><i class="fa fa-check"></i> Verified Purchase</span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-secondary small mb-0"><?php echo esc($rev['comment']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted small">No customer reviews yet. Be the first to leave a review!</p>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

</div>

<!-- Fitment JS is required if no active vehicle is set -->
<?php if (!isset($_SESSION['active_vehicle'])): ?>
    <script src="assets/js/fitment.js"></script>
<?php endif; ?>

<script>
$(document).ready(function() {
    // Gallery Switcher
    $('.gallery-thumb').on('click', function() {
        var src = $(this).data('src');
        $('#mainProductImage').attr('src', src);
    });

    // Installation Toggle
    $('#toggleInstallation').on('change', function() {
        if ($(this).is(':checked')) {
            $('#installationDetails').slideDown();
            $('#garageSelect').attr('required', true);
            $('#bookingDateInput').attr('required', true);
        } else {
            $('#installationDetails').slideUp();
            $('#garageSelect').attr('required', false);
            $('#bookingDateInput').attr('required', false);
            $('#garageSelect').val('');
            $('#bookingDateInput').val('');
        }
    });

    // AJAX Add to Cart form submit
    $('#addToCartForm').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        formData += '&action=add';

        $.ajax({
            url: 'api/cart.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Show Bootstrap Toast
                    var myToast = new bootstrap.Toast(document.getElementById('cartToast'));
                    myToast.show();
                    
                    // Reload navbar cart quantity badge by calling a page refresh after 1s or update manually
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    alert('Add to Cart Failed: ' + response.message);
                }
            },
            error: function() {
                alert('Connection failure adding item to cart.');
            }
        });
    });

    // Rating star handler
    $('.rating-star').on('click', function() {
        var rating = $(this).data('rating');
        $('#reviewRatingInput').val(rating);
        
        // Highlight stars
        $('.rating-star').removeClass('fas text-warning').addClass('far');
        for (var i = 1; i <= rating; i++) {
            $(`.rating-star[data-rating="${i}"]`).removeClass('far').addClass('fas text-warning');
        }
    });

    // AJAX Review submission
    $('#reviewSubmitForm').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var feedback = $('#reviewFeedback');
        
        $.ajax({
            url: 'api/review.php',
            type: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function(response) {
                feedback.removeClass('alert-danger alert-success').hide();
                if (response.success) {
                    feedback.addClass('alert alert-success').text(response.message).show();
                    form.slideUp();
                    
                    // Append review to list
                    var starHtml = '';
                    for (var i = 1; i <= 5; i++) {
                        starHtml += `<i class="${i <= response.review.rating ? 'fas' : 'far'} fa-star"></i>`;
                    }
                    var verifiedBadge = response.review.verified ? '<span class="badge bg-success ms-2 text-xs" style="font-size:10px;"><i class="fa fa-check"></i> Verified Purchase</span>' : '';
                    
                    var newReviewHtml = `
                        <div class="border-bottom pb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="fw-bold mb-0 text-dark">${response.review.name}</h6>
                                <small class="text-muted">${response.review.date}</small>
                            </div>
                            <div class="text-warning my-1 small">
                                ${starHtml}
                                ${verifiedBadge}
                            </div>
                            <p class="text-secondary small mb-0">${response.review.comment}</p>
                        </div>
                    `;
                    $('#reviewsList').prepend(newReviewHtml);
                } else {
                    feedback.addClass('alert alert-danger').text(response.message).show();
                }
            },
            error: function() {
                feedback.addClass('alert alert-danger').text('Connection error.').show();
            }
        });
    });
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
