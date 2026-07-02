<?php
/**
 * Namma AutoParts - User Login Page
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// Redirect if already logged in
if (is_logged_in()) {
    if ($_SESSION['user_role'] === 'admin') {
        redirect('admin/dashboard.php');
    } elseif ($_SESSION['user_role'] === 'vendor') {
        redirect('vendor-panel/dashboard.php');
    } else {
        redirect('index.php');
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = "CSRF verification failed. Please reload the page.";
    } else {
        $email = clean_input($_POST['email']);
        $password = $_POST['password'];

        if (empty($email) || empty($password)) {
            $error = "Both email and password are required.";
        } else {
            try {
                // Find user by email
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user) {
                    $now = date('Y-m-d H:i:s');
                    
                    // Check Lockout
                    if (!empty($user['lockout_until']) && strtotime($user['lockout_until']) > strtotime($now)) {
                        $diff = strtotime($user['lockout_until']) - strtotime($now);
                        $minutes = ceil($diff / 60);
                        $error = "This account is locked due to too many failed login attempts. Please try again in {$minutes} minute(s).";
                    } else {
                        // Verify Password
                        if (password_verify($password, $user['password'])) {
                            
                            // Check Vendor Status
                            if ($user['role'] === 'vendor') {
                                $v_stmt = $pdo->prepare("SELECT status FROM vendors WHERE user_id = ?");
                                $v_stmt->execute([$user['id']]);
                                $vendor = $v_stmt->fetch();
                                
                                if (!$vendor || $vendor['status'] === 'pending') {
                                    $error = "Your vendor registration is pending admin approval. You will receive an email once approved.";
                                } elseif ($vendor['status'] === 'rejected') {
                                    $error = "Your vendor registration has been rejected. Contact support for details.";
                                }
                            }

                            if (empty($error)) {
                                // Reset rate limiting
                                $reset_stmt = $pdo->prepare("UPDATE users SET login_attempts = 0, lockout_until = NULL WHERE id = ?");
                                $reset_stmt->execute([$user['id']]);

                                // Setup session
                                $_SESSION['user_id'] = $user['id'];
                                $_SESSION['user_name'] = $user['name'];
                                $_SESSION['user_email'] = $user['email'];
                                $_SESSION['user_role'] = $user['role'];
                                $_SESSION['loyalty_points'] = $user['loyalty_points'];

                                // Merge guest cart into DB
                                if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
                                    foreach ($_SESSION['cart'] as $p_id => $item) {
                                        $stmt_cart = $pdo->prepare("SELECT id, qty FROM cart WHERE user_id = ? AND product_id = ?");
                                        $stmt_cart->execute([$user['id'], $p_id]);
                                        $db_cart = $stmt_cart->fetch();
                                        if ($db_cart) {
                                            $new_qty = $db_cart['qty'] + $item['qty'];
                                            $up_cart = $pdo->prepare("UPDATE cart SET qty = ?, garage_id = ?, booking_date = ? WHERE id = ?");
                                            $up_cart->execute([$new_qty, $item['garage_id'], $item['booking_date'], $db_cart['id']]);
                                        } else {
                                            $ins_cart = $pdo->prepare("INSERT INTO cart (user_id, product_id, qty, garage_id, booking_date) VALUES (?, ?, ?, ?, ?)");
                                            $ins_cart->execute([$user['id'], $p_id, $item['qty'], $item['garage_id'], $item['booking_date']]);
                                        }
                                    }
                                    unset($_SESSION['cart']);
                                }

                                // Redirect depending on role
                                if ($user['role'] === 'admin') {
                                    redirect('admin/dashboard.php');
                                } elseif ($user['role'] === 'vendor') {
                                    redirect('vendor-panel/dashboard.php');
                                } else {
                                    redirect('index.php');
                                }
                            }
                        } else {
                            // Increment failed attempts
                            $attempts = $user['login_attempts'] + 1;
                            $lockout_until = null;
                            if ($attempts >= 5) {
                                $lockout_until = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                                $error = "Invalid password. Your account is now locked for 15 minutes.";
                            } else {
                                $remaining = 5 - $attempts;
                                $error = "Invalid password. {$remaining} attempts remaining before account lockout.";
                            }
                            
                            $up_stmt = $pdo->prepare("UPDATE users SET login_attempts = ?, lockout_until = ? WHERE id = ?");
                            $up_stmt->execute([$attempts, $lockout_until, $user['id']]);
                        }
                    }
                } else {
                    $error = "Invalid email address or account does not exist.";
                }
            } catch (PDOException $e) {
                $error = "Database Error: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang'] ?? 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('login'); ?> - <?php echo SITE_NAME; ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0b1d33;
            --accent-color: #f75d00;
            --light-bg: #f8f9fa;
        }
        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            background: #fff;
        }
        .login-header {
            background: var(--primary-color);
            color: #fff;
            border-radius: 12px 12px 0 0;
            padding: 2rem;
            text-align: center;
        }
        .btn-accent {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
            color: white;
            font-weight: 600;
        }
        .btn-accent:hover {
            background-color: #d65000;
            border-color: #d65000;
            color: white;
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom py-3">
        <div class="container">
            <a class="navbar-brand fw-bold text-dark" href="index.php"><span class="text-orange" style="color: var(--accent-color);">Namma</span> AutoParts</a>
            <div class="d-flex">
                <a href="register.php" class="btn btn-outline-primary"><i class="fa fa-user-plus me-1"></i><?php echo __('register'); ?></a>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="login-card card">
                    <div class="login-header">
                        <h2 class="mb-1"><i class="fa fa-sign-in-alt me-2"></i><?php echo __('login'); ?></h2>
                        <p class="mb-0 text-white-50">Log in to manage your orders & vehicles</p>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fa fa-exclamation-circle me-2"></i><?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="login.php">
                            <?php echo csrf_input(); ?>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-envelope text-muted"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" required placeholder="Enter your email">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <label for="password" class="form-label mb-0">Password</label>
                                    <a href="forgot-password.php" class="text-decoration-none small text-muted">Forgot Password?</a>
                                </div>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-lock text-muted"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required placeholder="Enter your password">
                                </div>
                            </div>

                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-accent btn-lg"><i class="fa fa-sign-in-alt me-2"></i>Sign In</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap & jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
