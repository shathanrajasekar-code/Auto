<?php
/**
 * Namma AutoParts - Customer My Account Dashboard
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// Enforce login
require_role(['customer', 'admin']);

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

try {
    // 1. Fetch User details
    $u_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $u_stmt->execute([$user_id]);
    $user = $u_stmt->fetch();

    // Update session loyalty points count
    $_SESSION['loyalty_points'] = $user['loyalty_points'];

    // 2. Fetch Saved Vehicles (My Garage)
    $g_stmt = $pdo->prepare("SELECT usv.*, vm.make, vm.model, vm.year_from, vm.year_to, vm.engine_type, vm.fuel_type 
                             FROM user_saved_vehicles usv 
                             JOIN vehicles_master vm ON usv.vehicle_id = vm.id 
                             WHERE usv.user_id = ?");
    $g_stmt->execute([$user_id]);
    $saved_vehicles = $g_stmt->fetchAll();

    // 3. Fetch Orders History
    $o_stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
    $o_stmt->execute([$user_id]);
    $orders = $o_stmt->fetchAll();

    // 4. Fetch Service Bookings
    $sb_stmt = $pdo->prepare("SELECT sb.*, g.name as garage_name, g.location as garage_location, g.contact as garage_contact 
                              FROM service_bookings sb
                              JOIN garages g ON sb.garage_id = g.id
                              WHERE sb.user_id = ?
                              ORDER BY sb.booking_date DESC");
    $sb_stmt->execute([$user_id]);
    $bookings = $sb_stmt->fetchAll();

    // Fetch master vehicles list for adding new car
    $v_master_stmt = $pdo->query("SELECT DISTINCT make FROM vehicles_master ORDER BY make ASC");
    $makes = $v_master_stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    die("Database Error: " . htmlspecialchars($e->getMessage()));
}

$page_title = "My Account";
require_once __DIR__ . '/includes/header.php';
?>

<div class="container my-4">
    <!-- Header -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-8">
            <h2 class="fw-bold mb-1 text-dark"><i class="fa fa-user-circle text-orange me-2"></i>My Dashboard</h2>
            <p class="text-muted mb-0">Manage your garage, track spare parts orders, and view service bookings.</p>
        </div>
        <div class="col-md-4 text-md-end mt-2 mt-md-0">
            <span class="badge bg-secondary p-2"><i class="fa fa-envelope me-1"></i><?php echo esc($user['email']); ?></span>
            <span class="badge bg-primary p-2"><i class="fa fa-phone me-1"></i><?php echo esc($user['phone']); ?></span>
        </div>
    </div>

    <div class="row g-4">
        <!-- Sidebar stats (Loyalty Points, Referrals, B2B status) -->
        <div class="col-lg-4">
            <!-- Loyalty Points Card -->
            <div class="card shadow-sm mb-4 border border-light bg-navy text-white" style="background: linear-gradient(135deg, #0b1d33 0%, #1e293b 100%);">
                <div class="card-body p-4 text-center">
                    <i class="fa fa-coins text-orange fa-3x mb-3"></i>
                    <h5 class="fw-bold mb-1">Loyalty Points Balance</h5>
                    <div class="display-5 fw-bold text-orange mb-3"><?php echo $user['loyalty_points']; ?> <span class="fs-6 text-white-50">Points</span></div>
                    
                    <div class="p-3 bg-dark-blue rounded text-start border border-secondary">
                        <small class="text-orange d-block fw-bold mb-1"><i class="fa fa-share-alt me-1"></i>Referral System</small>
                        <small class="text-white-50 d-block mb-2">Share your code. Both you and your friend get 100 points when they make their first order!</small>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-dark border-secondary text-white-50 text-xs">Your Code</span>
                            <input type="text" class="form-control bg-dark border-secondary text-white fw-bold text-center" value="<?php echo esc($user['referral_code']); ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <!-- B2B Account Card -->
            <?php if ($user['is_b2b_approved'] == 1): ?>
                <div class="card shadow-sm border-success bg-light mb-4">
                    <div class="card-body p-4">
                        <h5 class="fw-bold text-success mb-2"><i class="fa fa-check-circle me-2"></i>B2B Garage Verified</h5>
                        <p class="small text-muted mb-3">You have access to Pay Later bulk pricing credit line.</p>
                        
                        <div class="row text-center border-top pt-3">
                            <div class="col-6 border-end">
                                <small class="text-muted d-block text-uppercase text-xs">Credit Limit</small>
                                <span class="fw-bold text-dark fs-5"><?php echo format_price($user['b2b_credit_limit']); ?></span>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block text-uppercase text-xs">Used Credit</small>
                                <span class="fw-bold text-danger fs-5"><?php echo format_price($user['b2b_credit_used']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card shadow-sm border border-light p-4 mb-4 bg-white text-center">
                    <i class="fa fa-warehouse text-muted fa-2x mb-2"></i>
                    <h6 class="fw-bold mb-1">Upgrade to B2B Garage Account</h6>
                    <p class="text-xs text-muted">Garage owners can register to pay later on credit limits, and unlock wholesale pricing tiers.</p>
                    <button class="btn btn-outline-primary btn-sm w-100" onclick="alert('Please contact admin@namma.com with your GST certificates to activate B2B status.');">Contact Sales Upgrade</button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Content Panels (My Garage, Orders, Bookings) -->
        <div class="col-lg-8">
            <!-- Tabs Navigation -->
            <ul class="nav nav-tabs border-bottom mb-3 gap-2" id="accountTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-bold text-dark" id="garage-tab" data-bs-toggle="tab" data-bs-target="#garagePanel" type="button" role="tab"><i class="fa fa-car text-orange me-2"></i>My Garage</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold text-dark" id="orders-tab" data-bs-toggle="tab" data-bs-target="#ordersPanel" type="button" role="tab"><i class="fa fa-box-open text-orange me-2"></i>Orders History</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold text-dark" id="bookings-tab" data-bs-toggle="tab" data-bs-target="#bookingsPanel" type="button" role="tab"><i class="fa fa-wrench text-orange me-2"></i>Garage Bookings</button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="accountTabsContent">
                
                <!-- 1. My Garage Tab -->
                <div class="tab-pane fade show active" id="garagePanel" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold text-dark mb-0">Saved Garage Profiles</h5>
                        <button class="btn btn-sm btn-accent" data-bs-toggle="collapse" data-bs-target="#addVehicleCollapse"><i class="fa fa-plus me-1"></i>Add New Car</button>
                    </div>

                    <!-- Add Vehicle Collapse section -->
                    <div class="collapse mb-4" id="addVehicleCollapse">
                        <div class="card card-body bg-light border">
                            <h6 class="fw-bold text-primary mb-3">Add Vehicle Profile to Garage</h6>
                            <form id="addVehicleForm">
                                <div class="row g-2">
                                    <div class="col-md-3">
                                        <select class="form-select form-select-sm" id="fitmentMake" required>
                                            <option value="">Select Make</option>
                                            <?php foreach ($makes as $m): ?>
                                                <option value="<?php echo esc($m); ?>"><?php echo esc($m); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <select class="form-select form-select-sm" id="fitmentModel" disabled required>
                                            <option value="">Select Model</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <select class="form-select form-select-sm" id="fitmentYear" disabled required>
                                            <option value="">Select Year</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <select class="form-select form-select-sm" id="fitmentEngine" name="vehicle_id" disabled required>
                                            <option value="">Select Engine</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-8">
                                        <input type="text" class="form-control form-control-sm" name="label" id="vehicleLabelInput" placeholder="Give this vehicle a nickname (e.g. My Daily Swift)">
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <button type="submit" class="btn btn-accent btn-sm w-100" id="fitmentSubmitBtn" disabled>Save Vehicle</button>
                                    </div>
                                </div>
                            </form>
                            <div id="addVehicleFeedback" class="mt-2 small" style="display:none;"></div>
                        </div>
                    </div>

                    <!-- Vehicles List -->
                    <div class="row g-3" id="savedVehiclesList">
                        <?php if (count($saved_vehicles) > 0): ?>
                            <?php foreach ($saved_vehicles as $sv): ?>
                                <div class="col-md-6 vehicle-card-col" data-vehicle-id="<?php echo $sv['vehicle_id']; ?>">
                                    <div class="card p-3 border shadow-sm <?php echo $sv['is_active'] ? 'border-success bg-light' : ''; ?>">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h6 class="fw-bold mb-0 text-dark"><?php echo esc($sv['label']); ?></h6>
                                                <small class="text-muted d-block text-xs"><?php echo esc($sv['make'] . ' ' . $sv['model']); ?></small>
                                                <small class="text-muted d-block text-xs" style="font-size:10px;">Engine: <?php echo esc($sv['engine_type']); ?> | Fuel: <?php echo esc($sv['fuel_type']); ?></small>
                                            </div>
                                            
                                            <!-- Actions -->
                                            <button class="btn btn-sm btn-outline-danger btn-delete-vehicle py-0 px-2" style="font-size:11px;" data-id="<?php echo $sv['vehicle_id']; ?>"><i class="fa fa-trash-alt"></i></button>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center mt-3 border-top pt-2">
                                            <div>
                                                <?php if ($sv['is_active']): ?>
                                                    <span class="text-success small fw-bold"><i class="fa fa-check-circle me-1"></i>Active filter</span>
                                                <?php else: ?>
                                                    <button class="btn btn-xs btn-outline-success py-0 px-2 btn-activate-vehicle" style="font-size:11px;" data-id="<?php echo $sv['vehicle_id']; ?>">Set Active Filter</button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12 text-center py-5">
                                <i class="fa fa-car text-muted fa-3x mb-2"></i>
                                <p class="text-muted">You have no vehicles saved in your garage.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 2. Orders History Tab -->
                <div class="tab-pane fade" id="ordersPanel" role="tabpanel">
                    <h5 class="fw-bold text-dark mb-3">Order History Log</h5>

                    <div class="d-flex flex-column gap-3">
                        <?php if (count($orders) > 0): ?>
                            <?php foreach ($orders as $ord): ?>
                                <div class="card p-3 border shadow-sm">
                                    <div class="row align-items-center g-2 small">
                                        <div class="col-md-3">
                                            <small class="text-muted d-block">Order Number</small>
                                            <span class="fw-bold text-dark"><?php echo esc($ord['order_no']); ?></span>
                                        </div>
                                        <div class="col-md-3">
                                            <small class="text-muted d-block">Date Placed</small>
                                            <span class="text-dark fw-bold"><?php echo date('d M Y', strtotime($ord['created_at'])); ?></span>
                                        </div>
                                        <div class="col-md-2">
                                            <small class="text-muted d-block">Total Value</small>
                                            <span class="text-orange fw-bold fs-6"><?php echo format_price($ord['total_amount']); ?></span>
                                        </div>
                                        <div class="col-md-2">
                                            <small class="text-muted d-block">Status</small>
                                            <!-- Status badges -->
                                            <?php 
                                            $st_color = 'bg-secondary';
                                            if ($ord['status'] === 'Placed') $st_color = 'bg-primary';
                                            elseif ($ord['status'] === 'Packed') $st_color = 'bg-info';
                                            elseif ($ord['status'] === 'Shipped') $st_color = 'bg-warning text-dark';
                                            elseif ($ord['status'] === 'Delivered') $st_color = 'bg-success';
                                            elseif ($ord['status'] === 'Cancelled') $st_color = 'bg-danger';
                                            ?>
                                            <span class="badge <?php echo $st_color; ?>"><?php echo esc($ord['status']); ?></span>
                                        </div>
                                        <div class="col-md-2 text-md-end">
                                            <!-- Download invoice link -->
                                            <a href="invoice.php?order_no=<?php echo esc($ord['order_no']); ?>" target="_blank" class="btn btn-sm btn-outline-primary w-100 py-1" style="font-size:11px;">
                                                <i class="fa fa-file-pdf me-1"></i>Invoice
                                            </a>
                                        </div>
                                    </div>
                                    
                                    <!-- Order status tracking progression bar -->
                                    <div class="mt-3 pt-2 border-top">
                                        <div class="d-flex justify-content-between text-xs text-muted" style="position: relative;">
                                            <span class="fw-bold <?php echo in_array($ord['status'], ['Placed', 'Packed', 'Shipped', 'Delivered']) ? 'text-primary' : ''; ?>"><i class="fa fa-shopping-basket"></i> Placed</span>
                                            <span class="fw-bold <?php echo in_array($ord['status'], ['Packed', 'Shipped', 'Delivered']) ? 'text-primary' : ''; ?>"><i class="fa fa-box"></i> Packed</span>
                                            <span class="fw-bold <?php echo in_array($ord['status'], ['Shipped', 'Delivered']) ? 'text-primary' : ''; ?>"><i class="fa fa-truck"></i> Shipped</span>
                                            <span class="fw-bold <?php echo ($ord['status'] === 'Delivered') ? 'text-success' : ''; ?>"><i class="fa fa-check-circle"></i> Delivered</span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-5 border rounded bg-light">
                                <i class="fa fa-box-open text-muted fa-3x mb-2"></i>
                                <p class="text-muted">You have not placed any orders yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 3. Garage Bookings Tab -->
                <div class="tab-pane fade" id="bookingsPanel" role="tabpanel">
                    <h5 class="fw-bold text-dark mb-3">Mechanic Service Appointments</h5>

                    <div class="table-responsive bg-white p-3 border rounded shadow-sm">
                        <?php if (count($bookings) > 0): ?>
                            <table class="table align-middle small mb-0">
                                <thead>
                                    <tr>
                                        <th>Service Details</th>
                                        <th>Workshop</th>
                                        <th>Vehicle Profile</th>
                                        <th>Scheduled Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookings as $b): ?>
                                        <tr>
                                            <td><strong><?php echo esc($b['service_type']); ?></strong></td>
                                            <td>
                                                <strong><?php echo esc($b['garage_name']); ?></strong><br>
                                                <span class="text-muted text-xs"><i class="fa fa-phone me-1"></i><?php echo esc($b['garage_contact']); ?></span>
                                            </td>
                                            <td><span class="badge bg-secondary"><?php echo esc($b['vehicle_details']); ?></span></td>
                                            <td><span class="fw-bold"><?php echo date('d M Y, h:i A', strtotime($b['booking_date'])); ?></span></td>
                                            <td>
                                                <?php 
                                                $b_badge = 'bg-secondary';
                                                if ($b['status'] === 'Pending') $b_badge = 'bg-warning text-dark';
                                                elseif ($b['status'] === 'Confirmed') $b_badge = 'bg-primary';
                                                elseif ($b['status'] === 'Completed') $b_badge = 'bg-success';
                                                elseif ($b['status'] === 'Cancelled') $b_badge = 'bg-danger';
                                                ?>
                                                <span class="badge <?php echo $b_badge; ?>"><?php echo esc($b['status']); ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fa fa-wrench text-muted fa-3x mb-2"></i>
                                <p class="text-muted">No garage service bookings scheduled.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Load fitment cascading dropdown javascript -->
<script src="assets/js/fitment.js"></script>

<script>
$(document).ready(function() {
    // Activate vehicle profile via AJAX
    $('.btn-activate-vehicle').on('click', function() {
        var id = $(this).data('id');
        var col = $(this).closest('.vehicle-card-col');

        $.ajax({
            url: 'api/garage.php',
            type: 'GET',
            data: { action: 'set_active', vehicle_id: id },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.message);
                }
            }
        });
    });

    // Delete vehicle profile via AJAX
    $('.btn-delete-vehicle').on('click', function() {
        if (!confirm('Are you sure you want to remove this vehicle from your garage?')) return;
        
        var id = $(this).data('id');
        var col = $(this).closest('.vehicle-card-col');

        $.ajax({
            url: 'api/garage.php',
            type: 'GET',
            data: { action: 'delete', vehicle_id: id },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    col.fadeOut(300, function() {
                        location.reload();
                    });
                } else {
                    alert(response.message);
                }
            }
        });
    });

    // Add Vehicle Form AJAX submission
    $('#addVehicleForm').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var feedback = $('#addVehicleFeedback');
        var vId = $('#selectedVehicleId').val();
        var label = $('#vehicleLabelInput').val();

        $.ajax({
            url: 'api/garage.php',
            type: 'POST',
            data: {
                action: 'add',
                vehicle_id: vId,
                label: label
            },
            dataType: 'json',
            success: function(response) {
                feedback.removeClass('alert-danger alert-success').hide();
                if (response.success) {
                    feedback.addClass('alert alert-success').text('Vehicle added successfully!').show();
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    feedback.addClass('alert alert-danger').text(response.message).show();
                }
            },
            error: function() {
                feedback.addClass('alert alert-danger').text('Connection error saving vehicle.').show();
            }
        });
    });
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
