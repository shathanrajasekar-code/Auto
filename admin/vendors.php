<?php
/**
 * Namma AutoParts - Admin Vendor Approvals Control
 */
require_once __DIR__ . '/includes/header.php';

$error = '';
$success = '';

// Update vendor status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = "CSRF verification failed.";
    } else {
        $vendor_id = intval($_POST['vendor_id']);
        $new_status = clean_input($_POST['status']); // approved, rejected
        $commission = floatval($_POST['commission_rate']);

        if ($vendor_id > 0 && in_array($new_status, ['approved', 'rejected'])) {
            try {
                $pdo->beginTransaction();

                // Update vendor
                $stmt = $pdo->prepare("UPDATE vendors SET status = ?, commission_rate = ? WHERE id = ?");
                $stmt->execute([$new_status, $commission, $vendor_id]);

                // Fetch vendor user details to notify
                $u_stmt = $pdo->prepare("SELECT user_id, shop_name FROM vendors WHERE id = ?");
                $u_stmt->execute([$vendor_id]);
                $v = $u_stmt->fetch();

                if ($v) {
                    $msg = "Your vendor registration for '{$v['shop_name']}' has been updated to: " . strtoupper($new_status) . ".";
                    $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")->execute([$v['user_id'], $msg]);
                }

                $pdo->commit();
                $success = "Vendor status updated successfully.";
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "System Error: " . $e->getMessage();
            }
        }
    }
}

// Fetch all vendors registrations
try {
    $stmt = $pdo->query("SELECT v.*, u.name as owner_name, u.email as owner_email, u.phone as owner_phone 
                         FROM vendors v 
                         JOIN users u ON v.user_id = u.id 
                         ORDER BY v.created_at DESC");
    $registrations = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database Error fetching vendors: " . $e->getMessage());
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

    <h5 class="fw-bold mb-4 text-dark"><i class="fa fa-store-alt text-orange me-2"></i>Vendor Shop Registrations</h5>

    <div class="table-responsive">
        <table class="table align-middle small table-hover">
            <thead>
                <tr>
                    <th>Shop Info</th>
                    <th>Owner Details</th>
                    <th>GSTIN No</th>
                    <th>Commission</th>
                    <th class="text-center">Status</th>
                    <th class="text-end">Manage Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($registrations) > 0): ?>
                    <?php foreach ($registrations as $v): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc($v['shop_name']); ?></strong><br>
                                <small class="text-muted"><i class="fa fa-map-marker-alt me-1"></i><?php echo esc($v['location']); ?></small>
                            </td>
                            <td>
                                <strong><?php echo esc($v['owner_name']); ?></strong><br>
                                <small class="text-muted"><i class="fa fa-envelope me-1"></i><?php echo esc($v['owner_email']); ?></small>
                            </td>
                            <td><code><?php echo esc($v['gst_number']); ?></code></td>
                            <td><strong><?php echo $v['commission_rate']; ?>%</strong></td>
                            <td class="text-center">
                                <?php 
                                $v_badge = 'bg-secondary';
                                if ($v['status'] === 'pending') $v_badge = 'bg-warning text-dark';
                                elseif ($v['status'] === 'approved') $v_badge = 'bg-success';
                                elseif ($v['status'] === 'rejected') $v_badge = 'bg-danger';
                                ?>
                                <span class="badge <?php echo $v_badge; ?>"><?php echo strtoupper($v['status']); ?></span>
                            </td>
                            <td class="text-end">
                                <!-- Trigger Modal to process status & commission -->
                                <button class="btn btn-sm btn-outline-primary py-1 px-3" data-bs-toggle="modal" data-bs-target="#vendorModal_<?php echo $v['id']; ?>">
                                    <i class="fa fa-cog"></i> Process
                                </button>

                                <!-- Vendor Process Modal -->
                                <div class="modal fade" id="vendorModal_<?php echo $v['id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog text-start">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title fw-bold text-dark">Process registration: <?php echo esc($v['shop_name']); ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form method="POST" action="admin/vendors.php">
                                                <?php echo csrf_input(); ?>
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="vendor_id" value="<?php echo $v['id']; ?>">
                                                
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-bold">Set Status</label>
                                                        <select class="form-select" name="status" required>
                                                            <option value="pending" <?php echo $v['status'] === 'pending' ? 'selected' : ''; ?>>Pending Approval</option>
                                                            <option value="approved" <?php echo $v['status'] === 'approved' ? 'selected' : ''; ?>>Approve Shop</option>
                                                            <option value="rejected" <?php echo $v['status'] === 'rejected' ? 'selected' : ''; ?>>Reject Shop</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-bold">Commission Rate (%)</label>
                                                        <input type="number" step="0.01" class="form-control" name="commission_rate" required value="<?php echo $v['commission_rate']; ?>">
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                                    <button type="submit" class="btn btn-accent btn-sm">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">No vendor profiles found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
