<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$id = $_GET['id'] ?? 0;

if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid ID']);
    exit();
}

try {
    $query = "SELECT 
                ai.*,
                u_condemned.full_name as condemned_by_name,
                u_archived.full_name as archived_by_name
              FROM archive_items ai
              LEFT JOIN users u_condemned ON ai.condemned_by = u_condemned.id
              LEFT JOIN users u_archived ON ai.archived_by = u_archived.id
              WHERE ai.id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        http_response_code(404);
        echo json_encode(['error' => 'Archived item not found']);
        exit();
    }
    
    // Format dates
    $response = [
        'id' => $item['id'],
        'original_id' => $item['original_id'],
        'model' => $item['model'] ?? 'N/A',
        'category' => $item['category'] ?? 'N/A',
        'serial_number' => $item['serial_number'] ?? 'N/A',
        'equipment_type' => $item['equipment_type'] ?? 'N/A',
        'reason_condemned' => $item['reason_condemned'] ?? 'No reason provided',
        'condemned_date' => !empty($item['condemned_date']) ? date('M d, Y', strtotime($item['condemned_date'])) : 'N/A',
        'condemned_by_name' => $item['condemned_by_name'] ?? 'Unknown',
        'archived_date' => !empty($item['archived_date']) ? date('M d, Y h:i A', strtotime($item['archived_date'])) : 'N/A',
        'archived_by_name' => $item['archived_by_name'] ?? 'Unknown',
        'archive_reason' => $item['archive_reason'] ?? 'No reason provided',
        'disposal_status' => $item['disposal_status'] ?? 'archived',
        'estimated_value' => number_format($item['estimated_value'] ?? 0, 2),
        'remarks' => $item['remarks'] ?? 'N/A',
        'created_at' => !empty($item['created_at']) ? date('M d, Y', strtotime($item['created_at'])) : 'N/A',
        'updated_at' => !empty($item['updated_at']) ? date('M d, Y', strtotime($item['updated_at'])) : 'N/A'
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>