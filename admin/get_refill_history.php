<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

$consumable_id = isset($_GET['consumable_id']) ? intval($_GET['consumable_id']) : 0;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;

if ($consumable_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit();
}

try {
    // Check if consumables_history table exists
    $check_table = $db->query("SHOW TABLES LIKE 'consumables_history'")->fetch();
    
    if ($check_table) {
        // Use new consumables_history table
        $query = "SELECT ch.*, u.full_name as performed_by_name 
                  FROM consumables_history ch
                  LEFT JOIN users u ON ch.performed_by = u.id
                  WHERE ch.consumable_id = ?
                  ORDER BY ch.action_date DESC
                  LIMIT " . intval($limit);
        
        $stmt = $db->prepare($query);
        $stmt->execute([$consumable_id]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format the data for display
        $formatted_history = array_map(function($record) {
            $action_labels = [
                'initial' => 'Initial Stock',
                'refill' => 'Refilled',
                'deduction' => 'Released',
                'adjustment' => 'Adjusted',
                'edit' => 'Edited'
            ];
            
            $action_icons = [
                'initial' => 'fa-star',
                'refill' => 'fa-plus-circle',
                'deduction' => 'fa-minus-circle',
                'adjustment' => 'fa-edit',
                'edit' => 'fa-pen'
            ];
            
            $action_colors = [
                'initial' => 'info',
                'refill' => 'success',
                'deduction' => 'danger',
                'adjustment' => 'warning',
                'edit' => 'secondary'
            ];
            
            return [
                'id' => $record['id'],
                'date' => date('M d, Y h:i A', strtotime($record['action_date'])),
                'action_type' => $record['action_type'],
                'action_label' => $action_labels[$record['action_type']] ?? ucfirst($record['action_type']),
                'action_icon' => $action_icons[$record['action_type']] ?? 'fa-circle',
                'action_color' => $action_colors[$record['action_type']] ?? 'secondary',
                'previous_quantity' => $record['previous_quantity'],
                'quantity_change' => $record['quantity_change'],
                'new_quantity' => $record['new_quantity'],
                'performed_by' => $record['performed_by_name'] ?? 'System',
                'reference_type' => $record['reference_type'] ?? '',
                'reference_id' => $record['reference_id'] ?? '',
                'remarks' => $record['remarks'] ?? ''
            ];
        }, $history);
        
    } else {
        // Fallback to old consumable_refills table
        $query = "SELECT cr.*, u.full_name as refilled_by_name 
                  FROM consumable_refills cr
                  LEFT JOIN users u ON cr.refilled_by = u.id
                  WHERE cr.consumable_id = ?
                  ORDER BY cr.refill_date DESC
                  LIMIT " . intval($limit);
        
        $stmt = $db->prepare($query);
        $stmt->execute([$consumable_id]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format old data to match new format
        $formatted_history = array_map(function($record) {
            return [
                'id' => $record['id'],
                'date' => date('M d, Y h:i A', strtotime($record['refill_date'])),
                'action_type' => 'refill',
                'action_label' => 'Refilled',
                'action_icon' => 'fa-plus-circle',
                'action_color' => 'success',
                'previous_quantity' => $record['previous_quantity'],
                'quantity_change' => $record['refill_quantity'],
                'new_quantity' => $record['new_quantity'],
                'performed_by' => $record['refilled_by_name'] ?? 'System',
                'reference_type' => 'manual_refill',
                'reference_id' => '',
                'remarks' => $record['remarks'] ?? ''
            ];
        }, $history);
    }
    
    echo json_encode([
        'success' => true, 
        'history' => $formatted_history,
        'table_used' => $check_table ? 'consumables_history' : 'consumable_refills'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage(),
        'error_details' => $e->getTraceAsString()
    ]);
}
?>
