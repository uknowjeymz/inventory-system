<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

$remarks = [];

// Get remarks from all equipment tables
$tables = [
    'computer_inventory',
    'kitchen_equipment',
    'office_equipment',
    'lab_equipment',
    'general_equipment'
];

foreach ($tables as $table) {
    try {
        // Check if table exists
        $check_table = $db->query("SHOW TABLES LIKE '{$table}'");
        if ($check_table->rowCount() == 0) {
            continue; // Skip if table doesn't exist
        }
        
        // Check if remarks column exists
        $check_column = $db->query("SHOW COLUMNS FROM {$table} LIKE 'remarks'");
        if ($check_column->rowCount() == 0) {
            continue; // Skip if remarks column doesn't exist
        }
        
        // Get distinct remarks - exclude common placeholder values
        $query = "SELECT DISTINCT remarks FROM {$table} 
                  WHERE remarks IS NOT NULL 
                  AND remarks != '' 
                  AND remarks != 'None' 
                  AND remarks != 'N/A' 
                  AND remarks != 'Unassigned' 
                  AND remarks != 'None Assigned'
                  AND remarks NOT LIKE '%null%'
                  AND LENGTH(TRIM(remarks)) > 3
                  ORDER BY remarks";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $remark = trim($row['remarks']);
            // Additional filtering
            if (!empty($remark) && 
                $remark !== 'None' && 
                $remark !== 'N/A' && 
                $remark !== 'Unassigned' && 
                $remark !== 'None Assigned' &&
                !in_array($remark, $remarks)) {
                $remarks[] = $remark;
            }
        }
    } catch (Exception $e) {
        // Log error for debugging
        error_log("Error fetching remarks from {$table}: " . $e->getMessage());
        continue;
    }
}

// Remove duplicates (just in case)
$remarks = array_unique($remarks);

// Sort alphabetically
sort($remarks);

// Re-index array
$remarks = array_values($remarks);

header('Content-Type: application/json');
echo json_encode($remarks);
?>