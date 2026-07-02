<?php
/**
 * Namma AutoParts - AJAX Cart Manager (CRUD API)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// Retrieve session identifier for guest cart tracking
if (empty($_SESSION['guest_session_id'])) {
    $_SESSION['guest_session_id'] = session_id();
}
$session_id = $_SESSION['guest_session_id'];
$user_id = is_logged_in() ? $_SESSION['user_id'] : null;

try {
    switch ($action) {
        case 'add':
            $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
            $qty = isset($_POST['qty']) ? max(1, intval($_POST['qty'])) : 1;
            $garage_id = isset($_POST['garage_id']) && intval($_POST['garage_id']) > 0 ? intval($_POST['garage_id']) : null;
            $booking_date = !empty($_POST['booking_date']) ? clean_input($_POST['booking_date']) : null;

            if ($product_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
                exit;
            }

            // Check stock
            $st_stmt = $pdo->prepare("SELECT stock_qty, name FROM products WHERE id = ?");
            $st_stmt->execute([$product_id]);
            $product = $st_stmt->fetch();
            if (!$product) {
                echo json_encode(['success' => false, 'message' => 'Product not found']);
                exit;
            }
            if ($product['stock_qty'] < $qty) {
                echo json_encode(['success' => false, 'message' => "Only {$product['stock_qty']} units of '{$product['name']}' in stock."]);
                exit;
            }

            if ($user_id) {
                // Logged in: DB Write
                $stmt = $pdo->prepare("SELECT id, qty FROM cart WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$user_id, $product_id]);
                $existing = $stmt->fetch();

                if ($existing) {
                    $new_qty = $existing['qty'] + $qty;
                    if ($product['stock_qty'] < $new_qty) {
                        $new_qty = $product['stock_qty']; // Limit to maximum stock
                    }
                    $up = $pdo->prepare("UPDATE cart SET qty = ?, garage_id = ?, booking_date = ? WHERE id = ?");
                    $up->execute([$new_qty, $garage_id, $booking_date, $existing['id']]);
                } else {
                    $ins = $pdo->prepare("INSERT INTO cart (user_id, product_id, qty, garage_id, booking_date) VALUES (?, ?, ?, ?, ?)");
                    $ins->execute([$user_id, $product_id, $qty, $garage_id, $booking_date]);
                }
            } else {
                // Guest: Session Write
                if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
                    $_SESSION['cart'] = [];
                }
                
                if (isset($_SESSION['cart'][$product_id])) {
                    $new_qty = $_SESSION['cart'][$product_id]['qty'] + $qty;
                    if ($product['stock_qty'] < $new_qty) {
                        $new_qty = $product['stock_qty'];
                    }
                    $_SESSION['cart'][$product_id]['qty'] = $new_qty;
                    $_SESSION['cart'][$product_id]['garage_id'] = $garage_id;
                    $_SESSION['cart'][$product_id]['booking_date'] = $booking_date;
                } else {
                    $_SESSION['cart'][$product_id] = [
                        'qty' => $qty,
                        'garage_id' => $garage_id,
                        'booking_date' => $booking_date
                    ];
                }
            }
            
            echo json_encode(['success' => true, 'message' => 'Item added to cart successfully!']);
            break;

        case 'update':
            $cart_id = isset($_POST['cart_id']) ? intval($_POST['cart_id']) : 0; // DB ID
            $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0; // For Session
            $qty = isset($_POST['qty']) ? max(1, intval($_POST['qty'])) : 1;

            if ($user_id && $cart_id > 0) {
                // Get product ID for stock check
                $c_stmt = $pdo->prepare("SELECT product_id FROM cart WHERE id = ? AND user_id = ?");
                $c_stmt->execute([$cart_id, $user_id]);
                $p_id = $c_stmt->fetchColumn();

                if ($p_id) {
                    $st_stmt = $pdo->prepare("SELECT stock_qty FROM products WHERE id = ?");
                    $st_stmt->execute([$p_id]);
                    $stock = $st_stmt->fetchColumn();
                    if ($stock < $qty) {
                        $qty = $stock; // Limit
                    }

                    $up = $pdo->prepare("UPDATE cart SET qty = ? WHERE id = ? AND user_id = ?");
                    $up->execute([$qty, $cart_id, $user_id]);
                    echo json_encode(['success' => true, 'qty' => $qty]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Cart item not found']);
                }
            } elseif (!$user_id && $product_id > 0) {
                // Session cart update
                if (isset($_SESSION['cart'][$product_id])) {
                    $st_stmt = $pdo->prepare("SELECT stock_qty FROM products WHERE id = ?");
                    $st_stmt->execute([$product_id]);
                    $stock = $st_stmt->fetchColumn();
                    if ($stock < $qty) {
                        $qty = $stock;
                    }
                    $_SESSION['cart'][$product_id]['qty'] = $qty;
                    echo json_encode(['success' => true, 'qty' => $qty]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Product not in cart']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            }
            break;

        case 'remove':
            $cart_id = isset($_POST['cart_id']) ? intval($_POST['cart_id']) : 0;
            $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

            if ($user_id && $cart_id > 0) {
                $del = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
                $del->execute([$cart_id, $user_id]);
                echo json_encode(['success' => true]);
            } elseif (!$user_id && $product_id > 0) {
                if (isset($_SESSION['cart'][$product_id])) {
                    unset($_SESSION['cart'][$product_id]);
                }
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
}
