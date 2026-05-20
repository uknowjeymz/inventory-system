<?php

require_once '../config/database.php';
require_once 'check_critical_stock.php';
require_once 'send_notification.php';

$database = new Database();
$db = $database->getConnection();

// Check all items
$result = checkCriticalStock($db);

// Log the result
error_log("[" . date('Y-m-d H:i:s') . "] Critical Stock Check: " . $result['message']);

// If there are critical items and we want to send a summary email (optional)
if (!empty($result['critical_items'])) {
    // You could send a summary email here if needed
    $admin_emails = getAdminEmails($db); // You'd need to create this function
    // sendSummaryEmail($admin_emails, $result['critical_items']);
}

echo $result['message'] . "\n";
?>