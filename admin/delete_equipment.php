<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if POST request with required data
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$id = $_POST['id'] ?? 0;
$type = $_POST['type'] ?? '';
$table = $_POST['table'] ?? '';

if (!$id || !$type || !$table) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

// Validate table name to prevent SQL injection (whitelist allowed tables)
$allowed_tables = ['computer_inventory', 'kitchen_equipment', 'office_equipment', 'lab_equipment', 'general_equipment'];
if (!in_array($table, $allowed_tables)) {
    echo json_encode(['success' => false, 'message' => 'Invalid table name']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Start transaction
    $db->beginTransaction();
    
    // First, delete any assignment history records related to this equipment
    $delete_history = $db->prepare("DELETE FROM assignment_history WHERE computer_id = ? AND equipment_table = ?");
    $delete_history->execute([$id, $table]);
    
    // Then delete the equipment record
    $delete_stmt = $db->prepare("DELETE FROM {$table} WHERE id = ?");
    $delete_stmt->execute([$id]);
    
    // Check if record was actually deleted
    if ($delete_stmt->rowCount() == 0) {
        throw new Exception("Equipment record not found");
    }
    
    $db->commit();
    
    echo json_encode(['success' => true, 'message' => 'Equipment deleted successfully!']);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error deleting equipment: ' . $e->getMessage()]);
}
?>