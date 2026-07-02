<?php
/**
 * Namma AutoParts - Shopping Cart Page
 */
$page_title = "Shopping Cart";
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';

$cart_items = [];
$subtotal = 0.00;
$total_core_charge = 0.00;
$installation_fee = 0.00;

try {
    if (is_logged_in()) {
        // Fetch cart items from DB
        $user_id = $_SESSION['user_id'];
        $sql = "SELECT c.id as cart_id, c.qty, c.booking_date, p.*, g.name as garage_name, g.location as garage_location 
                FROM cart c 
                JOIN products p ON c.product_id = p.id 
                LEFT JOIN garages g ON c.garage_id = g.id 
                WHERE c.user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $cart_items = $stmt->fetchAll();
    } else {
        // Fetch cart items from Session
        if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
            foreach ($_SESSION['cart'] as $p_id => $item) {
                $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                $stmt->execute([$p_id]);
                $prod = $stmt->fetch();
                if ($prod) {
                    $garage_name = '';
                    $garage_location = '';
                    if (!empty($item['garage_id'])) {
                        $g_stmt = $pdo->prepare("SELECT name, location FROM garages WHERE id = ?");
                        $g_stmt->execute([$item['garage_id']]);
                        $g = $g_stmt->fetch();
                        if ($g) {
                            $garage_name = $g['name'];
                            $garage_location = $g['location'];
                        }
                    }

                    // Structure matching database rows for uniformity
                    $prod['cart_id'] = 0; // indicates session
                    $prod['qty'] = $item['qty'];
                    $prod['garage_id'] = $item['garage_id'];
                    $prod['booking_date'] = $item['booking_date'];
                    $prod['garage_name'] = $garage_name;
                    $prod['garage_location'] = $garage_location;
                    $cart_items[] = $prod;
                }
            }
        }
    }

    // Calculations
    foreach ($cart_items as $item) {
        $price = ($item['discount_price'] > 0) ? $item['discount_price'] : $item['price'];
        $subtotal += $price * $item['qty'];
        
        if ($item['core_charge'] > 0) {
            $total_core_charge += $item['core_charge'] * $item['qty'];
        }

        if (!empty($item['garage_id'])) {
            $installation_fee += 1000.00 * $item['qty']; // Flat ₹1,000 fee per item installation
        }
    }
    
    $grand_total = $subtotal + $total_core_charge + $installation_fee;

} catch (PDOException $e) {
    die("Database Error: " . htmlspecialchars($e->getMessage()));
}
?>

<div class="container my-4">
    <h2 class="fw-bold mb-4"><i class="fa fa-shopping-cart text-orange me-2"></i>Shopping Cart</h2>

    <div class="row g-4">
        <!-- Cart Items Column -->
        <div class="col-lg-8">
            <div class="bg-white p-4 border rounded shadow-sm">
                <?php if (count($cart_items) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-cart align-middle">
                            <thead>
                                <tr>
                                    <th scope="col">Product</th>
                                    <th scope="col" class="text-center">Quantity</th>
                                    <th scope="col" class="text-end">Price</th>
                                    <th scope="col" class="text-end">Total</th>
                                    <th scope="col"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cart_items as $item): ?>
                                    <?php 
                                    $item_price = ($item['discount_price'] > 0) ? $item['discount_price'] : $item['price'];
                                    $item_total = $item_price * $item['qty'];
                                    $imgs = json_decode($item['images'] ?? '[]');
                                    $img = (is_array($imgs) && count($imgs) > 0) ? $imgs[0] : 'no-image.jpg';
                                    ?>
                                    <tr class="cart-row-item" data-cart-id="<?php echo $item['cart_id']; ?>" data-product-id="<?php echo $item['id']; ?>">
                                        <!-- Product Detail -->
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <div style="width: 60px; height: 60px; background: #eee;" class="rounded overflow-hidden flex-shrink-0 d-flex align-items-center justify-content-center">
                                                    <img src="assets/uploads/<?php echo $img; ?>" alt="<?php echo esc($item['name']); ?>" class="img-fluid" onerror="this.src='https://placehold.co/60x60?text=Part'">
                                                </div>
                                                <div>
                                                    <h6 class="fw-bold mb-0" style="font-size: 14px;">
                                                        <a href="product-details.php?slug=<?php echo $item['slug']; ?>" class="text-decoration-none text-dark hover-orange"><?php echo esc($item['name']); ?></a>
                                                    </h6>
                                                    <small class="text-muted d-block" style="font-size: 11px;">SKU: <?php echo esc($item['sku']); ?> | Brand: <?php echo esc($item['brand']); ?></small>
                                                    
                                                    <!-- Condition Badge -->
                                                    <?php if ($item['condition'] === 'new'): ?>
                                                        <span class="badge bg-success text-xs" style="font-size:9px;">NEW</span>
                                                    <?php elseif ($item['condition'] === 'used'): ?>
                                                        <span class="badge bg-warning text-dark text-xs" style="font-size:9px;">USED</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-info text-xs" style="font-size:9px;">REFURBISHED</span>
                                                    <?php endif; ?>

                                                    <!-- Core charge badge inside name -->
                                                    <?php if ($item['core_charge'] > 0): ?>
                                                        <span class="badge bg-danger text-xs ms-1" style="font-size:9px;" title="Refundable Deposit">+₹<?php echo number_format($item['core_charge'], 0); ?> Core Charge</span>
                                                    <?php endif; ?>

                                                    <!-- Garage Booking Details -->
                                                    <?php if (!empty($item['garage_id'])): ?>
                                                        <div class="mt-2 p-1 bg-light border border-dashed rounded text-primary" style="font-size: 11px;">
                                                            <i class="fa fa-wrench me-1"></i>Installation: <strong><?php echo esc($item['garage_name']); ?></strong>
                                                            <span class="d-block text-muted">Date: <?php echo date('d M Y, h:i A', strtotime($item['booking_date'])); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        
                                        <!-- Quantity Adjuster -->
                                        <td style="width: 130px;">
                                            <div class="input-group input-group-sm qty-control mx-auto">
                                                <button class="btn btn-outline-secondary btn-qty-adjust" data-dir="minus" type="button"><i class="fa fa-minus"></i></button>
                                                <input type="text" class="form-control text-center input-qty" value="<?php echo $item['qty']; ?>" readonly>
                                                <button class="btn btn-outline-secondary btn-qty-adjust" data-dir="plus" type="button" data-max="<?php echo $item['stock_qty']; ?>"><i class="fa fa-plus"></i></button>
                                            </div>
                                        </td>
                                        
                                        <!-- Price -->
                                        <td class="text-end" style="width: 110px;">
                                            <span class="small"><?php echo format_price($item_price); ?></span>
                                        </td>
                                        
                                        <!-- Total -->
                                        <td class="text-end fw-bold text-dark" style="width: 110px;">
                                            <span class="small item-line-total">₹<?php echo number_format($item_total, 2); ?></span>
                                        </td>
                                        
                                        <!-- Remove Action -->
                                        <td class="text-end" style="width: 40px;">
                                            <button class="btn btn-sm btn-outline-danger btn-cart-remove" type="button"><i class="fa fa-trash-alt"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fa fa-shopping-cart fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Your shopping cart is empty.</p>
                        <a href="shop.php" class="btn btn-accent btn-sm px-4"><i class="fa fa-shopping-bag me-2"></i>Shop Spare Parts</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Summary Column -->
        <?php if (count($cart_items) > 0): ?>
            <div class="col-lg-4">
                <div class="bg-white p-4 border rounded shadow-sm">
                    <h5 class="fw-bold mb-3 text-dark">Order Summary</h5>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">Parts Subtotal</span>
                        <span class="small fw-bold" id="summarySubtotal"><?php echo format_price($subtotal); ?></span>
                    </div>

                    <?php if ($total_core_charge > 0): ?>
                        <div class="d-flex justify-content-between mb-2 text-danger">
                            <span class="small"><i class="fa fa-info-circle me-1" title="Refundable deposit"></i>Core Charges (Refundable)</span>
                            <span class="small fw-bold" id="summaryCoreCharge">+<?php echo format_price($total_core_charge); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($installation_fee > 0): ?>
                        <div class="d-flex justify-content-between mb-2 text-primary">
                            <span class="small"><i class="fa fa-wrench me-1"></i>Garage Fitting Fee</span>
                            <span class="small fw-bold" id="summaryInstallation">+<?php echo format_price($installation_fee); ?></span>
                        </div>
                    <?php endif; ?>

                    <hr class="my-3">

                    <div class="d-flex justify-content-between mb-4">
                        <span class="fw-bold">Estimated Total</span>
                        <span class="fs-4 fw-bold text-orange" id="summaryGrandTotal"><?php echo format_price($grand_total); ?></span>
                    </div>

                    <div class="d-grid gap-2">
                        <a href="checkout.php" class="btn btn-accent btn-lg"><i class="fa fa-check-double me-2"></i>Proceed to Checkout</a>
                        <a href="shop.php" class="btn btn-outline-secondary btn-sm"><i class="fa fa-arrow-left me-2"></i>Continue Shopping</a>
                    </div>

                    <div class="text-xs text-muted text-center mt-3">
                        * GST split (CGST+SGST/IGST) and loyalty point deductions will be computed at the next step.
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
$(document).ready(function() {
    // Quantity adjustments
    $('.btn-qty-adjust').on('click', function() {
        var btn = $(this);
        var dir = btn.data('dir');
        var row = btn.closest('.cart-row-item');
        var cartId = row.data('cart-id');
        var productId = row.data('product-id');
        var input = row.find('.input-qty');
        var currentQty = parseInt(input.val());
        var maxVal = parseInt(btn.data('max')) || 99;

        var newQty = currentQty;
        if (dir === 'plus') {
            if (currentQty < maxVal) {
                newQty = currentQty + 1;
            } else {
                alert('Cannot exceed available stock limit (' + maxVal + ')');
                return;
            }
        } else {
            if (currentQty > 1) {
                newQty = currentQty - 1;
            } else {
                return;
            }
        }

        // Call update API
        $.ajax({
            url: 'api/cart.php',
            type: 'POST',
            data: {
                action: 'update',
                cart_id: cartId,
                product_id: productId,
                qty: newQty
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    input.val(response.qty);
                    // Simply reload to recalculate totals correctly
                    location.reload();
                } else {
                    alert('Update failed: ' + response.message);
                }
            }
        });
    });

    // Remove Item
    $('.btn-cart-remove').on('click', function() {
        if (!confirm('Are you sure you want to remove this item from your cart?')) return;
        
        var btn = $(this);
        var row = btn.closest('.cart-row-item');
        var cartId = row.data('cart-id');
        var productId = row.data('product-id');

        $.ajax({
            url: 'api/cart.php',
            type: 'POST',
            data: {
                action: 'remove',
                cart_id: cartId,
                product_id: productId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    row.fadeOut(300, function() {
                        location.reload();
                    });
                } else {
                    alert('Remove failed: ' + response.message);
                }
            }
        });
    });
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
