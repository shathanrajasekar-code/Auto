<?php
/**
 * Namma AutoParts - AJAX Live Product Search Autocomplete
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$query = isset($_GET['query']) ? trim($_GET['query']) : '';

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

try {
    // Search by name, SKU, or brand
    $search_term = "%{$query}%";
    $sql = "SELECT id, name, slug, sku, brand, price, discount_price, `condition`, images 
            FROM products 
            WHERE name LIKE ? OR sku LIKE ? OR brand LIKE ? 
            LIMIT 6";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$search_term, $search_term, $search_term]);
    $results = $stmt->fetchAll();
    
    echo json_encode($results);
} catch (PDOException $e) {
    // Return empty array on error to prevent breaking front-end UI
    echo json_encode([]);
}
