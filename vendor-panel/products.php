<?php
/**
 * Namma AutoParts - Vendor Spare Parts CRUD Portal
 */
require_once __DIR__ . '/includes/header.php';

$vendor_id = $_SESSION['vendor_id'];
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$error = '';
$success = '';

// Fetch all categories for product form
try {
    $cat_stmt = $pdo->query("SELECT * FROM categories WHERE parent_id IS NOT NULL ORDER BY name ASC");
    $categories = $cat_stmt->fetchAll();
    
    // Fetch all vehicles for compatibility tags
    $veh_stmt = $pdo->query("SELECT * FROM vehicles_master ORDER BY make ASC, model ASC");
    $vehicles = $veh_stmt->fetchAll();
} catch (PDOException $e) {
    die("Error loading forms: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = "CSRF security check failed.";
    } else {
        $name = clean_input($_POST['name']);
        $brand = clean_input($_POST['brand']);
        $sku = clean_input($_POST['sku']);
        $category_id = intval($_POST['category_id']);
        $price = floatval($_POST['price']);
        $discount_price = !empty($_POST['discount_price']) ? floatval($_POST['discount_price']) : null;
        $stock_qty = intval($_POST['stock_qty']);
        $condition = clean_input($_POST['condition']);
        $warranty_months = intval($_POST['warranty_months'] ?? 0);
        $core_charge = floatval($_POST['core_charge'] ?? 0.00);
        $description = clean_input($_POST['description']);
        $hsn_code = clean_input($_POST['hsn_code'] ?? '8708');
        $fitments = isset($_POST['fitments']) ? $_POST['fitments'] : []; // Array of vehicle IDs

        // Validation
        if (empty($name) || empty($sku) || $category_id <= 0 || $price <= 0 || $stock_qty < 0 || empty($brand)) {
            $error = "Mandatory fields: Name, Brand, SKU, Category, Price, Stock quantity are required.";
        } elseif ($condition === 'used' && empty($_FILES['primary_image']['name']) && $action === 'add') {
            $error = "Used spare parts require uploading actual photos of the part condition.";
        } else {
            try {
                // Check SKU uniqueness (excluding current product in edit mode)
                $sku_chk = "SELECT id FROM products WHERE sku = ?";
                $sku_params = [$sku];
                if ($action === 'edit') {
                    $sku_chk .= " AND id != ?";
                    $sku_params[] = intval($_POST['product_id']);
                }
                $chk_stmt = $pdo->prepare($sku_chk);
                $chk_stmt->execute($sku_params);
                
                if ($chk_stmt->fetch()) {
                    $error = "SKU is already registered to another product.";
                } else {
                    // Image Upload
                    $img_filename = '';
                    if (!empty($_FILES['primary_image']['name'])) {
                        $up_res = upload_file($_FILES['primary_image']);
                        if ($up_res['status']) {
                            $img_filename = $up_res['filename'];
                        } else {
                            $error = "Image upload failed: " . $up_res['message'];
                        }
                    }

                    if (empty($error)) {
                        $pdo->beginTransaction();

                        if ($action === 'add') {
                            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name))) . '-' . rand(100, 999);
                            
                            $images_json = json_encode($img_filename ? [$img_filename] : []);
                            
                            $sql = "INSERT INTO products (name, slug, sku, category_id, vendor_id, brand, price, discount_price, stock_qty, `condition`, warranty_months, core_charge, description, images, hsn_code) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([
                                $name, $slug, $sku, $category_id, $vendor_id, $brand, $price, 
                                $discount_price, $stock_qty, $condition, $warranty_months, $core_charge, 
                                $description, $images_json, $hsn_code
                            ]);
                            $new_prod_id = $pdo->lastInsertId();

                            // Log inventory adjustment
                            $pdo->prepare("INSERT INTO inventory_log (product_id, change_qty, reason, warehouse_id) VALUES (?, ?, 'Initial Vendor Stock', 1)")->execute([$new_prod_id, $stock_qty]);

                            // Insert compatibility fitment links
                            foreach ($fitments as $v_id) {
                                $pdo->prepare("INSERT INTO product_vehicle_fitment (product_id, vehicle_id) VALUES (?, ?)")->execute([$new_prod_id, $v_id]);
                            }

                            $success = "Product listed successfully!";
                            $action = 'list';
                        } elseif ($action === 'edit') {
                            $product_id = intval($_POST['product_id']);
                            
                            // Get existing images
                            $ex_stmt = $pdo->prepare("SELECT images FROM products WHERE id = ? AND vendor_id = ?");
                            $ex_stmt->execute([$product_id, $vendor_id]);
                            $ex_prod = $ex_stmt->fetch();
                            
                            if (!$ex_prod) {
                                throw new Exception("Unauthorized edit attempt.");
                            }

                            $existing_imgs = json_decode($ex_prod['images'] ?? '[]');
                            if ($img_filename) {
                                $images_json = json_encode([$img_filename]); // Replace primary
                            } else {
                                $images_json = json_encode($existing_imgs);
                            }

                            $sql = "UPDATE products 
                                    SET name = ?, sku = ?, category_id = ?, brand = ?, price = ?, discount_price = ?, stock_qty = ?, `condition` = ?, warranty_months = ?, core_charge = ?, description = ?, images = ?, hsn_code = ? 
                                    WHERE id = ? AND vendor_id = ?";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([
                                $name, $sku, $category_id, $brand, $price, $discount_price, $stock_qty, 
                                $condition, $warranty_months, $core_charge, $description, $images_json, 
                                $hsn_code, $product_id, $vendor_id
                            ]);

                            // Update Fitment link maps
                            $pdo->prepare("DELETE FROM product_vehicle_fitment WHERE product_id = ?")->execute([$product_id]);
                            foreach ($fitments as $v_id) {
                                $pdo->prepare("INSERT INTO product_vehicle_fitment (product_id, vehicle_id) VALUES (?, ?)")->execute([$product_id, $v_id]);
                            }

                            $success = "Product updated successfully!";
                            $action = 'list';
                        }

                        $pdo->commit();
                    }
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "Error updating product details: " . $e->getMessage();
            }
        }
    }
}

// Handle Delete Action
if ($action === 'delete') {
    $product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($product_id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ? AND vendor_id = ?");
            $stmt->execute([$product_id, $vendor_id]);
            if ($stmt->rowCount() > 0) {
                $success = "Product deleted successfully.";
            } else {
                $error = "Failed to delete product. Unauthorized or product not found.";
            }
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
    $action = 'list';
}

// Fetch current vendor inventory
$vendor_products = [];
if ($action === 'list') {
    try {
        $stmt = $pdo->prepare("SELECT p.*, c.name as category_name 
                               FROM products p 
                               JOIN categories c ON p.category_id = c.id 
                               WHERE p.vendor_id = ? 
                               ORDER BY p.created_at DESC");
        $stmt->execute([$vendor_id]);
        $vendor_products = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = "Database Error fetching items: " . $e->getMessage();
    }
}

// Edit Form pre-filler loader
$edit_prod = null;
$ex_fitment = [];
if ($action === 'edit') {
    $product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$product_id, $vendor_id]);
        $edit_prod = $stmt->fetch();
        
        if (!$edit_prod) {
            $error = "Product not found or access denied.";
            $action = 'list';
        } else {
            // Fetch fitments mapping
            $fit_stmt = $pdo->prepare("SELECT vehicle_id FROM product_vehicle_fitment WHERE product_id = ?");
            $fit_stmt->execute([$product_id]);
            $ex_fitment = $fit_stmt->fetchAll(PDO::FETCH_COLUMN);
        }
    } catch (PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
        $action = 'list';
    }
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

    <!-- 1. Inventory List view -->
    <?php if ($action === 'list'): ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="fw-bold mb-0 text-dark">My Listed Inventory</h5>
            <a href="products.php?action=add" class="btn btn-accent"><i class="fa fa-plus me-1"></i>List Spare Part</a>
        </div>

        <div class="table-responsive">
            <table class="table align-middle small table-hover">
                <thead>
                    <tr>
                        <th>Part</th>
                        <th>Category</th>
                        <th>SKU</th>
                        <th>Price</th>
                        <th class="text-center">Stock</th>
                        <th class="text-center">Condition</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($vendor_products) > 0): ?>
                        <?php foreach ($vendor_products as $p): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc($p['name']); ?></strong><br>
                                    <small class="text-muted">Brand: <?php echo esc($p['brand']); ?></small>
                                </td>
                                <td><?php echo esc($p['category_name']); ?></td>
                                <td><code><?php echo esc($p['sku']); ?></code></td>
                                <td>
                                    <?php if ($p['discount_price'] > 0): ?>
                                        <strong class="text-orange"><?php echo format_price($p['discount_price']); ?></strong>
                                        <span class="text-xs text-muted text-decoration-line-through"><?php echo format_price($p['price']); ?></span>
                                    <?php else: ?>
                                        <strong class="text-dark"><?php echo format_price($p['price']); ?></strong>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?php echo $p['stock_qty'] < 5 ? 'bg-danger' : 'bg-success'; ?>"><?php echo $p['stock_qty']; ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border"><?php echo ucfirst($p['condition']); ?></span>
                                </td>
                                <td class="text-end">
                                    <a href="products.php?action=edit&id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size:11px;"><i class="fa fa-edit"></i> Edit</a>
                                    <a href="products.php?action=delete&id=<?php echo $p['id']; ?>" onclick="return confirm('Are you sure you want to delete this listing?')" class="btn btn-sm btn-outline-danger py-0 px-2" style="font-size:11px;"><i class="fa fa-trash-alt"></i> Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">You have no products listed. Add one!</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <!-- 2. Add / Edit form view -->
    <?php elseif ($action === 'add' || $action === 'edit'): ?>
        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
            <h5 class="fw-bold mb-0 text-dark"><?php echo ($action === 'add') ? 'List New Spare Part' : 'Edit Spare Part details'; ?></h5>
            <a href="products.php" class="btn btn-sm btn-outline-secondary"><i class="fa fa-arrow-left me-1"></i>Cancel</a>
        </div>

        <form method="POST" action="products.php?action=<?php echo $action; ?>" enctype="multipart/form-data">
            <?php echo csrf_input(); ?>
            <?php if ($action === 'edit'): ?>
                <input type="hidden" name="product_id" value="<?php echo $edit_prod['id']; ?>">
            <?php endif; ?>

            <div class="row">
                <!-- Basic Fields -->
                <div class="col-md-8">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="name" class="form-label small fw-bold">Product Title *</label>
                            <input type="text" class="form-control form-control-sm" name="name" id="name" required value="<?php echo esc($edit_prod['name'] ?? ''); ?>" placeholder="e.g. Denso Front Starter Motor">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="brand" class="form-label small fw-bold">Brand *</label>
                            <input type="text" class="form-control form-control-sm" name="brand" id="brand" required value="<?php echo esc($edit_prod['brand'] ?? ''); ?>" placeholder="e.g. Denso, Bosch">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="sku" class="form-label small fw-bold">SKU Number *</label>
                            <input type="text" class="form-control form-control-sm" name="sku" id="sku" required value="<?php echo esc($edit_prod['sku'] ?? ''); ?>" placeholder="Unique SKU tag">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="category_id" class="form-label small fw-bold">Sub-Category *</label>
                            <select class="form-select form-select-sm" name="category_id" id="category_id" required>
                                <option value="">Choose category...</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo (isset($edit_prod['category_id']) && $edit_prod['category_id'] == $c['id']) ? 'selected' : ''; ?>><?php echo esc($c['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="price" class="form-label small fw-bold">Price *</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" name="price" id="price" required value="<?php echo esc($edit_prod['price'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="discount_price" class="form-label small fw-bold">Discount Price</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" name="discount_price" id="discount_price" value="<?php echo esc($edit_prod['discount_price'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="stock_qty" class="form-label small fw-bold">Stock Quantity *</label>
                            <input type="number" class="form-control form-control-sm" name="stock_qty" id="stock_qty" required value="<?php echo esc($edit_prod['stock_qty'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="condition" class="form-label small fw-bold">Condition *</label>
                            <select class="form-select form-select-sm" name="condition" id="conditionSelect" required>
                                <option value="new" <?php echo (isset($edit_prod['condition']) && $edit_prod['condition'] === 'new') ? 'selected' : ''; ?>>New</option>
                                <option value="used" <?php echo (isset($edit_prod['condition']) && $edit_prod['condition'] === 'used') ? 'selected' : ''; ?>>Used</option>
                                <option value="refurbished" <?php echo (isset($edit_prod['condition']) && $edit_prod['condition'] === 'refurbished') ? 'selected' : ''; ?>>Refurbished</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="warranty_months" class="form-label small fw-bold">Warranty Period (Months)</label>
                            <input type="number" class="form-control form-control-sm" name="warranty_months" id="warranty_months" value="<?php echo esc($edit_prod['warranty_months'] ?? 0); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="core_charge" class="form-label small fw-bold">Core Charge Refundable Deposit (₹)</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" name="core_charge" id="core_charge" value="<?php echo esc($edit_prod['core_charge'] ?? 0.00); ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label small fw-bold">Part Description</label>
                        <textarea class="form-control form-control-sm" name="description" id="description" rows="4" placeholder="Detail part description, material, dimensions..."><?php echo esc($edit_prod['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="hsn_code" class="form-label small fw-bold">HSN Code</label>
                            <input type="text" class="form-control form-control-sm" name="hsn_code" id="hsn_code" value="<?php echo esc($edit_prod['hsn_code'] ?? '8708'); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="primary_image" class="form-label small fw-bold">Primary Photo *</label>
                            <input type="file" class="form-control form-control-sm" name="primary_image" id="primary_image" <?php echo ($action === 'add') ? 'required' : ''; ?>>
                            <span class="text-xs text-muted" id="usedPhotoWarning" style="display:none; color:red !important;"><i class="fa fa-info-circle"></i> Used parts must upload actual photos of the item.</span>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Compatibility Tagger checkboxes -->
                <div class="col-md-4">
                    <div class="card p-3 bg-light border">
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-2"><i class="fa fa-car me-2"></i>Fits vehicles compatibility</h6>
                        <small class="text-muted d-block mb-3">Select the car models that this spare part fits:</small>
                        
                        <div class="d-flex flex-column gap-2" style="max-height: 380px; overflow-y: auto;">
                            <?php foreach ($vehicles as $v): ?>
                                <div class="form-check border-bottom pb-1 mb-0">
                                    <input class="form-check-input" type="checkbox" name="fitments[]" value="<?php echo $v['id']; ?>" id="fit_<?php echo $v['id']; ?>" <?php echo in_array($v['id'], $ex_fitment) ? 'checked' : ''; ?>>
                                    <label class="form-check-label text-xs fw-bold" for="fit_<?php echo $v['id']; ?>">
                                        <?php echo esc($v['make'] . ' ' . $v['model']); ?>
                                        <span class="d-block text-muted" style="font-size:9px; font-weight:normal;"><?php echo esc($v['engine_type'] . ' (' . $v['fuel_type'] . ')'); ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-end mt-4 border-top pt-3">
                <button type="submit" class="btn btn-accent btn-lg px-5">Save Listing Details</button>
            </div>
        </form>
    <?php endif; ?>

</div>

<script>
$(document).ready(function() {
    // Show used photos warning on select
    $('#conditionSelect').on('change', function() {
        var val = $(this).val();
        if (val === 'used') {
            $('#usedPhotoWarning').show();
        } else {
            $('#usedPhotoWarning').hide();
        }
    });

    // Initial check on edit load
    if ($('#conditionSelect').length > 0 && $('#conditionSelect').val() === 'used') {
        $('#usedPhotoWarning').show();
    }
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
