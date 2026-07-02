<?php
/**
 * Namma AutoParts - AJAX & Direct Garage Profile Manager
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// Function to set session active vehicle
function set_session_active_vehicle($pdo, $vehicle_id) {
    $stmt = $pdo->prepare("SELECT * FROM vehicles_master WHERE id = ?");
    $stmt->execute([$vehicle_id]);
    $vehicle = $stmt->fetch();
    if ($vehicle) {
        $_SESSION['active_vehicle'] = $vehicle;
        return true;
    }
    return false;
}

try {
    switch ($action) {
        case 'set_active_session':
            // Triggered from fitment finder form submit
            $vehicle_id = isset($_POST['vehicle_id']) ? intval($_POST['vehicle_id']) : 0;
            $year = isset($_POST['year']) ? intval($_POST['year']) : 0;
            
            if ($vehicle_id > 0) {
                if (set_session_active_vehicle($pdo, $vehicle_id)) {
                    // Update year selected to the specific year for display context
                    $_SESSION['active_vehicle']['selected_year'] = $year;

                    // If logged in, save to database
                    if (is_logged_in()) {
                        $user_id = $_SESSION['user_id'];
                        // Set all other vehicles to inactive
                        $stmt = $pdo->prepare("UPDATE user_saved_vehicles SET is_active = 0 WHERE user_id = ?");
                        $stmt->execute([$user_id]);

                        // Check if already in garage
                        $check = $pdo->prepare("SELECT id FROM user_saved_vehicles WHERE user_id = ? AND vehicle_id = ?");
                        $check->execute([$user_id, $vehicle_id]);
                        $exists = $check->fetch();

                        if ($exists) {
                            $stmt = $pdo->prepare("UPDATE user_saved_vehicles SET is_active = 1 WHERE id = ?");
                            $stmt->execute([$exists['id']]);
                        } else {
                            $label = $_SESSION['active_vehicle']['make'] . ' ' . $_SESSION['active_vehicle']['model'];
                            $stmt = $pdo->prepare("INSERT INTO user_saved_vehicles (user_id, vehicle_id, label, is_active) VALUES (?, ?, ?, 1)");
                            $stmt->execute([$user_id, $vehicle_id, $label]);
                        }
                    }
                    
                    if (isset($_POST['redirect'])) {
                        redirect($_POST['redirect']);
                    } else {
                        redirect('shop.php');
                    }
                }
            }
            redirect('index.php?error=invalid_vehicle');
            break;

        case 'set_active':
            // AJAX toggle from user profile page
            header('Content-Type: application/json');
            $vehicle_id = isset($_GET['vehicle_id']) ? intval($_GET['vehicle_id']) : 0;
            
            if ($vehicle_id > 0 && is_logged_in()) {
                $user_id = $_SESSION['user_id'];
                
                // Deactivate all
                $pdo->prepare("UPDATE user_saved_vehicles SET is_active = 0 WHERE user_id = ?")->execute([$user_id]);
                
                // Activate selected
                $stmt = $pdo->prepare("UPDATE user_saved_vehicles SET is_active = 1 WHERE user_id = ? AND vehicle_id = ?");
                $stmt->execute([$user_id, $vehicle_id]);
                
                if ($stmt->rowCount() > 0) {
                    set_session_active_vehicle($pdo, $vehicle_id);
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Vehicle not found in your garage']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Unauthorized or invalid vehicle ID']);
            }
            break;

        case 'add':
            header('Content-Type: application/json');
            if (!is_logged_in()) {
                echo json_encode(['success' => false, 'message' => 'Log in to save vehicles.']);
                exit;
            }
            
            $vehicle_id = isset($_POST['vehicle_id']) ? intval($_POST['vehicle_id']) : 0;
            $label = isset($_POST['label']) ? trim($_POST['label']) : '';
            $user_id = $_SESSION['user_id'];

            if ($vehicle_id > 0) {
                try {
                    // Check duplicate
                    $dup = $pdo->prepare("SELECT id FROM user_saved_vehicles WHERE user_id = ? AND vehicle_id = ?");
                    $dup->execute([$user_id, $vehicle_id]);
                    if ($dup->fetch()) {
                        echo json_encode(['success' => false, 'message' => 'This vehicle is already in your garage.']);
                        exit;
                    }

                    // Set others inactive if active is requested
                    $pdo->prepare("UPDATE user_saved_vehicles SET is_active = 0 WHERE user_id = ?")->execute([$user_id]);

                    if (empty($label)) {
                        $stmt = $pdo->prepare("SELECT make, model FROM vehicles_master WHERE id = ?");
                        $stmt->execute([$vehicle_id]);
                        $v = $stmt->fetch();
                        $label = $v['make'] . ' ' . $v['model'];
                    }

                    $stmt = $pdo->prepare("INSERT INTO user_saved_vehicles (user_id, vehicle_id, label, is_active) VALUES (?, ?, ?, 1)");
                    $stmt->execute([$user_id, $vehicle_id, $label]);
                    
                    set_session_active_vehicle($pdo, $vehicle_id);
                    echo json_encode(['success' => true]);
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Select a valid vehicle']);
            }
            break;

        case 'delete':
            header('Content-Type: application/json');
            $vehicle_id = isset($_GET['vehicle_id']) ? intval($_GET['vehicle_id']) : 0;
            if ($vehicle_id > 0 && is_logged_in()) {
                $user_id = $_SESSION['user_id'];
                
                // Delete
                $stmt = $pdo->prepare("DELETE FROM user_saved_vehicles WHERE user_id = ? AND vehicle_id = ?");
                $stmt->execute([$user_id, $vehicle_id]);

                // If active in session was deleted, clear session
                if (isset($_SESSION['active_vehicle']) && $_SESSION['active_vehicle']['id'] == $vehicle_id) {
                    unset($_SESSION['active_vehicle']);
                }
                
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid request']);
            }
            break;

        case 'clear':
            // Clear current session vehicle fitment filter
            unset($_SESSION['active_vehicle']);
            if (is_logged_in()) {
                $pdo->prepare("UPDATE user_saved_vehicles SET is_active = 0 WHERE user_id = ?")->execute([$_SESSION['user_id']]);
            }
            
            // Redirect back
            $ref = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
            header("Location: " . $ref);
            exit;
            break;

        default:
            redirect('index.php');
            break;
    }
} catch (PDOException $e) {
    if (isset($_GET['action']) || isset($_POST['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        redirect('index.php?error=system');
    }
}
