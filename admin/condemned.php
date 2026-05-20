<?php
date_default_timezone_set('Asia/Manila');
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Handle form submissions
if ($_POST && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'deploy':
            $id = $_POST['id'];
            $type = $_POST['equipment_type'];
            
            $table_map = [
                'computer' => 'computer_inventory', 
                'computer_lab' => 'computer_inventory',
                'kitchen' => 'kitchen_equipment', 
                'office' => 'office_equipment',
                'lab' => 'lab_equipment', 
                'regular_lab' => 'lab_equipment',
                'general' => 'general_equipment'
            ];
            
            if (isset($table_map[$type])) {
                $target_table = $table_map[$type];
                
                if ($type === 'condemned') {
                    $get_stmt = $db->prepare("SELECT * FROM condemned_equipment WHERE id = ?");
                    $get_stmt->execute([$id]);
                    $item = $get_stmt->fetch(PDO::FETCH_ASSOC);

                    if ($item) {
                        $actual_type = $item['equipment_type'];
                        $real_target = $table_map[$actual_type];
                        
                        $name_col = ($real_target === 'computer_inventory') ? 'computer_set_description' : (($real_target === 'general_equipment') ? 'article' : 'equipment_name');
                        $sn_col = ($real_target === 'general_equipment') ? 'property_no' : 'serial_number';

                        $restore_query = "INSERT INTO {$real_target} ({$name_col}, {$sn_col}, status, condition_status, is_condemned, updated_at) 
                                        VALUES (?, ?, 'available', 'Good', 0, NOW())";
                        $restore_stmt = $db->prepare($restore_query);
                        $restore_stmt->execute([$item['model'], $item['serial_number']]);

                        $db->prepare("DELETE FROM condemned_equipment WHERE id = ?")->execute([$id]);
                        $_SESSION['success_message'] = "Item successfully restored to active inventory!";
                    }
                } else {
                    $update_query = "UPDATE {$target_table} SET is_condemned = 0, status = 'available', condition_status = 'Good', updated_at = NOW() WHERE id = ?";
                    $update_stmt = $db->prepare($update_query);
                    $result = $update_stmt->execute([$id]);

                    if ($result) {
                        $_SESSION['success_message'] = "Equipment deployed! Status reset to Available.";
                    } else {
                        $_SESSION['error_message'] = "Failed to update equipment status.";
                    }
                }
            }
            header("Location: condemned.php");
            exit();
            break;
            
        case 'add':
            $query = "INSERT INTO condemned_equipment (
                model, category, serial_number, equipment_type, reason_condemned, 
                disposal_status, condemned_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([
                $_POST['model'], $_POST['category'], $_POST['serial_number'], 
                $_POST['equipment_type'], $_POST['reason_condemned'], 
                $_POST['disposal_status'], $_SESSION['user_id']
            ]);
            $_SESSION['success_message'] = "Condemned equipment added successfully!";
            header("Location: condemned.php");
            exit();
            break;
            
        case 'edit':
            $query = "UPDATE condemned_equipment SET 
                model = ?, category = ?, serial_number = ?, equipment_type = ?, 
                reason_condemned = ?, disposal_status = ?, 
                disposal_date = ?, disposal_notes = ?
                WHERE id = ?";
            $disposal_date = $_POST['disposal_status'] != 'pending' && !empty($_POST['disposal_date']) ? $_POST['disposal_date'] : null;
            $stmt = $db->prepare($query);
            $stmt->execute([
                $_POST['model'], $_POST['category'], $_POST['serial_number'], 
                $_POST['equipment_type'], $_POST['reason_condemned'], 
                $_POST['disposal_status'], 
                $disposal_date, $_POST['disposal_notes'], $_POST['id']
            ]);
            $_SESSION['success_message'] = "Condemned equipment updated successfully!";
            header("Location: condemned.php");
            exit();
            break;
            
        case 'delete':
            $id = $_POST['id'];
            $type = $_POST['equipment_type']; 

            if ($type === 'condemned' || empty($type)) {
                $query = "DELETE FROM condemned_equipment WHERE id = ?";
            } else {
                $table_map = [
                    'computer' => 'computer_inventory', 'computer_lab' => 'computer_inventory',
                    'kitchen' => 'kitchen_equipment', 'office' => 'office_equipment',
                    'lab' => 'lab_equipment', 'regular_lab' => 'lab_equipment',
                    'general' => 'general_equipment'
                ];
                $target_table = $table_map[$type] ?? 'condemned_equipment';
                $query = "DELETE FROM `$target_table` WHERE id = ?";
            }
            $stmt = $db->prepare($query);
            $stmt->execute([$id]);
            $_SESSION['success_message'] = "Record deleted successfully!";
            header("Location: condemned.php");
            exit();
            break;

        case 'transfer':
            $id = $_POST['id'];
            $type = $_POST['equipment_type'];
            
            $table_map = [
                'computer' => 'computer_inventory', 'computer_lab' => 'computer_inventory',
                'kitchen' => 'kitchen_equipment', 'office' => 'office_equipment',
                'lab' => 'lab_equipment', 'regular_lab' => 'lab_equipment',
                'general' => 'general_equipment'
            ];
            
            if (isset($table_map[$type])) {
                $source_table = $table_map[$type];
                
                $name_col = ($source_table === 'computer_inventory') ? 'computer_set_description' : (($source_table === 'general_equipment') ? 'article' : 'equipment_name');
                $sn_col = ($source_table === 'general_equipment') ? 'property_no' : 'serial_number';

                $get_stmt = $db->prepare("SELECT {$name_col} as model, {$sn_col} as sn, condemned_reason, condemned_date, condemned_by, remarks as accountable_person FROM {$source_table} WHERE id = ?");
                $get_stmt->execute([$id]);
                $item = $get_stmt->fetch(PDO::FETCH_ASSOC);

                if ($item) {
                    $real_time = date('Y-m-d H:i:s');

                    $ins_query = "INSERT INTO condemned_equipment (model, category, serial_number, equipment_type, reason_condemned, condemned_date, condemned_by, disposal_status, remarks) 
                                VALUES (?, ?, ?, 'condemned', ?, ?, ?, 'Complete Condemned', ?)";
                    $ins_stmt = $db->prepare($ins_query);
                    $ins_stmt->execute([
                        $item['model'], 
                        ucfirst($type), 
                        $item['sn'], 
                        $item['condemned_reason'], 
                        $real_time, 
                        $item['condemned_by'],
                        $item['accountable_person']
                    ]);

                    $del_stmt = $db->prepare("DELETE FROM {$source_table} WHERE id = ?");
                    $del_stmt->execute([$id]);
                    $_SESSION['success_message'] = "Equipment successfully finalized at " . date('h:i A');
                }
            }
            header("Location: condemned.php");
            exit();
            break;
            
        case 'archive':
            $id = $_POST['id'];
            $archive_reason = $_POST['archive_reason'] ?? 'Item archived from condemned list';
            
            try {
                // Start transaction
                $db->beginTransaction();
                
                // Get the condemned equipment details
                $get_stmt = $db->prepare("SELECT * FROM condemned_equipment WHERE id = ?");
                $get_stmt->execute([$id]);
                $item = $get_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$item) {
                    throw new Exception("Condemned equipment not found");
                }
                
                // Insert into archive_items table
                $archive_query = "INSERT INTO archive_items (
                    original_id, model, category, serial_number, equipment_type, 
                    reason_condemned, condemned_date, condemned_by, disposal_status,
                    archived_by, archive_reason, estimated_value, remarks
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $archive_stmt = $db->prepare($archive_query);
                $archive_stmt->execute([
                    $item['id'],
                    $item['model'],
                    $item['category'],
                    $item['serial_number'],
                    $item['equipment_type'],
                    $item['reason_condemned'],
                    $item['condemned_date'],
                    $item['condemned_by'],
                    'archived',
                    $_SESSION['user_id'],
                    $archive_reason,
                    $item['estimated_value'],
                    $item['remarks']
                ]);
                
                // Delete from condemned_equipment table
                $delete_stmt = $db->prepare("DELETE FROM condemned_equipment WHERE id = ?");
                $delete_stmt->execute([$id]);
                
                // Commit transaction
                $db->commit();
                
                $_SESSION['success_message'] = "Item successfully archived!";
                
            } catch (Exception $e) {
                $db->rollBack();
                $_SESSION['error_message'] = "Error archiving item: " . $e->getMessage();
            }
            
            header("Location: condemned.php");
            exit();
            break;
    }
}

// Get filter parameters
$category_filter = $_GET['category'] ?? '';
$disposal_filter = $_GET['disposal'] ?? '';
$equipment_type_filter = $_GET['type'] ?? '';
$search = $_GET['search'] ?? '';

// Build query with filters
$where_conditions = [];
$params = [];

if (!empty($category_filter)) {
    $where_conditions[] = "category = ?";
    $params[] = $category_filter;
}

if (!empty($disposal_filter)) {
    $where_conditions[] = "disposal_status = ?";
    $params[] = $disposal_filter;
}

if (!empty($equipment_type_filter)) {
    $where_conditions[] = "equipment_type = ?";
    $params[] = $equipment_type_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(model LIKE ? OR serial_number LIKE ? OR reason_condemned LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Check which equipment tables have condemned columns
$tables_with_condemned = [];
$equipment_tables = [
    'computer_inventory' => ['Computer', 'computer', 'item_number'],
    'kitchen_equipment' => ['Kitchen Equipment', 'kitchen', 'equipment_name'], 
    'office_equipment' => ['Office Equipment', 'office', 'equipment_name'],
    'lab_equipment' => ['Lab Equipment', 'lab', 'equipment_name'],
    'general_equipment' => ['General Equipment', 'general', 'article']
];

foreach ($equipment_tables as $table => $info) {
    try {
        $check_query = "SHOW COLUMNS FROM `{$table}` LIKE 'is_condemned'";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute();
        if ($check_stmt->rowCount() > 0) {
            $tables_with_condemned[$table] = $info;
        }
    } catch (Exception $e) {
        continue;
    }
}

// Build the main query
$query_parts = [];

// Main condemned_equipment table
$main_where = $where_clause;
$query_parts[] = "SELECT 
              ce.id,
              ce.model,
              ce.category,
              ce.serial_number,
              ce.equipment_type,
              ce.reason_condemned,
              ce.condemned_date,
              ce.disposal_status,
              ce.disposal_date,
              ce.disposal_notes,
              ce.estimated_value,
              ce.created_at,
              ce.updated_at,
              u.full_name as condemned_by_name,
              ce.remarks as accountable_person,
              'condemned' as source_type
          FROM condemned_equipment ce 
          LEFT JOIN users u ON ce.condemned_by = u.id 
          {$main_where}";

// Add inventory tables
foreach ($tables_with_condemned as $table => $info) {
    list($category, $equipment_type, $name_column) = $info;
    
    $sn_column = ($table === 'general_equipment') ? 'property_no' : 'serial_number';

    $query_parts[] = "SELECT 
        {$table}.id,
        {$table}.{$name_column} as model,
        '{$category}' as category,
        {$table}.{$sn_column} as serial_number,
        '{$equipment_type}' as equipment_type,
        {$table}.condemned_reason as reason_condemned,
        {$table}.condemned_date,
        'pending' as disposal_status,
        NULL as disposal_date,
        NULL as disposal_notes,
        0.00 as estimated_value,
        {$table}.created_at,
        {$table}.updated_at,
        {$table}.remarks as accountable_person,
        u_{$table}.full_name as condemned_by_name,
        'inventory' as source_type
    FROM {$table}
    LEFT JOIN users u_{$table} ON {$table}.condemned_by = u_{$table}.id
    WHERE {$table}.is_condemned = TRUE";
}

// Combine all query parts
if (count($query_parts) > 1) {
    $query = implode(" UNION ALL ", $query_parts) . " ORDER BY condemned_date DESC";
} else {
    $query = $query_parts[0] . " ORDER BY condemned_date DESC";
}
$stmt = $db->prepare($query);
$stmt->execute($params);
$condemned_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query_parts = [
    "(SELECT COUNT(*) FROM condemned_equipment)"
];

$pending_query_parts = [
    "(SELECT COUNT(*) FROM condemned_equipment WHERE disposal_status = 'pending')"
];

$system_units_query_parts = [
    "(SELECT COUNT(*) FROM condemned_equipment WHERE category = 'System Unit')"
];

$keyboards_query_parts = [
    "(SELECT COUNT(*) FROM condemned_equipment WHERE category = 'Keyboard')"
];

// Add counts from equipment tables
foreach ($tables_with_condemned as $table => $info) {
    $stats_query_parts[] = "(SELECT COUNT(*) FROM {$table} WHERE is_condemned = TRUE)";
    $pending_query_parts[] = "(SELECT COUNT(*) FROM {$table} WHERE is_condemned = TRUE)";
    
    if ($info[1] === 'computer') {
        $system_units_query_parts[] = "(SELECT COUNT(*) FROM {$table} WHERE is_condemned = TRUE)";
    } else {
        $keyboards_query_parts[] = "(SELECT COUNT(*) FROM {$table} WHERE is_condemned = TRUE)";
    }
}

$query = "SELECT 
    (" . implode(" + ", $stats_query_parts) . ") as total_condemned,
    (" . implode(" + ", $pending_query_parts) . ") as pending_disposal,
    (SELECT COUNT(*) FROM condemned_equipment WHERE disposal_status = 'disposed') as disposed,
    (SELECT COUNT(*) FROM condemned_equipment WHERE disposal_status = 'recycled') as recycled,
    (SELECT COUNT(*) FROM condemned_equipment WHERE disposal_status = 'repaired') as repaired,
    (SELECT COUNT(*) FROM condemned_equipment WHERE disposal_status = 'donated') as donated,
    (" . implode(" + ", $system_units_query_parts) . ") as system_units,
    (SELECT COUNT(*) FROM condemned_equipment WHERE category = 'Monitor') as monitors,
    (SELECT COUNT(*) FROM condemned_equipment WHERE category = 'All in one') as all_in_ones,
    (" . implode(" + ", $keyboards_query_parts) . ") as keyboards,
    (SELECT COUNT(*) FROM condemned_equipment WHERE category = 'AVR') as avrs,
    (SELECT COUNT(*) FROM condemned_equipment WHERE category = 'Other') as others,
    (SELECT SUM(estimated_value) FROM condemned_equipment) as total_estimated_value";
$stmt = $db->prepare($query);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$stats || $stats['total_condemned'] == 0) {
    $stats = [
        'total_condemned' => 0,
        'pending_disposal' => 0,
        'disposed' => 0,
        'recycled' => 0,
        'repaired' => 0,
        'donated' => 0,
        'system_units' => 0,
        'monitors' => 0,
        'all_in_ones' => 0,
        'keyboards' => 0,
        'avrs' => 0,
        'others' => 0,
        'total_estimated_value' => 0
    ];
}

// Get success/error messages from session
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
$warning_message = $_SESSION['warning'] ?? null;
$import_success = $_SESSION['import_success'] ?? null;
$import_errors = $_SESSION['import_errors'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message'], $_SESSION['warning'], $_SESSION['import_success'], $_SESSION['import_errors']);

$page_title = "Condemned Equipment Management";
include '../includes/header.php';
?>

<style>
:root {
    --condemned-red: #DC2626;
    --condemned-red-dark: #B91C1C;
    --condemned-red-light: #FEE2E2;
    --condemned-orange: #F97316;
    --condemned-orange-light: #FFF3E0;
    --condemned-green: #10B981;
    --condemned-green-light: #D1FAE5;
    --condemned-blue: #3B82F6;
    --condemned-blue-light: #DBEAFE;
    --condemned-purple: #8B5CF6;
    --condemned-purple-light: #EDE9FE;
    --condemned-gray: #6B7280;
    --condemned-gray-light: #F3F4F6;
}

/* Page Header */
.page-header {
    background: linear-gradient(135deg, var(--condemned-red) 0%, var(--condemned-red-dark) 100%);
    border-radius: 24px;
    padding: 2rem 2.5rem;
    margin-bottom: 2rem;
    color: white;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(220, 38, 38, 0.15);
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
    color: var(--condemned-red);
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
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
    border: 1px solid #FEE2E2;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(220, 38, 38, 0.15);
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

.stat-icon.red { background: linear-gradient(135deg, var(--condemned-red) 0%, var(--condemned-red-dark) 100%); }
.stat-icon.orange { background: linear-gradient(135deg, #F97316 0%, #EA580C 100%); }
.stat-icon.green { background: linear-gradient(135deg, #10B981 0%, #059669 100%); }
.stat-icon.blue { background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%); }
.stat-icon.purple { background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%); }
.stat-icon.gray { background: linear-gradient(135deg, #6B7280 0%, #4B5563 100%); }

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

/* Filter Section */
.filter-section {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
    border: 1px solid #FEE2E2;
}

.filter-title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--condemned-red-dark);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-title i {
    color: var(--condemned-red);
}

/* Table Styling */
.table-container {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
    border: 1px solid #FEE2E2;
    margin-bottom: 2rem;
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.table-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--condemned-red-dark);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.table-title i {
    color: var(--condemned-red);
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table thead th {
    background: #FEF2F2;
    color: var(--condemned-red-dark);
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 1rem;
    border-bottom: 2px solid var(--condemned-red);
}

.table tbody td {
    padding: 1rem;
    border-bottom: 1px solid #FEE2E2;
    vertical-align: middle;
}

.table tbody tr {
    transition: all 0.3s ease;
}

.table tbody tr:hover {
    background: #FEF2F2;
}

/* Equipment Info */
.equipment-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.equipment-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: white;
}

.equipment-icon.system-unit { background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%); }
.equipment-icon.monitor { background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%); }
.equipment-icon.keyboard { background: linear-gradient(135deg, #10B981 0%, #059669 100%); }
.equipment-icon.avr { background: linear-gradient(135deg, #F97316 0%, #EA580C 100%); }
.equipment-icon.other { background: linear-gradient(135deg, #6B7280 0%, #4B5563 100%); }

.equipment-details h6 {
    font-weight: 700;
    color: #1F2937;
    margin: 0 0 0.2rem 0;
}

.equipment-details small {
    color: #6B7280;
    font-size: 0.7rem;
}

/* Badges */
.category-badge {
    padding: 0.3rem 0.8rem;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.category-badge.system-unit { background: #FEE2E2; color: #DC2626; }
.category-badge.monitor { background: #DBEAFE; color: #3B82F6; }
.category-badge.keyboard { background: #D1FAE5; color: #10B981; }
.category-badge.avr { background: #FFF3E0; color: #F97316; }
.category-badge.other { background: #F3F4F6; color: #6B7280; }

.status-badge {
    padding: 0.3rem 0.8rem;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.status-badge.pending { background: #FEE2E2; color: #DC2626; }
.status-badge.disposed { background: #E5E7EB; color: #4B5563; }
.status-badge.recycled { background: #D1FAE5; color: #10B981; }
.status-badge.repaired { background: #DBEAFE; color: #3B82F6; }
.status-badge.donated { background: #FEF3C7; color: #D97706; }
.status-badge.complete { background: #FEE2E2; color: #DC2626; font-weight: 700; }

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 0.3rem;
    justify-content: center;
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

.action-btn.view { background: #DBEAFE; color: #3B82F6; }
.action-btn.deploy { background: #D1FAE5; color: #10B981; }
.action-btn.transfer { background: #FEF3C7; color: #D97706; }
.action-btn.archive { background: #E5E7EB; color: #6B7280; }

.action-btn:hover {
    transform: translateY(-2px);
    filter: brightness(0.9);
}

.action-btn.view:hover { background: #3B82F6; color: white; }
.action-btn.deploy:hover { background: #10B981; color: white; }
.action-btn.transfer:hover { background: #D97706; color: white; }
.action-btn.archive:hover { background: #6B7280; color: white; }

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

.modal-header.bg-danger { background: linear-gradient(135deg, var(--condemned-red) 0%, var(--condemned-red-dark) 100%) !important; }
.modal-header.bg-info { background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%) !important; }
.modal-header.bg-success { background: linear-gradient(135deg, #10B981 0%, #059669 100%) !important; }

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
    border-top: 1px solid #FEE2E2;
}

.form-label {
    font-weight: 600;
    color: var(--condemned-red-dark);
    font-size: 0.9rem;
    margin-bottom: 0.3rem;
}

.form-control, .form-select {
    border-radius: 12px;
    border: 2px solid #FEE2E2;
    padding: 0.6rem 1rem;
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    border-color: var(--condemned-red);
    box-shadow: 0 0 0 0.25rem rgba(220, 38, 38, 0.15);
}

textarea.form-control {
    min-height: 80px;
}

/* Detail View */
.detail-section {
    background: #F9FAFB;
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 1rem;
}

.detail-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    color: #6B7280;
    font-weight: 600;
    letter-spacing: 0.5px;
    margin-bottom: 0.2rem;
}

.detail-value {
    font-size: 1rem;
    font-weight: 600;
    color: #1F2937;
}

.reason-box {
    background: #FEE2E2;
    border-left: 4px solid var(--condemned-red);
    padding: 1rem;
    border-radius: 8px;
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
    background: #D1FAE5;
    color: #065F46;
    border-left: 4px solid #10B981;
}

.alert-danger {
    background: #FEE2E2;
    color: #991B1B;
    border-left: 4px solid #DC2626;
}

.alert-warning {
    background: #FEF3C7;
    color: #92400E;
    border-left: 4px solid #F59E0B;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: 30px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
}

.empty-state-icon {
    width: 120px;
    height: 120px;
    border-radius: 60px;
    background: #FEE2E2;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 2rem;
    font-size: 3rem;
    color: var(--condemned-red);
}

.empty-state h3 {
    font-size: 1.8rem;
    font-weight: 700;
    color: #1F2937;
    margin-bottom: 1rem;
}

.empty-state p {
    color: #6B7280;
    margin-bottom: 2rem;
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

.stat-card, .table-container {
    animation: slideIn 0.5s ease-out forwards;
}
</style>

<!-- Page Header -->
<div class="page-header">
    <div class="header-content">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <div class="header-title">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Condemned Equipment</span>
                </div>
                <p class="header-subtitle">
                    Manage and track equipment that has been condemned. Monitor disposal status, finalize condemnations, and generate reports.
                </p>
                <div class="header-stats">
                    <div class="header-stat-item">
                        <span class="header-stat-number"><?php echo $stats['total_condemned']; ?></span>
                        <span class="header-stat-label">Total Condemned</span>
                    </div>
                    <div class="header-stat-item">
                        <span class="header-stat-number"><?php echo $stats['pending_disposal']; ?></span>
                        <span class="header-stat-label">Pending</span>
                    </div>
                    <div class="header-stat-item">
                        <span class="header-stat-number"><?php echo $stats['disposed']; ?></span>
                        <span class="header-stat-label">Disposed</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="header-actions">
                    <a href="generate_condemned_report.php" target="_blank" class="btn">
                        <i class="fas fa-file-pdf"></i> Report
                    </a>
                    <button type="button" class="btn" id="downloadPRSBtn" disabled>
                        <i class="fas fa-file-excel"></i> PRS Form
                    </button>
                    <button type="button" class="btn" data-bs-toggle="modal" data-bs-target="#addModal">
                        <i class="fas fa-plus-circle"></i> Add Item
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Alert Messages -->
<?php if ($success_message): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i>
    <?php echo htmlspecialchars($success_message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error_message): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i>
    <?php echo htmlspecialchars($error_message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($warning_message): ?>
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <?php echo htmlspecialchars($warning_message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($import_success): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-file-import me-2"></i>
    <?php echo htmlspecialchars($import_success); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($import_errors): ?>
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <strong>Import completed with some errors:</strong>
    <ul class="mb-0 mt-2">
        <?php foreach ($import_errors as $error): ?>
        <li><?php echo htmlspecialchars($error); ?></li>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon red">
            <i class="fas fa-times-circle"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['total_condemned']; ?></h3>
            <p>Total Condemned</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon red">
            <i class="fas fa-hourglass-half"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['pending_disposal']; ?></h3>
            <p>Pending Disposal</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fas fa-tv"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['system_units'] + $stats['monitors'] + $stats['all_in_ones']; ?></h3>
            <p>System Units & Monitors</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-keyboard"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['keyboards'] + $stats['avrs']; ?></h3>
            <p>Keyboards & AVRs</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="fas fa-recycle"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['recycled']; ?></h3>
            <p>Recycled</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon gray">
            <i class="fas fa-trash-alt"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['disposed']; ?></h3>
            <p>Disposed</p>
        </div>
    </div>
</div>

<!-- Filter Section -->
<div class="filter-section">
    <div class="filter-title">
        <i class="fas fa-filter"></i>
        Filter Equipment
    </div>
    <form method="GET" class="row g-3">
        <div class="col-md-3">
            <label class="form-label">Search</label>
            <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" placeholder="Model, serial, reason...">
        </div>
        <div class="col-md-2">
            <label class="form-label">Category</label>
            <select class="form-select" name="category">
                <option value="">All Categories</option>
                <option value="Computer" <?php echo $category_filter == 'Computer' ? 'selected' : ''; ?>>Computer</option>
                <option value="System Unit" <?php echo $category_filter == 'System Unit' ? 'selected' : ''; ?>>System Unit</option>
                <option value="Monitor" <?php echo $category_filter == 'Monitor' ? 'selected' : ''; ?>>Monitor</option>
                <option value="Keyboard" <?php echo $category_filter == 'Keyboard' ? 'selected' : ''; ?>>Keyboard</option>
                <option value="AVR" <?php echo $category_filter == 'AVR' ? 'selected' : ''; ?>>AVR</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Disposal Status</label>
            <select class="form-select" name="disposal">
                <option value="">All Status</option>
                <option value="pending" <?php echo $disposal_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="disposed" <?php echo $disposal_filter == 'disposed' ? 'selected' : ''; ?>>Disposed</option>
                <option value="recycled" <?php echo $disposal_filter == 'recycled' ? 'selected' : ''; ?>>Recycled</option>
                <option value="repaired" <?php echo $disposal_filter == 'repaired' ? 'selected' : ''; ?>>Repaired</option>
                <option value="donated" <?php echo $disposal_filter == 'donated' ? 'selected' : ''; ?>>Donated</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Equipment Type</label>
            <select class="form-select" name="type">
                <option value="">All Types</option>
                <option value="computer" <?php echo $equipment_type_filter == 'computer' ? 'selected' : ''; ?>>Computer</option>
                <option value="kitchen" <?php echo $equipment_type_filter == 'kitchen' ? 'selected' : ''; ?>>Kitchen</option>
                <option value="office" <?php echo $equipment_type_filter == 'office' ? 'selected' : ''; ?>>Office</option>
                <option value="lab" <?php echo $equipment_type_filter == 'lab' ? 'selected' : ''; ?>>Lab</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">&nbsp;</label>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-search me-1"></i>Apply Filters
                </button>
                <a href="condemned.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i>Clear
                </a>
            </div>
        </div>
    </form>
</div>

<!-- Condemned Equipment Table -->
<div class="table-container">
    <div class="table-header">
        <div class="table-title">
            <i class="fas fa-list"></i>
            Condemned Equipment List
            <span class="badge bg-danger ms-2"><?php echo count($condemned_items); ?> items</span>
        </div>
        <div>
            <span class="text-muted small me-3">
                <i class="fas fa-info-circle me-1"></i>Select items for PRS form
            </span>
        </div>
    </div>
    
    <form id="prsForm" method="POST" action="generate_prs_form.php">
        <input type="hidden" name="selected_ids" id="selectedIds">
    </form>
    
    <div class="table-responsive">
        <table class="table" id="condemnedTable">
            <thead>
                <tr>
                    <th width="40">
                        <input type="checkbox" id="selectAll" class="form-check-input">
                    </th>
                    <th>Equipment</th>
                    <th>Category</th>
                    <th>Serial Number</th>
                    <th>Accountable Person</th>
                    <th>Condemned Date</th>
                    <th>Condemned By</th>
                    <th>Disposal Status</th>
                    <th width="120">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($condemned_items)): ?>
                <tr>
                    <td colspan="9" class="text-center py-5">
                        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No condemned equipment found</h5>
                        <p class="text-muted">Try adjusting your filters or add new condemned equipment.</p>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($condemned_items as $item): ?>
                <?php 
                    $isFinalized = ($item['disposal_status'] === 'Complete Condemned');
                    $categoryClass = 'other';
                    if (strpos($item['category'], 'System') !== false) $categoryClass = 'system-unit';
                    else if (strpos($item['category'], 'Monitor') !== false) $categoryClass = 'monitor';
                    else if (strpos($item['category'], 'Keyboard') !== false) $categoryClass = 'keyboard';
                    else if (strpos($item['category'], 'AVR') !== false) $categoryClass = 'avr';
                    
                    $statusClass = strtolower(str_replace(' ', '-', $item['disposal_status']));
                ?>
                <tr class="equipment-row" data-equipment-type="<?php echo htmlspecialchars($item['equipment_type']); ?>">
                    <td>
                        <input type="checkbox" class="form-check-input item-checkbox" 
                               value="<?php echo $item['id']; ?>" 
                               data-id="<?php echo $item['id']; ?>">
                    </td>
                    <td>
                        <div class="equipment-info">
                            <div class="equipment-icon <?php echo $categoryClass; ?>">
                                <i class="fas <?php 
                                    echo $categoryClass == 'system-unit' ? 'fa-server' : 
                                        ($categoryClass == 'monitor' ? 'fa-tv' : 
                                        ($categoryClass == 'keyboard' ? 'fa-keyboard' : 
                                        ($categoryClass == 'avr' ? 'fa-bolt' : 'fa-question-circle'))); 
                                ?>"></i>
                            </div>
                            <div class="equipment-details">
                                <h6><?php echo htmlspecialchars($item['model']); ?></h6>
                                <small>ID: <?php echo $item['id']; ?></small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="category-badge <?php echo $categoryClass; ?>">
                            <?php echo htmlspecialchars($item['category']); ?>
                        </span>
                    </td>
                    <td><code><?php echo htmlspecialchars($item['serial_number']) ?: 'N/A'; ?></code></td>
                    <td>
                        <span class="fw-bold" style="color: #1F2937;">
                            <?php 
                            $accountable = isset($item['accountable_person']) && !empty($item['accountable_person']) 
                                ? $item['accountable_person'] 
                                : (isset($item['remarks']) && !empty($item['remarks']) 
                                    ? $item['remarks'] 
                                    : 'N/A');
                            echo htmlspecialchars($accountable); 
                            ?>
                        </span>
                    </td>
                    <td>
                        <?php echo $item['condemned_date'] ? date('M d, Y', strtotime($item['condemned_date'])) : 'N/A'; ?>
                        <small class="text-muted d-block">
                            <?php echo $item['condemned_date'] ? date('h:i A', strtotime($item['condemned_date'])) : ''; ?>
                        </small>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark">
                            <?php echo htmlspecialchars($item['condemned_by_name']) ?: 'Unknown'; ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-badge <?php echo $statusClass; ?>">
                            <?php if ($isFinalized): ?>
                                <i class="fas fa-check-circle me-1"></i>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($item['disposal_status']); ?>
                        </span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <button type="button" class="action-btn view view-btn" 
                                    data-id="<?php echo $item['id']; ?>"
                                    data-type="<?php echo $item['source_type'] === 'condemned' ? 'condemned' : htmlspecialchars($item['equipment_type']); ?>"
                                    title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>

                            <?php if ($item['disposal_status'] === 'pending'): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Deploy this item back to active inventory?');">
                                    <input type="hidden" name="action" value="deploy">
                                    <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                    <input type="hidden" name="equipment_type" value="<?php echo $item['equipment_type']; ?>">
                                    <button type="submit" class="action-btn deploy" title="Deploy to Active">
                                        <i class="fas fa-rocket"></i>
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Transfer to waste management? This will finalize the condemnation.');">
                                    <input type="hidden" name="action" value="transfer">
                                    <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                    <input type="hidden" name="equipment_type" value="<?php echo $item['equipment_type']; ?>">
                                    <button type="submit" class="action-btn transfer" title="Finalize Condemnation">
                                        <i class="fas fa-file-export"></i>
                                    </button>
                                </form>
                                <button type="button" class="action-btn archive" 
                                        onclick="openArchiveModal(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['model']); ?>')" 
                                        title="Archive Item">
                                    <i class="fas fa-archive"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- View Details Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fas fa-search me-2"></i>
                    Condemned Item Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="detail-section">
                    <div class="row">
                        <div class="col-12">
                            <div class="detail-label">Equipment Model</div>
                            <div class="detail-value" id="view_model"></div>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="detail-label">Category</div>
                        <span class="category-badge" id="view_category"></span>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-label">Serial Number</div>
                        <code id="view_serial"></code>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="detail-label">Purchase Date</div>
                        <div class="detail-value" id="view_purchase_date">
                            <span class="badge bg-secondary"><i class="fas fa-calendar-alt me-1"></i> N/A</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-label">Condition Status</div>
                        <div class="detail-value" id="view_condition_status">
                            <span class="badge bg-info">N/A</span>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="detail-label">Condemned Date</div>
                        <div class="detail-value" id="view_condemned_date"></div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-label">Condemned By</div>
                        <div class="detail-value" id="view_condemned_by"></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="detail-label">Accountable Person</div>
                    <div class="detail-value" id="view_accountable"></div>
                </div>
                
                <div class="reason-box mb-3">
                    <div class="detail-label text-danger">Reason for Condemnation</div>
                    <p class="mb-0" id="view_reason"></p>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="detail-label">Disposal Status</div>
                        <span class="status-badge" id="view_disposal_status"></span>
                    </div>
                    <div class="col-md-6" id="view_disposal_date_container">
                        <div class="detail-label">Finalized Date</div>
                        <div class="detail-value" id="view_disposal_date"></div>
                    </div>
                </div>
                
                <div class="mt-3" id="view_notes_container">
                    <div class="detail-label">Administrative Notes</div>
                    <p class="text-muted" id="view_notes"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Archive Modal -->
<div class="modal fade" id="archiveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #6B7280 0%, #4B5563 100%); color: white;">
                <h5 class="modal-title">
                    <i class="fas fa-archive me-2"></i>
                    Archive Condemned Item
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="archiveForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="archive">
                    <input type="hidden" name="id" id="archive_id">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> Archiving will move this item from the condemned list to the archive. This action can be undone by restoring from the archive.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Item to Archive</label>
                        <p class="form-control-plaintext bg-light p-2 rounded" id="archive_item_name"></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Archive Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="archive_reason" id="archive_reason" rows="3" 
                                  placeholder="Why is this item being archived? (e.g., kept for documentation, awaiting final disposal, etc.)" required></textarea>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> This item will be removed from the condemned list and moved to the archive.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn" style="background: linear-gradient(135deg, #6B7280 0%, #4B5563 100%); color: white;">
                        <i class="fas fa-archive me-2"></i>Archive Item
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle me-2"></i>
                    Add Condemned Equipment
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Model</label>
                            <input type="text" class="form-control" name="model" placeholder="e.g., Dell Optiplex 3080" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category" required>
                                <option value="">Select Category</option>
                                <option value="System Unit">System Unit</option>
                                <option value="Monitor">Monitor</option>
                                <option value="All in one">All in One</option>
                                <option value="Keyboard">Keyboard</option>
                                <option value="AVR">AVR</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Serial Number</label>
                            <input type="text" class="form-control" name="serial_number" placeholder="Enter serial number">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Equipment Type</label>
                            <select class="form-select" name="equipment_type" required>
                                <option value="computer">Computer</option>
                                <option value="kitchen">Kitchen</option>
                                <option value="office">Office</option>
                                <option value="lab">Lab</option>
                                <option value="general">General</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Disposal Status</label>
                            <select class="form-select" name="disposal_status" required>
                                <option value="pending">Pending</option>
                                <option value="disposed">Disposed</option>
                                <option value="recycled">Recycled</option>
                                <option value="repaired">Repaired</option>
                                <option value="donated">Donated</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Reason for Condemnation</label>
                        <textarea class="form-control" name="reason_condemned" rows="3" placeholder="Explain why this equipment was condemned..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-save me-2"></i>Add Equipment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #6B7280 0%, #4B5563 100%); color: white;">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>
                    Edit Condemned Equipment
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Model</label>
                            <input type="text" class="form-control" name="model" id="edit_model" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category" id="edit_category" required>
                                <option value="System Unit">System Unit</option>
                                <option value="Monitor">Monitor</option>
                                <option value="All in one">All in One</option>
                                <option value="Keyboard">Keyboard</option>
                                <option value="AVR">AVR</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Serial Number</label>
                            <input type="text" class="form-control" name="serial_number" id="edit_serial_number">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Equipment Type</label>
                            <select class="form-select" name="equipment_type" id="edit_equipment_type" required>
                                <option value="computer">Computer</option>
                                <option value="kitchen">Kitchen</option>
                                <option value="office">Office</option>
                                <option value="lab">Lab</option>
                                <option value="general">General</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Disposal Status</label>
                            <select class="form-select" name="disposal_status" id="edit_disposal_status" required>
                                <option value="pending">Pending</option>
                                <option value="disposed">Disposed</option>
                                <option value="recycled">Recycled</option>
                                <option value="repaired">Repaired</option>
                                <option value="donated">Donated</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Disposal Date</label>
                            <input type="date" class="form-control" name="disposal_date" id="edit_disposal_date">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Reason for Condemnation</label>
                        <textarea class="form-control" name="reason_condemned" id="edit_reason_condemned" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Disposal Notes</label>
                        <textarea class="form-control" name="disposal_notes" id="edit_disposal_notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update Equipment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-trash-alt me-2"></i>
                    Delete Record
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_id">
                    <input type="hidden" name="equipment_type" id="delete_type">
                    
                    <div class="text-center mb-4">
                        <div class="text-danger mb-3">
                            <i class="fas fa-exclamation-triangle fa-4x"></i>
                        </div>
                        <h5 class="mb-3">Are you absolutely sure?</h5>
                        <p class="text-muted mb-0">
                            This action cannot be undone. This will permanently delete this condemned equipment record.
                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash-alt me-2"></i>Delete Permanently
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%); color: white;">
                <h5 class="modal-title">
                    <i class="fas fa-file-import me-2"></i>
                    Import Condemned Equipment
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-3">
                    <h6 class="fw-bold"><i class="fas fa-info-circle me-2"></i>CSV Format</h6>
                    <p class="small mb-0">Your CSV should have the following columns: Model, Category, Serial Number, Equipment Type, Reason Condemned, Disposal Status</p>
                </div>
                <form action="import_condemned_csv.php" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="csv_file" class="form-label">Select CSV File</label>
                        <input type="file" class="form-control" name="csv_file" accept=".csv" required>
                        <small class="text-muted">Maximum file size: 10MB</small>
                    </div>
                    <div class="modal-footer px-0 pb-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-upload me-2"></i>Import CSV
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- PRS Preview Modal -->
<div class="modal fade" id="prsPreviewModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="fas fa-file-excel me-2"></i>
                    PRS Form Preview
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Preview:</strong> Review the selected items below before downloading the PRS form.
                    <span id="previewItemCount" class="badge bg-primary ms-2"></span>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="prsPreviewTable">
                        <thead class="table-light">
                            <tr>
                                <th width="5%">#</th>
                                <th width="8%">QTY</th>
                                <th width="8%">UNIT</th>
                                <th width="25%">DESCRIPTION</th>
                                <th width="10%">YEAR ACQUIRED</th>
                                <th width="15%">PROP NO</th>
                                <th width="15%">END-USER</th>
                                <th width="14%">UNIT VALUE</th>
                            </tr>
                        </thead>
                        <tbody id="prsPreviewBody">
                            <!-- Preview items will be inserted here -->
                        </tbody>
                        <tfoot class="table-secondary">
                            <tr>
                                <td colspan="7" class="text-end"><strong>TOTAL:</strong></td>
                                <td><strong id="totalValue">₱ 0.00</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-success" id="confirmDownloadBtn">
                    <i class="fas fa-download me-2"></i>Download Excel File
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Global functions - defined outside document.ready to be accessible globally
function openArchiveModal(id, model) {
    document.getElementById('archive_id').value = id;
    document.getElementById('archive_item_name').textContent = model;
    document.getElementById('archive_reason').value = '';
    
    new bootstrap.Modal(document.getElementById('archiveModal')).show();
}

function getCategoryClass(category) {
    if (!category) return 'other';
    
    const cat = category.toString().toLowerCase();
    if (cat.includes('system')) return 'system-unit';
    if (cat.includes('monitor')) return 'monitor';
    if (cat.includes('keyboard')) return 'keyboard';
    if (cat.includes('avr')) return 'avr';
    if (cat.includes('kitchen')) return 'kitchen';
    if (cat.includes('office')) return 'office';
    if (cat.includes('lab')) return 'lab';
    if (cat.includes('computer')) return 'system-unit';
    return 'other';
}

function escapeHtml(text) {
    if (!text) return 'N/A';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
}

$(document).ready(function() {
    // Initialize DataTable
    if ($.fn.DataTable.isDataTable('#condemnedTable')) {
        $('#condemnedTable').DataTable().destroy();
    }

    $('#condemnedTable').DataTable({
        "pageLength": 25,
        "order": [[5, "desc"]],
        "columnDefs": [
            { "orderable": false, "targets": [0, 8] }
        ],
        "language": {
            "search": "<i class='fas fa-search me-2'></i>",
            "searchPlaceholder": "Search table...",
            "paginate": {
                "previous": "<i class='fas fa-chevron-left'></i>",
                "next": "<i class='fas fa-chevron-right'></i>"
            }
        }
    });

    // Select All Checkbox
    $('#selectAll').on('change', function() {
        $('.item-checkbox').prop('checked', $(this).prop('checked'));
        updatePRSButton();
    });

    // Individual Checkbox Change
    $(document).on('change', '.item-checkbox', function() {
        updatePRSButton();
        
        const totalCheckboxes = $('.item-checkbox').length;
        const checkedCheckboxes = $('.item-checkbox:checked').length;
        $('#selectAll').prop('checked', totalCheckboxes === checkedCheckboxes);
    });

    // Update PRS Button
    function updatePRSButton() {
        const checkedCount = $('.item-checkbox:checked').length;
        const maxItems = 46;
        
        if (checkedCount > 0) {
            $('#downloadPRSBtn').prop('disabled', false);
            let buttonText = '<i class="fas fa-file-excel me-2"></i>PRS Form (' + checkedCount + ')';
            if (checkedCount > maxItems) {
                buttonText += ' <span class="badge bg-warning text-dark">Max ' + maxItems + '</span>';
            }
            $('#downloadPRSBtn').html(buttonText);
        } else {
            $('#downloadPRSBtn').prop('disabled', true).html('<i class="fas fa-file-excel me-2"></i>PRS Form');
        }
    }

    // Download PRS Form
    $('#downloadPRSBtn').on('click', function() {
        const selectedIds = [];
        
        $('.item-checkbox:checked').each(function() {
            selectedIds.push($(this).val());
        });

        if (selectedIds.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'No Selection',
                text: 'Please select at least one item to generate PRS form.'
            });
            return;
        }

        const maxItems = 46;
        if (selectedIds.length > maxItems) {
            Swal.fire({
                icon: 'warning',
                title: 'Too Many Items',
                html: `You selected <strong>${selectedIds.length}</strong> items.<br>
                       PRS form can only accommodate ${maxItems} items.<br>
                       Only the first ${maxItems} will be included.`,
                showCancelButton: true,
                confirmButtonColor: '#10B981',
                cancelButtonColor: '#6B7280',
                confirmButtonText: 'Continue anyway'
            }).then((result) => {
                if (result.isConfirmed) {
                    showPRSPreview(selectedIds);
                }
            });
        } else {
            showPRSPreview(selectedIds);
        }
    });

    // Show PRS Preview
    function showPRSPreview(selectedIds) {
        $.ajax({
            url: 'get_prs_preview_data.php',
            method: 'POST',
            data: { selected_ids: selectedIds.join(',') },
            dataType: 'json',
            beforeSend: function() {
                Swal.fire({
                    title: 'Loading...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
            },
            success: function(response) {
                Swal.close();
                if (response.success) {
                    displayPRSPreview(response.data, selectedIds);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || 'Failed to load preview data.'
                    });
                }
            },
            error: function() {
                Swal.close();
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to load preview data. Please try again.'
                });
            }
        });
    }

    // Display PRS Preview
    function displayPRSPreview(items, selectedIds) {
        const previewBody = $('#prsPreviewBody');
        previewBody.empty();
        
        let totalValue = 0;
        const maxItems = 46;
        const itemsToShow = items.slice(0, maxItems);
        
        $('#previewItemCount').text(itemsToShow.length + ' items');
        
        itemsToShow.forEach(function(item, index) {
            const unitValue = parseFloat(item.estimated_value) || 0;
            totalValue += unitValue;
            
            const row = `
                <tr>
                    <td class="text-center">${index + 1}</td>
                    <td class="text-center">1</td>
                    <td>pc</td>
                    <td>${escapeHtml(item.description)}</td>
                    <td class="text-center">${escapeHtml(item.year_acquired)}</td>
                    <td>${escapeHtml(item.serial_number)}</td>
                    <td>${escapeHtml(item.end_user)}</td>
                    <td class="text-end">₱ ${unitValue.toFixed(2)}</td>
                </tr>
            `;
            previewBody.append(row);
        });
        
        $('#totalValue').text('₱ ' + totalValue.toFixed(2));
        $('#confirmDownloadBtn').data('selectedIds', selectedIds.join(','));
        $('#prsPreviewModal').modal('show');
    }

    // Confirm Download
    $('#confirmDownloadBtn').on('click', function() {
        const selectedIds = $(this).data('selectedIds');
        $('#selectedIds').val(selectedIds);
        $('#prsForm').submit();
        $('#prsPreviewModal').modal('hide');
    });

    // View Details Button Handler
    $(document).on('click', '.view-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const id = $(this).data('id');
        const type = $(this).data('type');
        
        console.log('🔍 View button clicked - ID:', id, 'Type:', type);
        
        // Reset modal content
        $('#view_model').text('Loading...');
        $('#view_category').text('').attr('class', 'category-badge');
        $('#view_serial').text('');
        $('#view_condemned_date').text('');
        $('#view_condemned_by').text('');
        $('#view_accountable').text('');
        $('#view_reason').text('');
        $('#view_disposal_status').text('').attr('class', 'status-badge');
        $('#view_disposal_date').text('');
        $('#view_notes').text('');
        
        // Reset purchase date and condition status
        $('#view_purchase_date').html('<span class="badge bg-secondary"><i class="fas fa-calendar-alt me-1"></i> N/A</span>');
        $('#view_condition_status').html('<span class="badge bg-info">N/A</span>');
        
        // Hide conditional containers initially
        $('#view_disposal_date_container').hide();
        $('#view_notes_container').hide();
        
        // Show modal with loading state
        $('#viewModal').modal('show');

        // Fetch details via AJAX
        $.ajax({
            url: 'get_condemned_details.php',
            method: 'GET',
            data: { id: id, type: type },
            dataType: 'json',
            timeout: 10000,
            success: function(response) {
                console.log('✅ AJAX Response:', response);
                
                if (response.error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.error
                    });
                    $('#viewModal').modal('hide');
                    return;
                }
                
                // Populate modal with response data
                $('#view_model').text(response.model || 'N/A');
                
                // Set category badge with proper class
                const category = response.category || type.toUpperCase();
                const categoryClass = getCategoryClass(category);
                $('#view_category').text(category).attr('class', 'category-badge ' + categoryClass);
                
                // Serial number
                $('#view_serial').text(response.serial_number || 'N/A');
                
                // Purchase Date
                if (response.purchase_date && response.purchase_date !== 'N/A' && response.purchase_date !== null) {
                    $('#view_purchase_date').html('<span class="badge bg-secondary"><i class="fas fa-calendar-alt me-1"></i> ' + response.purchase_date + '</span>');
                } else {
                    $('#view_purchase_date').html('<span class="badge bg-secondary"><i class="fas fa-calendar-alt me-1"></i> No Purchase Date</span>');
                }
                
                // Condition Status
                if (response.condition_status && response.condition_status !== 'N/A') {
                    let statusClass = 'bg-info';
                    if (response.condition_status === 'Excellent') statusClass = 'bg-success';
                    if (response.condition_status === 'Good') statusClass = 'bg-primary';
                    if (response.condition_status === 'Fair') statusClass = 'bg-warning text-dark';
                    if (response.condition_status === 'Poor') statusClass = 'bg-danger';
                    if (response.condition_status === 'Damaged') statusClass = 'bg-dark';
                    
                    $('#view_condition_status').html('<span class="badge ' + statusClass + '">' + response.condition_status + '</span>');
                } else {
                    $('#view_condition_status').html('<span class="badge bg-info">N/A</span>');
                }
                
                // Dates
                $('#view_condemned_date').text(response.condemned_date || 'N/A');
                $('#view_condemned_by').text(response.condemned_by_name || 'Unknown');
                
                // Accountable person
                $('#view_accountable').text(response.remarks || 'N/A');
                
                // Reason
                $('#view_reason').text(response.reason_condemned || 'No reason provided');
                
                // Set status badge
                const status = response.disposal_status || 'pending';
                const statusClass = status.toLowerCase().replace(/ /g, '-');
                $('#view_disposal_status').text(status.toUpperCase()).attr('class', 'status-badge ' + statusClass);
                
                // Handle disposal date
                if (response.disposal_date && response.disposal_date !== 'N/A' && response.disposal_date !== null) {
                    $('#view_disposal_date').text(response.disposal_date);
                    $('#view_disposal_date_container').show();
                } else {
                    $('#view_disposal_date_container').hide();
                }
                
                // Handle notes
                if (response.disposal_notes && response.disposal_notes !== 'N/A' && response.disposal_notes !== null) {
                    $('#view_notes').text(response.disposal_notes);
                    $('#view_notes_container').show();
                } else {
                    $('#view_notes_container').hide();
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ AJAX Error:', status, error);
                console.error('Response Text:', xhr.responseText);
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to load item details. Please try again.'
                });
                $('#viewModal').modal('hide');
            }
        });
    });
});

// Debug: Log all view buttons on page load
console.log('👁️ View buttons found:', $('.view-btn').length);
</script>

<?php include '../includes/footer.php'; ?>