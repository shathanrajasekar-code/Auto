<?php
/**
 * Namma AutoParts - AJAX Catalog Filter Engine
 * Returns JSON product results, fitment mapping, and pagination HTML
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$categories = isset($_GET['categories']) ? $_GET['categories'] : []; // Array of slugs
$brands = isset($_GET['brands']) ? $_GET['brands'] : []; // Array of brands
$conditions = isset($_GET['conditions']) ? $_GET['conditions'] : []; // Array
$min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0.00;
$max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 0.00;
$core_charge = isset($_GET['core_charge']) ? intval($_GET['core_charge']) : 0;
$fitment_only = isset($_GET['fitment_only']) ? intval($_GET['fitment_only']) : 0;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 9;
$offset = ($page - 1) * $limit;

try {
    $where = ["p.stock_qty > 0"]; // Only show in-stock products
    $params = [];

    // Search term filter
    if (!empty($search)) {
        $where[] = "(p.name LIKE :search OR p.sku LIKE :search OR p.brand LIKE :search)";
        $params['search'] = "%{$search}%";
    }

    // Category filter
    if (!empty($categories) && is_array($categories)) {
        $cat_placeholders = [];
        foreach ($categories as $i => $cat_slug) {
            $key = "cat_{$i}";
            $cat_placeholders[] = ":{$key}";
            $params[$key] = $cat_slug;
        }
        $where[] = "c.slug IN (" . implode(',', $cat_placeholders) . ")";
    }

    // Brand filter
    if (!empty($brands) && is_array($brands)) {
        $brand_placeholders = [];
        foreach ($brands as $i => $brand) {
            $key = "brand_{$i}";
            $brand_placeholders[] = ":{$key}";
            $params[$key] = $brand;
        }
        $where[] = "p.brand IN (" . implode(',', $brand_placeholders) . ")";
    }

    // Condition filter
    if (!empty($conditions) && is_array($conditions)) {
        $cond_placeholders = [];
        foreach ($conditions as $i => $cond) {
            $key = "cond_{$i}";
            $cond_placeholders[] = ":{$key}";
            $params[$key] = $cond;
        }
        $where[] = "p.condition IN (" . implode(',', $cond_placeholders) . ")";
    }

    // Price range filters
    if ($min_price > 0) {
        $where[] = "p.price >= :min_price";
        $params['min_price'] = $min_price;
    }
    if ($max_price > 0) {
        $where[] = "p.price <= :max_price";
        $params['max_price'] = $max_price;
    }

    // Core Charge only
    if ($core_charge === 1) {
        $where[] = "p.core_charge > 0";
    }

    // Fitment filter
    $active_vehicle_id = 0;
    if ($fitment_only === 1 && isset($_SESSION['active_vehicle'])) {
        $active_vehicle_id = $_SESSION['active_vehicle']['id'];
        $where[] = "p.id IN (SELECT product_id FROM product_vehicle_fitment WHERE vehicle_id = :active_vehicle_id)";
        $params['active_vehicle_id'] = $active_vehicle_id;
    }

    // Build query
    $where_sql = " WHERE " . implode(" AND ", $where);
    
    // Count total matches
    $count_sql = "SELECT COUNT(DISTINCT p.id) FROM products p JOIN categories c ON p.category_id = c.id" . $where_sql;
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_items = $count_stmt->fetchColumn();
    $total_pages = ceil($total_items / $limit);

    // Sorting clauses
    $order_sql = " ORDER BY p.created_at DESC";
    if ($sort === 'price_asc') {
        $order_sql = " ORDER BY p.price ASC";
    } elseif ($sort === 'price_desc') {
        $order_sql = " ORDER BY p.price DESC";
    } elseif ($sort === 'rating') {
        $order_sql = " ORDER BY (SELECT COALESCE(AVG(rating), 0) FROM reviews WHERE product_id = p.id) DESC";
    }

    // Select products
    $select_sql = "SELECT p.*, c.name as category_name, c.slug as category_slug 
                   FROM products p 
                   JOIN categories c ON p.category_id = c.id" . $where_sql . $order_sql . " LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($select_sql);
    
    // Bind limit & offset as integers (PDO need bindValue to avoid treating them as strings in prepared mode)
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue('limit', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue('offset', (int)$offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $products = $stmt->fetchAll();

    // Map fitment array for active vehicle
    $fitment_map = [];
    if (isset($_SESSION['active_vehicle'])) {
        $v_id = $_SESSION['active_vehicle']['id'];
        $fit_stmt = $pdo->prepare("SELECT product_id FROM product_vehicle_fitment WHERE vehicle_id = ?");
        $fit_stmt->execute([$v_id]);
        $fitment_map = $fit_stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // Generate Pagination HTML
    $pagination_html = '';
    if ($total_pages > 1) {
        $pagination_html .= '<nav aria-label="Catalog Navigation"><ul class="pagination justify-content-center">';
        
        // Previous Button
        $prev_disabled = ($page === 1) ? 'disabled' : '';
        $pagination_html .= '<li class="page-item ' . $prev_disabled . '"><button class="page-link" data-page="' . ($page-1) . '" type="button">Previous</button></li>';
        
        // Page Numbers
        for ($i = 1; $i <= $total_pages; $i++) {
            $active_class = ($i === $page) ? 'active' : '';
            $pagination_html .= '<li class="page-item ' . $active_class . '"><button class="page-link" data-page="' . $i . '" type="button">' . $i . '</button></li>';
        }
        
        // Next Button
        $next_disabled = ($page === $total_pages) ? 'disabled' : '';
        $pagination_html .= '<li class="page-item ' . $next_disabled . '"><button class="page-link" data-page="' . ($page+1) . '" type="button">Next</button></li>';
        
        $pagination_html .= '</ul></nav>';
    }

    echo json_encode([
        'success' => true,
        'products' => $products,
        'fitment_map' => $fitment_map,
        'total_items' => $total_items,
        'pagination' => $pagination_html,
        'lang' => $_SESSION['lang'] ?? 'en',
        'active_vehicle' => $_SESSION['active_vehicle'] ?? null
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
