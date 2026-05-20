<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

require_once '../config/database.php'; // Admin's database connection
$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Get notifications for this admin
$query = "SELECT n.*, 
          CASE 
              WHEN n.reference_type = 'request_group' THEN 
                  (SELECT COUNT(*) FROM request_items WHERE group_id = n.reference_id AND status = 'Pending')
              ELSE NULL
          END as pending_count
          FROM notifications n 
          WHERE n.user_id = ? 
          ORDER BY n.created_at DESC 
          LIMIT 50";

$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unread count
$unread_query = "SELECT COUNT(*) as unread FROM notifications 
                 WHERE user_id = ? AND is_read = 0";
$unread_stmt = $db->prepare($unread_query);
$unread_stmt->execute([$user_id]);
$unread_count = $unread_stmt->fetch(PDO::FETCH_ASSOC)['unread'];

echo json_encode([
    'success' => true,
    'notifications' => $notifications,
    'unread_count' => $unread_count
]);
?>