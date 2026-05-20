<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get the room ID and category from URL parameters
$room_id = $_GET['room_id'] ?? '';
$category_id = $_GET['category_id'] ?? '';

if (empty($room_id) || empty($category_id)) {
    header("Location: inventory_categories.php");
    exit();
}

// Get category details
$category_query = "SELECT * FROM location_types WHERE id = ? AND is_active = 1";
$category_stmt = $db->prepare($category_query);
$category_stmt->execute([$category_id]);
$category_info = $category_stmt->fetch(PDO::FETCH_ASSOC);

if (!$category_info) {
    header("Location: inventory_categories.php");
    exit();
}

$category = $category_info['type_code'];

// Set CSS variables for dynamic colors
$primary_color = $category_info['color_primary'];
$secondary_color = $category_info['color_secondary'];
$primary_rgb = hexdec(substr($primary_color, 1, 2)) . ',' . hexdec(substr($primary_color, 3, 2)) . ',' . hexdec(substr($primary_color, 5, 2));

// Get room details with manager info
$room_query = "SELECT l.*, 
               f.full_name as manager_name, 
               f.email as manager_email,
               f.department as manager_department,
               lt.type_name as location_type_name,
               lt.type_code as location_type_code,
               lt.icon_class as location_type_icon,
               lt.color_primary as location_type_color_primary,
               lt.color_secondary as location_type_color_secondary,
               lt.equipment_label as location_type_equipment_label
               FROM locations l 
               LEFT JOIN users f ON l.facilitator_id = f.id
               LEFT JOIN location_types lt ON l.location_type_id = lt.id
               WHERE l.id = ? AND l.location_type_id = ?";
$room_stmt = $db->prepare($room_query);
$room_stmt->execute([$room_id, $category_id]);
$room = $room_stmt->fetch(PDO::FETCH_ASSOC);

if (!$room) {
    header("Location: inventory_rooms.php?category_id=" . $category_id);
    exit();
}

// Handle equipment edit form submission
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'edit_equipment') {
    try {
        $equipment_id = $_POST['equipment_id'];
        $equipment_type = $_POST['equipment_type'] ?? 'computer_inventory';
        
        // Determine which table to update based on equipment_type
        $table_name = $equipment_type;
        
        // Build update query based on table type
        switch ($equipment_type) {
            case 'computer_inventory':
                $update_query = "UPDATE computer_inventory SET 
                                item_number = ?, 
                                computer_set_description = ?, 
                                processor = ?, 
                                ram = ?, 
                                storage = ?, 
                                device_type = ?, 
                                operating_system = ?, 
                                serial_number = ?, 
                                status = ?, 
                                condition_status = ?, 
                                keyboard_status = ?, 
                                mouse_status = ?, 
                                power_cord_status = ?, 
                                hdmi_status = ?, 
                                remarks = ?,
                                updated_at = NOW()
                                WHERE id = ?";
                
                $update_stmt = $db->prepare($update_query);
                $update_stmt->execute([
                    $_POST['item_number'],
                    $_POST['computer_set_description'],
                    $_POST['processor'],
                    $_POST['ram'],
                    $_POST['storage'],
                    $_POST['device_type'],
                    $_POST['operating_system'],
                    $_POST['serial_number'],
                    $_POST['status'],
                    $_POST['condition_status'],
                    $_POST['keyboard_status'],
                    $_POST['mouse_status'],
                    $_POST['power_cord_status'],
                    $_POST['hdmi_status'],
                    $_POST['remarks'],
                    $equipment_id
                ]);
                break;
                
            case 'general_equipment':
            case 'kitchen_equipment':
            case 'lab_equipment':
            case 'office_equipment':
                // Generic update for other equipment types
                $update_query = "UPDATE $table_name SET 
                                item_number = ?, 
                                equipment_name = ?, 
                                brand = ?, 
                                model = ?, 
                                serial_number = ?, 
                                status = ?, 
                                condition_status = ?, 
                                remarks = ?,
                                updated_at = NOW()
                                WHERE id = ?";
                
                $update_stmt = $db->prepare($update_query);
                $update_stmt->execute([
                    $_POST['item_number'],
                    $_POST['equipment_name'],
                    $_POST['brand'] ?? '',
                    $_POST['model'] ?? '',
                    $_POST['serial_number'] ?? '',
                    $_POST['status'],
                    $_POST['condition_status'],
                    $_POST['remarks'] ?? '',
                    $equipment_id
                ]);
                break;
                
            default:
                throw new Exception("Invalid equipment type");
        }
        
        $_SESSION['success_message'] = "Equipment updated successfully!";
        header("Location: inventory_room_detail.php?room_id=" . $room_id . "&category_id=" . $category_id);
        exit();
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error updating equipment: " . $e->getMessage();
        header("Location: inventory_room_detail.php?room_id=" . $room_id . "&category_id=" . $category_id);
        exit();
    }
}

// Get success/error messages from session
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Define all equipment tables with their configurations
$equipment_tables = [
    'computer_inventory' => [
        'name' => 'Computer Equipment',
        'icon' => 'fa-desktop',
        'label_singular' => 'Computer',
        'join_user' => true,
        'item_name_column' => 'computer_set_description',
        'color' => 'primary'
    ],
    'general_equipment' => [
        'name' => 'General Equipment',
        'icon' => 'fa-box',
        'label_singular' => 'General Item',
        'join_user' => true,
        'item_name_column' => 'equipment_name',
        'color' => 'secondary'
    ],
    'kitchen_equipment' => [
        'name' => 'Kitchen Equipment',
        'icon' => 'fa-utensils',
        'label_singular' => 'Kitchen Item',
        'join_user' => true,
        'item_name_column' => 'equipment_name',
        'color' => 'warning'
    ],
    'lab_equipment' => [
        'name' => 'Lab Equipment',
        'icon' => 'fa-flask',
        'label_singular' => 'Lab Item',
        'join_user' => true,
        'item_name_column' => 'equipment_name',
        'color' => 'danger'
    ],
    'office_equipment' => [
        'name' => 'Office Equipment',
        'icon' => 'fa-briefcase',
        'label_singular' => 'Office Item',
        'join_user' => true,
        'item_name_column' => 'equipment_name',
        'color' => 'info'
    ]
];

// Get all equipment in this room from ALL equipment tables
$all_equipment = [];
$equipment_count = 0;
$available_count = 0;
$assigned_count = 0;
$maintenance_count = 0;
$condemned_count = 0;

foreach ($equipment_tables as $table_name => $table_config) {
    try {
        // Check if table exists
        $check_table = $db->query("SHOW TABLES LIKE '$table_name'")->fetch();
        
        if ($check_table) {
            // Check if table has location_id column
            $check_column = $db->query("SHOW COLUMNS FROM $table_name LIKE 'location_id'")->fetch();
            
            if ($check_column) {
                // Build query based on table configuration
                if ($table_config['join_user']) {
                    // Check if table has assigned_to column
                    $check_assigned = $db->query("SHOW COLUMNS FROM $table_name LIKE 'assigned_to'")->fetch();
                    
                    if ($check_assigned) {
                        $query = "SELECT 
                                 '$table_name' as equipment_type,
                                 '" . $table_config['name'] . "' as table_name,
                                 '" . $table_config['icon'] . "' as icon_class,
                                 '" . $table_config['color'] . "' as icon_color,
                                 '" . $table_config['label_singular'] . "' as equipment_label,
                                 '" . $table_config['item_name_column'] . "' as item_name_column,
                                 e.*,
                                 u.full_name as assigned_to_name
                                 FROM $table_name e
                                 LEFT JOIN users u ON e.assigned_to = u.id
                                 WHERE e.location_id = ? AND (e.is_condemned IS NULL OR e.is_condemned = FALSE OR e.is_condemned = 0)
                                 ORDER BY e.item_number";
                    } else {
                        $query = "SELECT 
                                 '$table_name' as equipment_type,
                                 '" . $table_config['name'] . "' as table_name,
                                 '" . $table_config['icon'] . "' as icon_class,
                                 '" . $table_config['color'] . "' as icon_color,
                                 '" . $table_config['label_singular'] . "' as equipment_label,
                                 '" . $table_config['item_name_column'] . "' as item_name_column,
                                 e.*,
                                 NULL as assigned_to_name
                                 FROM $table_name e
                                 WHERE e.location_id = ? AND (e.is_condemned IS NULL OR e.is_condemned = FALSE OR e.is_condemned = 0)
                                 ORDER BY e.item_number";
                    }
                } else {
                    $query = "SELECT 
                             '$table_name' as equipment_type,
                             '" . $table_config['name'] . "' as table_name,
                             '" . $table_config['icon'] . "' as icon_class,
                             '" . $table_config['color'] . "' as icon_color,
                             '" . $table_config['label_singular'] . "' as equipment_label,
                             '" . $table_config['item_name_column'] . "' as item_name_column,
                             e.*,
                             NULL as assigned_to_name
                             FROM $table_name e
                             WHERE e.location_id = ? AND (e.is_condemned IS NULL OR e.is_condemned = FALSE OR e.is_condemned = 0)
                             ORDER BY e.item_number";
                }
                
                $stmt = $db->prepare($query);
                $stmt->execute([$room_id]);
                $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Add to all equipment array
                $all_equipment = array_merge($all_equipment, $equipment);
                
                // Get counts for this table
                $count_query = "SELECT 
                               COUNT(*) as total,
                               SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
                               SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as assigned,
                               SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance,
                               SUM(CASE WHEN is_condemned = TRUE OR is_condemned = 1 THEN 1 ELSE 0 END) as condemned
                               FROM $table_name 
                               WHERE location_id = ?";
                
                $count_stmt = $db->prepare($count_query);
                $count_stmt->execute([$room_id]);
                $counts = $count_stmt->fetch(PDO::FETCH_ASSOC);
                
                $equipment_count += $counts['total'] ?? 0;
                $available_count += $counts['available'] ?? 0;
                $assigned_count += $counts['assigned'] ?? 0;
                $maintenance_count += $counts['maintenance'] ?? 0;
                $condemned_count += $counts['condemned'] ?? 0;
            }
        }
    } catch (Exception $e) {
        // Skip table if there's an error
        error_log("Error accessing table $table_name: " . $e->getMessage());
        continue;
    }
}

// Sort all equipment by item number
usort($all_equipment, function($a, $b) {
    return strcmp($a['item_number'] ?? '', $b['item_number'] ?? '');
});

// Also keep separate arrays for different views if needed
$room_equipment = $all_equipment;

// Get recent activities (first 10 items sorted by updated_at)
usort($all_equipment, function($a, $b) {
    $dateA = strtotime($a['updated_at'] ?? '1970-01-01');
    $dateB = strtotime($b['updated_at'] ?? '1970-01-01');
    return $dateB - $dateA;
});
$recent_activities = array_slice($all_equipment, 0, 10);

// Sort back by item number for display
usort($all_equipment, function($a, $b) {
    return strcmp($a['item_number'] ?? '', $b['item_number'] ?? '');
});

$page_title = htmlspecialchars($room['location_name']) . " - " . htmlspecialchars($category_info['type_name']) . " Room Details";
include '../includes/header.php';
?>

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

/* Room Header */
.room-detail-header {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    border-radius: 24px;
    padding: 2.5rem;
    margin-bottom: 2rem;
    color: white;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(var(--primary-rgb), 0.25);
}

.room-detail-header::before {
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

.header-stats {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
    margin-top: 1rem;
}

.header-stat {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(255,255,255,0.15);
    padding: 0.5rem 1rem;
    border-radius: 50px;
    backdrop-filter: blur(5px);
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.header-stat:hover {
    background: rgba(255,255,255,0.25);
    transform: translateY(-2px);
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
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

.stat-trend {
    font-size: 0.75rem;
    color: var(--primary-color);
    margin-top: 0.3rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
    background: rgba(var(--primary-rgb), 0.08);
    padding: 0.2rem 0.6rem;
    border-radius: 50px;
    width: fit-content;
}

/* Equipment Cards */
.equipment-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.03);
    border: 1px solid rgba(0,0,0,0.03);
    overflow: hidden;
    transition: all 0.3s ease;
    height: 100%;
    cursor: pointer;
    position: relative;
}

.equipment-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(var(--primary-rgb), 0.15);
    border-color: var(--primary-color);
}

.equipment-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 0;
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    transition: height 0.3s ease;
    border-radius: 4px 0 0 4px;
}

.equipment-card:hover::before {
    height: 100%;
}

.equipment-card .card-body {
    padding: 1.5rem;
}

.equipment-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    margin-bottom: 1rem;
}

.equipment-icon.bg-primary { background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%); }
.equipment-icon.bg-success { background: linear-gradient(135deg, #198754 0%, #157347 100%); }
.equipment-icon.bg-warning { background: linear-gradient(135deg, #ffc107 0%, #ffca2c 100%); }
.equipment-icon.bg-danger { background: linear-gradient(135deg, #dc3545 0%, #bb2d3b 100%); }
.equipment-icon.bg-info { background: linear-gradient(135deg, #0dcaf0 0%, #31d2f2 100%); }
.equipment-icon.bg-secondary { background: linear-gradient(135deg, #6c757d 0%, #5c636a 100%); }

/* Equipment Table */
.equipment-table {
    width: 100%;
    border-collapse: collapse;
}

.equipment-table th {
    background: #f8fafc;
    color: #1e293b;
    font-weight: 600;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 1rem;
    border-bottom: 2px solid var(--primary-color);
}

.equipment-table td {
    padding: 1rem;
    border-bottom: 1px solid #e9ecef;
    vertical-align: middle;
}

.equipment-row {
    transition: all 0.3s ease;
    cursor: pointer;
}

.equipment-row:hover {
    background: rgba(var(--primary-rgb), 0.05);
}

/* Status Badges */
.status-badge {
    padding: 0.3rem 0.8rem;
    border-radius: 50px;
    font-size: 0.7rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.status-badge.available { background: #d1e7dd; color: #0a5e3a; }
.status-badge.assigned { background: #fff3cd; color: #856404; }
.status-badge.maintenance { background: #cff4fc; color: #055160; }
.status-badge.damaged { background: #f8d7da; color: #9a1c2a; }
.status-badge.condemned { background: #e9ecef; color: #495057; }

/* Action Buttons */
.action-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: none;
    transition: all 0.2s ease;
    cursor: pointer;
    margin: 0 2px;
}

.action-btn.view { background: #cff4fc; color: #0dcaf0; }
.action-btn.edit { background: #e7f1ff; color: #0d6efd; }

.action-btn:hover {
    transform: translateY(-2px);
    filter: brightness(0.9);
}

.action-btn.view:hover { background: #0dcaf0; color: white; }
.action-btn.edit:hover { background: #0d6efd; color: white; }

/* Manager Card */
.manager-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.03);
    border: 1px solid rgba(0,0,0,0.03);
    height: 100%;
}

.manager-avatar {
    width: 80px;
    height: 80px;
    border-radius: 40px;
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.5rem;
    margin: 0 auto 1rem;
    box-shadow: 0 10px 20px rgba(var(--primary-rgb), 0.2);
}

/* Activity Card */
.activity-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.03);
    border: 1px solid rgba(0,0,0,0.03);
    height: 100%;
}

.activity-item {
    padding: 1rem;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.2s ease;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-item:hover {
    background: rgba(var(--primary-rgb), 0.02);
    transform: translateX(5px);
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1rem;
}

.activity-icon.success { background: #198754; }
.activity-icon.warning { background: #ffc107; }
.activity-icon.info { background: #0dcaf0; }
.activity-icon.primary { background: var(--primary-color); }

/* Quick Actions Card */
.quick-actions-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.03);
    border: 1px solid rgba(0,0,0,0.03);
    height: 100%;
}

.quick-action-btn {
    padding: 0.8rem 1rem;
    border-radius: 12px;
    font-size: 0.9rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.8rem;
    transition: all 0.2s ease;
    border: 1px solid #e9ecef;
    background: white;
    color: #1e293b;
    width: 100%;
    margin-bottom: 0.5rem;
    text-decoration: none;
}

.quick-action-btn:hover {
    background: rgba(var(--primary-rgb), 0.05);
    border-color: var(--primary-color);
    color: var(--primary-color);
    transform: translateX(5px);
}

.quick-action-btn i {
    width: 24px;
    color: var(--primary-color);
}

/* Filter Dropdown */
.filter-dropdown {
    border: 1px solid #e9ecef;
    border-radius: 10px;
    padding: 0.4rem 1rem;
    font-size: 0.9rem;
    color: #1e293b;
    background: white;
    cursor: pointer;
    transition: all 0.2s ease;
}

.filter-dropdown:hover {
    border-color: var(--primary-color);
}

/* View Toggle */
.view-toggle {
    display: flex;
    gap: 0.3rem;
    background: #f8fafc;
    padding: 0.3rem;
    border-radius: 10px;
}

.view-toggle-btn {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: transparent;
    color: #64748b;
    transition: all 0.2s ease;
    cursor: pointer;
}

.view-toggle-btn.active {
    background: white;
    color: var(--primary-color);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
}

.view-toggle-btn:hover {
    color: var(--primary-color);
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

/* Detail Labels */
.detail-label {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748b;
    display: block;
    margin-bottom: 0.2rem;
}

.detail-value {
    font-size: 0.95rem;
    color: #1e293b;
    font-weight: 500;
}

/* Progress Bars */
.progress {
    height: 8px;
    border-radius: 4px;
    background: #e9ecef;
    overflow: hidden;
}

.progress-bar {
    background: linear-gradient(90deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    border-radius: 4px;
    transition: width 0.3s ease;
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
@media (max-width: 768px) {
    .room-detail-header {
        padding: 1.5rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .breadcrumb-nav {
        padding: 0.8rem;
    }
    
    .modal-dialog {
        margin: 1rem;
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
            <li class="breadcrumb-item active" aria-current="page">
                <i class="fas fa-door-open me-1"></i>
                <?php echo htmlspecialchars($room['location_name']); ?>
            </li>
        </ol>
    </nav>
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

<!-- Room Header -->
<div class="room-detail-header">
    <div class="header-content">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <div class="d-flex align-items-center gap-4">
                    <div class="flex-shrink-0">
                        <i class="fas fa-door-open fa-4x opacity-75"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h1 class="display-5 fw-bold mb-2"><?php echo htmlspecialchars($room['location_name']); ?></h1>
                        <p class="mb-3 fs-5 opacity-90"><?php echo htmlspecialchars($room['description'] ?? 'No description available'); ?></p>
                        <div class="header-stats">
                            <span class="header-stat">
                                <i class="fas <?php echo $category_info['icon_class']; ?>"></i>
                                <?php echo htmlspecialchars($category_info['type_name']); ?>
                            </span>
                            <span class="header-stat">
                                <i class="fas fa-boxes"></i>
                                <?php echo $equipment_count; ?> Equipment
                            </span>
                            <span class="header-stat">
                                <i class="fas fa-users"></i>
                                Capacity: <?php echo $room['capacity'] ?? 'N/A'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 text-lg-end">
                <div class="d-flex gap-3 justify-content-lg-end flex-wrap">
                    <a href="inventory_rooms.php?category_id=<?php echo $category_id; ?>" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left me-2"></i>Back
                    </a>
                    <button class="btn btn-light" onclick="window.location.href='locations.php?id=<?php echo $room['id']; ?>'">
                        <i class="fas fa-edit me-2"></i>Edit Room
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Overview -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-boxes"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $equipment_count; ?></h3>
            <p>Total Equipment</p>
            <div class="stat-trend">
                <i class="fas fa-layer-group"></i> All items
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
                <i class="fas fa-arrow-up text-success"></i> Ready to use
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
                <i class="fas fa-user"></i> In use
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
                <i class="fas fa-wrench"></i> In repair
            </div>
        </div>
    </div>
</div>

<!-- Equipment Section -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-3">
                <h5 class="fw-bold mb-0">
                    <i class="fas fa-boxes me-2" style="color: var(--primary-color);"></i>
                    Equipment in this Room (<?php echo count($room_equipment); ?>)
                </h5>
                
                <div class="d-flex gap-3 align-items-center flex-wrap">
                    <!-- Filter Dropdown -->
                    <select class="filter-dropdown" id="equipmentFilter" onchange="filterEquipment(this.value)">
                        <option value="all">All Equipment</option>
                        <?php foreach ($equipment_tables as $table => $config): ?>
                        <option value="<?php echo $table; ?>"><?php echo $config['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <!-- View Toggle -->
                    <div class="view-toggle">
                        <button class="view-toggle-btn active" onclick="toggleView('grid')" title="Grid View">
                            <i class="fas fa-th"></i>
                        </button>
                        <button class="view-toggle-btn" onclick="toggleView('list')" title="List View">
                            <i class="fas fa-list"></i>
                        </button>
                    </div>
                    
                    <!-- Generate Report Button -->
                    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#generateReportModal">
                        <i class="fas fa-file-pdf me-1"></i>Generate Report
                    </button>
                </div>
            </div>
            
            <div class="card-body p-4">
                <?php if (!empty($room_equipment)): ?>
                
                <!-- Grid View -->
                <div id="gridView" class="row g-4">
                    <?php foreach ($room_equipment as $equipment): ?>
                    <?php 
                    $equipment_type = $equipment['equipment_type'] ?? 'unknown';
                    $type_info = $equipment_tables[$equipment_type] ?? [
                        'name' => 'Unknown',
                        'icon' => 'fa-box',
                        'color' => 'secondary',
                        'label_singular' => 'Item'
                    ];
                    
                    $item_name = ($equipment_type === 'computer_inventory') ? 
                                ($equipment['computer_set_description'] ?? 'Computer #' . $equipment['id']) : 
                                ($equipment['equipment_name'] ?? 'Equipment #' . $equipment['id']);
                    
                    $status = strtolower($equipment['status'] ?? 'unknown');
                    $status_class = $status === 'available' ? 'available' : 
                                   ($status === 'assigned' ? 'assigned' : 
                                   ($status === 'maintenance' ? 'maintenance' : 
                                   ($status === 'damaged' ? 'damaged' : 'condemned')));
                    
                    $assigned_to = $equipment['assigned_to_name'] ?? null;
                    ?>
                    <div class="col-xl-3 col-lg-4 col-md-6 equipment-item" data-type="<?php echo $equipment_type; ?>">
                        <div class="equipment-card" onclick="showEquipmentDetails(<?php echo $equipment['id']; ?>, '<?php echo $equipment_type; ?>')">
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <div class="equipment-icon bg-<?php echo $type_info['color']; ?> mx-auto">
                                        <i class="fas <?php echo $type_info['icon']; ?>"></i>
                                    </div>
                                </div>
                                
                                <h6 class="fw-bold text-center mb-2"><?php echo htmlspecialchars($item_name); ?></h6>
                                
                                <div class="d-flex justify-content-center gap-2 mb-3">
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($equipment['item_number'] ?? 'N/A'); ?></span>
                                    <span class="status-badge <?php echo $status_class; ?>"><?php echo ucfirst($status); ?></span>
                                </div>
                                
                                <?php if ($assigned_to): ?>
                                <div class="small text-center text-success">
                                    <i class="fas fa-user me-1"></i>
                                    <?php echo htmlspecialchars($assigned_to); ?>
                                </div>
                                <?php else: ?>
                                <div class="small text-center text-muted">
                                    <i class="fas fa-user-slash me-1"></i>
                                    Unassigned
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- List View -->
                <div id="listView" class="table-responsive" style="display: none;">
                    <table class="equipment-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Equipment</th>
                                <th>Item #</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Assigned To</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($room_equipment as $equipment): ?>
                            <?php 
                            $equipment_type = $equipment['equipment_type'] ?? 'unknown';
                            $type_info = $equipment_tables[$equipment_type] ?? [
                                'name' => 'Unknown',
                                'icon' => 'fa-box',
                                'color' => 'secondary',
                                'label_singular' => 'Item'
                            ];
                            
                            $item_name = ($equipment_type === 'computer_inventory') ? 
                                        ($equipment['computer_set_description'] ?? 'Computer #' . $equipment['id']) : 
                                        ($equipment['equipment_name'] ?? 'Equipment #' . $equipment['id']);
                            
                            $status = strtolower($equipment['status'] ?? 'unknown');
                            $status_class = $status === 'available' ? 'available' : 
                                           ($status === 'assigned' ? 'assigned' : 
                                           ($status === 'maintenance' ? 'maintenance' : 
                                           ($status === 'damaged' ? 'damaged' : 'condemned')));
                            
                            $description = '';
                            if ($equipment_type === 'computer_inventory') {
                                $specs = array_filter([
                                    $equipment['processor'] ?? '',
                                    $equipment['ram'] ?? '',
                                    $equipment['storage'] ?? ''
                                ]);
                                $description = implode(' • ', $specs);
                            } else {
                                $description = $equipment['remarks'] ?? 
                                              ($equipment['brand'] ? $equipment['brand'] . ' ' . ($equipment['model'] ?? '') : 'No description');
                            }
                            
                            $assigned_to = $equipment['assigned_to_name'] ?? null;
                            ?>
                            <tr class="equipment-row equipment-item" data-type="<?php echo $equipment_type; ?>" onclick="showEquipmentDetails(<?php echo $equipment['id']; ?>, '<?php echo $equipment_type; ?>')">
                                <td>
                                    <span class="badge bg-<?php echo $type_info['color']; ?>">
                                        <i class="fas <?php echo $type_info['icon']; ?> me-1"></i>
                                        <?php echo $type_info['label_singular']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <i class="fas <?php echo $type_info['icon']; ?> fa-lg" style="color: var(--primary-color);"></i>
                                        </div>
                                        <div>
                                            <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($item_name); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($equipment['serial_number'] ?? 'N/A'); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($equipment['item_number'] ?? 'N/A'); ?></span>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars(substr($description, 0, 40)) . (strlen($description) > 40 ? '...' : ''); ?></small>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($assigned_to): ?>
                                    <small class="text-success">
                                        <i class="fas fa-user me-1"></i>
                                        <?php echo htmlspecialchars($assigned_to); ?>
                                    </small>
                                    <?php else: ?>
                                    <small class="text-muted">
                                        <i class="fas fa-user-slash me-1"></i>
                                        Unassigned
                                    </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="action-btn view" onclick="event.stopPropagation(); showEquipmentDetails(<?php echo $equipment['id']; ?>, '<?php echo $equipment_type; ?>')" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="action-btn edit" onclick="event.stopPropagation(); showEditModal(<?php echo $equipment['id']; ?>, '<?php echo $equipment_type; ?>')" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php else: ?>
                <!-- Empty State -->
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-box-open"></i>
                    </div>
                    <h4 class="mb-2">No Equipment Found</h4>
                    <p class="text-muted mb-4">This room doesn't have any equipment yet.</p>
                    <button class="btn btn-primary px-4" data-bs-toggle="modal" data-bs-target="#addEquipmentModal">
                        <i class="fas fa-plus-circle me-2"></i>Add Equipment
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Side Cards Row -->
<div class="row mt-4">
    <!-- Manager Information -->
    <div class="col-md-4 mb-4">
        <div class="manager-card">
            <h5 class="fw-bold mb-4">
                <i class="fas fa-user-tie me-2" style="color: var(--primary-color);"></i>
                Room Manager
            </h5>
            
            <?php if ($room['manager_name']): ?>
            <div class="text-center">
                <div class="manager-avatar">
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
                <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($room['manager_name']); ?></h5>
                <p class="text-muted small mb-3"><?php echo htmlspecialchars($room['manager_email']); ?></p>
                
                <div class="d-grid gap-2">
                    <a href="mailto:<?php echo htmlspecialchars($room['manager_email']); ?>" class="btn btn-outline-primary">
                        <i class="fas fa-envelope me-2"></i>Send Email
                    </a>
                    <button class="btn btn-outline-secondary" onclick="window.location.href='locations.php?id=<?php echo $room['id']; ?>'">
                        <i class="fas fa-user-edit me-2"></i>Change Manager
                    </button>
                </div>
            </div>
            <?php else: ?>
            <div class="text-center py-4">
                <i class="fas fa-user-slash fa-4x text-muted mb-3"></i>
                <p class="text-muted mb-3">No manager assigned to this room</p>
                <button class="btn btn-primary" onclick="window.location.href='locations.php?id=<?php echo $room['id']; ?>'">
                    <i class="fas fa-plus me-2"></i>Assign Manager
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="col-md-4 mb-4">
        <div class="quick-actions-card">
            <h5 class="fw-bold mb-4">
                <i class="fas fa-bolt me-2" style="color: var(--primary-color);"></i>
                Quick Actions
            </h5>
            
            <a href="room_assignments.php?room_id=<?php echo $room['id']; ?>&category_id=<?php echo $category_id; ?>" class="quick-action-btn">
                <i class="fas fa-user-cog"></i>
                Manage Assignments
            </a>
            
            <a href="assignment_history.php?location_id=<?php echo $room['id']; ?>" class="quick-action-btn">
                <i class="fas fa-history"></i>
                View Assignment History
            </a>
            
            <button class="quick-action-btn" data-bs-toggle="modal" data-bs-target="#generateReportModal">
                <i class="fas fa-file-pdf"></i>
                Generate Room Report
            </button>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <div class="col-md-4 mb-4">
        <div class="activity-card">
            <h5 class="fw-bold mb-4">
                <i class="fas fa-clock me-2" style="color: var(--primary-color);"></i>
                Recent Activity
            </h5>
            
            <?php if (!empty($recent_activities)): ?>
            <div class="activity-list">
                <?php foreach ($recent_activities as $activity): ?>
                <?php 
                $activity_type = $activity['equipment_type'] ?? 'unknown';
                $activity_icon = $equipment_tables[$activity_type]['icon'] ?? 'fa-box';
                $activity_color = $activity['status'] === 'available' ? 'success' : 
                                 ($activity['status'] === 'assigned' ? 'warning' : 'info');
                
                $item_name = ($activity_type === 'computer_inventory') ? 
                            ($activity['computer_set_description'] ?? 'Computer') : 
                            ($activity['equipment_name'] ?? 'Equipment');
                ?>
                <div class="activity-item" onclick="showEquipmentDetails(<?php echo $activity['id']; ?>, '<?php echo $activity_type; ?>')">
                    <div class="activity-icon <?php echo $activity_color; ?>">
                        <i class="fas <?php echo $activity_icon; ?>"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($item_name); ?></h6>
                        <small class="text-muted">
                            <?php if ($activity['status'] === 'assigned' && $activity['assigned_to_name']): ?>
                                <i class="fas fa-user-check me-1"></i>
                                Assigned to <?php echo htmlspecialchars($activity['assigned_to_name']); ?>
                            <?php else: ?>
                                <i class="fas fa-<?php echo $activity['status'] === 'available' ? 'check-circle' : 'wrench'; ?> me-1"></i>
                                Status: <?php echo ucfirst($activity['status']); ?>
                            <?php endif; ?>
                        </small>
                    </div>
                    <small class="text-muted">
                        <?php echo date('M d, H:i', strtotime($activity['updated_at'] ?? $activity['created_at'] ?? 'now')); ?>
                    </small>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-3">
                <a href="assignment_history.php?location_id=<?php echo $room['id']; ?>" class="btn btn-sm btn-outline-primary">
                    View All Activity
                </a>
            </div>
            <?php else: ?>
            <div class="text-center py-4">
                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                <p class="text-muted">No recent activity</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Equipment Details Modal -->
<div class="modal fade" id="equipmentDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-info-circle me-2"></i>
                    Equipment Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="equipmentDetailsContent">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary mb-3" role="status"></div>
                    <p class="text-muted">Loading equipment details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Close
                </button>
                <button type="button" class="btn btn-primary" id="editEquipmentBtn">
                    <i class="fas fa-edit me-2"></i>Edit Equipment
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Equipment Edit Modal -->
<div class="modal fade" id="equipmentEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>
                    Edit Equipment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editEquipmentForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_equipment">
                    <input type="hidden" name="equipment_id" id="edit_equipment_id">
                    <input type="hidden" name="equipment_type" id="edit_equipment_type">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Item Number</label>
                            <input type="text" class="form-control" name="item_number" id="edit_item_number" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Serial Number</label>
                            <input type="text" class="form-control" name="serial_number" id="edit_serial_number">
                        </div>
                    </div>
                    
                    <!-- Computer Fields -->
                    <div id="computer_fields" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Computer Description</label>
                            <input type="text" class="form-control" name="computer_set_description" id="edit_computer_set_description">
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Device Type</label>
                                <select class="form-select" name="device_type" id="edit_device_type">
                                    <option value="Desktop">Desktop</option>
                                    <option value="Laptop">Laptop</option>
                                    <option value="All-in-One">All-in-One</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Operating System</label>
                                <input type="text" class="form-control" name="operating_system" id="edit_operating_system">
                            </div>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Processor</label>
                                <input type="text" class="form-control" name="processor" id="edit_processor">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">RAM</label>
                                <input type="text" class="form-control" name="ram" id="edit_ram">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Storage</label>
                                <input type="text" class="form-control" name="storage" id="edit_storage">
                            </div>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Keyboard</label>
                                <select class="form-select" name="keyboard_status" id="edit_keyboard_status">
                                    <option value="OK">OK</option>
                                    <option value="Missing">Missing</option>
                                    <option value="Damaged">Damaged</option>
                                    <option value="Needs Repair">Needs Repair</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Mouse</label>
                                <select class="form-select" name="mouse_status" id="edit_mouse_status">
                                    <option value="OK">OK</option>
                                    <option value="Missing">Missing</option>
                                    <option value="Damaged">Damaged</option>
                                    <option value="Needs Repair">Needs Repair</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Power Cord</label>
                                <select class="form-select" name="power_cord_status" id="edit_power_cord_status">
                                    <option value="OK">OK</option>
                                    <option value="Missing">Missing</option>
                                    <option value="Damaged">Damaged</option>
                                    <option value="Needs Repair">Needs Repair</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">HDMI</label>
                                <select class="form-select" name="hdmi_status" id="edit_hdmi_status">
                                    <option value="OK">OK</option>
                                    <option value="Missing">Missing</option>
                                    <option value="Damaged">Damaged</option>
                                    <option value="Needs Repair">Needs Repair</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Other Equipment Fields -->
                    <div id="other_equipment_fields" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Equipment Name</label>
                            <input type="text" class="form-control" name="equipment_name" id="edit_equipment_name">
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Brand</label>
                                <input type="text" class="form-control" name="brand" id="edit_brand">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Model</label>
                                <input type="text" class="form-control" name="model" id="edit_model">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Common Fields -->
                    <div class="row g-3 mt-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Status</label>
                            <select class="form-select" name="status" id="edit_status" required>
                                <option value="available">Available</option>
                                <option value="assigned">Assigned</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="damaged">Damaged</option>
                                <option value="condemned">Condemned</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Condition</label>
                            <select class="form-select" name="condition_status" id="edit_condition_status" required>
                                <option value="Excellent">Excellent</option>
                                <option value="Good">Good</option>
                                <option value="Fair">Fair</option>
                                <option value="Poor">Poor</option>
                                <option value="Damaged">Damaged</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3 mt-3">
                        <label class="form-label fw-bold">Remarks</label>
                        <textarea class="form-control" name="remarks" id="edit_remarks" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Equipment Modal -->
<div class="modal fade" id="addEquipmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle me-2"></i>
                    Add Equipment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-4">Select the type of equipment you want to add to this room:</p>
                
                <div class="list-group">
                    <a href="computers.php?location_id=<?php echo $room_id; ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3">
                        <div class="bg-primary bg-opacity-10 p-3 rounded-3">
                            <i class="fas fa-desktop fa-xl text-primary"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1">Computer Equipment</h6>
                            <small class="text-muted">Desktops, Laptops, All-in-One</small>
                        </div>
                    </a>
                    
                    <a href="general_equipment.php?location_id=<?php echo $room_id; ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3">
                        <div class="bg-secondary bg-opacity-10 p-3 rounded-3">
                            <i class="fas fa-box fa-xl text-secondary"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1">General Equipment</h6>
                            <small class="text-muted">Miscellaneous items and furniture</small>
                        </div>
                    </a>
                    
                    <a href="kitchen_equipment.php?location_id=<?php echo $room_id; ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3">
                        <div class="bg-warning bg-opacity-10 p-3 rounded-3">
                            <i class="fas fa-utensils fa-xl text-warning"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1">Kitchen Equipment</h6>
                            <small class="text-muted">Kitchen appliances and tools</small>
                        </div>
                    </a>
                    
                    <a href="lab_equipment.php?location_id=<?php echo $room_id; ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3">
                        <div class="bg-danger bg-opacity-10 p-3 rounded-3">
                            <i class="fas fa-flask fa-xl text-danger"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1">Laboratory Equipment</h6>
                            <small class="text-muted">Lab instruments and apparatus</small>
                        </div>
                    </a>
                    
                    <a href="office_equipment.php?location_id=<?php echo $room_id; ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3">
                        <div class="bg-info bg-opacity-10 p-3 rounded-3">
                            <i class="fas fa-briefcase fa-xl text-info"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1">Office Equipment</h6>
                            <small class="text-muted">Office supplies and furniture</small>
                        </div>
                    </a>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Generate Report Modal -->
<div class="modal fade" id="generateReportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-file-pdf me-2"></i>
                    Generate Room Report
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="generate_room_report.php" method="POST" target="_blank">
                <div class="modal-body">
                    <input type="hidden" name="room_id" value="<?php echo $room_id; ?>">
                    <input type="hidden" name="category_id" value="<?php echo $category_id; ?>">
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">Report Type</label>
                        <select class="form-select" name="report_type">
                            <option value="detailed">Detailed Inventory Report</option>
                            <option value="summary">Summary Report</option>
                            <option value="status">Equipment Status Report</option>
                            <option value="assignments">Assignment History</option>
                        </select>
                    </div>
                    
                    <div class="bg-light p-3 rounded-3">
                        <div class="d-flex align-items-center gap-3">
                            <i class="fas fa-info-circle fa-2x text-primary opacity-50"></i>
                            <div>
                                <p class="mb-0 small">
                                    <strong>Room:</strong> <?php echo htmlspecialchars($room['location_name']); ?><br>
                                    <strong>Category:</strong> <?php echo htmlspecialchars($category_info['type_name']); ?><br>
                                    <strong>Total Equipment:</strong> <?php echo $equipment_count; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-download me-2"></i>Generate PDF
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let currentEquipment = null;

// Initialize view preference
document.addEventListener('DOMContentLoaded', function() {
    const savedView = localStorage.getItem('roomViewPreference');
    if (savedView) {
        toggleView(savedView);
    }
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
});

// Toggle view function
function toggleView(viewType) {
    const gridView = document.getElementById('gridView');
    const listView = document.getElementById('listView');
    const gridBtn = document.querySelectorAll('.view-toggle-btn')[0];
    const listBtn = document.querySelectorAll('.view-toggle-btn')[1];
    
    if (viewType === 'grid') {
        gridView.style.display = 'block';
        listView.style.display = 'none';
        gridBtn.classList.add('active');
        listBtn.classList.remove('active');
        localStorage.setItem('roomViewPreference', 'grid');
    } else {
        gridView.style.display = 'none';
        listView.style.display = 'block';
        listBtn.classList.add('active');
        gridBtn.classList.remove('active');
        localStorage.setItem('roomViewPreference', 'list');
    }
}

// Filter equipment function
function filterEquipment(type) {
    const items = document.querySelectorAll('.equipment-item');
    items.forEach(item => {
        if (type === 'all' || item.dataset.type === type) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
}

// Show equipment details
function showEquipmentDetails(equipmentId, equipmentType) {
    const modal = new bootstrap.Modal(document.getElementById('equipmentDetailsModal'));
    const modalContent = document.getElementById('equipmentDetailsContent');
    const editBtn = document.getElementById('editEquipmentBtn');
    
    modalContent.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary mb-3" role="status"></div>
            <p class="text-muted">Loading equipment details...</p>
        </div>
    `;
    
    modal.show();
    
    const equipmentData = <?php echo json_encode($room_equipment ?? []); ?>;
    const equipment = equipmentData.find(item => item.id == equipmentId && item.equipment_type == equipmentType);
    
    if (equipment) {
        currentEquipment = equipment;
        
        const typeInfo = <?php echo json_encode($equipment_tables); ?>;
        const info = typeInfo[equipmentType] || { name: 'Unknown', icon: 'fa-box', color: 'secondary', label_singular: 'Item' };
        
        let itemName = (equipmentType === 'computer_inventory') ? 
                      (equipment.computer_set_description || 'Computer #' + equipment.id) : 
                      (equipment.equipment_name || 'Equipment #' + equipment.id);
        
        let specs = '';
        if (equipmentType === 'computer_inventory') {
            const specList = [];
            if (equipment.processor) specList.push(`<span class="badge bg-light text-dark me-1">CPU: ${equipment.processor}</span>`);
            if (equipment.ram) specList.push(`<span class="badge bg-light text-dark me-1">RAM: ${equipment.ram}</span>`);
            if (equipment.storage) specList.push(`<span class="badge bg-light text-dark">Storage: ${equipment.storage}</span>`);
            specs = specList.join(' ');
        }
        
        let dualSerials = '';
        if (equipmentType === 'computer_inventory' && equipment.serial_number_monitor && equipment.serial_number_system) {
            dualSerials = `
                <div class="mt-3 p-3 bg-light rounded-3">
                    <small class="text-muted d-block mb-2">Package Serial Numbers:</small>
                    <div class="d-flex gap-3">
                        <div><span class="badge bg-primary">Monitor</span> <code>${equipment.serial_number_monitor}</code></div>
                        <div><span class="badge bg-info">System</span> <code>${equipment.serial_number_system}</code></div>
                    </div>
                </div>
            `;
        }
        
        let html = `
            <div class="row g-0">
                <div class="col-md-4 p-4 text-center bg-light rounded-start">
                    <div class="equipment-icon bg-${info.color} mx-auto mb-3">
                        <i class="fas ${info.icon}"></i>
                    </div>
                    <h5 class="fw-bold mb-1">${itemName}</h5>
                    <span class="badge bg-secondary mb-3">${equipment.item_number || 'N/A'}</span>
                    <div class="d-grid gap-2">
                        <span class="badge bg-${info.color} py-2">${info.label_singular}</span>
                        <span class="status-badge ${equipment.status || 'available'} py-2">${(equipment.status || 'unknown').toUpperCase()}</span>
                    </div>
                </div>
                <div class="col-md-8 p-4">
                    <h6 class="fw-bold mb-3">Specifications</h6>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <small class="text-muted d-block">Condition</small>
                            <span class="fw-bold">${equipment.condition_status || 'N/A'}</span>
                        </div>
                        <div class="col-sm-6">
                            <small class="text-muted d-block">Property Number</small>
                            <span class="fw-bold">${equipment.property_no || 'N/A'}</span>
                        </div>
                        <div class="col-12">
                            <small class="text-muted d-block">Serial Number</small>
                            <code class="fw-bold">${equipment.serial_number || 'N/A'}</code>
                        </div>
                        ${specs ? `<div class="col-12 mt-2">${specs}</div>` : ''}
                        <div class="col-12">
                            <small class="text-muted d-block">Assigned To</small>
                            ${equipment.assigned_to_name ? 
                                `<span class="text-success"><i class="fas fa-user me-1"></i>${equipment.assigned_to_name}</span>` : 
                                `<span class="text-muted"><i class="fas fa-user-slash me-1"></i>Unassigned</span>`}
                        </div>
                    </div>
                    
                    ${dualSerials}
                    
                    ${equipment.remarks ? `
                        <div class="mt-3 p-3 bg-light rounded-3">
                            <small class="text-muted d-block mb-1">Remarks</small>
                            <p class="mb-0">${equipment.remarks}</p>
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
        
        modalContent.innerHTML = html;
        
        editBtn.onclick = function() {
            showEditModal(equipmentId, equipmentType);
        };
    } else {
        modalContent.innerHTML = `
            <div class="alert alert-danger m-3">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Equipment details could not be loaded.
            </div>
        `;
    }
}

// Show edit modal
function showEditModal(equipmentId, equipmentType) {
    if (!currentEquipment) {
        alert('No equipment selected');
        return;
    }
    
    const detailsModal = bootstrap.Modal.getInstance(document.getElementById('equipmentDetailsModal'));
    if (detailsModal) detailsModal.hide();
    
    const editModal = new bootstrap.Modal(document.getElementById('equipmentEditModal'));
    
    document.getElementById('edit_equipment_id').value = currentEquipment.id;
    document.getElementById('edit_equipment_type').value = currentEquipment.equipment_type;
    
    const computerFields = document.getElementById('computer_fields');
    const otherFields = document.getElementById('other_equipment_fields');
    
    if (currentEquipment.equipment_type === 'computer_inventory') {
        computerFields.style.display = 'block';
        otherFields.style.display = 'none';
        
        document.getElementById('edit_computer_set_description').value = currentEquipment.computer_set_description || '';
        document.getElementById('edit_processor').value = currentEquipment.processor || '';
        document.getElementById('edit_ram').value = currentEquipment.ram || '';
        document.getElementById('edit_storage').value = currentEquipment.storage || '';
        document.getElementById('edit_device_type').value = currentEquipment.device_type || 'Desktop';
        document.getElementById('edit_operating_system').value = currentEquipment.operating_system || '';
        document.getElementById('edit_keyboard_status').value = currentEquipment.keyboard_status || 'OK';
        document.getElementById('edit_mouse_status').value = currentEquipment.mouse_status || 'OK';
        document.getElementById('edit_power_cord_status').value = currentEquipment.power_cord_status || 'OK';
        document.getElementById('edit_hdmi_status').value = currentEquipment.hdmi_status || 'OK';
    } else {
        computerFields.style.display = 'none';
        otherFields.style.display = 'block';
        
        document.getElementById('edit_equipment_name').value = currentEquipment.equipment_name || '';
        document.getElementById('edit_brand').value = currentEquipment.brand || '';
        document.getElementById('edit_model').value = currentEquipment.model || '';
    }
    
    document.getElementById('edit_item_number').value = currentEquipment.item_number || '';
    document.getElementById('edit_serial_number').value = currentEquipment.serial_number || '';
    document.getElementById('edit_status').value = currentEquipment.status || 'available';
    document.getElementById('edit_condition_status').value = currentEquipment.condition_status || 'Good';
    document.getElementById('edit_remarks').value = currentEquipment.remarks || '';
    
    editModal.show();
}

// Initialize filter dropdown
document.addEventListener('DOMContentLoaded', function() {
    const filter = document.getElementById('equipmentFilter');
    if (filter) {
        filter.addEventListener('change', function() {
            filterEquipment(this.value);
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>