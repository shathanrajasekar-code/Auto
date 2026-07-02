<?php
/**
 * Namma AutoParts - Admin Analytics & Reporting Dashboard
 */
require_once __DIR__ . '/includes/header.php';

try {
    // 1. Core KPIs
    $sales_kpi = $pdo->query("SELECT SUM(total_amount) as rev, COUNT(id) as count FROM orders WHERE status != 'Cancelled'")->fetch();
    $cust_kpi = $pdo->query("SELECT COUNT(id) as count FROM users WHERE role = 'customer'")->fetch();
    $vend_kpi = $pdo->query("SELECT COUNT(id) as count FROM vendors WHERE status = 'approved'")->fetch();
    $low_stock_kpi = $pdo->query("SELECT COUNT(id) as count FROM products WHERE stock_qty < 5")->fetch();

    // 2. Sales Trend (Last 7 Days)
    $trend_stmt = $pdo->query("SELECT DATE(created_at) as order_date, SUM(total_amount) as revenue 
                               FROM orders 
                               WHERE status != 'Cancelled' 
                               GROUP BY order_date 
                               ORDER BY order_date ASC LIMIT 7");
    $trend_data = $trend_stmt->fetchAll();
    
    // Prepare arrays for ChartJS
    $trend_labels = [];
    $trend_values = [];
    foreach ($trend_data as $row) {
        $trend_labels[] = date('d M', strtotime($row['order_date']));
        $trend_values[] = floatval($row['revenue']);
    }

    // 3. Category Split Revenue
    $cat_stmt = $pdo->query("SELECT c.name as cat_name, SUM(oi.price * oi.qty) as revenue 
                             FROM order_items oi 
                             JOIN products p ON oi.product_id = p.id 
                             JOIN categories c ON p.category_id = c.id 
                             GROUP BY c.id");
    $cat_data = $cat_stmt->fetchAll();
    
    $cat_labels = [];
    $cat_values = [];
    foreach ($cat_data as $row) {
        $cat_labels[] = $row['cat_name'];
        $cat_values[] = floatval($row['revenue']);
    }

    // 4. Low stock products list
    $low_stock_stmt = $pdo->query("SELECT p.*, v.shop_name 
                                   FROM products p 
                                   LEFT JOIN vendors v ON p.vendor_id = v.id 
                                   WHERE p.stock_qty < 5 
                                   ORDER BY p.stock_qty ASC LIMIT 5");
    $low_stock_products = $low_stock_stmt->fetchAll();

    // 5. Top-selling parts leaderboard
    $top_stmt = $pdo->query("SELECT p.name, p.brand, p.sku, SUM(oi.qty) as total_sold, SUM(oi.price * oi.qty) as total_rev 
                             FROM order_items oi 
                             JOIN products p ON oi.product_id = p.id 
                             GROUP BY p.id 
                             ORDER BY total_sold DESC LIMIT 5");
    $top_products = $top_stmt->fetchAll();

} catch (PDOException $e) {
    die("Reporting query failed: " . $e->getMessage());
}
?>

<!-- KPI summary cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card p-3 border shadow-sm bg-white">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <small class="text-muted text-uppercase fw-bold">Platform Revenue</small>
                <i class="fa fa-rupee-sign text-success fa-lg"></i>
            </div>
            <h3 class="fw-bold mb-1 text-dark"><?php echo format_price($sales_kpi['rev'] ?? 0.00); ?></h3>
            <small class="text-muted"><?php echo $sales_kpi['count']; ?> orders processed</small>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card p-3 border shadow-sm bg-white">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <small class="text-muted text-uppercase fw-bold">Registered Users</small>
                <i class="fa fa-users text-primary fa-lg"></i>
            </div>
            <h3 class="fw-bold mb-1 text-dark"><?php echo $cust_kpi['count']; ?></h3>
            <small class="text-muted">Retail & B2B buyers</small>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card p-3 border shadow-sm bg-white">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <small class="text-muted text-uppercase fw-bold">Approved Vendors</small>
                <i class="fa fa-store text-warning fa-lg"></i>
            </div>
            <h3 class="fw-bold mb-1 text-dark"><?php echo $vend_kpi['count']; ?></h3>
            <small class="text-muted">Active spares shops</small>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card p-3 border shadow-sm bg-white">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <small class="text-muted text-uppercase fw-bold">Low Stock alerts</small>
                <i class="fa fa-exclamation-triangle text-danger fa-lg"></i>
            </div>
            <h3 class="fw-bold mb-1 text-danger"><?php echo $low_stock_kpi['count']; ?></h3>
            <small class="text-muted">Products below 5 units</small>
        </div>
    </div>
</div>

<!-- Charts Section -->
<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="card shadow-sm border p-4 bg-white">
            <h5 class="fw-bold mb-3 text-dark">Platform Sales Trend (7 Days)</h5>
            <canvas id="salesTrendChart" style="max-height: 250px;"></canvas>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card shadow-sm border p-4 bg-white">
            <h5 class="fw-bold mb-3 text-dark">Revenue split by category</h5>
            <canvas id="categoryRevenueChart" style="max-height: 250px;"></canvas>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Top Sellers Leaderboard -->
    <div class="col-lg-6">
        <div class="card shadow-sm border p-4 bg-white h-100">
            <h5 class="fw-bold mb-3 text-dark"><i class="fa fa-trophy text-warning me-2"></i>Top Selling Spare Parts</h5>
            <div class="table-responsive">
                <table class="table align-middle small mb-0">
                    <thead>
                        <tr>
                            <th>Part</th>
                            <th>SKU</th>
                            <th class="text-center">Units Sold</th>
                            <th class="text-end">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($top_products) > 0): ?>
                            <?php foreach ($top_products as $p): ?>
                                <tr>
                                    <td><strong><?php echo esc($p['name']); ?></strong><br><small class="text-muted"><?php echo esc($p['brand']); ?></small></td>
                                    <td><code><?php echo esc($p['sku']); ?></code></td>
                                    <td class="text-center fw-bold text-dark"><?php echo $p['total_sold']; ?></td>
                                    <td class="text-end text-orange fw-bold"><?php echo format_price($p['total_rev']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">No sales logged.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Low Stock warning table -->
    <div class="col-lg-6">
        <div class="card shadow-sm border p-4 bg-white h-100">
            <h5 class="fw-bold mb-3 text-danger"><i class="fa fa-exclamation-circle me-2"></i>Critical Low Stock Warnings</h5>
            <div class="table-responsive">
                <table class="table align-middle small mb-0">
                    <thead>
                        <tr>
                            <th>Part</th>
                            <th>Vendor Shop</th>
                            <th class="text-center">Condition</th>
                            <th class="text-center">In Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($low_stock_products) > 0): ?>
                            <?php foreach ($low_stock_products as $p): ?>
                                <tr>
                                    <td><strong><?php echo esc($p['name']); ?></strong><br><small class="text-muted">SKU: <?php echo esc($p['sku']); ?></small></td>
                                    <td><?php echo esc($p['shop_name'] ?? 'Namma Direct'); ?></td>
                                    <td class="text-center"><span class="badge bg-light text-dark border"><?php echo ucfirst($p['condition']); ?></span></td>
                                    <td class="text-center"><span class="badge bg-danger p-2 fs-6"><?php echo $p['stock_qty']; ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center py-4 text-success fw-bold">All stock levels are optimal!</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ChartJS setup -->
<script>
$(document).ready(function() {
    // 1. Sales Trend Line Chart
    const trendCtx = document.getElementById('salesTrendChart').getContext('2d');
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($trend_labels); ?>,
            datasets: [{
                label: 'Sales Revenue (₹)',
                data: <?php echo json_encode($trend_values); ?>,
                borderColor: '#f75d00',
                backgroundColor: 'rgba(247, 93, 0, 0.08)',
                fill: true,
                tension: 0.3,
                borderWidth: 3
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });

    // 2. Category split Pie Chart
    const catCtx = document.getElementById('categoryRevenueChart').getContext('2d');
    new Chart(catCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($cat_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($cat_values); ?>,
                backgroundColor: [
                    '#0b1d33',
                    '#f75d00',
                    '#0284c7',
                    '#10b981',
                    '#8b5cf6',
                    '#e11d48'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
