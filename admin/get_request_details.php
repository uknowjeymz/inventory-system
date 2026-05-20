<?php
session_start();
require_once '../config/database.php';
header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

$group_id = $_GET['group_id'] ?? 0;

if (!$group_id) {
    echo json_encode(['success' => false, 'message' => 'Group ID required']);
    exit();
}

try {
    // Get request group details - include campus field
    $group_query = "SELECT rg.*, 
                    u1.full_name as requested_by_name,
                    u2.full_name as approved_by_name,
                    u3.full_name as supply_officer_name
                    FROM request_groups rg
                    LEFT JOIN users u1 ON rg.requested_by = u1.full_name
                    LEFT JOIN users u2 ON rg.approved_by = u2.full_name
                    LEFT JOIN users u3 ON rg.supply_officer = u3.full_name
                    WHERE rg.id = ?";
    
    $group_stmt = $db->prepare($group_query);
    $group_stmt->execute([$group_id]);
    $group = $group_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit();
    }
    
    // Get all items in this group
    $items_query = "SELECT ri.*, c.item_name, c.unit, c.identification
                    FROM request_items ri
                    JOIN consumables c ON ri.consumable_id = c.id
                    WHERE ri.group_id = ?
                    ORDER BY ri.id";

    $items_stmt = $db->prepare($items_query);
    $items_stmt->execute([$group_id]);
    $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate total quantity
    $total_quantity = 0;
    foreach ($items as $item) {
        $total_quantity += $item['quantity'];
    }

    // Format response - include campus and total quantity
    $response = [
        'success' => true,
        'request' => [
            'id' => $group['id'],
            'group_code' => $group['group_code'],
            'employee' => $group['employee'],
            'campus' => $group['campus'] ?? 'N/A',
            'office' => $group['office'],
            'request_date' => date('M d, Y', strtotime($group['request_date'])),
            'requested_by' => $group['requested_by'] ?? 'N/A',
            'approved_by' => $group['approved_by'] ?? 'N/A',
            'supply_officer' => $group['supply_officer'] ?? 'N/A',
            'status' => $group['status'],
            'total_quantity' => $total_quantity // Add total quantity
        ],
        'items' => []
    ];

    foreach ($items as $item) {
        $response['items'][] = [
            'id' => $item['id'],
            'item_name' => $item['item_name'],
            'quantity' => $item['quantity'],
            'unit' => $item['unit'],
            'description' => $item['description'],
            'status' => $item['status'],
            'identification' => $item['identification']
        ];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>