<?php
/**
 * Namma AutoParts - Vendor Panel Dashboard
 */
require_once __DIR__ . '/includes/header.php';

$vendor_id = $_SESSION['vendor_id'];
$commission_rate = $vendor_info['commission_rate'];

try {
    // 1. Total Sales Metrics
    $sales_stmt = $pdo->prepare("SELECT SUM(oi.price * oi.qty) as parts_val, SUM(oi.core_charge * oi.qty) as cores_val 
                                 FROM order_items oi 
                                 JOIN products p ON oi.product_id = p.id 
                                 JOIN orders o ON oi.order_id = o.id 
                                 WHERE p.vendor_id = ? AND o.status != 'Cancelled'");
    $sales_stmt->execute([$vendor_id]);
    $sales_data = $sales_stmt->fetch();
    
    $parts_sales = $sales_data['parts_val'] ?? 0.00;
    $cores_sales = $sales_data['cores_val'] ?? 0.00;
    $total_gross = $parts_sales + $cores_sales;
    $commission_owed = ($parts_sales * $commission_rate) / 100;
    $net_earnings = $total_gross - $commission_owed;

    // 2. Count Active listings
    $list_stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE vendor_id = ?");
    $list_stmt->execute([$vendor_id]);
    $total_listings = $list_stmt->fetchColumn();

    // 3. Count Low stock items (< 5)
    $stock_stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE vendor_id = ? AND stock_qty < 5");
    $stock_stmt->execute([$vendor_id]);
    $low_stock_count = $stock_stmt->fetchColumn();

    // 4. Recent Orders
    $orders_stmt = $pdo->prepare("SELECT DISTINCT o.* 
                                  FROM orders o 
                                  JOIN order_items oi ON o.id = oi.order_id 
                                  JOIN products p ON oi.product_id = p.id 
                                  WHERE p.vendor_id = ? 
                                  ORDER BY o.created_at DESC LIMIT 5");
    $orders_stmt->execute([$vendor_id]);
    $recent_orders = $orders_stmt->fetchAll();

} catch (PDOException $e) {
    die("Dashboard Query Error: " . $e->getMessage());
}
?>

<div class="row g-4 mb-4">
    <!-- Sales Card -->
    <div class="col-md-3">
        <div class="card p-3 border shadow-sm bg-white">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <small class="text-muted text-uppercase fw-bold">Gross Sales</small>
                <i class="fa fa-coins text-success fa-lg"></i>
            </div>
            <h3 class="fw-bold mb-1 text-dark"><?php echo format_price($total_gross); ?></h3>
            <small class="text-muted">Includes parts & cores</small>
        </div>
    </div>
    
    <!-- Net Earnings -->
    <div class="col-md-3">
        <div class="card p-3 border shadow-sm bg-white">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <small class="text-muted text-uppercase fw-bold">Net Earnings</small>
                <i class="fa fa-hand-holding-usd text-orange fa-lg"></i>
            </div>
            <h3 class="fw-bold mb-1 text-orange"><?php echo format_price($net_earnings); ?></h3>
            <small class="text-muted">Minus <?php echo $commission_rate; ?>% commission</small>
        </div>
    </div>

    <!-- Listings Count -->
    <div class="col-md-3">
        <div class="card p-3 border shadow-sm bg-white">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <small class="text-muted text-uppercase fw-bold">Active Listings</small>
                <i class="fa fa-tools text-primary fa-lg"></i>
            </div>
            <h3 class="fw-bold mb-1 text-dark"><?php echo $total_listings; ?></h3>
            <small class="text-muted">Spare parts listed</small>
        </div>
    </div>

    <!-- Low Stock -->
    <div class="col-md-3">
        <div class="card p-3 border shadow-sm bg-white">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <small class="text-muted text-uppercase fw-bold">Low Stock Alerts</small>
                <i class="fa fa-exclamation-triangle text-danger fa-lg"></i>
            </div>
            <h3 class="fw-bold mb-1 text-danger"><?php echo $low_stock_count; ?></h3>
            <small class="text-muted">Need urgent restock</small>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Recent Orders List -->
    <div class="col-lg-8">
        <div class="card shadow-sm border p-4 bg-white">
            <h5 class="fw-bold mb-3 text-dark"><i class="fa fa-shopping-basket text-orange me-2"></i>Recent Orders</h5>
            
            <div class="table-responsive">
                <table class="table align-middle small mb-0">
                    <thead>
                        <tr>
                            <th>Order No</th>
                            <th>Date</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($recent_orders) > 0): ?>
                            <?php foreach ($recent_orders as $o): ?>
                                <tr>
                                    <td><strong><?php echo esc($o['order_no']); ?></strong></td>
                                    <td><?php echo date('d M Y', strtotime($o['created_at'])); ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?php echo esc($o['payment_method']); ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                        $o_color = 'bg-secondary';
                                        if ($o['status'] === 'Placed') $o_color = 'bg-primary';
                                        elseif ($o['status'] === 'Packed') $o_color = 'bg-info';
                                        elseif ($o['status'] === 'Shipped') $o_color = 'bg-warning text-dark';
                                        elseif ($o['status'] === 'Delivered') $o_color = 'bg-success';
                                        elseif ($o['status'] === 'Cancelled') $o_color = 'bg-danger';
                                        ?>
                                        <span class="badge <?php echo $o_color; ?>"><?php echo esc($o['status']); ?></span>
                                    </td>
                                    <td>
                                        <a href="orders.php?order_no=<?php echo esc($o['order_no']); ?>" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 11px;">Process</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No orders received yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Quick Actions Panel -->
    <div class="col-lg-4">
        <div class="card shadow-sm border p-4 bg-white text-center">
            <h5 class="fw-bold mb-3 text-dark">Quick Operations</h5>
            <div class="d-flex flex-column gap-2">
                <a href="products.php?action=add" class="btn btn-accent"><i class="fa fa-plus me-1"></i>List New Part</a>
                <a href="products.php" class="btn btn-outline-primary"><i class="fa fa-warehouse me-1"></i>Adjust Stock</a>
                <a href="orders.php" class="btn btn-outline-secondary"><i class="fa fa-undo me-1"></i>Core Refund Requests</a>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
