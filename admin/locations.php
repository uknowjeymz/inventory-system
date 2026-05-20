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
                    $facilitator_id = $_POST['facilitator_id'] == '' ? null : $_POST['facilitator_id'];
                    $location_type_id = $_POST['location_type_id'] == '' ? null : $_POST['location_type_id'];
                    
                    $query = "INSERT INTO locations (location_name, location_type_id, description, capacity, facilitator_id, campus) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    $result = $stmt->execute([
                        $_POST['location_name'], 
                        $location_type_id, 
                        $_POST['description'], 
                        $_POST['capacity'], 
                        $facilitator_id,
                        $_POST['campus']
                    ]);
                    
                    if ($result) {
                        $_SESSION['success_message'] = "Location added successfully!";
                        header("Location: locations.php");
                        exit();
                    }
                    break;
                    
                case 'edit':
                    $facilitator_id = $_POST['facilitator_id'] == '' ? null : $_POST['facilitator_id'];
                    $location_type_id = $_POST['location_type_id'] == '' ? null : $_POST['location_type_id'];
                    
                    $query = "UPDATE locations SET location_name = ?, location_type_id = ?, description = ?, capacity = ?, facilitator_id = ?, campus = ? WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $result = $stmt->execute([
                        $_POST['location_name'], 
                        $location_type_id, 
                        $_POST['description'], 
                        $_POST['capacity'], 
                        $facilitator_id, 
                        $_POST['campus'],
                        $_POST['id']
                    ]);
                    
                    if ($result) {
                        $_SESSION['success_message'] = "Location updated successfully!";
                        header("Location: locations.php");
                        exit();
                    }
                    break;
                    
                case 'delete':
                    $query = "DELETE FROM locations WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $result = $stmt->execute([$_POST['id']]);
                    
                    if ($result) {
                        $_SESSION['success_message'] = "Location deleted successfully!";
                        header("Location: locations.php");
                        exit();
                    } else {
                        $_SESSION['error_message'] = "Failed to delete location.";
                    }
                    break;
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Database error: " . $e->getMessage();
            header("Location: locations.php");
            exit();
        }
    }
}

// Get success/error messages from session
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Get floor filter from URL
$selected_floor = $_GET['floor'] ?? '';

// Get all locations with equipment counts, facilitator info, and location type details
$query = "SELECT l.*, 
          COUNT(ci.id) as computer_count,
          SUM(CASE WHEN ci.status = 'available' THEN 1 ELSE 0 END) as available_count,
          SUM(CASE WHEN ci.status = 'assigned' THEN 1 ELSE 0 END) as assigned_count,
          f.full_name as facilitator_name, 
          f.email as facilitator_email,
          lt.type_name as location_type_name,
          lt.type_code as location_type_code,
          lt.icon_class as location_type_icon,
          lt.color_primary as location_type_color_primary,
          lt.color_secondary as location_type_color_secondary,
          lt.equipment_label as location_type_equipment_label
          FROM locations l 
          LEFT JOIN computer_inventory ci ON l.id = ci.location_id 
          LEFT JOIN users f ON l.facilitator_id = f.id
          LEFT JOIN location_types lt ON l.location_type_id = lt.id";

// Add floor filter if selected
if (!empty($selected_floor)) {
    $query .= " WHERE lt.type_code = :selected_floor";
}

$query .= " GROUP BY l.id ORDER BY 
            CASE 
                WHEN lt.type_code = 'Basement' THEN 1
                WHEN lt.type_code = 'Ground Floor' THEN 2
                WHEN lt.type_code = '1st Floor' THEN 3
                WHEN lt.type_code = '2nd Floor' THEN 4
                WHEN lt.type_code = '3rd Floor' THEN 5
                WHEN lt.type_code = '4th Floor' THEN 6
                WHEN lt.type_code = '5th Floor' THEN 7
                ELSE 8
            END, l.location_name";

$stmt = $db->prepare($query);

// Bind parameter if floor filter is selected
if (!empty($selected_floor)) {
    $stmt->bindParam(':selected_floor', $selected_floor);
}

$stmt->execute();
$locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all users for facilitator dropdown
$query = "SELECT id, full_name, email FROM users ORDER BY full_name";
$stmt = $db->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all active location types for dropdown
$query = "SELECT id, type_code, type_name, icon_class, color_primary FROM location_types WHERE is_active = 1 ORDER BY type_name";
$stmt = $db->prepare($query);
$stmt->execute();
$location_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique floor levels from location_types for filter buttons
$floor_query = "SELECT DISTINCT type_code FROM location_types WHERE type_code IS NOT NULL AND type_code != '' ORDER BY 
                CASE 
                    WHEN type_code = 'Basement' THEN 1
                    WHEN type_code = 'Ground Floor' THEN 2
                    WHEN type_code = '1st Floor' THEN 3
                    WHEN type_code = '2nd Floor' THEN 4
                    WHEN type_code = '3rd Floor' THEN 5
                    WHEN type_code = '4th Floor' THEN 6
                    WHEN type_code = '5th Floor' THEN 7
                    ELSE 8
                END";
$floor_stmt = $db->prepare($floor_query);
$floor_stmt->execute();
$floors = $floor_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_locations = count($locations);
$locations_with_facilitators = count(array_filter($locations, fn($loc) => !empty($loc['facilitator_name'])));
$total_equipment = array_sum(array_column($locations, 'computer_count'));
$total_capacity = array_sum(array_column($locations, 'capacity'));

$page_title = "Location Management";
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
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
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
    color: #546E7A;
    font-size: 0.9rem;
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
    border: 1px solid var(--ucc-green-mint);
}

.filter-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--ucc-green-dark);
    margin-bottom: 1.5rem;
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
    padding: 0.3rem 1rem;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-left: 1rem;
}

.filter-btn-group {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.filter-btn {
    border: 1px solid var(--ucc-green-mint);
    border-radius: 50px;
    padding: 0.6rem 1.5rem;
    font-size: 0.9rem;
    font-weight: 500;
    color: #546E7A;
    background: white;
    transition: all 0.2s ease;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
}

.filter-btn:hover {
    background: var(--ucc-green-soft);
    border-color: var(--ucc-green-primary);
    color: var(--ucc-green-dark);
    transform: translateY(-2px);
}

.filter-btn.active {
    background: linear-gradient(135deg, var(--ucc-green-primary) 0%, var(--ucc-green-dark) 100%);
    color: white;
    border-color: var(--ucc-green-primary);
    box-shadow: 0 5px 15px rgba(46, 125, 50, 0.2);
}

.filter-btn i {
    font-size: 0.9rem;
}

.active-filter .alert {
    border-radius: 12px;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
}

.active-filter .btn-outline-secondary {
    border-color: var(--ucc-green-mint);
    color: var(--ucc-green-dark);
    transition: all 0.3s ease;
}

.active-filter .btn-outline-secondary:hover {
    background: var(--ucc-green-primary);
    border-color: var(--ucc-green-primary);
    color: white;
}

/* Floor Badge */
.floor-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    background: rgba(255,215,0,0.2);
    color: #FFD700;
    padding: 0.3rem 0.8rem;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 600;
    backdrop-filter: blur(10px);
}

.floor-badge i {
    font-size: 0.7rem;
}

/* Locations Grid */
.locations-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.location-card {
    background: white;
    border-radius: 24px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
    border: 1px solid var(--ucc-green-mint);
    overflow: hidden;
    transition: all 0.3s ease;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.location-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 20px 40px rgba(46, 125, 50, 0.15);
}

.location-header {
    background: linear-gradient(135deg, var(--ucc-green-primary) 0%, var(--ucc-green-dark) 100%);
    padding: 1.5rem;
    color: white;
    position: relative;
}

.location-type-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(255,255,255,0.2);
    padding: 0.4rem 1rem;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-bottom: 1rem;
    backdrop-filter: blur(10px);
    flex-wrap: wrap;
}

.location-type-badge i {
    font-size: 0.9rem;
}

.location-title {
    font-size: 1.4rem;
    font-weight: 700;
    margin: 0 0 0.3rem 0;
    line-height: 1.3;
}

.location-description {
    font-size: 0.9rem;
    opacity: 0.9;
    margin: 0;
}

.location-menu {
    position: absolute;
    top: 1.5rem;
    right: 1.5rem;
}

.menu-btn {
    background: rgba(255,255,255,0.2);
    border: none;
    color: white;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.menu-btn:hover {
    background: rgba(255,255,255,0.3);
    transform: scale(1.05);
}

.dropdown-menu {
    border: none;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    padding: 0.5rem 0;
    min-width: 180px;
}

.dropdown-item {
    padding: 0.7rem 1.2rem;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.dropdown-item:hover {
    background: var(--ucc-green-soft);
    transform: translateX(5px);
}

.dropdown-item i {
    width: 20px;
    margin-right: 0.5rem;
}

/* Facilitator Section */
.facilitator-section {
    padding: 1.5rem;
    border-bottom: 1px solid var(--ucc-green-mint);
}

.facilitator-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    background: var(--ucc-green-soft);
    padding: 1rem;
    border-radius: 16px;
    border: 1px solid var(--ucc-green-mint);
}

.facilitator-avatar {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--ucc-green-primary) 0%, var(--ucc-green-secondary) 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.facilitator-info {
    flex: 1;
    min-width: 0;
}

.facilitator-name {
    font-size: 1rem;
    font-weight: 700;
    color: var(--ucc-green-dark);
    margin: 0 0 0.2rem 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.facilitator-email {
    font-size: 0.8rem;
    color: #546E7A;
    margin: 0 0 0.3rem 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.facilitator-badge {
    display: inline-block;
    background: var(--ucc-green-primary);
    color: white;
    padding: 0.2rem 0.8rem;
    border-radius: 50px;
    font-size: 0.7rem;
    font-weight: 600;
}

.email-btn {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    border: 2px solid #EA4335;
    background: white;
    color: #EA4335;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    flex-shrink: 0;
}

.email-btn:hover {
    background: #EA4335;
    color: white;
    transform: scale(1.1);
}

/* No Facilitator */
.no-facilitator {
    padding: 1.5rem;
    border-bottom: 1px solid var(--ucc-green-mint);
}

.empty-facilitator {
    text-align: center;
    background: linear-gradient(135deg, #FFF3E0 0%, #FFE0B2 100%);
    padding: 1.5rem;
    border-radius: 16px;
    border: 1px dashed #F57C00;
}

.empty-icon {
    font-size: 2rem;
    color: #F57C00;
    margin-bottom: 0.5rem;
}

.empty-text {
    color: #F57C00;
    font-weight: 600;
    margin-bottom: 1rem;
}

.assign-btn {
    background: white;
    border: 2px solid #F57C00;
    color: #F57C00;
    padding: 0.5rem 1.2rem;
    border-radius: 50px;
    font-size: 0.9rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.assign-btn:hover {
    background: #F57C00;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(245, 124, 0, 0.3);
}

/* Equipment Stats */
.equipment-stats {
    padding: 1.5rem;
    border-bottom: 1px solid var(--ucc-green-mint);
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.8rem;
}

.stat-box {
    text-align: center;
    padding: 0.8rem 0.3rem;
    background: var(--ucc-green-soft);
    border-radius: 12px;
    transition: all 0.3s ease;
}

.stat-box:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(46, 125, 50, 0.1);
}

.stat-value {
    font-size: 1.3rem;
    font-weight: 800;
    color: var(--ucc-green-dark);
    line-height: 1.2;
    margin-bottom: 0.2rem;
}

.stat-label {
    font-size: 0.65rem;
    color: #546E7A;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

/* Capacity Section */
.capacity-section {
    padding: 1.5rem;
    margin-top: auto;
}

.capacity-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.capacity-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--ucc-green-dark);
}

.capacity-percentage {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--ucc-green-primary);
}

.capacity-bar {
    height: 8px;
    background: var(--ucc-green-soft);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 0.3rem;
}

.capacity-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.6s ease;
}

.capacity-fill.low { background: linear-gradient(90deg, var(--ucc-green-primary) 0%, var(--ucc-green-secondary) 100%); }
.capacity-fill.medium { background: linear-gradient(90deg, #F57C00 0%, #FFB74D 100%); }
.capacity-fill.high { background: linear-gradient(90deg, #D32F2F 0%, #EF5350 100%); }

.capacity-footer {
    display: flex;
    justify-content: space-between;
    font-size: 0.7rem;
    color: #90A4AE;
}

/* Campus Badge */
.campus-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    background: rgba(255,255,255,0.2);
    padding: 0.3rem 0.8rem;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 500;
    backdrop-filter: blur(10px);
}

/* Modal Styling */
.modal-content {
    border-radius: 20px;
    border: none;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

.modal-header {
    background: linear-gradient(135deg, var(--ucc-green-primary) 0%, var(--ucc-green-dark) 100%);
    color: white;
    border-radius: 20px 20px 0 0;
    padding: 1.5rem;
    border: none;
}

.modal-header .modal-title {
    font-weight: 700;
    font-size: 1.3rem;
}

.modal-header .btn-close {
    background: white;
    opacity: 0.8;
    border-radius: 50%;
    padding: 0.5rem;
}

.modal-body {
    padding: 2rem;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--ucc-green-mint);
}

.form-label {
    font-weight: 600;
    color: var(--ucc-green-dark);
    font-size: 0.9rem;
    margin-bottom: 0.3rem;
}

.form-control, .form-select {
    border-radius: 12px;
    border: 2px solid var(--ucc-green-mint);
    padding: 0.6rem 1rem;
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    border-color: var(--ucc-green-primary);
    box-shadow: 0 0 0 0.25rem rgba(46, 125, 50, 0.15);
}

textarea.form-control {
    min-height: 80px;
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
    background: var(--ucc-green-soft);
    color: var(--ucc-green-dark);
    border-left: 4px solid var(--ucc-green-primary);
}

.alert-danger {
    background: #FFEBEE;
    color: #D32F2F;
    border-left: 4px solid #D32F2F;
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
    color: var(--ucc-green-dark);
    margin-bottom: 1rem;
}

.empty-state p {
    color: #546E7A;
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
    
    .filter-btn-group {
        flex-direction: column;
    }
    
    .filter-btn {
        width: 100%;
        justify-content: center;
    }
    
    .locations-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .facilitator-card {
        flex-wrap: wrap;
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

.location-card {
    animation: slideIn 0.5s ease-out forwards;
    opacity: 0;
}

.location-card:nth-child(1) { animation-delay: 0.1s; }
.location-card:nth-child(2) { animation-delay: 0.15s; }
.location-card:nth-child(3) { animation-delay: 0.2s; }
.location-card:nth-child(4) { animation-delay: 0.25s; }
.location-card:nth-child(5) { animation-delay: 0.3s; }
.location-card:nth-child(6) { animation-delay: 0.35s; }
.location-card:nth-child(7) { animation-delay: 0.4s; }
.location-card:nth-child(8) { animation-delay: 0.45s; }
</style>

<!-- Page Header -->
<div class="page-header">
    <div class="header-content">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <div class="header-title">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>Location Management</span>
                </div>
                <p class="header-subtitle">
                    Manage all laboratory locations, assign facilitators, and track equipment distribution across campuses.
                </p>
                <div class="header-stats">
                    <div class="header-stat-item">
                        <span class="header-stat-number"><?php echo $total_locations; ?></span>
                        <span class="header-stat-label">Total Locations</span>
                    </div>
                    <div class="header-stat-item">
                        <span class="header-stat-number"><?php echo $locations_with_facilitators; ?></span>
                        <span class="header-stat-label">With Facilitators</span>
                    </div>
                    <div class="header-stat-item">
                        <span class="header-stat-number"><?php echo $total_equipment; ?></span>
                        <span class="header-stat-label">Equipment</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="header-actions">
                    <button type="button" class="btn" data-bs-toggle="modal" data-bs-target="#addModal">
                        <i class="fas fa-plus-circle"></i> New Location
                    </button>
                    <a href="location_types.php" class="btn">
                        <i class="fas fa-tags"></i> Manage Types
                    </a>
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

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary">
            <i class="fas fa-building"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $total_locations; ?></h3>
            <p>Total Locations</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon success">
            <i class="fas fa-user-tie"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $locations_with_facilitators; ?></h3>
            <p>With Facilitators</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon info">
            <i class="fas fa-desktop"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $total_equipment; ?></h3>
            <p>Equipment Items</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon warning">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $total_capacity; ?></h3>
            <p>Total Capacity</p>
        </div>
    </div>
</div>

<!-- Floor Filter Section -->
<div class="filter-section">
    <div class="filter-title">
        <i class="fas fa-layer-group"></i>
        <span>Filter by Floor</span>
        <span class="filter-badge"><?php echo count($locations); ?> locations</span>
    </div>
    
    <div class="row">
        <div class="col-12">
            <div class="filter-btn-group">
                <a href="locations.php" class="filter-btn <?php echo empty($selected_floor) ? 'active' : ''; ?>">
                    <i class="fas fa-globe me-1"></i>All Floors
                </a>
                
                <?php 
                // Define floor order for display
                $floor_order = ['Basement', 'Ground Floor', '1st Floor', '2nd Floor', '3rd Floor', '4th Floor', '5th Floor'];
                
                foreach ($floor_order as $floor): 
                    // Check if this floor exists in the database
                    $floor_exists = false;
                    foreach ($floors as $f) {
                        if ($f['type_code'] === $floor) {
                            $floor_exists = true;
                            break;
                        }
                    }
                    
                    // Only show if floor exists in database
                    if ($floor_exists):
                ?>
                <a href="?floor=<?php echo urlencode($floor); ?>" class="filter-btn <?php echo $selected_floor === $floor ? 'active' : ''; ?>">
                    <i class="fas fa-building me-1"></i><?php echo $floor; ?>
                </a>
                <?php 
                    endif;
                endforeach; 
                ?>
            </div>
        </div>
    </div>
    
    <!-- Active Filter Display -->
    <?php if (!empty($selected_floor)): ?>
    <div class="active-filter mt-3">
        <div class="alert alert-info border-0 py-2 px-3 mb-0" style="background: rgba(46, 125, 50, 0.1);">
            <i class="fas fa-filter me-2" style="color: var(--ucc-green-primary);"></i>
            <span>Currently showing locations on <strong><?php echo htmlspecialchars($selected_floor); ?></strong></span>
            <a href="locations.php" class="btn btn-sm btn-outline-secondary ms-3">
                <i class="fas fa-times me-1"></i>Clear Filter
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Locations Grid -->
<?php if (empty($locations)): ?>
<div class="empty-state">
    <div class="empty-state-icon">
        <i class="fas fa-map-marker-alt"></i>
    </div>
    <h3>No Locations Found</h3>
    <p>
        <?php if (!empty($selected_floor)): ?>
            No locations found on <strong><?php echo htmlspecialchars($selected_floor); ?></strong>. 
            <a href="locations.php" class="text-success">View all locations</a> or create a new one.
        <?php else: ?>
            Get started by creating your first laboratory location.
        <?php endif; ?>
    </p>
    <button type="button" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="fas fa-plus-circle me-2"></i>Add New Location
    </button>
</div>
<?php else: ?>
<div class="locations-grid">
    <?php foreach ($locations as $location): ?>
    <div class="location-card">
        <!-- Card Header -->
        <div class="location-header">
            <div>
                <div class="location-type-badge">
                    <i class="fas <?php echo $location['location_type_icon'] ?? 'fa-door-open'; ?>"></i>
                    <span><?php echo htmlspecialchars($location['location_type_name'] ?? 'Uncategorized'); ?></span>
                    <?php if (!empty($location['location_type_code'])): ?>
                    <span class="floor-badge ms-2">
                        <i class="fas fa-layer-group"></i>
                        <?php echo htmlspecialchars($location['location_type_code']); ?>
                    </span>
                    <?php endif; ?>
                    <span class="campus-badge ms-2">
                        <i class="fas fa-university"></i>
                        <?php echo htmlspecialchars($location['campus']); ?>
                    </span>
                </div>
                <h3 class="location-title"><?php echo htmlspecialchars($location['location_name']); ?></h3>
                <?php if (!empty($location['description'])): ?>
                <p class="location-description"><?php echo htmlspecialchars($location['description']); ?></p>
                <?php endif; ?>
            </div>
            
            <div class="location-menu">
                <div class="dropdown">
                    <button class="menu-btn" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-ellipsis-h"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item edit-btn" href="#" 
                                data-id="<?php echo $location['id']; ?>"
                                data-name="<?php echo htmlspecialchars($location['location_name']); ?>"
                                data-campus="<?php echo htmlspecialchars($location['campus']); ?>"
                                data-location-type-id="<?php echo $location['location_type_id'] ?? ''; ?>"
                                data-description="<?php echo htmlspecialchars($location['description'] ?? ''); ?>"
                                data-capacity="<?php echo intval($location['capacity']); ?>"
                                data-facilitator="<?php echo $location['facilitator_id'] ?? ''; ?>"
                                data-bs-toggle="modal" data-bs-target="#editModal">
                                <i class="fas fa-edit text-primary"></i> Edit Location
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item delete-btn" href="#" 
                               data-id="<?php echo $location['id']; ?>"
                               data-name="<?php echo htmlspecialchars($location['location_name']); ?>"
                               data-bs-toggle="modal" data-bs-target="#deleteModal">
                                <i class="fas fa-trash text-danger"></i> Delete Location
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Facilitator Section -->
        <div class="facilitator-section">
            <?php if ($location['facilitator_name']): ?>
            <div class="facilitator-card">
                <div class="facilitator-avatar">
                    <?php 
                    $initials = '';
                    $name_parts = explode(' ', $location['facilitator_name']);
                    foreach ($name_parts as $part) {
                        $initials .= strtoupper(substr($part, 0, 1));
                    }
                    echo htmlspecialchars(substr($initials, 0, 2));
                    ?>
                </div>
                <div class="facilitator-info">
                    <div class="facilitator-name"><?php echo htmlspecialchars($location['facilitator_name']); ?></div>
                    <div class="facilitator-email"><?php echo htmlspecialchars($location['facilitator_email']); ?></div>
                    <span class="facilitator-badge">Lab Facilitator</span>
                </div>
                <button class="email-btn" 
                        data-email="<?php echo htmlspecialchars($location['facilitator_email']); ?>"
                        data-name="<?php echo htmlspecialchars($location['facilitator_name']); ?>"
                        title="Send Email via Gmail">
                    <i class="fab fa-google"></i>
                </button>
            </div>
            <?php else: ?>
            <div class="no-facilitator">
                <div class="empty-facilitator">
                    <div class="empty-icon">
                        <i class="fas fa-user-slash"></i>
                    </div>
                    <div class="empty-text">No facilitator assigned</div>
                    <button class="assign-btn edit-btn" 
                            data-id="<?php echo $location['id']; ?>"
                            data-name="<?php echo htmlspecialchars($location['location_name']); ?>"
                            data-campus="<?php echo htmlspecialchars($location['campus']); ?>"
                            data-location-type-id="<?php echo $location['location_type_id'] ?? ''; ?>"
                            data-description="<?php echo htmlspecialchars($location['description'] ?? ''); ?>"
                            data-capacity="<?php echo intval($location['capacity']); ?>"
                            data-facilitator=""
                            data-bs-toggle="modal" data-bs-target="#editModal">
                        <i class="fas fa-user-plus me-2"></i>Assign Facilitator
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Equipment Statistics -->
        <div class="equipment-stats">
            <div class="stats-row">
                <div class="stat-box">
                    <div class="stat-value"><?php echo $location['computer_count']; ?></div>
                    <div class="stat-label">Total</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value text-success"><?php echo $location['available_count']; ?></div>
                    <div class="stat-label">Available</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value text-info"><?php echo $location['assigned_count']; ?></div>
                    <div class="stat-label">Assigned</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?php echo $location['capacity']; ?></div>
                    <div class="stat-label">Capacity</div>
                </div>
            </div>
        </div>
        
        <!-- Capacity Usage -->
        <?php 
        $usage_percent = $location['capacity'] > 0 ? round(($location['computer_count'] / $location['capacity']) * 100, 1) : 0;
        $capacity_class = $usage_percent > 80 ? 'high' : ($usage_percent > 60 ? 'medium' : 'low');
        ?>
        <div class="capacity-section">
            <div class="capacity-header">
                <span class="capacity-title">Capacity Usage</span>
                <span class="capacity-percentage"><?php echo $usage_percent; ?>%</span>
            </div>
            <div class="capacity-bar">
                <div class="capacity-fill <?php echo $capacity_class; ?>" style="width: <?php echo min($usage_percent, 100); ?>%"></div>
            </div>
            <div class="capacity-footer">
                <span><i class="fas fa-desktop me-1"></i><?php echo $location['computer_count']; ?> items</span>
                <span><i class="fas fa-users me-1"></i><?php echo $location['capacity']; ?> capacity</span>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle me-2"></i>
                    Add New Location
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-tag me-2 text-success"></i>Location Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" name="location_name" placeholder="e.g., Computer Lab 1" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-layer-group me-2 text-success"></i>Location Type <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" name="location_type_id" required>
                            <option value="">Select Location Type</option>
                            <?php foreach ($location_types as $type): ?>
                            <option value="<?php echo $type['id']; ?>">
                                <?php echo htmlspecialchars($type['type_name']); ?> (<?php echo $type['type_code']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-align-left me-2 text-success"></i>Description
                        </label>
                        <textarea class="form-control" name="description" rows="2" placeholder="Brief description of the location..."></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fas fa-users me-2 text-success"></i>Capacity
                            </label>
                            <input type="number" class="form-control" name="capacity" min="0" value="0" placeholder="Max capacity">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fas fa-university me-2 text-success"></i>Campus <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" name="campus" required>
                                <option value="">Select Campus</option>
                                <option value="South Campus">South Campus</option>
                                <option value="Congressional Campus">Congressional Campus</option>
                                <option value="Bagong Silang Campus">Bagong Silang Campus</option>
                                <option value="Camarin Campus">Camarin Campus</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-user-tie me-2 text-success"></i>Facilitator (Optional)
                        </label>
                        <select class="form-select" name="facilitator_id">
                            <option value="">No Facilitator</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo htmlspecialchars($user['full_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Assign a user as the location facilitator</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-2"></i>Save Location
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>
                    Edit Location
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-tag me-2 text-success"></i>Location Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" name="location_name" id="edit_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-layer-group me-2 text-success"></i>Location Type <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" name="location_type_id" id="edit_location_type_id" required>
                            <option value="">Select Location Type</option>
                            <?php foreach ($location_types as $type): ?>
                            <option value="<?php echo $type['id']; ?>">
                                <?php echo htmlspecialchars($type['type_name']); ?> (<?php echo $type['type_code']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-align-left me-2 text-success"></i>Description
                        </label>
                        <textarea class="form-control" name="description" id="edit_description" rows="2"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fas fa-users me-2 text-success"></i>Capacity
                            </label>
                            <input type="number" class="form-control" name="capacity" id="edit_capacity" min="0">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fas fa-university me-2 text-success"></i>Campus <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" name="campus" id="edit_campus" required>
                                <option value="South Campus">South Campus</option>
                                <option value="Congressional Campus">Congressional Campus</option>
                                <option value="Bagong Silang Campus">Bagong Silang Campus</option>
                                <option value="Camarin Campus">Camarin Campus</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-user-tie me-2 text-success"></i>Facilitator (Optional)
                        </label>
                        <select class="form-select" name="facilitator_id" id="edit_facilitator">
                            <option value="">No Facilitator</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo htmlspecialchars($user['full_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-2"></i>Update Location
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
                    Delete Location
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_id">
                    
                    <div class="text-center mb-4">
                        <div class="text-danger mb-3">
                            <i class="fas fa-exclamation-triangle fa-4x"></i>
                        </div>
                        <h5 class="mb-3">Are you absolutely sure?</h5>
                        <p class="text-muted mb-4">
                            This action cannot be undone. This will permanently delete the location
                            <strong class="text-danger" id="delete_location_name"></strong>.
                        </p>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Note: Any equipment assigned to this location will have their location set to null.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash-alt me-2"></i>Delete Location
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Initialize locations functionality
window.initLocations = function() {
    console.log('🏢 Locations page initialized');
    
    // Edit button click handler
    $(document).on('click', '.edit-btn', function(e) {
        e.preventDefault();
        
        // Get data from the clicked element
        const id = $(this).data('id');
        const name = $(this).data('name');
        const campus = $(this).data('campus');
        const locationTypeId = $(this).data('location-type-id');
        const description = $(this).data('description');
        const capacity = $(this).data('capacity');
        const facilitator = $(this).data('facilitator');
        
        console.log('✏️ Edit location:', { id, name, campus, locationTypeId, description, capacity, facilitator });
        
        // Populate modal fields
        $('#edit_id').val(id);
        $('#edit_name').val(name);
        $('#edit_campus').val(campus);
        $('#edit_location_type_id').val(locationTypeId);
        $('#edit_description').val(description);
        $('#edit_capacity').val(capacity);
        $('#edit_facilitator').val(facilitator);
    });
    
    // Delete button click handler
    $(document).on('click', '.delete-btn', function(e) {
        e.preventDefault();
        
        const id = $(this).data('id');
        const name = $(this).data('name');
        
        console.log('🗑️ Delete location:', { id, name });
        
        $('#delete_id').val(id);
        $('#delete_location_name').text(name);
    });
    
    // Gmail button click handler
    $(document).on('click', '.email-btn', function(e) {
        e.preventDefault();
        
        const email = $(this).data('email');
        const name = $(this).data('name');
        
        const subject = encodeURIComponent('Regarding Laboratory Management');
        const body = encodeURIComponent(`Dear ${name},\n\nI hope this email finds you well.\n\n\n\nBest regards,\n`);
        const gmailUrl = `https://mail.google.com/mail/?view=cm&fs=1&to=${encodeURIComponent(email)}&su=${subject}&body=${body}`;
        
        window.open(gmailUrl, '_blank');
    });
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut(500);
    }, 5000);
};

// Initialize on document ready
$(document).ready(function() {
    if (typeof window.initLocations === 'function') {
        window.initLocations();
    }
});
</script>

<?php include '../includes/footer.php'; ?>