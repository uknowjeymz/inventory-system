<?php
session_start();
require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Check if this is the new group edit form submission
if(isset($_POST['edit_request_group_submit'])) {
    try {
        // Debug - log received data
        error_log("Edit request group submitted - POST data: " . print_r($_POST, true));
        
        $group_id = $_POST['group_id'] ?? 0;
        $employee = $_POST['employee'] ?? '';
        $office = $_POST['office'] ?? '';
        $approved_by = $_POST['approved_by'] ?? '';
        $supply_officer = $_POST['supply_officer'] ?? '';
        $group_status = $_POST['group_status'] ?? 'Pending';
        
        // Check if arrays exist
        $item_ids = $_POST['item_ids'] ?? [];
        $consumable_ids = $_POST['consumable_ids'] ?? [];
        $quantities = $_POST['quantities'] ?? [];
        $descriptions = $_POST['descriptions'] ?? [];
        $statuses = $_POST['statuses'] ?? [];
        
        // Validate required data
        if (empty($group_id)) {
            throw new Exception("Group ID is missing");
        }
        
        // Start transaction
        $db->beginTransaction();
        
        // Update request_groups
        $update_group = $db->prepare("UPDATE request_groups SET 
                                      employee = ?, 
                                      office = ?, 
                                      approved_by = ?, 
                                      supply_officer = ?, 
                                      status = ? 
                                      WHERE id = ?");
        $update_group->execute([$employee, $office, $approved_by, $supply_officer, $group_status, $group_id]);
        
        // Process each item if there are any
        if (!empty($item_ids) && is_array($item_ids)) {
            for ($i = 0; $i < count($item_ids); $i++) {
                $item_id = $item_ids[$i];
                $new_consumable_id = $consumable_ids[$i] ?? 0;
                $new_quantity = (int)($quantities[$i] ?? 0);
                $new_description = $descriptions[$i] ?? '';
                $new_status = $statuses[$i] ?? 'Pending';
                
                if (empty($item_id) || empty($new_consumable_id) || $new_quantity <= 0) {
                    continue; // Skip invalid items
                }
                
                // Get current item data
                $stmt = $db->prepare("SELECT ri.*, c.quantity as current_stock 
                                      FROM request_items ri 
                                      JOIN consumables c ON ri.consumable_id = c.id 
                                      WHERE ri.id = ?");
                $stmt->execute([$item_id]);
                $current_item = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($current_item) {
                    // Check if consumable_id changed
                    if ($current_item['consumable_id'] != $new_consumable_id) {
                        // Item changed - restore stock to old item, deduct from new item
                        
                        // Restore stock to old consumable
                        $restore_stmt = $db->prepare("UPDATE consumables SET quantity = quantity + ? WHERE id = ?");
                        $restore_stmt->execute([$current_item['quantity'], $current_item['consumable_id']]);
                        
                        // Check stock availability for new item
                        $check_stmt = $db->prepare("SELECT quantity FROM consumables WHERE id = ?");
                        $check_stmt->execute([$new_consumable_id]);
                        $new_stock = $check_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($new_quantity > $new_stock['quantity']) {
                            throw new Exception("Insufficient stock for new item selection. Available: " . $new_stock['quantity']);
                        }
                        
                        // Deduct from new consumable
                        $deduct_stmt = $db->prepare("UPDATE consumables SET quantity = quantity - ? WHERE id = ?");
                        $deduct_stmt->execute([$new_quantity, $new_consumable_id]);
                        
                    } else {
                        // Same item - adjust quantity difference
                        $quantity_diff = $new_quantity - $current_item['quantity'];
                        
                        if ($quantity_diff != 0) {
                            // Check stock availability if increasing
                            if ($quantity_diff > 0) {
                                $check_stmt = $db->prepare("SELECT quantity FROM consumables WHERE id = ?");
                                $check_stmt->execute([$new_consumable_id]);
                                $current_stock = $check_stmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($quantity_diff > $current_stock['quantity']) {
                                    throw new Exception("Insufficient stock to increase quantity. Available: " . $current_stock['quantity']);
                                }
                            }
                            
                            // Adjust stock
                            $adjust_stmt = $db->prepare("UPDATE consumables SET quantity = quantity - ? WHERE id = ?");
                            $adjust_stmt->execute([$quantity_diff, $new_consumable_id]);
                        }
                    }
                    
                    // Update request_items
                    $update_item = $db->prepare("UPDATE request_items SET 
                                                consumable_id = ?,
                                                quantity = ?,
                                                description = ?,
                                                status = ?
                                                WHERE id = ?");
                    $update_item->execute([$new_consumable_id, $new_quantity, $new_description, $new_status, $item_id]);
                }
            }
        }
        
        // Check if all items in group are approved to update group status accordingly
        $check_pending = $db->prepare("SELECT COUNT(*) as pending FROM request_items WHERE group_id = ? AND status != 'Approved'");
        $check_pending->execute([$group_id]);
        $pending = $check_pending->fetch(PDO::FETCH_ASSOC);
        
        // Auto-adjust group status based on item statuses if needed
        if ($pending['pending'] == 0 && $group_status != 'Approved') {
            // All items approved but group not marked as approved - update group status
            $db->prepare("UPDATE request_groups SET status = 'Approved' WHERE id = ?")->execute([$group_id]);
        } elseif ($pending['pending'] > 0 && $group_status == 'Approved') {
            // Some items pending but group marked as approved - revert group status
            $db->prepare("UPDATE request_groups SET status = 'Pending' WHERE id = ?")->execute([$group_id]);
        }
        
        $db->commit();
        $_SESSION['success_message'] = "Request group updated successfully and inventory adjusted!";
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $_SESSION['error_message'] = "Error updating request: " . $e->getMessage();
        error_log("Error in consumables_edit_request_action.php: " . $e->getMessage());
    }
    
    header("Location: consumables.php");
    exit();
}

// Keep the old edit functionality for backward compatibility
if(isset($_POST['edit_request'])) {
    try {
        $id = $_POST['request_id'] ?? 0;
        $new_qty = (int)($_POST['quantity'] ?? 0);

        if (empty($id)) {
            throw new Exception("Request ID is missing");
        }

        // 1. Get old quantity
        $old_stmt = $db->prepare("SELECT quantity, consumable_id FROM requests WHERE id = ?");
        $old_stmt->execute([$id]);
        $old_data = $old_stmt->fetch(PDO::FETCH_ASSOC);

        if ($old_data) {
            $diff = $new_qty - $old_data['quantity'];
            
            // 2. Adjust stock in consumables
            $update_stock = $db->prepare("UPDATE consumables SET quantity = quantity - ? WHERE id = ?");
            $update_stock->execute([$diff, $old_data['consumable_id']]);

            // 3. Update 'requests' record
            $sql = "UPDATE requests SET 
                    employee = ?, office = ?, request_date = ?, 
                    date_received = ?, description = ?, 
                    quantity = ?, requested_by = ?, approved_by = ?, 
                    supply_officer = ?, received_by = ?,
                    request_status = ? 
                    WHERE id = ?";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                $_POST['employee'] ?? '',
                $_POST['office'] ?? '',
                $_POST['request_date'] ?? date('Y-m-d'),
                !empty($_POST['date_received']) ? $_POST['date_received'] : null,
                $_POST['description'] ?? '',
                $new_qty,
                $_POST['requested_by'] ?? '',
                $_POST['approved_by'] ?? '',
                $_POST['supply_officer'] ?? '',
                $_POST['received_by'] ?? '',
                $_POST['request_status'] ?? 'Pending',
                $id
            ]);

            $_SESSION['success_message'] = "Request history updated and stock levels adjusted.";
        } else {
            throw new Exception("Original request not found");
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error updating request: " . $e->getMessage();
        error_log("Error in consumables_edit_request_action.php (old edit): " . $e->getMessage());
    }
    header("Location: consumables.php");
    exit();
}

// If we get here, no valid action was found
header("Location: consumables.php");
exit();
?>