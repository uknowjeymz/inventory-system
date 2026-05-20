<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Check if equipment tables exist, if not create them
try {
    $db->query("SELECT 1 FROM kitchen_equipment LIMIT 1");
} catch (Exception $e) {
    // Tables don't exist, redirect to setup
    header("Location: create_equipment_tables.php");
    exit();
}

// Get all locations for selection with equipment counts based on location type
$query = "SELECT l.id, l.location_name, l.location_type, l.facilitator_id, 
          f.full_name as facilitator_name,
          CASE 
            WHEN l.location_type = 'computer_lab' THEN (SELECT COUNT(*) FROM computer_inventory WHERE location_id = l.id)
            WHEN l.location_type = 'kitchen' THEN (SELECT COUNT(*) FROM kitchen_equipment WHERE location_id = l.id)
            WHEN l.location_type = 'office' THEN (SELECT COUNT(*) FROM office_equipment WHERE location_id = l.id)
            WHEN l.location_type = 'regular_lab' THEN (SELECT COUNT(*) FROM lab_equipment WHERE location_id = l.id)
            ELSE (SELECT COUNT(*) FROM general_equipment WHERE location_id = l.id)
          END as equipment_count
          FROM locations l 
          LEFT JOIN users f ON l.facilitator_id = f.id
          ORDER BY l.location_name";
$stmt = $db->prepare($query);
$stmt->execute();
$locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get equipment statistics from all tables (with fallback for missing tables)
try {
    $query = "SELECT 
              (SELECT COUNT(*) FROM computer_inventory) +
              (SELECT COUNT(*) FROM kitchen_equipment) +
              (SELECT COUNT(*) FROM office_equipment) +
              (SELECT COUNT(*) FROM lab_equipment) +
              (SELECT COUNT(*) FROM general_equipment) as total_equipment,
              (SELECT COUNT(*) FROM computer_inventory WHERE device_type = 'Desktop') as desktop_count,
              (SELECT COUNT(*) FROM computer_inventory WHERE device_type = 'Laptop') as laptop_count,
              (SELECT COUNT(*) FROM computer_inventory WHERE device_type = 'All-in-One') as aio_count,
              (SELECT COUNT(*) FROM kitchen_equipment) as kitchen_count,
              (SELECT COUNT(*) FROM office_equipment) as office_count,
              (SELECT COUNT(*) FROM lab_equipment) as lab_count,
              (SELECT COUNT(*) FROM computer_inventory WHERE status = 'available') +
              (SELECT COUNT(*) FROM kitchen_equipment WHERE status = 'available') +
              (SELECT COUNT(*) FROM office_equipment WHERE status = 'available') +
              (SELECT COUNT(*) FROM lab_equipment WHERE status = 'available') +
              (SELECT COUNT(*) FROM general_equipment WHERE status = 'available') as available_count,
              (SELECT COUNT(*) FROM computer_inventory WHERE status = 'assigned') +
              (SELECT COUNT(*) FROM kitchen_equipment WHERE status = 'assigned') +
              (SELECT COUNT(*) FROM office_equipment WHERE status = 'assigned') +
              (SELECT COUNT(*) FROM lab_equipment WHERE status = 'assigned') +
              (SELECT COUNT(*) FROM general_equipment WHERE status = 'assigned') as assigned_count,
              (SELECT COUNT(*) FROM computer_inventory WHERE status = 'maintenance') +
              (SELECT COUNT(*) FROM kitchen_equipment WHERE status = 'maintenance') +
              (SELECT COUNT(*) FROM office_equipment WHERE status = 'maintenance') +
              (SELECT COUNT(*) FROM lab_equipment WHERE status = 'maintenance') +
              (SELECT COUNT(*) FROM general_equipment WHERE status = 'maintenance') as maintenance_count";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $equipment_stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Fallback to computer inventory only if other tables don't exist
    $query = "SELECT 
              COUNT(*) as total_equipment,
              COUNT(CASE WHEN device_type = 'Desktop' THEN 1 END) as desktop_count,
              COUNT(CASE WHEN device_type = 'Laptop' THEN 1 END) as laptop_count,
              COUNT(CASE WHEN device_type = 'All-in-One' THEN 1 END) as aio_count,
              0 as kitchen_count,
              0 as office_count,
              0 as lab_count,
              COUNT(CASE WHEN status = 'available' THEN 1 END) as available_count,
              COUNT(CASE WHEN status = 'assigned' THEN 1 END) as assigned_count,
              COUNT(CASE WHEN status = 'maintenance' THEN 1 END) as maintenance_count
              FROM computer_inventory";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $equipment_stats = $stmt->fetch(PDO::FETCH_ASSOC);
}

$page_title = "Inventory Monitor";
include '../includes/header.php';
?>

<!-- Modern Header Section with Enhanced Design -->
<div class="inventory-monitor-header mb-5">
    <div class="header-background"></div>
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="page-title-section">
                    <div class="title-animation">
                        <h1 class="page-title mb-3">
                            <div class="title-icon-wrapper">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                            <span class="title-text">Equipment Dashboard</span>
                        </h1>
                        <p class="page-subtitle mb-0">Comprehensive facility and equipment management system</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <div class="monitor-stats-enhanced">
                    <div class="stat-badge primary">
                        <div class="stat-icon">
                            <i class="fas fa-door-open"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo count($locations); ?></span>
                            <span class="stat-label">Locations</span>
                        </div>
                    </div>
                    <div class="stat-badge success">
                        <div class="stat-icon">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo $equipment_stats['total_equipment']; ?></span>
                            <span class="stat-label">Items</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Enhanced Location Selection -->
<div class="locations-grid-section mb-5">
    <div class="section-header-enhanced mb-5">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="section-title-wrapper">
                    <div class="section-icon">
                        <i class="fas fa-door-open"></i>
                    </div>
                    <div class="section-content">
                        <h2 class="section-title">Browse by Location</h2>
                        <p class="section-subtitle mb-0">Click on any location below to see what equipment is available there</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <div class="filter-controls-enhanced">
                    <div class="filter-wrapper">
                        <label class="filter-label">Filter by Type:</label>
                        <select class="form-select-enhanced" id="typeFilter">
                            <option value="">All Types</option>
                            <option value="computer_lab">Computer Labs</option>
                            <option value="regular_lab">Regular Labs</option>
                            <option value="kitchen">Kitchens</option>
                            <option value="office">Offices</option>
                            <option value="storage">Storage Rooms</option>
                            <option value="classroom">Classrooms</option>
                            <option value="library">Libraries</option>
                            <option value="conference">Conference Rooms</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="location-counter">
                        <span class="counter-badge">
                            <span id="locationCount"><?php echo count($locations); ?></span> Locations
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (empty($locations)): ?>
    <div class="empty-state">
        <div class="empty-icon">
            <i class="fas fa-door-open"></i>
        </div>
        <h4>No Locations Set Up Yet</h4>
        <p class="text-muted">You need to add some locations before you can manage equipment by location.</p>
        <a href="locations.php" class="btn btn-primary modern-btn">
            <i class="fas fa-plus me-1"></i> Add Locations
        </a>
    </div>
    <?php else: ?>
    <div class="row location-cards-grid">
        <?php foreach ($locations as $location): ?>
        <div class="col-xl-4 col-lg-6 col-md-6 mb-4 location-item" data-type="<?php echo $location['location_type'] ?? 'computer_lab'; ?>">
            <div class="location-card-enhanced" onclick="window.location.href='inventory.php?location=<?php echo $location['id']; ?>'">
                <!-- Enhanced Card Header -->
                <div class="location-header-enhanced">
                    <div class="header-background-pattern"></div>
                    <div class="location-icon-enhanced">
                        <?php 
                        $type_icons = [
                            'computer_lab' => 'fa-desktop',
                            'regular_lab' => 'fa-flask',
                            'kitchen' => 'fa-utensils',
                            'office' => 'fa-briefcase',
                            'storage' => 'fa-boxes',
                            'classroom' => 'fa-chalkboard-teacher',
                            'library' => 'fa-book',
                            'conference' => 'fa-users',
                            'other' => 'fa-building'
                        ];
                        
                        $location_type = $location['location_type'] ?? 'computer_lab';
                        $icon = $type_icons[$location_type] ?? 'fa-building';
                        ?>
                        <i class="fas <?php echo $icon; ?>"></i>
                    </div>
                    <div class="location-status-indicator">
                        <div class="status-dot <?php echo $location['equipment_count'] > 0 ? 'active' : 'inactive'; ?>"></div>
                    </div>
                </div>
                
                <!-- Enhanced Card Body -->
                <div class="location-body-enhanced">
                    <div class="location-type-badge-enhanced">
                        <?php 
                        $type_labels = [
                            'computer_lab' => 'Computer Lab',
                            'regular_lab' => 'Laboratory',
                            'kitchen' => 'Kitchen',
                            'office' => 'Office',
                            'storage' => 'Storage',
                            'classroom' => 'Classroom',
                            'library' => 'Library',
                            'conference' => 'Conference Room',
                            'other' => 'Other'
                        ];
                        
                        $label = $type_labels[$location_type] ?? 'Other';
                        ?>
                        <span class="type-badge-enhanced type-<?php echo $location_type; ?>">
                            <i class="fas <?php echo $icon; ?>"></i>
                            <?php echo $label; ?>
                        </span>
                    </div>
                    
                    <h4 class="location-name-enhanced"><?php echo htmlspecialchars($location['location_name']); ?></h4>
                    
                    <!-- Enhanced Statistics Grid -->
                    <div class="location-stats-enhanced">
                        <div class="stat-card-mini">
                            <div class="stat-icon-mini">
                                <?php 
                                $equipment_icons = [
                                    'computer_lab' => 'fa-desktop',
                                    'kitchen' => 'fa-utensils',
                                    'office' => 'fa-briefcase',
                                    'regular_lab' => 'fa-flask',
                                    'storage' => 'fa-boxes',
                                    'classroom' => 'fa-chalkboard',
                                    'library' => 'fa-book',
                                    'conference' => 'fa-users',
                                    'other' => 'fa-box'
                                ];
                                $equipment_icon = $equipment_icons[$location_type] ?? 'fa-box';
                                ?>
                                <i class="fas <?php echo $equipment_icon; ?>"></i>
                            </div>
                            <div class="stat-content-mini">
                                <span class="stat-number-mini"><?php echo $location['equipment_count']; ?></span>
                                <span class="stat-label-mini">
                                    <?php 
                                    $equipment_labels = [
                                        'computer_lab' => 'Computers',
                                        'kitchen' => 'Appliances',
                                        'office' => 'Equipment',
                                        'regular_lab' => 'Instruments',
                                        'storage' => 'Items',
                                        'classroom' => 'Equipment',
                                        'library' => 'Resources',
                                        'conference' => 'Equipment',
                                        'other' => 'Items'
                                    ];
                                    echo $equipment_labels[$location_type] ?? 'Items';
                                    ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="stat-card-mini">
                            <div class="stat-icon-mini">
                                <i class="fas fa-<?php echo $location['facilitator_name'] ? 'user-tie' : 'user-slash'; ?>"></i>
                            </div>
                            <div class="stat-content-mini">
                                <span class="stat-name-mini"><?php echo $location['facilitator_name'] ? htmlspecialchars($location['facilitator_name']) : 'No Manager'; ?></span>
                                <span class="stat-label-mini">
                                    <?php 
                                    $manager_titles = [
                                        'computer_lab' => 'Lab Manager',
                                        'kitchen' => 'Kitchen Manager',
                                        'office' => 'Office Manager',
                                        'regular_lab' => 'Lab Supervisor',
                                        'storage' => 'Storage Keeper',
                                        'classroom' => 'Room Manager',
                                        'library' => 'Librarian',
                                        'conference' => 'Room Manager',
                                        'other' => 'Manager'
                                    ];
                                    echo $manager_titles[$location_type] ?? 'Manager';
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Enhanced Card Footer -->
                <div class="location-footer-enhanced">
                    <div class="action-buttons-enhanced">
                        <button class="action-btn-enhanced primary" 
                                onclick="event.stopPropagation(); window.location.href='inventory.php?location=<?php echo $location['id']; ?>'"
                                title="<?php 
                                $view_tooltips = [
                                    'computer_lab' => 'View Computers in This Lab',
                                    'kitchen' => 'View Kitchen Appliances',
                                    'office' => 'View Office Equipment',
                                    'regular_lab' => 'View Lab Instruments',
                                    'storage' => 'View Stored Items',
                                    'classroom' => 'View Classroom Equipment',
                                    'library' => 'View Library Resources',
                                    'conference' => 'View Conference Equipment',
                                    'other' => 'View Equipment'
                                ];
                                echo $view_tooltips[$location_type] ?? 'View Equipment';
                                ?>">
                            <i class="fas fa-eye"></i>
                            <span>View</span>
                        </button>
                        <button class="action-btn-enhanced success" 
                                onclick="event.stopPropagation(); window.location.href='locations.php'"
                                title="<?php 
                                $manage_tooltips = [
                                    'computer_lab' => 'Manage This Computer Lab',
                                    'kitchen' => 'Manage This Kitchen',
                                    'office' => 'Manage This Office',
                                    'regular_lab' => 'Manage This Laboratory',
                                    'storage' => 'Manage This Storage Area',
                                    'classroom' => 'Manage This Classroom',
                                    'library' => 'Manage This Library',
                                    'conference' => 'Manage This Conference Room',
                                    'other' => 'Manage This Location'
                                ];
                                echo $manage_tooltips[$location_type] ?? 'Manage This Location';
                                ?>">
                            <i class="fas fa-cog"></i>
                            <span>Manage</span>
                        </button>
                    </div>
                </div>
                
                <!-- Enhanced Hover Overlay -->
                <div class="location-overlay-enhanced">
                    <div class="overlay-content-enhanced">
                        <div class="overlay-icon">
                            <i class="fas fa-mouse-pointer"></i>
                        </div>
                        <h5 class="overlay-title">
                            <?php 
                            $overlay_labels = [
                                'computer_lab' => 'View Computers',
                                'kitchen' => 'View Appliances',
                                'office' => 'View Equipment',
                                'regular_lab' => 'View Instruments',
                                'storage' => 'View Items',
                                'classroom' => 'View Equipment',
                                'library' => 'View Resources',
                                'conference' => 'View Equipment',
                                'other' => 'View Items'
                            ];
                            echo $overlay_labels[$location_type] ?? 'View Items';
                            ?>
                        </h5>
                        <p class="overlay-subtitle">Click to explore this location</p>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<style>
/* Enhanced Inventory Monitor UI Styles */

/* Enhanced Header Section */
.inventory-monitor-header {
    position: relative;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 20px;
    padding: 3rem 0;
    margin-bottom: 3rem;
    overflow: hidden;
}

.header-background {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: 
        radial-gradient(circle at 20% 80%, rgba(0, 133, 67, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(32, 201, 151, 0.1) 0%, transparent 50%);
    z-index: 1;
}

.inventory-monitor-header .container-fluid {
    position: relative;
    z-index: 2;
}

.title-animation {
    animation: slideInUp 0.8s ease-out;
}

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.page-title {
    font-size: 3rem;
    font-weight: 800;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.title-icon-wrapper {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #008543, #20c997);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 2rem;
    box-shadow: 0 8px 30px rgba(0, 133, 67, 0.3);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.title-text {
    background: linear-gradient(135deg, #008543, #20c997);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.page-subtitle {
    font-size: 1.2rem;
    color: #6c757d;
    font-weight: 500;
}

/* Enhanced Monitor Stats */
.monitor-stats-enhanced {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.stat-badge {
    background: white;
    border-radius: 15px;
    padding: 1rem 1.5rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.3s ease;
    border-left: 4px solid;
    min-width: 120px;
}

.stat-badge.primary {
    border-left-color: #007bff;
}

.stat-badge.success {
    border-left-color: #28a745;
}

.stat-badge:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 25px rgba(0, 0, 0, 0.15);
}

.stat-badge .stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: white;
}

.stat-badge.primary .stat-icon {
    background: linear-gradient(135deg, #007bff, #0056b3);
}

.stat-badge.success .stat-icon {
    background: linear-gradient(135deg, #28a745, #1e7e34);
}

.stat-badge .stat-content {
    display: flex;
    flex-direction: column;
}

.stat-badge .stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: #2c3e50;
    line-height: 1;
}

.stat-badge .stat-label {
    font-size: 0.9rem;
    color: #6c757d;
    font-weight: 500;
}

/* Enhanced Section Header */
.section-header-enhanced {
    margin-bottom: 2rem;
}

.section-title-wrapper {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.section-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #008543, #20c997);
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    box-shadow: 0 4px 15px rgba(0, 133, 67, 0.3);
}

.section-content .section-title {
    font-size: 2rem;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 0.5rem;
}

.section-content .section-subtitle {
    font-size: 1.1rem;
    color: #6c757d;
    margin: 0;
}

/* Enhanced Filter Controls */
.filter-controls-enhanced {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    align-items: flex-end;
}

.filter-wrapper {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.filter-label {
    font-weight: 600;
    color: #2c3e50;
    margin: 0;
}

.form-select-enhanced {
    border: 2px solid #e9ecef;
    border-radius: 10px;
    padding: 0.75rem 1rem;
    font-weight: 500;
    background: white;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    min-width: 200px;
}

.form-select-enhanced:focus {
    border-color: #008543;
    box-shadow: 0 0 0 0.2rem rgba(0, 133, 67, 0.25);
}

.counter-badge {
    background: linear-gradient(135deg, #008543, #20c997);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 25px;
    font-weight: 600;
    box-shadow: 0 4px 15px rgba(0, 133, 67, 0.3);
}

/* Enhanced Location Cards */
.location-cards-grid {
    animation: fadeInGrid 0.8s ease-out;
}

@keyframes fadeInGrid {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.location-item {
    animation: slideInCard 0.6s ease-out forwards;
    opacity: 0;
}

.location-item:nth-child(1) { animation-delay: 0.1s; }
.location-item:nth-child(2) { animation-delay: 0.2s; }
.location-item:nth-child(3) { animation-delay: 0.3s; }
.location-item:nth-child(4) { animation-delay: 0.4s; }
.location-item:nth-child(5) { animation-delay: 0.5s; }
.location-item:nth-child(6) { animation-delay: 0.6s; }

@keyframes slideInCard {
    from {
        opacity: 0;
        transform: translateY(30px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.location-card-enhanced {
    background: white;
    border-radius: 25px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    position: relative;
    overflow: hidden;
    border: 2px solid transparent;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.location-card-enhanced:hover {
    transform: translateY(-10px) scale(1.02);
    box-shadow: 0 20px 50px rgba(0, 133, 67, 0.15);
    border-color: #008543;
}

/* Enhanced Card Header */
.location-header-enhanced {
    position: relative;
    height: 120px;
    background: linear-gradient(135deg, #008543, #20c997);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.header-background-pattern {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: 
        radial-gradient(circle at 20% 20%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
}

.location-icon-enhanced {
    position: relative;
    z-index: 2;
    width: 70px;
    height: 70px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 2rem;
    backdrop-filter: blur(10px);
    border: 2px solid rgba(255, 255, 255, 0.3);
    transition: all 0.3s ease;
}

.location-card-enhanced:hover .location-icon-enhanced {
    transform: scale(1.1) rotate(5deg);
}

.location-status-indicator {
    position: absolute;
    top: 15px;
    right: 15px;
    z-index: 3;
}

.status-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid white;
    animation: pulse-dot 2s infinite;
}

.status-dot.active {
    background: #28a745;
}

.status-dot.inactive {
    background: #6c757d;
}

@keyframes pulse-dot {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.2); }
}

/* Enhanced Card Body */
.location-body-enhanced {
    padding: 2rem;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.location-type-badge-enhanced {
    margin-bottom: 1rem;
}

.type-badge-enhanced {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.location-name-enhanced {
    font-size: 1.4rem;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 1.5rem;
    line-height: 1.3;
}

/* Enhanced Statistics */
.location-stats-enhanced {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-top: auto;
}

.stat-card-mini {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 1rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.3s ease;
}

.stat-card-mini:hover {
    background: #e9ecef;
    transform: translateX(5px);
}

.stat-icon-mini {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #008543, #20c997);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1rem;
}

.stat-content-mini {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.stat-number-mini {
    font-size: 1.3rem;
    font-weight: 700;
    color: #2c3e50;
    line-height: 1;
}

.stat-name-mini {
    font-size: 1rem;
    font-weight: 600;
    color: #2c3e50;
    line-height: 1.2;
    word-break: break-word;
}

.stat-label-mini {
    font-size: 0.8rem;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 500;
}

/* Enhanced Card Footer */
.location-footer-enhanced {
    padding: 1.5rem 2rem;
    border-top: 1px solid #f1f3f4;
    background: #fafbfc;
}

.action-buttons-enhanced {
    display: flex;
    gap: 1rem;
    justify-content: center;
}

.action-btn-enhanced {
    flex: 1;
    padding: 0.75rem 1rem;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    cursor: pointer;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 0.85rem;
}

.action-btn-enhanced.primary {
    background: linear-gradient(135deg, #007bff, #0056b3);
    color: white;
}

.action-btn-enhanced.primary:hover {
    background: linear-gradient(135deg, #0056b3, #004085);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
}

.action-btn-enhanced.success {
    background: linear-gradient(135deg, #28a745, #1e7e34);
    color: white;
}

.action-btn-enhanced.success:hover {
    background: linear-gradient(135deg, #1e7e34, #155724);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
}

/* Enhanced Overlay */
.location-overlay-enhanced {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(0, 133, 67, 0.95), rgba(32, 201, 151, 0.95));
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: all 0.4s ease;
    backdrop-filter: blur(10px);
}

.location-card-enhanced:hover .location-overlay-enhanced {
    opacity: 1;
}

.overlay-content-enhanced {
    text-align: center;
    color: white;
    transform: translateY(20px);
    transition: all 0.4s ease;
}

.location-card-enhanced:hover .overlay-content-enhanced {
    transform: translateY(0);
}

.overlay-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    animation: bounce 2s infinite;
}

@keyframes bounce {
    0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
    40% { transform: translateY(-10px); }
    60% { transform: translateY(-5px); }
}

.overlay-title {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.overlay-subtitle {
    font-size: 1rem;
    opacity: 0.9;
    margin: 0;
}

/* Responsive Design */
@media (max-width: 768px) {
    .page-title {
        font-size: 2rem;
        flex-direction: column;
        text-align: center;
    }
    
    .title-icon-wrapper {
        width: 60px;
        height: 60px;
        font-size: 1.5rem;
    }
    
    .monitor-stats-enhanced {
        justify-content: center;
        margin-top: 2rem;
    }
    
    .section-title-wrapper {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .filter-controls-enhanced {
        align-items: center;
        margin-top: 2rem;
    }
    
    .filter-wrapper {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .location-card-enhanced {
        margin-bottom: 2rem;
    }
    
    .action-buttons-enhanced {
        flex-direction: column;
    }
}
</style>

<script>
$(document).ready(function() {
    // Location type filtering
    $('#typeFilter').on('change', function() {
        const selectedType = $(this).val();
        let visibleCount = 0;
        
        $('.location-item').each(function() {
            const itemType = $(this).data('type');
            
            if (selectedType === '' || itemType === selectedType) {
                $(this).show();
                visibleCount++;
            } else {
                $(this).hide();
            }
        });
        
        // Update the count
        $('#locationCount').text(visibleCount);
        
        // Show/hide empty state
        if (visibleCount === 0) {
            if ($('.no-results-message').length === 0) {
                $('.row').after(`
                    <div class="no-results-message text-center py-5">
                        <div class="empty-icon mb-3">
                            <i class="fas fa-search" style="font-size: 3rem; color: #6c757d;"></i>
                        </div>
                        <h4>No rooms found</h4>
                        <p class="text-muted">No rooms match the selected type. Try selecting a different type.</p>
                    </div>
                `);
            }
        } else {
            $('.no-results-message').remove();
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>