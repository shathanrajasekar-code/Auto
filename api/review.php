<?php
/**
 * Namma AutoParts - AJAX Review Submission Endpoint
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'You must log in to submit a review.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'CSRF validation failed. Please refresh the page.']);
    exit;
}

$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
$rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
$user_id = $_SESSION['user_id'];

if ($product_id <= 0 || $rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Please provide a valid rating (1-5 stars).']);
    exit;
}

try {
    // Check if verified purchase
    // Query if the user has an order containing this product that is Delivered
    $verify_sql = "SELECT COUNT(oi.id) 
                   FROM order_items oi 
                   JOIN orders o ON oi.order_id = o.id 
                   WHERE o.user_id = ? AND oi.product_id = ? AND o.status = 'Delivered'";
    $v_stmt = $pdo->prepare($verify_sql);
    $v_stmt->execute([$user_id, $product_id]);
    $verified = $v_stmt->fetchColumn() > 0 ? 1 : 0;

    // Optional: Only allow reviews from verified purchasers (strict check)
    if (!$verified) {
        echo json_encode(['success' => false, 'message' => 'Only verified purchasers of this product can submit a review.']);
        exit;
    }

    // Insert Review
    $insert_sql = "INSERT INTO reviews (product_id, user_id, rating, comment, verified_purchase) VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($insert_sql);
    $stmt->execute([$product_id, $user_id, $rating, $comment, $verified]);

    echo json_encode([
        'success' => true, 
        'message' => 'Thank you! Your review has been submitted successfully.',
        'review' => [
            'name' => $_SESSION['user_name'],
            'rating' => $rating,
            'comment' => esc($comment),
            'verified' => $verified,
            'date' => date('d M Y')
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
}
