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
                        $query = "INSERT INTO location_types (type_code, type_name, campus, description, icon_class, color_primary, color_secondary, equipment_label, manager_title) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $db->prepare($query);
                        $result = $stmt->execute([
                            $_POST['type_code'], 
                            $_POST['type_name'], 
                            $_POST['campus'],
                            $_POST['description'], 
                            $_POST['icon_class'], 
                            $_POST['color_primary'], 
                            $_POST['color_secondary'], 
                            $_POST['equipment_label'], 
                            $_POST['manager_title']
                        ]);
                    if ($result) {
                        $_SESSION['success_message'] = "Location type added successfully!";
                        header("Location: location_types.php");
                        exit();
                    } else {
                        $_SESSION['error_message'] = "Failed to add location type.";
                    }
                    break;
                    
                case 'edit':
                        $query = "UPDATE location_types SET type_code = ?, type_name = ?, campus = ?, description = ?, icon_class = ?, color_primary = ?, color_secondary = ?, equipment_label = ?, manager_title = ?, is_active = ? WHERE id = ?";
                        $stmt = $db->prepare($query);
                        $result = $stmt->execute([
                            $_POST['type_code'], 
                            $_POST['type_name'], 
                            $_POST['campus'],
                            $_POST['description'], 
                            $_POST['icon_class'], 
                            $_POST['color_primary'], 
                            $_POST['color_secondary'], 
                            $_POST['equipment_label'], 
                            $_POST['manager_title'],
                            isset($_POST['is_active']) ? 1 : 0,
                            $_POST['id']
                        ]);
                    if ($result) {
                        $_SESSION['success_message'] = "Location type updated successfully!";
                        header("Location: location_types.php");
                        exit();
                    } else {
                        $_SESSION['error_message'] = "Failed to update location type.";
                    }
                    break;
                    
                case 'delete':
                    // Check if location type is in use
                    $check_query = "SELECT COUNT(*) as count FROM locations WHERE location_type_id = ?";
                    $check_stmt = $db->prepare($check_query);
                    $check_stmt->execute([$_POST['id']]);
                    $in_use = $check_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($in_use['count'] > 0) {
                        $_SESSION['error_message'] = "Cannot delete location type that is currently in use.";
                    } else {
                        $query = "DELETE FROM location_types WHERE id = ?";
                        $stmt = $db->prepare($query);
                        $result = $stmt->execute([$_POST['id']]);
                        if ($result) {
                            $_SESSION['success_message'] = "Location type deleted successfully!";
                        } else {
                            $_SESSION['error_message'] = "Failed to delete location type.";
                        }
                    }
                    header("Location: location_types.php");
                    exit();
                    break;
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Database error: " . $e->getMessage();
            header("Location: location_types.php");
            exit();
        }
    }
}

// Get success/error messages from session
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Get all location types with usage counts
$query = "SELECT lt.*, 
          COUNT(DISTINCT l.id) as location_count,
          COALESCE(SUM(
              CASE 
                  WHEN lt.type_code = 'computer_lab' THEN (SELECT COUNT(*) FROM computer_inventory WHERE location_id = l.id)
                  WHEN lt.type_code = 'kitchen' THEN (SELECT COUNT(*) FROM kitchen_equipment WHERE location_id = l.id)
                  WHEN lt.type_code = 'office' THEN (SELECT COUNT(*) FROM office_equipment WHERE location_id = l.id)
                  WHEN lt.type_code = 'regular_lab' THEN (SELECT COUNT(*) FROM lab_equipment WHERE location_id = l.id)
                  ELSE (SELECT COUNT(*) FROM general_equipment WHERE location_id = l.id)
              END
          ), 0) as total_equipment
          FROM location_types lt 
          LEFT JOIN locations l ON lt.id = l.location_type_id
          GROUP BY lt.id 
          ORDER BY lt.type_name";
$stmt = $db->prepare($query);
$stmt->execute();
$location_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_types = count($location_types);
$active_types = count(array_filter($location_types, fn($type) => $type['is_active']));
$total_locations_using = array_sum(array_column($location_types, 'location_count'));
$total_equipment_all = array_sum(array_column($location_types, 'total_equipment'));

$page_title = "Location Types Management";
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

/* Types Grid */
.types-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.type-card {
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

.type-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 20px 40px rgba(46, 125, 50, 0.15);
}

.type-header {
    background: linear-gradient(135deg, var(--type-primary) 0%, var(--type-secondary) 100%);
    padding: 1.5rem;
    color: white;
    position: relative;
}

.type-icon-wrapper {
    width: 60px;
    height: 60px;
    background: rgba(255,255,255,0.2);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    margin-bottom: 1rem;
    backdrop-filter: blur(10px);
}

.type-name {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0 0 0.3rem 0;
    line-height: 1.3;
}

.type-code {
    display: inline-block;
    background: rgba(255,255,255,0.2);
    padding: 0.2rem 1rem;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-right: 0.5rem;
    backdrop-filter: blur(10px);
}

.type-menu {
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

.type-body {
    padding: 1.5rem;
    flex: 1;
}

.type-description {
    color: #546E7A;
    font-size: 0.95rem;
    line-height: 1.5;
    margin-bottom: 1.5rem;
}

/* Info Grid */
.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.info-item {
    background: var(--ucc-green-soft);
    border-radius: 12px;
    padding: 1rem;
    text-align: center;
}

.info-label {
    font-size: 0.7rem;
    color: #546E7A;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.3rem;
}

.info-value {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--ucc-green-dark);
}

/* Stats Row */
.stats-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.8rem;
    margin-bottom: 1.5rem;
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
    font-size: 1.2rem;
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

/* Status Badge */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.4rem 1rem;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
}

.status-badge.active {
    background: var(--ucc-green-soft);
    color: var(--ucc-green-primary);
}

.status-badge.inactive {
    background: #FFEBEE;
    color: #D32F2F;
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

/* Color Preview */
.color-preview {
    width: 100%;
    height: 40px;
    border-radius: 8px;
    margin-top: 0.5rem;
    border: 2px solid var(--ucc-green-mint);
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

.empty-state .btn {
    background: linear-gradient(135deg, var(--ucc-green-primary) 0%, var(--ucc-green-secondary) 100%);
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
    
    .types-grid {
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

.type-card {
    animation: slideIn 0.5s ease-out forwards;
    opacity: 0;
}

.type-card:nth-child(1) { animation-delay: 0.1s; }
.type-card:nth-child(2) { animation-delay: 0.15s; }
.type-card:nth-child(3) { animation-delay: 0.2s; }
.type-card:nth-child(4) { animation-delay: 0.25s; }
.type-card:nth-child(5) { animation-delay: 0.3s; }
.type-card:nth-child(6) { animation-delay: 0.35s; }
.type-card:nth-child(7) { animation-delay: 0.4s; }
.type-card:nth-child(8) { animation-delay: 0.45s; }
</style>

<!-- Page Header -->
<div class="page-header">
    <div class="header-content">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <div class="header-title">
                    <i class="fas fa-tags"></i>
                    <span>Location Types</span>
                </div>
                <p class="header-subtitle">
                    Configure and manage different types of locations in your system. Define colors, icons, and settings for each location category.
                </p>
                <div class="header-stats">
                    <div class="header-stat-item">
                        <span class="header-stat-number"><?php echo $total_types; ?></span>
                        <span class="header-stat-label">Total Types</span>
                    </div>
                    <div class="header-stat-item">
                        <span class="header-stat-number"><?php echo $active_types; ?></span>
                        <span class="header-stat-label">Active</span>
                    </div>
                    <div class="header-stat-item">
                        <span class="header-stat-number"><?php echo $total_locations_using; ?></span>
                        <span class="header-stat-label">Locations</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="header-actions">
                    <button type="button" class="btn" data-bs-toggle="modal" data-bs-target="#addModal">
                        <i class="fas fa-plus-circle"></i> New Type
                    </button>
                    <a href="locations.php" class="btn">
                        <i class="fas fa-map-marker-alt"></i> View Locations
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
            <i class="fas fa-tags"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $total_types; ?></h3>
            <p>Total Types</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon success">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $active_types; ?></h3>
            <p>Active Types</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon info">
            <i class="fas fa-map-marker-alt"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $total_locations_using; ?></h3>
            <p>Locations Using</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon warning">
            <i class="fas fa-boxes"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $total_equipment_all; ?></h3>
            <p>Total Equipment</p>
        </div>
    </div>
</div>

<!-- Location Types Grid -->
<?php if (empty($location_types)): ?>
<div class="empty-state">
    <div class="empty-state-icon">
        <i class="fas fa-tags"></i>
    </div>
    <h3>No Location Types Found</h3>
    <p>Get started by creating your first location type. Location types help you categorize different areas like Computer Labs, Kitchens, Offices, etc.</p>
    <button type="button" class="btn" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="fas fa-plus-circle me-2"></i>Create Your First Type
    </button>
</div>
<?php else: ?>
<div class="types-grid">
    <?php foreach ($location_types as $type): ?>
    <div class="type-card" style="--type-primary: <?php echo $type['color_primary']; ?>; --type-secondary: <?php echo $type['color_secondary']; ?>">
        <!-- Card Header with Gradient -->
        <div class="type-header">
            <div class="type-icon-wrapper">
                <i class="fas <?php echo $type['icon_class']; ?>"></i>
            </div>
            
            <div>
                <span class="type-code"><?php echo htmlspecialchars($type['type_code']); ?></span>
                <span class="campus-badge">
                    <i class="fas fa-university"></i>
                    <?php echo htmlspecialchars($type['campus']); ?>
                </span>
            </div>
            
            <h3 class="type-name"><?php echo htmlspecialchars($type['type_name']); ?></h3>
            
            <div class="type-menu">
                <div class="dropdown">
                    <button class="menu-btn" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-ellipsis-h"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item edit-btn" href="#" 
                                data-id="<?php echo $type['id']; ?>"
                                data-code="<?php echo htmlspecialchars($type['type_code']); ?>"
                                data-name="<?php echo htmlspecialchars($type['type_name']); ?>"
                                data-campus="<?php echo htmlspecialchars($type['campus']); ?>"
                                data-description="<?php echo htmlspecialchars($type['description'] ?? ''); ?>"
                                data-icon="<?php echo htmlspecialchars($type['icon_class']); ?>"
                                data-color-primary="<?php echo htmlspecialchars($type['color_primary']); ?>"
                                data-color-secondary="<?php echo htmlspecialchars($type['color_secondary']); ?>"
                                data-equipment-label="<?php echo htmlspecialchars($type['equipment_label']); ?>"
                                data-manager-title="<?php echo htmlspecialchars($type['manager_title']); ?>"
                                data-is-active="<?php echo $type['is_active']; ?>"
                                data-bs-toggle="modal" data-bs-target="#editModal">
                                <i class="fas fa-edit text-primary"></i> Edit
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item delete-btn" href="#" 
                               data-id="<?php echo $type['id']; ?>"
                               data-name="<?php echo htmlspecialchars($type['type_name']); ?>"
                               data-in-use="<?php echo $type['location_count'] > 0 ? 'true' : 'false'; ?>"
                               data-bs-toggle="modal" data-bs-target="#deleteModal">
                                <i class="fas fa-trash text-danger"></i> Delete
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Card Body -->
        <div class="type-body">
            <!-- Description -->
            <div class="type-description">
                <?php echo htmlspecialchars($type['description'] ?: 'No description provided.'); ?>
            </div>
            
            <!-- Info Grid -->
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Equipment Label</div>
                    <div class="info-value"><?php echo htmlspecialchars($type['equipment_label']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Manager Title</div>
                    <div class="info-value"><?php echo htmlspecialchars($type['manager_title']); ?></div>
                </div>
            </div>
            
            <!-- Usage Statistics -->
            <div class="stats-row">
                <div class="stat-box">
                    <div class="stat-value"><?php echo $type['location_count']; ?></div>
                    <div class="stat-label">Locations</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?php echo $type['total_equipment']; ?></div>
                    <div class="stat-label">Equipment</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value">
                        <span class="status-badge <?php echo $type['is_active'] ? 'active' : 'inactive'; ?>">
                            <i class="fas fa-<?php echo $type['is_active'] ? 'check-circle' : 'times-circle'; ?>"></i>
                            <?php echo $type['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </div>
                    <div class="stat-label">Status</div>
                </div>
            </div>
            
            <!-- Color Preview -->
            <div class="color-preview" style="background: linear-gradient(90deg, <?php echo $type['color_primary']; ?> 0%, <?php echo $type['color_secondary']; ?> 100%);"></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle me-2"></i>
                    Add New Location Type
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-layer-group me-2 text-success"></i>Type Code <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" name="type_code" required>
                                    <option value="" selected disabled>Select Floor</option>
                                    <option value="1st Floor">1st Floor</option>
                                    <option value="2nd Floor">2nd Floor</option>
                                    <option value="3rd Floor">3rd Floor</option>
                                    <option value="4th Floor">4th Floor</option>
                                    <option value="5th Floor">5th Floor</option>
                                    <option value="Ground Floor">Ground Floor</option>
                                    <option value="Basement">Basement</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-tag me-2 text-success"></i>Type Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" name="type_name" placeholder="e.g., Computer Laboratory" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-university me-2 text-success"></i>Campus <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" name="campus" required>
                            <option value="" selected disabled>Select Campus</option>
                            <option value="South Campus">South Campus</option>
                            <option value="Congressional Campus">Congressional Campus</option>
                            <option value="Bagong Silang Campus">Bagong Silang Campus</option>
                            <option value="Camarin Campus">Camarin Campus</option>
                            <option value="Main Campus">Main Campus</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-align-left me-2 text-success"></i>Description
                        </label>
                        <textarea class="form-control" name="description" rows="2" placeholder="Brief description of this location type..."></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-icons me-2 text-success"></i>Icon
                                </label>
                                <select class="form-select" name="icon_class">
                                    <option value="fa-desktop">Desktop</option>
                                    <option value="fa-flask">Flask (Lab)</option>
                                    <option value="fa-utensils">Utensils (Kitchen)</option>
                                    <option value="fa-briefcase">Briefcase (Office)</option>
                                    <option value="fa-tools">Tools (Workshop)</option>
                                    <option value="fa-chalkboard-teacher">Chalkboard (Classroom)</option>
                                    <option value="fa-book">Book (Library)</option>
                                    <option value="fa-users">Users (Meeting Room)</option>
                                    <option value="fa-building">Building</option>
                                    <option value="fa-warehouse">Warehouse</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-palette me-2 text-success"></i>Primary Color
                                </label>
                                <input type="color" class="form-control" name="color_primary" value="#2E7D32">
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-palette me-2 text-success"></i>Secondary Color
                                </label>
                                <input type="color" class="form-control" name="color_secondary" value="#4CAF50">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-box me-2 text-success"></i>Equipment Label
                                </label>
                                <input type="text" class="form-control" name="equipment_label" value="Equipment" placeholder="e.g., Units, Items">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-user-tie me-2 text-success"></i>Manager Title
                                </label>
                                <input type="text" class="form-control" name="manager_title" value="Manager" placeholder="e.g., Lab Manager">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Color Preview -->
                    <div class="color-preview" id="addColorPreview" style="background: linear-gradient(90deg, #2E7D32 0%, #4CAF50 100%);"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-2"></i>Save Location Type
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
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>
                    Edit Location Type
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-layer-group me-2 text-success"></i>Type Code <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" name="type_code" id="edit_code" required>
                                    <option value="1st Floor">1st Floor</option>
                                    <option value="2nd Floor">2nd Floor</option>
                                    <option value="3rd Floor">3rd Floor</option>
                                    <option value="4th Floor">4th Floor</option>
                                    <option value="5th Floor">5th Floor</option>
                                    <option value="Ground Floor">Ground Floor</option>
                                    <option value="Basement">Basement</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-tag me-2 text-success"></i>Type Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" name="type_name" id="edit_name" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-university me-2 text-success"></i>Campus <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" name="campus" id="edit_campus" required>
                            <option value="South Campus">South Campus</option>
                            <option value="Congressional Campus">Congressional Campus</option>
                            <option value="Bagong Silang Campus">Bagong Silang Campus</option>
                            <option value="Camarin Campus">Camarin Campus</option>
                            <option value="Main Campus">Main Campus</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-align-left me-2 text-success"></i>Description
                        </label>
                        <textarea class="form-control" name="description" id="edit_description" rows="2"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-icons me-2 text-success"></i>Icon
                                </label>
                                <select class="form-select" name="icon_class" id="edit_icon">
                                    <option value="fa-desktop">Desktop</option>
                                    <option value="fa-flask">Flask (Lab)</option>
                                    <option value="fa-utensils">Utensils (Kitchen)</option>
                                    <option value="fa-briefcase">Briefcase (Office)</option>
                                    <option value="fa-tools">Tools (Workshop)</option>
                                    <option value="fa-chalkboard-teacher">Chalkboard (Classroom)</option>
                                    <option value="fa-book">Book (Library)</option>
                                    <option value="fa-users">Users (Meeting Room)</option>
                                    <option value="fa-building">Building</option>
                                    <option value="fa-warehouse">Warehouse</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-palette me-2 text-success"></i>Primary Color
                                </label>
                                <input type="color" class="form-control" name="color_primary" id="edit_color_primary">
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-palette me-2 text-success"></i>Secondary Color
                                </label>
                                <input type="color" class="form-control" name="color_secondary" id="edit_color_secondary">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-box me-2 text-success"></i>Equipment Label
                                </label>
                                <input type="text" class="form-control" name="equipment_label" id="edit_equipment_label">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-user-tie me-2 text-success"></i>Manager Title
                                </label>
                                <input type="text" class="form-control" name="manager_title" id="edit_manager_title">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active" value="1" style="cursor: pointer; width: 3em; height: 1.5em;">
                            <label class="form-check-label fw-semibold" for="edit_is_active">
                                <i class="fas fa-toggle-on me-2 text-success"></i>Active Status
                            </label>
                            <div class="form-text">Enable or disable this location type</div>
                        </div>
                    </div>
                    
                    <!-- Color Preview -->
                    <div class="color-preview" id="editColorPreview"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-2"></i>Update Location Type
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
                    Delete Location Type
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_id">
                    <input type="hidden" name="in_use" id="delete_in_use">
                    
                    <div class="text-center mb-4">
                        <div class="text-danger mb-3">
                            <i class="fas fa-exclamation-triangle fa-4x"></i>
                        </div>
                        <h5 class="mb-3" id="delete_warning_title">Are you absolutely sure?</h5>
                        <p class="text-muted mb-2" id="delete_message">
                            This action cannot be undone. This will permanently delete the location type
                            <strong class="text-danger" id="delete_name"></strong>.
                        </p>
                        <div id="delete_warning" class="alert alert-warning mt-3" style="display: none;">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            This location type is currently in use and cannot be deleted.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="delete_confirm_btn">
                        <i class="fas fa-trash-alt me-2"></i>Delete Location Type
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Initialize location types functionality
window.initLocationTypes = function() {
    console.log('🏷️ Location Types page initialized');
    
    // Live color preview for add modal
    $('#addModal input[name="color_primary"], #addModal input[name="color_secondary"]').on('input', function() {
        const primary = $('#addModal input[name="color_primary"]').val();
        const secondary = $('#addModal input[name="color_secondary"]').val();
        $('#addColorPreview').css('background', `linear-gradient(90deg, ${primary} 0%, ${secondary} 100%)`);
    });
    
    // Live color preview for edit modal
    $('#edit_color_primary, #edit_color_secondary').on('input', function() {
        const primary = $('#edit_color_primary').val();
        const secondary = $('#edit_color_secondary').val();
        $('#editColorPreview').css('background', `linear-gradient(90deg, ${primary} 0%, ${secondary} 100%)`);
    });
    
    // Edit button click handler
    $(document).on('click', '.edit-btn', function(e) {
        e.preventDefault();
        
        // Get data from the clicked element
        const id = $(this).data('id');
        const code = $(this).data('code');
        const name = $(this).data('name');
        const campus = $(this).data('campus');
        const description = $(this).data('description');
        const icon = $(this).data('icon');
        const colorPrimary = $(this).data('color-primary');
        const colorSecondary = $(this).data('color-secondary');
        const equipmentLabel = $(this).data('equipment-label');
        const managerTitle = $(this).data('manager-title');
        const isActive = $(this).data('is-active');
        
        console.log('✏️ Edit location type:', { id, code, name, campus, icon });
        
        // Populate modal fields
        $('#edit_id').val(id);
        $('#edit_code').val(code);
        $('#edit_name').val(name);
        $('#edit_campus').val(campus);
        $('#edit_description').val(description);
        $('#edit_icon').val(icon);
        $('#edit_color_primary').val(colorPrimary);
        $('#edit_color_secondary').val(colorSecondary);
        $('#edit_equipment_label').val(equipmentLabel);
        $('#edit_manager_title').val(managerTitle);
        $('#edit_is_active').prop('checked', isActive == 1);
        
        // Update color preview
        $('#editColorPreview').css('background', `linear-gradient(90deg, ${colorPrimary} 0%, ${colorSecondary} 100%)`);
    });
    
    // Delete button click handler
    $(document).on('click', '.delete-btn', function(e) {
        e.preventDefault();
        
        const id = $(this).data('id');
        const name = $(this).data('name');
        const inUse = $(this).data('in-use') === 'true';
        
        console.log('🗑️ Delete location type:', { id, name, inUse });
        
        $('#delete_id').val(id);
        $('#delete_name').text(name);
        $('#delete_in_use').val(inUse);
        
        if (inUse) {
            $('#delete_warning').show();
            $('#delete_warning_title').text('Cannot Delete');
            $('#delete_message').hide();
            $('#delete_confirm_btn').prop('disabled', true);
        } else {
            $('#delete_warning').hide();
            $('#delete_warning_title').text('Are you absolutely sure?');
            $('#delete_message').show();
            $('#delete_confirm_btn').prop('disabled', false);
        }
    });
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut(500);
    }, 5000);
};

// Initialize on document ready
$(document).ready(function() {
    if (typeof window.initLocationTypes === 'function') {
        window.initLocationTypes();
    }
});
</script>

<?php include '../includes/footer.php'; ?>