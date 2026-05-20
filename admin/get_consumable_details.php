<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit();
}

try {
    $query = "SELECT * FROM consumables WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($item) {
        // Add threshold information
        if (!empty($item['max_stock']) && $item['max_stock'] > 0) {
            $item['critical_threshold'] = round($item['max_stock'] * 0.2);
            $item['low_threshold'] = round($item['max_stock'] * 0.4);
            $item['threshold_type'] = 'percentage';
        } else {
            $item['threshold_type'] = 'default';
            
            // Calculate default thresholds based on unit
            $unit = strtolower(trim($item['unit'] ?? ''));
            switch ($unit) {
                case 'pcs':
                    $item['critical_threshold'] = 30;
                    $item['low_threshold'] = 50;
                    break;
                case 'unit':
                    $item['critical_threshold'] = 10;
                    $item['low_threshold'] = 20;
                    break;
                case 'box':
                case 'ream':
                    $item['critical_threshold'] = 10;
                    $item['low_threshold'] = 20;
                    break;
                default:
                    $item['critical_threshold'] = 10;
                    $item['low_threshold'] = 20;
            }
        }
        
        // Ensure all fields are present
        $item['item_name'] = $item['item_name'] ?? '';
        $item['category'] = $item['category'] ?? '';
        $item['quantity'] = $item['quantity'] ?? 0;
        $item['max_stock'] = $item['max_stock'] ?? null;
        $item['unit'] = $item['unit'] ?? '';
        $item['brand'] = $item['brand'] ?? '';
        $item['identification'] = $item['identification'] ?? '';
        $item['status'] = $item['status'] ?? 'Available';
        
        echo json_encode(['success' => true, 'item' => $item]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Item not found']);
    }
} catch (Exception $e) {
    error_log("Error in get_consumable_details.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>