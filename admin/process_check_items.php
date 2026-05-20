<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Include notification helper - ADD THIS
require_once '../consumable/send_notification.php';

// Include critical stock checker
require_once 'check_critical_stock.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $group_id = $_POST['group_id'] ?? 0;
    $item_ids = $_POST['item_ids'] ?? [];
    $statuses = $_POST['status'] ?? [];
    $reasons = $_POST['reason'] ?? [];
    
    try {
        $db->beginTransaction();
        
        $success_count = 0;
        $critical_alerts = []; // Track items that become critical
        
        foreach ($item_ids as $item_id) {
            $status = $statuses[$item_id] ?? '';
            $reason = $reasons[$item_id] ?? '';
            
            if (empty($status)) {
                continue; // Skip if no status selected
            }
            
            // Validate rejection reason
            if ($status === 'Rejected' && empty(trim($reason))) {
                throw new Exception("Rejection reason is required for rejected items.");
            }
            
            // Get item details to check stock - FIXED
            $item_query = $db->prepare("
                SELECT ri.*, c.quantity as stock_qty, c.id as consumable_id, c.unit, c.max_stock
                FROM request_items ri 
                JOIN consumables c ON ri.consumable_id = c.id 
                WHERE ri.id = ?
            ");
            $item_query->execute([$item_id]);
            $item = $item_query->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                throw new Exception("Item not found.");
            }

            if ($status === 'Approved') {
                // Check if enough stock is available
                if ($item['quantity'] > $item['stock_qty']) {
                    throw new Exception("Insufficient stock for item. Available: " . $item['stock_qty']);
                }
                
                // Store previous quantity for critical check
                $previous_quantity = $item['stock_qty'];
                
                // Deduct stock - NOW USING CORRECT COLUMN NAME
                $update_stock = $db->prepare("UPDATE consumables SET quantity = quantity - ? WHERE id = ?");
                $update_stock->execute([$item['quantity'], $item['consumable_id']]);
                
                // Check if this item became critical after deduction
                $critical_result = checkItemAfterDeduction($db, $item['consumable_id'], $item['quantity'], $previous_quantity);
                if (!empty($critical_result['critical_items'])) {
                    $critical_alerts = array_merge($critical_alerts, $critical_result['critical_items']);
                }
                
                // Update item status
                $update_item = $db->prepare("
                    UPDATE request_items 
                    SET status = 'Approved', 
                        release_date = NOW(),
                        checked_by = ?,
                        checked_at = NOW()
                    WHERE id = ?
                ");
                $update_item->execute([$_SESSION['user_id'], $item_id]);
                
            } elseif ($status === 'Rejected') {
                // Update item status with rejection reason
                $update_item = $db->prepare("
                    UPDATE request_items 
                    SET status = 'Rejected', 
                        rejection_reason = ?,
                        checked_by = ?,
                        checked_at = NOW()
                    WHERE id = ?
                ");
                $update_item->execute([$reason, $_SESSION['user_id'], $item_id]);
            }
            
            $success_count++;
        }
        
        // Check if all items in group are processed
        $check_pending = $db->prepare("
            SELECT COUNT(*) as pending 
            FROM request_items 
            WHERE group_id = ? AND status = 'Pending'
        ");
        $check_pending->execute([$group_id]);
        $pending = $check_pending->fetch(PDO::FETCH_ASSOC);
        
        // If no pending items, determine the group status
        if ($pending['pending'] == 0) {
            // Check if any items were approved
            $check_approved = $db->prepare("
                SELECT COUNT(*) as approved 
                FROM request_items 
                WHERE group_id = ? AND status = 'Approved'
            ");
            $check_approved->execute([$group_id]);
            $approved = $check_approved->fetch(PDO::FETCH_ASSOC);
            
            // Check if any items were rejected
            $check_rejected = $db->prepare("
                SELECT COUNT(*) as rejected 
                FROM request_items 
                WHERE group_id = ? AND status = 'Rejected'
            ");
            $check_rejected->execute([$group_id]);
            $rejected = $check_rejected->fetch(PDO::FETCH_ASSOC);
            
            // Determine new group status
            if ($rejected['rejected'] > 0 && $approved['approved'] > 0) {
                // Mix of approved and rejected
                $new_status = 'Partially Approved';
            } elseif ($rejected['rejected'] > 0) {
                // All items rejected
                $new_status = 'Rejected';
            } elseif ($approved['approved'] > 0) {
                // All items approved
                $new_status = 'Approved';
            } else {
                $new_status = 'Pending'; // Should not happen
            }
            
            // Update group status
            $update_group = $db->prepare("UPDATE request_groups SET status = ? WHERE id = ?");
            $update_group->execute([$new_status, $group_id]);
        }
        
        $db->commit();
        
        // Store critical items in session for highlighting
        if (!empty($critical_alerts)) {
            $_SESSION['critical_stock_alerts'] = $critical_alerts;
        }
        
        $_SESSION['equipment_success'] = "$success_count item(s) processed successfully!";
        
        // Add message about critical items if any
        if (!empty($critical_alerts)) {
            $_SESSION['equipment_success'] .= " " . count($critical_alerts) . " item(s) are now at critical level and need refill.";
        }
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $_SESSION['equipment_error'] = "Error: " . $e->getMessage();
    }
    
    header("Location: consumables.php");
    exit();
}
?>