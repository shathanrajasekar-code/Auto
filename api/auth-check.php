<?php
/**
 * Namma AutoParts - AJAX Email Uniqueness Checker
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$email = isset($_GET['email']) ? trim($_GET['email']) : '';

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['valid' => false, 'message' => 'Invalid email address']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo json_encode(['valid' => true, 'exists' => true, 'message' => 'Email is already registered']);
    } else {
        echo json_encode(['valid' => true, 'exists' => false, 'message' => 'Email is available']);
    }
} catch (PDOException $e) {
    echo json_encode(['valid' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
