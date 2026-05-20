<?php
session_start();
date_default_timezone_set('Asia/Manila');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get enhanced statistics from all inventory tables
$stats = [];

// Get computer inventory statistics
try {
    $query = "SELECT 
                COUNT(*) as total_computers,
                SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_count,
                SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as assigned_count,
                SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_count,
                SUM(CASE WHEN status = 'damaged' THEN 1 ELSE 0 END) as damaged_count,
                SUM(CASE WHEN condition_status IN ('Excellent', 'Good') THEN 1 ELSE 0 END) as good_condition,
                SUM(CASE WHEN condition_status = 'Fair' THEN 1 ELSE 0 END) as fair_condition,
                SUM(CASE WHEN condition_status IN ('Poor', 'Damaged') THEN 1 ELSE 0 END) as poor_condition
              FROM computer_inventory
              WHERE (is_condemned = 0 OR is_condemned IS NULL)";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $inventory_stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $inventory_stats = [
        'total_computers' => 0,
        'available_count' => 0,
        'assigned_count' => 0,
        'maintenance_count' => 0,
        'damaged_count' => 0,
        'good_condition' => 0,
        'fair_condition' => 0,
        'poor_condition' => 0
    ];
}

// Get all equipment count from all tables
try {
    $query = "SELECT 
                (SELECT COUNT(*) FROM computer_inventory WHERE (is_condemned = 0 OR is_condemned IS NULL)) +
                (SELECT COUNT(*) FROM kitchen_equipment WHERE (is_condemned = 0 OR is_condemned IS NULL)) +
                (SELECT COUNT(*) FROM office_equipment WHERE (is_condemned = 0 OR is_condemned IS NULL)) +
                (SELECT COUNT(*) FROM lab_equipment WHERE (is_condemned = 0 OR is_condemned IS NULL)) +
                (SELECT COUNT(*) FROM general_equipment WHERE (is_condemned = 0 OR is_condemned IS NULL)) as total_all_equipment";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $total_all = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_all_equipment = $total_all['total_all_equipment'] ?? 0;
} catch (Exception $e) {
    $total_all_equipment = 0;
}

// Get location-based statistics
try {
    $query = "SELECT 
                l.id,
                l.location_name,
                l.campus,
                COUNT(DISTINCT ci.id) as computer_count,
                COUNT(DISTINCT ke.id) as kitchen_count,
                COUNT(DISTINCT oe.id) as office_count,
                COUNT(DISTINCT le.id) as lab_count,
                COUNT(DISTINCT ge.id) as general_count,
                (COUNT(DISTINCT ci.id) + COUNT(DISTINCT ke.id) + COUNT(DISTINCT oe.id) + COUNT(DISTINCT le.id) + COUNT(DISTINCT ge.id)) as total_equipment
              FROM locations l 
              LEFT JOIN computer_inventory ci ON l.id = ci.location_id AND (ci.is_condemned = 0 OR ci.is_condemned IS NULL)
              LEFT JOIN kitchen_equipment ke ON l.id = ke.location_id AND (ke.is_condemned = 0 OR ke.is_condemned IS NULL)
              LEFT JOIN office_equipment oe ON l.id = oe.location_id AND (oe.is_condemned = 0 OR oe.is_condemned IS NULL)
              LEFT JOIN lab_equipment le ON l.id = le.location_id AND (le.is_condemned = 0 OR le.is_condemned IS NULL)
              LEFT JOIN general_equipment ge ON l.id = ge.location_id AND (ge.is_condemned = 0 OR ge.is_condemned IS NULL)
              GROUP BY l.id, l.location_name, l.campus
              HAVING total_equipment > 0
              ORDER BY total_equipment DESC
              LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $top_locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $top_locations = [];
}

// Get all locations count
try {
    $query = "SELECT COUNT(*) as total FROM locations WHERE is_active = 1 OR is_active IS NULL";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $location_count = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $location_count = ['total' => 0];
}

// Count users
try {
    $query = "SELECT COUNT(*) as total FROM users WHERE role = 'user' AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $user_count = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $user_count = ['total' => 0];
}

// Count admin users
try {
    $query = "SELECT COUNT(*) as total FROM users WHERE role = 'admin' AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $admin_count = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $admin_count = ['total' => 0];
}

// Get peripheral issues count
try {
    $query = "SELECT COUNT(*) as issues_count FROM computer_inventory 
              WHERE (keyboard_status != 'OK' OR mouse_status != 'OK' OR 
                    power_cord_status != 'OK' OR hdmi_status != 'OK')
              AND (is_condemned = 0 OR is_condemned IS NULL)";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $peripheral_issues = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $peripheral_issues = ['issues_count' => 0];
}

// Get condemned equipment statistics
try {
    $query = "SELECT 
                COUNT(*) as total_condemned,
                SUM(CASE WHEN disposal_status = 'pending' THEN 1 ELSE 0 END) as pending_disposal,
                SUM(CASE WHEN category = 'System Unit' OR category = 'All in one' THEN 1 ELSE 0 END) as system_units,
                SUM(CASE WHEN category = 'Monitor' THEN 1 ELSE 0 END) as monitors
              FROM condemned_equipment";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $condemned_stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $condemned_stats = [
        'total_condemned' => 0,
        'pending_disposal' => 0,
        'system_units' => 0,
        'monitors' => 0
    ];
}

// Get recent assignments
try {
    $query = "SELECT 
                ah.*,
                ci.item_number,
                ci.computer_set_description,
                l.location_name,
                u.full_name as user_name,
                au.full_name as assigned_by_name
              FROM assignment_history ah
              LEFT JOIN computer_inventory ci ON ah.computer_id = ci.id
              LEFT JOIN locations l ON ah.location_id = l.id
              LEFT JOIN users u ON ah.user_id = u.id
              LEFT JOIN users au ON ah.assigned_by = au.id
              ORDER BY ah.assigned_date DESC
              LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $recent_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_assignments = [];
}

// Get consumables status
try {
    $query = "SELECT 
                COUNT(*) as total_items,
                SUM(CASE WHEN status = 'Low' THEN 1 ELSE 0 END) as low_stock,
                SUM(CASE WHEN status = 'Out of Stock' THEN 1 ELSE 0 END) as out_of_stock
              FROM consumables";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $consumables_stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $consumables_stats = [
        'total_items' => 0,
        'low_stock' => 0,
        'out_of_stock' => 0
    ];
}

$page_title = "Dashboard";
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
}

/* Welcome Banner */
.welcome-banner {
    background: linear-gradient(135deg, var(--ucc-green-primary) 0%, var(--ucc-green-dark) 100%);
    border-radius: 24px;
    padding: 2rem 2.5rem;
    margin-bottom: 2rem;
    color: white;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(46, 125, 50, 0.15);
}

.welcome-banner::before {
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

.welcome-content {
    position: relative;
    z-index: 2;
}

.welcome-title {
    font-size: 2.2rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.welcome-subtitle {
    font-size: 1rem;
    opacity: 0.9;
    margin-bottom: 0;
}

.welcome-stats {
    display: flex;
    gap: 2rem;
    margin-top: 1rem;
}

.welcome-stat-item {
    text-align: center;
    background: rgba(255,255,255,0.1);
    padding: 0.8rem 1.5rem;
    border-radius: 50px;
    backdrop-filter: blur(10px);
}

.welcome-stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    display: block;
    line-height: 1;
}

.welcome-stat-label {
    font-size: 0.8rem;
    opacity: 0.9;
}

/* Stats Cards Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
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
    position: relative;
    overflow: hidden;
}

.stat-card::after {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg, var(--ucc-green-soft) 0%, transparent 100%);
    border-radius: 50%;
    opacity: 0.5;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(46, 125, 50, 0.15);
}

.stat-card:hover::after {
    transform: scale(1.5);
    opacity: 0.8;
}

.stat-icon-wrapper {
    width: 70px;
    height: 70px;
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: white;
    position: relative;
    z-index: 2;
}

.stat-icon-wrapper.primary { background: linear-gradient(135deg, var(--ucc-green-primary) 0%, var(--ucc-green-secondary) 100%); }
.stat-icon-wrapper.success { background: linear-gradient(135deg, #43A047 0%, #66BB6A 100%); }
.stat-icon-wrapper.info { background: linear-gradient(135deg, #0288D1 0%, #4FC3F7 100%); }
.stat-icon-wrapper.warning { background: linear-gradient(135deg, #F57C00 0%, #FFB74D 100%); }
.stat-icon-wrapper.danger { background: linear-gradient(135deg, #D32F2F 0%, #EF5350 100%); }
.stat-icon-wrapper.secondary { background: linear-gradient(135deg, #546E7A 0%, #90A4AE 100%); }

.stat-content {
    flex: 1;
    position: relative;
    z-index: 2;
}

.stat-content h3 {
    font-size: 2.2rem;
    font-weight: 800;
    margin: 0;
    line-height: 1.2;
    color: #1a1c2e;
}

.stat-content p {
    margin: 0;
    color: #546E7A;
    font-size: 0.9rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-trend {
    font-size: 0.8rem;
    color: var(--ucc-green-primary);
    margin-top: 0.3rem;
}

.stat-trend i {
    margin-right: 0.2rem;
}

/* Section Headers */
.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
}

.section-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--ucc-green-dark);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.section-title i {
    color: var(--ucc-green-primary);
    font-size: 1.5rem;
}

.section-link {
    color: var(--ucc-green-primary);
    text-decoration: none;
    font-weight: 500;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.section-link:hover {
    color: var(--ucc-green-dark);
    transform: translateX(5px);
}

/* Health Overview Card */
.health-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
    border: 1px solid var(--ucc-green-mint);
    height: 100%;
}

.health-card-header {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--ucc-green-soft);
}

.health-card-header i {
    font-size: 1.5rem;
    color: var(--ucc-green-primary);
}

.health-card-header h6 {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--ucc-green-dark);
    margin: 0;
}

/* Progress Bars */
.progress-custom {
    height: 30px;
    border-radius: 15px;
    background: var(--ucc-green-soft);
    margin-bottom: 1rem;
    overflow: hidden;
}

.progress-custom .progress-bar {
    position: relative;
    overflow: visible;
    font-weight: 600;
    font-size: 0.8rem;
    line-height: 30px;
    padding: 0 10px;
}

.progress-custom .progress-bar.bg-success { background: linear-gradient(90deg, var(--ucc-green-primary) 0%, var(--ucc-green-secondary) 100%) !important; }
.progress-custom .progress-bar.bg-primary { background: linear-gradient(90deg, #1976D2 0%, #42A5F5 100%) !important; }
.progress-custom .progress-bar.bg-warning { background: linear-gradient(90deg, #F57C00 0%, #FFB74D 100%) !important; }
.progress-custom .progress-bar.bg-danger { background: linear-gradient(90deg, #D32F2F 0%, #EF5350 100%) !important; }

/* Legend Items */
.legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: #546E7A;
}

.legend-color {
    width: 12px;
    height: 12px;
    border-radius: 4px;
}

.legend-color.available { background: var(--ucc-green-primary); }
.legend-color.assigned { background: #1976D2; }
.legend-color.maintenance { background: #F57C00; }
.legend-color.damaged { background: #D32F2F; }

/* Metric Boxes */
.metric-box {
    text-align: center;
    padding: 1rem;
    background: var(--ucc-green-soft);
    border-radius: 12px;
    transition: all 0.3s ease;
}

.metric-box:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(46, 125, 50, 0.1);
}

.metric-value {
    font-size: 2rem;
    font-weight: 800;
    color: var(--ucc-green-dark);
    line-height: 1;
    margin-bottom: 0.3rem;
}

.metric-label {
    font-size: 0.8rem;
    color: #546E7A;
    font-weight: 500;
}

/* Condemned Items */
.condemned-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.8rem;
    background: var(--ucc-green-soft);
    border-radius: 10px;
    margin-bottom: 0.5rem;
}

.condemned-item:last-child {
    margin-bottom: 0;
}

.condemned-item span {
    font-weight: 600;
    color: var(--ucc-green-dark);
}

/* Activity Timeline */
.activity-timeline {
    max-height: 300px;
    overflow-y: auto;
    padding-right: 0.5rem;
}

.timeline-item {
    display: flex;
    gap: 1rem;
    padding: 1rem 0;
    border-bottom: 1px solid var(--ucc-green-soft);
}

.timeline-item:last-child {
    border-bottom: none;
}

.timeline-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: var(--ucc-green-soft);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--ucc-green-primary);
    font-size: 1.2rem;
    flex-shrink: 0;
}

.timeline-content {
    flex: 1;
}

.timeline-title {
    font-weight: 600;
    color: var(--ucc-green-dark);
    margin-bottom: 0.2rem;
    font-size: 0.95rem;
}

.timeline-subtitle {
    color: #546E7A;
    font-size: 0.8rem;
    margin-bottom: 0.3rem;
}

.timeline-time {
    color: #90A4AE;
    font-size: 0.7rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

/* Location Table */
.location-table {
    width: 100%;
    border-collapse: collapse;
}

.location-table th {
    text-align: left;
    padding: 1rem;
    background: var(--ucc-green-soft);
    color: var(--ucc-green-dark);
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.location-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--ucc-green-soft);
}

.location-table tr:hover td {
    background: var(--ucc-green-soft);
}

.location-badge {
    padding: 0.3rem 0.8rem;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
}

.location-badge.total { background: var(--ucc-green-soft); color: var(--ucc-green-dark); }
.location-badge.available { background: #E8F5E9; color: var(--ucc-green-primary); }
.location-badge.assigned { background: #E3F2FD; color: #1976D2; }
.location-badge.maintenance { background: #FFF3E0; color: #F57C00; }

/* Quick Actions */
.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 0.8rem;
    margin-top: 1rem;
}

.quick-action-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 1rem;
    background: var(--ucc-green-soft);
    border-radius: 12px;
    text-decoration: none;
    color: var(--ucc-green-dark);
    transition: all 0.3s ease;
}

.quick-action-item:hover {
    background: linear-gradient(135deg, var(--ucc-green-primary) 0%, var(--ucc-green-secondary) 100%);
    color: white;
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(46, 125, 50, 0.2);
}

.quick-action-item i {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}

.quick-action-item span {
    font-size: 0.8rem;
    font-weight: 500;
}

#consumableMobileQRModal .modal-content {
    border: none;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(46, 125, 50, 0.3);
}

#consumableMobileQRModal .modal-header {
    padding: 1.5rem;
    border: none;
}

#consumableMobileQRModal .modal-body {
    padding: 2rem;
}

#consumableMobileQRModal .btn-close-white {
    filter: brightness(0) invert(1);
}

#consumableMobileQRModal img {
    transition: transform 0.3s ease;
    box-shadow: 0 10px 30px rgba(46, 125, 50, 0.15);
}

#consumableMobileQRModal img:hover {
    transform: scale(1.05);
}

#consumableMobileQRModal .alert {
    border-radius: 50px;
    padding: 0.8rem 1.2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

#consumableMobileQRModal .btn-outline-success {
    border-color: var(--ucc-green-primary);
    color: var(--ucc-green-primary);
}

#consumableMobileQRModal .btn-outline-success:hover {
    background: var(--ucc-green-primary);
    color: white;
}

#consumableMobileQRModal .btn-success {
    background: linear-gradient(135deg, var(--ucc-green-primary) 0%, var(--ucc-green-dark) 100%);
    border: none;
}

#consumableMobileQRModal .btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(46, 125, 50, 0.3);
}

/* Responsive */
@media (max-width: 768px) {
    .welcome-title {
        font-size: 1.5rem;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .welcome-stats {
        flex-wrap: wrap;
        gap: 0.8rem;
    }
    
    .welcome-stat-item {
        padding: 0.5rem 1rem;
    }
    
    .welcome-stat-number {
        font-size: 1.2rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
}
</style>

<!-- Welcome Banner -->
<div class="welcome-banner">
    <div class="welcome-content">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="welcome-title">
                    <i class="fas fa-chart-line"></i>
                    <span>Good <?php echo date('H') < 12 ? 'Morning' : (date('H') < 18 ? 'Afternoon' : 'Evening'); ?>, <?php echo explode(' ', $_SESSION['full_name'])[0]; ?>!</span>
                </div>
                <p class="welcome-subtitle">
                    Here's what's happening with your inventory today. Track equipment status, monitor locations, and manage resources efficiently.
                </p>
                <div class="welcome-stats">
                    <div class="welcome-stat-item">
                        <span class="welcome-stat-number"><?php echo date('M d, Y'); ?></span>
                        <span class="welcome-stat-label">Current Date</span>
                    </div>
                    <div class="welcome-stat-item">
                        <span class="welcome-stat-number"><?php echo date('h:i A'); ?></span>
                        <span class="welcome-stat-label">System Time</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a href="all_equipment.php" class="btn btn-light btn-lg px-4" style="color: var(--ucc-green-primary); border: none;">
                    <i class="fas fa-plus-circle me-2"></i>Add Equipment
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon-wrapper primary">
            <i class="fas fa-desktop"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo number_format($inventory_stats['total_computers']); ?></h3>
            <p>Computer Units</p>
            <div class="stat-trend">
                <i class="fas fa-arrow-up"></i> +12% from last month
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon-wrapper success">
            <i class="fas fa-cubes"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo number_format($total_all_equipment); ?></h3>
            <p>Total Equipment</p>
            <div class="stat-trend">
                <i class="fas fa-layer-group"></i> All categories
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon-wrapper info">
            <i class="fas fa-map-marker-alt"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $location_count['total']; ?></h3>
            <p>Active Locations</p>
            <div class="stat-trend">
                <i class="fas fa-building"></i> Lab rooms & offices
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon-wrapper warning">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $user_count['total']; ?></h3>
            <p>Active Users</p>
            <div class="stat-trend">
                <i class="fas fa-user-tie"></i> <?php echo $admin_count['total']; ?> administrators
            </div>
        </div>
    </div>
</div>

<!-- System Health and Condemned Section -->
<div class="row mb-4">
    <div class="col-lg-8">
        <div class="health-card">
            <div class="health-card-header">
                <i class="fas fa-heartbeat"></i>
                <h6>System Health Overview</h6>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <h6 class="text-muted mb-3">Inventory Status Distribution</h6>
                    <?php 
                    $total = $inventory_stats['total_computers'];
                    if ($total > 0):
                        $available = $inventory_stats['available_count'];
                        $assigned = $inventory_stats['assigned_count'];
                        $maintenance = $inventory_stats['maintenance_count'];
                        $damaged = $inventory_stats['damaged_count'];
                    ?>
                    <div class="progress-custom">
                        <div class="progress-bar bg-success" style="width: <?php echo ($available/$total)*100; ?>%">
                            <?php echo $available; ?> Available
                        </div>
                        <div class="progress-bar bg-primary" style="width: <?php echo ($assigned/$total)*100; ?>%">
                            <?php echo $assigned; ?> Assigned
                        </div>
                        <div class="progress-bar bg-warning" style="width: <?php echo ($maintenance/$total)*100; ?>%">
                            <?php echo $maintenance; ?> Maint
                        </div>
                        <div class="progress-bar bg-danger" style="width: <?php echo ($damaged/$total)*100; ?>%">
                            <?php echo $damaged; ?> Damaged
                        </div>
                    </div>
                    
                    <div class="d-flex flex-wrap gap-3 mt-3">
                        <div class="legend-item">
                            <span class="legend-color available"></span>
                            <span>Available (<?php echo $available; ?>)</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color assigned"></span>
                            <span>Assigned (<?php echo $assigned; ?>)</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color maintenance"></span>
                            <span>Maintenance (<?php echo $maintenance; ?>)</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color damaged"></span>
                            <span>Damaged (<?php echo $damaged; ?>)</span>
                        </div>
                    </div>
                    <?php else: ?>
                    <p class="text-muted text-center py-4">No inventory data available</p>
                    <?php endif; ?>
                </div>
                
                <div class="col-md-6">
                    <h6 class="text-muted mb-3">Equipment Condition</h6>
                    <div class="row g-3">
                        <div class="col-4">
                            <div class="metric-box">
                                <div class="metric-value text-success"><?php echo $inventory_stats['good_condition']; ?></div>
                                <div class="metric-label">Excellent/Good</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="metric-box">
                                <div class="metric-value text-warning"><?php echo $inventory_stats['fair_condition']; ?></div>
                                <div class="metric-label">Fair</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="metric-box">
                                <div class="metric-value text-danger"><?php echo $inventory_stats['poor_condition']; ?></div>
                                <div class="metric-label">Poor/Damaged</div>
                            </div>
                        </div>
                    </div>
                    
                    <h6 class="text-muted mt-4 mb-3">Quick Stats</h6>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span><i class="fas fa-keyboard text-primary me-2"></i>Peripheral Issues</span>
                        <span class="badge bg-warning"><?php echo $peripheral_issues['issues_count']; ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span><i class="fas fa-boxes text-info me-2"></i>Consumables Total</span>
                        <span class="badge bg-info"><?php echo $consumables_stats['total_items']; ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-exclamation-circle text-danger me-2"></i>Low/Out of Stock</span>
                        <span>
                            <span class="badge bg-warning me-1"><?php echo $consumables_stats['low_stock']; ?> Low</span>
                            <span class="badge bg-danger"><?php echo $consumables_stats['out_of_stock']; ?> Out</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="health-card">
            <div class="health-card-header">
                <i class="fas fa-exclamation-triangle text-danger"></i>
                <h6>Condemned Equipment</h6>
            </div>
            
            <div class="text-center mb-4">
                <div class="metric-value text-danger"><?php echo $condemned_stats['total_condemned']; ?></div>
                <div class="metric-label">Total Condemned Items</div>
                <div class="mt-2">
                    <span class="badge bg-warning"><?php echo $condemned_stats['pending_disposal']; ?> Pending Disposal</span>
                </div>
            </div>
            
            <div class="condemned-item">
                <span><i class="fas fa-server me-2"></i>System Units</span>
                <span class="badge bg-primary"><?php echo $condemned_stats['system_units']; ?></span>
            </div>
            <div class="condemned-item">
                <span><i class="fas fa-tv me-2"></i>Monitors</span>
                <span class="badge bg-info"><?php echo $condemned_stats['monitors']; ?></span>
            </div>
            
            <div class="d-grid mt-3">
                <a href="condemned.php" class="btn btn-outline-danger">
                    <i class="fas fa-arrow-right me-2"></i>Manage Condemned Items
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity and Top Locations -->
<div class="row">
    <div class="col-lg-7">
        <div class="health-card">
            <div class="health-card-header">
                <i class="fas fa-history"></i>
                <h6>Recent Assignment Activity</h6>
                <a href="assignment_history.php" class="section-link ms-auto">
                    View All <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <?php if (empty($recent_assignments)): ?>
            <div class="text-center py-4">
                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                <p class="text-muted">No recent assignment activity</p>
            </div>
            <?php else: ?>
            <div class="activity-timeline">
                <?php foreach ($recent_assignments as $activity): ?>
                <div class="timeline-item">
                    <div class="timeline-icon">
                        <i class="fas fa-<?php echo $activity['status'] == 'active' ? 'check' : 'exchange-alt'; ?>"></i>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-title">
                            <?php echo htmlspecialchars($activity['computer_set_description'] ?? 'Equipment'); ?>
                            <span class="badge bg-<?php echo $activity['status'] == 'active' ? 'success' : 'secondary'; ?> ms-2">
                                <?php echo ucfirst($activity['status']); ?>
                            </span>
                        </div>
                        <div class="timeline-subtitle">
                            <i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($activity['location_name'] ?? 'Unknown'); ?>
                            <i class="fas fa-user ms-2 me-1"></i> <?php echo htmlspecialchars($activity['user_name'] ?? 'Unknown'); ?>
                        </div>
                        <div class="timeline-time">
                            <i class="far fa-clock"></i>
                            <?php echo date('M d, Y h:i A', strtotime($activity['assigned_date'])); ?>
                            <span class="ms-2">by <?php echo htmlspecialchars($activity['assigned_by_name'] ?? 'System'); ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="col-lg-5">
        <div class="health-card">
            <div class="health-card-header">
                <i class="fas fa-trophy"></i>
                <h6>Top Locations by Equipment</h6>
                <a href="locations.php" class="section-link ms-auto">
                    View All <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <?php if (empty($top_locations)): ?>
            <div class="text-center py-4">
                <i class="fas fa-map-marker-alt fa-3x text-muted mb-3"></i>
                <p class="text-muted">No location data available</p>
            </div>
            <?php else: ?>
            <table class="location-table">
                <thead>
                    <tr>
                        <th>Location</th>
                        <th>Campus</th>
                        <th class="text-center">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_locations as $loc): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($loc['location_name']); ?></strong>
                        </td>
                        <td>
                            <span class="badge bg-secondary"><?php echo htmlspecialchars($loc['campus'] ?? 'Main'); ?></span>
                        </td>
                        <td class="text-center">
                            <span class="location-badge total"><?php echo $loc['total_equipment']; ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="mt-3">
                <div class="d-flex justify-content-between align-items-center small">
                    <span><i class="fas fa-circle text-success me-1"></i> Computers</span>
                    <span><i class="fas fa-circle text-info me-1"></i> Kitchen</span>
                    <span><i class="fas fa-circle text-warning me-1"></i> Office</span>
                    <span><i class="fas fa-circle text-danger me-1"></i> Lab</span>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Quick Actions Section -->
<div class="row mt-4">
    <div class="col-12">
        <div class="health-card">
            <div class="health-card-header">
                <i class="fas fa-bolt"></i>
                <h6>Quick Actions</h6>
            </div>
            
            <div class="quick-actions-grid">
                <a href="all_equipment.php" class="quick-action-item">
                    <i class="fas fa-list-alt"></i>
                    <span>All Equipment</span>
                </a>
                <a href="locations.php" class="quick-action-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>Locations</span>
                </a>
                <a href="users.php" class="quick-action-item">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
                <a href="consumables.php" class="quick-action-item">
                    <i class="fas fa-tint"></i>
                    <span>Consumables</span>
                </a>
                <a href="condemned.php" class="quick-action-item">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Condemned</span>
                </a>
                <!-- Changed from direct link to QR Code modal trigger -->
                <a href="#" class="quick-action-item" data-bs-toggle="modal" data-bs-target="#consumableMobileQRModal">
                    <i class="fas fa-qrcode"></i>
                    <span>Consumable (Mobile Site)</span>
                </a>
                <a href="assignment_history.php" class="quick-action-item">
                    <i class="fas fa-history"></i>
                    <span>History</span>
                </a>
                <a href="location_types.php" class="quick-action-item">
                    <i class="fas fa-tags"></i>
                    <span>Categories</span>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Consumable Mobile Site QR Code Modal -->
<div class="modal fade" id="consumableMobileQRModal" tabindex="-1" aria-labelledby="consumableMobileQRModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--ucc-green-primary) 0%, var(--ucc-green-dark) 100%);">
                <h5 class="modal-title text-white" id="consumableMobileQRModalLabel">
                    <i class="fas fa-qrcode me-2"></i>
                    Consumable Mobile Site QR Code
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="mb-4">
                    <img src="../assets/ucc_cms_qr_code.png" 
                         alt="Consumable Mobile Site QR Code" 
                         class="img-fluid" 
                         style="max-width: 250px; border: 5px solid var(--ucc-green-soft); border-radius: 20px;"
                         onerror="this.onerror=null; this.src='https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=<?php echo urlencode('http://consumable.ucc-caloocan.com/login.php'); ?>';">
                </div>
                
                <h6 class="fw-bold mb-3" style="color: var(--ucc-green-dark);">Scan to Access Mobile Site</h6>
                
                <p class="text-muted small mb-3">
                    <i class="fas fa-info-circle me-1" style="color: var(--ucc-green-primary);"></i>
                    Open your mobile camera and scan the QR code to access the consumable mobile site.
                </p>
                
                <div class="alert alert-success border-0" style="background: var(--ucc-green-soft); color: var(--ucc-green-dark);">
                    <i class="fas fa-link me-2"></i>
                    <span class="small" id="mobileSiteUrl">http://consumable.ucc-caloocan.com/login.php</span>
                    <button class="btn btn-sm btn-outline-success ms-2" onclick="copyMobileUrl()">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
                
                <hr class="my-3">
                
                <div class="d-flex justify-content-center gap-2">
                    <a href="http://consumable.ucc-caloocan.com/login.php" class="btn btn-outline-success btn-sm" target="_blank">
                        <i class="fas fa-external-link-alt me-1"></i> Open Directly
                    </a>
                    <button class="btn btn-success btn-sm" onclick="downloadQRCode()">
                        <i class="fas fa-download me-1"></i> Download QR
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyMobileUrl() {
    const url = document.getElementById('mobileSiteUrl').textContent;
    navigator.clipboard.writeText(url).then(function() {
        Swal.fire({
            icon: 'success',
            title: 'Copied!',
            text: 'URL copied to clipboard',
            timer: 1500,
            showConfirmButton: false
        });
    }, function() {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to copy URL'
        });
    });
}

function downloadQRCode() {
    // Try to download the actual QR code image
    const img = document.querySelector('#consumableMobileQRModal img');
    if (img && img.src && !img.src.includes('api.qrserver.com')) {
        // If it's a local image, create a download link
        const link = document.createElement('a');
        link.href = img.src;
        link.download = 'consumable_mobile_qr.png';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    } else {
        // If using fallback QR code, show a message
        Swal.fire({
            icon: 'info',
            title: 'QR Code URL',
            text: 'You can save the QR code by right-clicking on it and selecting "Save Image As..."',
            confirmButtonColor: '#2E7D32'
        });
    }
}

// Optional: Add a keyboard shortcut (ESC to close modal)
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = bootstrap.Modal.getInstance(document.getElementById('consumableMobileQRModal'));
        if (modal) {
            modal.hide();
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>