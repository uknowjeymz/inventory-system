<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit();
}

// Include database connection
require_once '../consumable/config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get item IDs from query string
$ids = $_GET['ids'] ?? '';
if (empty($ids)) {
    echo json_encode([]);
    exit();
}

// Convert comma-separated string to array
$id_array = explode(',', $ids);
$placeholders = implode(',', array_fill(0, count($id_array), '?'));

// Fetch items from database - removed price column
$query = "SELECT id, item_name, category, brand, quantity as available_stock, unit 
          FROM consumables 
          WHERE id IN ($placeholders)";
$stmt = $db->prepare($query);
$stmt->execute($id_array);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($items);
?>