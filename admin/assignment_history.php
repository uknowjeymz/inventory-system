<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get filter parameters
$user_filter = $_GET['user'] ?? '';
$computer_filter = $_GET['computer'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Build query with filters
$where_conditions = ['1=1'];
$params = [];

if (!empty($user_filter)) {
    $where_conditions[] = "ah.user_id = ?";
    $params[] = $user_filter;
}

if (!empty($computer_filter)) {
    $where_conditions[] = "ah.computer_id = ?";
    $params[] = $computer_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "ah.status = ?";
    $params[] = $status_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(ah.assigned_date) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(ah.assigned_date) <= ?";
    $params[] = $date_to;
}

if (!empty($search)) {
    $where_conditions[] = "(u.full_name LIKE ? OR u.email LIKE ? OR 
                           ci.item_number LIKE ? OR ci.computer_set_description LIKE ? OR 
                           ke.item_number LIKE ? OR ke.equipment_name LIKE ? OR
                           oe.item_number LIKE ? OR oe.equipment_name LIKE ? OR
                           le.item_number LIKE ? OR le.equipment_name LIKE ? OR
                           ge.item_number LIKE ? OR ge.article LIKE ? OR
                           ah.notes LIKE ?)";
    $search_term = "%{$search}%";
    // Add multiple search terms for each table
    for ($i = 0; $i < 13; $i++) {
        $params[] = $search_term;
    }
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Get assignment history with details - modified to handle different equipment types
$query = "SELECT ah.*, 
          CASE 
              WHEN ah.equipment_table = 'computer_inventory' THEN ci.item_number
              WHEN ah.equipment_table = 'kitchen_equipment' THEN ke.item_number
              WHEN ah.equipment_table = 'office_equipment' THEN oe.item_number
              WHEN ah.equipment_table = 'lab_equipment' THEN le.item_number
              WHEN ah.equipment_table = 'general_equipment' THEN ge.item_number
              ELSE 'N/A'
          END as item_number,
          CASE 
              WHEN ah.equipment_table = 'computer_inventory' THEN ci.computer_set_description
              WHEN ah.equipment_table = 'kitchen_equipment' THEN ke.equipment_name
              WHEN ah.equipment_table = 'office_equipment' THEN oe.equipment_name
              WHEN ah.equipment_table = 'lab_equipment' THEN le.equipment_name
              WHEN ah.equipment_table = 'general_equipment' THEN ge.article
              ELSE 'Unknown Equipment'
          END as equipment_description,
          CASE 
              WHEN ah.equipment_table = 'computer_inventory' THEN 'computer_lab'
              WHEN ah.equipment_table = 'kitchen_equipment' THEN 'kitchen'
              WHEN ah.equipment_table = 'office_equipment' THEN 'office'
              WHEN ah.equipment_table = 'lab_equipment' THEN 'regular_lab'
              WHEN ah.equipment_table = 'general_equipment' THEN 'general'
              ELSE 'unknown'
          END as equipment_type,
          u.full_name as user_name, u.email as user_email,
          ab.full_name as assigned_by_name,
          rb.full_name as maintenance_resolved_by_name,
          l.location_name,
          DATEDIFF(COALESCE(ah.returned_date, NOW()), ah.assigned_date) as days_assigned
          FROM assignment_history ah
          LEFT JOIN users u ON ah.user_id = u.id
          LEFT JOIN users ab ON ah.assigned_by = ab.id
          LEFT JOIN users rb ON ah.maintenance_resolved_by = rb.id
          LEFT JOIN locations l ON ah.location_id = l.id
          LEFT JOIN computer_inventory ci ON ah.equipment_table = 'computer_inventory' AND ah.computer_id = ci.id
          LEFT JOIN kitchen_equipment ke ON ah.equipment_table = 'kitchen_equipment' AND ah.computer_id = ke.id
          LEFT JOIN office_equipment oe ON ah.equipment_table = 'office_equipment' AND ah.computer_id = oe.id
          LEFT JOIN lab_equipment le ON ah.equipment_table = 'lab_equipment' AND ah.computer_id = le.id
          LEFT JOIN general_equipment ge ON ah.equipment_table = 'general_equipment' AND ah.computer_id = ge.id
          {$where_clause}
          ORDER BY ah.assigned_date DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get users for filter dropdown
$query = "SELECT id, full_name, email FROM users WHERE role = 'user' ORDER BY full_name";
$stmt = $db->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get equipment for filter dropdown - now including all types
$equipment_query = "
    SELECT id, item_number, 'computer_inventory' as source_table, computer_set_description as description FROM computer_inventory 
    UNION ALL
    SELECT id, item_number, 'kitchen_equipment' as source_table, equipment_name as description FROM kitchen_equipment 
    UNION ALL
    SELECT id, item_number, 'office_equipment' as source_table, equipment_name as description FROM office_equipment 
    UNION ALL
    SELECT id, item_number, 'lab_equipment' as source_table, equipment_name as description FROM lab_equipment 
    UNION ALL
    SELECT id, item_number, 'general_equipment' as source_table, article as description FROM general_equipment 
    ORDER BY CAST(item_number AS UNSIGNED)";
$equipment_stmt = $db->prepare($equipment_query);
$equipment_stmt->execute();
$equipment_list = $equipment_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get detailed statistics
$query = "SELECT 
          COUNT(*) as total_assignments,
          COUNT(CASE WHEN status = 'active' THEN 1 END) as active_assignments,
          COUNT(CASE WHEN status = 'returned' THEN 1 END) as returned_assignments,
          COUNT(CASE WHEN status = 'transferred' THEN 1 END) as transferred_assignments,
          AVG(DATEDIFF(COALESCE(returned_date, NOW()), assigned_date)) as avg_assignment_days,
          MAX(DATEDIFF(COALESCE(returned_date, NOW()), assigned_date)) as max_days,
          MIN(DATEDIFF(COALESCE(returned_date, NOW()), assigned_date)) as min_days
          FROM assignment_history";
$stmt = $db->prepare($query);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get monthly trends
$monthly_query = "SELECT 
                  DATE_FORMAT(assigned_date, '%Y-%m') as month,
                  COUNT(*) as assignments
                  FROM assignment_history
                  WHERE assigned_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                  GROUP BY DATE_FORMAT(assigned_date, '%Y-%m')
                  ORDER BY month DESC";
$stmt = $db->prepare($monthly_query);
$stmt->execute();
$monthly_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Assignment History";
include '../includes/header.php';
?>

<style>
:root {
    --ucc-green-primary: #2E7D32;
    --ucc-green-secondary: #4CAF50;
    --ucc-green-light: #81C784;
    --ucc-green-soft: #E8F5E9;
    --ucc-green-mint: #C8E6C9;
    --ucc-green-dark: #1B5E20;
    --ucc-white: #FFFFFF;
    --ucc-off-white: #F8F9FA;
    --ucc-gray-light: #F1F8E9;
}

/* Equipment Type Icons */
.equipment-icon.computer_lab { background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%); }
.equipment-icon.kitchen { background: linear-gradient(135deg, #fd7e14 0%, #dc6b12 100%); }
.equipment-icon.office { background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); }
.equipment-icon.regular_lab { background: linear-gradient(135deg, #dc3545 0%, #b02a37 100%); }
.equipment-icon.general { background: linear-gradient(135deg, #6c757d 0%, #545b62 100%); }

/* Page Header */
.page-header {
    background: linear-gradient(135deg, var(--ucc-green-primary) 0%, var(--ucc-green-dark) 100%);
    border-radius: 24px;
    padding: 2rem 2.5rem;
    margin-bottom: 2rem;
    color: white;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(46, 125, 50, 0.15);
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
    color: var(--ucc-green-primary);
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
    border: 1px solid var(--ucc-green-mint);
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(46, 125, 50, 0.15);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: white;
}

.stat-icon.primary { background: linear-gradient(135deg, var(--ucc-green-primary) 0%, var(--ucc-green-secondary) 100%); }
.stat-icon.success { background: linear-gradient(135deg, #43A047 0%, #66BB6A 100%); }
.stat-icon.info { background: linear-gradient(135deg, #0288D1 0%, #4FC3F7 100%); }
.stat-icon.warning { background: linear-gradient(135deg, #F57C00 0%, #FFB74D 100%); }

.stat-content {
    flex: 1;
}

.stat-content h3 {
    font-size: 2rem;
    font-weight: 800;
    margin: 0;
    line-height: 1.2;
    color: var(--ucc-green-dark);
}

.stat-content p {
    margin: 0;
    color: #6B7280;
    font-size: 0.9rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-trend {
    font-size: 0.75rem;
    color: var(--ucc-green-primary);
    margin-top: 0.3rem;
}

/* Filter Section */
.filter-section {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
    border: 1px solid var(--ucc-green-mint);
}

.filter-title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--ucc-green-dark);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-title i {
    color: var(--ucc-green-primary);
}

.filter-badge {
    background: var(--ucc-green-soft);
    color: var(--ucc-green-dark);
    padding: 0.2rem 0.8rem;
    border-radius: 50px;
    font-size: 0.7rem;
    margin-left: 1rem;
}

/* Table Container */
.table-container {
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
    border: 1px solid var(--ucc-green-mint);
    overflow: hidden;
    margin-bottom: 2rem;
}

.table-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--ucc-green-mint);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.table-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--ucc-green-dark);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.table-title i {
    color: var(--ucc-green-primary);
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table thead th {
    background: var(--ucc-green-soft);
    color: var(--ucc-green-dark);
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 1rem;
    border-bottom: 2px solid var(--ucc-green-primary);
}

.table tbody td {
    padding: 1rem;
    border-bottom: 1px solid var(--ucc-green-mint);
    vertical-align: middle;
}

.table tbody tr {
    transition: all 0.3s ease;
}

.table tbody tr:hover {
    background: var(--ucc-green-soft);
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
    flex-shrink: 0;
}

.equipment-details h6 {
    font-weight: 700;
    color: var(--ucc-green-dark);
    margin: 0 0 0.2rem 0;
}

.equipment-details small {
    color: #6B7280;
    font-size: 0.7rem;
}

/* User Info */
.user-info {
    display: flex;
    flex-direction: column;
}

.user-name {
    font-weight: 600;
    color: #1F2937;
    margin-bottom: 0.2rem;
}

.user-email {
    font-size: 0.7rem;
    color: #6B7280;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.user-email i {
    font-size: 0.6rem;
    color: var(--ucc-green-primary);
}

/* Date Display */
.date-display {
    display: flex;
    flex-direction: column;
}

.date-main {
    font-weight: 600;
    color: #1F2937;
    font-size: 0.85rem;
    margin-bottom: 0.2rem;
}

.date-time {
    font-size: 0.7rem;
    color: #6B7280;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.date-time i {
    font-size: 0.6rem;
    color: var(--ucc-green-primary);
}

/* Duration Badge */
.duration-badge {
    display: inline-block;
    padding: 0.3rem 0.8rem;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
    background: var(--ucc-green-soft);
    color: var(--ucc-green-dark);
}

.duration-detail {
    font-size: 0.7rem;
    color: #6B7280;
    margin-top: 0.3rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.duration-detail i {
    color: var(--ucc-green-primary);
}

/* Status Badges */
.status-badge {
    padding: 0.4rem 1rem;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
}

.status-badge.active {
    background: #d4edda;
    color: #155724;
}

.status-badge.returned {
    background: #e2e3e5;
    color: #383d41;
}

.status-badge.transferred {
    background: #d1ecf1;
    color: #0c5460;
}

.status-badge.maintenance {
    background: #fff3cd;
    color: #856404;
}

/* Assigned By */
.assigned-by {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.assigned-by .avatar {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    background: var(--ucc-green-soft);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--ucc-green-dark);
}

.assigned-by .name {
    font-weight: 500;
    color: #1F2937;
    font-size: 0.9rem;
}

/* Notes */
.notes-cell {
    max-width: 200px;
}

.notes-preview {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #6B7280;
    font-size: 0.8rem;
    cursor: pointer;
    transition: color 0.3s ease;
}

.notes-preview:hover {
    color: var(--ucc-green-primary);
}

.notes-preview i {
    font-size: 0.9rem;
}

/* Maintenance Info */
.maintenance-info {
    margin-top: 0.5rem;
    padding: 0.5rem;
    border-radius: 8px;
    font-size: 0.7rem;
}

.maintenance-info.warning {
    background: #fff3cd;
    color: #856404;
}

.maintenance-info.success {
    background: #d4edda;
    color: #155724;
}

/* Location Badge */
.location-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    background: var(--ucc-green-soft);
    color: var(--ucc-green-dark);
    padding: 0.2rem 0.8rem;
    border-radius: 50px;
    font-size: 0.7rem;
    font-weight: 500;
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
    background: var(--ucc-green-soft);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 2rem;
    font-size: 3rem;
    color: var(--ucc-green-primary);
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

.empty-state .btn {
    background: linear-gradient(135deg, var(--ucc-green-primary) 0%, var(--ucc-green-dark) 100%);
    color: white;
    border: none;
    padding: 0.8rem 2rem;
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.empty-state .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(46, 125, 50, 0.3);
}

/* Responsive */
@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

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
    
    .table-container {
        padding: 1rem;
    }
    
    .table thead {
        display: none;
    }
    
    .table tbody tr {
        display: block;
        margin-bottom: 1rem;
        border: 1px solid var(--ucc-green-mint);
        border-radius: 10px;
    }
    
    .table tbody td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.8rem 1rem;
        border-bottom: 1px solid var(--ucc-green-mint);
    }
    
    .table tbody td::before {
        content: attr(data-label);
        font-weight: 600;
        color: var(--ucc-green-dark);
        width: 40%;
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
                    <i class="fas fa-history"></i>
                    <span>Assignment History</span>
                </div>
                <p class="header-subtitle">
                    Track equipment assignments, monitor usage patterns, and view complete history of equipment movements across the organization.
                </p>
                <div class="header-stats">
                    <div class="header-stat-item">
                        <span class="header-stat-number"><?php echo $stats['total_assignments']; ?></span>
                        <span class="header-stat-label">Total</span>
                    </div>
                    <div class="header-stat-item">
                        <span class="header-stat-number"><?php echo $stats['active_assignments']; ?></span>
                        <span class="header-stat-label">Active</span>
                    </div>
                    <div class="header-stat-item">
                        <span class="header-stat-number"><?php echo round($stats['avg_assignment_days']); ?></span>
                        <span class="header-stat-label">Avg Days</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="header-actions">
                    <a href="inventory_categories.php" class="btn">
                        <i class="fas fa-warehouse"></i> Inventory
                    </a>
                    <a href="users.php" class="btn">
                        <i class="fas fa-users"></i> Users
                    </a>
                    <button type="button" class="btn" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i> Export
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon purple">
            <i class="fas fa-clipboard-list"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['total_assignments']; ?></h3>
            <p>Total Assignments</p>
            <div class="stat-trend">
                <i class="fas fa-calendar"></i> Lifetime
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['active_assignments']; ?></h3>
            <p>Active</p>
            <div class="stat-trend">
                <i class="fas fa-clock"></i> Currently assigned
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fas fa-undo-alt"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['returned_assignments']; ?></h3>
            <p>Returned</p>
            <div class="stat-trend">
                <i class="fas fa-check-double"></i> Completed
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="fas fa-exchange-alt"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['transferred_assignments'] ?? 0; ?></h3>
            <p>Transferred</p>
            <div class="stat-trend">
                <i class="fas fa-arrow-right"></i> Reassigned
            </div>
        </div>
    </div>
</div>

<!-- Filter Section -->
<div class="filter-section">
    <div class="filter-title">
        <i class="fas fa-filter"></i>
        Filter History
        <?php if (!empty(array_filter([$user_filter, $computer_filter, $status_filter, $date_from, $date_to, $search]))): ?>
        <span class="filter-badge">Filters Applied</span>
        <?php endif; ?>
    </div>
    
    <form method="GET" class="row g-3">
        <div class="col-md-3">
            <label class="form-label">
                <i class="fas fa-search me-1 text-purple"></i>Search
            </label>
            <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="User, equipment, notes...">
        </div>
        
        <div class="col-md-2">
            <label class="form-label">
                <i class="fas fa-user me-1 text-purple"></i>User
            </label>
            <select class="form-select" name="user">
                <option value="">All Users</option>
                <?php foreach ($users as $user): ?>
                <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($user['full_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-md-2">
            <label class="form-label">
                <i class="fas fa-tag me-1 text-purple"></i>Status
            </label>
            <select class="form-select" name="status">
                <option value="">All Status</option>
                <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="returned" <?php echo $status_filter == 'returned' ? 'selected' : ''; ?>>Returned</option>
                <option value="transferred" <?php echo $status_filter == 'transferred' ? 'selected' : ''; ?>>Transferred</option>
                <option value="maintenance" <?php echo $status_filter == 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
            </select>
        </div>
        
        <div class="col-md-2">
            <label class="form-label">
                <i class="fas fa-calendar-alt me-1 text-purple"></i>From
            </label>
            <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
        </div>
        
        <div class="col-md-2">
            <label class="form-label">
                <i class="fas fa-calendar-alt me-1 text-purple"></i>To
            </label>
            <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
        </div>
        
        <div class="col-md-1">
            <label class="form-label">&nbsp;</label>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100" style="background: var(--history-purple); border-color: var(--history-purple);">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
    </form>
    
    <?php if (!empty(array_filter([$user_filter, $computer_filter, $status_filter, $date_from, $date_to, $search]))): ?>
    <div class="mt-3 d-flex justify-content-end">
        <a href="assignment_history.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-times me-1"></i>Clear All Filters
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- Assignment History Table -->
<div class="table-container">
    <div class="table-header">
        <div class="table-title">
            <i class="fas fa-list"></i>
            Assignment Records
            <span class="badge ms-2" style="background: var(--history-purple-soft); color: var(--history-purple-dark);">
                <?php echo count($assignments); ?> records
            </span>
        </div>
        <div>
            <span class="text-muted small">
                <i class="fas fa-info-circle me-1"></i>Showing most recent first
            </span>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="table" id="assignmentTable">
            <thead>
                <tr>
                    <th>Equipment</th>
                    <th>User</th>
                    <th>Assigned Date</th>
                    <th>Duration</th>
                    <th>Status</th>
                    <th>Assigned By</th>
                    <th>Location</th>
                    <th>Notes / Maintenance</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($assignments)): ?>
                <tr>
                    <td colspan="8" class="text-center py-5">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-history"></i>
                            </div>
                            <h3>No Assignment History Found</h3>
                            <p>There are no assignment records matching your criteria. Try adjusting your filters or check back later.</p>
                            <div class="mt-3">
                                <a href="inventory_categories.php" class="btn">
                                    <i class="fas fa-warehouse me-2"></i>Browse Inventory
                                </a>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($assignments as $assignment): 
                    // Determine icon based on equipment type
                    $icon_class = 'fa-desktop';
                    $equipment_type_class = 'computer_lab';
                    
                    if (isset($assignment['equipment_type'])) {
                        switch($assignment['equipment_type']) {
                            case 'kitchen':
                                $icon_class = 'fa-utensils';
                                $equipment_type_class = 'kitchen';
                                break;
                            case 'office':
                                $icon_class = 'fa-briefcase';
                                $equipment_type_class = 'office';
                                break;
                            case 'regular_lab':
                                $icon_class = 'fa-flask';
                                $equipment_type_class = 'regular_lab';
                                break;
                            case 'general':
                                $icon_class = 'fa-box';
                                $equipment_type_class = 'general';
                                break;
                        }
                    }
                ?>
                <tr>
                    <td data-label="Equipment">
                        <div class="equipment-info">
                            <div class="equipment-icon <?php echo $equipment_type_class; ?>">
                                <i class="fas <?php echo $icon_class; ?>"></i>
                            </div>
                            <div class="equipment-details">
                                <h6><?php echo htmlspecialchars($assignment['item_number'] ?? 'N/A'); ?></h6>
                                <small><?php echo htmlspecialchars($assignment['equipment_description'] ?? 'Unknown Equipment'); ?></small>
                            </div>
                        </div>
                    </td>
                    
                    <td data-label="User">
                        <div class="user-info">
                            <span class="user-name"><?php echo htmlspecialchars($assignment['user_name'] ?? 'N/A'); ?></span>
                            <span class="user-email">
                                <i class="fas fa-envelope"></i>
                                <?php echo htmlspecialchars($assignment['user_email'] ?? 'N/A'); ?>
                            </span>
                        </div>
                    </td>
                    
                    <td data-label="Assigned Date">
                        <div class="date-display">
                            <span class="date-main"><?php echo date('M d, Y', strtotime($assignment['assigned_date'])); ?></span>
                            <span class="date-time">
                                <i class="far fa-clock"></i>
                                <?php echo date('g:i A', strtotime($assignment['assigned_date'])); ?>
                            </span>
                        </div>
                    </td>
                    
                    <td data-label="Duration">
                        <span class="duration-badge">
                            <?php echo $assignment['days_assigned']; ?> days
                        </span>
                        <?php if ($assignment['returned_date']): ?>
                        <div class="duration-detail">
                            <i class="fas fa-calendar-check"></i>
                            Returned: <?php echo date('M d, Y', strtotime($assignment['returned_date'])); ?>
                        </div>
                        <?php else: ?>
                        <div class="duration-detail" style="color: #fd7e14;">
                            <i class="fas fa-hourglass-half"></i>
                            Currently assigned
                        </div>
                        <?php endif; ?>
                    </td>
                    
                    <td data-label="Status">
                        <span class="status-badge <?php echo $assignment['status']; ?>">
                            <i class="fas fa-<?php 
                                echo $assignment['status'] == 'active' ? 'check-circle' : 
                                    ($assignment['status'] == 'returned' ? 'undo-alt' : 
                                    ($assignment['status'] == 'maintenance' ? 'tools' : 'exchange-alt')); 
                            ?>"></i>
                            <?php echo ucfirst($assignment['status']); ?>
                        </span>
                    </td>
                    
                    <td data-label="Assigned By">
                        <div class="assigned-by">
                            <div class="avatar">
                                <?php 
                                $name_parts = explode(' ', $assignment['assigned_by_name'] ?? 'Unknown');
                                $initials = '';
                                foreach ($name_parts as $part) {
                                    $initials .= strtoupper(substr($part, 0, 1));
                                }
                                echo substr($initials, 0, 2);
                                ?>
                            </div>
                            <span class="name"><?php echo htmlspecialchars($assignment['assigned_by_name'] ?? 'Unknown'); ?></span>
                        </div>
                    </td>
                    
                    <td data-label="Location">
                        <?php if (!empty($assignment['location_name'])): ?>
                        <span class="location-badge">
                            <i class="fas fa-map-marker-alt"></i>
                            <?php echo htmlspecialchars($assignment['location_name']); ?>
                        </span>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    
                    <td data-label="Notes" class="notes-cell">
                        <?php if ($assignment['notes']): ?>
                        <div class="notes-preview" title="<?php echo htmlspecialchars($assignment['notes']); ?>">
                            <i class="fas fa-sticky-note"></i>
                            <span><?php echo htmlspecialchars(strlen($assignment['notes']) > 30 ? substr($assignment['notes'], 0, 30) . '...' : $assignment['notes']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($assignment['maintenance_reason']): ?>
                        <div class="maintenance-info warning">
                            <i class="fas fa-tools me-1"></i>
                            <strong>Maintenance:</strong> <?php echo htmlspecialchars($assignment['maintenance_reason']); ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($assignment['maintenance_fix_details']): ?>
                        <div class="maintenance-info success">
                            <i class="fas fa-check-circle me-1"></i>
                            <strong>Fix:</strong> <?php echo htmlspecialchars($assignment['maintenance_fix_details']); ?>
                            <?php if ($assignment['maintenance_resolved_by_name']): ?>
                            <br><small>by <?php echo htmlspecialchars($assignment['maintenance_resolved_by_name']); ?> on <?php echo date('M d, Y', strtotime($assignment['maintenance_resolved_date'])); ?></small>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!$assignment['notes'] && !$assignment['maintenance_reason'] && !$assignment['maintenance_fix_details']): ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Monthly Trends Card (Optional) -->
<?php if (!empty($monthly_trends)): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="card" style="border-radius: 20px; border: 1px solid var(--history-purple-mint);">
            <div class="card-header bg-transparent" style="border-bottom: 1px solid var(--history-purple-mint); padding: 1.5rem;">
                <h5 class="mb-0" style="color: var(--history-purple-dark);">
                    <i class="fas fa-chart-line me-2" style="color: var(--history-purple);"></i>
                    Assignment Trends (Last 6 Months)
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($monthly_trends as $trend): ?>
                    <div class="col-md-2 col-6 mb-3">
                        <div class="text-center p-3 rounded" style="background: var(--history-purple-soft);">
                            <div class="small text-muted"><?php echo date('M Y', strtotime($trend['month'] . '-01')); ?></div>
                            <div class="h3 mb-0" style="color: var(--history-purple-dark);"><?php echo $trend['assignments']; ?></div>
                            <small class="text-muted">assignments</small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
$(document).ready(function() {
    // Initialize DataTable with custom options
    if ($.fn.DataTable.isDataTable('#assignmentTable')) {
        $('#assignmentTable').DataTable().destroy();
    }

    $('#assignmentTable').DataTable({
        "pageLength": 25,
        "order": [[2, "desc"]], // Sort by assigned date descending
        "columnDefs": [
            { "orderable": false, "targets": [7] } // Disable sorting on notes column
        ],
        "language": {
            "search": "<i class='fas fa-search me-2'></i>",
            "searchPlaceholder": "Search records...",
            "paginate": {
                "previous": "<i class='fas fa-chevron-left'></i>",
                "next": "<i class='fas fa-chevron-right'></i>"
            },
            "emptyTable": "No data available in table",
            "info": "Showing _START_ to _END_ of _TOTAL_ entries",
            "infoEmpty": "Showing 0 to 0 of 0 entries",
            "infoFiltered": "(filtered from _MAX_ total entries)",
            "lengthMenu": "Show _MENU_ entries"
        },
        "initComplete": function() {
            $('.dataTables_filter input').attr('placeholder', 'Search...');
        }
    });
});

// Export to Excel function
function exportToExcel() {
    // Create a CSV from the table data
    let csv = [];
    let rows = document.querySelectorAll('#assignmentTable tr');
    
    rows.forEach(row => {
        let rowData = [];
        let cols = row.querySelectorAll('td, th');
        cols.forEach(col => {
            // Get text content, clean it up
            let text = col.textContent.trim().replace(/\s+/g, ' ').replace(/[\n\r]/g, ' ');
            rowData.push('"' + text + '"');
        });
        csv.push(rowData.join(','));
    });
    
    // Download as CSV
    let csvContent = csv.join('\n');
    let blob = new Blob([csvContent], { type: 'text/csv' });
    let url = window.URL.createObjectURL(blob);
    let a = document.createElement('a');
    a.href = url;
    a.download = 'assignment_history_' + new Date().toISOString().slice(0,10) + '.csv';
    a.click();
    
    Swal.fire({
        icon: 'success',
        title: 'Export Started',
        text: 'Your file is being downloaded.',
        timer: 2000,
        showConfirmButton: false
    });
}
</script>

<?php include '../includes/footer.php'; ?>