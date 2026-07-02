<?php
/**
 * Namma AutoParts - Admin Barcode/QR Inventory Adjuster
 */
require_once __DIR__ . '/includes/header.php';

$error = '';
$success = '';

// Process Stock Adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'adjust_stock') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = "CSRF verification failed.";
    } else {
        $product_id = intval($_POST['product_id']);
        $change_qty = intval($_POST['change_qty']);
        $reason = clean_input($_POST['reason']);
        $warehouse_id = intval($_POST['warehouse_id'] ?? 1);

        if ($product_id > 0 && $change_qty !== 0 && !empty($reason)) {
            try {
                $pdo->beginTransaction();

                // Update product stock
                $up = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?");
                $up->execute([$change_qty, $product_id]);

                // Insert into inventory logs
                $ins = $pdo->prepare("INSERT INTO inventory_log (product_id, change_qty, reason, warehouse_id) VALUES (?, ?, ?, ?)");
                $ins->execute([$product_id, $change_qty, $reason, $warehouse_id]);

                $pdo->commit();
                $success = "Stock level adjusted successfully by {$change_qty} units.";
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "Database Error: " . $e->getMessage();
            }
        } else {
            $error = "Please fill in all adjustment fields correctly.";
        }
    }
}

// Fetch Warehouses list for adjustment form
try {
    $warehouses = $pdo->query("SELECT * FROM warehouses ORDER BY name ASC")->fetchAll();
    
    // Fetch latest 10 inventory logs for logging table
    $logs = $pdo->query("SELECT il.*, p.name as product_name, p.sku, w.name as warehouse_name 
                         FROM inventory_log il 
                         JOIN products p ON il.product_id = p.id 
                         JOIN warehouses w ON il.warehouse_id = w.id 
                         ORDER BY il.created_at DESC LIMIT 10")->fetchAll();
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<div class="row g-4">
    <!-- QR Lookup & Adjustment Form -->
    <div class="col-lg-6">
        <div class="card p-4 border shadow-sm bg-white h-100">
            <h5 class="fw-bold mb-3 text-dark"><i class="fa fa-barcode text-orange me-2"></i>QR / Barcode Stock Adjuster</h5>
            <p class="small text-muted">Lookup items by typing or scanning product SKU barcodes to print QR labels and adjust warehouse stocks.</p>
            
            <!-- SKU Lookup Input -->
            <div class="mb-4">
                <label for="skuSearch" class="form-label small fw-bold">Scan or Enter SKU</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-search"></i></span>
                    <input type="text" class="form-control" id="skuSearch" placeholder="e.g. SKU-NGK-IR-001">
                    <button class="btn btn-accent" type="button" id="skuSearchBtn">Lookup SKU</button>
                </div>
                <div id="lookupFeedback" class="small text-danger mt-1" style="display:none;"></div>
            </div>

            <!-- AJAX Loaded Product Details Card -->
            <div id="productAdjustmentPanel" style="display: none;" class="p-3 bg-light rounded border border-secondary mb-3">
                <div class="row align-items-center">
                    <div class="col-sm-8">
                        <h6 class="fw-bold text-dark mb-1" id="adjProdName">Product Name</h6>
                        <small class="text-muted d-block mb-1">SKU: <strong id="adjProdSku"></strong> | Brand: <strong id="adjProdBrand"></strong></small>
                        <small class="text-muted d-block mb-3">Current Stock Level: <span class="badge bg-success p-2 fs-6" id="adjProdStock">0</span></small>
                    </div>
                    <!-- QR Display -->
                    <div class="col-sm-4 text-center">
                        <img id="adjProdQR" src="" alt="QR SKU Code" class="img-thumbnail border" style="width: 110px; height: 110px;">
                        <span class="d-block text-xs text-muted mt-1">Print Label Sheet</span>
                    </div>
                </div>

                <hr class="my-3">

                <!-- Stock Adjustment form -->
                <form method="POST" action="admin/inventory.php">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="adjust_stock">
                    <input type="hidden" name="product_id" id="adjProductIdInput" value="">
                    
                    <div class="row g-2">
                        <div class="col-md-6 mb-2">
                            <label for="change_qty" class="form-label small fw-bold">Quantity Adjustment (+/-)</label>
                            <input type="number" class="form-control form-control-sm" name="change_qty" id="change_qty" required placeholder="e.g. 10 or -5">
                        </div>
                        <div class="col-md-6 mb-2">
                            <label for="warehouse_id" class="form-label small fw-bold">Warehouse</label>
                            <select class="form-select form-select-sm" name="warehouse_id" id="warehouse_id" required>
                                <?php foreach ($warehouses as $w): ?>
                                    <option value="<?php echo $w['id']; ?>"><?php echo esc($w['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="reason" class="form-label small fw-bold">Reason for Change *</label>
                        <input type="text" class="form-control form-control-sm" name="reason" id="reason" required placeholder="e.g. Stock Receipt, Audit Audit, Re-sizing">
                    </div>
                    
                    <button type="submit" class="btn btn-accent btn-sm w-100">Submit Stock Adjustment</button>
                </form>
            </div>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success mt-3 small"><i class="fa fa-check-circle me-1"></i><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger mt-3 small"><i class="fa fa-times-circle me-1"></i><?php echo $error; ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stock Movement Log Logs -->
    <div class="col-lg-6">
        <div class="card p-4 border shadow-sm bg-white h-100">
            <h5 class="fw-bold mb-3 text-dark"><i class="fa fa-history text-muted me-2"></i>Stock Movement Logs</h5>
            <p class="small text-muted mb-4">Latest 10 inventory adjustment logs across platform warehouses.</p>
            
            <div class="table-responsive">
                <table class="table align-middle small table-striped mb-0" style="font-size: 11px;">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Part Details</th>
                            <th>Warehouse</th>
                            <th class="text-center">Change</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo date('d M, H:i', strtotime($log['created_at'])); ?></td>
                                <td><strong><?php echo esc($log['product_name']); ?></strong><br><small class="text-muted">SKU: <?php echo esc($log['sku']); ?></small></td>
                                <td><?php echo esc($log['warehouse_name']); ?></td>
                                <td class="text-center">
                                    <span class="badge <?php echo $log['change_qty'] > 0 ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $log['change_qty'] > 0 ? '+' : ''; ?><?php echo $log['change_qty']; ?>
                                    </span>
                                </td>
                                <td><?php echo esc($log['reason']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Lookup SKU handler
    $('#skuSearchBtn').on('click', function() {
        performLookup();
    });

    // Handle Enter key on search input
    $('#skuSearch').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            performLookup();
        }
    });

    function performLookup() {
        var sku = $('#skuSearch').val().trim();
        var fb = $('#lookupFeedback');
        var panel = $('#productAdjustmentPanel');

        if (sku.length === 0) return;

        $.ajax({
            url: 'api/sku-lookup.php',
            type: 'GET',
            data: { sku: sku },
            dataType: 'json',
            success: function(response) {
                fb.hide().text('');
                if (response.success) {
                    var p = response.product;
                    $('#adjProdName').text(p.name);
                    $('#adjProdSku').text(p.sku);
                    $('#adjProdBrand').text(p.brand);
                    $('#adjProdStock').text(p.stock_qty);
                    $('#adjProdQR').attr('src', p.qr_code_url);
                    $('#adjProductIdInput').val(p.id);
                    
                    panel.fadeIn();
                } else {
                    panel.hide();
                    fb.text(response.message).show();
                }
            },
            error: function() {
                panel.hide();
                fb.text('Failed to contact database.').show();
            }
        });
    }
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
