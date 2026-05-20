<?php
session_start();
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get selected items from POST
$selected_items = isset($_POST['selected_items']) ? $_POST['selected_items'] : '';

if (empty($selected_items)) {
    echo json_encode(['success' => false, 'message' => 'No items selected']);
    exit();
}

// Parse the selected items (format: "type:id,type:id,...")
$items_array = explode(',', $selected_items);
$preview_data = [];

// Table mapping
$table_map = [
    'computer' => 'computer_inventory',
    'computer_lab' => 'computer_inventory',
    'kitchen' => 'kitchen_equipment',
    'office' => 'office_equipment',
    'lab' => 'lab_equipment',
    'regular_lab' => 'lab_equipment',
    'general' => 'general_equipment'
];

foreach ($items_array as $item) {
    if (empty($item) || strpos($item, ':') === false) {
        continue;
    }
    
    list($type, $id) = explode(':', $item, 2);
    $table_name = $table_map[$type] ?? '';
    
    if (empty($table_name)) {
        continue;
    }
    
    try {
        // Determine the name column based on table
        $name_column = 'equipment_name';
        if ($table_name === 'computer_inventory') {
            $name_column = 'computer_set_description';
        } elseif ($table_name === 'general_equipment') {
            $name_column = 'article';
        }
        
        // Determine serial/property column
        $serial_column = 'serial_number';
        if ($table_name === 'general_equipment') {
            $serial_column = 'property_no';
        }
        
        // Build the query
        $query = "SELECT 
                    id,
                    {$name_column} as description,
                    {$serial_column} as serial_number,
                    remarks as end_user,
                    cost as unit_value,
                    purchase_date,
                    created_at
                  FROM {$table_name} 
                  WHERE id = ?";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
        $equipment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($equipment) {
            // FIXED: Extract year from purchase_date or created_at
            $year_acquired = 'N/A';
            
            // Check purchase_date
            if (!empty($equipment['purchase_date']) && $equipment['purchase_date'] !== '0000-00-00') {
                $timestamp = strtotime($equipment['purchase_date']);
                if ($timestamp !== false && $timestamp > 0) {
                    $year_acquired = date('Y', $timestamp);
                }
            } 
            // Check created_at if purchase_date not available
            elseif (!empty($equipment['created_at']) && $equipment['created_at'] !== '0000-00-00') {
                $timestamp = strtotime($equipment['created_at']);
                if ($timestamp !== false && $timestamp > 0) {
                    $year_acquired = date('Y', $timestamp);
                }
            }
            
            // Ensure we don't get weird old years
            if ($year_acquired !== 'N/A' && (int)$year_acquired < 1900) {
                $year_acquired = 'N/A';
            }
            
            $preview_data[] = [
                'description' => $equipment['description'] ?: 'N/A',
                'serial_number' => $equipment['serial_number'] ?: 'N/A',
                'end_user' => $equipment['end_user'] ?: 'Unassigned',
                'unit_value' => $equipment['unit_value'] ?: 0,
                'year_acquired' => $year_acquired
            ];
        }
    } catch (Exception $e) {
        // Skip items that cause errors
        continue;
    }
}

echo json_encode(['success' => true, 'data' => $preview_data]);
?>