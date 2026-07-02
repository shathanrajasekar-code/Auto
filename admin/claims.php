<?php
/**
 * Namma AutoParts - Admin Fitment Guarantee Claims Manager
 */
require_once __DIR__ . '/includes/header.php';

$error = '';
$success = '';

// Handle Claim Decision
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_claim') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = "CSRF verification failed.";
    } else {
        $claim_id = intval($_POST['claim_id']);
        $decision = clean_input($_POST['decision']); // Approved, Rejected

        if ($claim_id > 0 && in_array($decision, ['Approved', 'Rejected'])) {
            try {
                $pdo->beginTransaction();

                // Fetch claim details
                $stmt = $pdo->prepare("SELECT fc.*, p.name as product_name, u.name as customer_name 
                                       FROM fitment_claims fc 
                                       JOIN products p ON fc.product_id = p.id 
                                       JOIN users u ON fc.user_id = u.id 
                                       WHERE fc.id = ?");
                $stmt->execute([$claim_id]);
                $claim = $stmt->fetch();

                if (!$claim) {
                    throw new Exception("Claim not found.");
                }

                if ($claim['status'] !== 'Pending') {
                    throw new Exception("Claim has already been processed.");
                }

                // Update claim status
                $up = $pdo->prepare("UPDATE fitment_claims SET status = ? WHERE id = ?");
                $up->execute([$decision, $claim_id]);

                // If Approved, award compensation points to customer
                if ($decision === 'Approved') {
                    $points = $claim['compensation_points'];
                    
                    // Award points
                    $pdo->prepare("UPDATE users SET loyalty_points = loyalty_points + ? WHERE id = ?")->execute([$points, $claim['user_id']]);

                    // Send Notification
                    $msg = "Your Fitment Guarantee Claim for '{$claim['product_name']}' has been APPROVED! {$points} compensation loyalty points have been credited to your account.";
                    $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")->execute([$claim['user_id'], $msg]);
                } else {
                    // Send Rejected Notification
                    $msg = "Your Fitment Guarantee Claim for '{$claim['product_name']}' has been REJECTED following standard admin review.";
                    $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")->execute([$claim['user_id'], $msg]);
                }

                $pdo->commit();
                $success = "Claim #{$claim_id} has been successfully {$decision}.";
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "System Error: " . $e->getMessage();
            }
        }
    }
}

// Fetch all claims
try {
    $stmt = $pdo->query("SELECT fc.*, p.name as product_name, p.sku, o.order_no, u.name as customer_name, u.email as customer_email 
                         FROM fitment_claims fc 
                         JOIN products p ON fc.product_id = p.id 
                         JOIN orders o ON fc.order_id = o.id 
                         JOIN users u ON fc.user_id = u.id 
                         ORDER BY fc.created_at DESC");
    $claims = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database Error fetching claims: " . $e->getMessage());
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

    <h5 class="fw-bold mb-4 text-dark"><i class="fa fa-shield-alt text-orange me-2"></i>Fitment Guarantee Claims Manager</h5>

    <div class="table-responsive">
        <table class="table align-middle small table-hover">
            <thead>
                <tr>
                    <th>Claim ID</th>
                    <th>Customer details</th>
                    <th>Part details</th>
                    <th>Reason</th>
                    <th class="text-center">Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($claims) > 0): ?>
                    <?php foreach ($claims as $c): ?>
                        <tr>
                            <td>#<?php echo $c['id']; ?></td>
                            <td>
                                <strong><?php echo esc($c['customer_name']); ?></strong><br>
                                <small class="text-muted"><?php echo esc($c['customer_email']); ?></small>
                            </td>
                            <td>
                                <strong><?php echo esc($c['product_name']); ?></strong><br>
                                <small class="text-muted">SKU: <?php echo esc($c['sku']); ?> | Order: <?php echo esc($c['order_no']); ?></small>
                            </td>
                            <td>
                                <span class="d-inline-block text-truncate" style="max-width:200px;" title="<?php echo esc($c['reason']); ?>">
                                    <?php echo esc($c['reason']); ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php 
                                $status_badge = 'bg-secondary';
                                if ($c['status'] === 'Pending') $status_badge = 'bg-warning text-dark';
                                elseif ($c['status'] === 'Approved') $status_badge = 'bg-success';
                                elseif ($c['status'] === 'Rejected') $status_badge = 'bg-danger';
                                ?>
                                <span class="badge <?php echo $status_badge; ?>"><?php echo esc($c['status']); ?></span>
                            </td>
                            <td class="text-end">
                                <?php if ($c['status'] === 'Pending'): ?>
                                    <button class="btn btn-sm btn-outline-primary py-0 px-2" data-bs-toggle="modal" data-bs-target="#claimModal_<?php echo $c['id']; ?>">
                                        Process
                                    </button>

                                    <!-- Process Claim Modal -->
                                    <div class="modal fade" id="claimModal_<?php echo $c['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog text-start">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fw-bold text-dark">Process Claim #<?php echo $c['id']; ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form method="POST" action="admin/claims.php">
                                                    <?php echo csrf_input(); ?>
                                                    <input type="hidden" name="action" value="process_claim">
                                                    <input type="hidden" name="claim_id" value="<?php echo $c['id']; ?>">
                                                    
                                                    <div class="modal-body">
                                                        <p class="small text-muted">Review customer reason:</p>
                                                        <blockquote class="bg-light p-2 rounded border small text-dark">
                                                            "<?php echo esc($c['reason']); ?>"
                                                        </blockquote>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label small fw-bold">Select Decision</label>
                                                            <select class="form-select" name="decision" required>
                                                                <option value="Approved">Approve (Award 100 Compensation Points)</option>
                                                                <option value="Rejected">Reject Claim</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                                        <button type="submit" class="btn btn-accent btn-sm">Confirm Decision</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted text-xs">Processed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">No fitment claims lodged.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
