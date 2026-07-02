<?php
/**
 * Namma AutoParts - AJAX Coupon Verification Endpoint
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$code = isset($_GET['code']) ? strtoupper(trim($_GET['code'])) : '';

if (empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a coupon code.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND expiry >= CURDATE()");
    $stmt->execute([$code]);
    $coupon = $stmt->fetch();

    if ($coupon) {
        if ($coupon['used_count'] >= $coupon['usage_limit']) {
            echo json_encode(['success' => false, 'message' => 'This coupon has reached its usage limit.']);
        } else {
            echo json_encode([
                'success' => true,
                'code' => $coupon['code'],
                'discount_type' => $coupon['discount_type'],
                'discount_value' => floatval($coupon['discount_value'])
            ]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired coupon code.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
