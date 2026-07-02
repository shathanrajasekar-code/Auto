<?php
/**
 * Namma AutoParts - User Registration Page
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect('index.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF verification
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = "CSRF security verification failed. Please try again.";
    } else {
        // Collect & Sanitize input
        $name = clean_input($_POST['name']);
        $email = clean_input($_POST['email']);
        $phone = clean_input($_POST['phone']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $role = clean_input($_POST['role']); // customer, vendor
        $is_b2b = isset($_POST['is_b2b']) ? 1 : 0;
        $referral_code = clean_input($_POST['referral_code']);

        // Vendor details
        $shop_name = clean_input($_POST['shop_name'] ?? '');
        $gst_number = clean_input($_POST['gst_number'] ?? '');
        $location = clean_input($_POST['location'] ?? '');
        $whatsapp_number = clean_input($_POST['whatsapp_number'] ?? '');

        // Validation
        if (empty($name) || empty($email) || empty($phone) || empty($password) || empty($confirm_password)) {
            $error = "All mandatory fields are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email address format.";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters long.";
        } elseif ($role === 'vendor' && (empty($shop_name) || empty($gst_number) || empty($location))) {
            $error = "Vendor business details (Shop Name, GSTIN, Location) are required.";
        } else {
            try {
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = "Email address is already registered.";
                } else {
                    // Check referrer
                    $referred_by_id = null;
                    if (!empty($referral_code)) {
                        $ref_stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
                        $ref_stmt->execute([$referral_code]);
                        $ref_user = $ref_stmt->fetch();
                        if ($ref_user) {
                            $referred_by_id = $ref_user['id'];
                        } else {
                            $error = "Invalid referral code.";
                        }
                    }

                    if (empty($error)) {
                        $pdo->beginTransaction();

                        // Create unique referral code for the new user
                        $new_ref_code = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $name), 0, 4)) . rand(100, 999);
                        
                        // Hash password
                        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                        
                        // Determine final role & status
                        $user_role = ($role === 'vendor') ? 'vendor' : 'customer';
                        $is_b2b_approved = 0; // If they want B2B, it starts as pending
                        
                        // Insert User
                        $user_sql = "INSERT INTO users (name, email, phone, password, role, loyalty_points, referral_code, referred_by, is_b2b_approved) 
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        // Customers registering with referral code get 50 welcome points immediately, 
                        // Referrer gets points on customer's first purchase.
                        $initial_points = $referred_by_id ? 50 : 0;
                        
                        $stmt = $pdo->prepare($user_sql);
                        $stmt->execute([$name, $email, $phone, $hashed_password, $user_role, $initial_points, $new_ref_code, $referred_by_id, ($is_b2b ? 0 : 0)]);
                        $new_user_id = $pdo->lastInsertId();

                        // If Vendor, Insert into vendors table
                        if ($user_role === 'vendor') {
                            $vendor_sql = "INSERT INTO vendors (user_id, shop_name, owner_name, gst_number, location, commission_rate, status, whatsapp_number) 
                                           VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)";
                            $stmt = $pdo->prepare($vendor_sql);
                            $stmt->execute([$new_user_id, $shop_name, $name, $gst_number, $location, DEFAULT_COMMISSION_RATE, $whatsapp_number]);
                        }

                        $pdo->commit();
                        $success = "Registration successful! " . ($user_role === 'vendor' ? "Your vendor account is pending admin approval." : "You can now log in.");
                    }
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "System Error: " . $e->getMessage();
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
    <title><?php echo __('register'); ?> - <?php echo SITE_NAME; ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom styling -->
    <style>
        :root {
            --primary-color: #0b1d33; /* Dark navy */
            --accent-color: #f75d00;  /* Orange CTAs */
            --light-bg: #f8f9fa;
            --dark-bg: #1c1c1e;
        }
        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar-brand {
            font-weight: 700;
            color: var(--primary-color) !important;
        }
        .navbar-brand span {
            color: var(--accent-color);
        }
        .register-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            background: #fff;
        }
        .register-header {
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
            transition: all 0.2s ease-in-out;
        }
        .btn-accent:hover {
            background-color: #d65000;
            border-color: #d65000;
            color: white;
        }
        .password-strength-bar {
            height: 5px;
            transition: width 0.3s ease-in-out;
            width: 0%;
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom py-3">
        <div class="container">
            <a class="navbar-brand" href="index.php"><span>Namma</span> AutoParts</a>
            <div class="d-flex">
                <a href="login.php" class="btn btn-outline-primary me-2"><i class="fa fa-sign-in-alt me-1"></i><?php echo __('login'); ?></a>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="register-card card">
                    <div class="register-header">
                        <h2 class="mb-1"><i class="fa fa-user-plus me-2"></i><?php echo __('register'); ?></h2>
                        <p class="mb-0 text-white-50"><?php echo __('tagline'); ?></p>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fa fa-exclamation-circle me-2"></i><?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fa fa-check-circle me-2"></i><?php echo $success; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="register.php" id="registerForm">
                            <?php echo csrf_input(); ?>
                            
                            <h5 class="mb-3 text-primary"><i class="fa fa-id-card me-2"></i>Account Details</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" required placeholder="Enter full name">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="email" name="email" required placeholder="Enter email address">
                                    <div id="emailFeedback" class="form-text"></div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone Number *</label>
                                    <input type="text" class="form-control" id="phone" name="phone" required placeholder="10-digit mobile number">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="referral_code" class="form-label">Referral Code (Optional)</label>
                                    <input type="text" class="form-control" id="referral_code" name="referral_code" placeholder="Enter referral code">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Password *</label>
                                    <input type="password" class="form-control" id="password" name="password" required placeholder="Minimum 8 characters">
                                    <div class="progress mt-2" style="height: 5px;">
                                        <div id="strengthBar" class="progress-bar password-strength-bar bg-danger" role="progressbar"></div>
                                    </div>
                                    <small id="passwordFeedback" class="form-text text-muted">Password Strength: Too Weak</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required placeholder="Re-enter password">
                                </div>
                            </div>

                            <hr class="my-4">
                            
                            <h5 class="mb-3 text-primary"><i class="fa fa-user-tag me-2"></i>Choose Account Type</h5>
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <div class="form-check card p-3 text-center mb-2">
                                        <input class="form-check-input mx-auto mb-2" type="radio" name="role" id="roleCustomer" value="customer" checked>
                                        <label class="form-check-label fw-bold" for="roleCustomer">
                                            <i class="fa fa-shopping-bag fa-lg text-primary d-block mb-1"></i>
                                            Customer Account
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check card p-3 text-center mb-2">
                                        <input class="form-check-input mx-auto mb-2" type="radio" name="role" id="roleB2B" value="customer">
                                        <label class="form-check-label fw-bold" for="roleB2B">
                                            <i class="fa fa-warehouse fa-lg text-success d-block mb-1"></i>
                                            B2B Garage Account
                                        </label>
                                        <!-- Hidden input to send B2B status -->
                                        <input type="hidden" name="is_b2b" id="is_b2b_input" value="0">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check card p-3 text-center mb-2">
                                        <input class="form-check-input mx-auto mb-2" type="radio" name="role" id="roleVendor" value="vendor">
                                        <label class="form-check-label fw-bold" for="roleVendor">
                                            <i class="fa fa-truck fa-lg text-warning d-block mb-1"></i>
                                            Seller / Vendor
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Vendor Specific Section (Toggled via JS) -->
                            <div id="vendorDetailsSection" style="display: none;" class="p-3 bg-light rounded border mb-4">
                                <h5 class="mb-3 text-warning"><i class="fa fa-store me-2"></i>Vendor Shop details</h5>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="shop_name" class="form-label">Shop Name *</label>
                                        <input type="text" class="form-control" id="shop_name" name="shop_name" placeholder="e.g. Coimbatore Auto Spares">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="gst_number" class="form-label">GSTIN (GST Number) *</label>
                                        <input type="text" class="form-control" id="gst_number" name="gst_number" placeholder="15-digit GSTIN number">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="location" class="form-label">Shop Location / Address *</label>
                                        <input type="text" class="form-control" id="location" name="location" placeholder="City name, State">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="whatsapp_number" class="form-label">WhatsApp Order Number</label>
                                        <input type="text" class="form-control" id="whatsapp_number" name="whatsapp_number" placeholder="Include country code (e.g. 919876543210)">
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-accent btn-lg"><i class="fa fa-check me-2"></i>Submit Registration</button>
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

    <script>
        $(document).ready(function() {
            // Toggles B2B input and Vendor inputs
            $('input[name="role"]').on('change', function() {
                var selectedId = $(this).attr('id');
                if (selectedId === 'roleVendor') {
                    $('#vendorDetailsSection').slideDown();
                    $('#is_b2b_input').val('0');
                    // make vendor fields required
                    $('#shop_name, #gst_number, #location').attr('required', true);
                } else {
                    $('#vendorDetailsSection').slideUp();
                    $('#shop_name, #gst_number, #location').attr('required', false);
                    if (selectedId === 'roleB2B') {
                        $('#is_b2b_input').val('1');
                    } else {
                        $('#is_b2b_input').val('0');
                    }
                }
            });

            // Email check via AJAX
            $('#email').on('blur', function() {
                var email = $(this).val();
                if (email.length > 0) {
                    $.ajax({
                        url: 'api/auth-check.php',
                        type: 'GET',
                        data: { email: email },
                        success: function(response) {
                            if (response.valid) {
                                if (response.exists) {
                                    $('#emailFeedback')
                                        .removeClass('text-success')
                                        .addClass('text-danger')
                                        .html('<i class="fa fa-times-circle me-1"></i>' + response.message);
                                } else {
                                    $('#emailFeedback')
                                        .removeClass('text-danger')
                                        .addClass('text-success')
                                        .html('<i class="fa fa-check-circle me-1"></i>' + response.message);
                                }
                            } else {
                                $('#emailFeedback').addClass('text-danger').html(response.message);
                            }
                        }
                    });
                }
            });

            // Password strength calculation
            $('#password').on('input', function() {
                var password = $(this).val();
                var score = 0;
                
                if (password.length >= 8) score++;
                if (password.match(/[a-z]/)) score++;
                if (password.match(/[A-Z]/)) score++;
                if (password.match(/[0-9]/)) score++;
                if (password.match(/[^a-zA-Z0-9]/)) score++;

                var bar = $('#strengthBar');
                var feedback = $('#passwordFeedback');

                bar.removeClass('bg-danger bg-warning bg-info bg-success');
                
                if (password.length === 0) {
                    bar.css('width', '0%');
                    feedback.text('Password Strength: Too Weak');
                } else if (score <= 2) {
                    bar.addClass('bg-danger').css('width', '25%');
                    feedback.text('Password Strength: Weak');
                } else if (score === 3) {
                    bar.addClass('bg-warning').css('width', '50%');
                    feedback.text('Password Strength: Moderate');
                } else if (score === 4) {
                    bar.addClass('bg-info').css('width', '75%');
                    feedback.text('Password Strength: Strong');
                } else {
                    bar.addClass('bg-success').css('width', '100%');
                    feedback.text('Password Strength: Excellent');
                }
            });
        });
    </script>
</body>
</html>
