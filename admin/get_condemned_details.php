<?php
require_once '../config/database.php';
header('Content-Type: application/json');

session_start();
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
    // If type is 'condemned', query the condemned_equipment table directly
    if ($type === 'condemned') {
        $query = "SELECT 
                    ce.id,
                    ce.model,
                    ce.category,
                    ce.serial_number,
                    ce.reason_condemned,
                    ce.condemned_date,
                    ce.disposal_status,
                    ce.disposal_date,
                    ce.disposal_notes,
                    ce.estimated_value,
                    ce.remarks,
                    u.full_name as condemned_by_name
                  FROM condemned_equipment ce 
                  LEFT JOIN users u ON ce.condemned_by = u.id 
                  WHERE ce.id = ?";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            // Format dates
            $response = [
                'model' => $data['model'] ?? 'N/A',
                'category' => $data['category'] ?? 'N/A',
                'serial_number' => $data['serial_number'] ?? 'N/A',
                'reason_condemned' => $data['reason_condemned'] ?? 'No reason provided',
                'condemned_date' => !empty($data['condemned_date']) ? date('M d, Y', strtotime($data['condemned_date'])) : 'N/A',
                'condemned_by_name' => $data['condemned_by_name'] ?? 'Unknown',
                'disposal_status' => $data['disposal_status'] ?? 'pending',
                'disposal_date' => !empty($data['disposal_date']) ? date('M d, Y', strtotime($data['disposal_date'])) : null,
                'disposal_notes' => $data['disposal_notes'] ?? null,
                'estimated_value' => $data['estimated_value'] ?? 0,
                'remarks' => $data['remarks'] ?? 'N/A',
                'purchase_date' => 'N/A', // condemned_equipment doesn't have purchase_date
                'condition_status' => 'N/A' // condemned_equipment doesn't have condition_status
            ];
            
            echo json_encode($response);
            exit();
        } else {
            echo json_encode(['error' => 'No record found in condemned_equipment table with ID: ' . $id]);
            exit();
        }
    }
    
    // If type is specific (not 'condemned'), check the appropriate equipment table
    $table_map = [
        'computer' => 'computer_inventory',
        'computer_lab' => 'computer_inventory',
        'kitchen' => 'kitchen_equipment',
        'office' => 'office_equipment',
        'lab' => 'lab_equipment',
        'regular_lab' => 'lab_equipment',
        'general' => 'general_equipment'
    ];
    
    if (isset($table_map[$type])) {
        $table = $table_map[$type];
        
        // Get the name column based on table
        $name_col = 'id'; // default
        $sn_col = 'id'; // default
        $remarks_col = 'remarks';
        
        if ($table == 'computer_inventory') {
            $name_col = 'computer_set_description';
            $sn_col = 'serial_number';
        } elseif (in_array($table, ['kitchen_equipment', 'office_equipment', 'lab_equipment'])) {
            $name_col = 'equipment_name';
            $sn_col = 'serial_number';
        } elseif ($table == 'general_equipment') {
            $name_col = 'article';
            $sn_col = 'property_no';
        }
        
        // Check if the table has is_condemned column
        $check_query = "SHOW COLUMNS FROM `{$table}` LIKE 'is_condemned'";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            // Table has condemnation support
            $query = "SELECT 
                        id,
                        {$name_col} as model,
                        {$sn_col} as serial_number,
                        condemned_reason as reason_condemned,
                        condemned_date,
                        condemned_by,
                        {$remarks_col} as remarks,
                        status,
                        condition_status,
                        purchase_date,
                        cost
                      FROM {$table} 
                      WHERE id = ? AND is_condemned = 1";
            
            $stmt = $db->prepare($query);
            $stmt->execute([$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($data) {
                // Get condemner's name
                $condemner_name = 'Unknown';
                if (!empty($data['condemned_by'])) {
                    $user_query = "SELECT full_name FROM users WHERE id = ?";
                    $user_stmt = $db->prepare($user_query);
                    $user_stmt->execute([$data['condemned_by']]);
                    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                    $condemner_name = $user['full_name'] ?? 'Unknown';
                }
                
                // Determine category
                $category = ucfirst($type);
                if ($table == 'computer_inventory') {
                    $category = 'System Unit';
                } elseif ($table == 'general_equipment') {
                    $category = 'General Equipment';
                } elseif ($table == 'kitchen_equipment') {
                    $category = 'Kitchen Equipment';
                } elseif ($table == 'office_equipment') {
                    $category = 'Office Equipment';
                } elseif ($table == 'lab_equipment') {
                    $category = 'Lab Equipment';
                }
                
                $response = [
                    'model' => $data['model'] ?? 'Unknown',
                    'category' => $category,
                    'serial_number' => $data['serial_number'] ?? 'N/A',
                    'reason_condemned' => $data['reason_condemned'] ?? 'No reason provided',
                    'condemned_date' => !empty($data['condemned_date']) ? date('M d, Y', strtotime($data['condemned_date'])) : 'N/A',
                    'condemned_by_name' => $condemner_name,
                    'disposal_status' => 'pending',
                    'disposal_date' => null,
                    'disposal_notes' => null,
                    'estimated_value' => $data['cost'] ?? 0,
                    'remarks' => $data['remarks'] ?? 'N/A',
                    'purchase_date' => !empty($data['purchase_date']) ? date('M d, Y', strtotime($data['purchase_date'])) : 'N/A',
                    'condition_status' => $data['condition_status'] ?? 'N/A'
                ];
                
                echo json_encode($response);
                exit();
            }
        }
    }
    
    // If we get here, no record was found
    echo json_encode(['error' => 'No record found for ID: ' . $id . ' with type: ' . $type]);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
}
?>