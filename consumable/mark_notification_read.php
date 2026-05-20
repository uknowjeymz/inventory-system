<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

require_once '../consumable/config/database.php';
$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$notification_id = $data['notification_id'] ?? 0;

if ($notification_id === 'all') {
    // Mark all as read
    $query = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
} else {
    // Mark single as read
    $query = "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$notification_id, $user_id]);
}

echo json_encode(['success' => true]);