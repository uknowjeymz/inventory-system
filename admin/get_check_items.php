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
    // Get all pending items in this group
    $query = "SELECT ri.id, ri.quantity, ri.description,
              c.id as consumable_id, c.item_name, c.unit, c.quantity as available_stock
              FROM request_items ri
              JOIN consumables c ON ri.consumable_id = c.id
              WHERE ri.group_id = ? AND ri.status = 'Pending'
              ORDER BY ri.id";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$group_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'items' => $items
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>