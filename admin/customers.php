<?php
/**
 * Namma AutoParts - Admin Customer & B2B Garage Accounts Manager
 */
require_once __DIR__ . '/includes/header.php';

$error = '';
$success = '';

// Process B2B Approval and Credit limits
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_b2b') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = "CSRF verification failed.";
    } else {
        $customer_id = intval($_POST['customer_id']);
        $is_approved = isset($_POST['is_b2b_approved']) ? 1 : 0;
        $credit_limit = floatval($_POST['b2b_credit_limit']);

        if ($customer_id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET is_b2b_approved = ?, b2b_credit_limit = ? WHERE id = ? AND role = 'customer'");
                $stmt->execute([$is_approved, $credit_limit, $customer_id]);
                
                // Notify User
                $msg = $is_approved 
                    ? "Congratulations! Your B2B Garage Account has been approved with a Credit Line limit of " . format_price($credit_limit) . ". You can now checkout using Pay Later credit terms."
                    : "Your B2B credit line permissions have been deactivated by the administrator.";
                
                $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")->execute([$customer_id, $msg]);

                $success = "Customer B2B settings saved successfully.";
            } catch (PDOException $e) {
                $error = "Database Error: " . $e->getMessage();
            }
        }
    }
}

// Fetch customers list
try {
    $stmt = $pdo->query("SELECT * FROM users WHERE role = 'customer' ORDER BY is_b2b_approved DESC, name ASC");
    $customers = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database Error fetching users: " . $e->getMessage());
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

    <h5 class="fw-bold mb-4 text-dark"><i class="fa fa-warehouse text-orange me-2"></i>Customer & B2B Garage Accounts</h5>

    <div class="table-responsive">
        <table class="table align-middle small table-hover">
            <thead>
                <tr>
                    <th>Customer Name</th>
                    <th>Email Address</th>
                    <th>Phone</th>
                    <th>Loyalty Points</th>
                    <th class="text-center">B2B Approved</th>
                    <th>Credit limit</th>
                    <th class="text-end">Manage Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($customers) > 0): ?>
                    <?php foreach ($customers as $c): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc($c['name']); ?></strong>
                                <?php if ($c['is_b2b_approved']): ?>
                                    <span class="badge bg-success ms-1" style="font-size: 9px;">B2B Verified</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc($c['email']); ?></td>
                            <td><?php echo esc($c['phone']); ?></td>
                            <td class="fw-bold"><?php echo $c['loyalty_points']; ?> pts</td>
                            <td class="text-center">
                                <?php if ($c['is_b2b_approved']): ?>
                                    <span class="badge bg-success"><i class="fa fa-check"></i> Approved</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">No</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($c['is_b2b_approved']): ?>
                                    <strong><?php echo format_price($c['b2b_credit_limit']); ?></strong>
                                    <span class="d-block text-xs text-muted">Used: <?php echo format_price($c['b2b_credit_used']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <!-- Trigger Modal to process B2B -->
                                <button class="btn btn-sm btn-outline-primary py-1 px-3" data-bs-toggle="modal" data-bs-target="#b2bModal_<?php echo $c['id']; ?>">
                                    <i class="fa fa-edit"></i> B2B Settings
                                </button>

                                <!-- Customer B2B Modal -->
                                <div class="modal fade" id="b2bModal_<?php echo $c['id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog text-start">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title fw-bold text-dark">Modify customer: <?php echo esc($c['name']); ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form method="POST" action="admin/customers.php">
                                                <?php echo csrf_input(); ?>
                                                <input type="hidden" name="action" value="update_b2b">
                                                <input type="hidden" name="customer_id" value="<?php echo $c['id']; ?>">
                                                
                                                <div class="modal-body">
                                                    <div class="form-check form-switch mb-3 p-3 bg-light rounded border">
                                                        <input class="form-check-input ms-0 me-2" type="checkbox" role="switch" id="b2bCheck_<?php echo $c['id']; ?>" name="is_b2b_approved" value="1" <?php echo $c['is_b2b_approved'] ? 'checked' : ''; ?>>
                                                        <label class="form-check-label fw-bold text-success" for="b2bCheck_<?php echo $c['id']; ?>">
                                                            Approve B2B Garage Accounts Status
                                                        </label>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-bold">Set Credit Limit (₹)</label>
                                                        <input type="number" step="0.01" class="form-control" name="b2b_credit_limit" required value="<?php echo $c['b2b_credit_limit']; ?>">
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                                    <button type="submit" class="btn btn-accent btn-sm">Save settings</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">No customer accounts registered.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
