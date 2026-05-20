<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'add':
                    // Set admin user ID for trigger
                    $query_set_var = "SET @admin_user_id = ?";
                    $stmt_set_var = $db->prepare($query_set_var);
                    $stmt_set_var->execute([$_SESSION['user_id']]);
                    
                    $assigned_to = $_POST['assigned_to'] ?? null;
                    $assigned_date = $assigned_to ? date('Y-m-d H:i:s') : null;
                    
                    $query = "INSERT INTO computer_inventory (
                        item_number, computer_set_description, processor, ram, storage, device_type,
                        keyboard_status, mouse_status, power_cord_status, hdmi_status,
                        operating_system, serial_number, condition_status, location_id, remarks, status, assigned_to, assigned_date
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([
                        $_POST['item_number'], $_POST['computer_set_description'], $_POST['processor'],
                        $_POST['ram'], $_POST['storage'], $_POST['device_type'],
                        $_POST['keyboard_status'], $_POST['mouse_status'], $_POST['power_cord_status'], $_POST['hdmi_status'],
                        $_POST['operating_system'], $_POST['serial_number'], $_POST['condition_status'],
                        $_POST['location_id'], $_POST['remarks'], $_POST['status'], $assigned_to, $assigned_date
                    ]);
                    $success = "Computer added successfully!";
                    break;
                    
                case 'edit':
                    // Validate admin user exists
                    $query_admin = "SELECT id FROM users WHERE id = ? AND role = 'admin'";
                    $stmt_admin = $db->prepare($query_admin);
                    $stmt_admin->execute([$_SESSION['user_id']]);
                    $admin_user = $stmt_admin->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$admin_user) {
                        // Fallback to first admin user if session user is invalid
                        $query_fallback = "SELECT id FROM users WHERE role = 'admin' LIMIT 1";
                        $stmt_fallback = $db->prepare($query_fallback);
                        $stmt_fallback->execute();
                        $admin_user = $stmt_fallback->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$admin_user) {
                            $error = "No admin user found to record assignment history.";
                            break;
                        }
                    }
                    
                    $admin_user_id = $admin_user['id'];
                    
                    // Get current assignment status to check if assignment changed
                    $query_current = "SELECT assigned_to FROM computer_inventory WHERE id = ?";
                    $stmt_current = $db->prepare($query_current);
                    $stmt_current->execute([$_POST['id']]);
                    $current_assignment = $stmt_current->fetch(PDO::FETCH_ASSOC);
                    
                    $assigned_to = $_POST['assigned_to'] == '' ? null : $_POST['assigned_to'];
                    $assigned_date = null;
                    
                    // Check if record exists and get current assignment
                    $current_assigned_to = ($current_assignment && isset($current_assignment['assigned_to'])) ? $current_assignment['assigned_to'] : null;
                    
                    // Start transaction for assignment history management
                    $db->beginTransaction();
                    
                    try {
                        // Handle assignment history - Mark previous assignment as returned
                        if ($current_assigned_to && $current_assigned_to != $assigned_to) {
                            $query_return = "UPDATE assignment_history 
                                           SET returned_date = NOW(), status = 'returned'
                                           WHERE computer_id = ? AND user_id = ? AND status = 'active'";
                            $stmt_return = $db->prepare($query_return);
                            $stmt_return->execute([$_POST['id'], $current_assigned_to]);
                        }
                        
                        // If assigning to someone new, set assigned_date and create history
                        if ($assigned_to && $assigned_to != $current_assigned_to) {
                            $assigned_date = date('Y-m-d H:i:s');
                            
                            // Validate that the user being assigned to exists
                            $query_user_check = "SELECT id FROM users WHERE id = ?";
                            $stmt_user_check = $db->prepare($query_user_check);
                            $stmt_user_check->execute([$assigned_to]);
                            $user_exists = $stmt_user_check->fetch(PDO::FETCH_ASSOC);
                            
                            if (!$user_exists) {
                                throw new Exception("User ID $assigned_to does not exist");
                            }
                            
                            // Create new assignment history record
                            $query_history = "INSERT INTO assignment_history (computer_id, user_id, assigned_date, assigned_by, notes, status)
                                            VALUES (?, ?, ?, ?, ?, ?)";
                            $stmt_history = $db->prepare($query_history);
                            $stmt_history->execute([
                                $_POST['id'],
                                $assigned_to,
                                $assigned_date,
                                $admin_user_id,
                                'Equipment assigned via full edit',
                                'active'
                            ]);
                            
                        } elseif (!$assigned_to) {
                            // If unassigning, clear assigned_date
                            $assigned_date = null;
                        } else {
                            // Keep existing assigned_date if assignment hasn't changed
                            $query_date = "SELECT assigned_date FROM computer_inventory WHERE id = ?";
                            $stmt_date = $db->prepare($query_date);
                            $stmt_date->execute([$_POST['id']]);
                            $existing_date = $stmt_date->fetch(PDO::FETCH_ASSOC);
                            $assigned_date = ($existing_date && isset($existing_date['assigned_date'])) ? $existing_date['assigned_date'] : null;
                        }
                        
                        // Update computer inventory
                        $query = "UPDATE computer_inventory SET 
                            item_number = ?, computer_set_description = ?, processor = ?, ram = ?, storage = ?, device_type = ?,
                            keyboard_status = ?, mouse_status = ?, power_cord_status = ?, hdmi_status = ?,
                            operating_system = ?, serial_number = ?, condition_status = ?, location_id = ?, remarks = ?, status = ?, assigned_to = ?, assigned_date = ?
                            WHERE id = ?";
                        $stmt = $db->prepare($query);
                        $result = $stmt->execute([
                            $_POST['item_number'], $_POST['computer_set_description'], $_POST['processor'],
                            $_POST['ram'], $_POST['storage'], $_POST['device_type'],
                            $_POST['keyboard_status'], $_POST['mouse_status'], $_POST['power_cord_status'], $_POST['hdmi_status'],
                            $_POST['operating_system'], $_POST['serial_number'], $_POST['condition_status'],
                            $_POST['location_id'], $_POST['remarks'], $_POST['status'], $assigned_to, $assigned_date, $_POST['id']
                        ]);
                        
                        if ($result) {
                            $db->commit();
                            $success = "Computer updated successfully!";
                        } else {
                            $db->rollback();
                            $error = "Failed to update computer.";
                        }
                        
                    } catch (Exception $e) {
                        $db->rollback();
                        $error = "Update failed: " . $e->getMessage();
                    }
                    break;
                    $stmt->execute([
                        $_POST['item_number'], $_POST['computer_set_description'], $_POST['processor'],
                        $_POST['ram'], $_POST['storage'], $_POST['device_type'],
                        $_POST['keyboard_status'], $_POST['mouse_status'], $_POST['power_cord_status'], $_POST['hdmi_status'],
                        $_POST['operating_system'], $_POST['serial_number'], $_POST['condition_status'],
                        $_POST['location_id'], $_POST['remarks'], $_POST['status'], $assigned_to, $assigned_date, $_POST['id']
                    ]);
                    $success = "Computer updated successfully!";
                    break;
                    
                case 'delete':
                    $query = "DELETE FROM computer_inventory WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$_POST['id']]);
                    $success = "Computer deleted successfully!";
                    break;
                    
                case 'quick_assign':
                    // Validate admin user exists
                    $query_admin = "SELECT id FROM users WHERE id = ? AND role = 'admin'";
                    $stmt_admin = $db->prepare($query_admin);
                    $stmt_admin->execute([$_SESSION['user_id']]);
                    $admin_user = $stmt_admin->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$admin_user) {
                        // Fallback to first admin user if session user is invalid
                        $query_fallback = "SELECT id FROM users WHERE role = 'admin' LIMIT 1";
                        $stmt_fallback = $db->prepare($query_fallback);
                        $stmt_fallback->execute();
                        $admin_user = $stmt_fallback->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$admin_user) {
                            $error = "No admin user found to record assignment history.";
                            break;
                        }
                    }
                    
                    $admin_user_id = $admin_user['id'];
                    
                    // Debug logging
                    error_log("Quick assign request - ID: " . ($_POST['id'] ?? 'missing') . ", assigned_to: " . ($_POST['assigned_to'] ?? 'missing') . ", admin_id: " . $admin_user_id);
                    
                    // Get current assignment status
                    $query_current = "SELECT assigned_to FROM computer_inventory WHERE id = ?";
                    $stmt_current = $db->prepare($query_current);
                    $stmt_current->execute([$_POST['id']]);
                    $current_assignment = $stmt_current->fetch(PDO::FETCH_ASSOC);
                    
                    $assigned_to = $_POST['assigned_to'] == '' ? null : $_POST['assigned_to'];
                    $assigned_date = null;
                    $status = 'available'; // Default status
                    
                    // Check if record exists and get current assignment
                    $current_assigned_to = ($current_assignment && isset($current_assignment['assigned_to'])) ? $current_assignment['assigned_to'] : null;
                    
                    // Start transaction for assignment history management
                    $db->beginTransaction();
                    
                    try {
                        // Handle assignment history - Mark previous assignment as returned
                        if ($current_assigned_to && $current_assigned_to != $assigned_to) {
                            $query_return = "UPDATE assignment_history 
                                           SET returned_date = NOW(), status = 'returned'
                                           WHERE computer_id = ? AND user_id = ? AND status = 'active'";
                            $stmt_return = $db->prepare($query_return);
                            $stmt_return->execute([$_POST['id'], $current_assigned_to]);
                        }
                        
                        // If assigning to someone new, set assigned_date and status
                        if ($assigned_to && $assigned_to != $current_assigned_to) {
                            $assigned_date = date('Y-m-d H:i:s');
                            $status = 'assigned';
                            
                            // Validate that the user being assigned to exists
                            $query_user_check = "SELECT id FROM users WHERE id = ?";
                            $stmt_user_check = $db->prepare($query_user_check);
                            $stmt_user_check->execute([$assigned_to]);
                            $user_exists = $stmt_user_check->fetch(PDO::FETCH_ASSOC);
                            
                            if (!$user_exists) {
                                throw new Exception("User ID $assigned_to does not exist");
                            }
                            
                            // Create new assignment history record
                            $query_history = "INSERT INTO assignment_history (computer_id, user_id, assigned_date, assigned_by, notes, status)
                                            VALUES (?, ?, ?, ?, ?, ?)";
                            $stmt_history = $db->prepare($query_history);
                            $stmt_history->execute([
                                $_POST['id'],
                                $assigned_to,
                                $assigned_date,
                                $admin_user_id,
                                'Equipment assigned via quick assignment',
                                'active'
                            ]);
                            
                        } elseif (!$assigned_to) {
                            // If unassigning, clear assigned_date and set status to available
                            $assigned_date = null;
                            $status = 'available';
                        } else {
                            // Keep existing assigned_date if assignment hasn't changed
                            $query_date = "SELECT assigned_date, status FROM computer_inventory WHERE id = ?";
                            $stmt_date = $db->prepare($query_date);
                            $stmt_date->execute([$_POST['id']]);
                            $existing_date = $stmt_date->fetch(PDO::FETCH_ASSOC);
                            $assigned_date = ($existing_date && isset($existing_date['assigned_date'])) ? $existing_date['assigned_date'] : null;
                            $status = ($existing_date && isset($existing_date['status'])) ? $existing_date['status'] : 'available';
                        }
                        
                        // Update computer inventory
                        $query = "UPDATE computer_inventory SET assigned_to = ?, assigned_date = ?, status = ? WHERE id = ?";
                        $stmt = $db->prepare($query);
                        $result = $stmt->execute([$assigned_to, $assigned_date, $status, $_POST['id']]);
                        
                        if ($result && $stmt->rowCount() > 0) {
                            $db->commit();
                            
                            // Return JSON response for AJAX
                            if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
                                header('Content-Type: application/json');
                                echo json_encode([
                                    'success' => true,
                                    'message' => 'Assignment updated successfully!',
                                    'assigned_to' => $assigned_to,
                                    'status' => $status,
                                    'assigned_date' => $assigned_date
                                ]);
                                exit();
                            }
                            
                            $success = "Assignment updated successfully!";
                            error_log("Quick assign successful - Assignment history managed in PHP");
                        } else {
                            $db->rollback();
                            
                            if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
                                header('Content-Type: application/json');
                                echo json_encode([
                                    'success' => false,
                                    'message' => 'No changes were made. Please check if the computer exists.'
                                ]);
                                exit();
                            }
                            
                            $error = "No changes were made. Please check if the computer exists.";
                        }
                        
                    } catch (Exception $e) {
                        $db->rollback();
                        
                        if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
                            header('Content-Type: application/json');
                            echo json_encode([
                                'success' => false,
                                'message' => 'Assignment failed: ' . $e->getMessage()
                            ]);
                            exit();
                        }
                        
                        $error = "Assignment failed: " . $e->getMessage();
                        error_log("Quick assign failed: " . $e->getMessage());
                    }
                    break;
                    
                case 'quick_location':
                    $query = "UPDATE computer_inventory SET location_id = ? WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $result = $stmt->execute([$_POST['location_id'], $_POST['id']]);
                    
                    if ($result && $stmt->rowCount() > 0) {
                        // Return JSON response for AJAX
                        if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
                            // Get location name for response
                            $query_location = "SELECT location_name FROM locations WHERE id = ?";
                            $stmt_location = $db->prepare($query_location);
                            $stmt_location->execute([$_POST['location_id']]);
                            $location = $stmt_location->fetch(PDO::FETCH_ASSOC);
                            
                            header('Content-Type: application/json');
                            echo json_encode([
                                'success' => true,
                                'message' => 'Location updated successfully!',
                                'location_id' => $_POST['location_id'],
                                'location_name' => $location ? $location['location_name'] : 'Unknown'
                            ]);
                            exit();
                        }
                        
                        $success = "Location updated successfully!";
                    } else {
                        if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
                            header('Content-Type: application/json');
                            echo json_encode([
                                'success' => false,
                                'message' => 'No changes were made. Please check if the computer exists.'
                            ]);
                            exit();
                        }
                        
                        $error = "No changes were made. Please check if the computer exists.";
                    }
                    break;
            }
        } catch (Exception $e) {
            $error = "Database error: " . $e->getMessage();
        }
        
        // Redirect after successful form submission to prevent resubmission
        if (isset($success)) {
            $_SESSION['success_message'] = $success;
            header("Location: inventory.php");
            exit();
        }
    }
}

// Get filter parameters
$location_filter = $_GET['location'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Get location type if location is specified
$location_type = 'computer_lab'; // default
$location_name = 'All Locations';
if (!empty($location_filter)) {
    $query_location = "SELECT location_name, location_type FROM locations WHERE id = ?";
    $stmt_location = $db->prepare($query_location);
    $stmt_location->execute([$location_filter]);
    $location_info = $stmt_location->fetch(PDO::FETCH_ASSOC);
    
    if ($location_info) {
        $location_type = $location_info['location_type'] ?? 'computer_lab';
        $location_name = $location_info['location_name'];
    }
}

// Build query with filters
$where_conditions = [];
$params = [];

if (!empty($location_filter)) {
    $where_conditions[] = "ci.location_id = ?";
    $params[] = $location_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "ci.status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Determine what type of inventory to show based on location type
$inventory_data = [];
$page_title = "Inventory Management";

if ($location_type === 'computer_lab') {
    // Show computer inventory for computer labs
    $query = "SELECT ci.*, l.location_name, u.full_name as assigned_user 
              FROM computer_inventory ci 
              LEFT JOIN locations l ON ci.location_id = l.id
              LEFT JOIN users u ON ci.assigned_to = u.id 
              {$where_clause}
              ORDER BY CAST(ci.item_number AS UNSIGNED) ASC";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $inventory_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $page_title = "Computer Inventory - " . $location_name;
    $inventory_type = 'computers';
    
} elseif ($location_type === 'kitchen') {
    // Show kitchen equipment inventory for kitchens
    $kitchen_where_conditions = [];
    $kitchen_params = [];
    
    if (!empty($location_filter)) {
        $kitchen_where_conditions[] = "ke.location_id = ?";
        $kitchen_params[] = $location_filter;
    }
    
    if (!empty($status_filter)) {
        $kitchen_where_conditions[] = "ke.status = ?";
        $kitchen_params[] = $status_filter;
    }
    
    $kitchen_where_clause = !empty($kitchen_where_conditions) ? "WHERE " . implode(" AND ", $kitchen_where_conditions) : "";
    
    $query = "SELECT ke.*, l.location_name, u.full_name as assigned_user 
              FROM kitchen_equipment ke 
              LEFT JOIN locations l ON ke.location_id = l.id
              LEFT JOIN users u ON ke.assigned_to = u.id 
              {$kitchen_where_clause}
              ORDER BY ke.item_number ASC";
    $stmt = $db->prepare($query);
    $stmt->execute($kitchen_params);
    $inventory_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $page_title = "Kitchen Equipment - " . $location_name;
    $inventory_type = 'kitchen';
    
} elseif ($location_type === 'office') {
    // Show office equipment inventory
    $office_where_conditions = [];
    $office_params = [];
    
    if (!empty($location_filter)) {
        $office_where_conditions[] = "oe.location_id = ?";
        $office_params[] = $location_filter;
    }
    
    if (!empty($status_filter)) {
        $office_where_conditions[] = "oe.status = ?";
        $office_params[] = $status_filter;
    }
    
    $office_where_clause = !empty($office_where_conditions) ? "WHERE " . implode(" AND ", $office_where_conditions) : "";
    
    $query = "SELECT oe.*, l.location_name, u.full_name as assigned_user 
              FROM office_equipment oe 
              LEFT JOIN locations l ON oe.location_id = l.id
              LEFT JOIN users u ON oe.assigned_to = u.id 
              {$office_where_clause}
              ORDER BY oe.item_number ASC";
    $stmt = $db->prepare($query);
    $stmt->execute($office_params);
    $inventory_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $page_title = "Office Equipment - " . $location_name;
    $inventory_type = 'office';
    
} elseif ($location_type === 'regular_lab') {
    // Show lab equipment inventory
    $lab_where_conditions = [];
    $lab_params = [];
    
    if (!empty($location_filter)) {
        $lab_where_conditions[] = "le.location_id = ?";
        $lab_params[] = $location_filter;
    }
    
    if (!empty($status_filter)) {
        $lab_where_conditions[] = "le.status = ?";
        $lab_params[] = $status_filter;
    }
    
    $lab_where_clause = !empty($lab_where_conditions) ? "WHERE " . implode(" AND ", $lab_where_conditions) : "";
    
    $query = "SELECT le.*, l.location_name, u.full_name as assigned_user 
              FROM lab_equipment le 
              LEFT JOIN locations l ON le.location_id = l.id
              LEFT JOIN users u ON le.assigned_to = u.id 
              {$lab_where_clause}
              ORDER BY le.item_number ASC";
    $stmt = $db->prepare($query);
    $stmt->execute($lab_params);
    $inventory_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $page_title = "Lab Equipment - " . $location_name;
    $inventory_type = 'lab';
    
} else {
    // Default to showing general equipment for other location types
    $general_where_conditions = [];
    $general_params = [];
    
    if (!empty($location_filter)) {
        $general_where_conditions[] = "ge.location_id = ?";
        $general_params[] = $location_filter;
    }
    
    if (!empty($status_filter)) {
        $general_where_conditions[] = "ge.status = ?";
        $general_params[] = $status_filter;
    }
    
    $general_where_clause = !empty($general_where_conditions) ? "WHERE " . implode(" AND ", $general_where_conditions) : "";
    
    $query = "SELECT ge.*, l.location_name, u.full_name as assigned_user 
              FROM general_equipment ge 
              LEFT JOIN locations l ON ge.location_id = l.id
              LEFT JOIN users u ON ge.assigned_to = u.id 
              {$general_where_clause}
              ORDER BY ge.item_number ASC";
    $stmt = $db->prepare($query);
    $stmt->execute($general_params);
    $inventory_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $page_title = "General Equipment - " . $location_name;
    $inventory_type = 'general';
}

// Get all locations for dropdown
$query = "SELECT id, location_name FROM locations ORDER BY location_name";
$stmt = $db->prepare($query);
$stmt->execute();
$locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all users for assignment dropdown
$query = "SELECT id, full_name FROM users WHERE role = 'user' ORDER BY full_name";
$stmt = $db->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = $page_title;
include '../includes/header.php';
?>

<?php 
// Check for session success message
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
?>

<?php if (isset($success)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Modern Header Section -->
<div class="inventory-header mb-4">
    <div class="row align-items-center">
        <div class="col-md-8">
            <div class="page-title-section">
                <h2 class="page-title mb-2">
                    <i class="fas fa-<?php 
                        echo $inventory_type === 'computers' ? 'desktop' : 
                            ($inventory_type === 'kitchen' ? 'utensils' : 
                            ($inventory_type === 'office' ? 'briefcase' : 
                            ($inventory_type === 'lab' ? 'flask' : 'boxes'))); 
                    ?> text-primary me-3"></i>
                    <?php echo htmlspecialchars($page_title); ?>
                </h2>
                <p class="page-subtitle text-muted mb-0">
                    <?php 
                    if ($inventory_type === 'computers') {
                        echo 'Manage and monitor all computer equipment';
                    } elseif ($inventory_type === 'kitchen') {
                        echo 'Manage kitchen appliances and utilities';
                    } elseif ($inventory_type === 'office') {
                        echo 'Manage office furniture and equipment';
                    } elseif ($inventory_type === 'lab') {
                        echo 'Manage laboratory instruments and equipment';
                    } else {
                        echo 'Manage equipment and inventory';
                    }
                    ?>
                </p>
            </div>
        </div>
        <div class="col-md-4 text-end">
            <a href="inventory_monitor.php" class="btn btn-info btn-lg modern-btn me-2">
                <i class="fas fa-monitor-waveform me-2"></i> Monitor Dashboard
            </a>
            <button type="button" class="btn btn-success btn-lg modern-btn me-2" data-bs-toggle="modal" data-bs-target="#importModal">
                <i class="fas fa-file-import me-2"></i> Import CSV
            </button>
            <button type="button" class="btn btn-primary btn-lg modern-btn" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="fas fa-plus me-2"></i> Add New Computer
            </button>
        </div>
    </div>
</div>

<!-- Active Filters Display -->
<?php if (!empty($location_filter) || !empty($status_filter)): ?>
<div class="filters-display mb-4">
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between">
                <div class="active-filters">
                    <h6 class="mb-2">
                        <i class="fas fa-filter me-2"></i>
                        Active Filters:
                    </h6>
                    <div class="filter-badges">
                        <?php if (!empty($location_filter)): ?>
                            <?php
                            // Get location name
                            $location_query = "SELECT location_name FROM locations WHERE id = ?";
                            $location_stmt = $db->prepare($location_query);
                            $location_stmt->execute([$location_filter]);
                            $location_name = $location_stmt->fetchColumn();
                            ?>
                            <span class="badge badge-primary-modern me-2">
                                <i class="fas fa-map-marker-alt me-1"></i>
                                Location: <?php echo htmlspecialchars($location_name); ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($status_filter)): ?>
                            <span class="badge badge-<?php 
                                echo $status_filter == 'available' ? 'success' : 
                                    ($status_filter == 'assigned' ? 'info' : 
                                    ($status_filter == 'maintenance' ? 'warning' : 'danger')); 
                            ?>-modern me-2">
                                <i class="fas fa-<?php 
                                    echo $status_filter == 'available' ? 'check-circle' : 
                                        ($status_filter == 'assigned' ? 'user' : 
                                        ($status_filter == 'maintenance' ? 'tools' : 'exclamation-triangle')); 
                                ?> me-1"></i>
                                Status: <?php echo ucfirst($status_filter); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="filter-actions">
                    <a href="inventory.php" class="btn btn-outline-secondary modern-btn">
                        <i class="fas fa-times me-1"></i> Clear Filters
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Statistics Overview -->
<div class="row mb-4">
    <?php
    $total_items = count($inventory_data);
    $available_count = count(array_filter($inventory_data, function($c) { return $c['status'] == 'available'; }));
    $assigned_count = count(array_filter($inventory_data, function($c) { return $c['status'] == 'assigned'; }));
    $maintenance_count = count(array_filter($inventory_data, function($c) { return $c['status'] == 'maintenance'; }));
    ?>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-primary">
                <i class="fas fa-desktop"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $total_items; ?></h3>
                <p>Total Items</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-success">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $available_count; ?></h3>
                <p>Available</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-info">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $assigned_count; ?></h3>
                <p>Assigned</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-warning">
                <i class="fas fa-tools"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $maintenance_count; ?></h3>
                <p>Maintenance</p>
            </div>
        </div>
    </div>
</div>

<!-- Modern List Container -->
<div class="modern-list-container">
    <div class="modern-list-header">
        <h3 class="modern-list-title">
            <i class="fas fa-list me-2"></i>
            Equipment Inventory
        </h3>
        <div class="modern-list-actions">
            <span class="badge badge-primary-modern">
                <?php echo $total_computers; ?> Total Items
            </span>
        </div>
    </div>
    
    <div class="modern-list-wrapper">
        <?php if (empty($inventory_data)): ?>
        <div class="empty-state">
            <div class="empty-icon">
                <i class="fas fa-box-open"></i>
            </div>
            <h4>No Items Found</h4>
            <p class="text-muted">No equipment found for this location type.</p>
        </div>
        <?php else: ?>
        <div class="list-group modern-list-group">
            <?php foreach ($inventory_data as $item): ?>
            <div class="list-group-item modern-list-item clickable-row" data-item='<?php echo json_encode($item); ?>'>
                <div class="list-item-content">
                    <!-- Single Row Layout -->
                    <div class="list-item-main">
                        <div class="list-item-icon icon-<?php echo $location_type; ?>">
                            <?php if ($inventory_type === 'computers'): ?>
                            <i class="fas fa-<?php 
                                echo $item['device_type'] == 'Desktop' ? 'desktop' : 
                                    ($item['device_type'] == 'Laptop' ? 'laptop' : 'tv'); 
                            ?>"></i>
                            <?php elseif ($inventory_type === 'kitchen'): ?>
                            <i class="fas fa-utensils"></i>
                            <?php elseif ($inventory_type === 'office'): ?>
                            <i class="fas fa-briefcase"></i>
                            <?php elseif ($inventory_type === 'lab'): ?>
                            <i class="fas fa-flask"></i>
                            <?php else: ?>
                            <i class="fas fa-box"></i>
                            <?php endif; ?>
                        </div>
                        <div class="list-item-details">
                            <div class="list-item-title">
                                <h5 class="mb-1">
                                    <?php echo htmlspecialchars($item['item_number']); ?> - 
                                    <?php 
                                    if ($inventory_type === 'computers') {
                                        echo htmlspecialchars($item['computer_set_description']);
                                    } else {
                                        echo htmlspecialchars($item['equipment_name']);
                                    }
                                    ?>
                                </h5>
                            </div>
                            <div class="list-item-meta">
                                <?php if ($inventory_type === 'computers'): ?>
                                <span class="badge badge-<?php 
                                    echo $item['device_type'] == 'Desktop' ? 'primary' : 
                                        ($item['device_type'] == 'Laptop' ? 'info' : 'success'); 
                                ?>-modern me-2">
                                    <?php echo htmlspecialchars($item['device_type']); ?>
                                </span>
                                <span class="text-muted me-3">
                                    <i class="fas fa-microchip me-1"></i>
                                    <?php echo htmlspecialchars($item['processor']); ?>
                                </span>
                                <span class="text-muted me-3">
                                    <i class="fas fa-memory me-1"></i>
                                    <?php echo htmlspecialchars($item['ram']); ?>
                                </span>
                                <?php else: ?>
                                <span class="badge badge-info-modern me-2">
                                    <?php echo htmlspecialchars($item['brand'] ?? 'Unknown Brand'); ?>
                                </span>
                                <span class="text-muted me-3">
                                    <i class="fas fa-tag me-1"></i>
                                    <?php echo htmlspecialchars($item['model'] ?? 'N/A'); ?>
                                </span>
                                <?php endif; ?>
                                <span class="text-muted me-3">
                                    <i class="fas fa-map-marker-alt me-1"></i>
                                    <?php echo htmlspecialchars($item['location_name']) ?: 'Not Set'; ?>
                                </span>
                                <span class="text-muted me-3">
                                    <i class="fas fa-<?php echo $item['assigned_user'] ? 'user-check' : 'user-slash'; ?> me-1"></i>
                                    <?php echo htmlspecialchars($item['assigned_user']) ?: 'Not Assigned'; ?>
                                </span>
                                <?php if ($inventory_type === 'computers'): ?>
                                <span class="text-muted me-3">
                                    <i class="fas fa-barcode me-1"></i>
                                    <?php echo htmlspecialchars($item['serial_number']) ?: 'N/A'; ?>
                                </span>
                                <?php endif; ?>
                                <!-- Status Badge Inline -->
                                <span class="badge badge-<?php 
                                    echo $item['status'] == 'available' ? 'success' : 
                                        ($item['status'] == 'assigned' ? 'primary' : 
                                        ($item['status'] == 'maintenance' ? 'warning' : 'danger')); 
                                ?>-modern me-3">
                                    <i class="fas fa-<?php 
                                        echo $item['status'] == 'available' ? 'check-circle' : 
                                            ($item['status'] == 'assigned' ? 'user' : 
                                            ($item['status'] == 'maintenance' ? 'tools' : 'exclamation-triangle')); 
                                    ?> me-1"></i>
                                    <?php echo ucfirst($item['status']); ?>
                                </span>
                                <!-- Action Buttons Inline -->
                                <button class="btn btn-sm btn-outline-info view-details-btn me-1" 
                                        data-item='<?php echo json_encode($item); ?>'
                                        data-bs-toggle="modal" data-bs-target="#detailsModal"
                                        title="View Full Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php if ($inventory_type === 'computers'): ?>
                                <button class="btn btn-sm btn-outline-success quick-assign-btn me-1" 
                                        data-id="<?php echo $item['id']; ?>"
                                        data-assigned="<?php echo $item['assigned_to']; ?>"
                                        data-bs-toggle="modal" data-bs-target="#assignModal"
                                        title="Quick Assign">
                                    <i class="fas fa-user-plus"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-primary edit-btn me-1" 
                                        data-item='<?php echo json_encode($item); ?>'
                                        data-bs-toggle="modal" data-bs-target="#editModal"
                                        title="Edit Computer">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger delete-btn" 
                                        data-id="<?php echo $item['id']; ?>"
                                        data-bs-toggle="modal" data-bs-target="#deleteModal"
                                        title="Delete Computer">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php else: ?>
                                <button class="btn btn-sm btn-outline-primary edit-btn me-1" 
                                        data-item='<?php echo json_encode($item); ?>'
                                        title="Edit Equipment">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-warning" 
                                        title="Maintenance">
                                    <i class="fas fa-tools"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Computer Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-desktop me-2"></i>
                    Computer Details - <span id="details-item-number"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <!-- Basic Information -->
                    <div class="col-md-6">
                        <div class="details-section">
                            <h6 class="details-section-title">
                                <i class="fas fa-info-circle me-2"></i>Basic Information
                            </h6>
                            <div class="details-grid">
                                <div class="detail-item">
                                    <label>Item Number:</label>
                                    <span id="details-item-num" class="detail-value"></span>
                                </div>
                                <div class="detail-item">
                                    <label>Description:</label>
                                    <span id="details-description" class="detail-value"></span>
                                </div>
                                <div class="detail-item">
                                    <label>Device Type:</label>
                                    <span id="details-device-type" class="detail-value"></span>
                                </div>
                                <div class="detail-item">
                                    <label>Serial Number:</label>
                                    <span id="details-serial" class="detail-value serial-code"></span>
                                </div>
                                <div class="detail-item">
                                    <label>Operating System:</label>
                                    <span id="details-os" class="detail-value"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Technical Specifications -->
                    <div class="col-md-6">
                        <div class="details-section">
                            <h6 class="details-section-title">
                                <i class="fas fa-microchip me-2"></i>Technical Specifications
                            </h6>
                            <div class="details-grid">
                                <div class="detail-item">
                                    <label>Processor:</label>
                                    <span id="details-processor" class="detail-value"></span>
                                </div>
                                <div class="detail-item">
                                    <label>RAM:</label>
                                    <span id="details-ram" class="detail-value"></span>
                                </div>
                                <div class="detail-item">
                                    <label>Storage:</label>
                                    <span id="details-storage" class="detail-value"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <!-- Peripheral Status -->
                    <div class="col-md-6">
                        <div class="details-section">
                            <h6 class="details-section-title">
                                <i class="fas fa-keyboard me-2"></i>Peripheral Status
                            </h6>
                            <div class="peripherals-grid">
                                <div class="peripheral-item">
                                    <i class="fas fa-keyboard me-2"></i>
                                    <span>Keyboard:</span>
                                    <span id="details-keyboard" class="peripheral-status"></span>
                                </div>
                                <div class="peripheral-item">
                                    <i class="fas fa-mouse me-2"></i>
                                    <span>Mouse:</span>
                                    <span id="details-mouse" class="peripheral-status"></span>
                                </div>
                                <div class="peripheral-item">
                                    <i class="fas fa-plug me-2"></i>
                                    <span>Power Cord:</span>
                                    <span id="details-power-cord" class="peripheral-status"></span>
                                </div>
                                <div class="peripheral-item">
                                    <i class="fas fa-tv me-2"></i>
                                    <span>HDMI Cable:</span>
                                    <span id="details-hdmi" class="peripheral-status"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Status & Assignment -->
                    <div class="col-md-6">
                        <div class="details-section">
                            <h6 class="details-section-title">
                                <i class="fas fa-chart-line me-2"></i>Status & Assignment
                            </h6>
                            <div class="details-grid">
                                <div class="detail-item">
                                    <label>Condition:</label>
                                    <span id="details-condition" class="detail-value"></span>
                                </div>
                                <div class="detail-item">
                                    <label>Status:</label>
                                    <span id="details-status" class="detail-value"></span>
                                </div>
                                <div class="detail-item">
                                    <label>Location:</label>
                                    <span id="details-location" class="detail-value"></span>
                                </div>
                                <div class="detail-item">
                                    <label>Assigned To:</label>
                                    <span id="details-assigned" class="detail-value"></span>
                                </div>
                                <div class="detail-item" id="assigned-date-item" style="display: none;">
                                    <label>Assigned Date:</label>
                                    <span id="details-assigned-date" class="detail-value"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Remarks Section -->
                <div class="row mt-4" id="remarks-section" style="display: none;">
                    <div class="col-12">
                        <div class="details-section">
                            <h6 class="details-section-title">
                                <i class="fas fa-sticky-note me-2"></i>Remarks
                            </h6>
                            <div class="remarks-content">
                                <p id="details-remarks" class="detail-value"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Close
                </button>
                <button type="button" class="btn btn-warning" id="edit-from-details">
                    <i class="fas fa-edit me-1"></i> Edit Computer
                </button>
                <button type="button" class="btn btn-primary" id="assign-from-details">
                    <i class="fas fa-user-plus me-1"></i> Quick Assign
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import CSV Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Use the dedicated import page to upload and process CSV files for computer inventory.</p>
                <div class="d-grid gap-2">
                    <a href="import_csv.php" class="btn btn-primary">
                        <i class="fas fa-file-import"></i> Go to Import Page
                    </a>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Computer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Item Number</label>
                                <input type="text" class="form-control" name="item_number" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Computer Set Description</label>
                                <input type="text" class="form-control" name="computer_set_description" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Processor</label>
                                <input type="text" class="form-control" name="processor" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">RAM</label>
                                <input type="text" class="form-control" name="ram" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Storage</label>
                                <input type="text" class="form-control" name="storage" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Device Type</label>
                                <select class="form-select" name="device_type" required>
                                    <option value="Desktop">Desktop</option>
                                    <option value="Laptop">Laptop</option>
                                    <option value="All-in-One">All-in-One</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Operating System</label>
                                <input type="text" class="form-control" name="operating_system">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Condition</label>
                                <select class="form-select" name="condition_status" required>
                                    <option value="Excellent">Excellent</option>
                                    <option value="Good">Good</option>
                                    <option value="Fair">Fair</option>
                                    <option value="Poor">Poor</option>
                                    <option value="Damaged">Damaged</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" required>
                                    <option value="available">Available</option>
                                    <option value="assigned">Assigned</option>
                                    <option value="maintenance">Maintenance</option>
                                    <option value="damaged">Damaged</option>
                                    <option value="retired">Retired</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <h6 class="mb-3">Peripheral Status</h6>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Keyboard</label>
                                <select class="form-select" name="keyboard_status">
                                    <option value="OK">OK</option>
                                    <option value="Missing">Missing</option>
                                    <option value="Damaged">Damaged</option>
                                    <option value="Needs Repair">Needs Repair</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Mouse</label>
                                <select class="form-select" name="mouse_status">
                                    <option value="OK">OK</option>
                                    <option value="Missing">Missing</option>
                                    <option value="Damaged">Damaged</option>
                                    <option value="Needs Repair">Needs Repair</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Power Cord</label>
                                <select class="form-select" name="power_cord_status">
                                    <option value="OK">OK</option>
                                    <option value="Missing">Missing</option>
                                    <option value="Damaged">Damaged</option>
                                    <option value="Needs Repair">Needs Repair</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">HDMI Cable</label>
                                <select class="form-select" name="hdmi_status">
                                    <option value="OK">OK</option>
                                    <option value="Missing">Missing</option>
                                    <option value="Damaged">Damaged</option>
                                    <option value="Needs Repair">Needs Repair</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Serial Number</label>
                                <input type="text" class="form-control" name="serial_number" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Location</label>
                                <select class="form-select" name="location_id" required>
                                    <option value="">Select Location</option>
                                    <?php foreach ($locations as $location): ?>
                                    <option value="<?php echo $location['id']; ?>"><?php echo htmlspecialchars($location['location_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Assign To User</label>
                                <select class="form-select" name="assigned_to">
                                    <option value="">Not Assigned</option>
                                    <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea class="form-control" name="remarks" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Computer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Computer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Item Number</label>
                                <input type="text" class="form-control" name="item_number" id="edit_item_number" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Computer Set Description</label>
                                <input type="text" class="form-control" name="computer_set_description" id="edit_description" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Processor</label>
                                <input type="text" class="form-control" name="processor" id="edit_processor" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">RAM</label>
                                <input type="text" class="form-control" name="ram" id="edit_ram" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Storage</label>
                                <input type="text" class="form-control" name="storage" id="edit_storage" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Device Type</label>
                                <select class="form-select" name="device_type" id="edit_device_type" required>
                                    <option value="Desktop">Desktop</option>
                                    <option value="Laptop">Laptop</option>
                                    <option value="All-in-One">All-in-One</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Operating System</label>
                                <input type="text" class="form-control" name="operating_system" id="edit_os">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Condition</label>
                                <select class="form-select" name="condition_status" id="edit_condition" required>
                                    <option value="Excellent">Excellent</option>
                                    <option value="Good">Good</option>
                                    <option value="Fair">Fair</option>
                                    <option value="Poor">Poor</option>
                                    <option value="Damaged">Damaged</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" id="edit_status" required>
                                    <option value="available">Available</option>
                                    <option value="assigned">Assigned</option>
                                    <option value="maintenance">Maintenance</option>
                                    <option value="damaged">Damaged</option>
                                    <option value="retired">Retired</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <h6 class="mb-3">Peripheral Status</h6>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Keyboard</label>
                                <select class="form-select" name="keyboard_status" id="edit_keyboard">
                                    <option value="OK">OK</option>
                                    <option value="Missing">Missing</option>
                                    <option value="Damaged">Damaged</option>
                                    <option value="Needs Repair">Needs Repair</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Mouse</label>
                                <select class="form-select" name="mouse_status" id="edit_mouse">
                                    <option value="OK">OK</option>
                                    <option value="Missing">Missing</option>
                                    <option value="Damaged">Damaged</option>
                                    <option value="Needs Repair">Needs Repair</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Power Cord</label>
                                <select class="form-select" name="power_cord_status" id="edit_power_cord">
                                    <option value="OK">OK</option>
                                    <option value="Missing">Missing</option>
                                    <option value="Damaged">Damaged</option>
                                    <option value="Needs Repair">Needs Repair</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">HDMI Cable</label>
                                <select class="form-select" name="hdmi_status" id="edit_hdmi">
                                    <option value="OK">OK</option>
                                    <option value="Missing">Missing</option>
                                    <option value="Damaged">Damaged</option>
                                    <option value="Needs Repair">Needs Repair</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Serial Number</label>
                                <input type="text" class="form-control" name="serial_number" id="edit_serial" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Location</label>
                                <select class="form-select" name="location_id" id="edit_location" required>
                                    <option value="">Select Location</option>
                                    <?php foreach ($locations as $location): ?>
                                    <option value="<?php echo $location['id']; ?>"><?php echo htmlspecialchars($location['location_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Assign To User</label>
                                <select class="form-select" name="assigned_to" id="edit_assigned">
                                    <option value="">Not Assigned</option>
                                    <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea class="form-control" name="remarks" id="edit_remarks" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Computer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Quick Assignment Modal -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Change Assignment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="quick_assign">
                    <input type="hidden" name="id" id="assign_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Assign To User</label>
                        <select class="form-select" name="assigned_to" id="assign_user">
                            <option value="">Not Assigned</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Assignment Change:</strong> This will automatically update the assignment history and track when the equipment was assigned or returned.
                        <?php if (!empty($users)): ?>
                        <br><small class="text-muted">Available users: <?php echo count($users); ?> active users</small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Quick Location Modal -->
<div class="modal fade" id="locationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Change Location</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="quick_location">
                    <input type="hidden" name="id" id="location_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <select class="form-select" name="location_id" id="location_select" required>
                            <option value="">Select Location</option>
                            <?php foreach ($locations as $location): ?>
                            <option value="<?php echo $location['id']; ?>"><?php echo htmlspecialchars($location['location_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Location Change:</strong> This will update the physical location of the equipment in the system.
                        <?php if (!empty($locations)): ?>
                        <br><small class="text-muted">Available locations: <?php echo count($locations); ?> lab locations</small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Location</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Computer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_id">
                    <p>Are you sure you want to delete this computer? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Dynamic Icon Background Colors Based on Location Type */
.list-item-icon.icon-computer_lab {
    background: linear-gradient(135deg, #007bff, #0056b3) !important;
}

.list-item-icon.icon-regular_lab {
    background: linear-gradient(135deg, #28a745, #1e7e34) !important;
}

.list-item-icon.icon-kitchen {
    background: linear-gradient(135deg, #fd7e14, #e55a00) !important;
}

.list-item-icon.icon-office {
    background: linear-gradient(135deg, #6f42c1, #5a2d91) !important;
}

.list-item-icon.icon-storage {
    background: linear-gradient(135deg, #6c757d, #545b62) !important;
}

.list-item-icon.icon-classroom {
    background: linear-gradient(135deg, #17a2b8, #117a8b) !important;
}

.list-item-icon.icon-library {
    background: linear-gradient(135deg, #dc3545, #c82333) !important;
}

.list-item-icon.icon-conference {
    background: linear-gradient(135deg, #ffc107, #e0a800) !important;
}

.list-item-icon.icon-other {
    background: linear-gradient(135deg, #343a40, #23272b) !important;
}

/* Ensure text color is white for all icon backgrounds */
.list-item-icon.icon-computer_lab i,
.list-item-icon.icon-regular_lab i,
.list-item-icon.icon-kitchen i,
.list-item-icon.icon-office i,
.list-item-icon.icon-storage i,
.list-item-icon.icon-classroom i,
.list-item-icon.icon-library i,
.list-item-icon.icon-other i {
    color: white !important;
}

/* Special case for conference room - dark text on light background */
.list-item-icon.icon-conference i {
    color: #212529 !important;
}
</style>

<?php include '../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    console.log('Inventory management script loaded with enhanced details modal');
    
    // Details modal functionality
    $('.view-details-btn, .clickable-row').click(function(e) {
        e.stopPropagation();
        const computer = $(this).data('computer');
        console.log('Showing details for computer:', computer);
        
        // Populate basic information
        $('#details-item-number').text(computer.item_number);
        $('#details-item-num').text(computer.item_number);
        $('#details-description').text(computer.computer_set_description);
        $('#details-device-type').text(computer.device_type);
        $('#details-serial').text(computer.serial_number);
        $('#details-os').text(computer.operating_system || 'Not specified');
        
        // Populate technical specifications
        $('#details-processor').text(computer.processor);
        $('#details-ram').text(computer.ram);
        $('#details-storage').text(computer.storage);
        
        // Populate peripheral status with badges
        updatePeripheralStatus('#details-keyboard', computer.keyboard_status);
        updatePeripheralStatus('#details-mouse', computer.mouse_status);
        updatePeripheralStatus('#details-power-cord', computer.power_cord_status);
        updatePeripheralStatus('#details-hdmi', computer.hdmi_status);
        
        // Populate status & assignment
        updateStatusBadge('#details-condition', computer.condition_status, getConditionClass(computer.condition_status));
        updateStatusBadge('#details-status', computer.status, getStatusClass(computer.status));
        $('#details-location').text(computer.location_name || 'Not assigned');
        $('#details-assigned').text(computer.assigned_user || 'Not assigned');
        
        // Show/hide assigned date
        if (computer.assigned_date) {
            $('#details-assigned-date').text(formatDate(computer.assigned_date));
            $('#assigned-date-item').show();
        } else {
            $('#assigned-date-item').hide();
        }
        
        // Show/hide remarks
        if (computer.remarks && computer.remarks.trim()) {
            $('#details-remarks').text(computer.remarks);
            $('#remarks-section').show();
        } else {
            $('#remarks-section').hide();
        }
        
        // Store computer data for action buttons
        $('#edit-from-details').data('computer', computer);
        $('#assign-from-details').data('computer', computer);
        
        // Show the modal
        $('#detailsModal').modal('show');
    });
    
    // Helper function to update peripheral status
    function updatePeripheralStatus(selector, status) {
        const element = $(selector);
        element.removeClass('badge-success-modern badge-danger-modern badge-warning-modern');
        
        if (status === 'OK') {
            element.addClass('badge-success-modern').text('OK');
        } else if (status === 'Missing') {
            element.addClass('badge-danger-modern').text('Missing');
        } else if (status === 'Damaged') {
            element.addClass('badge-danger-modern').text('Damaged');
        } else {
            element.addClass('badge-warning-modern').text(status || 'Unknown');
        }
    }
    
    // Helper function to update status badges
    function updateStatusBadge(selector, status, className) {
        const element = $(selector);
        element.removeClass('badge-success-modern badge-primary-modern badge-warning-modern badge-danger-modern badge-info-modern');
        element.addClass(className).text(status);
    }
    
    // Helper function to get condition class
    function getConditionClass(condition) {
        switch(condition) {
            case 'Excellent':
            case 'Good': return 'badge-success-modern';
            case 'Fair': return 'badge-warning-modern';
            case 'Poor':
            case 'Damaged': return 'badge-danger-modern';
            default: return 'badge-info-modern';
        }
    }
    
    // Helper function to get status class
    function getStatusClass(status) {
        switch(status) {
            case 'available': return 'badge-success-modern';
            case 'assigned': return 'badge-primary-modern';
            case 'maintenance': return 'badge-warning-modern';
            case 'damaged':
            case 'retired': return 'badge-danger-modern';
            default: return 'badge-info-modern';
        }
    }
    
    // Helper function to format date
    function formatDate(dateString) {
        if (!dateString) return 'Not specified';
        const date = new Date(dateString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    }
    
    // Edit from details modal
    $('#edit-from-details').click(function() {
        const computer = $(this).data('computer');
        $('#detailsModal').modal('hide');
        
        // Populate edit modal
        $('#edit_id').val(computer.id);
        $('#edit_item_number').val(computer.item_number);
        $('#edit_description').val(computer.computer_set_description);
        $('#edit_processor').val(computer.processor);
        $('#edit_ram').val(computer.ram);
        $('#edit_storage').val(computer.storage);
        $('#edit_device_type').val(computer.device_type);
        $('#edit_os').val(computer.operating_system);
        $('#edit_condition').val(computer.condition_status);
        $('#edit_status').val(computer.status);
        $('#edit_keyboard').val(computer.keyboard_status);
        $('#edit_mouse').val(computer.mouse_status);
        $('#edit_power_cord').val(computer.power_cord_status);
        $('#edit_hdmi').val(computer.hdmi_status);
        $('#edit_serial').val(computer.serial_number);
        $('#edit_location').val(computer.location_id);
        $('#edit_assigned').val(computer.assigned_to || '');
        $('#edit_remarks').val(computer.remarks);
        
        $('#editModal').modal('show');
    });
    
    // Assign from details modal
    $('#assign-from-details').click(function() {
        const computer = $(this).data('computer');
        $('#detailsModal').modal('hide');
        
        $('#assign_id').val(computer.id);
        $('#assign_user').val(computer.assigned_to || '');
        
        $('#assignModal').modal('show');
    });
    
    // Full edit modal
    $('.edit-btn').click(function() {
        const computer = $(this).data('computer');
        console.log('Edit button clicked for computer:', computer);
        
        // Populate all form fields
        $('#edit_id').val(computer.id);
        $('#edit_item_number').val(computer.item_number);
        $('#edit_description').val(computer.computer_set_description);
        $('#edit_processor').val(computer.processor);
        $('#edit_ram').val(computer.ram);
        $('#edit_storage').val(computer.storage);
        $('#edit_device_type').val(computer.device_type);
        $('#edit_os').val(computer.operating_system);
        $('#edit_condition').val(computer.condition_status);
        $('#edit_status').val(computer.status);
        $('#edit_keyboard').val(computer.keyboard_status);
        $('#edit_mouse').val(computer.mouse_status);
        $('#edit_power_cord').val(computer.power_cord_status);
        $('#edit_hdmi').val(computer.hdmi_status);
        $('#edit_serial').val(computer.serial_number);
        $('#edit_location').val(computer.location_id);
        $('#edit_assigned').val(computer.assigned_to || '');
        $('#edit_remarks').val(computer.remarks);
    });
    
    // Quick assignment functionality
    $('.quick-assign-btn').click(function() {
        const id = $(this).data('id');
        const assigned = $(this).data('assigned');
        console.log('Quick assign clicked for ID:', id, 'Current assigned:', assigned);
        
        $('#assign_id').val(id);
        $('#assign_user').val(assigned || '');
    });
    
    // Delete modal
    $('.delete-btn').click(function() {
        const id = $(this).data('id');
        console.log('Delete button clicked for ID:', id);
        $('#delete_id').val(id);
    });
    
    // AJAX form submission for quick assignment
    $('#assignModal form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('ajax', '1');
        
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.text();
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Updating...');
        
        $.ajax({
            url: 'inventory.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message);
                    $('#assignModal').modal('hide');
                    
                    // Refresh the page to show updated data
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showAlert('danger', response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                showAlert('danger', 'An error occurred while updating the assignment.');
            },
            complete: function() {
                submitBtn.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Function to show alerts
    function showAlert(type, message) {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        // Remove existing alerts
        $('.alert').remove();
        
        // Add new alert at the top
        $('.inventory-header').before(alertHtml);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            $('.alert').fadeOut();
        }, 5000);
    }
    
    // Auto-hide existing alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut();
    }, 5000);
});
</script>

</script>