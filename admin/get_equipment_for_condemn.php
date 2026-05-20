<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$id = $_GET['id'] ?? 0;
$type = $_GET['type'] ?? '';

if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid ID']);
    exit();
}

try {
    // Check if this is from condemned_equipment table or from inventory tables
    if ($type === 'condemned' || $type === 'monitor_system' || $type === 'keyboard') {
        // Query from condemned_equipment table
        $query = "SELECT ce.*, u.full_name as condemned_by_name 
                  FROM condemned_equipment ce
                  LEFT JOIN users u ON ce.condemned_by = u.id
                  WHERE ce.id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($item) {
            $response = [
                'model' => $item['model'] ?? 'N/A',
                'category' => $item['category'] ?? 'N/A',
                'serial_number' => $item['serial_number'] ?? 'N/A',
                'reason_condemned' => $item['reason_condemned'] ?? 'No reason provided',
                'condemned_date' => $item['condemned_date'] ? date('M d, Y', strtotime($item['condemned_date'])) : 'N/A',
                'condemned_by_name' => $item['condemned_by_name'] ?? 'Unknown',
                'disposal_status' => $item['disposal_status'] ?? 'pending',
                'disposal_date' => $item['disposal_date'] ? date('M d, Y', strtotime($item['disposal_date'])) : null,
                'disposal_notes' => $item['disposal_notes'] ?? null,
                'estimated_value' => $item['estimated_value'] ?? 0,
                'remarks' => $item['remarks'] ?? 'N/A',
                'purchase_date' => null, // Not available in condemned_equipment table
                'condition_status' => 'N/A' // Not available in condemned_equipment table
            ];
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Item not found']);
            exit();
        }
    } else {
        // Map equipment type to table
        $table_map = [
            'computer' => 'computer_inventory',
            'computer_lab' => 'computer_inventory',
            'kitchen' => 'kitchen_equipment',
            'office' => 'office_equipment',
            'lab' => 'lab_equipment',
            'regular_lab' => 'lab_equipment',
            'general' => 'general_equipment'
        ];
        
        $table_name = $table_map[$type] ?? '';
        
        if (!$table_name) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid equipment type']);
            exit();
        }
        
        // Query from inventory table where is_condemned = TRUE
        $query = "SELECT * FROM {$table_name} WHERE id = ? AND is_condemned = TRUE";
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($item) {
            // Get condemned by name
            $condemned_by_name = 'Unknown';
            if (!empty($item['condemned_by'])) {
                $user_query = "SELECT full_name FROM users WHERE id = ?";
                $user_stmt = $db->prepare($user_query);
                $user_stmt->execute([$item['condemned_by']]);
                $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                $condemned_by_name = $user['full_name'] ?? 'Unknown';
            }
            
            // Get model name
            $model = '';
            if (!empty($item['computer_set_description'])) {
                $model = $item['computer_set_description'];
            } elseif (!empty($item['equipment_name'])) {
                $model = $item['equipment_name'];
            } elseif (!empty($item['article'])) {
                $model = $item['article'];
            } else {
                $model = 'Unknown Model';
            }
            
            // Get serial number
            $serial_number = 'N/A';
            if (!empty($item['serial_number'])) {
                $serial_number = $item['serial_number'];
            } elseif (!empty($item['property_no'])) {
                $serial_number = $item['property_no'];
            } elseif (!empty($item['serial_number_system'])) {
                $serial_number = $item['serial_number_system'];
            }
            
            // Determine category
            $category = ucfirst($type);
            if ($table_name == 'computer_inventory') {
                $device_type = $item['device_type'] ?? '';
                if (strpos($device_type, 'Desktop') !== false) {
                    $category = 'System Unit';
                } elseif (strpos($device_type, 'All-in-One') !== false) {
                    $category = 'All in one';
                } elseif (strpos($device_type, 'Laptop') !== false) {
                    $category = 'Laptop';
                }
            } elseif ($table_name == 'general_equipment') {
                $article = $item['article'] ?? '';
                if (strpos($article, 'Keyboard') !== false) {
                    $category = 'Keyboard';
                } elseif (strpos($article, 'AVR') !== false) {
                    $category = 'AVR';
                } elseif (strpos($article, 'Monitor') !== false) {
                    $category = 'Monitor';
                }
            }
            
            $response = [
                'model' => $model,
                'category' => $category,
                'serial_number' => $serial_number,
                'reason_condemned' => $item['condemned_reason'] ?? 'No reason provided',
                'condemned_date' => $item['condemned_date'] ? date('M d, Y', strtotime($item['condemned_date'])) : 'N/A',
                'condemned_by_name' => $condemned_by_name,
                'disposal_status' => 'pending', // Inventory items are always pending when condemned
                'disposal_date' => null,
                'disposal_notes' => null,
                'estimated_value' => $item['cost'] ?? 0,
                'remarks' => $item['remarks'] ?? 'N/A',
                'purchase_date' => $item['purchase_date'] ? date('M d, Y', strtotime($item['purchase_date'])) : null,
                'condition_status' => $item['condition_status'] ?? 'N/A'
            ];
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Item not found']);
            exit();
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}