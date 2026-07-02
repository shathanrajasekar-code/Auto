<?php
/**
 * Namma AutoParts - Order Checkout Page
 */
$page_title = "Checkout";
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// Retrieve cart items
$cart_items = [];
$subtotal = 0.00;
$total_core_charge = 0.00;
$installation_fee = 0.00;
$user_id = is_logged_in() ? $_SESSION['user_id'] : null;

// Get user profile if logged in
$user_profile = null;
if (is_logged_in()) {
    $u_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $u_stmt->execute([$user_id]);
    $user_profile = $u_stmt->fetch();
}

try {
    if ($user_id) {
        $sql = "SELECT c.id as cart_id, c.qty, c.garage_id, c.booking_date, p.*, g.name as garage_name 
                FROM cart c 
                JOIN products p ON c.product_id = p.id 
                LEFT JOIN garages g ON c.garage_id = g.id 
                WHERE c.user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $cart_items = $stmt->fetchAll();
    } else {
        if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
            foreach ($_SESSION['cart'] as $p_id => $item) {
                $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                $stmt->execute([$p_id]);
                $prod = $stmt->fetch();
                if ($prod) {
                    $prod['cart_id'] = 0;
                    $prod['qty'] = $item['qty'];
                    $prod['garage_id'] = $item['garage_id'];
                    $prod['booking_date'] = $item['booking_date'];
                    $cart_items[] = $prod;
                }
            }
        }
    }

    if (count($cart_items) === 0) {
        redirect('cart.php');
    }

    // Baseline Calculations
    foreach ($cart_items as $item) {
        $price = ($item['discount_price'] > 0) ? $item['discount_price'] : $item['price'];
        $subtotal += $price * $item['qty'];
        if ($item['core_charge'] > 0) {
            $total_core_charge += $item['core_charge'] * $item['qty'];
        }
        if (!empty($item['garage_id'])) {
            $installation_fee += 1000.00 * $item['qty'];
        }
    }

} catch (PDOException $e) {
    die("Database Error: " . htmlspecialchars($e->getMessage()));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = "CSRF verification failed. Please try again.";
    } else {
        // Collect inputs
        $name = clean_input($_POST['name']);
        $email = clean_input($_POST['email']);
        $phone = clean_input($_POST['phone']);
        $address = clean_input($_POST['address']);
        $state = clean_input($_POST['state']);
        $payment_method = clean_input($_POST['payment_method']);
        $coupon_code = strtoupper(clean_input($_POST['coupon_code'] ?? ''));
        $redeem_points = isset($_POST['redeem_points']) ? 1 : 0;

        // Validation
        if (empty($name) || empty($email) || empty($phone) || empty($address) || empty($state)) {
            $error = "All shipping fields are required.";
        } elseif (!in_array($payment_method, ['COD', 'Card', 'UPI', 'B2B_Credit'])) {
            $error = "Please select a valid payment method.";
        } else {
            try {
                $pdo->beginTransaction();

                // 1. Calculate discount coupon
                $discount = 0.00;
                if (!empty($coupon_code)) {
                    $c_stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND expiry >= CURDATE()");
                    $c_stmt->execute([$coupon_code]);
                    $coupon = $c_stmt->fetch();
                    if ($coupon && $coupon['used_count'] < $coupon['usage_limit']) {
                        if ($coupon['discount_type'] === 'Percentage') {
                            $discount = ($subtotal * $coupon['discount_value']) / 100;
                        } else {
                            $discount = min($subtotal, $coupon['discount_value']);
                        }
                        // Increment coupon count
                        $pdo->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?")->execute([$coupon['id']]);
                    }
                }

                // 2. Calculate Loyalty points redemption
                $points_discount = 0.00;
                $points_to_deduct = 0;
                if ($redeem_points && $user_profile && $user_profile['loyalty_points'] > 0) {
                    $available_points = $user_profile['loyalty_points'];
                    $max_discount = $subtotal - $discount;
                    
                    if ($available_points >= $max_discount) {
                        $points_discount = $max_discount;
                        $points_to_deduct = intval($max_discount);
                    } else {
                        $points_discount = $available_points;
                        $points_to_deduct = $available_points;
                    }
                }

                // Subtotal after discount and points
                $chargeable_subtotal = $subtotal - $discount - $points_discount;
                if ($chargeable_subtotal < 0) $chargeable_subtotal = 0;

                // Grand Total
                $grand_total = $chargeable_subtotal + $total_core_charge + $installation_fee;

                // 3. GST Calculation (Split CGST/SGST/IGST based on state)
                // Default vendor location is Tamil Nadu
                $gst_data = calculate_gst($chargeable_subtotal, $state, 'Tamil Nadu');

                // 4. B2B Credit check
                if ($payment_method === 'B2B_Credit') {
                    if (!$user_profile || $user_profile['is_b2b_approved'] != 1) {
                        $error = "Your account is not approved for B2B Pay Later credit.";
                    } else {
                        $available_credit = $user_profile['b2b_credit_limit'] - $user_profile['b2b_credit_used'];
                        if ($grand_total > $available_credit) {
                            $error = "Insufficient credit line limit. Available: " . format_price($available_credit) . ", Order: " . format_price($grand_total);
                        }
                    }
                }

                if (empty($error)) {
                    // Generate Order Number
                    $order_no = "NMAP-" . date("Ymd") . "-" . rand(1000, 9999);

                    // Insert Order
                    $order_sql = "INSERT INTO orders (user_id, order_no, total_amount, gst_amount, cgst_amount, sgst_amount, igst_amount, status, payment_method, payment_status, shipping_address, shipping_state, coupon_code, discount_amount, loyalty_points_used, loyalty_points_earned) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, 'Placed', ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    // Payment Status
                    $payment_status = ($payment_method === 'B2B_Credit') ? 'Paid' : 'Pending';
                    if ($payment_method === 'Card' || $payment_method === 'UPI') {
                        $payment_status = 'Paid'; // Simulated instant payment
                    }

                    // Earned loyalty points (1 point per ₹100 of base subtotal)
                    $points_earned = calculate_earned_points($chargeable_subtotal);

                    $order_stmt = $pdo->prepare($order_sql);
                    $order_stmt->execute([
                        $user_id, $order_no, $grand_total, $gst_data['total_gst'], 
                        $gst_data['cgst'], $gst_data['sgst'], $gst_data['igst'],
                        $payment_method, $payment_status, $address, $state,
                        (!empty($coupon_code) ? $coupon_code : null), $discount,
                        $points_to_deduct, $points_earned
                    ]);
                    $order_id = $pdo->lastInsertId();

                    // Loop through cart items and insert order items & deduct stock
                    foreach ($cart_items as $item) {
                        $item_price = ($item['discount_price'] > 0) ? $item['discount_price'] : $item['price'];
                        
                        // Core Charge Return state
                        $core_state = ($item['core_charge'] > 0) ? 'Pending Return' : 'Not Applicable';

                        // Insert Order Item
                        $item_stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, qty, price, core_charge, core_charge_returned) VALUES (?, ?, ?, ?, ?, ?)");
                        $item_stmt->execute([$order_id, $item['id'], $item['qty'], $item_price, $item['core_charge'], $core_state]);

                        // Deduct Stock
                        $deduct_stmt = $pdo->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?");
                        $deduct_stmt->execute([$item['qty'], $item['id']]);

                        // Log Inventory Adjustment
                        $inv_stmt = $pdo->prepare("INSERT INTO inventory_log (product_id, change_qty, reason, warehouse_id) VALUES (?, ?, 'Customer Sale', 1)");
                        $inv_stmt->execute([$item['id'], -$item['qty']]);

                        // If partnered garage booking is selected, insert into service_bookings
                        if (!empty($item['garage_id'])) {
                            $booking_stmt = $pdo->prepare("INSERT INTO service_bookings (user_id, garage_id, service_type, vehicle_details, booking_date, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
                            
                            $vehicle_label = 'Car Fitting';
                            if (isset($_SESSION['active_vehicle'])) {
                                $v = $_SESSION['active_vehicle'];
                                $vehicle_label = $v['make'] . ' ' . $v['model'] . ' (' . $v['year_from'] . ')';
                            }
                            
                            $booking_stmt->execute([
                                $user_id ? $user_id : 4, // Default to Customer user if guest checkout
                                $item['garage_id'],
                                "Fitting of part: " . $item['name'],
                                $vehicle_label,
                                $item['booking_date']
                            ]);
                        }
                    }

                    // 5. User Profile changes (Points & referral updates)
                    if ($user_id) {
                        // Deduct points + Add earned points
                        $new_user_points = $user_profile['loyalty_points'] - $points_to_deduct + $points_earned;
                        
                        // Check if this is the first order of the user
                        $order_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
                        $order_count_stmt->execute([$user_id]);
                        $order_count = $order_count_stmt->fetchColumn();

                        // If first order and referred, award referral points
                        if ($order_count == 1 && !empty($user_profile['referred_by'])) {
                            $referrer_id = $user_profile['referred_by'];
                            
                            // Give referrer 100 points
                            $pdo->prepare("UPDATE users SET loyalty_points = loyalty_points + 100 WHERE id = ?")->execute([$referrer_id]);
                            $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")->execute([$referrer_id, "Referral reward! You earned 100 points because " . $user_profile['name'] . " completed their first order."]);
                            
                            // Give referee 100 points
                            $new_user_points += 100;
                            $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")->execute([$user_id, "First order referral bonus! You earned 100 loyalty points."]);
                        }

                        // Update user points
                        $pdo->prepare("UPDATE users SET loyalty_points = ? WHERE id = ?")->execute([$new_user_points, $user_id]);

                        // Update B2B credit if used
                        if ($payment_method === 'B2B_Credit') {
                            $pdo->prepare("UPDATE users SET b2b_credit_used = b2b_credit_used + ? WHERE id = ?")->execute([$grand_total, $user_id]);
                        }
                    }

                    // 6. Clear Cart
                    if ($user_id) {
                        $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$user_id]);
                    } else {
                        unset($_SESSION['cart']);
                    }

                    $pdo->commit();
                    $_SESSION['last_order_no'] = $order_no;
                    redirect('order-success.php');
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "System Error: " . $e->getMessage();
            }
        }
    }
}
require_once __DIR__ . '/includes/header.php';
?>

<div class="container my-4">
    <h2 class="fw-bold mb-4"><i class="fa fa-credit-card text-orange me-2"></i>Checkout</h2>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fa fa-exclamation-circle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form method="POST" action="checkout.php">
        <?php echo csrf_input(); ?>
        <div class="row g-4">
            <!-- Left Column: Shipping & Payment Address -->
            <div class="col-lg-7">
                <div class="bg-white p-4 border rounded shadow-sm mb-4">
                    <h5 class="fw-bold mb-3 text-dark"><i class="fa fa-truck me-2"></i>Shipping Address</h5>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label small fw-bold">Full Name *</label>
                            <input type="text" class="form-control form-control-sm" id="name" name="name" required value="<?php echo esc($user_profile['name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label small fw-bold">Phone Number *</label>
                            <input type="text" class="form-control form-control-sm" id="phone" name="phone" required value="<?php echo esc($user_profile['phone'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label small fw-bold">Email Address *</label>
                        <input type="email" class="form-control form-control-sm" id="email" name="email" required value="<?php echo esc($user_profile['email'] ?? ''); ?>">
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label small fw-bold">Address Details *</label>
                        <textarea class="form-control form-control-sm" id="address" name="address" rows="3" required placeholder="Street address, block, building name..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="stateSelect" class="form-label small fw-bold">Shipping State *</label>
                        <select class="form-select form-select-sm" name="state" id="stateSelect" required>
                            <option value="Tamil Nadu" selected>Tamil Nadu (CGST + SGST Applies)</option>
                            <option value="Karnataka">Karnataka (IGST Applies)</option>
                            <option value="Kerala">Kerala (IGST Applies)</option>
                            <option value="Andhra Pradesh">Andhra Pradesh (IGST Applies)</option>
                            <option value="Maharashtra">Maharashtra (IGST Applies)</option>
                            <option value="Delhi">Delhi (IGST Applies)</option>
                        </select>
                        <span class="text-xs text-muted">Taxes will adjust dynamically based on shipping state.</span>
                    </div>
                </div>

                <div class="bg-white p-4 border rounded shadow-sm">
                    <h5 class="fw-bold mb-3 text-dark"><i class="fa fa-wallet me-2"></i>Payment Method</h5>
                    
                    <div class="d-flex flex-column gap-3">
                        <div class="form-check p-3 border rounded">
                            <input class="form-check-input" type="radio" name="payment_method" id="payCOD" value="COD" checked>
                            <label class="form-check-label fw-bold text-dark" for="payCOD">
                                <i class="fa fa-money-bill-wave text-success me-2"></i>Cash on Delivery (COD)
                            </label>
                        </div>
                        
                        <div class="form-check p-3 border rounded">
                            <input class="form-check-input" type="radio" name="payment_method" id="payOnline" value="Card">
                            <label class="form-check-label fw-bold text-dark" for="payOnline">
                                <i class="fa fa-credit-card text-primary me-2"></i>Online Payment (Simulated Card / UPI)
                            </label>
                        </div>

                        <!-- B2B Pay Later Credit Options -->
                        <?php if ($user_profile && $user_profile['is_b2b_approved'] == 1): ?>
                            <?php 
                            $available_credit = $user_profile['b2b_credit_limit'] - $user_profile['b2b_credit_used'];
                            $is_credit_disabled = ($available_credit < $subtotal) ? 'disabled' : '';
                            ?>
                            <div class="form-check p-3 border rounded border-success <?php echo $is_credit_disabled; ?>">
                                <input class="form-check-input" type="radio" name="payment_method" id="payB2B" value="B2B_Credit" <?php echo $is_credit_disabled; ?>>
                                <label class="form-check-label fw-bold text-success" for="payB2B">
                                    <i class="fa fa-handshake me-2"></i>Pay Later (B2B Credit Line)
                                </label>
                                <div class="text-xs text-muted mt-1 ms-4">
                                    Approved B2B Garage Account Credit. 
                                    Available Limit: <strong><?php echo format_price($available_credit); ?></strong>
                                    <?php if ($is_credit_disabled): ?>
                                        <span class="text-danger d-block">Error: Order total exceeds your available credit line.</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column: Summary Box with Interactive GST and Loyalty Points -->
            <div class="col-lg-5">
                <div class="bg-white p-4 border rounded shadow-sm mb-4">
                    <h5 class="fw-bold mb-3 text-dark">Order Breakdown</h5>

                    <!-- Cart list mini -->
                    <div class="mb-4">
                        <?php foreach($cart_items as $item): ?>
                            <?php $item_price = ($item['discount_price'] > 0) ? $item['discount_price'] : $item['price']; ?>
                            <div class="d-flex justify-content-between text-xs text-muted mb-2 border-bottom pb-2">
                                <span><?php echo esc($item['name']); ?> <strong>x<?php echo $item['qty']; ?></strong></span>
                                <span class="fw-bold"><?php echo format_price($item_price * $item['qty']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Coupon Code Input -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Promo Coupon</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" name="coupon_code" id="couponInput" placeholder="Enter coupon code">
                            <button class="btn btn-outline-secondary" type="button" id="applyCouponBtn">Apply</button>
                        </div>
                        <div id="couponFeedback" class="small mt-1" style="display:none;"></div>
                    </div>

                    <!-- Loyalty Points Redemption -->
                    <?php if ($user_profile && $user_profile['loyalty_points'] > 0): ?>
                        <div class="mb-3 bg-light p-2 rounded border">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="redeem_points" id="redeemPointsCheck" value="1">
                                <label class="form-check-label fw-bold text-dark small" for="redeemPointsCheck">
                                    Redeem Loyalty Points (Balance: <?php echo $user_profile['loyalty_points']; ?> pts)
                                </label>
                            </div>
                            <span class="text-xs text-muted d-block mt-1">1 point = ₹1 discount on parts.</span>
                        </div>
                    <?php endif; ?>

                    <hr class="my-3">

                    <!-- Breakdown Calculations -->
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">Subtotal</span>
                        <span class="small" id="breakdownSubtotal" data-val="<?php echo $subtotal; ?>"><?php echo format_price($subtotal); ?></span>
                    </div>

                    <div class="d-flex justify-content-between mb-2 text-success" id="couponRow" style="display: none !important;">
                        <span class="text-muted small">Coupon Discount</span>
                        <span class="small fw-bold" id="breakdownCouponDiscount">-₹0.00</span>
                    </div>

                    <div class="d-flex justify-content-between mb-2 text-success" id="pointsRow" style="display: none !important;">
                        <span class="text-muted small">Loyalty Points Discount</span>
                        <span class="small fw-bold" id="breakdownPointsDiscount" data-points="<?php echo $user_profile['loyalty_points'] ?? 0; ?>">-₹0.00</span>
                    </div>

                    <!-- Core Charge -->
                    <?php if ($total_core_charge > 0): ?>
                        <div class="d-flex justify-content-between mb-2 text-danger">
                            <span class="text-muted small">Core Charges (Refundable)</span>
                            <span class="small" id="breakdownCore" data-val="<?php echo $total_core_charge; ?>"><?php echo format_price($total_core_charge); ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Fitting Charge -->
                    <?php if ($installation_fee > 0): ?>
                        <div class="d-flex justify-content-between mb-2 text-primary">
                            <span class="text-muted small">Garage Installation</span>
                            <span class="small" id="breakdownFitting" data-val="<?php echo $installation_fee; ?>"><?php echo format_price($installation_fee); ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- GST Dynamic Split Lines -->
                    <div id="taxSplitContainer" class="bg-light p-2 rounded mb-3 border">
                        <div class="d-flex justify-content-between text-xs text-muted mb-1">
                            <span>Base Amount (Tax Excl.)</span>
                            <span id="breakdownBasePrice">₹0.00</span>
                        </div>
                        <div class="d-flex justify-content-between text-xs text-muted mb-1 tax-line-cgst">
                            <span>CGST (9%)</span>
                            <span id="breakdownCGST">₹0.00</span>
                        </div>
                        <div class="d-flex justify-content-between text-xs text-muted mb-1 tax-line-sgst">
                            <span>SGST (9%)</span>
                            <span id="breakdownSGST">₹0.00</span>
                        </div>
                        <div class="d-flex justify-content-between text-xs text-muted tax-line-igst" style="display:none;">
                            <span>IGST (18%)</span>
                            <span id="breakdownIGST">₹0.00</span>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mb-4">
                        <span class="fw-bold">Grand Total</span>
                        <span class="fs-4 fw-bold text-orange" id="breakdownGrandTotal">₹0.00</span>
                    </div>

                    <button type="submit" class="btn btn-accent btn-lg w-100 py-3"><i class="fa fa-lock me-2"></i>Place Secure Order</button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
$(document).ready(function() {
    // Dynamic price factors
    var subtotal = parseFloat($('#breakdownSubtotal').data('val'));
    var coreCharge = parseFloat($('#breakdownCore').data('val')) || 0.00;
    var fittingCharge = parseFloat($('#breakdownFitting').data('val')) || 0.00;
    
    var couponType = '';
    var couponVal = 0.00;
    var pointsLimit = parseFloat($('#breakdownPointsDiscount').data('points')) || 0;

    // Apply Coupon via AJAX
    $('#applyCouponBtn').on('click', function() {
        var code = $('#couponInput').val().trim();
        var fb = $('#couponFeedback');
        
        if (code.length === 0) return;

        $.ajax({
            url: 'api/coupon.php',
            type: 'GET',
            data: { code: code },
            dataType: 'json',
            success: function(response) {
                fb.removeClass('text-danger text-success').hide();
                if (response.success) {
                    fb.addClass('text-success').text('Coupon applied successfully!').show();
                    couponType = response.discount_type;
                    couponVal = parseFloat(response.discount_value);
                    recalculateTotals();
                } else {
                    fb.addClass('text-danger').text(response.message).show();
                    couponType = '';
                    couponVal = 0.00;
                    recalculateTotals();
                }
            }
        });
    });

    // Points Redemption checkbox listener
    $('#redeemPointsCheck').on('change', function() {
        recalculateTotals();
    });

    // Shipping State Dropdown change listener
    $('#stateSelect').on('change', function() {
        recalculateTotals();
    });

    // Initial Trigger
    recalculateTotals();

    function recalculateTotals() {
        // Calculate coupon discount
        var couponDiscount = 0.00;
        if (couponVal > 0) {
            if (couponType === 'Percentage') {
                couponDiscount = (subtotal * couponVal) / 100;
            } else {
                couponDiscount = Math.min(subtotal, couponVal);
            }
            $('#breakdownCouponDiscount').text('-₹' + couponDiscount.toFixed(2));
            $('#couponRow').attr('style', 'display: flex !important;');
        } else {
            $('#couponRow').attr('style', 'display: none !important;');
        }

        // Calculate points discount
        var pointsDiscount = 0.00;
        var isRedeem = $('#redeemPointsCheck').is(':checked');
        if (isRedeem && pointsLimit > 0) {
            var partsTotalAfterCoupon = subtotal - couponDiscount;
            pointsDiscount = Math.min(partsTotalAfterCoupon, pointsLimit);
            
            $('#breakdownPointsDiscount').text('-₹' + pointsDiscount.toFixed(2));
            $('#pointsRow').attr('style', 'display: flex !important;');
        } else {
            $('#pointsRow').attr('style', 'display: none !important;');
        }

        // Subtotal taxable price (Base Parts Subtotal - coupon - points)
        var taxablePartsTotal = subtotal - couponDiscount - pointsDiscount;
        if (taxablePartsTotal < 0) taxablePartsTotal = 0;

        // GST calculations (Inclusive split)
        var state = $('#stateSelect').val();
        var taxRate = 0.18;
        var baseAmount = taxablePartsTotal / (1 + taxRate);
        var totalGST = taxablePartsTotal - baseAmount;

        $('#breakdownBasePrice').text('₹' + baseAmount.toFixed(2));

        if (state === 'Tamil Nadu') {
            var cgst = totalGST / 2;
            var sgst = totalGST / 2;
            $('#breakdownCGST').text('₹' + cgst.toFixed(2));
            $('#breakdownSGST').text('₹' + sgst.toFixed(2));
            
            $('.tax-line-cgst').show();
            $('.tax-line-sgst').show();
            $('.tax-line-igst').hide();
        } else {
            $('#breakdownIGST').text('₹' + totalGST.toFixed(2));
            
            $('.tax-line-cgst').hide();
            $('.tax-line-sgst').hide();
            $('.tax-line-igst').show();
        }

        // Grand Total
        var grandTotal = taxablePartsTotal + coreCharge + fittingCharge;
        $('#breakdownGrandTotal').text('₹' + grandTotal.toFixed(2));
    }
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
