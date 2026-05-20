<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get the category from URL parameter
$category_id = $_GET['category_id'] ?? '';

if (empty($category_id)) {
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

// Handle form submissions for manager assignment
if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] === 'assign_manager') {
        $location_id = $_POST['location_id'];
        $facilitator_id = $_POST['facilitator_id'] == '' ? null : $_POST['facilitator_id'];
        
        $update_query = "UPDATE locations SET facilitator_id = ? WHERE id = ?";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->execute([$facilitator_id, $location_id]);
        
        $_SESSION['success_message'] = "Manager assigned successfully!";
        header("Location: inventory_rooms.php?category_id=" . $category_id);
        exit();
    }
}

// Get success/error messages from session
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Get all rooms for this category with equipment counts and manager info
$rooms_query = "SELECT l.*, 
                f.full_name as manager_name, 
                f.email as manager_email,
                f.department as manager_department,
                COALESCE((SELECT COUNT(*) FROM computer_inventory WHERE location_id = l.id AND (is_condemned IS NULL OR is_condemned = FALSE)), 0) as equipment_count,
                COALESCE((SELECT COUNT(*) FROM computer_inventory WHERE location_id = l.id AND status = 'available' AND (is_condemned IS NULL OR is_condemned = FALSE)), 0) as available_count,
                COALESCE((SELECT COUNT(*) FROM computer_inventory WHERE location_id = l.id AND status = 'maintenance' AND (is_condemned IS NULL OR is_condemned = FALSE)), 0) as maintenance_count,
                COALESCE((SELECT COUNT(*) FROM computer_inventory WHERE location_id = l.id AND is_condemned = TRUE), 0) as condemned_count
                FROM locations l 
                LEFT JOIN users f ON l.facilitator_id = f.id
                WHERE l.location_type_id = ? 
                ORDER BY l.location_name";
$rooms_stmt = $db->prepare($rooms_query);
$rooms_stmt->execute([$category_id]);
$rooms = $rooms_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all users for manager assignment
$users_query = "SELECT id, full_name, email, department FROM users WHERE status = 'active' ORDER BY full_name";
$users_stmt = $db->prepare($users_query);
$users_stmt->execute();
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_rooms = count($rooms);
$total_equipment = array_sum(array_column($rooms, 'equipment_count'));
$total_available = array_sum(array_column($rooms, 'available_count'));
$total_maintenance = array_sum(array_column($rooms, 'maintenance_count'));
$total_condemned = array_sum(array_column($rooms, 'condemned_count'));
$managed_rooms = count(array_filter($rooms, function($room) { return !empty($room['manager_name']); }));
$occupancy_rate = $total_rooms > 0 ? round(($managed_rooms / $total_rooms) * 100) : 0;
$availability_rate = $total_equipment > 0 ? round(($total_available / $total_equipment) * 100) : 0;

// Set CSS variables for dynamic colors
$primary_color = $category_info['color_primary'];
$secondary_color = $category_info['color_secondary'];
$primary_rgb = hexdec(substr($primary_color, 1, 2)) . ',' . hexdec(substr($primary_color, 3, 2)) . ',' . hexdec(substr($primary_color, 5, 2));

$page_title = $category_info['type_name'] . " - Rooms";
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

/* Category Header */
.category-header {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    border-radius: 24px;
    padding: 2.5rem;
    margin-bottom: 2rem;
    color: white;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(var(--primary-rgb), 0.25);
}

.category-header::before {
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
    margin-top: 0.5rem;
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
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
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

/* Rooms Grid */
.rooms-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 1.8rem;
    margin-top: 1rem;
}

.room-card {
    background: white;
    border-radius: 24px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
    border: 1px solid rgba(0,0,0,0.03);
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    height: 100%;
    display: flex;
    flex-direction: column;
    position: relative;
    cursor: pointer;
}

.room-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 25px 50px rgba(var(--primary-rgb), 0.15);
    border-color: rgba(var(--primary-rgb), 0.2);
}

.room-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 0;
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    transition: height 0.3s ease;
    border-radius: 4px 0 0 4px;
    z-index: 1;
}

.room-card:hover::before {
    height: 100%;
}

/* Room Header */
.room-header {
    padding: 1.5rem;
    border-bottom: 1px solid rgba(0,0,0,0.03);
    position: relative;
    background: linear-gradient(135deg, rgba(var(--primary-rgb), 0.02) 0%, transparent 100%);
}

.room-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 0.5rem 0;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding-right: 3rem;
}

.room-title i {
    color: var(--primary-color);
    font-size: 1.2rem;
}

.room-description {
    color: #64748b;
    font-size: 0.85rem;
    margin: 0;
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    line-height: 1.5;
}

.room-description i {
    font-size: 0.8rem;
    color: var(--primary-color);
    margin-top: 0.2rem;
}

.room-badge {
    top: 1.5rem;
    right: 1.5rem;
    background: rgba(var(--primary-rgb), 0.1);
    color: var(--primary-color);
    padding: 0.3rem 1rem;
    border-radius: 50px;
    font-size: 0.7rem;
    font-weight: 600;
    border: 1px solid rgba(var(--primary-rgb), 0.2);
    text-transform: uppercase;
    letter-spacing: 0.3px;
    transition: all 0.3s ease;
}

.room-card:hover .room-badge {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.header-action-btn {
    padding: 0.6rem 1.5rem;
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.header-action-btn.btn-light {
    background: white;
    color: var(--primary-color);
    border: 1px solid rgba(255,255,255,0.3);
}

.header-action-btn.btn-outline-light {
    background: transparent;
    color: white;
    border: 2px solid white;
}

.header-action-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

/* Room Menu */
.room-menu {
    position: absolute;
    top: 1rem;
    right: 1rem;
    z-index: 10;
}

.room-menu .btn {
    background: white;
    border: none;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.02);
    transition: all 0.2s ease;
}

.room-menu .btn:hover {
    background: var(--primary-color);
    color: white;
    transform: rotate(90deg);
    box-shadow: 0 8px 15px rgba(var(--primary-rgb), 0.3);
}

.dropdown-menu {
    border: none;
    border-radius: 14px;
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
    padding: 0.5rem;
    min-width: 200px;
    animation: dropdownFade 0.2s ease;
}

@keyframes dropdownFade {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.dropdown-item {
    border-radius: 10px;
    padding: 0.6rem 1rem;
    font-size: 0.9rem;
    color: #1e293b;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.dropdown-item i {
    width: 20px;
    color: var(--primary-color);
    font-size: 0.9rem;
}

.dropdown-item:hover {
    background: rgba(var(--primary-rgb), 0.08);
    color: var(--primary-color);
    transform: translateX(3px);
}

/* Manager Section */
.manager-section {
    padding: 1.2rem 1.5rem;
    background: #f8fafc;
    border-bottom: 1px solid rgba(0,0,0,0.03);
}

.manager-avatar {
    width: 48px;
    height: 48px;
    border-radius: 16px;
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.1rem;
    box-shadow: 0 8px 15px rgba(var(--primary-rgb), 0.2);
    flex-shrink: 0;
}

.manager-info {
    flex: 1;
    min-width: 0;
}

.manager-name {
    font-weight: 700;
    color: #1e293b;
    margin: 0;
    font-size: 1rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.manager-email {
    font-size: 0.75rem;
    color: #64748b;
    margin: 0.2rem 0 0.3rem 0;
    display: flex;
    align-items: center;
    gap: 0.3rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.manager-email i {
    font-size: 0.7rem;
    color: var(--primary-color);
    flex-shrink: 0;
}

.manager-badge {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
    padding: 0.2rem 0.8rem;
    border-radius: 50px;
    font-size: 0.65rem;
    font-weight: 600;
    display: inline-block;
    letter-spacing: 0.3px;
}

.no-manager .btn {
    border: 1px solid var(--primary-color);
    color: var(--primary-color);
    background: white;
    transition: all 0.2s ease;
    padding: 0.4rem 1rem;
    font-size: 0.85rem;
    width: auto;
    display: inline-flex;
}

.no-manager {
    text-align: center;
    padding: 1rem;
    background: white;
    border-radius: 14px;
    border: 2px dashed #e2e8f0;
    transition: all 0.2s ease;
}

.no-manager:hover {
    border-color: var(--primary-color);
    background: rgba(var(--primary-rgb), 0.02);
}

.no-manager i {
    font-size: 2rem;
    color: #cbd5e0;
    margin-bottom: 0.5rem;
}

.no-manager p {
    color: #64748b;
    margin-bottom: 0.8rem;
    font-size: 0.85rem;
}

.no-manager .btn {
    border: 1px solid var(--primary-color);
    color: var(--primary-color);
    background: white;
    transition: all 0.2s ease;
    padding: 0.4rem 1rem;
    font-size: 0.85rem;
}

.no-manager .btn:hover {
    background: var(--primary-color);
    color: white;
}

/* Equipment Stats */
.equipment-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.5rem;
    padding: 1.2rem 1.5rem;
    background: white;
}

.equipment-stat-item {
    text-align: center;
    padding: 0.8rem 0.3rem;
    background: #f8fafc;
    border-radius: 12px;
    transition: all 0.2s ease;
}

.equipment-stat-item:hover {
    background: rgba(var(--primary-rgb), 0.05);
    transform: translateY(-2px);
}

.stat-value {
    font-size: 1.2rem;
    font-weight: 800;
    display: block;
    line-height: 1.2;
    margin-bottom: 0.2rem;
}

.stat-label {
    font-size: 0.6rem;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    font-weight: 600;
}

.stat-value.total { color: var(--primary-color); }
.stat-value.available { color: #059669; }
.stat-value.maintenance { color: #d97706; }
.stat-value.condemned { color: #dc2626; }

/* Capacity Progress */
.capacity-progress {
    padding: 0 1.5rem 1.2rem 1.5rem;
}

.progress-label {
    display: flex;
    justify-content: space-between;
    font-size: 0.7rem;
    color: #64748b;
    margin-bottom: 0.4rem;
    font-weight: 500;
}

.progress-label i {
    color: var(--primary-color);
    margin-right: 0.3rem;
}

.progress {
    height: 6px;
    border-radius: 3px;
    background: #e9ecef;
    overflow: hidden;
}

.progress-bar {
    background: linear-gradient(90deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    border-radius: 3px;
    transition: width 0.3s ease;
}

/* Action Buttons */
.room-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.8rem;
    padding: 1.2rem 1.5rem 1.5rem;
    background: white;
    border-top: 1px solid rgba(0,0,0,0.03);
}

/* In inventory_rooms.php - replace the existing .action-btn styles with these */

/* Room Action Buttons - separate from header action buttons */
.room-action-btn {
    padding: 0.7rem 1rem;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
    width: 100%;
    text-decoration: none;
}

.room-action-btn.primary {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
    box-shadow: 0 5px 15px rgba(var(--primary-rgb), 0.2);
}

.room-action-btn.primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(var(--primary-rgb), 0.3);
}

.room-action-btn.primary:active {
    transform: translateY(-1px);
}

.room-action-btn.secondary {
    background: white;
    color: var(--primary-color);
    border: 2px solid rgba(var(--primary-rgb), 0.3);
}

.room-action-btn.secondary:hover {
    background: rgba(var(--primary-rgb), 0.05);
    border-color: var(--primary-color);
    transform: translateY(-2px);
}

.room-action-btn i {
    font-size: 0.9rem;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: 30px;
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.02);
    border: 1px solid rgba(0,0,0,0.03);
}

.empty-state-icon {
    width: 140px;
    height: 140px;
    border-radius: 70px;
    background: rgba(var(--primary-rgb), 0.08);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 2rem;
    font-size: 3.5rem;
    color: var(--primary-color);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.empty-state h4 {
    font-size: 1.8rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 0.8rem;
}

.empty-state p {
    color: #64748b;
    font-size: 1rem;
    margin-bottom: 2rem;
    max-width: 400px;
    margin-left: auto;
    margin-right: auto;
}

.empty-state .btn {
    padding: 0.8rem 2rem;
    font-size: 1rem;
    border-radius: 50px;
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    border: none;
    color: white;
    transition: all 0.3s ease;
}

.empty-state .btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(var(--primary-rgb), 0.3);
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
    background-size: 0.8rem;
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

/* Loading States */
.loading-skeleton {
    background: linear-gradient(90deg, #f0f0f0 25%, #f8f8f8 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
}

@keyframes loading {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* Responsive Design */
@media (max-width: 992px) {
    .rooms-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .category-header {
        padding: 1.8rem;
    }
    
    .category-header .d-flex {
        flex-direction: column;
        text-align: center;
        gap: 1rem !important;
    }
    
    .header-stats {
        justify-content: center;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .rooms-grid {
        grid-template-columns: 1fr;
    }
    
    .room-actions {
        grid-template-columns: 1fr;
    }
    
    .modal-dialog {
        margin: 1rem;
    }
}

@media (max-width: 480px) {
    .equipment-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .room-header {
        padding: 1.2rem;
    }
    
    .room-title {
        font-size: 1.1rem;
        padding-right: 2.5rem;
    }
    
    .room-badge {
        top: 1.2rem;
        right: 1.2rem;
        padding: 0.2rem 0.8rem;
        font-size: 0.65rem;
    }
    
    .stat-card {
        padding: 1.2rem;
    }
    
    .stat-icon {
        width: 50px;
        height: 50px;
        font-size: 1.5rem;
    }
    
    .stat-content h3 {
        font-size: 1.6rem;
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
            <li class="breadcrumb-item active" aria-current="page">
                <i class="fas <?php echo $category_info['icon_class']; ?> me-1"></i>
                <?php echo htmlspecialchars($category_info['type_name']); ?>
            </li>
        </ol>
    </nav>
</div>

<!-- Category Header -->
<div class="category-header">
    <div class="header-content">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <div class="d-flex align-items-center gap-4">
                    <div class="flex-shrink-0">
                        <i class="fas <?php echo $category_info['icon_class']; ?> fa-4x opacity-75"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h1 class="display-5 fw-bold mb-2"><?php echo htmlspecialchars($category_info['type_name']); ?></h1>
                        <p class="mb-3 fs-5 opacity-90"><?php echo htmlspecialchars($category_info['description']); ?></p>
                        <div class="header-stats">
                            <span class="header-stat">
                                <i class="fas fa-door-open"></i>
                                <?php echo $total_rooms; ?> Rooms
                            </span>
                            <span class="header-stat">
                                <i class="fas fa-boxes"></i>
                                <?php echo $total_equipment; ?> Equipment
                            </span>
                            <span class="header-stat">
                                <i class="fas fa-user-tie"></i>
                                <?php echo $managed_rooms; ?> Managed
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-3 justify-content-lg-end">
                <a href="locations.php" class="btn btn-light header-action-btn">
                    <i class="fas fa-plus-circle me-2"></i>Add Room
                </a>
                <?php if ($category_info['type_code'] === 'computer_lab'): ?>
                <a href="computers.php" class="btn btn-outline-light header-action-btn">
                    <i class="fas fa-desktop me-2"></i>Add Equipment
                </a>
                <?php endif; ?>
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

<?php if (empty($rooms)): ?>
<!-- No Rooms Found -->
<div class="empty-state">
    <div class="empty-state-icon">
        <i class="fas <?php echo $category_info['icon_class']; ?>"></i>
    </div>
    <h4>No Rooms Found</h4>
    <p>Get started by adding your first room to the <?php echo htmlspecialchars($category_info['type_name']); ?> category.</p>
    <a href="locations.php" class="btn btn-lg">
        <i class="fas fa-plus-circle me-2"></i>Add Room
    </a>
</div>
<?php else: ?>

<!-- Statistics Overview -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-door-open"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $total_rooms; ?></h3>
            <p>Total Rooms</p>
            <div class="stat-trend">
                <i class="fas fa-layer-group"></i> Active locations
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-boxes"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $total_equipment; ?></h3>
            <p>Total Equipment</p>
            <div class="stat-trend">
                <i class="fas fa-check-circle text-success"></i> <?php echo $total_available; ?> available
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-chart-pie"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $availability_rate; ?>%</h3>
            <p>Availability Rate</p>
            <div class="stat-trend">
                <i class="fas fa-arrow-up"></i> Current status
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-user-tie"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $managed_rooms; ?> / <?php echo $total_rooms; ?></h3>
            <p>Managed Rooms</p>
            <div class="stat-trend">
                <i class="fas fa-chart-line"></i> <?php echo $occupancy_rate; ?>% occupancy
            </div>
        </div>
    </div>
</div>

<!-- Rooms Grid -->
<div class="rooms-grid">
    <?php foreach ($rooms as $room): ?>
    <div class="room-card" onclick="window.location.href='inventory_room_detail.php?room_id=<?php echo $room['id']; ?>&category_id=<?php echo $category_id; ?>'">
        <!-- Room Menu -->
        <div class="room-menu" onclick="event.stopPropagation();">
            <div class="dropdown">
                <button class="btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item" href="locations.php?id=<?php echo $room['id']; ?>">
                            <i class="fas fa-edit"></i>Edit Room
                        </a>
                    </li>
                    <?php if ($category_info['type_code'] === 'computer_lab'): ?>
                    <li>
                        <a class="dropdown-item" href="computers.php?location_id=<?php echo $room['id']; ?>">
                            <i class="fas fa-desktop"></i>Manage Equipment
                        </a>
                    </li>
                    <?php endif; ?>
                    <li>
                        <button class="btn btn-sm assign-manager-btn no-manager-btn"
                                data-room-id="<?php echo $room['id']; ?>"
                                data-room-name="<?php echo htmlspecialchars($room['location_name']); ?>"
                                data-current-manager=""
                                data-bs-toggle="modal" data-bs-target="#assignManagerModal"
                                onclick="event.stopPropagation();">
                            <i class="fas fa-plus me-1"></i>Assign Manager
                        </button>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger" href="#" onclick="event.stopPropagation(); return confirm('Are you sure you want to delete this room? This action cannot be undone.')">
                            <i class="fas fa-trash-alt"></i>Delete Room
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Room Header -->
        <div class="room-header">
            <h5 class="room-title">
                <i class="fas fa-door-open"></i>
                <?php echo htmlspecialchars($room['location_name']); ?>
            </h5>
            <p class="room-description">
                <i class="fas fa-map-marker-alt"></i>
                <span><?php echo htmlspecialchars($room['description'] ?? 'No description provided'); ?></span>
            </p>
            <span class="room-badge">
                <i class="fas fa-folder me-1"></i>
                <?php echo $category_info['type_code']; ?>
            </span>
        </div>
        
        <!-- Manager Section -->
        <div class="manager-section">
            <?php if ($room['manager_name']): ?>
            <div class="d-flex align-items-center gap-3">
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
                <div class="manager-info">
                    <h6 class="manager-name" title="<?php echo htmlspecialchars($room['manager_name']); ?>">
                        <?php echo htmlspecialchars($room['manager_name']); ?>
                    </h6>
                    <p class="manager-email" title="<?php echo htmlspecialchars($room['manager_email']); ?>">
                        <i class="fas fa-envelope"></i>
                        <span><?php echo htmlspecialchars($room['manager_email']); ?></span>
                    </p>
                    <span class="manager-badge">
                        <i class="fas fa-check-circle me-1"></i>Manager
                    </span>
                </div>
            </div>
            <?php else: ?>
            <div class="no-manager">
                <i class="fas fa-user-slash"></i>
                <p>No manager assigned</p>
                <button class="btn btn-sm assign-manager-btn"
                        data-room-id="<?php echo $room['id']; ?>"
                        data-room-name="<?php echo htmlspecialchars($room['location_name']); ?>"
                        data-current-manager=""
                        data-bs-toggle="modal" data-bs-target="#assignManagerModal"
                        onclick="event.stopPropagation();">
                    <i class="fas fa-plus me-1"></i>Assign Manager
                </button>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Equipment Stats -->
        <div class="equipment-stats">
            <div class="equipment-stat-item" title="Total equipment in this room">
                <span class="stat-value total"><?php echo $room['equipment_count']; ?></span>
                <span class="stat-label">Total</span>
            </div>
            <div class="equipment-stat-item" title="Available equipment">
                <span class="stat-value available"><?php echo $room['available_count']; ?></span>
                <span class="stat-label">Available</span>
            </div>
            <div class="equipment-stat-item" title="Equipment under maintenance">
                <span class="stat-value maintenance"><?php echo $room['maintenance_count']; ?></span>
                <span class="stat-label">Maintenance</span>
            </div>
            <div class="equipment-stat-item" title="Condemned equipment">
                <span class="stat-value condemned"><?php echo $room['condemned_count']; ?></span>
                <span class="stat-label">Condemned</span>
            </div>
        </div>
        
        <!-- Capacity Progress -->
        <?php if (!empty($room['capacity']) && $room['capacity'] > 0): ?>
        <div class="capacity-progress">
            <div class="progress-label">
                <span><i class="fas fa-users me-1"></i>Capacity Usage</span>
                <span><?php echo $room['equipment_count']; ?> / <?php echo $room['capacity']; ?></span>
            </div>
            <div class="progress">
                <?php 
                $capacity_percentage = min(100, round(($room['equipment_count'] / $room['capacity']) * 100));
                $capacity_percentage = max(0, $capacity_percentage);
                ?>
                <div class="progress-bar" style="width: <?php echo $capacity_percentage; ?>%;" 
                     aria-valuenow="<?php echo $capacity_percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Action Buttons - Change class from "action-btn" to "room-action-btn" -->
        <div class="room-actions">
            <button class="room-action-btn primary" onclick="event.stopPropagation(); window.location.href='inventory_room_detail.php?room_id=<?php echo $room['id']; ?>&category_id=<?php echo $category_id; ?>'">
                <i class="fas fa-eye"></i>
                View Details
            </button>
            <button class="room-action-btn secondary" onclick="event.stopPropagation(); window.location.href='room_assignments.php?room_id=<?php echo $room['id']; ?>&category_id=<?php echo $category_id; ?>'">
                <i class="fas fa-user-cog"></i>
                Assignments
            </button>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Assign Manager Modal -->
<div class="modal fade" id="assignManagerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user-tie me-2"></i>
                    Assign Room Manager
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="assign_manager">
                    <input type="hidden" name="location_id" id="assign_room_id">
                    
                    <div class="mb-4">
                        <label class="form-label text-muted small fw-bold mb-2">Room</label>
                        <div class="p-3 bg-light rounded-3 d-flex align-items-center">
                            <i class="fas fa-door-open fa-lg me-3" style="color: var(--primary-color);"></i>
                            <strong id="assign_room_name" class="fs-5"></strong>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold mb-2">Select Manager</label>
                        <select class="form-select form-select-lg" name="facilitator_id" id="assign_manager_select">
                            <option value="">— No Manager (Unassigned) —</option>
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
                            The manager will receive notifications and can manage this room's equipment.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary px-4" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); border: none;">
                        <i class="fas fa-check-circle me-2"></i>Assign Manager
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Initialize inventory rooms functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('Inventory rooms script initialized');
    
    // Assign manager button click handler
    document.querySelectorAll('.assign-manager-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const roomId = this.dataset.roomId;
            const roomName = this.dataset.roomName;
            const currentManager = this.dataset.currentManager;
            
            document.getElementById('assign_room_id').value = roomId;
            document.getElementById('assign_room_name').textContent = roomName;
            document.getElementById('assign_manager_select').value = currentManager;
        });
    });
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
    
    // Add smooth hover effect to stat cards
    document.querySelectorAll('.stat-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    
    // Prevent card click when clicking on action buttons
    document.querySelectorAll('.room-actions button, .room-actions a, .dropdown-toggle, .dropdown-item').forEach(el => {
        el.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>