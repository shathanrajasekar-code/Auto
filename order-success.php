<?php
/**
 * Namma AutoParts - Order Success Page
 */
$page_title = "Order Confirmed!";
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$order_no = isset($_SESSION['last_order_no']) ? $_SESSION['last_order_no'] : '';

if (empty($order_no)) {
    redirect('index.php');
}

// Fetch order details
try {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_no = ?");
    $stmt->execute([$order_no]);
    $order = $stmt->fetch();

    if (!$order) {
        redirect('index.php');
    }
} catch (PDOException $e) {
    die("Database Error: " . htmlspecialchars($e->getMessage()));
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container my-5 text-center">
    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="bg-white p-5 border rounded shadow-sm">
                <i class="fa fa-check-circle fa-5x text-success mb-4"></i>
                
                <h1 class="display-6 fw-bold text-dark mb-2">Thank you for your order!</h1>
                <p class="lead text-muted mb-4">Your spare parts order has been placed successfully.</p>
                
                <div class="bg-light p-4 rounded mb-4 text-start border">
                    <h5 class="fw-bold mb-3 border-bottom pb-2">Order Summary</h5>
                    <div class="row g-2 small">
                        <div class="col-6 text-muted">Order Number:</div>
                        <div class="col-6 fw-bold text-dark text-end"><?php echo esc($order['order_no']); ?></div>
                        
                        <div class="col-6 text-muted">Estimated Delivery:</div>
                        <div class="col-6 text-dark text-end fw-bold"><?php echo date('d M Y', strtotime('+3 days')); ?></div>
                        
                        <div class="col-6 text-muted">Payment Method:</div>
                        <div class="col-6 text-dark text-end fw-bold"><?php echo esc($order['payment_method']); ?></div>
                        
                        <div class="col-6 text-muted">Total Paid (Incl. GST):</div>
                        <div class="col-6 text-orange text-end fw-bold fs-5"><?php echo format_price($order['total_amount']); ?></div>
                    </div>
                </div>

                <div class="d-flex flex-wrap justify-content-center gap-3">
                    <!-- Download invoice link (invoice.php) -->
                    <a href="invoice.php?order_no=<?php echo esc($order['order_no']); ?>" target="_blank" class="btn btn-accent btn-lg">
                        <i class="fa fa-file-pdf me-2"></i>Download GST Invoice
                    </a>
                    
                    <a href="my-account.php" class="btn btn-outline-primary btn-lg">
                        <i class="fa fa-user me-2"></i>My Account History
                    </a>
                </div>

                <div class="mt-4">
                    <a href="shop.php" class="text-orange text-decoration-none fw-bold small">Continue Shopping <i class="fa fa-arrow-right ms-1"></i></a>
                </div>

            </div>
        </div>
    </div>
</div>

<?php
// Clear last order number from session to prevent repeat displays on refresh
unset($_SESSION['last_order_no']);

require_once __DIR__ . '/includes/footer.php';
?>
