<?php
session_start();
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';
require_once 'consumables_history_logger.php';

$database = new Database();
$db = $database->getConnection();
$historyLogger = new ConsumablesHistoryLogger($db);

$success = "";
$error = "";

// --- HANDLE ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        // 1. Add New Consumable (Multiple Items)
        if ($_POST['action'] === 'add_consumable') {
            $item_names = $_POST['item_name'];
            $categories = $_POST['category'];
            $quantities = $_POST['quantity'];
            $max_stocks = $_POST['max_stock'] ?? []; // New field
            $units = $_POST['unit'];
            $brands = $_POST['brand'];
            $identifications = $_POST['identification'];
            
            $success_count = 0;
            
            // Start transaction
            $db->beginTransaction();
            
            try {
                // Updated SQL to include max_stock
                $sql = "INSERT INTO consumables (item_name, category, quantity, max_stock, unit, brand, identification, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $db->prepare($sql);
                
                for ($i = 0; $i < count($item_names); $i++) {
                    if (!empty($item_names[$i]) && !empty($quantities[$i])) {
                        $max_stock = isset($max_stocks[$i]) && !empty($max_stocks[$i]) ? $max_stocks[$i] : null;
                        
                        $stmt->execute([
                            $item_names[$i], 
                            $categories[$i] ?? null, 
                            $quantities[$i], 
                            $max_stock, // New field
                            $units[$i] ?? null, 
                            $brands[$i] ?? null, 
                            $identifications[$i] ?? ('ITM-' . uniqid())
                        ]);
                        
                        // Get the newly inserted consumable ID
                        $new_consumable_id = $db->lastInsertId();
                        
                        // Log to consumables_history as initial stock
                        $historyLogger->log(
                            $new_consumable_id,
                            'initial',
                            0, // previous quantity
                            $quantities[$i], // quantity change
                            $quantities[$i], // new quantity
                            $_SESSION['user_id'],
                            'initial_stock',
                            null,
                            'Initial stock when item was added'
                        );
                        
                        $success_count++;
                    }
                }
                
                $db->commit();
                $_SESSION['success_message'] = "$success_count new consumable item(s) added successfully!";
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            header("Location: consumables.php");
            exit();
        }

        // 2. Submit Multi-Item Request (Single Group)
        if ($_POST['action'] === 'request_items') {
            // Combine the name fields
            $last_name = $_POST['last_name'];
            $first_name = $_POST['first_name'];
            $middle_initial = $_POST['middle_initial'] ?? '';
            
            // Format: Last Name, First Name Middle Initial
            $employee = trim($last_name . ', ' . $first_name . ' ' . $middle_initial);
            
            $campus = $_POST['campus']; // New campus field
            $office = $_POST['office'];
            $request_date = $_POST['request_date'];
            $approved_by = $_POST['approved_by'];
            $supply_officer = $_POST['supply_officer'];
            $requested_by = $_SESSION['full_name'];
            
            $item_ids = $_POST['item_id'];
            $quantities = $_POST['req_quantity'];
            $descriptions = $_POST['description'];
            
            // Generate unique group code
            $group_code = 'REQ-' . date('Ymd') . '-' . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
            
            // Start transaction
            $db->beginTransaction();
            
            // Insert into request_groups - Updated to include campus
            $group_sql = "INSERT INTO request_groups (group_code, employee, campus, office, request_date, requested_by, approved_by, supply_officer, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";
            $group_stmt = $db->prepare($group_sql);
            $group_stmt->execute([$group_code, $employee, $campus, $office, $request_date, $requested_by, $approved_by, $supply_officer]);
            
            $group_id = $db->lastInsertId();
            
            // Insert each item into request_items
            $item_sql = "INSERT INTO request_items (group_id, consumable_id, quantity, description, status) 
                        VALUES (?, ?, ?, ?, 'Pending')";
            $item_stmt = $db->prepare($item_sql);
            
            for ($i = 0; $i < count($item_ids); $i++) {
                if (!empty($item_ids[$i]) && $quantities[$i] > 0) {
                    $item_stmt->execute([$group_id, $item_ids[$i], $quantities[$i], $descriptions[$i]]);
                }
            }
            
            $db->commit();
            $_SESSION['success_message'] = "Request submitted successfully! Reference Code: <strong>$group_code</strong>";
            header("Location: consumables.php");
            exit();
        }

        // 3. Release Item (Approve individual item)
        if ($_POST['action'] === 'release_item') {
            $item_id = $_POST['item_id'];
            
            // Fetch item and current stock
            $stmt = $db->prepare("SELECT ri.*, c.quantity as stock_qty, c.id as consumable_id 
                                  FROM request_items ri 
                                  JOIN consumables c ON ri.consumable_id = c.id 
                                  WHERE ri.id = ?");
            $stmt->execute([$item_id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) throw new Exception("Request item not found.");

            if ($item['quantity'] > $item['stock_qty']) {
                throw new Exception("Insufficient stock to release this item! Available: " . $item['stock_qty']);
            }

            // Start transaction
            $db->beginTransaction();
            
            // Get current stock before deduction
            $stock_stmt = $db->prepare("SELECT quantity FROM consumables WHERE id = ?");
            $stock_stmt->execute([$item['consumable_id']]);
            $current_stock = $stock_stmt->fetchColumn();
            
            // DEDUCT STOCK AND MARK ITEM AS APPROVED
            $db->prepare("UPDATE consumables SET quantity = quantity - ? WHERE id = ?")->execute([$item['quantity'], $item['consumable_id']]);
            $db->prepare("UPDATE request_items SET status = 'Approved', release_date = NOW() WHERE id = ?")->execute([$item_id]);
            
            // Log to consumables_history
            $historyLogger->logDeduction(
                $item['consumable_id'],
                $current_stock,
                $item['quantity'],
                $current_stock - $item['quantity'],
                $_SESSION['user_id'],
                $item['group_id'],
                "Released to request #" . $item['group_id']
            );
            
            // Check if all items in group are approved
            $check_stmt = $db->prepare("SELECT COUNT(*) as pending FROM request_items WHERE group_id = ? AND status != 'Approved'");
            $check_stmt->execute([$item['group_id']]);
            $pending = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($pending['pending'] == 0) {
                // All items approved, update group status
                $db->prepare("UPDATE request_groups SET status = 'Approved' WHERE id = ?")->execute([$item['group_id']]);
            }
            
            $db->commit();
            $_SESSION['success_message'] = "Item released successfully and inventory updated!";
            header("Location: consumables.php");
            exit();
        }

        // 4. Release Entire Group
        if ($_POST['action'] === 'release_group') {
            $group_id = $_POST['group_id'];
            
            // Get all pending items in the group
            $stmt = $db->prepare("SELECT ri.*, c.quantity as stock_qty, c.id as consumable_id 
                                  FROM request_items ri 
                                  JOIN consumables c ON ri.consumable_id = c.id 
                                  WHERE ri.group_id = ? AND ri.status = 'Pending'");
            $stmt->execute([$group_id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($items)) throw new Exception("No pending items in this request.");
            
            // Check stock availability for all items
            foreach ($items as $item) {
                if ($item['quantity'] > $item['stock_qty']) {
                    throw new Exception("Insufficient stock for item ID " . $item['id'] . "! Available: " . $item['stock_qty']);
                }
            }
            
            // Start transaction
            $db->beginTransaction();
            
            // Process all items
            foreach ($items as $item) {
                // Get current stock before deduction
                $stock_stmt = $db->prepare("SELECT quantity FROM consumables WHERE id = ?");
                $stock_stmt->execute([$item['consumable_id']]);
                $current_stock = $stock_stmt->fetchColumn();
                
                $db->prepare("UPDATE consumables SET quantity = quantity - ? WHERE id = ?")->execute([$item['quantity'], $item['consumable_id']]);
                $db->prepare("UPDATE request_items SET status = 'Approved', release_date = NOW() WHERE id = ?")->execute([$item['id']]);
                
                // Log to consumables_history
                $historyLogger->logDeduction(
                    $item['consumable_id'],
                    $current_stock,
                    $item['quantity'],
                    $current_stock - $item['quantity'],
                    $_SESSION['user_id'],
                    $group_id,
                    "Released to request #" . $group_id
                );
            }
            
            // Update group status
            $db->prepare("UPDATE request_groups SET status = 'Approved' WHERE id = ?")->execute([$group_id]);
            
            $db->commit();
            $_SESSION['success_message'] = "All items in the request have been released successfully!";
            header("Location: consumables.php");
            exit();
        }

        // 5. Refill Item Stock
        if ($_POST['action'] === 'refill_item') {
            $item_id = $_POST['item_id'];
            $refill_qty = (int)$_POST['refill_quantity'];
            $remarks = $_POST['refill_remarks'] ?? '';
            
            // First get current unit, quantity, and max_stock
            $get_stmt = $db->prepare("SELECT unit, quantity, max_stock FROM consumables WHERE id = ?");
            $get_stmt->execute([$item_id]);
            $item = $get_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$item) {
                throw new Exception("Item not found.");
            }
            
            $previous_quantity = $item['quantity'];
            $new_quantity = $previous_quantity + $refill_qty;
            $unit = strtolower(trim($item['unit'] ?? ''));
            $max_stock = $item['max_stock'];
            
            // Calculate new status based on 20% threshold if max_stock is set
            if ($max_stock && $max_stock > 0) {
                $critical_threshold = round($max_stock * 0.2); // 20% of max stock
                
                if ($new_quantity <= 0) {
                    $status = 'Critical';
                } elseif ($new_quantity <= $critical_threshold) {
                    $status = 'Critical';
                } elseif ($new_quantity <= ($critical_threshold * 2)) { // 40% of max stock for Low
                    $status = 'Low';
                } else {
                    $status = 'Available';
                }
            } else {
                // Fallback to old threshold system
                if ($new_quantity <= 0) {
                    $status = 'Critical';
                } else {
                    switch ($unit) {
                        case 'pcs':
                            if ($new_quantity <= 20) $status = 'Critical';
                            elseif ($new_quantity <= 50) $status = 'Low';
                            else $status = 'Available';
                            break;
                        case 'unit':
                            if ($new_quantity <= 10) $status = 'Critical';
                            elseif ($new_quantity <= 20) $status = 'Low';
                            else $status = 'Available';
                            break;
                        case 'box':
                        case 'ream':
                            if ($new_quantity <= 20) $status = 'Critical';
                            elseif ($new_quantity <= 30) $status = 'Low';
                            else $status = 'Available';
                            break;
                        default:
                            if ($new_quantity <= 10) $status = 'Low';
                            else $status = 'Available';
                            break;
                    }
                }
            }
            
            // Start transaction
            $db->beginTransaction();
            
            try {
                // Update consumable quantity and status
                $stmt = $db->prepare("UPDATE consumables SET quantity = ?, status = ? WHERE id = ?");
                $stmt->execute([$new_quantity, $status, $item_id]);
                
                // Insert refill history (old table)
                $history_stmt = $db->prepare("INSERT INTO consumable_refills 
                                            (consumable_id, previous_quantity, refill_quantity, new_quantity, refilled_by, remarks) 
                                            VALUES (?, ?, ?, ?, ?, ?)");
                $history_stmt->execute([
                    $item_id, 
                    $previous_quantity, 
                    $refill_qty, 
                    $new_quantity, 
                    $_SESSION['user_id'], 
                    $remarks
                ]);
                
                // Log to consumables_history (new comprehensive table)
                $historyLogger->logRefill(
                    $item_id,
                    $previous_quantity,
                    $refill_qty,
                    $new_quantity,
                    $_SESSION['user_id'],
                    $remarks
                );
                
                $db->commit();
                $_SESSION['success_message'] = "Stock refilled successfully! Added {$refill_qty} units. New total: {$new_quantity}.";
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            
            header("Location: consumables.php");
            exit();
        }

        // 6. Edit Consumable Item
        if ($_POST['action'] === 'edit_consumable') {
            $item_id = $_POST['item_id'];
            $item_name = $_POST['item_name'];
            $category = $_POST['category'] ?? null;
            $quantity = (int)$_POST['quantity'];
            $max_stock = !empty($_POST['max_stock']) ? (int)$_POST['max_stock'] : null;
            $unit = strtolower(trim($_POST['unit'] ?? ''));
            $brand = $_POST['brand'] ?? null;
            $identification = $_POST['identification'];
            
            // Calculate status based on 20% threshold if max_stock is set
            if ($max_stock && $max_stock > 0) {
                $critical_threshold = round($max_stock * 0.2); // 20% of max stock
                
                if ($quantity <= 0) {
                    $status = 'Critical';
                } elseif ($quantity <= $critical_threshold) {
                    $status = 'Critical';
                } elseif ($quantity <= ($critical_threshold * 2)) { // 40% of max stock for Low
                    $status = 'Low';
                } else {
                    $status = 'Available';
                }
            } else {
                // Fallback to old threshold system if max_stock not set
                if ($quantity <= 0) {
                    $status = 'Critical';
                } else {
                    switch ($unit) {
                        case 'pcs':
                            if ($quantity <= 20) $status = 'Critical';
                            elseif ($quantity <= 50) $status = 'Low';
                            else $status = 'Available';
                            break;
                        case 'unit':
                            if ($quantity <= 10) $status = 'Critical';
                            elseif ($quantity <= 20) $status = 'Low';
                            else $status = 'Available';
                            break;
                        case 'box':
                        case 'ream':
                            if ($quantity <= 20) $status = 'Critical';
                            elseif ($quantity <= 30) $status = 'Low';
                            else $status = 'Available';
                            break;
                        default:
                            if ($quantity <= 10) $status = 'Low';
                            else $status = 'Available';
                            break;
                    }
                }
            }
            
            $query = "UPDATE consumables SET 
                    item_name = ?, 
                    category = ?, 
                    quantity = ?, 
                    max_stock = ?,
                    unit = ?, 
                    brand = ?, 
                    identification = ?,
                    status = ?
                    WHERE id = ?";
            
            $stmt = $db->prepare($query);
            $stmt->execute([$item_name, $category, $quantity, $max_stock, $unit, $brand, $identification, $status, $item_id]);
            
            $_SESSION['success_message'] = "Item updated successfully!";
            header("Location: consumables.php");
            exit();
        }
        
        // 7. Delete Consumable Item
        if ($_POST['action'] === 'delete_consumable') {
            $item_id = $_POST['item_id'];
            
            try {
                // Start transaction
                $db->beginTransaction();
                
                // First check if this consumable has any pending requests
                $check_stmt = $db->prepare("SELECT COUNT(*) as pending_count 
                                            FROM request_items ri 
                                            JOIN request_groups rg ON ri.group_id = rg.id 
                                            WHERE ri.consumable_id = ? AND rg.status = 'Pending'");
                $check_stmt->execute([$item_id]);
                $pending = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($pending['pending_count'] > 0) {
                    throw new Exception("Cannot delete item because it has pending requests. Please process or cancel the requests first.");
                }
                
                // Check if this consumable has any approved requests (optional - you can decide to allow or block)
                $approved_check = $db->prepare("SELECT COUNT(*) as approved_count 
                                                FROM request_items ri 
                                                JOIN request_groups rg ON ri.group_id = rg.id 
                                                WHERE ri.consumable_id = ? AND rg.status = 'Approved'");
                $approved_check->execute([$item_id]);
                $approved = $approved_check->fetch(PDO::FETCH_ASSOC);
                
                // Get item details for logging
                $get_stmt = $db->prepare("SELECT item_name, identification FROM consumables WHERE id = ?");
                $get_stmt->execute([$item_id]);
                $item = $get_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$item) {
                    throw new Exception("Item not found.");
                }
                
                // Delete related records first (foreign key constraints)
                
                // 1. Delete from consumables_history
                $stmt1 = $db->prepare("DELETE FROM consumables_history WHERE consumable_id = ?");
                $stmt1->execute([$item_id]);
                
                // 2. Delete from consumable_refills
                $stmt2 = $db->prepare("DELETE FROM consumable_refills WHERE consumable_id = ?");
                $stmt2->execute([$item_id]);
                
                // 3. Delete from request_items (only if requests are not Approved)
                // We already checked for pending requests, but also handle approved/rejected
                $stmt3 = $db->prepare("DELETE ri FROM request_items ri 
                                       JOIN request_groups rg ON ri.group_id = rg.id 
                                       WHERE ri.consumable_id = ? AND rg.status IN ('Rejected', 'Approved')");
                $stmt3->execute([$item_id]);
                
                // 4. Finally delete the consumable itself
                $stmt4 = $db->prepare("DELETE FROM consumables WHERE id = ?");
                $stmt4->execute([$item_id]);
                
                // Log the deletion to consumable_logs
                $log_stmt = $db->prepare("INSERT INTO consumable_logs (action, remarks, performed_by, created_at) 
                                          VALUES (?, ?, ?, NOW())");
                $log_stmt->execute([
                    'item_deleted',
                    "Deleted consumable item: {$item['item_name']} (ID: {$item['identification']})",
                    $_SESSION['user_id']
                ]);
                
                $db->commit();
                
                $_SESSION['success_message'] = "Item '{$item['item_name']}' has been deleted successfully!";
                
            } catch (Exception $e) {
                $db->rollBack();
                $_SESSION['error_message'] = "Error deleting item: " . $e->getMessage();
                error_log("Delete error: " . $e->getMessage());
            }
            
            header("Location: consumables.php");
            exit();
        }
        
    } catch (Exception $e) { 
        $_SESSION['error_message'] = $e->getMessage();
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        header("Location: consumables.php");
        exit();
    }
}

/**
 * Calculate stock status based on quantity and max_stock (20% threshold)
 * Falls back to unit-based thresholds if max_stock not set
 */
function calculateStockStatus($quantity, $max_stock = null, $unit = '') {
    if ($quantity <= 0) {
        return 'Critical';
    }
    
    // Use max_stock if available for percentage-based threshold
    if ($max_stock && $max_stock > 0) {
        $critical_threshold = round($max_stock * 0.2); // 20% of max stock
        $low_threshold = round($max_stock * 0.4);      // 40% of max stock
        
        if ($quantity <= $critical_threshold) {
            return 'Critical';
        } elseif ($quantity <= $low_threshold) {
            return 'Low';
        } else {
            return 'Available';
        }
    }
    
    // Fallback to unit-based thresholds
    $unit = strtolower(trim($unit));
    switch ($unit) {
        case 'pcs':
            if ($quantity <= 20) return 'Critical';
            elseif ($quantity <= 50) return 'Low';
            else return 'Available';
        case 'unit':
            if ($quantity <= 10) return 'Critical';
            elseif ($quantity <= 20) return 'Low';
            else return 'Available';
        case 'box':
        case 'ream':
            if ($quantity <= 20) return 'Critical';
            elseif ($quantity <= 30) return 'Low';
            else return 'Available';
        default:
            if ($quantity <= 10) return 'Low';
            else return 'Available';
    }
}

// Get monthly consumption data
function getMonthlyConsumption($db, $year = null, $category = null, $department = null) {
    if (!$year) $year = date('Y');
    
    $query = "SELECT 
                MONTH(rg.request_date) as month,
                MONTHNAME(rg.request_date) as month_name,
                DATE_FORMAT(rg.request_date, '%Y-%m-%d') as full_date,
                c.category,
                rg.office as department,
                SUM(ri.quantity) as total_quantity,
                COUNT(DISTINCT rg.id) as request_count,
                COUNT(ri.id) as item_count
              FROM request_items ri
              JOIN request_groups rg ON ri.group_id = rg.id
              JOIN consumables c ON ri.consumable_id = c.id
              WHERE rg.status = 'Approved'
                AND YEAR(rg.request_date) = :year";
    
    if ($category && $category !== 'all') {
        $query .= " AND c.category = :category";
    }
    
    if ($department && $department !== 'all') {
        $query .= " AND rg.office = :department";
    }
    
    $query .= " GROUP BY MONTH(rg.request_date), c.category, rg.office
                ORDER BY MONTH(rg.request_date)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':year', $year);
    
    if ($category && $category !== 'all') {
        $stmt->bindParam(':category', $category);
    }
    
    if ($department && $department !== 'all') {
        $stmt->bindParam(':department', $department);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get annual consumption summary
function getAnnualConsumption($db, $year = null, $category = null, $department = null) {
    if (!$year) $year = date('Y');
    
    $query = "SELECT 
                c.category,
                rg.office as department,
                SUM(ri.quantity) as total_quantity,
                COUNT(DISTINCT rg.id) as request_count,
                COUNT(ri.id) as item_count,
                AVG(ri.quantity) as avg_per_request
              FROM request_items ri
              JOIN request_groups rg ON ri.group_id = rg.id
              JOIN consumables c ON ri.consumable_id = c.id
              WHERE rg.status = 'Approved'
                AND YEAR(rg.request_date) = :year";
    
    if ($category && $category !== 'all') {
        $query .= " AND c.category = :category";
    }
    
    if ($department && $department !== 'all') {
        $query .= " AND rg.office = :department";
    }
    
    $query .= " GROUP BY c.category, rg.office
                ORDER BY total_quantity DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':year', $year);
    
    if ($category && $category !== 'all') {
        $stmt->bindParam(':category', $category);
    }
    
    if ($department && $department !== 'all') {
        $stmt->bindParam(':department', $department);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get all distinct categories
function getAllCategories($db) {
    $query = "SELECT DISTINCT category FROM consumables WHERE category IS NOT NULL AND category != '' ORDER BY category";
    $stmt = $db->query($query);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get all distinct departments/offices
function getAllDepartments($db) {
    $query = "SELECT DISTINCT office FROM request_groups WHERE office IS NOT NULL AND office != '' ORDER BY office";
    $stmt = $db->query($query);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get category summary
function getCategorySummary($db) {
    $query = "SELECT 
                category,
                COUNT(*) as total_items,
                SUM(quantity) as total_stock,
                AVG(quantity) as avg_stock,
                SUM(CASE WHEN quantity <= 10 THEN 1 ELSE 0 END) as out_of_stock,
                SUM(CASE WHEN quantity > 0 AND quantity <= 10 THEN 1 ELSE 0 END) as low_stock,
                SUM(CASE WHEN quantity > 10 THEN 1 ELSE 0 END) as in_stock,
                COUNT(DISTINCT brand) as total_brands
              FROM consumables 
              WHERE category IS NOT NULL AND category != ''
              GROUP BY category
              ORDER BY category";
    $stmt = $db->query($query);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get success/error messages from session
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Get statistics with updated threshold logic
$stats_query = "SELECT 
                COUNT(*) as total_items,
                SUM(quantity) as total_units,
                COUNT(DISTINCT category) as total_categories
                FROM consumables";
$stmt = $db->prepare($stats_query);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculate stock status counts based on your thresholds
$critical_count = 0;
$low_count = 0;
$available_count = 0;

$all_items = $db->query("SELECT quantity, unit FROM consumables");
while($item = $all_items->fetch(PDO::FETCH_ASSOC)) {
    $unit = strtolower(trim($item['unit'] ?? ''));
    $qty = $item['quantity'];
    
    // Apply thresholds based on unit type
    if ($qty <= 0) {
        $critical_count++;
    } else {
        switch ($unit) {
            case 'pcs':
                if ($qty <= 30) $critical_count++;
                elseif ($qty <= 50) $low_count++;
                else $available_count++;
                break;
            case 'unit':
                if ($qty <= 10) $critical_count++;
                elseif ($qty <= 20) $low_count++;
                else $available_count++;
                break;
            case 'box':
            case 'ream':
                if ($qty <= 10) $critical_count++;
                elseif ($qty <= 20) $low_count++;
                else $available_count++;
                break;
            default:
                if ($qty <= 10) $critical_count++;
                elseif ($qty <= 20) $low_count++;
                else $available_count++;
        }
    }
}

// Add these to the stats array
$stats['critical_stock'] = $critical_count;
$stats['low_stock'] = $low_count;
$stats['available'] = $available_count;

// Get pending requests count
$pending_query = "SELECT COUNT(DISTINCT rg.id) as pending_requests 
                  FROM request_groups rg 
                  WHERE rg.status = 'Pending'";
$stmt = $db->prepare($pending_query);
$stmt->execute();
$pending_requests = $stmt->fetch(PDO::FETCH_ASSOC);

$page_title = "Consumables Management";
include '../includes/header.php';

// Check for critical stock alerts from session
$critical_alerts = $_SESSION['critical_stock_alerts'] ?? [];
$highlight_critical_id = $_GET['highlight_critical'] ?? null;
unset($_SESSION['critical_stock_alerts']);
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
:root {
    --consumable-primary: #0d6efd;
    --consumable-primary-dark: #0b5ed7;
    --consumable-primary-light: #e7f1ff;
    --consumable-secondary: #6c757d;
    --consumable-success: #198754;
    --consumable-success-light: #d1e7dd;
    --consumable-warning: #ffc107;
    --consumable-warning-light: #fff3cd;
    --consumable-danger: #dc3545;
    --consumable-danger-light: #f8d7da;
    --consumable-info: #0dcaf0;
    --consumable-info-light: #cff4fc;
    --consumable-soft-bg: #f8f9fa;
    --consumable-border: #dee2e6;
}

/* Page Header */
.page-header {
    background: linear-gradient(135deg, var(--consumable-primary) 0%, var(--consumable-primary-dark) 100%);
    border-radius: 24px;
    padding: 2rem 2.5rem;
    margin-bottom: 2rem;
    color: white;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(13, 110, 253, 0.2);
}

.page-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: rotate 20s linear infinite;
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.header-content {
    position: relative;
    z-index: 2;
}

.header-title {
    font-size: 2.2rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.header-subtitle {
    font-size: 1rem;
    opacity: 0.9;
    margin-bottom: 1.5rem;
}

.header-stats {
    display: flex;
    gap: 2rem;
    flex-wrap: wrap;
}

.header-stat-item {
    text-align: center;
    background: rgba(255,255,255,0.1);
    padding: 0.8rem 1.5rem;
    border-radius: 50px;
    backdrop-filter: blur(10px);
}

.header-stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    display: block;
    line-height: 1;
}

.header-stat-label {
    font-size: 0.8rem;
    opacity: 0.9;
}

.header-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
}

.header-actions .btn {
    background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.3);
    color: white;
    padding: 0.8rem 1.5rem;
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.header-actions .btn:hover {
    background: white;
    color: var(--consumable-primary);
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

/* Quick Navigation Buttons */
.quick-nav {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    flex-wrap: wrap;
}

.quick-nav-btn {
    padding: 1rem 2rem;
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.8rem;
    border: none;
    cursor: pointer;
}

.quick-nav-btn.request-history {
    background: white;
    color: var(--consumable-primary);
    box-shadow: 0 10px 30px rgba(13, 110, 253, 0.1);
}

.quick-nav-btn.consumption-reports {
    background: linear-gradient(135deg, var(--consumable-success) 0%, #157347 100%);
    color: white;
    box-shadow: 0 10px 30px rgba(25, 135, 84, 0.2);
}

.quick-nav-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 40px rgba(13, 110, 253, 0.2);
}

/* Back to Top Button */
.back-to-top {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--consumable-primary) 0%, var(--consumable-primary-dark) 100%);
    color: white;
    border: none;
    cursor: pointer;
    display: none;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    box-shadow: 0 10px 30px rgba(13, 110, 253, 0.3);
    transition: all 0.3s ease;
    z-index: 1000;
}

.back-to-top:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(13, 110, 253, 0.4);
}

.back-to-top.show {
    display: flex;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
    border: 1px solid var(--consumable-border);
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(13, 110, 253, 0.15);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.stat-icon.primary { background: linear-gradient(135deg, var(--consumable-primary) 0%, var(--consumable-primary-dark) 100%); }
.stat-icon.success { background: linear-gradient(135deg, #198754 0%, #157347 100%); }
.stat-icon.warning { background: linear-gradient(135deg, #ffc107 0%, #ffca2c 100%); }
.stat-icon.danger { background: linear-gradient(135deg, #dc3545 0%, #bb2d3b 100%); }
.stat-icon.info { background: linear-gradient(135deg, #0dcaf0 0%, #31d2f2 100%); }

.stat-content {
    flex: 1;
}

.stat-content h3 {
    font-size: 1.8rem;
    font-weight: 800;
    margin: 0;
    line-height: 1.2;
    color: #1F2937;
}

.stat-content p {
    margin: 0;
    color: #6B7280;
    font-size: 0.8rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-trend {
    font-size: 0.75rem;
    color: var(--consumable-primary);
    margin-top: 0.3rem;
}

/* Section Cards */
.section-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
    border: 1px solid var(--consumable-border);
    margin-bottom: 2rem;
    overflow: hidden;
}

.section-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--consumable-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.section-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--consumable-primary-dark);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.section-title i {
    color: var(--consumable-primary);
}

.section-badge {
    background: var(--consumable-primary-light);
    color: var(--consumable-primary-dark);
    padding: 0.3rem 1rem;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-left: 1rem;
}

/* Inventory Table */
.inventory-table {
    width: 100%;
    border-collapse: collapse;
}

.inventory-table thead th {
    background: var(--consumable-primary-light);
    color: var(--consumable-primary-dark);
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 1rem;
    border-bottom: 2px solid var(--consumable-primary);
}

.inventory-table tbody td {
    padding: 1rem;
    border-bottom: 1px solid var(--consumable-border);
    vertical-align: middle;
}

.inventory-table tbody tr {
    transition: all 0.3s ease;
}

.inventory-table tbody tr:hover {
    background: var(--consumable-primary-light);
}

/* Item Info */
.item-info {
    display: flex;
    flex-direction: column;
}

.item-name {
    font-weight: 700;
    color: #1F2937;
    margin-bottom: 0.2rem;
}

.item-brand {
    font-size: 0.7rem;
    color: #6B7280;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.item-brand i {
    color: var(--consumable-primary);
    font-size: 0.6rem;
}

/* Category Badge */
.category-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.3rem 1rem;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 600;
    background: #e9ecef;
    color: #495057;
}

.category-badge i {
    font-size: 0.7rem;
}

/* Stock Badge */
.stock-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-size: 0.85rem;
    font-weight: 600;
}

.stock-badge.available {
    background: var(--consumable-success-light);
    color: var(--consumable-success);
    border: 1px solid var(--consumable-success);
}

.stock-badge.low {
    background: var(--consumable-warning-light);
    color: #856404;
    border: 1px solid #ffc107;
}

.stock-badge.critical {
    background: var(--consumable-danger-light);
    color: var(--consumable-danger);
    border: 1px solid var(--consumable-danger);
}

.stock-badge i {
    font-size: 0.9rem;
}

/* Identification Code */
.id-code {
    font-family: monospace;
    font-size: 0.8rem;
    color: var(--consumable-primary-dark);
    background: var(--consumable-primary-light);
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
}

/* Action Button */
.refill-btn {
    background: var(--consumable-success);
    color: white;
    border: none;
    padding: 0.4rem 1rem;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.refill-btn:hover {
    background: #157347;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(25, 135, 84, 0.3);
}

.refill-btn i {
    margin-right: 0.3rem;
}

/* Request History Table */
.history-table {
    width: 100%;
    border-collapse: collapse;
}

.history-table thead th {
    background: #f8f9fa;
    color: #495057;
    font-weight: 600;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 1rem;
    border-bottom: 2px solid var(--consumable-border);
}

.history-table tbody td {
    padding: 1rem;
    border-bottom: 1px solid var(--consumable-border);
    vertical-align: middle;
}

.history-table tbody tr {
    transition: all 0.3s ease;
}

.history-table tbody tr:hover {
    background: #f8f9fa;
}

/* Group Code */
.group-code {
    font-family: monospace;
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--consumable-primary);
    background: var(--consumable-primary-light);
    padding: 0.3rem 0.8rem;
    border-radius: 50px;
    display: inline-block;
}

/* Recipient Avatar */
.recipient-avatar {
    width: 35px;
    height: 35px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--consumable-primary) 0%, var(--consumable-primary-dark) 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.9rem;
}

/* Items Collapse Button */
.items-toggle {
    background: none;
    border: 1px solid var(--consumable-border);
    color: var(--consumable-primary);
    padding: 0.3rem 0.8rem;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.items-toggle:hover {
    background: var(--consumable-primary-light);
    border-color: var(--consumable-primary);
}

/* Signatory Info */
.signatory-info {
    font-size: 0.8rem;
    color: #495057;
}

.signatory-info i {
    color: var(--consumable-success);
    margin-right: 0.3rem;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 0.3rem;
    justify-content: flex-end;
}

.action-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    transition: all 0.3s ease;
    cursor: pointer;
}

.action-btn.pdf { background: #f8d7da; color: #dc3545; }
.action-btn.check { background: #fff3cd; color: #856404; }
.action-btn.edit { background: #e7f1ff; color: #0d6efd; }

.action-btn:hover {
    transform: translateY(-2px);
    filter: brightness(0.9);
}

.action-btn.pdf:hover { background: #dc3545; color: white; }
.action-btn.check:hover { background: #ffc107; color: white; }
.action-btn.edit:hover { background: #0d6efd; color: white; }

/* Add this to your existing action-btn styles */
.action-btn.view { background: #cff4fc; color: #0dcaf0; }
.action-btn.view:hover { background: #0dcaf0; color: white; }

/* Status Badges */
.status-badge {
    padding: 0.3rem 1rem;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.status-badge.pending { background: #fff3cd; color: #856404; }
.status-badge.approved { background: #d1e7dd; color: #198754; }
.status-badge.rejected { background: #f8d7da; color: #dc3545; }
.status-badge.warning { background: #fff3cd; color: #856404; }

/* Modal Styling */
.modal-content {
    border-radius: 20px;
    border: none;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

.modal-header {
    border-radius: 20px 20px 0 0;
    padding: 1.5rem;
    border: none;
}

.modal-header.bg-primary { background: linear-gradient(135deg, var(--consumable-primary) 0%, var(--consumable-primary-dark) 100%) !important; }
.modal-header.bg-warning { background: linear-gradient(135deg, #ffc107 0%, #ffca2c 100%) !important; }
.modal-header.bg-success { background: linear-gradient(135deg, #198754 0%, #157347 100%) !important; }

.modal-header .modal-title {
    font-weight: 700;
    font-size: 1.3rem;
}

.modal-header .btn-close {
    opacity: 0.8;
    border-radius: 50%;
    padding: 0.5rem;
}

.modal-body {
    padding: 2rem;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--consumable-border);
}

.form-label {
    font-weight: 600;
    color: var(--consumable-primary-dark);
    font-size: 0.9rem;
    margin-bottom: 0.3rem;
}

.form-control, .form-select {
    border-radius: 12px;
    border: 2px solid var(--consumable-border);
    padding: 0.6rem 1rem;
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    border-color: var(--consumable-primary);
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
}

/* Item Row */
.item-row {
    background: #f8f9fa;
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    border-left: 4px solid var(--consumable-primary);
    position: relative;
}

.remove-item-btn {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
}

.add-item-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--consumable-primary);
    color: white;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.add-item-btn:hover {
    background: var(--consumable-primary-dark);
    transform: rotate(90deg);
}

/* Edit Button */
.edit-btn {
    background: var(--consumable-primary-light);
    color: var(--consumable-primary-dark);
    border: 1px solid var(--consumable-primary);
    padding: 0.4rem 0.8rem;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.edit-btn:hover {
    background: var(--consumable-primary);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(13, 110, 253, 0.3);
}

.edit-btn i {
    margin-right: 0.3rem;
}

/* Alert Styling */
.alert {
    border-radius: 16px;
    border: none;
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}

.alert-success {
    background: #d1e7dd;
    color: #0a5e3a;
    border-left: 4px solid #198754;
}

.alert-danger {
    background: #f8d7da;
    color: #9a1c2a;
    border-left: 4px solid #dc3545;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-state-icon {
    width: 120px;
    height: 120px;
    border-radius: 60px;
    background: var(--consumable-primary-light);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 2rem;
    font-size: 3rem;
    color: var(--consumable-primary);
}

/* Responsive */
@media (max-width: 768px) {
    .page-header {
        padding: 1.5rem;
    }
    
    .header-title {
        font-size: 1.8rem;
        flex-direction: column;
        align-items: flex-start;
    }
    
    .header-actions {
        margin-top: 1rem;
        justify-content: flex-start;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .quick-nav {
        flex-direction: column;
    }
    
    .quick-nav-btn {
        width: 100%;
        justify-content: center;
    }
}

/* Animation */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.stat-card, .section-card {
    animation: slideIn 0.5s ease-out forwards;
}

/* Custom CSS for Other Department */
#otherDepartmentField {
    transition: all 0.3s ease;
}

/* Report Cards */
.report-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 5px 20px rgba(0,0,0,0.03);
    border: 1px solid var(--consumable-border);
    transition: all 0.3s ease;
}

.report-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(13, 110, 253, 0.1);
}

.report-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: white;
    margin-bottom: 1rem;
}
</style>

<!-- Page Header -->
<div class="page-header">
    <div class="header-content">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <div class="header-title">
                    <i class="fas fa-boxes"></i>
                    <span>Consumables Management</span>
                </div>
                <p class="header-subtitle">
                    Track and manage office supplies, laboratory consumables, and inventory levels. Monitor stock status, process requests, and generate reports.
                </p>
                <div class="header-stats">
                    <div class="header-stat-item">
                        <span class="header-stat-number"><?php echo $stats['total_items']; ?></span>
                        <span class="header-stat-label">Total Items</span>
                    </div>
                    <div class="header-stat-item">
                        <span class="header-stat-number"><?php echo $stats['total_categories']; ?></span>
                        <span class="header-stat-label">Categories</span>
                    </div>
                    <div class="header-stat-item">
                        <span class="header-stat-number"><?php echo $pending_requests['pending_requests'] ?? 0; ?></span>
                        <span class="header-stat-label">Pending Requests</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="header-actions">
                    <button type="button" class="btn" data-bs-toggle="modal" data-bs-target="#requestItemsModal">
                        <i class="fas fa-shopping-cart"></i> Request
                    </button>
                    <button type="button" class="btn" data-bs-toggle="modal" data-bs-target="#addConsumableModal">
                        <i class="fas fa-plus-circle"></i> Add Item
                    </button>
                    <button type="button" class="btn" data-bs-toggle="modal" data-bs-target="#generateStockReportModal">
                        <i class="fas fa-file-download"></i> Report
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Navigation Buttons -->
<div class="quick-nav">
    <a href="#request-history" class="quick-nav-btn request-history" onclick="scrollToSection('request-history')">
        <i class="fas fa-history"></i>
        <span>View Request History</span>
    </a>
    <a href="#consumption-reports" class="quick-nav-btn consumption-reports" onclick="scrollToSection('consumption-reports')">
        <i class="fas fa-chart-line"></i>
        <span>View Consumption Reports</span>
    </a>
</div>

<!-- Alert Messages -->
<?php if ($success_message): ?>
<div class="alert alert-success alert-dismissible fade show flash-message" role="alert">
    <i class="fas fa-check-circle me-2"></i>
    <?php echo $success_message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error_message): ?>
<div class="alert alert-danger alert-dismissible fade show flash-message" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i>
    <?php echo $error_message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary">
            <i class="fas fa-box"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['total_items']; ?></h3>
            <p>Total Items</p>
            <div class="stat-trend">
                <i class="fas fa-layer-group"></i> <?php echo $stats['total_categories']; ?> categories
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon success">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['available'] ?? 0; ?></h3>
            <p>Available Stock</p>
            <div class="stat-trend">
                <i class="fas fa-arrow-up text-success"></i> Good supply
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon warning">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['low_stock'] ?? 0; ?></h3>
            <p>Low Stock</p>
            <div class="stat-trend">
                <i class="fas fa-clock"></i> Reorder soon
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon danger">
            <i class="fas fa-times-circle"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['critical_stock'] ?? 0; ?></h3>
            <p>Critical Stock</p>
            <div class="stat-trend">
                <i class="fas fa-exclamation-circle"></i> Urgent
            </div>
        </div>
    </div>
</div>

<!-- Current Inventory Section -->
<div class="section-card">
    <div class="section-header">
        <div class="section-title">
            <i class="fas fa-clipboard-list"></i>
            <span>Current Inventory</span>
            <span class="section-badge"><?php echo $stats['total_items']; ?> items</span>
        </div>
        <div class="d-flex gap-3 align-items-center">
            <!-- Search Bar -->
            <div class="d-flex align-items-center gap-2">
                <div class="input-group" style="width: 250px;">
                    <span class="input-group-text bg-white border-end-0" style="border-color: var(--consumable-border);">
                        <i class="fas fa-search text-muted"></i>
                    </span>
                    <input type="text" id="inventorySearch" class="form-control border-start-0 ps-0" 
                           placeholder="Search items..." style="border-color: var(--consumable-border);"
                           onkeyup="searchInventory()">
                </div>
            </div>
            <!-- Category Filter Dropdown -->
            <div class="d-flex align-items-center gap-2">
                <label class="text-muted small fw-bold mb-0">
                    <i class="fas fa-filter me-1"></i>Filter by Category:
                </label>
                <select id="categoryFilter" class="form-select form-select-sm" style="width: 200px;" onchange="filterInventoryTable()">
                    <option value="all">All Categories</option>
                    <?php
                    $cat_stmt = $db->query("SELECT DISTINCT category FROM consumables WHERE category IS NOT NULL AND category != '' ORDER BY category");
                    while($cat = $cat_stmt->fetch(PDO::FETCH_ASSOC)):
                    ?>
                    <option value="<?php echo htmlspecialchars($cat['category']); ?>"><?php echo htmlspecialchars($cat['category']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <!-- Stock Status Filter -->
            <div class="d-flex align-items-center gap-2">
                <label class="text-muted small fw-bold mb-0">
                    <i class="fas fa-tag me-1"></i>Stock Status:
                </label>
                <select id="stockStatusFilter" class="form-select form-select-sm" style="width: 150px;" onchange="filterInventoryTable()">
                    <option value="all">All Status</option>
                    <option value="available">Available</option>
                    <option value="low">Low Stock</option>
                    <option value="critical">Critical Stock</option>
                </select>
            </div>
            <!-- Clear Filters Button -->
            <button class="btn btn-sm btn-outline-secondary" onclick="clearFilters()" title="Clear all filters">
                <i class="fas fa-times"></i> Clear
            </button>
            <span class="text-muted small me-3">
                <i class="fas fa-info-circle me-1"></i>Click on stock badge to see details
            </span>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="inventory-table" id="consumablesTable">
            <thead>
                <tr>
                    <th>Item Description</th>
                    <th>Category</th>
                    <th>ID Code</th>
                    <th>Current Stock</th>
                    <th>Threshold</th>
                    <th>Status</th>
                    <th>Date Added</th> <!-- Add this column -->
                    <th>Actions</th>
                </tr>
            </thead>

            <!-- Update the table body to include threshold -->
            <tbody id="inventoryTableBody">
                <?php
                $stmt = $db->query("SELECT * FROM consumables ORDER BY 
                                    CASE 
                                        WHEN quantity <= 0 THEN 1
                                        WHEN quantity <= 10 THEN 2
                                        ELSE 3
                                    END, item_name ASC");
                if ($stmt->rowCount() == 0):
                ?>
                <!-- ... empty state ... -->
                <?php else: ?>
                <?php while($row = $stmt->fetch(PDO::FETCH_ASSOC)): 
                    $unit = strtolower(trim($row['unit'] ?? ''));
                    $qty = $row['quantity'];
                    
                    // Determine stock status based on your thresholds
                    if ($qty <= 0) {
                        $stockStatus = 'critical';
                        $stockClass = 'critical';
                        $stockIcon = 'fa-times-circle';
                    } else {
                        switch ($unit) {
                            case 'pcs':
                                if ($qty <= 30) {
                                    $stockStatus = 'critical';
                                    $stockClass = 'critical';
                                    $stockIcon = 'fa-times-circle';
                                } elseif ($qty <= 50) {
                                    $stockStatus = 'low';
                                    $stockClass = 'low';
                                    $stockIcon = 'fa-exclamation-triangle';
                                } else {
                                    $stockStatus = 'available';
                                    $stockClass = 'available';
                                    $stockIcon = 'fa-check-circle';
                                }
                                break;
                            case 'unit':
                                if ($qty <= 10) {
                                    $stockStatus = 'critical';
                                    $stockClass = 'critical';
                                    $stockIcon = 'fa-times-circle';
                                } elseif ($qty <= 20) {
                                    $stockStatus = 'low';
                                    $stockClass = 'low';
                                    $stockIcon = 'fa-exclamation-triangle';
                                } else {
                                    $stockStatus = 'available';
                                    $stockClass = 'available';
                                    $stockIcon = 'fa-check-circle';
                                }
                                break;
                            case 'box':
                            case 'ream':
                                if ($qty <= 10) {
                                    $stockStatus = 'critical';
                                    $stockClass = 'critical';
                                    $stockIcon = 'fa-times-circle';
                                } elseif ($qty <= 20) {
                                    $stockStatus = 'low';
                                    $stockClass = 'low';
                                    $stockIcon = 'fa-exclamation-triangle';
                                } else {
                                    $stockStatus = 'available';
                                    $stockClass = 'available';
                                    $stockIcon = 'fa-check-circle';
                                }
                                break;
                            default:
                                if ($qty <= 10) {
                                    $stockStatus = 'critical';
                                    $stockClass = 'critical';
                                    $stockIcon = 'fa-times-circle';
                                } elseif ($qty <= 20) {
                                    $stockStatus = 'low';
                                    $stockClass = 'low';
                                    $stockIcon = 'fa-exclamation-triangle';
                                } else {
                                    $stockStatus = 'available';
                                    $stockClass = 'available';
                                    $stockIcon = 'fa-check-circle';
                                }
                        }
                    }
                    
                    $category = htmlspecialchars($row['category'] ?: 'Uncategorized');
                    
                    // Calculate threshold information for display
                    $threshold_info = '';
                    if (!empty($row['max_stock']) && $row['max_stock'] > 0) {
                        $critical_at = round($row['max_stock'] * 0.2);
                        $low_at = round($row['max_stock'] * 0.4);
                        $threshold_info = "<span class='d-block small' title='20% of max stock'>Critical at ≤ {$critical_at}</span>";
                    } else {
                        // Show default thresholds
                        switch ($unit) {
                            case 'pcs':
                                $threshold_info = "<span class='d-block small'>Critical ≤30, Low 31-50</span>";
                                break;
                            case 'unit':
                                $threshold_info = "<span class='d-block small'>Critical ≤10, Low 11-20</span>";
                                break;
                            case 'box':
                            case 'ream':
                                $threshold_info = "<span class='d-block small'>Critical ≤10, Low 11-20</span>";
                                break;
                            default:
                                $threshold_info = "<span class='d-block small'>Critical ≤10, Low 11-20</span>";
                        }
                    }
                ?>
                <tr data-category="<?php echo $category; ?>" data-stock="<?php echo $stockStatus; ?>">
                    <td>
                        <div class="item-info">
                            <span class="item-name"><?php echo htmlspecialchars($row['item_name']); ?></span>
                            <span class="item-brand">
                                <i class="fas fa-tag"></i>
                                <?php echo htmlspecialchars($row['brand'] ?: 'Generic'); ?>
                            </span>
                        </div>
                    </td>
                    <td>
                        <span class="category-badge" data-category-value="<?php echo $category; ?>">
                            <i class="fas fa-folder"></i>
                            <?php echo $category; ?>
                        </span>
                    </td>
                    <td>
                        <code class="id-code"><?php echo htmlspecialchars($row['identification'] ?: '-'); ?></code>
                    </td>
                    <td>
                        <span class="stock-badge <?php echo $stockClass; ?>" 
                            data-bs-toggle="tooltip" 
                            title="<?php 
                                echo $stockStatus == 'critical' ? 'Critical stock - Refill immediately' : 
                                    ($stockStatus == 'low' ? 'Low stock - Reorder soon' : 'Good stock level'); 
                            ?>">
                            <i class="fas <?php echo $stockIcon; ?>"></i>
                            <span><?php echo $row['quantity']; ?> <?php echo htmlspecialchars($row['unit']); ?></span>
                        </span>
                        <?php if (!empty($row['max_stock']) && $row['max_stock'] > 0): ?>
                        <small class="d-block text-muted">Max: <?php echo $row['max_stock']; ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo $threshold_info; ?>
                    </td>
                    <td>
                        <span class="status-badge <?php echo $stockClass; ?>">
                            <i class="fas <?php echo $stockIcon; ?>"></i>
                            <?php echo ucfirst($stockStatus); ?>
                        </span>
                        <?php if ($stockStatus == 'critical'): ?>
                        <span class="d-block small text-danger fw-bold">Below threshold</span>
                        <?php elseif ($stockStatus == 'low'): ?>
                        <span class="d-block small text-warning fw-bold">Near threshold</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Date Added">
                        <?php 
                        if (!empty($row['created_at'])) {
                            echo date('M d, Y', strtotime($row['created_at']));
                        } else {
                            echo '—';
                        }
                        ?>
                    </td>
                    <td data-label="Actions">
                        <div class="d-flex gap-2 justify-content-end">
                            <!-- Refill Button - Now shown for ALL items regardless of status -->
                            <button class="refill-btn" data-bs-toggle="modal" data-bs-target="#refillModal<?php echo $row['id']; ?>">
                                <i class="fas fa-plus-circle me-1"></i>Refill
                            </button>
                            
                            <!-- View Details Button -->
                            <button type="button" class="btn btn-sm" 
                                    style="background: var(--consumable-info-light); color: var(--consumable-info); border: 1px solid var(--consumable-info);"
                                    onclick="viewConsumable(<?php echo $row['id']; ?>)"
                                    title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            
                            <!-- Edit Button -->
                            <button type="button" class="btn btn-sm" 
                                    style="background: var(--consumable-primary-light); color: var(--consumable-primary-dark); border: 1px solid var(--consumable-primary);"
                                    onclick="editConsumable(<?php echo $row['id']; ?>)" 
                                    title="Edit Item">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Request History Section -->
<div class="section-card" id="request-history">
    <div class="section-header">
        <div class="section-title">
            <i class="fas fa-history"></i>
            <span>Request History</span>
            <span class="section-badge"><?php echo $pending_requests['pending_requests'] ?? 0; ?> pending</span>
        </div>
        <a href="generate_release_report.php" target="_blank" class="btn btn-outline-danger btn-sm">
            <i class="fas fa-file-pdf me-2"></i>Release Report
        </a>
    </div>
    
    <div class="table-responsive">
        <table class="history-table" id="historyTable">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Recipient</th>
                    <th>Date</th>
                    <th>Items</th>
                    <th>Signatories</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $groups = $db->query("SELECT rg.*, 
                                      COUNT(ri.id) as item_count,
                                      SUM(CASE WHEN ri.status = 'Pending' THEN 1 ELSE 0 END) as pending_count
                                      FROM request_groups rg
                                      LEFT JOIN request_items ri ON rg.id = ri.group_id
                                      GROUP BY rg.id
                                      ORDER BY rg.created_at DESC");
                
                if ($groups->rowCount() == 0):
                ?>
                <tr>
                    <td colspan="7" class="text-center py-5">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-history"></i>
                            </div>
                            <h5 class="mb-2">No Request History</h5>
                            <p class="text-muted mb-3">Requests will appear here once created.</p>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#requestItemsModal">
                                <i class="fas fa-shopping-cart me-2"></i>Create New Request
                            </button>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php while($group = $groups->fetch(PDO::FETCH_ASSOC)): 
                    $statusClass = '';
                    $statusIcon = '';

                    switch($group['status']) {
                        case 'Approved':
                            $statusClass = 'approved';
                            $statusIcon = 'check-circle';
                            break;
                        case 'Rejected':
                            $statusClass = 'rejected';
                            $statusIcon = 'times-circle';
                            break;
                        case 'Partially Approved':
                            $statusClass = 'warning';
                            $statusIcon = 'exclamation-circle';
                            break;
                        default:
                            $statusClass = 'pending';
                            $statusIcon = 'clock';
                    }
                ?>
                <tr>
                    <td>
                        <span class="group-code"><?php echo $group['group_code']; ?></span>
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="recipient-avatar">
                                <?php echo strtoupper(substr($group['employee'], 0, 1)); ?>
                            </div>
                            <div>
                                <div class="fw-bold small"><?php echo htmlspecialchars($group['employee']); ?></div>
                                <div class="text-muted ultra-small"><?php echo htmlspecialchars($group['office']); ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="small">
                            <i class="far fa-calendar me-1 text-primary"></i>
                            <?php echo date('M d, Y', strtotime($group['request_date'])); ?>
                        </div>
                    </td>
                    <td>
                        <button class="items-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#items-<?php echo $group['id']; ?>">
                            <i class="fas fa-eye me-1"></i>View (<?php echo $group['item_count']; ?>)
                        </button>
                        <div class="collapse mt-2" id="items-<?php echo $group['id']; ?>">
                            <div class="bg-light p-3 rounded" style="max-width: 300px;">
                                <?php
                                $items = $db->prepare("SELECT ri.*, c.item_name, c.unit 
                                                    FROM request_items ri 
                                                    JOIN consumables c ON ri.consumable_id = c.id 
                                                    WHERE ri.group_id = ?");
                                $items->execute([$group['id']]);
                                while($item = $items->fetch(PDO::FETCH_ASSOC)):
                                    $itemStatusClass = $item['status'] == 'Approved' ? 'success' : ($item['status'] == 'Rejected' ? 'danger' : 'warning');
                                ?>
                                <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                    <div>
                                        <span class="fw-bold small"><?php echo htmlspecialchars($item['item_name']); ?></span>
                                        <span class="badge bg-<?php echo $itemStatusClass; ?> ms-2"><?php echo $item['status']; ?></span>
                                        <?php if($item['status'] == 'Rejected' && !empty($item['rejection_reason'])): ?>
                                            <small class="text-danger d-block mt-1">
                                                <i class="fas fa-exclamation-circle me-1"></i>
                                                <?php echo htmlspecialchars($item['rejection_reason']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-primary"><?php echo $item['quantity']; ?> <?php echo $item['unit']; ?></span>
                                        <?php if($item['status'] == 'Pending'): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Release this item?')">
                                            <input type="hidden" name="action" value="release_item">
                                            <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" class="btn btn-success btn-sm" title="Release Item">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="signatory-info">
                            <i class="fas fa-check-circle"></i>
                            <?php echo htmlspecialchars($group['supply_officer']); ?>
                        </div>
                    </td>
                    <td>
                        <span class="status-badge <?php echo $statusClass; ?>">
                            <i class="fas fa-<?php echo $statusIcon; ?>"></i>
                            <?php echo $group['status']; ?>
                        </span>
                    </td>
                    <td class="text-end">
                        <div class="action-buttons">
                            <button type="button" class="action-btn view view-request-btn" 
                                    data-group-id="<?php echo $group['id']; ?>"
                                    data-group-code="<?php echo $group['group_code']; ?>"
                                    title="View Request Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            
                            <!-- PDF Button - Only show for Approved requests -->
                            <?php if($group['status'] == 'Approved'): ?>
                            <a href="generate_release_report.php?group_id=<?php echo $group['id']; ?>" 
                            target="_blank" 
                            class="action-btn pdf" 
                            title="Print PDF Report">
                                <i class="fas fa-file-pdf"></i>
                            </a>
                            <?php endif; ?>

                            <?php if($group['status'] == 'Pending' || $group['status'] == 'Partially Approved'): ?>
                                <button type="button" class="action-btn check check-items-btn" 
                                        data-group-id="<?php echo $group['id']; ?>"
                                        data-group-code="<?php echo $group['group_code']; ?>"
                                        data-employee="<?php echo htmlspecialchars($group['employee']); ?>"
                                        data-office="<?php echo htmlspecialchars($group['office']); ?>"
                                        data-request-date="<?php echo date('M d, Y', strtotime($group['request_date'])); ?>"
                                        title="Check Items">
                                    <i class="fas fa-clipboard-check"></i>
                                </button>
                            <?php endif; ?>
                            
                            <button type="button" class="action-btn edit edit-group-btn" 
                                    data-group-id="<?php echo $group['id']; ?>"
                                    data-group-code="<?php echo $group['group_code']; ?>"
                                    data-employee="<?php echo htmlspecialchars($group['employee']); ?>"
                                    data-office="<?php echo htmlspecialchars($group['office']); ?>"
                                    data-approved-by="<?php echo htmlspecialchars($group['approved_by'] ?? 'REYNALDO H. CARANDANG JR.'); ?>"
                                    data-supply-officer="<?php echo htmlspecialchars($group['supply_officer'] ?? 'MARVIN Z. GERVACIO'); ?>"
                                    data-status="<?php echo $group['status']; ?>"
                                    title="Edit Group">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Summary Reports Section -->
<div class="section-card mt-4" id="consumption-reports">
    <div class="section-header">
        <div class="section-title">
            <i class="fas fa-chart-line"></i>
            <span>Consumption Reports</span>
        </div>
        <div>
            <span class="text-muted small me-3">
                <i class="fas fa-info-circle me-1"></i>Based on approved requests
            </span>
        </div>
    </div>
    
    <div class="p-4">
        <!-- Report Type Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="report-card text-center">
                    <div class="report-icon bg-primary mx-auto">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h6 class="fw-bold mt-3 mb-2">Monthly Consumption</h6>
                    <p class="small text-muted mb-3">View consumption patterns month by month</p>
                    <button class="btn btn-sm btn-outline-primary" onclick="showReportTab('monthly')">
                        <i class="fas fa-eye me-1"></i>View Report
                    </button>
                </div>
            </div>
            <div class="col-md-4">
                <div class="report-card text-center">
                    <div class="report-icon bg-success mx-auto">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <h6 class="fw-bold mt-3 mb-2">Annual Summary</h6>
                    <p class="small text-muted mb-3">Yearly consumption by category & department</p>
                    <button class="btn btn-sm btn-outline-success" onclick="showReportTab('annual')">
                        <i class="fas fa-eye me-1"></i>View Report
                    </button>
                </div>
            </div>
            <div class="col-md-4">
                <div class="report-card text-center">
                    <div class="report-icon bg-warning mx-auto">
                        <i class="fas fa-folder"></i>
                    </div>
                    <h6 class="fw-bold mt-3 mb-2">Category Report</h6>
                    <p class="small text-muted mb-3">Inventory status by category</p>
                    <button class="btn btn-sm btn-outline-warning" onclick="showReportTab('category')">
                        <i class="fas fa-eye me-1"></i>View Report
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Report Tabs -->
        <ul class="nav nav-tabs mb-4" id="reportTabs" role="tablist" style="display: none;">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="monthly-tab" data-bs-toggle="tab" data-bs-target="#monthly" type="button" role="tab">
                    Monthly Consumption
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="annual-tab" data-bs-toggle="tab" data-bs-target="#annual" type="button" role="tab">
                    Annual Summary
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="category-tab" data-bs-toggle="tab" data-bs-target="#category-report" type="button" role="tab">
                    Category Report
                </button>
            </li>
        </ul>
        
        <!-- Tab Content -->
        <div class="tab-content" id="reportTabsContent">
            <!-- Monthly Consumption Tab -->
            <div class="tab-pane fade" id="monthly" role="tabpanel">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Select Year</label>
                        <select class="form-select" id="monthlyYear" onchange="loadMonthlyReport()">
                            <?php for($y = date('Y'); $y >= 2024; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == date('Y') ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Filter by Month</label>
                        <select class="form-select" id="monthlyMonth" onchange="loadMonthlyReport()">
                            <option value="all">All Months</option>
                            <option value="1">January</option>
                            <option value="2">February</option>
                            <option value="3">March</option>
                            <option value="4">April</option>
                            <option value="5">May</option>
                            <option value="6">June</option>
                            <option value="7">July</option>
                            <option value="8">August</option>
                            <option value="9">September</option>
                            <option value="10">October</option>
                            <option value="11">November</option>
                            <option value="12">December</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Filter by Category</label>
                        <select class="form-select" id="monthlyCategory" onchange="loadMonthlyReport()">
                            <option value="all">All Categories</option>
                            <?php 
                            $categories = getAllCategories($db);
                            foreach($categories as $cat): 
                            ?>
                            <option value="<?php echo htmlspecialchars($cat['category']); ?>"><?php echo htmlspecialchars($cat['category']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Filter by Department</label>
                        <select class="form-select" id="monthlyDepartment" onchange="loadMonthlyReport()">
                            <option value="all">All Departments</option>
                            <?php 
                            $departments = getAllDepartments($db);
                            foreach($departments as $dept): 
                            ?>
                            <option value="<?php echo htmlspecialchars($dept['office']); ?>"><?php echo htmlspecialchars($dept['office']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-12">
                        <button class="btn btn-outline-success btn-sm" onclick="exportMonthlyReport()">
                            <i class="fas fa-file-excel me-1"></i>Export to Excel
                        </button>
                        <button class="btn btn-outline-danger btn-sm ms-2" onclick="exportMonthlyReportPDF()">
                            <i class="fas fa-file-pdf me-1"></i>Export to PDF
                        </button>
                    </div>
                </div>
                
                <div id="monthlyReportContainer" class="table-responsive">
                    <!-- Monthly report will be loaded here via AJAX -->
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary mb-3"></div>
                        <p class="text-muted">Loading monthly consumption data...</p>
                    </div>
                </div>
            </div>
            
            <!-- Annual Summary Tab -->
            <div class="tab-pane fade" id="annual" role="tabpanel">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Select Year</label>
                        <select class="form-select" id="annualYear" onchange="loadAnnualReport()">
                            <?php for($y = date('Y'); $y >= 2024; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == date('Y') ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Filter by Category</label>
                        <select class="form-select" id="annualCategory" onchange="loadAnnualReport()">
                            <option value="all">All Categories</option>
                            <?php foreach($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['category']); ?>"><?php echo htmlspecialchars($cat['category']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Filter by Department</label>
                        <select class="form-select" id="annualDepartment" onchange="loadAnnualReport()">
                            <option value="all">All Departments</option>
                            <?php foreach($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept['office']); ?>"><?php echo htmlspecialchars($dept['office']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-12">
                        <button class="btn btn-outline-success btn-sm" onclick="exportAnnualReport()">
                            <i class="fas fa-file-excel me-1"></i>Export to Excel
                        </button>
                        <button class="btn btn-outline-danger btn-sm ms-2" onclick="exportAnnualReportPDF()">
                            <i class="fas fa-file-pdf me-1"></i>Export to PDF
                        </button>
                    </div>
                </div>
                
                <div id="annualReportContainer" class="table-responsive">
                    <!-- Annual report will be loaded here via AJAX -->
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary mb-3"></div>
                        <p class="text-muted">Loading annual consumption data...</p>
                    </div>
                </div>
            </div>
            
            <!-- Category Report Tab -->
            <div class="tab-pane fade" id="category-report" role="tabpanel">
                <div class="row mb-3">
                    <div class="col-12">
                        <button class="btn btn-outline-success btn-sm" onclick="exportCategoryReport()">
                            <i class="fas fa-file-excel me-1"></i>Export to Excel
                        </button>
                        <button class="btn btn-outline-danger btn-sm ms-2" onclick="exportCategoryReportPDF()">
                            <i class="fas fa-file-pdf me-1"></i>Export to PDF
                        </button>
                    </div>
                </div>

                <!-- Category Report Charts -->
                <div class="row mb-4 g-3">
                    <div class="col-md-5">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white border-0 pt-3 pb-0">
                                <h6 class="fw-bold mb-0"><i class="fas fa-chart-pie text-warning me-2"></i>Stock Distribution by Category</h6>
                                <small class="text-muted">Total stock units per category</small>
                            </div>
                            <div class="card-body" style="position:relative;height:260px;">
                                <canvas id="categoryStockDoughnut"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white border-0 pt-3 pb-0">
                                <h6 class="fw-bold mb-0"><i class="fas fa-chart-bar text-danger me-2"></i>Stock Health by Category</h6>
                                <small class="text-muted">In-stock vs Low vs Out-of-stock items</small>
                            </div>
                            <div class="card-body" style="position:relative;height:260px;">
                                <canvas id="categoryHealthBar"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="categoryReportContainer" class="table-responsive">
                    <?php
                    $categorySummary = getCategorySummary($db);
                    ?>
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Category</th>
                                <th class="text-center">Total Items</th>
                                <th class="text-center">Total Stock</th>
                                <th class="text-center">Average Stock</th>
                                <th class="text-center">In Stock</th>
                                <th class="text-center">Low Stock</th>
                                <th class="text-center">Critical Stock</th>
                                <th class="text-center">Unique Brands</th>
                                <th class="text-center">Stock Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($categorySummary)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <i class="fas fa-folder-open fa-2x text-muted mb-2"></i>
                                    <p class="mb-0">No categories found</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php 
                            $grand_total_items = 0;
                            $grand_total_stock = 0;
                            foreach($categorySummary as $cat): 
                                $grand_total_items += $cat['total_items'];
                                $grand_total_stock += $cat['total_stock'];
                                $stockPercentage = ($cat['total_stock'] > 0) ? round(($cat['in_stock'] / $cat['total_items']) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($cat['category']); ?></strong></td>
                                <td class="text-center"><?php echo $cat['total_items']; ?></td>
                                <td class="text-center"><?php echo $cat['total_stock']; ?></td>
                                <td class="text-center"><?php echo round($cat['avg_stock'], 1); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-success"><?php echo $cat['in_stock']; ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-warning text-dark"><?php echo $cat['low_stock']; ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-danger"><?php echo $cat['out_of_stock']; ?></span>
                                </td>
                                <td class="text-center"><?php echo $cat['total_brands']; ?></td>
                                <td class="text-center">
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-success" style="width: <?php echo $stockPercentage; ?>%"></div>
                                        <div class="progress-bar bg-warning" style="width: <?php echo ($cat['low_stock'] / $cat['total_items']) * 100; ?>%"></div>
                                        <div class="progress-bar bg-danger" style="width: <?php echo ($cat['out_of_stock'] / $cat['total_items']) * 100; ?>%"></div>
                                    </div>
                                    <small class="text-muted"><?php echo $stockPercentage; ?>% healthy</small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="table-secondary fw-bold">
                                <td>TOTAL</td>
                                <td class="text-center"><?php echo $grand_total_items; ?></td>
                                <td class="text-center"><?php echo $grand_total_stock; ?></td>
                                <td class="text-center">-</td>
                                <td colspan="5"></td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Category Items Breakdown -->
                <div class="mt-4">
                    <h6 class="fw-bold mb-3">
                        <i class="fas fa-boxes me-2"></i>Items per Category
                    </h6>
                    <div class="row">
                        <?php foreach($categorySummary as $cat): ?>
                        <div class="col-md-4 mb-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <h6 class="card-title fw-bold"><?php echo htmlspecialchars($cat['category']); ?></h6>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="small">Total Items:</span>
                                        <span class="fw-bold"><?php echo $cat['total_items']; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="small">Total Stock:</span>
                                        <span class="fw-bold"><?php echo $cat['total_stock']; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="small">Unique Brands:</span>
                                        <span class="fw-bold"><?php echo $cat['total_brands']; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Request Details Modal -->
<div class="modal fade" id="viewRequestModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-eye me-2"></i>
                    Request Details: <span id="view-modal-group-code"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Recipient Info -->
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <h6 class="text-info border-bottom pb-2">
                            <i class="fas fa-user me-2"></i>Recipient Information
                        </h6>
                    </div>
                    <div class="col-md-6">
                        <div class="bg-light p-3 rounded">
                            <small class="text-muted d-block">Employee Name</small>
                            <strong id="view-employee" class="fs-5"></strong>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="bg-light p-3 rounded">
                            <small class="text-muted d-block">Office/Department</small>
                            <strong id="view-office" class="fs-5"></strong>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="bg-light p-3 rounded">
                            <small class="text-muted d-block">Campus</small>
                            <strong id="view-campus"></strong>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="bg-light p-3 rounded">
                            <small class="text-muted d-block">Request Date</small>
                            <strong id="view-request-date"></strong>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="bg-light p-3 rounded">
                            <small class="text-muted d-block">Status</small>
                            <span id="view-status" class="badge fs-6"></span>
                        </div>
                    </div>
                </div>
                
                <!-- Items Section -->
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <h6 class="text-success border-bottom pb-2">
                            <i class="fas fa-boxes me-2"></i>Requested Items
                        </h6>
                    </div>
                    <div class="col-12">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th width="40%">Item</th>
                                        <th width="15%">Quantity</th>
                                        <th width="25%">Purpose</th>
                                        <th width="20%">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="view-items-body">
                                    <tr>
                                        <td colspan="4" class="text-center py-4">
                                            <div class="spinner-border text-info" role="status"></div>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="table-secondary">
                                    <tr>
                                        <td colspan="2" class="text-end"><strong>Total Items:</strong></td>
                                        <td><strong id="view-total-items">0</strong></td>
                                        <td><strong>Total Qty: <span id="view-total-quantity">0</span></strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Signatories Section -->
                <div class="row g-3">
                    <div class="col-12">
                        <h6 class="text-warning border-bottom pb-2">
                            <i class="fas fa-signature me-2"></i>Signatories
                        </h6>
                    </div>
                    <div class="col-md-4">
                        <div class="bg-light p-3 rounded">
                            <small class="text-muted d-block">Requested By</small>
                            <strong id="view-requested-by"></strong>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="bg-light p-3 rounded">
                            <small class="text-muted d-block">Approved By</small>
                            <strong id="view-approved-by"></strong>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="bg-light p-3 rounded">
                            <small class="text-muted d-block">Supply Officer</small>
                            <strong id="view-supply-officer"></strong>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Close
                </button>
                <!-- Download PDF button removed -->
            </div>
        </div>
    </div>
</div>

<!-- View Consumable Details Modal -->
<div class="modal fade" id="viewConsumableModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-eye me-2"></i>
                    Consumable Item Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4">
                    <!-- Item Information Card -->
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="card-title text-info border-bottom pb-2 mb-3">
                                    <i class="fas fa-box me-2"></i>Item Information
                                </h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="text-muted small">Item Name</label>
                                        <div class="fw-bold fs-5" id="view_item_name"></div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="text-muted small">Category</label>
                                        <div class="fw-bold" id="view_category"></div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="text-muted small">Brand</label>
                                        <div class="fw-bold" id="view_brand"></div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="text-muted small">Unit</label>
                                        <div class="fw-bold" id="view_unit"></div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="text-muted small">ID Code</label>
                                        <div><code class="id-code" id="view_identification"></code></div>
                                    </div>
                                    <!-- Add Date Added field -->
                                    <div class="col-md-6 mb-3">
                                        <label class="text-muted small">Date Added</label>
                                        <div class="fw-bold" id="view_date_added"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Stock Status Card -->
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <h6 class="card-title text-success border-bottom pb-2 mb-3">
                                    <i class="fas fa-chart-line me-2"></i>Stock Status
                                </h6>
                                <div class="text-center mb-3">
                                    <div class="display-4 fw-bold" id="view_quantity">0</div>
                                    <small class="text-muted" id="view_unit_small"></small>
                                </div>
                                <div class="d-flex justify-content-center">
                                    <span class="status-badge" id="view_status_badge"></span>
                                </div>
                                <div class="mt-3 small text-center" id="view_status_description"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Threshold Information Card -->
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <h6 class="card-title text-warning border-bottom pb-2 mb-3">
                                    <i class="fas fa-info-circle me-2"></i>Threshold Information
                                </h6>
                                <div class="row g-2 text-center">
                                    <div class="col-4">
                                        <div class="p-2 bg-danger bg-opacity-10 rounded">
                                            <span class="badge bg-danger mb-1">Critical</span>
                                            <div class="small fw-bold" id="view_critical_threshold"></div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="p-2 bg-warning bg-opacity-10 rounded">
                                            <span class="badge bg-warning text-dark mb-1">Low</span>
                                            <div class="small fw-bold" id="view_low_threshold"></div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="p-2 bg-success bg-opacity-10 rounded">
                                            <span class="badge bg-success mb-1">Available</span>
                                            <div class="small fw-bold" id="view_available_threshold"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <div class="progress" style="height: 10px;">
                                        <div class="progress-bar bg-danger" id="view_critical_bar" style="width: 0%"></div>
                                        <div class="progress-bar bg-warning" id="view_low_bar" style="width: 0%"></div>
                                        <div class="progress-bar bg-success" id="view_available_bar" style="width: 0%"></div>
                                    </div>
                                </div>
                                <div class="mt-3 small text-center" id="view_max_stock_info"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Refill History Card -->
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="card-title text-secondary border-bottom pb-2 mb-3">
                                    <i class="fas fa-history me-2"></i>Recent History
                                </h6>
                                <div id="view_refill_history" class="table-responsive">
                                    <table class="table table-sm">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Date</th>
                                                <th>Action</th>
                                                <th>Change</th>
                                                <th>Previous</th>
                                                <th>New Total</th>
                                                <th>By</th>
                                            </tr>
                                        </thead>
                                        <tbody id="view_refill_history_body">
                                            <tr>
                                                <td colspan="6" class="text-center py-3">
                                                    <div class="spinner-border spinner-border-sm text-info"></div>
                                                    Loading...
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-end mt-2">
                                    <a href="#" id="view_full_history_link" class="small">View full history →</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Consumable Modal -->
<div class="modal fade" id="editConsumableModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--consumable-primary) 0%, var(--consumable-primary-dark) 100%);">
                <h5 class="modal-title text-white">
                    <i class="fas fa-edit me-2"></i>
                    Edit Consumable Item
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editConsumableForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="edit_consumable">
                    <input type="hidden" name="item_id" id="edit_item_id">
                    
                    <div class="alert alert-info mb-4">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> Quantity, Max Stock, and Unit cannot be edited here. 
                        Please use the Refill button to adjust stock levels.
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Item Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="item_name" id="edit_item_name" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <input type="text" class="form-control" name="category" id="edit_category" placeholder="e.g., Office Supplies">
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" class="form-control bg-light" name="quantity" id="edit_quantity" readonly>
                            <small class="text-muted">Read-only - Use Refill to adjust</small>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Max Stock</label>
                            <input type="number" class="form-control bg-light" name="max_stock" id="edit_max_stock" readonly>
                            <small class="text-muted">Read-only</small>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Unit</label>
                            <input type="text" class="form-control bg-light" name="unit" id="edit_unit" readonly>
                            <small class="text-muted">Read-only</small>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Brand</label>
                            <input type="text" class="form-control" name="brand" id="edit_brand" placeholder="Brand name">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">ID Code</label>
                            <div class="input-group">
                                <input type="text" class="form-control bg-light" name="identification" id="edit_identification" readonly>
                                <button type="button" class="btn btn-outline-secondary" onclick="regenerateEditIdCode()" title="Generate New Code">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                            <small class="text-muted">Unique identifier (read-only, click refresh to change)</small>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="alert alert-warning mb-0">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Current Status:</strong> <span id="edit_status_display"></span>
                                <br><small>Status is automatically calculated based on quantity and thresholds</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <div>
                        <!-- Delete Button -->
                        <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                            <i class="fas fa-trash-alt me-2"></i>Delete Item
                        </button>
                    </div>
                    <div>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-primary px-4" style="background: linear-gradient(135deg, var(--consumable-primary) 0%, var(--consumable-primary-dark) 100%); border: none;">
                            <i class="fas fa-save me-2"></i>Update Item
                        </button>
                    </div>
                </div>
            </form>
            
            <!-- Hidden form for delete action (outside the main form) -->
            <form method="POST" id="deleteConsumableForm" style="display: none;">
                <input type="hidden" name="action" value="delete_consumable">
                <input type="hidden" name="item_id" id="delete_item_id">
            </form>
        </div>
    </div>
</div>

<!-- Refill Modals -->
<?php
$stmt = $db->query("SELECT * FROM consumables ORDER BY item_name ASC");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)): 
    // Calculate threshold based on unit type
    $unit = strtolower(trim($row['unit'] ?? ''));
    $critical_threshold = 0;
    $low_threshold = 0;
    
    // Set thresholds based on unit type
    switch ($unit) {
        case 'pcs':
            $critical_threshold = 20; // Critical at ≤20 pcs
            $low_threshold = 50;       // Low at ≤50 pcs
            break;
        case 'unit':
            $critical_threshold = 10;  // Critical at ≤10 units
            $low_threshold = 20;        // Low at ≤20 units
            break;
        case 'box':
        case 'ream':
            $critical_threshold = 20;  // Critical at ≤20 boxes/reams
            $low_threshold = 30;        // Low at ≤30 boxes/reams
            break;
        default:
            $critical_threshold = 10;   // Default critical at ≤10
            $low_threshold = 20;         // Default low at ≤20
    }
    
    // Get current status based on thresholds
    $current_status = '';
    if ($row['quantity'] <= 0) {
        $current_status = 'Out of Stock';
        $status_class = 'danger';
    } elseif ($row['quantity'] <= $critical_threshold) {
        $current_status = 'Critical';
        $status_class = 'danger';
    } elseif ($row['quantity'] <= $low_threshold) {
        $current_status = 'Low';
        $status_class = 'warning';
    } else {
        $current_status = 'Available';
        $status_class = 'success';
    }
?>
<div class="modal fade" id="refillModal<?php echo $row['id']; ?>" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-plus-circle me-2"></i>
                    Refill Stock: <?php echo htmlspecialchars($row['item_name']); ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="refill_item">
                    <input type="hidden" name="item_id" value="<?php echo $row['id']; ?>">
                    
                    <!-- Current Stock Status Card -->
                    <div class="card mb-4 border-0 shadow-sm">
                        <div class="card-body">
                            <h6 class="card-title text-muted mb-3">Current Stock Status</h6>
                            
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="text-center p-2 bg-light rounded">
                                        <small class="text-muted d-block">Current Stock</small>
                                        <span class="fw-bold fs-4"><?php echo $row['quantity']; ?></span>
                                        <small class="text-muted"><?php echo $row['unit']; ?></small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center p-2 bg-light rounded">
                                        <small class="text-muted d-block">Status</small>
                                        <span class="badge bg-<?php echo $status_class; ?> p-2">
                                            <?php echo $current_status; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Threshold Information Card -->
                    <div class="card mb-4 border-0 bg-light">
                        <div class="card-body">
                            <h6 class="card-title text-muted mb-3">
                                <i class="fas fa-info-circle me-2"></i>
                                Stock Thresholds (<?php echo strtoupper($row['unit']); ?>)
                            </h6>
                            
                            <div class="row g-2">
                                <div class="col-4">
                                    <div class="text-center">
                                        <span class="badge bg-danger mb-1">Critical</span>
                                        <div class="small fw-bold">≤ <?php echo $critical_threshold; ?> <?php echo $row['unit']; ?></div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="text-center">
                                        <span class="badge bg-warning text-dark mb-1">Low</span>
                                        <div class="small fw-bold"><?php echo $critical_threshold + 1; ?> - <?php echo $low_threshold; ?> <?php echo $row['unit']; ?></div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="text-center">
                                        <span class="badge bg-success mb-1">Available</span>
                                        <div class="small fw-bold">> <?php echo $low_threshold; ?> <?php echo $row['unit']; ?></div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Progress Bar -->
                            <?php if (!empty($row['max_stock']) && $row['max_stock'] > 0): ?>
                            <div class="mt-3">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span>Current: <?php echo $row['quantity']; ?> <?php echo $row['unit']; ?></span>
                                    <span>Max: <?php echo $row['max_stock']; ?> <?php echo $row['unit']; ?></span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <?php 
                                    $percentage = min(100, round(($row['quantity'] / $row['max_stock']) * 100));
                                    $progress_class = 'bg-success';
                                    if ($row['quantity'] <= $critical_threshold) {
                                        $progress_class = 'bg-danger';
                                    } elseif ($row['quantity'] <= $low_threshold) {
                                        $progress_class = 'bg-warning';
                                    }
                                    ?>
                                    <div class="progress-bar <?php echo $progress_class; ?>" 
                                         style="width: <?php echo $percentage; ?>%"></div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Refill Quantity -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">Quantity to Add <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" name="refill_quantity" class="form-control form-control-lg text-center" 
                                   placeholder="0" required min="1" value="1"
                                   onchange="updateNewTotal(this, <?php echo $row['quantity']; ?>, '<?php echo $row['unit']; ?>', <?php echo $critical_threshold; ?>, <?php echo $low_threshold; ?>)">
                            <span class="input-group-text"><?php echo $row['unit']; ?></span>
                        </div>
                    </div>
                    
                    <!-- New Total Preview -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">New Total After Refill</label>
                        <div class="bg-light p-3 rounded text-center">
                            <span id="newTotal_<?php echo $row['id']; ?>" class="fs-4 fw-bold text-success">
                                <?php echo $row['quantity'] + 1; ?> <?php echo $row['unit']; ?>
                            </span>
                            <div id="newStatus_<?php echo $row['id']; ?>" class="small mt-1">
                                <?php 
                                $new_qty = $row['quantity'] + 1;
                                if ($new_qty <= $critical_threshold) {
                                    echo '<span class="badge bg-danger">Will be Critical</span>';
                                } elseif ($new_qty <= $low_threshold) {
                                    echo '<span class="badge bg-warning text-dark">Will be Low</span>';
                                } else {
                                    echo '<span class="badge bg-success">Will be Available</span>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Refill Date (Auto-filled as today) -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">Refill Date</label>
                        <input type="text" class="form-control" value="<?php echo date('F d, Y'); ?>" readonly disabled>
                        <small class="text-muted">Date will be automatically recorded</small>
                    </div>
                    
                    <!-- Remarks/Notes -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">Remarks / Notes</label>
                        <textarea name="refill_remarks" class="form-control" rows="2" 
                                  placeholder="e.g., Received new shipment, Additional stock from supplier, etc."></textarea>
                    </div>
                    
                    <!-- Recent Refill History (Last 3 refills) -->
                    <?php
                    // Get recent refill history for this item
                    $history_stmt = $db->prepare("SELECT cr.*, u.full_name 
                                                  FROM consumable_refills cr
                                                  LEFT JOIN users u ON cr.refilled_by = u.id
                                                  WHERE cr.consumable_id = ?
                                                  ORDER BY cr.refill_date DESC
                                                  LIMIT 3");
                    $history_stmt->execute([$row['id']]);
                    $refill_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($refill_history)):
                    ?>
                    <div class="mt-4">
                        <label class="form-label fw-bold">Recent Refill History</label>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Qty Added</th>
                                        <th>Previous</th>
                                        <th>New Total</th>
                                        <th>Refilled By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($refill_history as $history): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($history['refill_date'])); ?></td>
                                        <td class="text-success fw-bold">+<?php echo $history['refill_quantity']; ?></td>
                                        <td><?php echo $history['previous_quantity']; ?></td>
                                        <td><?php echo $history['new_quantity']; ?></td>
                                        <td><?php echo $history['full_name'] ?? 'Unknown'; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <a href="consumable_refill_history.php?id=<?php echo $row['id']; ?>" class="small" target="_blank">View full history →</a>
                    </div>
                    <?php endif; ?>
                    
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check-circle me-2"></i>Confirm Refill
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Update new total preview when quantity changes
function updateNewTotal(input, currentQty, unit, criticalThreshold, lowThreshold) {
    const addQty = parseInt(input.value) || 0;
    const newQty = currentQty + addQty;
    
    // Update total display
    document.getElementById('newTotal_<?php echo $row['id']; ?>').innerHTML = newQty + ' ' + unit;
    
    // Update status preview
    const statusDiv = document.getElementById('newStatus_<?php echo $row['id']; ?>');
    if (newQty <= criticalThreshold) {
        statusDiv.innerHTML = '<span class="badge bg-danger">Will be Critical</span>';
    } else if (newQty <= lowThreshold) {
        statusDiv.innerHTML = '<span class="badge bg-warning text-dark">Will be Low</span>';
    } else {
        statusDiv.innerHTML = '<span class="badge bg-success">Will be Available</span>';
    }
}
</script>

<?php endwhile; ?>

<!-- Add Consumable Modal -->
<div class="modal fade" id="addConsumableModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-plus-circle me-2"></i>
                    Add New Consumable Items
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addConsumableForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="add_consumable">
                    
                    <div class="alert alert-info border-0 mb-4">
                        <i class="fas fa-info-circle me-2"></i>
                        You can add multiple items at once. Each item will have a unique identification code.
                    </div>
                    
                    <div id="consumable-items-container">
                        <div class="consumable-item-row" id="consumable-row-1">
                            <div class="row g-3 align-items-end mb-3 pb-3 border-bottom">
                                <div class="col-md-4">
                                    <label class="small fw-bold">Item Name <span class="text-danger">*</span></label>
                                    <input type="text" name="item_name[]" class="form-control" placeholder="e.g. A4 Bond Paper" required onchange="updateIdCodeFromName(1)">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold">Category</label>
                                    <input type="text" name="category[]" class="form-control" placeholder="e.g. Office Supplies">
                                </div>
                                <div class="col-md-2">
                                    <label class="small fw-bold">Current Qty <span class="text-danger">*</span></label>
                                    <input type="number" name="quantity[]" class="form-control" placeholder="0" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="small fw-bold">Max Stock</label>
                                    <input type="number" name="max_stock[]" class="form-control" placeholder="e.g. 100" 
                                           title="Maximum stock level - critical threshold will be 20% of this">
                                </div>
                                <div class="col-md-2">
                                    <label class="small fw-bold">Unit</label>
                                    <input type="text" name="unit[]" class="form-control" placeholder="Reams, Pcs">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold">Brand</label>
                                    <input type="text" name="brand[]" class="form-control" placeholder="Brand name">
                                </div>
                                <div class="col-md-4">
                                    <label class="small fw-bold">ID Code <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="text" name="identification[]" class="form-control bg-light" id="idcode-1" readonly placeholder="Auto-generated" required>
                                        <button type="button" class="btn btn-outline-secondary" onclick="regenerateIdCode(1)" title="Generate New Code">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted">Auto-generated unique code</small>
                                </div>
                                <div class="col-md-2 text-end">
                                    <button type="button" class="btn btn-outline-danger remove-consumable-btn" onclick="removeConsumableRow(1)" style="display: none;">
                                        <i class="fas fa-trash-alt me-1"></i> Remove
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-3">
                        <button type="button" class="btn btn-outline-primary" onclick="addConsumableRow()">
                            <i class="fas fa-plus-circle me-2"></i>Add Another Item
                        </button>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-5">
                        <i class="fas fa-save me-2"></i>Save All Items
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Request Items Modal -->
<div class="modal fade" id="requestItemsModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="fas fa-shopping-cart me-2"></i>
                    Request Multiple Items
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="multiRequestForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="request_items">
                    
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-2">
                                <i class="fas fa-user me-2"></i>Recipient Details
                            </h6>
                        </div>
                        <!-- Last Name, First Name, Middle Initial fields -->
                        <div class="col-md-4">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control" placeholder="e.g. Dela Cruz" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" placeholder="e.g. Juan" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Middle Initial</label>
                            <input type="text" name="middle_initial" class="form-control" placeholder="e.g. M" maxlength="2">
                        </div>
                        
                        <!-- Campus Dropdown -->
                        <div class="col-md-6">
                            <label class="form-label">Campus <span class="text-danger">*</span></label>
                            <select name="campus" id="campusSelect" class="form-select" required>
                                <option value="">-- Select Campus --</option>
                                <option value="South Campus">South Campus</option>
                                <option value="Congressional Campus">Congressional Campus</option>
                                <option value="Camarin Campus">Camarin Campus</option>
                                <option value="Bagong Silang Campus">Bagong Silang Campus</option>
                            </select>
                            <small class="text-muted">
                                <i class="fas fa-map-marker-alt me-1"></i>
                                Select the campus where the request is from
                            </small>
                        </div>
                        
                        <!-- Office/Department Dropdown with dynamic data -->
                        <div class="col-md-6">
                            <label class="form-label">Office/Department <span class="text-danger">*</span></label>
                            <select name="office" id="officeSelect" class="form-select" required onchange="toggleOtherDepartment()">
                                <option value="">-- Select Department --</option>
                                <?php
                                // Get all active departments from the database
                                $dept_query = "SELECT department_name FROM departments WHERE is_active = 1 ORDER BY department_name ASC";
                                $dept_stmt = $db->prepare($dept_query);
                                $dept_stmt->execute();
                                $departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($departments as $dept):
                                ?>
                                <option value="<?php echo htmlspecialchars($dept['department_name']); ?>">
                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                </option>
                                <?php endforeach; ?>
                                <option value="others">Others (Please specify)</option>
                            </select>
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Can't find your department? <a href="settings.php?tab=departments" target="_blank">Add new department</a>
                            </small>
                        </div>
                        
                        <!-- Other Department Input (hidden by default) -->
                        <div class="col-md-6" id="otherDepartmentField" style="display: none;">
                            <label class="form-label">Specify Department <span class="text-danger">*</span></label>
                            <input type="text" name="other_office" id="otherOffice" class="form-control" placeholder="Enter department name">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Request Date <span class="text-danger">*</span></label>
                            <input type="date" name="request_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    
                    <!-- Items Requested section -->
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <h6 class="text-success border-bottom pb-2">
                                <i class="fas fa-boxes me-2"></i>Items Requested
                            </h6>
                        </div>
                        <div class="col-12">
                            <div id="items-container">
                                <div class="item-row" id="item-row-1">
                                    <button type="button" class="btn btn-outline-danger btn-sm remove-item-btn" onclick="removeItemRow(1)" style="display: none;">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <div class="row g-3">
                                        <div class="col-md-12">
                                            <label class="small fw-bold">Search Item</label>
                                            <input type="text" class="form-control item-search" placeholder="Type to filter items..." onkeyup="filterItems(this)">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="small fw-bold">Select Item <span class="text-danger">*</span></label>
                                            <select name="item_id[]" class="form-select item-select" required onchange="updateStockHint(this)">
                                                <option value="">-- Choose Item --</option>
                                                <?php
                                                // Modified query to include stock status and only show items that are NOT Critical
                                                // But we still need to check based on thresholds
                                                $items = $db->query("SELECT * FROM consumables ORDER BY item_name ASC");
                                                while($item = $items->fetch(PDO::FETCH_ASSOC)):
                                                    $unit = strtolower(trim($item['unit'] ?? ''));
                                                    $qty = $item['quantity'];
                                                    
                                                    // Determine if item is Critical based on thresholds
                                                    $is_critical = false;
                                                    if ($qty <= 0) {
                                                        $is_critical = true;
                                                    } else {
                                                        switch ($unit) {
                                                            case 'pcs':
                                                                if ($qty <= 30) $is_critical = true;
                                                                break;
                                                            case 'unit':
                                                                if ($qty <= 10) $is_critical = true;
                                                                break;
                                                            case 'box':
                                                            case 'ream':
                                                                if ($qty <= 10) $is_critical = true;
                                                                break;
                                                            default:
                                                                if ($qty <= 10) $is_critical = true;
                                                        }
                                                    }
                                                    
                                                    // Determine status class for styling
                                                    $status_class = '';
                                                    $status_text = '';
                                                    if ($is_critical) {
                                                        $status_class = 'text-danger fw-bold';
                                                        $status_text = ' (CRITICAL - Cannot request)';
                                                    } elseif ($qty <= 20 && ($unit == 'unit' || $unit == 'box' || $unit == 'ream')) {
                                                        $status_class = 'text-warning fw-bold';
                                                        $status_text = ' (LOW STOCK)';
                                                    } elseif ($qty <= 50 && $unit == 'pcs') {
                                                        $status_class = 'text-warning fw-bold';
                                                        $status_text = ' (LOW STOCK)';
                                                    }
                                                ?>
                                                <option value="<?php echo $item['id']; ?>" 
                                                        data-max="<?php echo $item['quantity']; ?>" 
                                                        data-unit="<?php echo $item['unit']; ?>"
                                                        data-critical="<?php echo $is_critical ? '1' : '0'; ?>"
                                                        <?php echo $is_critical ? 'disabled class="text-danger"' : ''; ?>>
                                                    <?php echo htmlspecialchars($item['item_name']); ?> 
                                                    (Available: <?php echo $item['quantity']; ?> <?php echo $item['unit']; ?>)
                                                    <?php echo $status_text; ?>
                                                </option>
                                                <?php endwhile; ?>
                                            </select>
                                            <small class="text-muted" id="stock-status-hint-1"></small>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="small fw-bold">Quantity <span class="text-danger">*</span></label>
                                            <input type="number" name="req_quantity[]" class="form-control quantity-input" min="1" required onchange="validateQuantity(this)">
                                            <small class="text-muted stock-hint"></small>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="small fw-bold">Purpose</label>
                                            <input type="text" name="description[]" class="form-control" placeholder="e.g. Office use">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center mt-3">
                                <button type="button" class="btn btn-outline-primary" onclick="addItemRow()">
                                    <i class="fas fa-plus-circle me-2"></i>Add Another Item
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Approvals & Signatories section -->
                    <div class="row g-3">
                        <div class="col-12">
                            <h6 class="text-info border-bottom pb-2">
                                <i class="fas fa-signature me-2"></i>Approvals & Signatories
                            </h6>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Approved By</label>
                            <input type="text" name="approved_by" class="form-control" value="REYNALDO H. CARANDANG JR.">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Supply Officer</label>
                            <input type="text" name="supply_officer" class="form-control" value="MARVIN Z. GERVACIO">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning fw-bold px-5" onclick="return validateRequestForm()">
                        <i class="fas fa-paper-plane me-2"></i>Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Check Items Modal -->
<div class="modal fade" id="checkItemsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="fas fa-clipboard-check me-2"></i>
                    Check Request Items: <span id="check-modal-group-code"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form method="POST" action="process_check_items.php" id="checkItemsForm">
                    <input type="hidden" name="group_id" id="check-modal-group-id">
                    
                    <!-- Recipient Info Card - Replacing the alert -->
                    <div class="card mb-4 border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="bg-warning bg-opacity-25 p-3 rounded-circle me-3">
                                    <i class="fas fa-user-circle fa-2x text-warning"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1" id="check-modal-employee"></h6>
                                    <p class="mb-0 text-muted small">
                                        <i class="fas fa-building me-1"></i><span id="check-modal-office"></span>
                                        <span class="mx-2">|</span>
                                        <i class="far fa-calendar me-1"></i><span id="check-modal-request-date"></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Item</th>
                                    <th width="100">Quantity</th>
                                    <th width="150">Status</th>
                                    <th width="200">Rejection Reason</th>
                                </tr>
                            </thead>
                            <tbody id="check-items-body">
                                <tr>
                                    <td colspan="4" class="text-center py-3">
                                        <div class="spinner-border text-warning" role="status"></div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Note Card - Replacing the alert -->
                    <div class="card mt-3 border-0 bg-warning bg-opacity-10">
                        <div class="card-body p-3">
                            <div class="d-flex">
                                <i class="fas fa-info-circle text-warning me-2 mt-1"></i>
                                <div class="small">
                                    <strong>Note:</strong> Approved items will be deducted from inventory. Rejected items require a reason.
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="checkItemsForm" class="btn btn-warning fw-bold px-5">
                    <i class="fas fa-save me-2"></i>Submit Check
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Group Modal -->
<div class="modal fade" id="editGroupModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-edit me-2"></i>
                    Edit Request Group: <span id="modal-group-code"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="consumables_edit_request_action.php" id="editGroupForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="group_id" id="modal-group-id">
                    
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-2">
                                <i class="fas fa-user me-2"></i>Recipient Details
                            </h6>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Recipient Name <span class="text-danger">*</span></label>
                            <input type="text" name="employee" id="modal-employee" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Office/Department <span class="text-danger">*</span></label>
                            <input type="text" name="office" id="modal-office" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <h6 class="text-success border-bottom pb-2">
                                <i class="fas fa-boxes me-2"></i>Requested Items
                            </h6>
                        </div>
                        <div class="col-12">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="40%">Item</th>
                                            <th width="20%">Quantity</th>
                                            <th width="30%">Purpose</th>
                                            <th width="10%">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="edit-items-body">
                                        <tr>
                                            <td colspan="4" class="text-center py-3">
                                                <div class="spinner-border text-primary" role="status"></div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info border-0 small mb-3">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> Changing quantities will automatically adjust inventory stock levels.
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <h6 class="text-info border-bottom pb-2">
                                <i class="fas fa-signature me-2"></i>Signatories
                            </h6>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Approved By</label>
                            <input type="text" name="approved_by" id="modal-approved-by" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Supply Officer</label>
                            <input type="text" name="supply_officer" id="modal-supply-officer" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Group Status</label>
                            <select name="group_status" id="modal-group-status" class="form-select">
                                <option value="Pending">Pending</option>
                                <option value="Approved">Approved</option>
                                <option value="Rejected">Rejected</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_request_group_submit" class="btn btn-warning fw-bold px-5">
                        <i class="fas fa-save me-2"></i>Update Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Stock Report Modal -->
<div class="modal fade" id="generateStockReportModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-file-download me-2"></i>
                    Stock Report Options
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <div class="bg-light d-inline-block p-3 rounded-circle mb-2">
                        <i class="fas fa-chart-pie fa-2x text-primary"></i>
                    </div>
                    <h6 class="fw-bold">Filter Report Options</h6>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">Stock Status</label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="stock_filter" id="filterAll" value="all" checked>
                        <label class="btn btn-outline-primary" for="filterAll">All</label>
                        
                        <input type="radio" class="btn-check" name="stock_filter" id="filterAvailable" value="available">
                        <label class="btn btn-outline-primary" for="filterAvailable">Available</label>
                        
                        <input type="radio" class="btn-check" name="stock_filter" id="filterLow" value="low">
                        <label class="btn btn-outline-primary" for="filterLow">Low Stock</label>
                        
                        <input type="radio" class="btn-check" name="stock_filter" id="filterCritical" value="critical">
                        <label class="btn btn-outline-primary" for="filterCritical">Critical</label>
                    </div>
                </div>
                
                <div class="row g-3 mb-4">
                    <div class="col-6">
                        <label class="form-label">Low Stock (≤)</label>
                        <input type="number" class="form-control" id="lowThreshold" value="10" min="1">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Critical (≤)</label>
                        <input type="number" class="form-control" id="criticalThreshold" value="5" min="0">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">Sort By</label>
                    <select class="form-select" id="sortBy">
                        <option value="item_name">Item Name (A-Z)</option>
                        <option value="category">Category</option>
                        <option value="quantity_asc">Stock Level (Low to High)</option>
                        <option value="quantity_desc">Stock Level (High to Low)</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">Category</label>
                    <select class="form-select" id="categoryFilter">
                        <option value="all">All Categories</option>
                        <?php
                        $cat_stmt = $db->query("SELECT DISTINCT category FROM consumables WHERE category IS NOT NULL AND category != '' ORDER BY category");
                        while($cat = $cat_stmt->fetch(PDO::FETCH_ASSOC)):
                        ?>
                        <option value="<?php echo htmlspecialchars($cat['category']); ?>"><?php echo htmlspecialchars($cat['category']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="alert alert-info border-0 small mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    <span id="filterSummary">Showing all items</span>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-danger" onclick="generateReport('pdf')">
                        <i class="fas fa-file-pdf me-2"></i>PDF
                    </button>
                    <button type="button" class="btn btn-outline-success" onclick="generateReport('excel')">
                        <i class="fas fa-file-excel me-2"></i>Excel
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Back to Top Button -->
<button class="back-to-top" id="backToTop" onclick="scrollToTop()">
    <i class="fas fa-arrow-up"></i>
</button>

<script>
window.isHighlighting = false;
window.highlightCompleted = false;

let consumableRowCount = 1;
let itemRowCount = 1;

// Back to Top functionality
window.onscroll = function() {
    const backToTop = document.getElementById('backToTop');
    if (document.body.scrollTop > 300 || document.documentElement.scrollTop > 300) {
        backToTop.classList.add('show');
    } else {
        backToTop.classList.remove('show');
    }
};

function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

function scrollToSection(sectionId) {
    const element = document.getElementById(sectionId);
    if (element) {
        element.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }
}

// Function to show specific report tab
function showReportTab(tabName) {
    // Show the reports section first
    const reportsSection = document.getElementById('consumption-reports');
    if (reportsSection) {
        reportsSection.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }
    
    // Activate the corresponding tab
    const tabs = document.querySelectorAll('#reportTabs button');
    const tabContents = document.querySelectorAll('.tab-pane');
    
    tabs.forEach(tab => {
        tab.classList.remove('active');
    });
    
    tabContents.forEach(content => {
        content.classList.remove('show', 'active');
    });
    
    if (tabName === 'monthly') {
        document.getElementById('monthly-tab').classList.add('active');
        document.getElementById('monthly').classList.add('show', 'active');
        loadMonthlyReport();
    } else if (tabName === 'annual') {
        document.getElementById('annual-tab').classList.add('active');
        document.getElementById('annual').classList.add('show', 'active');
        loadAnnualReport();
    } else if (tabName === 'category') {
        document.getElementById('category-tab').classList.add('active');
        document.getElementById('category-report').classList.add('show', 'active');
    }
}

// Function to toggle other department field
function toggleOtherDepartment() {
    const select = document.getElementById('officeSelect');
    const otherField = document.getElementById('otherDepartmentField');
    const otherInput = document.getElementById('otherOffice');
    
    if (select.value === 'others') {
        otherField.style.display = 'block';
        otherInput.required = true;
        select.name = ''; // Temporarily remove name to not submit this value
    } else {
        otherField.style.display = 'none';
        otherInput.required = false;
        select.name = 'office'; // Restore name
    }
}

// Function to validate office before form submission
function validateOffice() {
    const select = document.getElementById('officeSelect');
    const otherInput = document.getElementById('otherOffice');
    
    if (select.value === 'others') {
        if (!otherInput.value.trim()) {
            Swal.fire({
                icon: 'warning',
                title: 'Missing Information',
                text: 'Please specify the department name'
            });
            otherInput.focus();
            return false;
        }
        // Set the office value to the other input
        select.name = 'office';
        select.value = otherInput.value.trim();
    }
    return true;
}

// Debug check for buttons
$(document).ready(function() {
    console.log('🔍 Checking for buttons...');
    
    const checkButtons = $('.check-items-btn');
    console.log(`Found ${checkButtons.length} check items buttons`);
    
    if (checkButtons.length === 0) {
        console.warn('⚠️ No check items buttons found with class .check-items-btn');
        console.log('Available buttons with class .action-btn.check:', $('.action-btn.check').length);
    } else {
        console.log('First check button data:', {
            groupId: checkButtons.first().data('group-id'),
            groupCode: checkButtons.first().data('group-code'),
            employee: checkButtons.first().data('employee')
        });
    }
    
    const editButtons = $('.edit-group-btn');
    console.log(`Found ${editButtons.length} edit buttons`);
    
    // Load monthly report when monthly tab is shown
    $('#monthly-tab').on('shown.bs.tab', function(e) {
        loadMonthlyReport();
    });
    
    // Load annual report when annual tab is shown
    $('#annual-tab').on('shown.bs.tab', function(e) {
        loadAnnualReport();
    });
    
    // Initial load of monthly report (since it's active by default)
    setTimeout(loadMonthlyReport, 500);
});

// Add this right after your document ready to debug
$(document).ready(function() {
    console.log('🔍 Checking for edit buttons...');
    const editButtons = $('.edit-group-btn');
    console.log(`Found ${editButtons.length} edit buttons`);
    
    if (editButtons.length === 0) {
        console.warn('⚠️ No edit buttons found with class .edit-group-btn');
        console.log('Available buttons with edit class:', $('.edit-btn').length);
        console.log('Available buttons with action-btn class:', $('.action-btn.edit').length);
    } else {
        // Log the first button's data for verification
        console.log('First button data:', {
            groupId: editButtons.first().data('group-id'),
            groupCode: editButtons.first().data('group-code'),
            employee: editButtons.first().data('employee')
        });
    }
});

// Function to generate a unique ID code
function generateIdCode(itemName) {
    const prefix = itemName ? itemName.replace(/[^a-zA-Z]/g, '').substring(0, 3).toUpperCase() : 'ITM';
    const randomPart = Math.random().toString(36).substring(2, 10).toUpperCase();
    return `${prefix}-${randomPart}`;
}

// Function to regenerate ID code for a specific row
function regenerateIdCode(rowId) {
    const row = document.getElementById(`consumable-row-${rowId}`);
    const itemNameInput = row.querySelector('input[name="item_name[]"]');
    const idCodeInput = row.querySelector('input[name="identification[]"]');
    const newCode = generateIdCode(itemNameInput.value);
    idCodeInput.value = newCode;
}

// Function to add a new consumable row
function addConsumableRow() {
    consumableRowCount++;
    const container = document.getElementById('consumable-items-container');
    const newRow = document.createElement('div');
    newRow.className = 'consumable-item-row';
    newRow.id = `consumable-row-${consumableRowCount}`;
    
    newRow.innerHTML = `
        <div class="row g-3 align-items-end mb-3 pb-3 border-bottom">
            <div class="col-md-6">
                <label class="small fw-bold text-primary">Item Name <span class="text-danger">*</span></label>
                <input type="text" name="item_name[]" class="form-control" placeholder="e.g. A4 Bond Paper" required onchange="updateIdCodeFromName(${consumableRowCount})">
            </div>
            <div class="col-md-3">
                <label class="small fw-bold text-primary">Category</label>
                <input type="text" name="category[]" class="form-control" placeholder="e.g. Office Supplies">
            </div>
            <div class="col-md-3">
                <label class="small fw-bold text-primary">Quantity <span class="text-danger">*</span></label>
                <input type="number" name="quantity[]" class="form-control" placeholder="0" required>
            </div>
            <div class="col-md-3">
                <label class="small fw-bold text-primary">Unit</label>
                <input type="text" name="unit[]" class="form-control" placeholder="e.g. Reams, Pcs">
            </div>
            <div class="col-md-3">
                <label class="small fw-bold text-primary">Brand</label>
                <input type="text" name="brand[]" class="form-control" placeholder="Brand name">
            </div>
            <div class="col-md-4">
                <label class="small fw-bold text-primary">ID Code <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="text" name="identification[]" class="form-control bg-light" id="idcode-${consumableRowCount}" readonly placeholder="Auto-generated" required>
                    <button type="button" class="btn btn-outline-secondary" onclick="regenerateIdCode(${consumableRowCount})" title="Generate New Code">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
                <small class="text-muted">Auto-generated unique code</small>
            </div>
            <div class="col-md-2 text-end">
                <button type="button" class="btn btn-outline-danger remove-consumable-btn" onclick="removeConsumableRow(${consumableRowCount})">
                    <i class="fas fa-trash-alt me-1"></i> Remove
                </button>
            </div>
        </div>
    `;
    
    container.appendChild(newRow);
    const newIdCodeInput = newRow.querySelector('input[name="identification[]"]');
    newIdCodeInput.value = generateIdCode('');
    updateRemoveButtonsVisibility();
}

// Function to generate report with filters
function generateReport(format) {
    // Get filter values
    const stockFilter = document.querySelector('input[name="stock_filter"]:checked').value;
    const lowThreshold = document.getElementById('lowThreshold').value;
    const criticalThreshold = document.getElementById('criticalThreshold').value;
    const sortBy = document.getElementById('sortBy').value;
    const categoryFilter = document.getElementById('categoryFilter').value;
    
    // Build URL with parameters
    let url = '';
    if (format === 'pdf') {
        url = 'generate_stock_report_pdf.php?';
    } else {
        url = 'generate_stock_report_xlsx.php?';
    }
    
    // Add parameters
    url += `stock_filter=${stockFilter}&low=${lowThreshold}&critical=${criticalThreshold}&sort=${sortBy}&category=${encodeURIComponent(categoryFilter)}`;
    
    // Open in new tab
    window.open(url, '_blank');
    
    // Close modal
    bootstrap.Modal.getInstance(document.getElementById('generateStockReportModal')).hide();
}

// Update filter summary
document.querySelectorAll('input[name="stock_filter"], #categoryFilter, #lowThreshold, #criticalThreshold').forEach(el => {
    el.addEventListener('change', updateFilterSummary);
    el.addEventListener('keyup', updateFilterSummary);
});

function updateFilterSummary() {
    const stockFilter = document.querySelector('input[name="stock_filter"]:checked').value;
    const categoryFilter = document.getElementById('categoryFilter').value;
    const lowThreshold = document.getElementById('lowThreshold').value;
    const criticalThreshold = document.getElementById('criticalThreshold').value;
    
    let summary = '';
    
    // Stock status
    switch(stockFilter) {
        case 'all':
            summary = 'All items';
            break;
        case 'available':
            summary = `Available items (> ${lowThreshold})`;
            break;
        case 'low':
            summary = `Low stock items (≤ ${lowThreshold})`;
            break;
        case 'critical':
            summary = `Critical items (≤ ${criticalThreshold})`;
            break;
    }
    
    // Category
    if (categoryFilter !== 'all') {
        summary += ` in category "${categoryFilter}"`;
    }
    
    document.getElementById('filterSummary').textContent = summary;
}

// Function to update ID code based on item name
function updateIdCodeFromName(rowId) {
    const row = document.getElementById(`consumable-row-${rowId}`);
    const itemNameInput = row.querySelector('input[name="item_name[]"]');
    const idCodeInput = row.querySelector('input[name="identification[]"]');
    const newCode = generateIdCode(itemNameInput.value);
    idCodeInput.value = newCode;
}

// Function to remove a consumable row
function removeConsumableRow(rowId) {
    const row = document.getElementById(`consumable-row-${rowId}`);
    if (row) {
        row.remove();
        consumableRowCount--;
        updateRemoveButtonsVisibility();
    }
}

// Function to update visibility of remove buttons
function updateRemoveButtonsVisibility() {
    const rows = document.querySelectorAll('.consumable-item-row');
    const removeButtons = document.querySelectorAll('.remove-consumable-btn');
    
    if (rows.length === 1) {
        removeButtons.forEach(btn => btn.style.display = 'none');
    } else {
        removeButtons.forEach(btn => btn.style.display = 'inline-block');
    }
}

// Monthly Report Functions
function loadMonthlyReport() {
    const year = $('#monthlyYear').val();
    const month = $('#monthlyMonth').val();
    const category = $('#monthlyCategory').val();
    const department = $('#monthlyDepartment').val();
    
    $('#monthlyReportContainer').html(`
        <div class="text-center py-5">
            <div class="spinner-border text-primary mb-3"></div>
            <p class="text-muted">Loading monthly consumption data...</p>
        </div>
    `);
    
    $.ajax({
        url: 'get_monthly_consumption.php',
        type: 'POST',
        data: {
            year: year,
            month: month,
            category: category,
            department: department
        },
        success: function(response) {
            $('#monthlyReportContainer').html(response);
        },
        error: function() {
            $('#monthlyReportContainer').html(`
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Error loading monthly report. Please try again.
                </div>
            `);
        }
    });
}

function loadAnnualReport() {
    const year = $('#annualYear').val();
    const category = $('#annualCategory').val();
    const department = $('#annualDepartment').val();
    
    $('#annualReportContainer').html(`
        <div class="text-center py-5">
            <div class="spinner-border text-primary mb-3"></div>
            <p class="text-muted">Loading annual consumption data...</p>
        </div>
    `);
    
    $.ajax({
        url: 'get_annual_consumption.php',
        type: 'POST',
        data: {
            year: year,
            category: category,
            department: department
        },
        success: function(response) {
            $('#annualReportContainer').html(response);
        },
        error: function() {
            $('#annualReportContainer').html(`
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Error loading annual report. Please try again.
                </div>
            `);
        }
    });
}

// Export functions
function exportMonthlyReport() {
    const year = $('#monthlyYear').val();
    const month = $('#monthlyMonth').val();
    const category = $('#monthlyCategory').val();
    const department = $('#monthlyDepartment').val();
    
    window.open(`export_monthly_report.php?year=${year}&month=${month}&category=${encodeURIComponent(category)}&department=${encodeURIComponent(department)}`, '_blank');
}

function exportMonthlyReportPDF() {
    const year = $('#monthlyYear').val();
    const month = $('#monthlyMonth').val();
    const category = $('#monthlyCategory').val();
    const department = $('#monthlyDepartment').val();
    
    window.open(`export_monthly_report_pdf.php?year=${year}&month=${month}&category=${encodeURIComponent(category)}&department=${encodeURIComponent(department)}`, '_blank');
}

function exportAnnualReport() {
    const year = $('#annualYear').val();
    const category = $('#annualCategory').val();
    const department = $('#annualDepartment').val();
    
    window.open(`export_annual_report.php?year=${year}&category=${encodeURIComponent(category)}&department=${encodeURIComponent(department)}`, '_blank');
}

function exportAnnualReportPDF() {
    const year = $('#annualYear').val();
    const category = $('#annualCategory').val();
    const department = $('#annualDepartment').val();
    
    window.open(`export_annual_report_pdf.php?year=${year}&category=${encodeURIComponent(category)}&department=${encodeURIComponent(department)}`, '_blank');
}

function exportCategoryReport() {
    window.open('export_category_report.php', '_blank');
}

function exportCategoryReportPDF() {
    window.open('export_category_report_pdf.php', '_blank');
}

// ── Category Report: Real-time charts ──
// Data is PHP-rendered once on page load (category report is static, not AJAX)
(function initCategoryCharts() {
    // Wait for DOM + Chart.js to be ready
    function tryInit() {
        if (typeof Chart === 'undefined') { setTimeout(tryInit, 200); return; }

        var catLabels   = <?php
            $cat_chart_labels  = [];
            $cat_chart_stock   = [];
            $cat_chart_in      = [];
            $cat_chart_low     = [];
            $cat_chart_out     = [];
            $categorySummary_chart = getCategorySummary($db);
            foreach ($categorySummary_chart as $c) {
                $cat_chart_labels[] = $c['category'];
                $cat_chart_stock[]  = (int)$c['total_stock'];
                $cat_chart_in[]     = (int)$c['in_stock'];
                $cat_chart_low[]    = (int)$c['low_stock'];
                $cat_chart_out[]    = (int)$c['out_of_stock'];
            }
            echo json_encode($cat_chart_labels);
        ?>;
        var stockTotals = <?php echo json_encode($cat_chart_stock); ?>;
        var inStock     = <?php echo json_encode($cat_chart_in); ?>;
        var lowStock    = <?php echo json_encode($cat_chart_low); ?>;
        var outStock    = <?php echo json_encode($cat_chart_out); ?>;

        if (!catLabels.length) return;

        // Destroy previous instances if any
        ['categoryStockDoughnut','categoryHealthBar'].forEach(function(id) {
            var ex = Chart.getChart(id);
            if (ex) ex.destroy();
        });

        // Doughnut – stock distribution
        var dCtx = document.getElementById('categoryStockDoughnut');
        if (dCtx) {
            new Chart(dCtx.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: catLabels,
                    datasets: [{
                        data: stockTotals,
                        backgroundColor: [
                            'rgba(13,110,253,0.8)','rgba(25,135,84,0.8)','rgba(255,193,7,0.8)',
                            'rgba(220,53,69,0.8)', 'rgba(13,202,240,0.8)','rgba(111,66,193,0.8)',
                            'rgba(253,126,20,0.8)','rgba(23,162,184,0.8)'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: { boxWidth: 12, font: { size: 10 }, padding: 8 }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    var total = ctx.dataset.data.reduce(function(a,b){return a+b;},0);
                                    var pct = total > 0 ? ((ctx.parsed/total)*100).toFixed(1) : 0;
                                    return ' ' + ctx.parsed.toLocaleString() + ' units (' + pct + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }

        // Stacked bar – stock health
        var bCtx = document.getElementById('categoryHealthBar');
        if (bCtx) {
            new Chart(bCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: catLabels,
                    datasets: [
                        { label: 'In Stock',     data: inStock,  backgroundColor: 'rgba(25,135,84,0.8)',  borderRadius: 4 },
                        { label: 'Low Stock',    data: lowStock, backgroundColor: 'rgba(255,193,7,0.8)',  borderRadius: 4 },
                        { label: 'Critical Stock', data: outStock, backgroundColor: 'rgba(220,53,69,0.8)',  borderRadius: 4 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top', labels: { boxWidth: 12, font: { size: 10 } } }
                    },
                    scales: {
                        x: {
                            stacked: true,
                            ticks: { font: { size: 9 }, maxRotation: 30 },
                            grid: { display: false }
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            ticks: { font: { size: 10 } },
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        }
                    }
                }
            });
        }
    }
    // Trigger after a short delay to ensure the tab canvas is visible
    document.addEventListener('DOMContentLoaded', function() { setTimeout(tryInit, 300); });
    // Also re-init when category tab is shown (canvas must be visible for Chart.js)
    document.addEventListener('DOMContentLoaded', function() {
        var catTabBtn = document.getElementById('category-tab');
        if (catTabBtn) {
            catTabBtn.addEventListener('shown.bs.tab', function() { tryInit(); });
        }
    });
})();

// VIEW REQUEST BUTTON HANDLER
$(document).on('click', '.view-request-btn', function(e) {
    e.preventDefault();
    e.stopPropagation();
    
    const groupId = $(this).data('group-id');
    const groupCode = $(this).data('group-code');
    
    console.log('👁️ View request clicked - ID:', groupId, 'Code:', groupCode);
    
    if (!groupId) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Missing group ID'
        });
        return;
    }
    
    // Set modal header
    $('#view-modal-group-code').text(groupCode || 'N/A');
    $('#view-modal-group-id').val(groupId);
    
    // Load request details
    loadRequestDetails(groupId);
    
    // Show modal
    const viewModal = new bootstrap.Modal(document.getElementById('viewRequestModal'));
    viewModal.show();
});

// Function to load request details
function loadRequestDetails(groupId) {
    const itemsBody = document.getElementById('view-items-body');
    const totalItemsElement = document.getElementById('view-total-items');
    const totalQuantityElement = document.getElementById('view-total-quantity');
    
    // Show loading state
    itemsBody.innerHTML = `
        <tr>
            <td colspan="4" class="text-center py-4">
                <div class="spinner-border text-info" role="status"></div>
                <p class="mt-2 text-muted small">Loading items...</p>
            </td>
        </tr>
    `;
    
    if (totalItemsElement) totalItemsElement.textContent = '0';
    if (totalQuantityElement) totalQuantityElement.textContent = '0';
    
    // Fetch request details via AJAX
    fetch(`get_request_details.php?group_id=${groupId}`)
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            console.log('📦 Request details loaded:', data);
            
            if (data.success) {
                // Set recipient info - including campus
                $('#view-employee').text(data.request.employee || 'N/A');
                $('#view-campus').text(data.request.campus || 'N/A');
                $('#view-office').text(data.request.office || 'N/A');
                $('#view-request-date').text(data.request.request_date || 'N/A');
                $('#view-requested-by').text(data.request.requested_by || 'N/A');
                $('#view-approved-by').text(data.request.approved_by || 'N/A');
                $('#view-supply-officer').text(data.request.supply_officer || 'N/A');
                
                // Set status badge
                const statusClass = data.request.status === 'Approved' ? 'success' : 
                                   (data.request.status === 'Rejected' ? 'danger' : 'warning');
                $('#view-status').text(data.request.status || 'Pending')
                    .removeClass('bg-success bg-warning bg-danger')
                    .addClass(`bg-${statusClass}`);
                
                // Update the items display
                if (data.items && data.items.length > 0) {
                    let itemsHtml = '';
                    let totalQuantity = 0;
                    
                    data.items.forEach(item => {
                        totalQuantity += item.quantity;
                        
                        const itemStatusClass = item.status === 'Approved' ? 'success' : 
                                            (item.status === 'Rejected' ? 'danger' : 'warning');
                        
                        let rejectionHtml = '';
                        if (item.status === 'Rejected' && item.rejection_reason) {
                            rejectionHtml = `
                                <small class="text-danger d-block mt-1">
                                    <i class="fas fa-exclamation-circle me-1"></i>
                                    Reason: ${escapeHtml(item.rejection_reason)}
                                </small>
                            `;
                        }
                        
                        itemsHtml += `
                            <tr>
                                <td>
                                    <strong>${escapeHtml(item.item_name)}</strong>
                                    <br>
                                    <small class="text-muted">ID: ${escapeHtml(item.identification || 'N/A')}</small>
                                    ${rejectionHtml}
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold">${item.quantity}</span>
                                    <small class="text-muted d-block">${escapeHtml(item.unit || '')}</small>
                                </td>
                                <td>${escapeHtml(item.description || '—')}</td>
                                <td class="text-center">
                                    <span class="badge bg-${itemStatusClass}">${item.status}</span>
                                </td>
                            </tr>
                        `;
                    });
                    
                    itemsBody.innerHTML = itemsHtml;
                    if (totalItemsElement) totalItemsElement.textContent = data.items.length;
                    if (totalQuantityElement) totalQuantityElement.textContent = totalQuantity;
                } else {
                    itemsBody.innerHTML = `
                        <tr>
                            <td colspan="4" class="text-center py-4">
                                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                <p class="mb-0">No items found for this request.</p>
                            </td>
                        </tr>
                    `;
                    if (totalItemsElement) totalItemsElement.textContent = '0';
                    if (totalQuantityElement) totalQuantityElement.textContent = '0';
                }
            } else {
                itemsBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center py-4 text-danger">
                            <i class="fas fa-exclamation-circle fa-3x mb-3"></i>
                            <p class="mb-0">${data.message || 'Failed to load request details.'}</p>
                        </td>
                    </tr>
                `;
                if (totalItemsElement) totalItemsElement.textContent = '0';
                if (totalQuantityElement) totalQuantityElement.textContent = '0';
            }
        })
        .catch(error => {
            console.error('❌ Error loading request details:', error);
            itemsBody.innerHTML = `
                <tr>
                    <td colspan="4" class="text-center py-4 text-danger">
                        <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                        <p class="mb-0">Error loading details. Please try again.</p>
                    </td>
                </tr>
            `;
            if (totalItemsElement) totalItemsElement.textContent = '0';
            if (totalQuantityElement) totalQuantityElement.textContent = '0';
        });
}

// Edit modal quantity validation
function validateEditQuantity(input) {
    const row = input.closest('tr');
    const select = row.querySelector('.item-select-edit');
    if (!select) return;
    
    const selectedOption = select.options[select.selectedIndex];
    const maxStock = selectedOption.getAttribute('data-max');
    const unit = selectedOption.getAttribute('data-unit');
    const stockHint = row.querySelector('.edit-stock-hint');
    const value = parseInt(input.value);
    const originalValue = parseInt(input.getAttribute('data-original'));
    
    if (maxStock && !isNaN(parseInt(maxStock))) {
        if (value > parseInt(maxStock)) {
            alert(`Quantity cannot exceed available stock (${maxStock} ${unit || ''})`);
            input.value = originalValue;
        } else if (value < 1) {
            input.value = 1;
        }
        if (stockHint) {
            stockHint.textContent = `Max: ${maxStock} ${unit || ''}`;
        }
    }
}

// Function to load check items modal data
function loadCheckModal(groupId, groupCode, employee, office, requestDate) {
    const modalBody = document.getElementById('check-items-body');
    
    // Set modal header
    document.getElementById('check-modal-group-code').textContent = groupCode;
    document.getElementById('check-modal-group-id').value = groupId;
    
    // Set recipient info in the card
    document.getElementById('check-modal-employee').textContent = employee || 'N/A';
    document.getElementById('check-modal-office').textContent = office || 'N/A';
    document.getElementById('check-modal-request-date').textContent = requestDate || 'N/A';
    
    modalBody.innerHTML = `
        <tr>
            <td colspan="4" class="text-center py-3">
                <div class="spinner-border text-warning" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </td>
        </tr>
    `;
    
    // Fetch pending items via AJAX
    fetch(`get_check_items.php?group_id=${groupId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.items.length > 0) {
                let html = '';
                data.items.forEach(item => {
                    html += `
                        <tr>
                            <td>
                                <input type="hidden" name="item_ids[]" value="${item.id}">
                                <strong>${escapeHtml(item.item_name)}</strong>
                                <br>
                                <small class="text-muted">
                                    Available: ${item.available_stock} ${escapeHtml(item.unit)}
                                </small>
                            </td>
                            <td class="text-center align-middle">
                                <span class="fw-bold">${item.quantity}</span>
                                <small class="text-muted d-block">${escapeHtml(item.unit)}</small>
                            </td>
                            <td class="align-middle">
                                <select name="status[${item.id}]" 
                                        class="form-select form-select-sm status-select" 
                                        onchange="toggleReason(${item.id}, this.value)">
                                    <option value="">-- Select --</option>
                                    <option value="Approved">✅ Approve</option>
                                    <option value="Rejected">❌ Reject</option>
                                </select>
                            </td>
                            <td class="align-middle">
                                <textarea name="reason[${item.id}]" 
                                          id="reason_${item.id}"
                                          class="form-control form-control-sm" 
                                          rows="2"
                                          placeholder="Reason for rejection (required if rejected)"
                                          style="display: none;"></textarea>
                            </td>
                        </tr>
                    `;
                });
                modalBody.innerHTML = html;
            } else if (data.success && data.items.length === 0) {
                modalBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center py-4">
                            <i class="fas fa-check-circle text-success fa-2x mb-2"></i>
                            <p class="mb-0">All items in this request have been processed.</p>
                        </td>
                    </tr>
                `;
            } else {
                modalBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center py-3 text-danger">
                            Failed to load items. Please try again.
                        </td>
                    </tr>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            modalBody.innerHTML = `
                <tr>
                    <td colspan="4" class="text-center py-3 text-danger">
                        Error loading items. Please try again.
                    </td>
                </tr>
            `;
        });
}

// Helper function to escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
}

// Update the toggleReason function to handle Rejected status properly
function toggleReason(itemId, status) {
    const reasonField = document.getElementById('reason_' + itemId);
    if (reasonField) {
        if (status === 'Rejected') {
            reasonField.style.display = 'block';
            reasonField.required = true;
            reasonField.focus();
        } else {
            reasonField.style.display = 'none';
            reasonField.required = false;
            reasonField.value = '';
        }
    }
}

// Update the modal event handlers for Rejected status
$(document).on('shown.bs.modal', '#checkItemsModal', function() {
    // Reset all reason fields to hidden
    $(this).find('[id^="reason_"]').each(function() {
        $(this).hide().prop('required', false);
    });
    
    // Check for any pre-selected "Rejected" statuses
    $(this).find('.status-select').each(function() {
        if ($(this).val() === 'Rejected') {
            const name = $(this).attr('name');
            if (name) {
                const match = name.match(/\d+/);
                if (match) {
                    const itemId = match[0];
                    const reasonField = document.getElementById('reason_' + itemId);
                    if (reasonField) {
                        reasonField.style.display = 'block';
                        reasonField.required = true;
                    }
                }
            }
        }
    });
});

// Add debug button check
$(document).ready(function() {
    console.log('🔍 Checking for view buttons...');
    const viewButtons = $('.view-request-btn');
    console.log(`Found ${viewButtons.length} view request buttons`);
});

$(document).on('hidden.bs.modal', '#checkItemsModal', function() {
    // Reset all selects to default
    $(this).find('.status-select').val('');
    
    // Hide all reason fields
    $(this).find('[id^="reason_"]').each(function() {
        $(this).hide().prop('required', false);
    });
});

// Update the CHECK ITEMS BUTTON HANDLER to handle Rejected status
$(document).on('click', '.check-items-btn', function(e) {
    e.preventDefault();
    e.stopPropagation();
    
    console.log('🔍 Check Items button clicked');
    
    // Get data attributes
    const groupId = $(this).data('group-id');
    const groupCode = $(this).data('group-code');
    const employee = $(this).data('employee');
    const office = $(this).data('office');
    const requestDate = $(this).data('request-date');
    
    console.log('Check items data:', { groupId, groupCode, employee, office, requestDate });
    
    if (!groupId) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Missing group ID'
        });
        return;
    }
    
    // Load items for this group - FIXED: Use loadCheckModal instead of loadCheckModalItems
    loadCheckModal(groupId, groupCode, employee, office, requestDate);
    
    // Show the modal
    const checkModal = new bootstrap.Modal(document.getElementById('checkItemsModal'));
    checkModal.show();
});

// Update the addItemRow function to include threshold info
function addItemRow() {
    itemRowCount++;
    const container = document.getElementById('items-container');
    const newRow = document.createElement('div');
    newRow.className = 'item-row';
    newRow.id = `item-row-${itemRowCount}`;
    
    const firstSelect = document.querySelector('select[name="item_id[]"]');
    const options = firstSelect ? firstSelect.innerHTML : '';
    
    newRow.innerHTML = `
        <button type="button" class="btn btn-outline-danger btn-sm remove-item-btn" onclick="removeItemRow(${itemRowCount})">
            <i class="fas fa-times"></i>
        </button>
        <div class="row g-3">
            <div class="col-md-12">
                <label class="small fw-bold">Search Item</label>
                <input type="text" class="form-control item-search" placeholder="Type to filter items..." onkeyup="filterItems(this)">
            </div>
            <div class="col-md-6">
                <label class="small fw-bold">Select Item <span class="text-danger">*</span></label>
                <select name="item_id[]" class="form-select item-select" required onchange="updateStockHint(this)">
                    ${options}
                </select>
                <small class="text-muted" id="stock-status-hint-${itemRowCount}"></small>
            </div>
            <div class="col-md-3">
                <label class="small fw-bold">Quantity <span class="text-danger">*</span></label>
                <input type="number" name="req_quantity[]" class="form-control quantity-input" min="1" required onchange="validateQuantity(this)">
                <small class="text-muted stock-hint"></small>
            </div>
            <div class="col-md-3">
                <label class="small fw-bold">Purpose/Description</label>
                <input type="text" name="description[]" class="form-control" placeholder="e.g. Office use">
            </div>
        </div>
    `;
    
    container.appendChild(newRow);
    document.querySelectorAll('.remove-item-btn').forEach(btn => btn.style.display = 'inline-block');
    
    // Initialize the stock hint for the new row
    const newSelect = newRow.querySelector('.item-select');
    if (newSelect) {
        updateStockHint(newSelect);
    }
}

// Update the filterItems function to also filter out critical items visually
function filterItems(searchInput) {
    const row = searchInput.closest('.item-row');
    const select = row.querySelector('.item-select');
    const searchTerm = searchInput.value.toLowerCase().trim();
    let visibleCount = 0;
    
    // Loop through all options and hide those that don't match
    for (let option of select.options) {
        if (option.value === '') {
            // Always show placeholder
            option.style.display = '';
            continue;
        }
        
        const text = option.text.toLowerCase();
        if (text.includes(searchTerm)) {
            option.style.display = '';
            visibleCount++;
        } else {
            option.style.display = 'none';
        }
    }
    
    // Handle "no matches" placeholder
    const noMatchOption = select.querySelector('option.no-match-option');
    if (visibleCount === 0 && searchTerm !== '') {
        if (!noMatchOption) {
            const option = document.createElement('option');
            option.className = 'no-match-option';
            option.value = '';
            option.textContent = '-- No matching items --';
            option.disabled = true;
            select.appendChild(option);
        } else {
            noMatchOption.style.display = '';
        }
    } else if (noMatchOption) {
        noMatchOption.style.display = 'none';
    }
    
    // If exactly one visible option, select it automatically
    if (visibleCount === 1) {
        for (let option of select.options) {
            if (option.value !== '' && option.style.display !== 'none') {
                option.selected = true;
                // Trigger change to update stock hint
                $(select).trigger('change');
                break;
            }
        }
    }
}

// Add this to the modal hidden event (already present, but ensure it's there)
$('#requestItemsModal').on('hidden.bs.modal', function () {
    const container = document.getElementById('items-container');
    if (container) {
        container.innerHTML = '';
        itemRowCount = 1;
        
        const firstRow = document.createElement('div');
        firstRow.className = 'item-row';
        firstRow.id = 'item-row-1';
        firstRow.innerHTML = `
            <button type="button" class="btn btn-outline-danger btn-sm remove-item-btn" onclick="removeItemRow(1)" style="display: none;">
                <i class="fas fa-times"></i>
            </button>
            <div class="row g-3">
                <div class="col-md-12">
                    <label class="small fw-bold">Search Item</label>
                    <input type="text" class="form-control item-search" placeholder="Type to filter items..." onkeyup="filterItems(this)">
                </div>
                <div class="col-md-6">
                    <label class="small fw-bold">Select Item <span class="text-danger">*</span></label>
                    <select name="item_id[]" class="form-select item-select" required>
                        <option value="">-- Choose Item --</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="small fw-bold">Quantity <span class="text-danger">*</span></label>
                    <input type="number" name="req_quantity[]" class="form-control quantity-input" min="1" required>
                    <small class="text-muted stock-hint"></small>
                </div>
                <div class="col-md-3">
                    <label class="small fw-bold">Purpose/Description</label>
                    <input type="text" name="description[]" class="form-control" placeholder="e.g. Office use">
                </div>
            </div>
        `;
        container.appendChild(firstRow);
    }
    
    // Also clear any stray "no-match" options (though they get recreated each time)
    $('.no-match-option').remove();
    
    // Reset other department field
    document.getElementById('officeSelect').value = '';
    document.getElementById('otherDepartmentField').style.display = 'none';
    document.getElementById('otherOffice').value = '';
});

// Function to remove item row
function removeItemRow(rowId) {
    const row = document.getElementById(`item-row-${rowId}`);
    if (row) {
        row.remove();
        itemRowCount--;
        
        const remainingRows = document.querySelectorAll('.item-row');
        if (remainingRows.length === 1) {
            const firstRowBtn = remainingRows[0].querySelector('.remove-item-btn');
            if (firstRowBtn) firstRowBtn.style.display = 'none';
        }
    }
}

// Update updateStockHint function to show thresholds correctly
function updateStockHint(select) {
    const row = select.closest('.item-row');
    const rowId = row.id.split('-')[2] || '1';
    const selectedOption = select.options[select.selectedIndex];
    const maxStock = selectedOption.getAttribute('data-max');
    const unit = (selectedOption.getAttribute('data-unit') || '').toLowerCase();
    const isCritical = selectedOption.getAttribute('data-critical') === '1';
    const quantityInput = row.querySelector('.quantity-input');
    const stockHint = row.querySelector('.stock-hint');
    const stockStatusHint = document.getElementById(`stock-status-hint-${rowId}`);
    
    if (isCritical) {
        // Item is critical - cannot be selected
        if (stockStatusHint) {
            stockStatusHint.innerHTML = '<span class="text-danger fw-bold"><i class="fas fa-exclamation-circle me-1"></i>This item is CRITICAL and cannot be requested</span>';
        }
        if (quantityInput) {
            quantityInput.disabled = true;
            quantityInput.value = '';
            quantityInput.placeholder = 'Cannot request';
        }
        if (stockHint) {
            stockHint.innerHTML = '';
        }
    } else {
        // Item is available for request
        if (stockStatusHint) {
            // Show thresholds based on unit
            let thresholdInfo = '';
            switch(unit) {
                case 'pcs':
                    thresholdInfo = 'Critical ≤30, Low 31-50, Available 51+';
                    break;
                case 'unit':
                case 'ream':
                case 'box':
                    thresholdInfo = 'Critical ≤10, Low 11-20, Available 21+';
                    break;
                default:
                    thresholdInfo = 'Critical ≤10, Low 11-20, Available 21+';
            }
            stockStatusHint.innerHTML = `<small class="text-info"><i class="fas fa-info-circle me-1"></i>${thresholdInfo}</small>`;
        }
        
        if (quantityInput) {
            quantityInput.disabled = false;
            quantityInput.placeholder = '';
            
            if (maxStock && quantityInput) {
                quantityInput.max = parseInt(maxStock);
                
                // Calculate maximum allowed to keep item in Available range
                let maxAllowed = parseInt(maxStock);
                let availableThreshold = 21;
                let lowThreshold = 20;
                
                if (unit === 'pcs') {
                    availableThreshold = 51;
                    lowThreshold = 50;
                }
                
                maxAllowed = maxStock - availableThreshold;
                
                if (stockHint) {
                    stockHint.innerHTML = `Available: ${maxStock} ${unit} | Max request to stay Available: ${maxAllowed > 0 ? maxAllowed : 0} ${unit}`;
                }
            }
        }
    }
}

// Update validateQuantity function to ALLOW requests that make items Critical
function validateQuantity(input) {
    const row = input.closest('.item-row');
    const select = row.querySelector('.item-select');
    const selectedOption = select.options[select.selectedIndex];
    const max = parseInt(input.max) || 999999;
    const value = parseInt(input.value) || 0;
    const unit = (selectedOption.getAttribute('data-unit') || '').toLowerCase();
    const currentStock = parseInt(selectedOption.getAttribute('data-max'));
    const stockHint = row.querySelector('.stock-hint');
    
    if (currentStock && value > 0) {
        const remainingStock = currentStock - value;
        
        // Define thresholds based on unit type
        let criticalThreshold, lowThreshold;
        
        switch(unit) {
            case 'pcs':
                criticalThreshold = 30;
                lowThreshold = 50;
                break;
            case 'unit':
                criticalThreshold = 10;
                lowThreshold = 20;
                break;
            case 'box':
            case 'ream':
                criticalThreshold = 10;
                lowThreshold = 20;
                break;
            default:
                criticalThreshold = 10;
                lowThreshold = 20;
        }
        
        // Determine if remaining stock would be Critical, Low, or Available
        let remainingStatus = '';
        let statusClass = '';
        
        if (remainingStock <= 0) {
            remainingStatus = 'OUT OF STOCK';
            statusClass = 'danger';
        } else if (remainingStock <= criticalThreshold) {
            remainingStatus = 'CRITICAL';
            statusClass = 'danger';
        } else if (remainingStock <= lowThreshold) {
            remainingStatus = 'LOW';
            statusClass = 'warning';
        } else {
            remainingStatus = 'AVAILABLE';
            statusClass = 'success';
        }
        
        // MODIFIED: Only block OUT OF STOCK, allow CRITICAL and LOW
        if (remainingStock <= 0) {
            // Block only if it would cause OUT OF STOCK
            input.classList.add('is-invalid');
            
            // Show error message
            if (stockHint) {
                stockHint.innerHTML = `<span class="text-danger fw-bold">
                    <i class="fas fa-exclamation-circle me-1"></i>
                    Cannot request ${value} ${unit}. This would leave ${remainingStock} ${unit} (OUT OF STOCK).
                    Maximum allowed to keep in stock: ${currentStock - 1} ${unit}
                </span>`;
            }
            
            // Highlight the input as invalid
            input.style.borderColor = '#dc3545';
        } 
        else if (remainingStatus === 'CRITICAL') {
            // ALLOW but show warning that it will become CRITICAL
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
            input.style.borderColor = '#ffc107'; // Yellow border for warning
            
            // Show warning message
            if (stockHint) {
                stockHint.innerHTML = `<span class="text-warning fw-bold">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    After request: ${remainingStock} ${unit} (CRITICAL) - This will trigger a critical stock alert
                </span>`;
            }
        }
        else if (remainingStatus === 'LOW') {
            // Allow and show info
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
            input.style.borderColor = '#ffc107';
            
            // Show info message
            if (stockHint) {
                stockHint.innerHTML = `<span class="text-warning">
                    <i class="fas fa-info-circle me-1"></i>
                    After request: ${remainingStock} ${unit} (LOW STOCK)
                </span>`;
            }
        } else {
            // Available - all good
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
            input.style.borderColor = '#198754';
            
            // Show success hint
            if (stockHint) {
                stockHint.innerHTML = `<span class="text-success">
                    <i class="fas fa-check-circle me-1"></i>
                    After request: ${remainingStock} ${unit} (AVAILABLE)
                </span>`;
            }
        }
    }
    
    // Basic validation
    if (!isNaN(max) && value > max) {
        alert(`Quantity cannot exceed available stock (${max})`);
        input.value = max;
    }
    
    if (value < 1) {
        input.value = 1;
    }
}

// Enhanced form validation - UPDATED to only block OUT OF STOCK
function validateRequestForm() {
    const selects = document.querySelectorAll('select[name="item_id[]"]');
    let hasOutOfStock = false;
    let errorMessages = [];
    
    for (let select of selects) {
        const selectedOption = select.options[select.selectedIndex];
        if (selectedOption && selectedOption.value) {
            const isCritical = selectedOption.getAttribute('data-critical') === '1';
            
            // Block if item is already Critical (can't request critical items)
            if (isCritical) {
                hasOutOfStock = true;
                errorMessages.push('Cannot request items that are already in Critical Stock.');
                break;
            }
            
            // Check quantities
            const row = select.closest('.item-row');
            const quantityInput = row.querySelector('.quantity-input');
            const quantity = parseInt(quantityInput.value) || 0;
            const unit = (selectedOption.getAttribute('data-unit') || '').toLowerCase();
            const currentStock = parseInt(selectedOption.getAttribute('data-max'));
            
            if (quantity > 0) {
                const remainingStock = currentStock - quantity;
                
                // Only block if it would cause OUT OF STOCK
                if (remainingStock < 0) {
                    hasOutOfStock = true;
                    errorMessages.push(`${selectedOption.text.split(' (')[0]}: Requesting ${quantity} ${unit} would leave ${remainingStock} ${unit} (OUT OF STOCK). Maximum allowed: ${currentStock} ${unit}`);
                }
                // NOTE: CRITICAL and LOW are now allowed
            }
        }
    }
    
    if (hasOutOfStock) {
        Swal.fire({
            icon: 'error',
            title: 'Invalid Request',
            html: errorMessages.join('<br><br>'),
            confirmButtonText: 'OK'
        });
        return false;
    }
    
    return validateOffice();
}

// Function to load edit modal data
function loadEditModal(groupId) {
    const modalBody = document.getElementById('edit-items-body');
    modalBody.innerHTML = `
        <tr>
            <td colspan="4" class="text-center py-3">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </td>
        </tr>
    `;
    
    // Fetch items via AJAX
    fetch(`get_request_items.php?group_id=${groupId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let html = '';
                data.items.forEach(item => {
                    const statusOptions = `
                        <select name="statuses[]" class="form-select form-select-sm">
                            <option value="Pending" ${item.status === 'Pending' ? 'selected' : ''}>Pending</option>
                            <option value="Approved" ${item.status === 'Approved' ? 'selected' : ''}>Approved</option>
                            <option value="Rejected" ${item.status === 'Rejected' ? 'selected' : ''}>Rejected</option>
                        </select>
                    `;
                    
                    const approvedBadge = `<span class="badge bg-success status-badge-sm">Approved</span><input type="hidden" name="statuses[]" value="Approved">`;
                    
                    html += `
                        <tr>
                            <td>
                                <input type="hidden" name="item_ids[]" value="${item.id}">
                                <select name="consumable_ids[]" class="form-select form-select-sm item-select-edit edit-item-select" required>
                                    <option value="">-- Select Item --</option>
                                    ${item.options}
                                </select>
                            </td>
                            <td>
                                <input type="number" name="quantities[]" class="form-control form-control-sm quantity-input-edit edit-quantity-input" 
                                       value="${item.quantity}" min="1" required
                                       data-original="${item.quantity}"
                                       onchange="validateEditQuantity(this)">
                                <small class="text-muted edit-stock-hint">Max: ${item.max_stock} ${item.unit}</small>
                            </td>
                            <td>
                                <input type="text" name="descriptions[]" class="form-control form-control-sm" 
                                       value="${item.description.replace(/'/g, "\\'")}"
                                       placeholder="Purpose">
                            </td>
                            <td class="text-center">
                                ${item.status === 'Approved' ? approvedBadge : statusOptions}
                            </td>
                        </tr>
                    `;
                });
                modalBody.innerHTML = html;
            } else {
                modalBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center py-3 text-danger">
                            Failed to load items. Please try again.
                        </td>
                    </tr>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            modalBody.innerHTML = `
                <tr>
                    <td colspan="4" class="text-center py-3 text-danger">
                        Error loading items. Please try again.
                    </td>
                </tr>
            `;
        });
}

// EDIT GROUP BUTTON HANDLER - FIXED VERSION
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔧 Edit group button handler initializing...');
    
    // Use event delegation to ensure it works even with dynamically loaded content
    $(document).on('click', '.edit-group-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        console.log('✏️ Edit button clicked');
        
        // Get all data attributes
        const groupId = $(this).data('group-id');
        const groupCode = $(this).data('group-code');
        const employee = $(this).data('employee');
        const office = $(this).data('office');
        const approvedBy = $(this).data('approved-by');
        const supplyOfficer = $(this).data('supply-officer');
        const status = $(this).data('status');
        
        console.log('Edit data:', { groupId, groupCode, employee, office, approvedBy, supplyOfficer, status });
        
        // Validate required data
        if (!groupId) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Missing group ID'
            });
            return;
        }
        
        // Set modal fields
        document.getElementById('modal-group-id').value = groupId;
        document.getElementById('modal-group-code').textContent = groupCode || 'N/A';
        document.getElementById('modal-employee').value = employee || '';
        document.getElementById('modal-office').value = office || '';
        document.getElementById('modal-approved-by').value = approvedBy || '';
        document.getElementById('modal-supply-officer').value = supplyOfficer || '';
        document.getElementById('modal-group-status').value = status || 'Pending';
        
        // Load items for this group
        loadEditModalItems(groupId);
        
        // Show the correct modal - EDIT GROUP MODAL, not Edit Consumable Modal
        const editGroupModalElement = document.getElementById('editGroupModal');
        if (!editGroupModalElement) {
            console.error('Edit Group Modal not found!');
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Edit Group Modal not found. Please refresh the page.'
            });
            return;
        }
        
        let editGroupModal = bootstrap.Modal.getInstance(editGroupModalElement);
        if (!editGroupModal) {
            editGroupModal = new bootstrap.Modal(editGroupModalElement);
        }
        editGroupModal.show();
    });
});

// Delete confirmation function
function confirmDelete() {
    const itemId = document.getElementById('edit_item_id').value;
    const itemName = document.getElementById('edit_item_name').value;
    
    if (!itemId) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'No item selected for deletion.'
        });
        return;
    }
    
    Swal.fire({
        title: 'Are you sure?',
        html: `You are about to delete <strong>${escapeHtml(itemName)}</strong><br><br>
               <span class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i>This action cannot be undone!</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-trash-alt me-2"></i>Yes, delete it!',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancel',
        reverseButtons: true,
        showLoaderOnConfirm: true,
        preConfirm: () => {
            // Set the item ID in the delete form
            document.getElementById('delete_item_id').value = itemId;
            // Submit the delete form
            document.getElementById('deleteConsumableForm').submit();
        }
    });
}

// Helper function to escape HTML (already exists, but ensure it's there)
function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
}

// Function to load edit modal items
function loadEditModalItems(groupId) {
    const modalBody = document.getElementById('edit-items-body');
    
    // Show loading state
    modalBody.innerHTML = `
        <tr>
            <td colspan="4" class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted small">Loading items...</p>
            </td>
        </tr>
    `;
    
    // Fetch items via AJAX
    fetch(`get_request_items.php?group_id=${groupId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            console.log('📦 Items loaded:', data);
            
            if (data.success && data.items && data.items.length > 0) {
                let html = '';
                
                data.items.forEach(item => {
                    // Create status options dropdown
                    const statusOptions = `
                        <select name="statuses[]" class="form-select form-select-sm">
                            <option value="Pending" ${item.status === 'Pending' ? 'selected' : ''}>Pending</option>
                            <option value="Approved" ${item.status === 'Approved' ? 'selected' : ''}>Approved</option>
                            <option value="Rejected" ${item.status === 'Rejected' ? 'selected' : ''}>Rejected</option>
                        </select>
                    `;
                    
                    // Create approved badge for already approved items
                    const approvedBadge = `<span class="badge bg-success">Approved</span><input type="hidden" name="statuses[]" value="Approved">`;
                    
                    html += `
                        <tr>
                            <td>
                                <input type="hidden" name="item_ids[]" value="${item.id}">
                                <select name="consumable_ids[]" class="form-select form-select-sm item-select-edit" required>
                                    <option value="">-- Select Item --</option>
                                    ${item.options}
                                </select>
                            </td>
                            <td>
                                <input type="number" name="quantities[]" class="form-control form-control-sm quantity-input-edit edit-quantity-input" 
                                       value="${item.quantity}" min="1" required
                                       data-original="${item.quantity}"
                                       onchange="validateEditQuantity(this)">
                                <small class="text-muted d-block edit-stock-hint">Max: ${item.max_stock} ${item.unit || ''}</small>
                            </td>
                            <td>
                                <input type="text" name="descriptions[]" class="form-control form-control-sm" 
                                       value="${escapeHtml(item.description || '')}"
                                       placeholder="Purpose">
                            </td>
                            <td class="text-center">
                                ${item.status === 'Approved' ? approvedBadge : statusOptions}
                            </td>
                        </tr>
                    `;
                });
                
                modalBody.innerHTML = html;
                
            } else if (data.success && data.items && data.items.length === 0) {
                modalBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center py-4">
                            <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                            <p class="mb-0">No items found for this request.</p>
                        </td>
                    </tr>
                `;
            } else {
                modalBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center py-4 text-danger">
                            <i class="fas fa-exclamation-circle fa-2x mb-2"></i>
                            <p class="mb-0">${data.message || 'Failed to load items.'}</p>
                        </td>
                    </tr>
                `;
            }
        })
        .catch(error => {
            console.error('❌ Error loading items:', error);
            modalBody.innerHTML = `
                <tr>
                    <td colspan="4" class="text-center py-4 text-danger">
                        <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                        <p class="mb-0">Error loading items. Please try again.</p>
                        <small class="text-muted">${error.message}</small>
                    </td>
                </tr>
            `;
        });
}

// Helper function to escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
}

// Regenerate ID code for edit modal
function regenerateEditIdCode() {
    const itemName = document.getElementById('edit_item_name').value;
    const idCodeInput = document.getElementById('edit_identification');
    const prefix = itemName ? itemName.replace(/[^a-zA-Z]/g, '').substring(0, 3).toUpperCase() : 'ITM';
    const randomPart = Math.random().toString(36).substring(2, 10).toUpperCase();
    const newCode = `${prefix}-${randomPart}`;
    idCodeInput.value = newCode;
}

// Make sure the regenerate button works
$(document).ready(function() {
    // Add event listener for regenerate button if needed
    $(document).on('click', '[onclick="regenerateEditIdCode()"]', function(e) {
        e.preventDefault();
        regenerateEditIdCode();
    });
});

// Category and Stock Status Filter Functions
$(document).ready(function() {
    // Category filter change event
    $('#categoryFilter').on('change', function() {
        filterInventoryTable();
    });
    
    // Stock status filter change event
    $('#stockStatusFilter').on('change', function() {
        filterInventoryTable();
    });
});

// Search inventory function
function searchInventory() {
    const searchTerm = document.getElementById('inventorySearch').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#inventoryTableBody tr');
    let visibleCount = 0;
    
    rows.forEach(row => {
        if (row.id === 'noResultsMessage') return;
        
        // Get all searchable text from the row
        const itemName = row.querySelector('.item-name')?.textContent.toLowerCase() || '';
        const itemBrand = row.querySelector('.item-brand')?.textContent.toLowerCase() || '';
        const category = row.querySelector('.category-badge')?.textContent.toLowerCase() || '';
        const idCode = row.querySelector('.id-code')?.textContent.toLowerCase() || '';
        
        // Combine all searchable fields
        const searchableText = `${itemName} ${itemBrand} ${category} ${idCode}`;
        
        // Check if search term matches
        const matches = searchTerm === '' || searchableText.includes(searchTerm);
        
        if (matches) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Show "no results" message if needed
    const noResultsMsg = document.getElementById('noResultsMessage');
    if (visibleCount === 0) {
        if (!noResultsMsg) {
            const tbody = document.getElementById('inventoryTableBody');
            const messageRow = document.createElement('tr');
            messageRow.id = 'noResultsMessage';
            messageRow.innerHTML = `
                <td colspan="8" class="text-center py-4">
                    <div class="empty-state" style="padding: 2rem;">
                        <div class="empty-state-icon" style="width: 80px; height: 80px; font-size: 2rem;">
                            <i class="fas fa-search"></i>
                        </div>
                        <h6 class="mb-2">No items match your search</h6>
                        <p class="text-muted mb-0 small">Try different search terms</p>
                    </div>
                </td>
            `;
            tbody.appendChild(messageRow);
        }
    } else if (noResultsMsg) {
        noResultsMsg.remove();
    }
}

// Update filterInventoryTable function to work with search
function filterInventoryTable() {
    const selectedCategory = $('#categoryFilter').val();
    const selectedStock = $('#stockStatusFilter').val();
    const searchTerm = document.getElementById('inventorySearch')?.value.toLowerCase().trim() || '';
    
    $('#inventoryTableBody tr').each(function() {
        const row = $(this);
        if (row.attr('id') === 'noResultsMessage') return;
        
        const rowCategory = row.data('category');
        const rowStock = row.data('stock');
        
        // Get searchable text
        const itemName = row.find('.item-name').text().toLowerCase() || '';
        const itemBrand = row.find('.item-brand').text().toLowerCase() || '';
        const category = row.find('.category-badge').text().toLowerCase() || '';
        const idCode = row.find('.id-code').text().toLowerCase() || '';
        const searchableText = `${itemName} ${itemBrand} ${category} ${idCode}`;
        
        let categoryMatch = (selectedCategory === 'all' || rowCategory === selectedCategory);
        let stockMatch = (selectedStock === 'all' || rowStock === selectedStock);
        let searchMatch = searchTerm === '' || searchableText.includes(searchTerm);
        
        if (categoryMatch && stockMatch && searchMatch) {
            row.show();
        } else {
            row.hide();
        }
    });
    
    // Show message if no rows visible
    const visibleRows = $('#inventoryTableBody tr:visible').length;
    if (visibleRows === 0) {
        if ($('#noResultsMessage').length === 0) {
            $('#inventoryTableBody').append(`
                <tr id="noResultsMessage">
                    <td colspan="8" class="text-center py-4">
                        <div class="empty-state" style="padding: 2rem;">
                            <div class="empty-state-icon" style="width: 80px; height: 80px; font-size: 2rem;">
                                <i class="fas fa-filter"></i>
                            </div>
                            <h6 class="mb-2">No items match your filters</h6>
                            <p class="text-muted mb-0 small">Try adjusting your filter criteria</p>
                        </div>
                    </td>
                </tr>
            `);
        }
    } else {
        $('#noResultsMessage').remove();
    }
}

// Update clearFilters function to also clear search
function clearFilters() {
    $('#categoryFilter').val('all');
    $('#stockStatusFilter').val('all');
    if (document.getElementById('inventorySearch')) {
        document.getElementById('inventorySearch').value = '';
    }
    filterInventoryTable();
}

// View Consumable Item Details
function viewConsumable(id) {
    console.log('Viewing consumable ID:', id);
    
    // Fetch item details via AJAX
    fetch('get_consumable_details.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const item = data.item;
                
                // Populate basic info
                document.getElementById('view_item_name').textContent = item.item_name;
                document.getElementById('view_category').textContent = item.category || 'Uncategorized';
                document.getElementById('view_brand').textContent = item.brand || 'Generic';
                document.getElementById('view_unit').textContent = item.unit || 'N/A';
                document.getElementById('view_unit_small').textContent = item.unit || '';
                document.getElementById('view_identification').textContent = item.identification || '-';
                document.getElementById('view_quantity').textContent = item.quantity;
                
                // Add date added display
                if (item.created_at) {
                    const dateAdded = new Date(item.created_at);
                    const formattedDate = dateAdded.toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric' 
                    });
                    document.getElementById('view_date_added').textContent = formattedDate;
                } else {
                    document.getElementById('view_date_added').textContent = '—';
                }
                
                // Calculate thresholds based on unit
                const unit = (item.unit || '').toLowerCase();
                let criticalThreshold, lowThreshold;
                
                switch(unit) {
                    case 'pcs':
                        criticalThreshold = 30;
                        lowThreshold = 50;
                        break;
                    case 'unit':
                        criticalThreshold = 10;
                        lowThreshold = 20;
                        break;
                    case 'box':
                    case 'ream':
                        criticalThreshold = 10;
                        lowThreshold = 20;
                        break;
                    default:
                        criticalThreshold = 10;
                        lowThreshold = 20;
                }
                
                // Set threshold displays
                document.getElementById('view_critical_threshold').textContent = `≤ ${criticalThreshold} ${item.unit || ''}`;
                document.getElementById('view_low_threshold').textContent = `${criticalThreshold + 1} - ${lowThreshold} ${item.unit || ''}`;
                document.getElementById('view_available_threshold').textContent = `> ${lowThreshold} ${item.unit || ''}`;
                
                // Determine status
                let status, statusClass, statusDescription;
                if (item.quantity <= 0) {
                    status = 'Critical';
                    statusClass = 'critical';
                    statusDescription = 'Item is out of stock - Refill immediately';
                } else if (item.quantity <= criticalThreshold) {
                    status = 'Critical';
                    statusClass = 'critical';
                    statusDescription = 'Item is at critical level - Refill immediately';
                } else if (item.quantity <= lowThreshold) {
                    status = 'Low';
                    statusClass = 'low';
                    statusDescription = 'Item is running low - Reorder soon';
                } else {
                    status = 'Available';
                    statusClass = 'available';
                    statusDescription = 'Item has sufficient stock';
                }
                
                // Set status badge
                const statusBadge = document.getElementById('view_status_badge');
                statusBadge.className = `status-badge ${statusClass}`;
                statusBadge.innerHTML = `<i class="fas ${statusClass === 'critical' ? 'fa-times-circle' : (statusClass === 'low' ? 'fa-exclamation-triangle' : 'fa-check-circle')}"></i>${status}`;
                
                document.getElementById('view_status_description').textContent = statusDescription;
                
                // Calculate progress bar percentages
                const maxForProgress = lowThreshold * 2; // Use double low threshold as max for progress bar
                const criticalPercent = Math.min(100, (criticalThreshold / maxForProgress) * 100);
                const lowPercent = Math.min(100, ((lowThreshold - criticalThreshold) / maxForProgress) * 100);
                const availablePercent = Math.max(0, 100 - criticalPercent - lowPercent);
                
                document.getElementById('view_critical_bar').style.width = criticalPercent + '%';
                document.getElementById('view_low_bar').style.width = lowPercent + '%';
                document.getElementById('view_available_bar').style.width = availablePercent + '%';
                
                // Set max stock info
                if (item.max_stock) {
                    document.getElementById('view_max_stock_info').innerHTML = `
                        <span class="text-muted">Max Stock: ${item.max_stock} ${item.unit || ''}</span>
                    `;
                } else {
                    document.getElementById('view_max_stock_info').innerHTML = '';
                }
                
                // Load refill history
                console.log('Loading history for consumable ID:', id);
                loadRefillHistoryForView(id);
                
                // Show modal
                const viewModal = new bootstrap.Modal(document.getElementById('viewConsumableModal'));
                viewModal.show();
                
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'Failed to load item details.'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to load item details. Please try again.'
            });
        });
}

// Load refill history for view modal
function loadRefillHistoryForView(consumableId) {
    console.log('loadRefillHistoryForView called with ID:', consumableId);
    
    fetch(`get_refill_history.php?consumable_id=${consumableId}&limit=10`)
        .then(response => {
            console.log('Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('History data received:', data);
            const tbody = document.getElementById('view_refill_history_body');
            
            if (!tbody) {
                console.error('History tbody element not found!');
                return;
            }
            
            if (data.success && data.history.length > 0) {
                let html = '';
                data.history.forEach(record => {
                    // Determine change display
                    let changeDisplay = '';
                    let changeClass = '';
                    if (record.quantity_change > 0) {
                        changeDisplay = `+${record.quantity_change}`;
                        changeClass = 'text-success';
                    } else if (record.quantity_change < 0) {
                        changeDisplay = record.quantity_change;
                        changeClass = 'text-danger';
                    } else {
                        changeDisplay = '0';
                        changeClass = 'text-muted';
                    }
                    
                    html += `
                        <tr>
                            <td><small>${record.date}</small></td>
                            <td>
                                <span class="badge bg-${record.action_color}">
                                    <i class="fas ${record.action_icon} me-1"></i>${record.action_label}
                                </span>
                            </td>
                            <td class="${changeClass} fw-bold">${changeDisplay}</td>
                            <td>${record.previous_quantity}</td>
                            <td class="fw-bold">${record.new_quantity}</td>
                            <td><small>${record.performed_by || 'System'}</small></td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
                document.getElementById('view_full_history_link').href = `consumable_history.php?consumable=${consumableId}`;
            } else {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center py-3 text-muted">
                            <i class="fas fa-box-open me-2"></i>No history found
                        </td>
                    </tr>
                `;
            }
        })
        .catch(error => {
            console.error('Error loading history:', error);
            const tbody = document.getElementById('view_refill_history_body');
            if (tbody) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center py-3 text-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>Error loading history
                            <br><small>${error.message}</small>
                        </td>
                    </tr>
                `;
            }
        });
}

// Edit Consumable Item
function editConsumable(id) {
    console.log('Editing consumable ID:', id);
    
    // Check if modal exists
    const editModalElement = document.getElementById('editConsumableModal');
    if (!editModalElement) {
        console.error('Edit modal not found');
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Edit modal not found. Please refresh the page.'
        });
        return;
    }
    
    // Show loading indicator
    Swal.fire({
        title: 'Loading...',
        text: 'Please wait',
        allowOutsideClick: false,
        showConfirmButton: false,
        willOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Fetch item details via AJAX
    fetch('get_consumable_details.php?id=' + id)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            Swal.close();
            
            if (data.success) {
                const item = data.item;
                
                // Helper function to safely set element values
                const setElementValue = (id, value) => {
                    const element = document.getElementById(id);
                    if (element) {
                        element.value = value !== null && value !== undefined ? value : '';
                    } else {
                        console.warn(`Element with id '${id}' not found`);
                    }
                };
                
                // Populate basic fields
                setElementValue('edit_item_id', item.id);
                setElementValue('edit_item_name', item.item_name);
                setElementValue('edit_category', item.category);
                setElementValue('edit_quantity', item.quantity);
                setElementValue('edit_max_stock', item.max_stock);
                setElementValue('edit_unit', item.unit);
                setElementValue('edit_brand', item.brand);
                setElementValue('edit_identification', item.identification);
                
                // Calculate and display current status
                const unit = (item.unit || '').toLowerCase();
                const qty = parseInt(item.quantity) || 0;
                let status = 'Available';
                let statusClass = 'success';
                
                if (qty <= 0) {
                    status = 'Critical';
                    statusClass = 'danger';
                } else {
                    switch(unit) {
                        case 'pcs':
                            if (qty <= 30) {
                                status = 'Critical';
                                statusClass = 'danger';
                            } else if (qty <= 50) {
                                status = 'Low';
                                statusClass = 'warning';
                            }
                            break;
                        case 'unit':
                            if (qty <= 10) {
                                status = 'Critical';
                                statusClass = 'danger';
                            } else if (qty <= 20) {
                                status = 'Low';
                                statusClass = 'warning';
                            }
                            break;
                        case 'box':
                        case 'ream':
                            if (qty <= 10) {
                                status = 'Critical';
                                statusClass = 'danger';
                            } else if (qty <= 20) {
                                status = 'Low';
                                statusClass = 'warning';
                            }
                            break;
                        default:
                            if (qty <= 10) {
                                status = 'Critical';
                                statusClass = 'danger';
                            } else if (qty <= 20) {
                                status = 'Low';
                                statusClass = 'warning';
                            }
                    }
                }
                
                // Update status display - check if element exists
                const statusDisplay = document.getElementById('edit_status_display');
                if (statusDisplay) {
                    statusDisplay.innerHTML = `<span class="badge bg-${statusClass}">${status}</span>`;
                } else {
                    console.warn('Status display element not found');
                    // If status display is missing, we can still show the modal
                }
                
                // Show the modal
                try {
                    const editModal = new bootstrap.Modal(editModalElement);
                    editModal.show();
                } catch (modalError) {
                    console.error('Error showing modal:', modalError);
                    // Fallback: use jQuery if available
                    if (typeof $ !== 'undefined') {
                        $(editModalElement).modal('show');
                    }
                }
                
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'Failed to load item details.'
                });
            }
        })
        .catch(error => {
            Swal.close();
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to load item details. Please try again.'
            });
        });
}

// Highlight critical items when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Check URL parameter for specific critical item
    const urlParams = new URLSearchParams(window.location.search);
    const highlightCritical = urlParams.get('highlight_critical');
    
    if (highlightCritical) {
        highlightCriticalItem(highlightCritical);
    }
    
    // Check session alerts for multiple critical items
    <?php if (!empty($critical_alerts)): ?>
        <?php foreach ($critical_alerts as $alert): ?>
            highlightCriticalItem(<?php echo $alert['id']; ?>);
        <?php endforeach; ?>
        
        // Show summary notification
        Swal.fire({
            icon: 'warning',
            title: 'Critical Stock Alert',
            html: '<?php echo count($critical_alerts); ?> item(s) have reached critical level!',
            timer: 5000,
            showConfirmButton: true,
            confirmButtonColor: '#dc3545'
        });
    <?php endif; ?>
});

// Function to highlight a critical item
function highlightCriticalItem(itemId) {
    // Find the row by searching through all rows
    const rows = document.querySelectorAll('#inventoryTableBody tr');
    let foundRow = null;
    
    for (let row of rows) {
        // Check if this row contains our item
        const viewBtn = row.querySelector('button[onclick*="viewConsumable(' + itemId + ')"]');
        if (viewBtn) {
            foundRow = row;
            break;
        }
    }
    
    if (foundRow) {
        // Scroll to the row
        foundRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        // Add highlight effect
        foundRow.style.transition = 'all 0.5s ease';
        foundRow.style.backgroundColor = '#fff3cd';
        foundRow.style.boxShadow = '0 0 0 3px #ffc107';
        foundRow.style.borderLeft = '4px solid #dc3545';
        
        // Also highlight the stock badge
        const stockBadge = foundRow.querySelector('.stock-badge.critical');
        if (stockBadge) {
            stockBadge.style.transition = 'all 0.3s ease';
            stockBadge.style.transform = 'scale(1.05)';
            stockBadge.style.boxShadow = '0 0 0 3px #ffc107';
        }
        
        // Remove highlight after 5 seconds but keep the critical styling
        setTimeout(function() {
            foundRow.style.backgroundColor = '';
            foundRow.style.boxShadow = '';
            if (stockBadge) {
                stockBadge.style.transform = '';
                stockBadge.style.boxShadow = '';
            }
        }, 5000);
    }
}

// Update the DataTable initialization to work with filters
window.initConsumables = function() {
    if ($.fn.DataTable) {
        // Destroy existing instances first
        if ($.fn.DataTable.isDataTable('#consumablesTable')) {
            $('#consumablesTable').DataTable().destroy();
        }
        if ($.fn.DataTable.isDataTable('#historyTable')) {
            $('#historyTable').DataTable().destroy();
        }
        
        // Initialize history table with date sorting (column index 2 is the date column)
        $('#historyTable').DataTable({ 
            "pageLength": 25,
            "order": [[2, "desc"]], // Sort by date column in descending order (latest first)
            "columnDefs": [
                { "orderable": false, "targets": [3, 6] } // Disable sorting on Items and Actions columns
            ],
            "drawCallback": function(settings) {
                console.log('History table redrawn');
                const urlParams = new URLSearchParams(window.location.search);
                const highlightCode = urlParams.get('highlight');
                if (highlightCode && !window.highlightCompleted && !window.isHighlighting) {
                    setTimeout(function() {
                        highlightRequest(highlightCode);
                    }, 100);
                }
            }
        });
    }
    
    // Reset add consumable modal when closed
    $('#addConsumableModal').on('hidden.bs.modal', function () {
        const container = document.getElementById('consumable-items-container');
        if (container) {
            container.innerHTML = '';
            consumableRowCount = 1;
            
            const firstRow = document.createElement('div');
            firstRow.className = 'consumable-item-row';
            firstRow.id = 'consumable-row-1';
            firstRow.innerHTML = `
                <div class="row g-3 align-items-end mb-3 pb-3 border-bottom">
                    <div class="col-md-6">
                        <label class="small fw-bold text-primary">Item Name <span class="text-danger">*</span></label>
                        <input type="text" name="item_name[]" class="form-control" placeholder="e.g. A4 Bond Paper" required onchange="updateIdCodeFromName(1)">
                    </div>
                    <div class="col-md-3">
                        <label class="small fw-bold text-primary">Category</label>
                        <input type="text" name="category[]" class="form-control" placeholder="e.g. Office Supplies">
                    </div>
                    <div class="col-md-3">
                        <label class="small fw-bold text-primary">Quantity <span class="text-danger">*</span></label>
                        <input type="number" name="quantity[]" class="form-control" placeholder="0" required>
                    </div>
                    <div class="col-md-3">
                        <label class="small fw-bold text-primary">Unit</label>
                        <input type="text" name="unit[]" class="form-control" placeholder="e.g. Reams, Pcs">
                    </div>
                    <div class="col-md-3">
                        <label class="small fw-bold text-primary">Brand</label>
                        <input type="text" name="brand[]" class="form-control" placeholder="Brand name">
                    </div>
                    <div class="col-md-4">
                        <label class="small fw-bold text-primary">ID Code <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" name="identification[]" class="form-control bg-light" id="idcode-1" readonly placeholder="Auto-generated" required>
                            <button type="button" class="btn btn-outline-secondary" onclick="regenerateIdCode(1)" title="Generate New Code">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                        <small class="text-muted">Auto-generated unique code</small>
                    </div>
                    <div class="col-md-2 text-end">
                        <button type="button" class="btn btn-outline-danger remove-consumable-btn" onclick="removeConsumableRow(1)" style="display: none;">
                            <i class="fas fa-trash-alt me-1"></i> Remove
                        </button>
                    </div>
                </div>
            `;
            container.appendChild(firstRow);
            
            const idCodeInput = document.getElementById('idcode-1');
            if (idCodeInput) {
                idCodeInput.value = generateIdCode('');
            }
        }
    });
    
    // Reset request items modal when closed
    $('#requestItemsModal').on('hidden.bs.modal', function () {
        const container = document.getElementById('items-container');
        if (container) {
            container.innerHTML = '';
            itemRowCount = 1;
            
            const firstRow = document.createElement('div');
            firstRow.className = 'item-row';
            firstRow.id = 'item-row-1';
            firstRow.innerHTML = `
                <button type="button" class="btn btn-outline-danger btn-sm remove-item-btn" onclick="removeItemRow(1)" style="display: none;">
                    <i class="fas fa-times"></i>
                </button>
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="small fw-bold">Search Item</label>
                        <input type="text" class="form-control item-search" placeholder="Type to filter items..." onkeyup="filterItems(this)">
                    </div>
                    <div class="col-md-6">
                        <label class="small fw-bold">Select Item <span class="text-danger">*</span></label>
                        <select name="item_id[]" class="form-select item-select" required onchange="updateStockHint(this)">
                            <option value="">-- Choose Item --</option>
                            <?php
                            // Re-fetch items to populate the select options
                            $items = $db->query("SELECT * FROM consumables ORDER BY item_name ASC");
                            while($item = $items->fetch(PDO::FETCH_ASSOC)):
                                $unit = strtolower(trim($item['unit'] ?? ''));
                                $qty = $item['quantity'];
                                
                                // Determine if item is Critical based on thresholds
                                $is_critical = false;
                                if ($qty <= 0) {
                                    $is_critical = true;
                                } else {
                                    switch ($unit) {
                                        case 'pcs':
                                            if ($qty <= 30) $is_critical = true;
                                            break;
                                        case 'unit':
                                            if ($qty <= 10) $is_critical = true;
                                            break;
                                        case 'box':
                                        case 'ream':
                                            if ($qty <= 10) $is_critical = true;
                                            break;
                                        default:
                                            if ($qty <= 10) $is_critical = true;
                                    }
                                }
                                
                                // Determine status class for styling
                                $status_class = '';
                                $status_text = '';
                                if ($is_critical) {
                                    $status_class = 'text-danger fw-bold';
                                    $status_text = ' (CRITICAL - Cannot request)';
                                } elseif ($qty <= 20 && ($unit == 'unit' || $unit == 'box' || $unit == 'ream')) {
                                    $status_class = 'text-warning fw-bold';
                                    $status_text = ' (LOW STOCK)';
                                } elseif ($qty <= 50 && $unit == 'pcs') {
                                    $status_class = 'text-warning fw-bold';
                                    $status_text = ' (LOW STOCK)';
                                }
                            ?>
                            <option value="<?php echo $item['id']; ?>" 
                                    data-max="<?php echo $item['quantity']; ?>" 
                                    data-unit="<?php echo $item['unit']; ?>"
                                    data-critical="<?php echo $is_critical ? '1' : '0'; ?>"
                                    <?php echo $is_critical ? 'disabled class="text-danger"' : ''; ?>>
                                <?php echo htmlspecialchars($item['item_name']); ?> 
                                (Available: <?php echo $item['quantity']; ?> <?php echo $item['unit']; ?>)
                                <?php echo $status_text; ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                        <small class="text-muted" id="stock-status-hint-1"></small>
                    </div>
                    <div class="col-md-3">
                        <label class="small fw-bold">Quantity <span class="text-danger">*</span></label>
                        <input type="number" name="req_quantity[]" class="form-control quantity-input" min="1" required onchange="validateQuantity(this)">
                        <small class="text-muted stock-hint"></small>
                    </div>
                    <div class="col-md-3">
                        <label class="small fw-bold">Purpose/Description</label>
                        <input type="text" name="description[]" class="form-control" placeholder="e.g. Office use">
                    </div>
                </div>
            `;
            container.appendChild(firstRow);
        }
    });
};

// Initialize everything
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Check for highlight parameter in URL - FIXED VERSION
document.addEventListener('DOMContentLoaded', function() {
    // Get URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const highlightCode = urlParams.get('highlight');
    
    if (highlightCode) {
        console.log('🎯 URL contains highlight parameter:', highlightCode);
        
        // Wait for DataTable to be fully initialized
        let initAttempts = 0;
        const maxAttempts = 10;
        
        function waitForTable() {
            initAttempts++;
            
            // Check if DataTable is initialized and table has rows
            if ($.fn.DataTable && $('#historyTable').hasClass('dataTable') && 
                $('#historyTable').DataTable().rows().count() > 0) {
                
                console.log('✅ DataTable ready, highlighting...');
                
                // DEBUG: Log all group codes in the table
                debugTableContents();
                
                setTimeout(function() {
                    highlightRequest(highlightCode);
                }, 300);
                
            } else if (initAttempts < maxAttempts) {
                console.log(`⏳ Waiting for DataTable (attempt ${initAttempts}/${maxAttempts})...`);
                setTimeout(waitForTable, 500);
            } else {
                console.log('⚠️ DataTable not ready after max attempts');
                debugTableContents(); // Debug even if not ready
                setTimeout(function() {
                    window.isHighlighting = false;
                    highlightRequest(highlightCode);
                }, 500);
            }
        }
        
        // Start waiting
        waitForTable();
    }
});

// Function to highlight and scroll to a specific request
function highlightRequest(groupCode) {
    console.log('🔍 Searching for request:', groupCode);
    
    // Flag to prevent recursive loops
    if (window.isHighlighting) {
        console.log('Already highlighting, skipping...');
        return;
    }
    window.isHighlighting = true;
    
    // Clean the group code - remove any extra spaces or characters
    const cleanGroupCode = groupCode ? groupCode.toString().trim() : '';
    console.log('Cleaned group code:', cleanGroupCode);
    
    // FIRST APPROACH: Search through DataTable's data (all pages)
    if ($.fn.DataTable && $('#historyTable').hasClass('dataTable')) {
        const table = $('#historyTable').DataTable();
        
        // Get all data from the table (including paginated rows)
        const allData = table.rows().data();
        console.log(`Total records in DataTable: ${allData.length}`);
        
        // Search through all data to find the matching row index
        let foundIndex = -1;
        for (let i = 0; i < allData.length; i++) {
            // The group code is in the first column (index 0)
            const rowData = allData[i];
            // The group code element is in the first column's HTML
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = rowData[0]; // First column HTML
            const codeSpan = tempDiv.querySelector('.group-code');
            if (codeSpan && codeSpan.textContent.trim() === cleanGroupCode) {
                foundIndex = i;
                console.log(`✅ Found matching row at index ${i}`);
                break;
            }
        }
        
        if (foundIndex !== -1) {
            // Go to the page containing this row
            const pageInfo = table.page.info();
            const rowsPerPage = pageInfo.length;
            const targetPage = Math.floor(foundIndex / rowsPerPage);
            
            console.log(`Navigating to page ${targetPage + 1} (row ${foundIndex})`);
            
            // Change to the correct page
            table.page(targetPage).draw(false);
            
            // After page change, find and highlight the row
            setTimeout(function() {
                // Now search in the DOM for the row (should be visible now)
                const rows = document.querySelectorAll('.history-table tbody tr');
                for (let row of rows) {
                    const groupCodeElement = row.querySelector('.group-code');
                    if (groupCodeElement && groupCodeElement.textContent.trim() === cleanGroupCode) {
                        console.log('✅ Found row after page change!');
                        
                        // Scroll to the row
                        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        
                        // Add highlight effect
                        row.style.transition = 'all 0.5s ease';
                        row.style.backgroundColor = '#fff3cd';
                        row.style.boxShadow = '0 0 0 3px #ffc107';
                        
                        // Also highlight the group code badge
                        groupCodeElement.style.transition = 'all 0.3s ease';
                        groupCodeElement.style.backgroundColor = '#ffc107';
                        groupCodeElement.style.color = '#000';
                        groupCodeElement.style.transform = 'scale(1.05)';
                        
                        // Mark as completed
                        window.highlightCompleted = true;
                        
                        // Remove highlight after 3 seconds
                        setTimeout(function() {
                            row.style.backgroundColor = '';
                            row.style.boxShadow = '';
                            groupCodeElement.style.backgroundColor = '';
                            groupCodeElement.style.color = '';
                            groupCodeElement.style.transform = '';
                            window.isHighlighting = false;
                        }, 3000);
                        
                        break;
                    }
                }
            }, 300);
            
            return;
        }
    }
    
    // SECOND APPROACH: Direct DOM search (fallback)
    console.log('Trying direct DOM search...');
    const rows = document.querySelectorAll('.history-table tbody tr');
    console.log(`Total rows in DOM: ${rows.length}`);
    
    let targetRow = null;
    let targetGroupCode = null;
    let targetFound = false;
    
    for (let row of rows) {
        const groupCodeElement = row.querySelector('.group-code');
        if (groupCodeElement) {
            const rowCode = groupCodeElement.textContent.trim();
            console.log(`Row code: "${rowCode}" vs search code: "${cleanGroupCode}"`);
            
            if (rowCode === cleanGroupCode) {
                targetRow = row;
                targetGroupCode = groupCodeElement;
                targetFound = true;
                console.log('✅ Exact match found in DOM!');
                break;
            }
        }
    }
    
    if (targetFound && targetRow) {
        // Scroll to the row
        targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        // Add highlight effect
        targetRow.style.transition = 'all 0.5s ease';
        targetRow.style.backgroundColor = '#fff3cd';
        targetRow.style.boxShadow = '0 0 0 3px #ffc107';
        
        if (targetGroupCode) {
            targetGroupCode.style.transition = 'all 0.3s ease';
            targetGroupCode.style.backgroundColor = '#ffc107';
            targetGroupCode.style.color = '#000';
            targetGroupCode.style.transform = 'scale(1.05)';
        }
        
        window.highlightCompleted = true;
        
        setTimeout(function() {
            targetRow.style.backgroundColor = '';
            targetRow.style.boxShadow = '';
            if (targetGroupCode) {
                targetGroupCode.style.backgroundColor = '';
                targetGroupCode.style.color = '';
                targetGroupCode.style.transform = '';
            }
            window.isHighlighting = false;
        }, 3000);
        
    } else {
        console.log('❌ Request not found anywhere:', cleanGroupCode);
        
        // Check if we're on the wrong page and need to search all pages
        if ($.fn.DataTable && $('#historyTable').hasClass('dataTable')) {
            const table = $('#historyTable').DataTable();
            
            // Try searching by group code using DataTable's search
            Swal.fire({
                icon: 'info',
                title: 'Searching for Request',
                text: `Looking for request ${cleanGroupCode}...`,
                showConfirmButton: false,
                timer: 1500
            });
            
            // Clear any existing search and search for this specific code
            table.search('').columns().search('').draw();
            
            // Search for the exact group code
            table.column(0).search('^' + cleanGroupCode + '$', true, false).draw();
            
            // Check if found after search
            setTimeout(function() {
                if (table.rows({ search: 'applied' }).count() > 0) {
                    // Found it with search, now highlight it
                    window.isHighlighting = false;
                    highlightRequest(cleanGroupCode);
                } else {
                    // Still not found
                    Swal.fire({
                        icon: 'warning',
                        title: 'Request Not Found',
                        text: `The request ${cleanGroupCode} could not be found in the table.`,
                        timer: 3000,
                        showConfirmButton: false
                    });
                    window.isHighlighting = false;
                    
                    // Clear the search
                    table.search('').columns().search('').draw();
                }
            }, 500);
        } else {
            window.isHighlighting = false;
        }
    }
}

// Add this debug function
function debugTableContents() {
    console.log('=== DEBUG: Table Contents ===');
    const rows = document.querySelectorAll('.history-table tbody tr');
    console.log(`Total rows in table: ${rows.length}`);
    
    rows.forEach((row, index) => {
        const groupCodeElement = row.querySelector('.group-code');
        if (groupCodeElement) {
            console.log(`Row ${index + 1}: group_code = "${groupCodeElement.textContent.trim()}"`);
        } else {
            console.log(`Row ${index + 1}: No group-code element found`);
        }
    });
    console.log('=== END DEBUG ===');
}

// Function to open view details modal for a specific group
function viewRequestDetails(groupId) {
    const viewButton = document.querySelector(`.view-request-btn[data-group-id="${groupId}"]`);
    if (viewButton) {
        viewButton.click();
    }
}
</script>

<?php include '../includes/footer.php'; ?>