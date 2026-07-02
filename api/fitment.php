<?php
/**
 * Namma AutoParts - AJAX Fitment Finder API
 * Returns cascading data for Make -> Model -> Year -> Engine
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    switch ($action) {
        case 'makes':
            $stmt = $pdo->query("SELECT DISTINCT make FROM vehicles_master ORDER BY make ASC");
            $makes = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode($makes);
            break;

        case 'models':
            $make = isset($_GET['make']) ? trim($_GET['make']) : '';
            if (empty($make)) {
                echo json_encode([]);
                exit;
            }
            $stmt = $pdo->prepare("SELECT DISTINCT model FROM vehicles_master WHERE make = ? ORDER BY model ASC");
            $stmt->execute([$make]);
            $models = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode($models);
            break;

        case 'years':
            $make = isset($_GET['make']) ? trim($_GET['make']) : '';
            $model = isset($_GET['model']) ? trim($_GET['model']) : '';
            if (empty($make) || empty($model)) {
                echo json_encode([]);
                exit;
            }
            
            // Get range
            $stmt = $pdo->prepare("SELECT MIN(year_from) as min_year, MAX(year_to) as max_year FROM vehicles_master WHERE make = ? AND model = ?");
            $stmt->execute([$make, $model]);
            $range = $stmt->fetch();
            
            $years = [];
            if ($range && $range['min_year'] !== null) {
                for ($y = $range['max_year']; $y >= $range['min_year']; $y--) {
                    $years[] = $y;
                }
            }
            echo json_encode($years);
            break;

        case 'engines':
            $make = isset($_GET['make']) ? trim($_GET['make']) : '';
            $model = isset($_GET['model']) ? trim($_GET['model']) : '';
            $year = isset($_GET['year']) ? intval($_GET['year']) : 0;
            
            if (empty($make) || empty($model) || $year == 0) {
                echo json_encode([]);
                exit;
            }
            
            // Find vehicles where the year falls within the model range
            $stmt = $pdo->prepare("SELECT id, engine_type, fuel_type 
                                   FROM vehicles_master 
                                   WHERE make = ? AND model = ? AND ? BETWEEN year_from AND year_to 
                                   ORDER BY engine_type ASC");
            $stmt->execute([$make, $model, $year]);
            $engines = $stmt->fetchAll();
            echo json_encode($engines);
            break;

        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
