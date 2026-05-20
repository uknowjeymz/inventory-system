<?php
session_start();
require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

$id = $_GET['id'] ?? 0;
$type = $_GET['type'] ?? 'item';

try {
    if ($type === 'request') {
        $stmt = $db->prepare("DELETE FROM consumable_requests WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success'] = "History record deleted.";
    } else {
        $stmt = $db->prepare("DELETE FROM consumables WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success'] = "Consumable item deleted permanently.";
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
}

header("Location: consumables.php");
exit();