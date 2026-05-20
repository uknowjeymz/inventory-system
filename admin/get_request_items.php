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
    // Get request group details
    $group_query = "SELECT rg.* 
                    FROM request_groups rg
                    WHERE rg.id = ?";
    
    $group_stmt = $db->prepare($group_query);
    $group_stmt->execute([$group_id]);
    $group = $group_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit();
    }
    
    // Get all items in this group
    $items_query = "SELECT ri.*, c.item_name, c.unit, c.identification, c.quantity as current_stock
                    FROM request_items ri
                    JOIN consumables c ON ri.consumable_id = c.id
                    WHERE ri.group_id = ?
                    ORDER BY ri.id";
    
    $items_stmt = $db->prepare($items_query);
    $items_stmt->execute([$group_id]);
    $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all consumables for dropdown options
    $consumables_query = "SELECT id, item_name, quantity, unit FROM consumables ORDER BY item_name ASC";
    $cons_stmt = $db->prepare($consumables_query);
    $cons_stmt->execute();
    $all_consumables = $cons_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build response items with options and max_stock
    $response_items = [];
    foreach ($items as $item) {
        $current_id = $item['consumable_id'];
        // Generate options for this row, with current selected
        $options_html = '';
        foreach ($all_consumables as $c) {
            $selected = ($c['id'] == $current_id) ? ' selected' : '';
            $options_html .= '<option value="' . $c['id'] . '"' . $selected . ' data-max="' . $c['quantity'] . '" data-unit="' . htmlspecialchars($c['unit']) . '">' 
                . htmlspecialchars($c['item_name']) . ' (Available: ' . $c['quantity'] . ' ' . htmlspecialchars($c['unit']) . ')</option>';
        }
        
        // Current stock for this item (for max hint)
        $current_stock = 0;
        foreach ($all_consumables as $c) {
            if ($c['id'] == $current_id) {
                $current_stock = $c['quantity'];
                break;
            }
        }
        
        $response_items[] = [
            'id' => $item['id'],
            'consumable_id' => $current_id,
            'item_name' => $item['item_name'],
            'quantity' => $item['quantity'],
            'unit' => $item['unit'],
            'description' => $item['description'],
            'status' => $item['status'],
            'rejection_reason' => $item['rejection_reason'] ?? null,
            'identification' => $item['identification'],
            'options' => $options_html,
            'max_stock' => $current_stock
        ];
    }
    
    // Format response
    $response = [
        'success' => true,
        'request' => [
            'id' => $group['id'],
            'group_code' => $group['group_code'],
            'employee' => $group['employee'],
            'office' => $group['office'],
            'request_date' => date('M d, Y', strtotime($group['request_date'])),
            'requested_by' => $group['requested_by'] ?? 'N/A',
            'approved_by' => $group['approved_by'] ?? 'N/A',
            'supply_officer' => $group['supply_officer'] ?? 'N/A',
            'status' => $group['status']
        ],
        'items' => $response_items
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>