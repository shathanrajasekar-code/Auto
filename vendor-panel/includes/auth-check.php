<?php
/**
 * Namma AutoParts - Vendor Panel Auth Checker
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// Enforce login and vendor role
require_role('vendor');

// Get vendor specific record
try {
    $stmt = $pdo->prepare("SELECT * FROM vendors WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $vendor_info = $stmt->fetch();

    if (!$vendor_info || $vendor_info['status'] !== 'approved') {
        // Destroy session and redirect if not approved vendor
        header("Location: " . SITE_URL . "/login.php?error=unauthorized_vendor");
        exit;
    }
    
    $_SESSION['vendor_id'] = $vendor_info['id'];
} catch (PDOException $e) {
    die("Vendor Authentication Database Error: " . $e->getMessage());
}
