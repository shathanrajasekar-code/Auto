<?php
/**
 * Namma AutoParts - Global Functions & Utilities
 */

// Generate CSRF Token
function get_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF Token
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Output Hidden CSRF Input
function csrf_input() {
    return '<input type="hidden" name="csrf_token" value="' . esc(get_csrf_token()) . '">';
}

// Sanitize Inputs
function clean_input($data) {
    if (is_array($data)) {
        return array_map('clean_input', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Escape Outputs
function esc($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// Check if User is Logged In
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Get Logged In User
function get_logged_in_user() {
    return is_logged_in() ? [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'role' => $_SESSION['user_role']
    ] : null;
}

// Check Role
function has_role($roles) {
    if (!is_logged_in()) return false;
    $user_role = $_SESSION['user_role'];
    if (is_array($roles)) {
        return in_array($user_role, $roles);
    }
    return $user_role === $roles;
}

// Enforce Role
function require_role($roles) {
    if (!is_logged_in()) {
        header("Location: " . SITE_URL . "/login.php");
        exit;
    }
    if (!has_role($roles)) {
        header("Location: " . SITE_URL . "/index.php?error=unauthorized");
        exit;
    }
}

// Redirect Helper
function redirect($path) {
    header("Location: " . SITE_URL . "/" . ltrim($path, '/'));
    exit;
}

// Safe File Upload Helper
function upload_file($file, $allowed_types = ['image/jpeg', 'image/png', 'image/webp'], $max_size = 2097152) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['status' => false, 'message' => 'No file uploaded or upload error.'];
    }

    // Check size
    if ($file['size'] > $max_size) {
        return ['status' => false, 'message' => 'File size exceeds 2MB limit.'];
    }

    // Check extension
    $filename = $file['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowed_extensions)) {
        return ['status' => false, 'message' => 'Invalid file extension. Only JPG, PNG, WEBP allowed.'];
    }

    // Check MIME type using finfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed_types)) {
        return ['status' => false, 'message' => 'Invalid file type. Upload failed.'];
    }

    // Create target directory if it doesn't exist
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    // Generate unique name
    $new_filename = uniqid('part_', true) . '.' . $ext;
    $target_file = UPLOAD_DIR . $new_filename;

    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return ['status' => true, 'filename' => $new_filename];
    }

    return ['status' => false, 'message' => 'Failed to move uploaded file.'];
}

// Calculate loyalty points earned (1 point per ₹100 spent)
function calculate_earned_points($total_amount) {
    return floor($total_amount / 100);
}

// Convert points to discount (1 point = ₹1)
function points_to_rupees($points) {
    return $points * 1.00;
}

// GST Calculation
// Prices are treated as INCLUSIVE of GST (18%). We split out base and taxes.
// CGST/SGST apply if the buyer and seller are in the same state (default Tamil Nadu).
// Otherwise, IGST applies.
function calculate_gst($subtotal, $shipping_state, $vendor_state = 'Tamil Nadu') {
    $tax_rate = 0.18; // 18% standard rate for auto parts
    
    // Reverse calculate base price: InclusivePrice = Base + Base*0.18 = Base * 1.18
    $base_amount = $subtotal / (1 + $tax_rate);
    $total_gst = $subtotal - $base_amount;
    
    $cgst = 0;
    $sgst = 0;
    $igst = 0;
    
    // Check state matching (case insensitive)
    if (strcasecmp(trim($shipping_state), trim($vendor_state)) === 0) {
        $cgst = $total_gst / 2;
        $sgst = $total_gst / 2;
    } else {
        $igst = $total_gst;
    }
    
    return [
        'base_amount' => round($base_amount, 2),
        'total_gst' => round($total_gst, 2),
        'cgst' => round($cgst, 2),
        'sgst' => round($sgst, 2),
        'igst' => round($igst, 2)
    ];
}

// Format Currency
function format_price($amount) {
    return '₹' . number_format($amount, 2);
}
