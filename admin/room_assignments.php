<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// 1. Change to category_id to match your new URL structure
$category_id = $_GET['category_id'] ?? '';
$room_id = $_GET['room_id'] ?? '';

// 2. Validate IDs
if (empty($category_id) || empty($room_id)) {
    header("Location: inventory_categories.php");
    exit();
}

// 3. Get category details using ID instead of type_code
$category_query = "SELECT * FROM location_types WHERE id = ? AND is_active = 1";
$category_stmt = $db->prepare($category_query);
$category_stmt->execute([$category_id]);
$category_info = $category_stmt->fetch(PDO::FETCH_ASSOC);

if (!$category_info) {
    header("Location: inventory_categories.php");
    exit();
}

// Set CSS variables for dynamic colors
$primary_color = $category_info['color_primary'];
$secondary_color = $category_info['color_secondary'];
$primary_rgb = hexdec(substr($primary_color, 1, 2)) . ',' . hexdec(substr($primary_color, 3, 2)) . ',' . hexdec(substr($primary_color, 5, 2));

// 4. Update Room Query to use category_id
$room_query = "SELECT l.*, lt.type_name as location_type_name, lt.type_code as location_type_code,
               lt.icon_class, lt.equipment_label,
               f.full_name as manager_name, f.email as manager_email
               FROM locations l 
               LEFT JOIN location_types lt ON l.location_type_id = lt.id
               LEFT JOIN users f ON l.facilitator_id = f.id
               WHERE l.id = ? AND l.location_type_id = ?";
$room_stmt = $db->prepare($room_query);
$room_stmt->execute([$room_id, $category_id]);
$room = $room_stmt->fetch(PDO::FETCH_ASSOC);

if (!$room) {
    header("Location: inventory_rooms.php?category_id=" . $category_id);
    exit();
}

// Handle status changes
if ($_POST && isset($_POST['action'])) {
    try {
        // Get common POST variables
        $action = $_POST['action'];
        $equipment_id = $_POST['equipment_id'] ?? 0;
        $equipment_type = $_POST['equipment_type'] ?? '';
        $source_table = $_POST['source_table'] ?? '';
        
        // Determine which table to use
        if (empty($source_table) && !empty($equipment_type)) {
            // Map equipment type to table name
            switch ($equipment_type) {
                case 'computer':
                    $source_table = 'computer_inventory';
                    break;
                case 'kitchen':
                    $source_table = 'kitchen_equipment';
                    break;
                case 'office':
                    $source_table = 'office_equipment';
                    break;
                case 'lab':
                    $source_table = 'lab_equipment';
                    break;
                case 'general':
                    $source_table = 'general_equipment';
                    break;
                default:
                    $source_table = 'computer_inventory';
            }
        }
        
        switch ($action) {
            case 'change_status':
                $new_status = $_POST['new_status'];
                
                // Get equipment details for history
                $get_query = "SELECT * FROM {$source_table} WHERE id = ?";
                $get_stmt = $db->prepare($get_query);
                $get_stmt->execute([$equipment_id]);
                $equipment = $get_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($new_status === 'available') {
                    // Update equipment status and clear assignment
                    $update_query = "UPDATE {$source_table} SET status = ?, assigned_to = NULL, assigned_date = NULL WHERE id = ?";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->execute([$new_status, $equipment_id]);
                    
                    // Log to assignment history - equipment returned to available
                    $log_query = "INSERT INTO assignment_history (computer_id, location_id, user_id, assigned_by, notes, status, returned_date, equipment_type, equipment_table) 
                                VALUES (?, ?, ?, ?, ?, 'returned', NOW(), ?, ?)";
                    $log_stmt = $db->prepare($log_query);
                    $log_stmt->execute([
                        $equipment_id, 
                        $room_id, 
                        $equipment['assigned_to'] ?? $_SESSION['user_id'], 
                        $_SESSION['user_id'], 
                        'Equipment set to available',
                        $source_table,
                        $source_table
                    ]);
                    
                    $_SESSION['success_message'] = "Equipment set to available successfully!";
                    
                } elseif ($new_status === 'maintenance') {
                    $maintenance_reason = $_POST['maintenance_reason'] ?? 'Maintenance required';
                    
                    // Update equipment status
                    $update_query = "UPDATE {$source_table} SET status = ? WHERE id = ?";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->execute([$new_status, $equipment_id]);
                    
                    // Log to assignment history - sent to maintenance
                    $log_query = "INSERT INTO assignment_history (computer_id, location_id, user_id, assigned_by, notes, status, maintenance_reason, equipment_type, equipment_table) 
                                VALUES (?, ?, ?, ?, ?, 'maintenance', ?, ?, ?)";
                    $log_stmt = $db->prepare($log_query);
                    $log_stmt->execute([
                        $equipment_id, 
                        $room_id, 
                        $equipment['assigned_to'] ?? $_SESSION['user_id'], 
                        $_SESSION['user_id'], 
                        'Equipment sent for maintenance',
                        $maintenance_reason,
                        $source_table,
                        $source_table
                    ]);
                    
                    $_SESSION['success_message'] = "Equipment set to maintenance successfully!";
                }
                
                header("Location: room_assignments.php?room_id=" . $room_id . "&category_id=" . $category_id);
                exit();
                break;
                
            case 'bulk_status_change':
                $equipment_ids_raw = $_POST['equipment_ids'] ?? '';
                $equipment_ids = explode(',', $equipment_ids_raw);
                $new_status = $_POST['bulk_status'];
                $maintenance_reason = $_POST['bulk_maintenance_reason'] ?? 'Bulk maintenance action';
                
                if (!empty($equipment_ids)) {
                    $updated_count = 0;
                    $errors = [];
                    
                    foreach ($equipment_ids as $item) {
                        if (empty($item)) continue;
                        
                        // Parse the item value (format: equipment_type:equipment_id)
                        $parts = explode(':', $item);
                        if (count($parts) !== 2) {
                            $errors[] = "Invalid item format: $item";
                            continue;
                        }
                        
                        $item_type = $parts[0];
                        $item_id = $parts[1];
                        
                        // Determine the correct table
                        $item_table = '';
                        switch ($item_type) {
                            case 'computer':
                                $item_table = 'computer_inventory';
                                break;
                            case 'kitchen':
                                $item_table = 'kitchen_equipment';
                                break;
                            case 'office':
                                $item_table = 'office_equipment';
                                break;
                            case 'lab':
                                $item_table = 'lab_equipment';
                                break;
                            default:
                                $item_table = 'general_equipment';
                        }
                        
                        try {
                            // Get equipment details for history
                            $get_query = "SELECT * FROM {$item_table} WHERE id = ?";
                            $get_stmt = $db->prepare($get_query);
                            $get_stmt->execute([$item_id]);
                            $equipment = $get_stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($new_status === 'available') {
                                // Update equipment status
                                $update_query = "UPDATE {$item_table} SET status = ?, assigned_to = NULL, assigned_date = NULL WHERE id = ?";
                                $update_stmt = $db->prepare($update_query);
                                $update_stmt->execute([$new_status, $item_id]);
                                
                                // Log to assignment history
                                $log_query = "INSERT INTO assignment_history (computer_id, location_id, user_id, assigned_by, notes, status, returned_date, equipment_type, equipment_table) 
                                            VALUES (?, ?, ?, ?, ?, 'returned', NOW(), ?, ?)";
                                $log_stmt = $db->prepare($log_query);
                                $log_stmt->execute([
                                    $item_id, 
                                    $room_id, 
                                    $equipment['assigned_to'] ?? $_SESSION['user_id'], 
                                    $_SESSION['user_id'], 
                                    'Bulk action: Set to available',
                                    $item_table,
                                    $item_table
                                ]);
                                
                            } elseif ($new_status === 'maintenance') {
                                // Update equipment status
                                $update_query = "UPDATE {$item_table} SET status = ? WHERE id = ?";
                                $update_stmt = $db->prepare($update_query);
                                $update_stmt->execute([$new_status, $item_id]);
                                
                                // Log to assignment history
                                $log_query = "INSERT INTO assignment_history (computer_id, location_id, user_id, assigned_by, notes, status, maintenance_reason, equipment_type, equipment_table) 
                                            VALUES (?, ?, ?, ?, ?, 'maintenance', ?, ?, ?)";
                                $log_stmt = $db->prepare($log_query);
                                $log_stmt->execute([
                                    $item_id, 
                                    $room_id, 
                                    $equipment['assigned_to'] ?? $_SESSION['user_id'], 
                                    $_SESSION['user_id'], 
                                    'Bulk action: Sent for maintenance',
                                    $maintenance_reason,
                                    $item_table,
                                    $item_table
                                ]);
                            }
                            
                            $updated_count++;
                            
                        } catch (Exception $e) {
                            $errors[] = "Error updating item ID $item_id: " . $e->getMessage();
                        }
                    }
                    
                    if ($updated_count > 0) {
                        $_SESSION['success_message'] = $updated_count . " items updated successfully!";
                    }
                    if (!empty($errors)) {
                        $_SESSION['error_message'] = "Some items failed: " . implode(", ", $errors);
                    }
                    
                    header("Location: room_assignments.php?room_id=" . $room_id . "&category_id=" . $category_id);
                    exit();
                }
                break;
                
            case 'assign_user':
                $user_id = $_POST['user_id'] == '' ? null : $_POST['user_id'];
                
                // Validate inputs
                if (!$equipment_id || !$source_table) {
                    throw new Exception("Missing equipment information");
                }
                
                // Get equipment details
                $get_query = "SELECT * FROM {$source_table} WHERE id = ?";
                $get_stmt = $db->prepare($get_query);
                $get_stmt->execute([$equipment_id]);
                $equipment = $get_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$equipment) {
                    throw new Exception("Equipment not found");
                }
                
                if ($user_id) {
                    // Get user name for assignment history
                    $user_query = "SELECT full_name, email FROM users WHERE id = ?";
                    $user_stmt = $db->prepare($user_query);
                    $user_stmt->execute([$user_id]);
                    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$user) {
                        throw new Exception("User not found");
                    }
                    
                    // Update equipment - set assigned_to and status
                    $update_query = "UPDATE {$source_table} SET assigned_to = ?, status = 'assigned', assigned_date = NOW() WHERE id = ?";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->execute([$user_id, $equipment_id]);
                    
                    // Log to assignment history
                    $log_query = "INSERT INTO assignment_history (computer_id, location_id, user_id, assigned_by, notes, status, equipment_type, equipment_table) 
                                VALUES (?, ?, ?, ?, ?, 'active', ?, ?)";
                    $log_stmt = $db->prepare($log_query);
                    $log_stmt->execute([
                        $equipment_id, 
                        $room_id, 
                        $user_id, 
                        $_SESSION['user_id'], 
                        'Assigned to: ' . $user['full_name'],
                        $source_table,
                        $source_table
                    ]);
                    
                    $_SESSION['success_message'] = "Equipment assigned to " . $user['full_name'] . " successfully!";
                    
                } else {
                    // Unassign equipment
                    $update_query = "UPDATE {$source_table} SET assigned_to = NULL, assigned_date = NULL, status = 'available' WHERE id = ?";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->execute([$equipment_id]);
                    
                    // Log unassignment
                    $log_query = "INSERT INTO assignment_history (computer_id, location_id, user_id, assigned_by, notes, status, returned_date, equipment_type, equipment_table) 
                                VALUES (?, ?, ?, ?, ?, 'returned', NOW(), ?, ?)";
                    $log_stmt = $db->prepare($log_query);
                    $log_stmt->execute([
                        $equipment_id, 
                        $room_id, 
                        $_SESSION['user_id'], 
                        $_SESSION['user_id'], 
                        'Unassigned from user',
                        $source_table,
                        $source_table
                    ]);
                    
                    $_SESSION['success_message'] = "Equipment unassigned successfully!";
                }
                
                header("Location: room_assignments.php?room_id=" . $room_id . "&category_id=" . $category_id);
                exit();
                break;
                
            case 'complete_maintenance':
                $history_id = $_POST['history_id'];
                $fix_details = $_POST['fix_details'];
                
                // Get the history record
                $history_query = "SELECT * FROM assignment_history WHERE id = ?";
                $history_stmt = $db->prepare($history_query);
                $history_stmt->execute([$history_id]);
                $history = $history_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($history) {
                    // Determine equipment table from history
                    $history_table = $history['equipment_table'] ?? 'computer_inventory';
                    
                    // Update the history record
                    $update_history = "UPDATE assignment_history 
                                       SET status = 'returned', 
                                           returned_date = NOW(), 
                                           maintenance_fix_details = ?,
                                           maintenance_resolved_date = NOW(),
                                           maintenance_resolved_by = ?
                                       WHERE id = ?";
                    $update_stmt = $db->prepare($update_history);
                    $update_stmt->execute([$fix_details, $_SESSION['user_id'], $history_id]);
                    
                    // Update equipment status to available
                    $update_equipment = "UPDATE {$history_table} SET status = 'available' WHERE id = ?";
                    $update_equip_stmt = $db->prepare($update_equipment);
                    $update_equip_stmt->execute([$history['computer_id']]);
                    
                    $_SESSION['success_message'] = "Maintenance completed! Equipment is now available.";
                }
                
                header("Location: room_assignments.php?room_id=" . $room_id . "&category_id=" . $category_id);
                exit();
                break;
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        header("Location: room_assignments.php?room_id=" . $room_id . "&category_id=" . $category_id);
        exit();
    }
}

// Get success/error messages from session
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Get ALL equipment for this room from ALL tables
$all_equipment_data = [];

// 1. Get computer equipment
$computer_query = "SELECT 
    'computer' as equipment_type,
    'computer_inventory' as source_table,
    ci.id,
    ci.item_number,
    ci.computer_set_description as equipment_name,
    ci.processor,
    ci.ram,
    ci.storage,
    ci.operating_system,
    ci.status,
    ci.location_id,
    ci.assigned_to,
    ci.assigned_date,
    ci.condition_status,
    ci.remarks,
    ci.campus,
    u.full_name as assigned_user_name
    FROM computer_inventory ci
    LEFT JOIN users u ON ci.assigned_to = u.id
    WHERE ci.location_id = ? AND (ci.is_condemned IS NULL OR ci.is_condemned = FALSE)
    ORDER BY ci.item_number ASC";
$computer_stmt = $db->prepare($computer_query);
$computer_stmt->execute([$room_id]);
$computers = $computer_stmt->fetchAll(PDO::FETCH_ASSOC);
$all_equipment_data = array_merge($all_equipment_data, $computers);

// 2. Get kitchen equipment
$kitchen_query = "SELECT 
    'kitchen' as equipment_type,
    'kitchen_equipment' as source_table,
    ke.id,
    ke.item_number,
    ke.equipment_name,
    ke.brand,
    ke.model,
    NULL as processor,
    NULL as ram,
    NULL as storage,
    NULL as operating_system,
    ke.status,
    ke.location_id,
    ke.assigned_to,
    ke.assigned_date,
    ke.condition_status,
    ke.remarks,
    ke.campus,
    u.full_name as assigned_user_name
    FROM kitchen_equipment ke
    LEFT JOIN users u ON ke.assigned_to = u.id
    WHERE ke.location_id = ? AND (ke.is_condemned IS NULL OR ke.is_condemned = FALSE)
    ORDER BY ke.item_number ASC";
$kitchen_stmt = $db->prepare($kitchen_query);
$kitchen_stmt->execute([$room_id]);
$kitchen = $kitchen_stmt->fetchAll(PDO::FETCH_ASSOC);
$all_equipment_data = array_merge($all_equipment_data, $kitchen);

// 3. Get office equipment
$office_query = "SELECT 
    'office' as equipment_type,
    'office_equipment' as source_table,
    oe.id,
    oe.item_number,
    oe.equipment_name,
    oe.brand,
    oe.model,
    NULL as processor,
    NULL as ram,
    NULL as storage,
    NULL as operating_system,
    oe.status,
    oe.location_id,
    oe.assigned_to,
    oe.assigned_date,
    oe.condition_status,
    oe.remarks,
    oe.campus,
    u.full_name as assigned_user_name
    FROM office_equipment oe
    LEFT JOIN users u ON oe.assigned_to = u.id
    WHERE oe.location_id = ? AND (oe.is_condemned IS NULL OR oe.is_condemned = FALSE)
    ORDER BY oe.item_number ASC";
$office_stmt = $db->prepare($office_query);
$office_stmt->execute([$room_id]);
$office = $office_stmt->fetchAll(PDO::FETCH_ASSOC);
$all_equipment_data = array_merge($all_equipment_data, $office);

// 4. Get lab equipment
$lab_query = "SELECT 
    'lab' as equipment_type,
    'lab_equipment' as source_table,
    le.id,
    le.item_number,
    le.equipment_name,
    le.brand,
    le.model,
    NULL as processor,
    NULL as ram,
    NULL as storage,
    NULL as operating_system,
    le.status,
    le.location_id,
    le.assigned_to,
    le.assigned_date,
    le.condition_status,
    le.remarks,
    le.campus,
    u.full_name as assigned_user_name
    FROM lab_equipment le
    LEFT JOIN users u ON le.assigned_to = u.id
    WHERE le.location_id = ? AND (le.is_condemned IS NULL OR le.is_condemned = FALSE)
    ORDER BY le.item_number ASC";
$lab_stmt = $db->prepare($lab_query);
$lab_stmt->execute([$room_id]);
$lab = $lab_stmt->fetchAll(PDO::FETCH_ASSOC);
$all_equipment_data = array_merge($all_equipment_data, $lab);

// 5. Get general equipment
$general_query = "SELECT 
    'general' as equipment_type,
    'general_equipment' as source_table,
    ge.id,
    ge.item_number,
    ge.article as equipment_name,
    ge.brand,
    ge.model,
    NULL as processor,
    NULL as ram,
    NULL as storage,
    NULL as operating_system,
    ge.status,
    ge.location_id,
    ge.assigned_to,
    ge.assigned_date,
    ge.condition_status,
    ge.remarks,
    ge.campus,
    u.full_name as assigned_user_name
    FROM general_equipment ge
    LEFT JOIN users u ON ge.assigned_to = u.id
    WHERE ge.location_id = ? AND (ge.is_condemned IS NULL OR ge.is_condemned = FALSE)
    ORDER BY ge.item_number ASC";
$general_stmt = $db->prepare($general_query);
$general_stmt->execute([$room_id]);
$general = $general_stmt->fetchAll(PDO::FETCH_ASSOC);
$all_equipment_data = array_merge($all_equipment_data, $general);

// Get equipment in maintenance from history for this room
$maintenance_query = "SELECT ah.*, 
                     CASE 
                         WHEN ah.equipment_table = 'computer_inventory' THEN ci.computer_set_description
                         WHEN ah.equipment_table = 'kitchen_equipment' THEN ke.equipment_name
                         WHEN ah.equipment_table = 'office_equipment' THEN oe.equipment_name
                         WHEN ah.equipment_table = 'lab_equipment' THEN le.equipment_name
                         WHEN ah.equipment_table = 'general_equipment' THEN ge.article
                         ELSE 'Unknown Equipment'
                     END as equipment_name,
                     CASE 
                         WHEN ah.equipment_table = 'computer_inventory' THEN ci.item_number
                         WHEN ah.equipment_table = 'kitchen_equipment' THEN ke.item_number
                         WHEN ah.equipment_table = 'office_equipment' THEN oe.item_number
                         WHEN ah.equipment_table = 'lab_equipment' THEN le.item_number
                         WHEN ah.equipment_table = 'general_equipment' THEN ge.item_number
                         ELSE 'N/A'
                     END as item_number,
                     u.full_name as assigned_user_name,
                     ab.full_name as assigned_by_name
                     FROM assignment_history ah
                     LEFT JOIN computer_inventory ci ON ah.equipment_table = 'computer_inventory' AND ah.computer_id = ci.id
                     LEFT JOIN kitchen_equipment ke ON ah.equipment_table = 'kitchen_equipment' AND ah.computer_id = ke.id
                     LEFT JOIN office_equipment oe ON ah.equipment_table = 'office_equipment' AND ah.computer_id = oe.id
                     LEFT JOIN lab_equipment le ON ah.equipment_table = 'lab_equipment' AND ah.computer_id = le.id
                     LEFT JOIN general_equipment ge ON ah.equipment_table = 'general_equipment' AND ah.computer_id = ge.id
                     LEFT JOIN users u ON ah.user_id = u.id
                     LEFT JOIN users ab ON ah.assigned_by = ab.id
                     WHERE ah.location_id = ? AND ah.status = 'maintenance' AND ah.returned_date IS NULL
                     ORDER BY ah.assigned_date DESC";

$maintenance_stmt = $db->prepare($maintenance_query);
$maintenance_stmt->execute([$room_id]);
$maintenance_items = $maintenance_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all users for dropdown
$users_query = "SELECT id, full_name, email, department FROM users WHERE status = 'active' ORDER BY full_name";
$users_stmt = $db->prepare($users_query);
$users_stmt->execute();
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_equipment = count($all_equipment_data);
$available_count = count(array_filter($all_equipment_data, function($i) { return $i['status'] === 'available'; }));
$assigned_count = count(array_filter($all_equipment_data, function($i) { return $i['status'] === 'assigned'; }));
$maintenance_count = count($maintenance_items);

$page_title = "Manage Equipment - " . htmlspecialchars($room['location_name']);
include '../includes/header.php';
?>

<!-- Add debug information to console -->
<script>
console.log('=== ROOM ASSIGNMENTS DEBUG INFO ===');
console.log('Room ID: <?php echo $room_id; ?>');
console.log('Category ID: <?php echo $category_id; ?>');
console.log('Category Name: <?php echo addslashes($category_info['type_name']); ?>');
console.log('Total Equipment Found: <?php echo $total_equipment; ?>');

<?php if (!empty($all_equipment_data)): ?>
console.log('=== EQUIPMENT BREAKDOWN ===');
console.log('Computer Equipment: <?php echo count($computers); ?>');
console.log('Kitchen Equipment: <?php echo count($kitchen); ?>');
console.log('Office Equipment: <?php echo count($office); ?>');
console.log('Lab Equipment: <?php echo count($lab); ?>');
console.log('General Equipment: <?php echo count($general); ?>');

console.log('=== FIRST FEW EQUIPMENT ITEMS ===');
<?php 
$display_count = min(5, count($all_equipment_data));
for ($i = 0; $i < $display_count; $i++): 
    $item = $all_equipment_data[$i];
?>
console.log('Item <?php echo $i + 1; ?>:', {
    type: '<?php echo $item['equipment_type']; ?>',
    id: '<?php echo $item['id']; ?>',
    item_number: '<?php echo addslashes($item['item_number']); ?>',
    name: '<?php echo addslashes($item['equipment_name']); ?>',
    status: '<?php echo $item['status']; ?>',
    location_id: '<?php echo $item['location_id']; ?>'
});
<?php endfor; ?>
<?php endif; ?>

console.log('=== MAINTENANCE ITEMS ===');
console.log('Items in maintenance: <?php echo $maintenance_count; ?>');

console.log('=== END DEBUG INFO ===');
</script>

<style>
:root {
    --primary-color: <?php echo $primary_color; ?>;
    --secondary-color: <?php echo $secondary_color; ?>;
    --primary-rgb: <?php echo $primary_rgb; ?>;
}

/* General Styles */
body {
    background: #f8fafc;
}

/* Breadcrumb Navigation */
.breadcrumb-nav {
    background: white;
    padding: 1rem 1.5rem;
    border-radius: 16px;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
    border: 1px solid rgba(0,0,0,0.03);
}

.breadcrumb {
    margin: 0;
    background: transparent;
}

.breadcrumb-item + .breadcrumb-item::before {
    color: #cbd5e0;
}

.breadcrumb-item a {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 500;
    transition: all 0.2s ease;
}

.breadcrumb-item a:hover {
    color: var(--secondary-color);
}

.breadcrumb-item.active {
    color: #64748b;
    font-weight: 500;
}

/* Assignment Header */
.assignment-header {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    border-radius: 24px;
    padding: 2.5rem;
    margin-bottom: 2rem;
    color: white;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(var(--primary-rgb), 0.25);
}

.assignment-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 70%);
    animation: rotate 30s linear infinite;
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.header-content {
    position: relative;
    z-index: 2;
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.03);
    border: 1px solid rgba(0,0,0,0.03);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    gap: 1.2rem;
    position: relative;
    overflow: hidden;
}

.stat-card::after {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 120px;
    height: 120px;
    background: linear-gradient(135deg, rgba(var(--primary-rgb), 0.08) 0%, transparent 100%);
    border-radius: 50%;
    transform: translate(40px, -40px);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(var(--primary-rgb), 0.15);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: white;
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    box-shadow: 0 10px 20px rgba(var(--primary-rgb), 0.25);
    position: relative;
    z-index: 1;
}

.stat-content {
    flex: 1;
    position: relative;
    z-index: 1;
}

.stat-content h3 {
    font-size: 2rem;
    font-weight: 800;
    margin: 0;
    line-height: 1.2;
    color: #1e293b;
}

.stat-content p {
    margin: 0;
    color: #64748b;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Bulk Actions */
.bulk-actions {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.03);
    border: 1px solid rgba(0,0,0,0.03);
    position: sticky;
    top: 20px;
    z-index: 100;
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.95);
}

.bulk-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.bulk-title i {
    color: var(--primary-color);
}

.selection-badge {
    background: rgba(var(--primary-rgb), 0.1);
    color: var(--primary-color);
    padding: 0.3rem 1rem;
    border-radius: 50px;
    font-size: 0.9rem;
    font-weight: 700;
}

/* Equipment Cards */
.equipment-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 1.5rem;
}

/* Add to your existing CSS, after the .equipment-grid styles */

/* View Toggle */
.view-toggle {
    display: flex;
    gap: 0.5rem;
    background: #f1f5f9;
    padding: 0.25rem;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
}

.view-toggle-btn {
    padding: 0.5rem 1rem;
    border: none;
    background: transparent;
    border-radius: 8px;
    color: #64748b;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.view-toggle-btn:hover {
    color: var(--primary-color);
}

.view-toggle-btn.active {
    background: white;
    color: var(--primary-color);
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

/* List View Styles */
.equipment-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.equipment-list-item {
    background: white;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    padding: 1rem;
    gap: 1.5rem;
}

.equipment-list-item:hover {
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
    border-color: var(--primary-color);
}

.equipment-list-item.selected {
    border: 2px solid var(--primary-color);
    background: rgba(var(--primary-rgb), 0.02);
}

.equipment-list-checkbox {
    width: 20px;
    height: 20px;
    cursor: pointer;
    accent-color: var(--primary-color);
    margin-left: 0.5rem;
}

.equipment-list-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    flex-shrink: 0;
}

.equipment-list-icon.computer { background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%); }
.equipment-list-icon.kitchen { background: linear-gradient(135deg, #fd7e14 0%, #dc6b12 100%); }
.equipment-list-icon.office { background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); }
.equipment-list-icon.lab { background: linear-gradient(135deg, #dc3545 0%, #b02a37 100%); }
.equipment-list-icon.general { background: linear-gradient(135deg, #6c757d 0%, #545b62 100%); }

.equipment-list-content {
    flex: 1;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 2rem;
}

.equipment-list-info {
    min-width: 250px;
}

.equipment-list-info h6 {
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 0.25rem 0;
    font-size: 1rem;
}

.equipment-list-info .item-meta {
    display: flex;
    gap: 1rem;
    color: #64748b;
    font-size: 0.8rem;
}

.equipment-list-info .item-meta span {
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.equipment-list-info .item-meta i {
    color: var(--primary-color);
    font-size: 0.7rem;
}

.equipment-list-specs {
    flex: 1;
    min-width: 200px;
    font-size: 0.85rem;
    color: #475569;
    background: #f8fafc;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
}

.equipment-list-status {
    min-width: 120px;
}

.equipment-list-status .status-badge {
    margin-bottom: 0;
}

.equipment-list-assignment {
    min-width: 150px;
    font-size: 0.85rem;
}

.equipment-list-assignment .assigned-user {
    margin: 0;
    padding: 0.3rem 0.5rem;
    background: #f8fafc;
}

.equipment-list-assignment .user-avatar {
    width: 28px;
    height: 28px;
    font-size: 0.7rem;
}

.equipment-list-actions {
    display: flex;
    gap: 0.5rem;
    min-width: 120px;
    justify-content: flex-end;
}

.equipment-list-actions .action-btn {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: #f1f5f9;
    color: #475569;
}

.equipment-list-actions .action-btn:hover {
    background: var(--primary-color);
    color: white;
}

/* Responsive for list view */
@media (max-width: 1200px) {
    .equipment-list-content {
        gap: 1rem;
    }
    
    .equipment-list-specs {
        min-width: 150px;
    }
}

@media (max-width: 992px) {
    .equipment-list-item {
        flex-direction: column;
        align-items: flex-start;
        padding: 1.5rem;
    }
    
    .equipment-list-content {
        width: 100%;
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .equipment-list-info {
        width: 100%;
    }
    
    .equipment-list-specs {
        width: 100%;
    }
    
    .equipment-list-status,
    .equipment-list-assignment {
        width: 100%;
    }
    
    .equipment-list-actions {
        width: 100%;
        justify-content: flex-start;
    }
}

.equipment-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.03);
    border: 1px solid rgba(0,0,0,0.03);
    overflow: hidden;
    transition: all 0.3s ease;
    position: relative;
}

.equipment-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 35px rgba(var(--primary-rgb), 0.15);
    border-color: var(--primary-color);
}

.equipment-card.selected {
    border: 2px solid var(--primary-color);
    background: rgba(var(--primary-rgb), 0.02);
}

.equipment-header {
    padding: 1.2rem 1.5rem;
    background: linear-gradient(135deg, rgba(var(--primary-rgb), 0.05) 0%, transparent 100%);
    border-bottom: 1px solid rgba(0,0,0,0.03);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.equipment-checkbox {
    width: 20px;
    height: 20px;
    cursor: pointer;
    accent-color: var(--primary-color);
}

.equipment-icon {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    flex-shrink: 0;
}

.equipment-icon.computer { background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%); }
.equipment-icon.kitchen { background: linear-gradient(135deg, #fd7e14 0%, #dc6b12 100%); }
.equipment-icon.office { background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); }
.equipment-icon.lab { background: linear-gradient(135deg, #dc3545 0%, #b02a37 100%); }
.equipment-icon.general { background: linear-gradient(135deg, #6c757d 0%, #545b62 100%); }

.equipment-title {
    flex: 1;
}

.equipment-title h6 {
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 0.2rem 0;
}

.equipment-title small {
    color: #64748b;
    font-size: 0.7rem;
    display: block;
}

.equipment-body {
    padding: 1.5rem;
}

/* Maintenance Section */
.maintenance-section {
    margin-top: 2rem;
    background: #fff3e0;
    border-radius: 16px;
    padding: 1.5rem;
    border-left: 4px solid #f57c00;
}

.maintenance-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #b76e00;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.maintenance-item {
    background: white;
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 1rem;
    border: 1px solid #ffe0b2;
}

.maintenance-item:last-child {
    margin-bottom: 0;
}

.maintenance-reason {
    background: #fff3e0;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.9rem;
    margin-top: 0.5rem;
    border-left: 3px solid #f57c00;
}

/* Status Badges */
.status-badge {
    padding: 0.4rem 1rem;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.status-badge.available {
    background: #d1e7dd;
    color: #0a5e3a;
}

.status-badge.assigned {
    background: #cff4fc;
    color: #055160;
}

.status-badge.maintenance {
    background: #fff3cd;
    color: #856404;
}

.status-badge.damaged {
    background: #f8d7da;
    color: #9a1c2a;
}

/* Assignment Section */
.assignment-section {
    background: #f8fafc;
    border-radius: 12px;
    padding: 1rem;
    margin-top: 1rem;
}

.assignment-label {
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    color: #64748b;
    margin-bottom: 0.5rem;
    display: block;
}

.assigned-user {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem;
    background: white;
    border-radius: 8px;
}

.user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.8rem;
    flex-shrink: 0;
}

.user-info {
    flex: 1;
}

.user-name {
    font-weight: 600;
    color: #1e293b;
    margin: 0;
    font-size: 0.9rem;
}

.user-email {
    font-size: 0.7rem;
    color: #64748b;
    margin: 0;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
}

.action-btn {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.action-btn.primary {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
}

.action-btn.primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(var(--primary-rgb), 0.3);
}

.action-btn.outline {
    background: white;
    color: var(--primary-color);
    border: 1px solid var(--primary-color);
}

.action-btn.outline:hover {
    background: var(--primary-color);
    color: white;
}

.action-btn.success {
    background: #198754;
    color: white;
}

.action-btn.success:hover {
    background: #146c43;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(25, 135, 84, 0.3);
}

/* Quick Actions */
.quick-actions {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.03);
    border: 1px solid rgba(0,0,0,0.03);
    height: fit-content;
    position: sticky;
    top: 120px;
}

.quick-actions-title {
    font-size: 1rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.quick-actions-title i {
    color: var(--primary-color);
}

.quick-action-item {
    padding: 0.8rem;
    border-radius: 12px;
    background: #f8fafc;
    margin-bottom: 0.5rem;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 0.8rem;
}

.quick-action-item:hover {
    background: rgba(var(--primary-rgb), 0.1);
    transform: translateX(5px);
}

.quick-action-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: 24px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.03);
    border: 1px solid rgba(0,0,0,0.03);
}

.empty-state-icon {
    width: 120px;
    height: 120px;
    border-radius: 60px;
    background: rgba(var(--primary-rgb), 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 2rem;
    font-size: 3rem;
    color: var(--primary-color);
}

.empty-state h4 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: #64748b;
    margin-bottom: 2rem;
}

/* Modal Styling */
.modal-content {
    border-radius: 24px;
    border: none;
    box-shadow: 0 30px 70px rgba(0, 0, 0, 0.2);
}

.modal-header {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
    border-radius: 24px 24px 0 0;
    padding: 1.5rem 2rem;
    border: none;
}

.modal-header .btn-close {
    filter: brightness(0) invert(1);
    opacity: 0.8;
    transition: all 0.2s ease;
}

.modal-header .btn-close:hover {
    opacity: 1;
    transform: rotate(90deg);
}

.modal-title {
    font-weight: 700;
    font-size: 1.3rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.modal-body {
    padding: 2rem;
}

.modal-footer {
    padding: 1.5rem 2rem;
    border-top: 1px solid rgba(0,0,0,0.05);
    background: #f8fafc;
    border-radius: 0 0 24px 24px;
}

/* Alert Messages */
.alert {
    border-radius: 16px;
    border: none;
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
    animation: slideInDown 0.4s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.alert-success {
    background: #ecfdf5;
    color: #065f46;
    border-left: 4px solid #059669;
}

.alert-danger {
    background: #fef2f2;
    color: #991b1b;
    border-left: 4px solid #dc2626;
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive */
@media (max-width: 992px) {
    .equipment-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .assignment-header {
        padding: 1.5rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .breadcrumb-nav {
        padding: 0.8rem;
    }
    
    .bulk-actions {
        position: static;
    }
    
    .quick-actions {
        position: static;
        margin-top: 1.5rem;
    }
}
</style>

<!-- Breadcrumb Navigation -->
<div class="breadcrumb-nav">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="inventory_categories.php">
                    <i class="fas fa-th-large me-1"></i>Categories
                </a>
            </li>
            <li class="breadcrumb-item">
                <a href="inventory_rooms.php?category_id=<?php echo $category_id; ?>">
                    <i class="fas <?php echo $category_info['icon_class']; ?> me-1"></i>
                    <?php echo htmlspecialchars($category_info['type_name']); ?>
                </a>
            </li>
            <li class="breadcrumb-item">
                <a href="inventory_room_detail.php?room_id=<?php echo $room_id; ?>&category_id=<?php echo $category_id; ?>">
                    <i class="fas fa-door-open me-1"></i>
                    <?php echo htmlspecialchars($room['location_name']); ?>
                </a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">
                <i class="fas fa-user-cog me-1"></i>
                Manage Assignments
            </li>
        </ol>
    </nav>
</div>

<!-- Assignment Header -->
<div class="assignment-header">
    <div class="header-content">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="d-flex align-items-center gap-4">
                    <div class="flex-shrink-0">
                        <i class="fas fa-user-cog fa-4x opacity-75"></i>
                    </div>
                    <div>
                        <h1 class="display-5 fw-bold mb-2">Manage Equipment</h1>
                        <p class="mb-1 fs-5"><?php echo htmlspecialchars($room['location_name']); ?></p>
                        <div class="d-flex gap-3 mt-2">
                            <span class="badge bg-light text-dark">
                                <i class="fas <?php echo $category_info['icon_class']; ?> me-1"></i>
                                <?php echo htmlspecialchars($category_info['type_name']); ?>
                            </span>
                            <?php if ($room['manager_name']): ?>
                            <span class="badge bg-light text-dark">
                                <i class="fas fa-user-tie me-1"></i>
                                Manager: <?php echo htmlspecialchars($room['manager_name']); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="inventory_room_detail.php?room_id=<?php echo $room_id; ?>&category_id=<?php echo $category_id; ?>" class="btn btn-outline-light">
                    <i class="fas fa-arrow-left me-2"></i>Back to Room
                </a>
                <a href="assignment_history.php?location_id=<?php echo $room_id; ?>" class="btn btn-outline-light mt-2 mt-md-0 ms-md-2">
                    <i class="fas fa-history me-2"></i>View History
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Alert Messages -->
<?php if ($success_message): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i>
    <?php echo $success_message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error_message): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i>
    <?php echo $error_message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Statistics Overview -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-boxes"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $total_equipment; ?></h3>
            <p>Total Equipment</p>
            <div class="stat-trend">
                <i class="fas fa-layer-group"></i> In this room
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $available_count; ?></h3>
            <p>Available</p>
            <div class="stat-trend">
                <i class="fas fa-arrow-up text-success"></i> Ready to assign
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-user-check"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $assigned_count; ?></h3>
            <p>Assigned</p>
            <div class="stat-trend">
                <i class="fas fa-user"></i> Currently in use
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-tools"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $maintenance_count; ?></h3>
            <p>Maintenance</p>
            <div class="stat-trend">
                <i class="fas fa-wrench"></i> Under repair
            </div>
        </div>
    </div>
</div>

<!-- In the table-header div, after the table-title -->
<div class="table-header">
    <div class="table-title">
        <i class="fas fa-list"></i>
        Equipment Inventory
        <span class="table-badge"><?php echo $total_equipment; ?> items</span>
    </div>
    <div class="d-flex align-items-center gap-3">
        <div class="view-toggle" id="viewToggle">
            <button type="button" class="view-toggle-btn active" onclick="setView('grid')">
                <i class="fas fa-th-large"></i> Grid
            </button>
            <button type="button" class="view-toggle-btn" onclick="setView('list')">
                <i class="fas fa-list"></i> List
            </button>
        </div>
        <span class="text-muted small">
            <i class="fas fa-info-circle me-1"></i>Click on action buttons to manage equipment
        </span>
    </div>
</div>

<div class="row">
    <!-- Main Content - Equipment List -->
    <div class="col-lg-8">
        <!-- Bulk Actions -->
        <div class="bulk-actions">
            <div class="bulk-title">
                <i class="fas fa-tasks"></i>
                Bulk Actions
                <span class="selection-badge ms-2" id="selectedCount">0 selected</span>
            </div>
            
            <div class="row align-items-center g-3">
                <div class="col-md-auto">
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="selectAll()">
                        <i class="fas fa-check-double me-1"></i>Select All
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearSelection()">
                        <i class="fas fa-times me-1"></i>Clear
                    </button>
                </div>
                <div class="col-md-auto">
                    <button type="button" class="btn btn-warning btn-sm" onclick="bulkSetMaintenance()" id="maintenanceBtn" disabled>
                        <i class="fas fa-tools me-1"></i>Set Maintenance
                    </button>
                    <button type="button" class="btn btn-success btn-sm" onclick="bulkSetAvailable()" id="availableBtn" disabled>
                        <i class="fas fa-check-circle me-1"></i>Set Available
                    </button>
                </div>
            </div>
        </div>

        <!-- Equipment List -->
        <?php if (empty($all_equipment_data)): ?>  <!-- Changed from $equipment_data to $all_equipment_data -->
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="fas fa-box"></i>
            </div>
            <h4>No Equipment Found</h4>
            <p>There are no equipment items assigned to this room yet.</p>
            <div class="d-flex justify-content-center gap-2">
                <a href="all_equipment.php" class="btn btn-primary">
                    <i class="fas fa-plus-circle me-2"></i>Add Equipment
                </a>
                <a href="inventory_room_detail.php?room_id=<?php echo $room_id; ?>&category_id=<?php echo $category_id; ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Room
                </a>
            </div>
        </div>
        <?php else: ?>
        <!-- Equipment Container - supports both grid and list views -->
        <div id="equipmentContainer" class="equipment-grid">
            <?php foreach ($all_equipment_data as $equipment): 
                $status_class = $equipment['status'] ?? 'available';
                $equipment_type = $equipment['equipment_type'] ?? 'general';
                
                // Set icon based on equipment type
                $icon_class = 'fa-box';
                $type_class = 'general';
                switch($equipment_type) {
                    case 'computer':
                        $icon_class = 'fa-desktop';
                        $type_class = 'computer';
                        break;
                    case 'kitchen':
                        $icon_class = 'fa-utensils';
                        $type_class = 'kitchen';
                        break;
                    case 'office':
                        $icon_class = 'fa-briefcase';
                        $type_class = 'office';
                        break;
                    case 'lab':
                        $icon_class = 'fa-flask';
                        $type_class = 'lab';
                        break;
                    default:
                        $icon_class = 'fa-box';
                        $type_class = 'general';
                }
                
                // Build specs based on equipment type
                $specs = [];
                if ($equipment_type == 'computer') {
                    if (!empty($equipment['processor'])) $specs[] = $equipment['processor'];
                    if (!empty($equipment['ram'])) $specs[] = $equipment['ram'];
                    if (!empty($equipment['storage'])) $specs[] = $equipment['storage'];
                    if (!empty($equipment['operating_system'])) $specs[] = $equipment['operating_system'];
                } else {
                    if (!empty($equipment['brand'])) $specs[] = $equipment['brand'];
                    if (!empty($equipment['model'])) $specs[] = $equipment['model'];
                }
                $specs_text = !empty($specs) ? implode(' • ', $specs) : '';
            ?>
            <!-- Grid View Card -->
            <div class="equipment-card grid-view" data-equipment-id="<?php echo $equipment['id']; ?>" data-equipment-type="<?php echo $equipment_type; ?>" data-source-table="<?php echo $equipment['source_table']; ?>">
                <div class="equipment-header">
                    <input type="checkbox" class="equipment-checkbox" value="<?php echo $equipment_type; ?>:<?php echo $equipment['id']; ?>" onchange="updateSelection()">
                    <div class="equipment-icon <?php echo $type_class; ?>">
                        <i class="fas <?php echo $icon_class; ?>"></i>
                    </div>
                    <div class="equipment-title">
                        <h6><?php echo htmlspecialchars($equipment['equipment_name'] ?? 'Equipment #' . $equipment['id']); ?></h6>
                        <small>Item #: <?php echo htmlspecialchars($equipment['item_number'] ?? 'N/A'); ?></small>
                        <small class="d-block text-muted" style="font-size: 0.6rem;">Type: <?php echo ucfirst($equipment_type); ?></small>
                    </div>
                </div>
                
                <div class="equipment-body">
                    <!-- Status Badge -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="fw-bold">Status:</span>
                        <span class="status-badge <?php echo $status_class; ?>">
                            <i class="fas fa-circle"></i>
                            <?php echo ucfirst($status_class); ?>
                        </span>
                    </div>
                    
                    <!-- Specifications -->
                    <?php if ($specs_text): ?>
                    <div class="mb-3">
                        <span class="assignment-label">Specifications</span>
                        <div class="p-2 bg-light rounded-3 small">
                            <?php echo htmlspecialchars($specs_text); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Assignment Section -->
                    <div class="assignment-section">
                        <span class="assignment-label">Assignment</span>
                        
                        <?php if (!empty($equipment['assigned_user_name'])): ?>
                        <div class="assigned-user mb-2">
                            <div class="user-avatar">
                                <?php 
                                $name = $equipment['assigned_user_name'];
                                $initials = '';
                                $name_parts = explode(' ', $name);
                                if (count($name_parts) >= 2) {
                                    $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
                                } else {
                                    $initials = strtoupper(substr($name, 0, 2));
                                }
                                echo htmlspecialchars($initials);
                                ?>
                            </div>
                            <div class="user-info">
                                <p class="user-name"><?php echo htmlspecialchars($equipment['assigned_user_name']); ?></p>
                            </div>
                        </div>
                        <?php else: ?>
                        <p class="text-muted small mb-2">
                            <i class="fas fa-user-slash me-1"></i>
                            Not assigned to any user
                        </p>
                        <?php endif; ?>
                        
                        <!-- Quick Actions -->
                        <div class="action-buttons">
                            <form method="POST" style="display: inline;" class="flex-grow-1" id="statusForm_<?php echo $equipment['id']; ?>_<?php echo $equipment_type; ?>">
                                <input type="hidden" name="action" value="change_status">
                                <input type="hidden" name="equipment_id" value="<?php echo $equipment['id']; ?>">
                                <input type="hidden" name="equipment_type" value="<?php echo $equipment_type; ?>">
                                <input type="hidden" name="source_table" value="<?php echo $equipment['source_table']; ?>">
                                <input type="hidden" name="maintenance_reason" id="maintenance_reason_<?php echo $equipment['id']; ?>" value="">
                                <select class="form-select form-select-sm" name="new_status" onchange="handleStatusChange(this, <?php echo $equipment['id']; ?>, '<?php echo htmlspecialchars($equipment['equipment_name'] ?? ''); ?>', '<?php echo $equipment_type; ?>')">
                                    <option value="">Quick Status...</option>
                                    <option value="available">✅ Available</option>
                                    <option value="maintenance">🔧 Maintenance</option>
                                    <?php if ($status_class !== 'assigned'): ?>
                                    <option value="assigned">👤 Assign</option>
                                    <?php endif; ?>
                                </select>
                            </form>
                            
                            <button type="button" class="action-btn outline" onclick="openAssignModal(<?php echo $equipment['id']; ?>, '<?php echo htmlspecialchars($equipment['equipment_name'] ?? ''); ?>', '<?php echo $equipment['assigned_to'] ?? ''; ?>', '<?php echo $equipment_type; ?>')">
                                <i class="fas fa-user-edit"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- List View Item (hidden by default) -->
            <div class="equipment-list-item list-view" style="display: none;" data-equipment-id="<?php echo $equipment['id']; ?>" data-equipment-type="<?php echo $equipment_type; ?>" data-source-table="<?php echo $equipment['source_table']; ?>">
                <input type="checkbox" class="equipment-list-checkbox" value="<?php echo $equipment_type; ?>:<?php echo $equipment['id']; ?>" onchange="updateSelection()">
                
                <div class="equipment-list-icon <?php echo $type_class; ?>">
                    <i class="fas <?php echo $icon_class; ?>"></i>
                </div>
                
                <div class="equipment-list-content">
                    <div class="equipment-list-info">
                        <h6><?php echo htmlspecialchars($equipment['equipment_name'] ?? 'Equipment #' . $equipment['id']); ?></h6>
                        <div class="item-meta">
                            <span><i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($equipment['item_number'] ?? 'N/A'); ?></span>
                            <span><i class="fas fa-tag"></i> <?php echo ucfirst($equipment_type); ?></span>
                            <?php if (!empty($equipment['campus'])): ?>
                            <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($equipment['campus']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($specs_text): ?>
                    <div class="equipment-list-specs">
                        <i class="fas fa-microchip me-1"></i> <?php echo htmlspecialchars($specs_text); ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="equipment-list-status">
                        <span class="status-badge <?php echo $status_class; ?>">
                            <i class="fas fa-circle"></i>
                            <?php echo ucfirst($status_class); ?>
                        </span>
                    </div>
                    
                    <div class="equipment-list-assignment">
                        <?php if (!empty($equipment['assigned_user_name'])): ?>
                        <div class="assigned-user">
                            <div class="user-avatar">
                                <?php 
                                $name = $equipment['assigned_user_name'];
                                $initials = '';
                                $name_parts = explode(' ', $name);
                                if (count($name_parts) >= 2) {
                                    $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
                                } else {
                                    $initials = strtoupper(substr($name, 0, 2));
                                }
                                echo htmlspecialchars($initials);
                                ?>
                            </div>
                            <div class="user-info">
                                <p class="user-name"><?php echo htmlspecialchars($equipment['assigned_user_name']); ?></p>
                            </div>
                        </div>
                        <?php else: ?>
                        <span class="text-muted small">
                            <i class="fas fa-user-slash me-1"></i> Unassigned
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="equipment-list-actions">
                    <form method="POST" style="display: inline;" id="listStatusForm_<?php echo $equipment['id']; ?>_<?php echo $equipment_type; ?>">
                        <input type="hidden" name="action" value="change_status">
                        <input type="hidden" name="equipment_id" value="<?php echo $equipment['id']; ?>">
                        <input type="hidden" name="equipment_type" value="<?php echo $equipment_type; ?>">
                        <input type="hidden" name="source_table" value="<?php echo $equipment['source_table']; ?>">
                        <select class="form-select form-select-sm" name="new_status" onchange="this.form.submit()" style="width: 120px;">
                            <option value="">Status</option>
                            <option value="available">✅ Available</option>
                            <option value="maintenance">🔧 Maintenance</option>
                            <?php if ($status_class !== 'assigned'): ?>
                            <option value="assigned">👤 Assign</option>
                            <?php endif; ?>
                        </select>
                    </form>
                    
                    <button type="button" class="action-btn outline" onclick="openAssignModal(<?php echo $equipment['id']; ?>, '<?php echo htmlspecialchars($equipment['equipment_name'] ?? ''); ?>', '<?php echo $equipment['assigned_to'] ?? ''; ?>', '<?php echo $equipment_type; ?>')" title="Assign User">
                        <i class="fas fa-user-edit"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Maintenance Section -->
        <?php if (!empty($maintenance_items)): ?>
        <div class="maintenance-section">
            <div class="maintenance-title">
                <i class="fas fa-tools"></i>
                Equipment Under Maintenance (<?php echo count($maintenance_items); ?>)
            </div>
            
            <?php foreach ($maintenance_items as $item): ?>
            <div class="maintenance-item">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($item['equipment_name'] ?? $item['item_number']); ?></h6>
                        <small class="text-muted">Item #: <?php echo htmlspecialchars($item['item_number']); ?></small>
                    </div>
                    <span class="badge bg-warning">In Maintenance</span>
                </div>
                
                <div class="maintenance-reason">
                    <strong>Reason:</strong> <?php echo htmlspecialchars($item['maintenance_reason'] ?? 'Not specified'); ?>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <small class="text-muted">
                        <i class="fas fa-calendar me-1"></i> Since: <?php echo date('M d, Y', strtotime($item['assigned_date'])); ?>
                        <br>
                        <i class="fas fa-user me-1"></i> Reported by: <?php echo htmlspecialchars($item['assigned_by_name'] ?? 'Unknown'); ?>
                    </small>
                    <button type="button" class="btn btn-success btn-sm" onclick="completeMaintenance(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['equipment_name'] ?? $item['item_number']); ?>')">
                        <i class="fas fa-check-circle me-1"></i> Complete & Deploy
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Sidebar - Quick Actions & Info -->
    <div class="col-lg-4">
        <!-- Quick Actions -->
        <div class="quick-actions">
            <div class="quick-actions-title">
                <i class="fas fa-bolt"></i>
                Quick Actions
            </div>
            
            <div class="quick-action-item" onclick="window.location.href='inventory_room_detail.php?room_id=<?php echo $room_id; ?>&category_id=<?php echo $category_id; ?>'">
                <div class="quick-action-icon">
                    <i class="fas fa-door-open"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-0">View Room Details</h6>
                    <small class="text-muted">See all equipment and info</small>
                </div>
            </div>
            
            <div class="quick-action-item" onclick="window.location.href='all_equipment.php?location_id=<?php echo $room_id; ?>'">
                <div class="quick-action-icon">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-0">Add New Equipment</h6>
                    <small class="text-muted">Add items to this room</small>
                </div>
            </div>
            
            <div class="quick-action-item" onclick="window.location.href='assignment_history.php?location_id=<?php echo $room_id; ?>'">
                <div class="quick-action-icon">
                    <i class="fas fa-history"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-0">View History</h6>
                    <small class="text-muted">See assignment logs</small>
                </div>
            </div>
            
            <div class="quick-action-item" onclick="window.location.href='locations.php?id=<?php echo $room_id; ?>'">
                <div class="quick-action-icon">
                    <i class="fas fa-edit"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-0">Edit Room</h6>
                    <small class="text-muted">Update room details</small>
                </div>
            </div>
        </div>
        
        <!-- Room Info Card -->
        <div class="quick-actions mt-3">
            <div class="quick-actions-title">
                <i class="fas fa-info-circle"></i>
                Room Information
            </div>
            
            <div class="mb-3">
                <span class="assignment-label">Room Name</span>
                <p class="fw-bold mb-0"><?php echo htmlspecialchars($room['location_name']); ?></p>
            </div>
            
            <div class="mb-3">
                <span class="assignment-label">Description</span>
                <p class="mb-0 small"><?php echo htmlspecialchars($room['description'] ?? 'No description'); ?></p>
            </div>
            
            <div class="mb-3">
                <span class="assignment-label">Category</span>
                <p class="mb-0">
                    <span class="badge" style="background: <?php echo $primary_color; ?>; color: white;">
                        <i class="fas <?php echo $category_info['icon_class']; ?> me-1"></i>
                        <?php echo htmlspecialchars($category_info['type_name']); ?>
                    </span>
                </p>
            </div>
            
            <?php if ($room['manager_name']): ?>
            <div>
                <span class="assignment-label">Room Manager</span>
                <div class="d-flex align-items-center gap-2">
                    <div class="user-avatar">
                        <?php 
                        $name = $room['manager_name'];
                        $initials = '';
                        $name_parts = explode(' ', $name);
                        if (count($name_parts) >= 2) {
                            $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
                        } else {
                            $initials = strtoupper(substr($name, 0, 2));
                        }
                        echo htmlspecialchars($initials);
                        ?>
                    </div>
                    <div>
                        <p class="fw-bold mb-0"><?php echo htmlspecialchars($room['manager_name']); ?></p>
                        <small class="text-muted"><?php echo htmlspecialchars($room['manager_email']); ?></small>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Assign User Modal -->
<div class="modal fade" id="assignModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user-tie me-2"></i>
                    Assign Equipment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="assign_user">
                    <input type="hidden" name="equipment_id" id="modal_equipment_id">
                    
                    <div class="mb-4">
                        <label class="form-label text-muted small fw-bold mb-2">Equipment</label>
                        <div class="p-3 bg-light rounded-3 d-flex align-items-center">
                            <i class="fas <?php echo $config['icon']; ?> fa-lg me-3" style="color: var(--primary-color);"></i>
                            <strong id="modal_equipment_name" class="fs-6"></strong>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold mb-2">Select User</label>
                        <select class="form-select form-select-lg" name="user_id" id="modal_user_select">
                            <option value="">— Unassign (No User) —</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo htmlspecialchars($user['full_name']); ?>
                                <?php if (!empty($user['department'])): ?>
                                (<?php echo htmlspecialchars($user['department']); ?>)
                                <?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text text-muted small mt-2">
                            <i class="fas fa-info-circle me-1"></i>
                            Assign this equipment to a user. They will be recorded as the accountable person.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary px-4" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); border: none;">
                        <i class="fas fa-save me-2"></i>Save Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Maintenance Reason Modal -->
<div class="modal fade" id="maintenanceReasonModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: #f57c00; color: white;">
                <h5 class="modal-title">
                    <i class="fas fa-tools me-2"></i>
                    Maintenance Reason
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="maintenanceReasonForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="change_status">
                    <input type="hidden" name="equipment_id" id="maintenance_equipment_id">
                    <input type="hidden" name="new_status" value="maintenance">
                    
                    <div class="mb-4">
                        <label class="form-label text-muted small fw-bold mb-2">Equipment</label>
                        <div class="p-3 bg-light rounded-3 d-flex align-items-center">
                            <i class="fas <?php echo $config['icon']; ?> fa-lg me-3" style="color: #f57c00;"></i>
                            <strong id="maintenance_equipment_name" class="fs-6"></strong>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold mb-2">Reason for Maintenance <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="maintenance_reason" id="maintenance_reason_input" rows="4" placeholder="Describe the issue or problem with this equipment..." required></textarea>
                        <div class="form-text text-muted small mt-2">
                            <i class="fas fa-info-circle me-1"></i>
                            This reason will be logged in the assignment history.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-warning px-4">
                        <i class="fas fa-tools me-2"></i>Send to Maintenance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Complete Maintenance Modal -->
<div class="modal fade" id="completeMaintenanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: #198754; color: white;">
                <h5 class="modal-title">
                    <i class="fas fa-check-circle me-2"></i>
                    Complete Maintenance
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="completeMaintenanceForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="complete_maintenance">
                    <input type="hidden" name="history_id" id="complete_history_id">
                    
                    <div class="mb-4">
                        <label class="form-label text-muted small fw-bold mb-2">Equipment</label>
                        <div class="p-3 bg-light rounded-3 d-flex align-items-center">
                            <i class="fas <?php echo $config['icon']; ?> fa-lg me-3" style="color: #198754;"></i>
                            <strong id="complete_equipment_name" class="fs-6"></strong>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold mb-2">Fix Details / Resolution <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="fix_details" id="fix_details_input" rows="4" placeholder="Describe what was fixed, parts replaced, or actions taken..." required></textarea>
                        <div class="form-text text-muted small mt-2">
                            <i class="fas fa-info-circle me-1"></i>
                            These details will be logged in the assignment history.
                        </div>
                    </div>
                    
                    <div class="alert alert-success border-0">
                        <i class="fas fa-check-circle me-2"></i>
                        After completion, the equipment will be set to <strong>Available</strong> status.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-success px-4">
                        <i class="fas fa-check-circle me-2"></i>Complete & Deploy
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Maintenance Reason Modal -->
<div class="modal fade" id="bulkMaintenanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: #f57c00; color: white;">
                <h5 class="modal-title">
                    <i class="fas fa-tools me-2"></i>
                    Bulk Maintenance
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-4">
                    <div class="alert alert-warning border-0">
                        <i class="fas fa-info-circle me-2"></i>
                        You are about to set <strong id="bulkCount">0</strong> equipment items to <strong>Maintenance</strong> status.
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold mb-2">Reason for Maintenance <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="bulk_maintenance_reason" rows="4" placeholder="Describe the common issue for these items..." required></textarea>
                    <div class="form-text text-muted small mt-2">
                        <i class="fas fa-info-circle me-1"></i>
                        This reason will be logged for all selected items.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-warning px-4" onclick="executeBulk('maintenance')">
                    <i class="fas fa-tools me-2"></i>Send to Maintenance
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let selectedEquipment = [];

// View toggle functionality
function setView(viewType) {
    // Update toggle buttons
    document.querySelectorAll('.view-toggle-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.closest('.view-toggle-btn').classList.add('active');
    
    // Clear all checkboxes first
    document.querySelectorAll('.equipment-checkbox, .equipment-list-checkbox').forEach(c => {
        c.checked = false;
    });
    
    // Get all equipment items
    const gridViews = document.querySelectorAll('.grid-view');
    const listViews = document.querySelectorAll('.list-view');
    const container = document.getElementById('equipmentContainer');
    
    if (viewType === 'grid') {
        container.className = 'equipment-grid';
        gridViews.forEach(item => item.style.display = 'block');
        listViews.forEach(item => item.style.display = 'none');
    } else {
        container.className = 'equipment-list';
        gridViews.forEach(item => item.style.display = 'none');
        listViews.forEach(item => item.style.display = 'flex');
    }
    
    // Update selection to reflect cleared checkboxes
    updateSelection();
    
    // Save preference to localStorage
    localStorage.setItem('roomAssignmentsView', viewType);
}

// Load saved view preference on page load
document.addEventListener('DOMContentLoaded', function() {
    const savedView = localStorage.getItem('roomAssignmentsView') || 'grid';
    
    // Trigger the saved view
    const viewToggleBtns = document.querySelectorAll('.view-toggle-btn');
    viewToggleBtns.forEach(btn => {
        if ((savedView === 'grid' && btn.innerHTML.includes('Grid')) ||
            (savedView === 'list' && btn.innerHTML.includes('List'))) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
    
    // Apply the view
    const gridViews = document.querySelectorAll('.grid-view');
    const listViews = document.querySelectorAll('.list-view');
    const container = document.getElementById('equipmentContainer');
    
    if (savedView === 'grid') {
        container.className = 'equipment-grid';
        gridViews.forEach(item => item.style.display = 'block');
        listViews.forEach(item => item.style.display = 'none');
    } else {
        container.className = 'equipment-list';
        gridViews.forEach(item => item.style.display = 'none');
        listViews.forEach(item => item.style.display = 'flex');
    }
});

// Update the existing updateSelection function to work with both views
function updateSelection() {
    selectedEquipment = [];
    
    // Only count checkboxes from the currently visible view
    const container = document.getElementById('equipmentContainer');
    
    if (container.classList.contains('equipment-grid')) {
        // Grid view is active
        document.querySelectorAll('.equipment-checkbox:checked').forEach(c => {
            selectedEquipment.push(c.value);
            c.closest('.equipment-card').classList.add('selected');
        });
        
        // Remove selection from unchecked items
        document.querySelectorAll('.equipment-checkbox:not(:checked)').forEach(c => {
            c.closest('.equipment-card').classList.remove('selected');
        });
    } else {
        // List view is active
        document.querySelectorAll('.equipment-list-checkbox:checked').forEach(c => {
            selectedEquipment.push(c.value);
            c.closest('.equipment-list-item').classList.add('selected');
        });
        
        // Remove selection from unchecked items
        document.querySelectorAll('.equipment-list-checkbox:not(:checked)').forEach(c => {
            c.closest('.equipment-list-item').classList.remove('selected');
        });
    }
    
    const count = selectedEquipment.length;
    document.getElementById('selectedCount').textContent = count + ' selected';
    
    // Update bulk action buttons
    document.getElementById('maintenanceBtn').disabled = count === 0;
    document.getElementById('availableBtn').disabled = count === 0;
}

function selectAll() {
    const container = document.getElementById('equipmentContainer');
    
    if (container.classList.contains('equipment-grid')) {
        // Grid view
        document.querySelectorAll('.equipment-checkbox').forEach(c => {
            c.checked = true;
        });
    } else {
        // List view
        document.querySelectorAll('.equipment-list-checkbox').forEach(c => {
            c.checked = true;
        });
    }
    
    updateSelection();
}

function clearSelection() {
    const container = document.getElementById('equipmentContainer');
    
    if (container.classList.contains('equipment-grid')) {
        // Grid view
        document.querySelectorAll('.equipment-checkbox').forEach(c => {
            c.checked = false;
        });
    } else {
        // List view
        document.querySelectorAll('.equipment-list-checkbox').forEach(c => {
            c.checked = false;
        });
    }
    
    updateSelection();
}

function handleStatusChange(select, equipmentId, equipmentName, equipmentType) {
    if (select.value === 'maintenance') {
        // Open maintenance reason modal
        document.getElementById('maintenance_equipment_id').value = equipmentId;
        document.getElementById('maintenance_equipment_name').textContent = equipmentName;
        document.getElementById('maintenance_equipment_type').value = equipmentType;
        document.getElementById('maintenance_reason_input').value = '';
        
        // Reset select to default
        select.value = '';
        
        // Show modal
        new bootstrap.Modal(document.getElementById('maintenanceReasonModal')).show();
    } else if (select.value) {
        // Submit form directly for other statuses
        document.getElementById('statusForm_' + equipmentId + '_' + equipmentType).submit();
    }
}

function bulkSetMaintenance() {
    if (selectedEquipment.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'No Selection',
            text: 'Please select at least one equipment item.'
        });
        return;
    }
    
    // Show confirmation dialog first
    Swal.fire({
        title: 'Set to Maintenance',
        html: `Are you sure you want to set <strong>${selectedEquipment.length}</strong> item(s) to maintenance?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#f57c00',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, proceed'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('bulk_equipment_ids').value = selectedEquipment.join(',');
            document.getElementById('bulkCount').textContent = selectedEquipment.length;
            document.getElementById('bulk_maintenance_reason').value = '';
            
            new bootstrap.Modal(document.getElementById('bulkMaintenanceModal')).show();
        }
    });
}

function bulkSetAvailable() {
    if (selectedEquipment.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'No Selection',
            text: 'Please select at least one equipment item.'
        });
        return;
    }
    
    Swal.fire({
        title: 'Confirm Bulk Action',
        html: `Set <strong>${selectedEquipment.length}</strong> item(s) to <span class="badge bg-success">Available</span> status?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, set to available'
    }).then((result) => {
        if (result.isConfirmed) {
            executeBulk('available');
        }
    });
}

function executeBulk(status) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="bulk_status_change">
        <input type="hidden" name="equipment_ids" value="${selectedEquipment.join(',')}">
        <input type="hidden" name="bulk_status" value="${status}">
    `;
    
    // If setting to maintenance, get the reason from the modal
    if (status === 'maintenance') {
        const reason = document.getElementById('bulk_maintenance_reason').value;
        if (!reason) {
            Swal.fire({
                icon: 'warning',
                title: 'Reason Required',
                text: 'Please provide a reason for maintenance.'
            });
            return;
        }
        form.innerHTML += `<input type="hidden" name="bulk_maintenance_reason" value="${reason}">`;
    }
    
    document.body.appendChild(form);
    form.submit();
}

function completeMaintenance(historyId, equipmentName) {
    document.getElementById('complete_history_id').value = historyId;
    document.getElementById('complete_equipment_name').textContent = equipmentName;
    document.getElementById('fix_details_input').value = '';
    
    new bootstrap.Modal(document.getElementById('completeMaintenanceModal')).show();
}

function openAssignModal(equipmentId, equipmentName, currentUserId, equipmentType) {
    document.getElementById('modal_equipment_id').value = equipmentId;
    document.getElementById('modal_equipment_name').textContent = equipmentName;
    document.getElementById('modal_equipment_type').value = equipmentType;
    document.getElementById('modal_user_select').value = currentUserId || '';
    
    new bootstrap.Modal(document.getElementById('assignModal')).show();
}

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(alert => {
        alert.style.transition = 'opacity 0.5s ease';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    });
}, 5000);

// Prevent card selection when clicking on form elements
document.querySelectorAll('select, button, .equipment-checkbox').forEach(el => {
    el.addEventListener('click', function(e) {
        e.stopPropagation();
    });
});
</script>

<?php include '../includes/footer.php'; ?>