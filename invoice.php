<?php
/**
 * Namma AutoParts - GST-Compliant Invoice View (Printable)
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$order_no = isset($_GET['order_no']) ? clean_input($_GET['order_no']) : '';

if (empty($order_no)) {
    die("Error: Order number required.");
}

try {
    // Fetch Order details
    $order_stmt = $pdo->prepare("SELECT o.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone 
                                 FROM orders o 
                                 LEFT JOIN users u ON o.user_id = u.id 
                                 WHERE o.order_no = ?");
    $order_stmt->execute([$order_no]);
    $order = $order_stmt->fetch();

    if (!$order) {
        die("Error: Order not found.");
    }

    // Security: Only allow owner or admin/vendor to view
    // (If customer matches, or if admin/vendor, or if guest)
    if (is_logged_in()) {
        if (!has_role(['admin', 'vendor']) && $order['user_id'] != $_SESSION['user_id']) {
            die("Error: Unauthorized invoice access.");
        }
    }

    // Fetch items with vendor details
    $items_stmt = $pdo->prepare("SELECT oi.*, p.name as product_name, p.sku, p.hsn_code, p.condition, p.brand,
                                        v.shop_name, v.gst_number as vendor_gstin, v.location as vendor_loc
                                 FROM order_items oi
                                 JOIN products p ON oi.product_id = p.id
                                 LEFT JOIN vendors v ON p.vendor_id = v.id
                                 WHERE oi.order_id = ?");
    $items_stmt->execute([$order['id']]);
    $items = $items_stmt->fetchAll();

} catch (PDOException $e) {
    die("Database Error: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tax Invoice - <?php echo esc($order['order_no']); ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #fff;
            color: #333;
            font-size: 13px;
        }
        .invoice-box {
            max-width: 800px;
            margin: auto;
            padding: 30px;
            border: 1px solid #eee;
            background: #fff;
        }
        .invoice-header {
            border-bottom: 2px solid #0b1d33;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .table-invoice th {
            background-color: #0b1d33 !important;
            color: #fff !important;
            font-size: 11px;
            text-transform: uppercase;
        }
        .table-invoice td {
            font-size: 12px;
            vertical-align: middle;
        }
        @media print {
            .no-print {
                display: none;
            }
            .invoice-box {
                border: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>

<div class="container my-4 no-print text-end max-width-800" style="max-width: 800px; margin: auto;">
    <button onclick="window.print();" class="btn btn-primary"><i class="fa fa-print me-2"></i>Print / Save to PDF</button>
</div>

<div class="invoice-box shadow-sm mt-2">
    <!-- Invoice Header -->
    <div class="invoice-header d-flex justify-content-between align-items-center">
            <h2 class="fw-bold text-dark mb-0">VELOPARTS</h2>
            <small class="text-muted">Marketplace Operator GSTIN: 33VLPARTS1234Z</small><br>
            <small class="text-muted">Gandhipuram, Coimbatore, Tamil Nadu, 641012</small>
        </div>
        <div class="text-end">
            <h4 class="text-uppercase fw-bold text-danger">Tax Invoice</h4>
            <span class="d-block"><strong>Invoice No:</strong> <?php echo esc($order['order_no']); ?></span>
            <span class="d-block"><strong>Date:</strong> <?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?></span>
            <span class="d-block"><strong>Status:</strong> <?php echo esc($order['status']); ?></span>
        </div>
    </div>

    <!-- Billing Info -->
    <div class="row mb-4">
        <div class="col-6">
            <h6 class="fw-bold text-dark border-bottom pb-1">Billed To (Customer):</h6>
            <strong>Name:</strong> <?php echo esc($order['customer_name'] ?? 'Guest Buyer'); ?><br>
            <strong>Phone:</strong> <?php echo esc($order['customer_phone'] ?? ''); ?><br>
            <strong>Email:</strong> <?php echo esc($order['customer_email'] ?? ''); ?><br>
            <strong>Shipping State:</strong> <?php echo esc($order['shipping_state']); ?>
        </div>
        <div class="col-6">
            <h6 class="fw-bold text-dark border-bottom pb-1">Shipping Details:</h6>
            <p class="mb-0 text-secondary" style="white-space: pre-line;">
                <?php echo esc($order['shipping_address']); ?>
            </p>
        </div>
    </div>

    <!-- Items Table -->
    <div class="table-responsive">
        <table class="table table-bordered table-invoice">
            <thead>
                <tr>
                    <th>Item Description</th>
                    <th>HSN</th>
                    <th class="text-center">Qty</th>
                    <th class="text-end">Base (Excl. Tax)</th>
                    <th class="text-center">GST Rate</th>
                    <th class="text-end">GST Amt</th>
                    <th class="text-end">Core Charge</th>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $tax_rate = 0.18; // 18% standard rate
                $computed_subtotal = 0;
                $computed_gst = 0;
                $computed_core = 0;

                foreach ($items as $item): 
                    // Calculate base price & GST split from the recorded item price (which is inclusive of GST)
                    $item_base = $item['price'] / (1 + $tax_rate);
                    $item_gst = $item['price'] - $item_base;
                    
                    $line_base_total = $item_base * $item['qty'];
                    $line_gst_total = $item_gst * $item['qty'];
                    $line_core_total = $item['core_charge'] * $item['qty'];
                    $line_grand_total = ($item['price'] * $item['qty']) + $line_core_total;

                    $computed_subtotal += $line_base_total;
                    $computed_gst += $line_gst_total;
                    $computed_core += $line_core_total;
                ?>
                    <tr>
                        <td>
                            <strong><?php echo esc($item['product_name']); ?></strong><br>
                            <small class="text-muted">SKU: <?php echo esc($item['sku']); ?> | Brand: <?php echo esc($item['brand']); ?></small><br>
                            <small class="text-muted" style="font-size:10px;">Seller: <?php echo esc($item['shop_name'] ?? 'Namma Direct'); ?> (GSTIN: <?php echo esc($item['vendor_gstin'] ?? 'N/A'); ?>)</small>
                        </td>
                        <td class="text-center"><?php echo esc($item['hsn_code'] ?? '8708'); ?></td>
                        <td class="text-center"><?php echo $item['qty']; ?></td>
                        <td class="text-end">₹<?php echo number_format($item_base, 2); ?></td>
                        <td class="text-center"><?php echo ($tax_rate * 100); ?>%</td>
                        <td class="text-end">₹<?php echo number_format($item_gst, 2); ?></td>
                        <td class="text-end">₹<?php echo number_format($item['core_charge'], 2); ?></td>
                        <td class="text-end">₹<?php echo number_format($line_grand_total, 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Totals Summary & GST breakdown -->
    <div class="row mt-4">
        <div class="col-7">
            <div class="bg-light p-3 rounded border" style="font-size: 11px;">
                <h6 class="fw-bold mb-2">GST Tax Breakdown Summary</h6>
                <table class="table table-sm table-borderless mb-0">
                    <tbody>
                        <tr>
                            <td>Parts Taxable Value:</td>
                            <td class="text-end">₹<?php echo number_format($computed_subtotal, 2); ?></td>
                        </tr>
                        <?php if ($order['cgst_amount'] > 0 || $order['sgst_amount'] > 0): ?>
                            <tr>
                                <td>Central Tax (CGST @ 9%):</td>
                                <td class="text-end">₹<?php echo number_format($order['cgst_amount'], 2); ?></td>
                            </tr>
                            <tr>
                                <td>State Tax (SGST @ 9%):</td>
                                <td class="text-end">₹<?php echo number_format($order['sgst_amount'], 2); ?></td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td>Integrated Tax (IGST @ 18%):</td>
                                <td class="text-end">₹<?php echo number_format($order['igst_amount'], 2); ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr class="border-top">
                            <td class="fw-bold">Total GST Paid:</td>
                            <td class="text-end fw-bold">₹<?php echo number_format($order['gst_amount'], 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-3 text-xs text-muted">
                * Replaced parts corresponding to Core Charges must be returned within 30 days to claim core refund values.
            </div>
        </div>
        
        <div class="col-5">
            <table class="table table-borderless text-end small">
                <tbody>
                    <tr>
                        <td class="text-muted">Parts Total (GST Excl.):</td>
                        <td class="fw-bold">₹<?php echo number_format($computed_subtotal, 2); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">GST Tax Amount:</td>
                        <td class="fw-bold">₹<?php echo number_format($order['gst_amount'], 2); ?></td>
                    </tr>
                    <?php if ($order['discount_amount'] > 0): ?>
                        <tr class="text-success">
                            <td>Coupon Discount:</td>
                            <td class="fw-bold">-₹<?php echo number_format($order['discount_amount'], 2); ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($order['loyalty_points_used'] > 0): ?>
                        <tr class="text-success">
                            <td>Loyalty Points Discount:</td>
                            <td class="fw-bold">-₹<?php echo number_format($order['loyalty_points_used'], 2); ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($computed_core > 0): ?>
                        <tr class="text-danger">
                            <td>Core Deposits:</td>
                            <td class="fw-bold">+₹<?php echo number_format($computed_core, 2); ?></td>
                        </tr>
                    <?php endif; ?>
                    <!-- If installation was booked, check orders total difference or fetch from DB -->
                    <?php 
                    $order_base_parts = $subtotal - $order['discount_amount'] - $order['loyalty_points_used'];
                    $calc_total = $order_base_parts + $computed_core;
                    $diff = $order['total_amount'] - $calc_total;
                    if ($diff > 0): 
                    ?>
                        <tr class="text-primary">
                            <td>Garage Fitting Fee:</td>
                            <td class="fw-bold">+₹<?php echo number_format($diff, 2); ?></td>
                        </tr>
                    <?php endif; ?>
                    
                    <tr class="border-top">
                        <td class="fs-6 fw-bold text-dark">Invoice Grand Total:</td>
                        <td class="fs-6 fw-bold text-orange text-danger" style="color: #f75d00 !important;">₹<?php echo number_format($order['total_amount'], 2); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Signature -->
    <div class="row mt-5 pt-4 text-center">
        <div class="col-8"></div>
        <div class="col-4">
            <div class="border-top pt-2 text-muted small">
                Authorized Signatory
                <br><strong>VeloParts Marketplace</strong>
            </div>
        </div>
    </div>
</div>

</body>
</html>
