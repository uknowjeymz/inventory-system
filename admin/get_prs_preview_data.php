<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

$selected_ids = isset($_POST['selected_ids']) ? $_POST['selected_ids'] : '';

if (empty($selected_ids)) {
    echo json_encode(['success' => false, 'message' => 'No items selected']);
    exit();
}

// Convert comma-separated IDs to array
$ids_array = explode(',', $selected_ids);
$placeholders = implode(',', array_fill(0, count($ids_array), '?'));

try {
    // Fetch selected condemned items
    $query = "SELECT 
        ce.id,
        ce.model,
        ce.category,
        ce.serial_number,
        ce.reason_condemned,
        ce.condemned_date,
        ce.estimated_value,
        u.full_name as condemned_by_name
    FROM condemned_equipment ce 
    LEFT JOIN users u ON ce.condemned_by = u.id 
    WHERE ce.id IN ($placeholders)
    ORDER BY ce.condemned_date DESC";

    $stmt = $db->prepare($query);
    $stmt->execute($ids_array);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        echo json_encode(['success' => false, 'message' => 'No items found']);
        exit();
    }

    // Format data for preview
    $preview_data = [];
    foreach ($items as $item) {
        // Extract year from condemned_date
        $yearAcquired = $item['condemned_date'] ? date('Y', strtotime($item['condemned_date'])) : 'N/A';
        
        // Build description
        $description = $item['model'] . ' (' . $item['category'] . ')';
        if (!empty($item['reason_condemned'])) {
            $description .= ' - ' . $item['reason_condemned'];
        }
        
        $preview_data[] = [
            'id' => $item['id'],
            'description' => $description,
            'year_acquired' => $yearAcquired,
            'serial_number' => $item['serial_number'] ?: 'N/A',
            'end_user' => $item['condemned_by_name'] ?: 'N/A',
            'estimated_value' => $item['estimated_value']
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $preview_data
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
