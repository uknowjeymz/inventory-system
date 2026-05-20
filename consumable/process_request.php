<?php
session_start();
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Include database connection
require_once '../consumable/config/database.php';
$database = new Database();
$db = $database->getConnection();

// Include notification helper
require_once 'send_notification.php';

// Include critical stock checker
require_once '../admin/check_critical_stock.php';

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit();
}

$user_id = $_SESSION['user_id'];
$requested_by = $input['requested_by'] ?? '';
$department = $input['department'] ?? '';
$campus = $input['campus'] ?? '';
$overall_purpose = $input['overall_purpose'] ?? '';
$items = $input['items'] ?? [];
$approved_by = $input['approved_by'] ?? 'REYNALDO H. CARANDANG JR.';
$supply_officer = $input['supply_officer'] ?? 'MARVIN Z. GERVACIO';

// Validate data
if (empty($items)) {
    echo json_encode(['success' => false, 'message' => 'No items to request']);
    exit();
}

// Validate that each item has a purpose
foreach ($items as $item) {
    if (empty($item['purpose'])) {
        echo json_encode(['success' => false, 'message' => 'Each item must have a purpose']);
        exit();
    }
}

// Start transaction
$db->beginTransaction();

try {
    // Generate unique group code
    $group_code = 'REQ-' . date('Ymd') . '-' . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
    
    // Insert into request_groups
    $group_query = "INSERT INTO request_groups (group_code, employee, campus, office, request_date, requested_by, approved_by, supply_officer, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())";
    $group_stmt = $db->prepare($group_query);
    $group_stmt->execute([
        $group_code,
        $requested_by,
        $campus,
        $department,
        date('Y-m-d'),
        $requested_by,
        $approved_by,
        $supply_officer
    ]);
    
    $group_id = $db->lastInsertId();

    // Get the actual group code from the database (to be safe)
    $code_check = $db->prepare("SELECT group_code FROM request_groups WHERE id = ?");
    $code_check->execute([$group_id]);
    $actual_group_code = $code_check->fetchColumn();
    
    // Insert each item into request_items
    $item_query = "INSERT INTO request_items (group_id, consumable_id, quantity, description, status) 
                   VALUES (?, ?, ?, ?, 'Pending')";
    $item_stmt = $db->prepare($item_query);
    
    foreach ($items as $item) {
        // Check if enough stock is available
        $stock_check = $db->prepare("SELECT quantity FROM consumables WHERE id = ?");
        $stock_check->execute([$item['id']]);
        $available = $stock_check->fetch(PDO::FETCH_ASSOC);
        
        if (!$available || $available['quantity'] < $item['quantity']) {
            throw new Exception("Insufficient stock for one of the items");
        }
        
        // Combine item purpose with overall purpose if overall purpose exists
        $description = $item['purpose'];
        if (!empty($overall_purpose)) {
            $description .= " - " . $overall_purpose;
        }
        
        // Insert request item
        $item_stmt->execute([
            $group_id,
            $item['id'],
            $item['quantity'],
            $description
        ]);
    }
    
    $db->commit();
    
    // ============ SEND NOTIFICATIONS ============

    // 1. REMOVED: User notification - now shown as toast on my_requests.php
    
    // 2. Notify ALL admins about the new request
    $item_count = count($items);
    $item_names = [];
    foreach ($items as $item) {
        // Get item names for better notification
        $name_query = "SELECT item_name FROM consumables WHERE id = ?";
        $name_stmt = $db->prepare($name_query);
        $name_stmt->execute([$item['id']]);
        $item_data = $name_stmt->fetch(PDO::FETCH_ASSOC);
        if ($item_data) {
            $item_names[] = $item_data['item_name'];
        }
    }

    $item_list = implode(', ', array_slice($item_names, 0, 3));
    if (count($item_names) > 3) {
        $item_list .= ' and ' . (count($item_names) - 3) . ' more';
    }

    // Enhanced admin message with campus information
    $campus_text = !empty($campus) ? " from {$campus}" : "";
    $admin_message = "{$requested_by}{$campus_text} ({$department}) requested {$item_count} item(s): {$item_list}. Check it on Consumable Management.";
    $admin_link = '../admin/consumables.php?highlight=' . $group_code . '#request-history';

    // Notify all admins using the helper function
    notifyAdmins(
        $db,
        'new_request',
        'New Request Received',
        $admin_message,
        $admin_link,
        $group_id,
        'request_group'
    );

    // ============ END NOTIFICATIONS ============
    
    echo json_encode([
        'success' => true,
        'message' => 'Request submitted successfully! Reference Code: ' . $group_code,
        'group_code' => $group_code
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>