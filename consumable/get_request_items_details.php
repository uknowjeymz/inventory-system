<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Include database connection
require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get group_id from request
$group_id = $_GET['group_id'] ?? 0;

if (!$group_id) {
    echo json_encode(['success' => false, 'message' => 'Group ID required']);
    exit();
}

// Get user info for authorization check
$user_id = $_SESSION['user_id'];
$user_query = $db->prepare("SELECT full_name, role FROM users WHERE id = ?");
$user_query->execute([$user_id]);
$user = $user_query->fetch(PDO::FETCH_ASSOC);

// Get group details and verify ownership
$group_query = $db->prepare("
    SELECT rg.* 
    FROM request_groups rg
    WHERE rg.id = ?
");
$group_query->execute([$group_id]);
$group = $group_query->fetch(PDO::FETCH_ASSOC);

if (!$group) {
    echo json_encode(['success' => false, 'message' => 'Request not found']);
    exit();
}

// Check if user has access (admin or the requester)
if ($user['role'] !== 'admin' && $group['employee'] !== $user['full_name']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get items for this group
$items_query = $db->prepare("
    SELECT ri.*, c.item_name, c.unit, c.brand, c.category, c.quantity as available_stock
    FROM request_items ri
    JOIN consumables c ON ri.consumable_id = c.id
    WHERE ri.group_id = ?
    ORDER BY ri.id ASC
");
$items_query->execute([$group_id]);
$items = $items_query->fetchAll(PDO::FETCH_ASSOC);

// Format the response
$response = [
    'success' => true,
    'employee' => $group['employee'],
    'office' => $group['office'],
    'request_date' => date('M d, Y', strtotime($group['request_date'])),
    'items' => $items
];

echo json_encode($response);
?>