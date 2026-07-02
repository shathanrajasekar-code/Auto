<?php
/**
 * Namma AutoParts - Vendor Orders log & Core Charge Return Portal
 */
require_once __DIR__ . '/includes/header.php';

$vendor_id = $_SESSION['vendor_id'];
$order_no = isset($_GET['order_no']) ? clean_input($_GET['order_no']) : '';
$error = '';
$success = '';

// Core return approval / rejection trigger handler
if (isset($_POST['action']) && $_POST['action'] === 'process_core') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = "CSRF verification failed.";
    } else {
        $item_id = intval($_POST['order_item_id']);
        $decision = clean_input($_POST['decision']); // Approved, Rejected

        if ($item_id > 0 && in_array($decision, ['Approved', 'Rejected'])) {
            try {
                $pdo->beginTransaction();

                // Fetch item and order details to verify vendor owns it
                $chk_stmt = $pdo->prepare("SELECT oi.*, p.name as product_name, o.user_id, o.order_no 
                                           FROM order_items oi 
                                           JOIN products p ON oi.product_id = p.id 
                                           JOIN orders o ON oi.order_id = o.id
                                           WHERE oi.id = ? AND p.vendor_id = ?");
                $chk_stmt->execute([$item_id, $vendor_id]);
                $item = $chk_stmt->fetch();

                if (!$item) {
                    throw new Exception("Unauthorized or invalid item access.");
                }

                // Update core charge return state
                $up = $pdo->prepare("UPDATE order_items SET core_charge_returned = ? WHERE id = ?");
                $up->execute([$decision, $item_id]);

                // If Approved, refund core charge as loyalty points to customer
                if ($decision === 'Approved' && $item['core_charge'] > 0) {
                    $refund_points = intval($item['core_charge'] * $item['qty']); // 1 point = 1 rupee
                    
                    // Credit customer points
                    $pdo->prepare("UPDATE users SET loyalty_points = loyalty_points + ? WHERE id = ?")->execute([$refund_points, $item['user_id']]);
                    
                    // Insert Notification
                    $msg = "Your core return refund request for '{$item['product_name']}' has been APPROVED. {$refund_points} loyalty points credited to your account.";
                    $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")->execute([$item['user_id'], $msg]);
                } else {
                    // Rejected notification
                    $msg = "Your core return request for '{$item['product_name']}' has been REJECTED by Coimbatore Spares due to inspection failure.";
                    $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")->execute([$item['user_id'], $msg]);
                }

                $pdo->commit();
                $success = "Core charge status updated to {$decision} successfully.";
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "System Error: " . $e->getMessage();
            }
        }
    }
}

// Update Order Shipment Status Handler
if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = "CSRF verification failed.";
    } else {
        $order_id = intval($_POST['order_id']);
        $new_status = clean_input($_POST['status']); // Placed, Packed, Shipped, Delivered

        if ($order_id > 0 && in_array($new_status, ['Placed', 'Packed', 'Shipped', 'Delivered'])) {
            try {
                $up = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
                $up->execute([$new_status, $order_id]);
                
                // Fetch user ID to notify
                $u_stmt = $pdo->prepare("SELECT user_id, order_no FROM orders WHERE id = ?");
                $u_stmt->execute([$order_id]);
                $ord = $u_stmt->fetch();
                
                if ($ord && $ord['user_id']) {
                    $msg = "Your order #{$ord['order_no']} has been updated to: {$new_status}.";
                    $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")->execute([$ord['user_id'], $msg]);
                }

                $success = "Order status updated to '{$new_status}' successfully.";
            } catch (PDOException $e) {
                $error = "Database Error: " . $e->getMessage();
            }
        }
    }
}

// Fetch lists
try {
    if (!empty($order_no)) {
        // Fetch specific order details (restricted to vendor items)
        $ord_stmt = $pdo->prepare("SELECT o.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone 
                                   FROM orders o 
                                   JOIN users u ON o.user_id = u.id 
                                   WHERE o.order_no = ?");
        $ord_stmt->execute([$order_no]);
        $order = $ord_stmt->fetch();

        if (!$order) {
            die("Order not found.");
        }

        // Fetch only products belonging to this vendor in the order
        $items_stmt = $pdo->prepare("SELECT oi.*, p.name as product_name, p.sku, p.brand, p.condition 
                                     FROM order_items oi 
                                     JOIN products p ON oi.product_id = p.id 
                                     WHERE oi.order_id = ? AND p.vendor_id = ?");
        $items_stmt->execute([$order['id'], $vendor_id]);
        $order_items = $items_stmt->fetchAll();

        if (count($order_items) === 0) {
            die("Unauthorized order access.");
        }
    } else {
        // Fetch all orders containing vendor products
        $list_stmt = $pdo->prepare("SELECT DISTINCT o.* 
                                    FROM orders o 
                                    JOIN order_items oi ON o.id = oi.order_id 
                                    JOIN products p ON oi.product_id = p.id 
                                    WHERE p.vendor_id = ? 
                                    ORDER BY o.created_at DESC");
        $list_stmt->execute([$vendor_id]);
        $vendor_orders = $list_stmt->fetchAll();
    }
} catch (PDOException $e) {
    die("Database Query Error: " . $e->getMessage());
}
?>

<div class="card p-4 border shadow-sm bg-white">
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fa fa-exclamation-circle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa fa-check-circle me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- 1. Detailed order view -->
    <?php if (!empty($order_no)): ?>
        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
            <h5 class="fw-bold mb-0 text-dark">Process Order: #<?php echo esc($order['order_no']); ?></h5>
            <a href="orders.php" class="btn btn-sm btn-outline-secondary"><i class="fa fa-arrow-left me-1"></i>Back to log</a>
        </div>

        <div class="row g-4 mb-4">
            <!-- Customer Shipping details -->
            <div class="col-md-6">
                <div class="p-3 bg-light rounded border h-100">
                    <h6 class="fw-bold mb-2 text-dark"><i class="fa fa-user me-2"></i>Customer Information</h6>
                    <table class="table table-sm table-borderless mb-0 text-xs">
                        <tbody>
                            <tr><th>Name:</th><td><?php echo esc($order['customer_name']); ?></td></tr>
                            <tr><th>Phone:</th><td><?php echo esc($order['customer_phone']); ?></td></tr>
                            <tr><th>Email:</th><td><?php echo esc($order['customer_email']); ?></td></tr>
                            <tr><th>Address:</th><td><?php echo esc($order['shipping_address']); ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Shipping Status Update -->
            <div class="col-md-6">
                <div class="p-3 bg-light rounded border h-100">
                    <h6 class="fw-bold mb-2 text-dark"><i class="fa fa-shipping-fast me-2"></i>Shipment Status</h6>
                    <form method="POST" action="orders.php?order_no=<?php echo $order_no; ?>">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                        
                        <div class="mb-3">
                            <label for="status" class="form-label text-muted small mb-1">Update global order shipment status:</label>
                            <select class="form-select form-select-sm" name="status" id="status" required>
                                <option value="Placed" <?php echo $order['status'] === 'Placed' ? 'selected' : ''; ?>>Placed</option>
                                <option value="Packed" <?php echo $order['status'] === 'Packed' ? 'selected' : ''; ?>>Packed</option>
                                <option value="Shipped" <?php echo $order['status'] === 'Shipped' ? 'selected' : ''; ?>>Shipped</option>
                                <option value="Delivered" <?php echo $order['status'] === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-accent btn-sm px-4">Update Status</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Ordered Items Owned by Vendor -->
        <h6 class="fw-bold mb-3 text-dark border-bottom pb-2">Ordered Spare Parts:</h6>
        <div class="table-responsive">
            <table class="table align-middle small mb-4">
                <thead>
                    <tr>
                        <th>Part</th>
                        <th>SKU</th>
                        <th class="text-center">Qty</th>
                        <th class="text-end">Base Price</th>
                        <th class="text-end">Core Charge</th>
                        <th>Core Return Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order_items as $item): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc($item['product_name']); ?></strong><br>
                                <span class="badge bg-light text-dark border"><?php echo ucfirst($item['condition']); ?></span>
                            </td>
                            <td><code><?php echo esc($item['sku']); ?></code></td>
                            <td class="text-center"><?php echo $item['qty']; ?></td>
                            <td class="text-end"><?php echo format_price($item['price']); ?></td>
                            <td class="text-end"><?php echo format_price($item['core_charge']); ?></td>
                            
                            <!-- Core return validation buttons -->
                            <td>
                                <?php if ($item['core_charge_returned'] === 'Pending Return'): ?>
                                    <div class="d-flex gap-2">
                                        <form method="POST" action="orders.php?order_no=<?php echo $order_no; ?>" class="d-inline-block">
                                            <?php echo csrf_input(); ?>
                                            <input type="hidden" name="action" value="process_core">
                                            <input type="hidden" name="order_item_id" value="<?php echo $item['id']; ?>">
                                            <input type="hidden" name="decision" value="Approved">
                                            <button type="submit" class="btn btn-xs btn-success py-1 px-2" style="font-size:10px;" onclick="return confirm('Approve core inspection refund?')"><i class="fa fa-check"></i> Approve Refund</button>
                                        </form>
                                        <form method="POST" action="orders.php?order_no=<?php echo $order_no; ?>" class="d-inline-block">
                                            <?php echo csrf_input(); ?>
                                            <input type="hidden" name="action" value="process_core">
                                            <input type="hidden" name="order_item_id" value="<?php echo $item['id']; ?>">
                                            <input type="hidden" name="decision" value="Rejected">
                                            <button type="submit" class="btn btn-xs btn-danger py-1 px-2" style="font-size:10px;" onclick="return confirm('Reject core refund request?')"><i class="fa fa-times"></i> Reject</button>
                                        </form>
                                    </div>
                                <?php elseif ($item['core_charge_returned'] === 'Approved'): ?>
                                    <span class="badge bg-success"><i class="fa fa-check"></i> Refund Approved</span>
                                <?php elseif ($item['core_charge_returned'] === 'Rejected'): ?>
                                    <span class="badge bg-danger"><i class="fa fa-times"></i> Refund Rejected</span>
                                <?php else: ?>
                                    <span class="text-muted text-xs">Not Applicable</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <!-- 2. Orders Log List -->
    <?php else: ?>
        <h5 class="fw-bold mb-4 text-dark">My Incoming Orders Log</h5>

        <div class="table-responsive">
            <table class="table align-middle small table-hover">
                <thead>
                    <tr>
                        <th>Order No</th>
                        <th>Date Placed</th>
                        <th>Method</th>
                        <th>Invoice Value</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($vendor_orders) > 0): ?>
                        <?php foreach ($vendor_orders as $o): ?>
                            <tr>
                                <td><strong><?php echo esc($o['order_no']); ?></strong></td>
                                <td><?php echo date('d M Y, h:i A', strtotime($o['created_at'])); ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo esc($o['payment_method']); ?></span></td>
                                <td><span class="fw-bold text-orange"><?php echo format_price($o['total_amount']); ?></span></td>
                                <td class="text-center">
                                    <?php 
                                    $st_color = 'bg-secondary';
                                    if ($o['status'] === 'Placed') $st_color = 'bg-primary';
                                    elseif ($o['status'] === 'Packed') $st_color = 'bg-info';
                                    elseif ($o['status'] === 'Shipped') $st_color = 'bg-warning text-dark';
                                    elseif ($o['status'] === 'Delivered') $st_color = 'bg-success';
                                    elseif ($o['status'] === 'Cancelled') $st_color = 'bg-danger';
                                    ?>
                                    <span class="badge <?php echo $st_color; ?>"><?php echo esc($o['status']); ?></span>
                                </td>
                                <td class="text-end">
                                    <a href="orders.php?order_no=<?php echo esc($o['order_no']); ?>" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size:11px;"><i class="fa fa-cog"></i> Process Order</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">You have not received any orders. Keep updating your listings!</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
