<?php
/**
 * Namma AutoParts - Fitment Guarantee Claims Portal (Customer)
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// Enforce login
require_role('customer');

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle claim submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_claim') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = "CSRF verification failed.";
    } else {
        $order_product = clean_input($_POST['order_product'] ?? ''); // formatted as "orderId_productId"
        $reason = clean_input($_POST['reason'] ?? '');

        if (empty($order_product) || empty($reason)) {
            $error = "Please select an item and provide a reason for the claim.";
        } else {
            $parts = explode('_', $order_product);
            if (count($parts) === 2) {
                $order_id = intval($parts[0]);
                $product_id = intval($parts[1]);

                try {
                    // Check if already claimed
                    $chk_stmt = $pdo->prepare("SELECT id FROM fitment_claims WHERE order_id = ? AND product_id = ? AND user_id = ?");
                    $chk_stmt->execute([$order_id, $product_id, $user_id]);
                    if ($chk_stmt->fetch()) {
                        $error = "A claim has already been submitted for this item.";
                    } else {
                        // Log Claim
                        $ins = $pdo->prepare("INSERT INTO fitment_claims (order_id, product_id, user_id, reason, status, compensation_points) VALUES (?, ?, ?, ?, 'Pending', 100)");
                        $ins->execute([$order_id, $product_id, $user_id, $reason]);
                        
                        $success = "Your claim has been registered! A free return shipping label has been generated. 100 compensation loyalty points will be credited upon admin approval.";
                    }
                } catch (PDOException $e) {
                    $error = "Database Error: " . $e->getMessage();
                }
            } else {
                $error = "Invalid item selection.";
            }
        }
    }
}

try {
    // 1. Fetch delivered parts eligible for claims (Not already claimed)
    $eligible_stmt = $pdo->prepare("SELECT oi.product_id, oi.order_id, p.name as product_name, p.sku, o.order_no, o.created_at as order_date
                                    FROM order_items oi
                                    JOIN orders o ON oi.order_id = o.id
                                    JOIN products p ON oi.product_id = p.id
                                    WHERE o.user_id = ? AND o.status = 'Delivered'
                                    AND NOT EXISTS (
                                        SELECT 1 FROM fitment_claims fc 
                                        WHERE fc.order_id = oi.order_id AND fc.product_id = oi.product_id
                                    )");
    $eligible_stmt->execute([$user_id]);
    $eligible_items = $eligible_stmt->fetchAll();

    // 2. Fetch customer's claim history
    $history_stmt = $pdo->prepare("SELECT fc.*, p.name as product_name, p.sku, o.order_no 
                                   FROM fitment_claims fc
                                   JOIN products p ON fc.product_id = p.id
                                   JOIN orders o ON fc.order_id = o.id
                                   WHERE fc.user_id = ?
                                   ORDER BY fc.created_at DESC");
    $history_stmt->execute([$user_id]);
    $claims_history = $history_stmt->fetchAll();

} catch (PDOException $e) {
    die("Database Error: " . htmlspecialchars($e->getMessage()));
}

$page_title = "Fitment Guarantee Claims";
require_once __DIR__ . '/includes/header.php';
?>

<div class="container my-4">
    <div class="row mb-4 align-items-center">
        <div class="col-12">
            <h2 class="fw-bold mb-1 text-dark"><i class="fa fa-shield-alt text-orange me-2"></i>Fitment Guarantee Claims</h2>
            <p class="text-muted mb-0">Did a part not fit despite our fitment widget? Submit a claim for free returns and 100 bonus loyalty points.</p>
        </div>
    </div>

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

    <div class="row g-4">
        <!-- Lodge New Claim Form -->
        <div class="col-lg-5">
            <div class="bg-white p-4 border rounded shadow-sm">
                <h5 class="fw-bold text-dark mb-3"><i class="fa fa-plus-circle text-primary me-2"></i>Lodge a Fitment Claim</h5>
                
                <?php if (count($eligible_items) > 0): ?>
                    <form method="POST" action="claims.php">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="action" value="submit_claim">
                        
                        <div class="mb-3">
                            <label for="orderProductSelect" class="form-label small fw-bold">Select Purchased Part</label>
                            <select class="form-select" name="order_product" id="orderProductSelect" required>
                                <option value="">Choose item...</option>
                                <?php foreach ($eligible_items as $item): ?>
                                    <option value="<?php echo $item['order_id'] . '_' . $item['product_id']; ?>">
                                        <?php echo esc($item['product_name']); ?> (Order: <?php echo esc($item['order_no']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-xs text-muted mt-1 d-block">Only showing delivered parts from your orders.</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="reasonInput" class="form-label small fw-bold">Describe the fitment issue *</label>
                            <textarea class="form-control" name="reason" id="reasonInput" rows="4" required placeholder="Explain why the part didn't fit (e.g. connector plugs differed, rotor diameter mismatch)..."></textarea>
                        </div>

                        <button type="submit" class="btn btn-accent w-100 py-2 fw-bold"><i class="fa fa-paper-plane me-2"></i>Submit Guarantee Claim</button>
                    </form>
                <?php else: ?>
                    <div class="text-center py-4 text-muted border border-dashed rounded bg-light">
                        <i class="fa fa-info-circle fa-2x mb-2 text-primary"></i>
                        <p class="mb-0 small">No delivered parts eligible for fitment guarantee claims.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Claims Logs History -->
        <div class="col-lg-7">
            <div class="bg-white p-4 border rounded shadow-sm h-100">
                <h5 class="fw-bold text-dark mb-3"><i class="fa fa-history text-muted me-2"></i>Claims Status Log</h5>
                
                <div class="table-responsive">
                    <table class="table align-middle small table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Claim Date</th>
                                <th>Part / Order</th>
                                <th>Reason</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($claims_history) > 0): ?>
                                <?php foreach ($claims_history as $c): ?>
                                    <tr>
                                        <td><?php echo date('d M Y', strtotime($c['created_at'])); ?></td>
                                        <td>
                                            <strong><?php echo esc($c['product_name']); ?></strong><br>
                                            <span class="text-muted text-xs">Order: <?php echo esc($c['order_no']); ?></span>
                                        </td>
                                        <td><span class="text-secondary text-truncate d-inline-block" style="max-width:180px;" title="<?php echo esc($c['reason']); ?>"><?php echo esc($c['reason']); ?></span></td>
                                        <td class="text-center">
                                            <?php 
                                            $c_color = 'bg-secondary';
                                            if ($c['status'] === 'Pending') $c_color = 'bg-warning text-dark';
                                            elseif ($c['status'] === 'Approved') $c_color = 'bg-success';
                                            elseif ($c['status'] === 'Rejected') $c_color = 'bg-danger';
                                            ?>
                                            <span class="badge <?php echo $c_color; ?>"><?php echo esc($c['status']); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">No claims logged yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
