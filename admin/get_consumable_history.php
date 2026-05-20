<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once '../config/database.php';
require_once 'consumables_history_logger.php';

$database = new Database();
$db = $database->getConnection();
$logger = new ConsumablesHistoryLogger($db);

$consumable_id = $_GET['id'] ?? null;

if (!$consumable_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Consumable ID required']);
    exit();
}

// Get consumable details
$stmt = $db->prepare("SELECT * FROM consumables WHERE id = ?");
$stmt->execute([$consumable_id]);
$consumable = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$consumable) {
    http_response_code(404);
    echo json_encode(['error' => 'Consumable not found']);
    exit();
}

// Get history
$history = $logger->getHistory($consumable_id, 100);

// Get statistics
$stats = $logger->getStatistics($consumable_id);

echo json_encode([
    'success' => true,
    'consumable' => $consumable,
    'history' => $history,
    'statistics' => $stats
]);
?>
