<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$selected_items = isset($_POST['selected_items']) ? $_POST['selected_items'] : '';

// Debug output
$debug_info = [
    'raw_post' => $_POST,
    'selected_items_string' => $selected_items,
    'items_array' => explode(',', $selected_items),
    'item_count' => count(explode(',', $selected_items))
];

echo json_encode([
    'success' => true,
    'debug' => $debug_info
]);
