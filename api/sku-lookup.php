<?php
/**
 * Namma AutoParts - AJAX SKU Lookup Endpoint (Admin Barcode Scanner)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Enforce admin permission
if (!is_logged_in() || !has_role('admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$sku = isset($_GET['sku']) ? trim($_GET['sku']) : '';

if (empty($sku)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a valid SKU.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT p.*, c.name as category_name, v.shop_name 
                           FROM products p 
                           JOIN categories c ON p.category_id = c.id 
                           LEFT JOIN vendors v ON p.vendor_id = v.id 
                           WHERE p.sku = ?");
    $stmt->execute([$sku]);
    $product = $stmt->fetch();

    if ($product) {
        // QR Code generator URL
        $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($product['sku']);
        $product['qr_code_url'] = $qr_url;

        echo json_encode(['success' => true, 'product' => $product]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Product SKU not found in inventory.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
}
