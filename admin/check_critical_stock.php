<?php
/**
 * Check Critical Stock Levels
 * This function checks if any consumable items have reached critical stock
 * and sends notifications to admins
 */

// Include critical stock checker
require_once 'check_critical_stock.php';

require_once '../consumable/send_notification.php';

function checkCriticalStock($db, $consumable_id = null) {
    // Define function to get critical threshold based on unit and max_stock
    function getCriticalThreshold($quantity, $unit, $max_stock = null) {
        // If max_stock is set, critical is 20% of max stock
        if ($max_stock && $max_stock > 0) {
            return round($max_stock * 0.2);
        }
        
        // Fallback to unit-based thresholds
        $unit = strtolower(trim($unit));
        switch ($unit) {
            case 'pcs':
                return 30; // Changed from 20 to 30
            case 'unit':
                return 10; // Critical at ≤10 units
            case 'box':
            case 'ream':
                return 10; // Critical at ≤10 boxes/reams
            default:
                return 10; // Default critical threshold
        }
    }
    
    // Build query to check critical items
    if ($consumable_id) {
        // Check specific item
        $query = "SELECT c.*, 
                  (SELECT COUNT(*) FROM request_items ri 
                   JOIN request_groups rg ON ri.group_id = rg.id 
                   WHERE ri.consumable_id = c.id AND rg.status = 'Approved') as times_requested
                  FROM consumables c 
                  WHERE c.id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute([':id' => $consumable_id]);
    } else {
        // Check all items
        $query = "SELECT c.*, 
                  (SELECT COUNT(*) FROM request_items ri 
                   JOIN request_groups rg ON ri.group_id = rg.id 
                   WHERE ri.consumable_id = c.id AND rg.status = 'Approved') as times_requested
                  FROM consumables c";
        $stmt = $db->prepare($query);
        $stmt->execute();
    }
    
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $critical_items = [];
    $notifications_sent = 0;
    
    foreach ($items as $item) {
        $critical_threshold = getCriticalThreshold($item['quantity'], $item['unit'], $item['max_stock']);
        
        // Check if item is critical (quantity <= threshold)
        if ($item['quantity'] <= $critical_threshold) {
            $critical_items[] = $item;
            
            // Check if we already sent a notification for this critical state
            $check_query = "SELECT id FROM notifications 
                           WHERE reference_id = ? 
                           AND reference_type = 'critical_stock' 
                           AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                           AND is_read = 0";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([$item['id']]);
            
            // Only send notification if not already sent in the last 24 hours
            if ($check_stmt->rowCount() == 0) {
                // Determine threshold display
                $threshold_display = $critical_threshold;
                if ($item['max_stock'] && $item['max_stock'] > 0) {
                    $threshold_display = $critical_threshold . " (20% of max stock)";
                }
                
                // Send notification to admins
                $title = "⚠️ Critical Stock Alert";
                $message = "{$item['item_name']} is critically low! Current stock: {$item['quantity']} {$item['unit']} (Threshold: ≤{$threshold_display})";
                $link = "../admin/consumables.php?highlight_critical={$item['id']}#current-inventory";

                // DEBUG
                error_log("ABOUT TO CALL notifyAdmins for item ID: " . $item['id'] . " - " . $item['item_name']);

                $notifications_sent += notifyAdmins(
                    $db,
                    'critical_stock',
                    $title,
                    $message,
                    $link,
                    $item['id'],
                    'critical_stock'
                );

                error_log("AFTER notifyAdmins call - notifications_sent: " . $notifications_sent);
                
                // Also update the item's status in the database (if you have a status column)
                // Uncomment if you have a status column
                // $update_status = $db->prepare("UPDATE consumables SET status = 'Critical' WHERE id = ?");
                // $update_status->execute([$item['id']]);
            }
        } else {
            // Item is not critical, check if it should be marked as Available or Low
            // This is optional - you can implement if needed
        }
    }
    
    return [
        'success' => true,
        'critical_items' => $critical_items,
        'notifications_sent' => $notifications_sent,
        'message' => count($critical_items) . ' critical item(s) found, ' . $notifications_sent . ' notification(s) sent.'
    ];
}

// Helper function to check a single item after deduction
function checkItemAfterDeduction($db, $consumable_id, $deducted_quantity, $previous_quantity) {
    // Get updated item details
    $query = "SELECT * FROM consumables WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$consumable_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        return ['success' => false, 'message' => 'Item not found'];
    }
    
    $critical_threshold = 0;
    if ($item['max_stock'] && $item['max_stock'] > 0) {
        $critical_threshold = round($item['max_stock'] * 0.2);
    } else {
        $unit = strtolower(trim($item['unit'] ?? ''));
        switch ($unit) {
            case 'pcs':
                $critical_threshold = 20;
                break;
            case 'unit':
            case 'box':
            case 'ream':
                $critical_threshold = 10;
                break;
            default:
                $critical_threshold = 10;
        }
    }
    
    $is_critical_now = $item['quantity'] <= $critical_threshold;  // 30 <= 30 = TRUE
    $was_critical_before = $previous_quantity <= $critical_threshold;  // 50 <= 30 = FALSE

    // If it just became critical
    if ($is_critical_now && !$was_critical_before) {
        return checkCriticalStock($db, $consumable_id);
    }
    
    return [
        'success' => true,
        'is_critical' => $is_critical_now,
        'message' => $is_critical_now ? 'Item is now critical' : 'Item is not critical'
    ];
}
?>