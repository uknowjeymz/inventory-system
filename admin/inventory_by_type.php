<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get the location type from URL parameter
$location_type = $_GET['type'] ?? 'computer_lab';

// Get location type details
$type_query = "SELECT * FROM location_types WHERE type_code = ?";
$type_stmt = $db->prepare($type_query);
$type_stmt->execute([$location_type]);
$type_info = $type_stmt->fetch(PDO::FETCH_ASSOC);

// Fallback if location_types table doesn't exist or type not found
if (!$type_info) {
    $type_info = [
        'type_name' => ucfirst(str_replace('_', ' ', $location_type)),
        'icon_class' => 'fa-building',
        'color_primary' => '#008543',
        'color_secondary' => '#20c997',
        'equipment_label' => 'Equipment'
    ];
}

// Get locations of this specific type with equipment counts
$query = "SELECT l.*, 
          COUNT(ci.id) as equipment_count,
          SUM(CASE WHEN ci.status = 'available' THEN 1 ELSE 0 END) as available_count,
          SUM(CASE WHEN ci.status = 'assigned' THEN 1 ELSE 0 END) as assigned_count,
          f.full_name as facilitator_name, f.email as facilitator_email
          FROM locations l 
          LEFT JOIN computer_inventory ci ON l.id = ci.location_id 
          LEFT JOIN users f ON l.facilitator_id = f.id
          WHERE l.location_type = ? OR l.location_type_id = (SELECT id FROM location_types WHERE type_code = ? LIMIT 1)
          GROUP BY l.id 
          ORDER BY l.location_name";
$stmt = $db->prepare($query);
$stmt->execute([$location_type, $location_type]);
$locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = $type_info['type_name'] . " Inventory";
include '../includes/header.php';
?>

<style>
.location-type-header {
    background: linear-gradient(135deg, <?php echo $type_info['color_primary']; ?> 0%, <?php echo $type_info['color_secondary']; ?> 100%);
    color: white;
    border-radius: 15px;
    padding: 2rem;
    margin-bottom: 2rem;
}

.location-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid #f1f3f4;
    overflow: hidden;
    transition: all 0.3s ease;
    height: 100%;
}

.location-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.location-header {
    background: linear-gradient(135deg, <?php echo $type_info['color_primary']; ?> 0%, <?php echo $type_info['color_secondary']; ?> 100%);
    color: white;
    padding: 1.5rem;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    border: 1px solid #f1f3f4;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
}

.breadcrumb-nav {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 10px;
    margin-bottom: 1.5rem;
}
</style>

<!-- Breadcrumb Navigation -->
<div class="breadcrumb-nav">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="inventory_monitor.php">Inventory Overview</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($type_info['type_name']); ?></li>
        </ol>
    </nav>
</div>

<!-- Header Section -->
<div class="location-type-header">
    <div class="row align-items-center">
        <div class="col-md-8">
            <div class="d-flex align-items-center">
                <div class="me-4">
                    <i class="fas <?php echo $type_info['icon_class']; ?> fa-3x"></i>
                </div>
                <div>
                    <h2 class="mb-2"><?php echo htmlspecialchars($type_info['type_name']); ?> Inventory</h2>
                    <p class="mb-0 opacity-75">Manage <?php echo strtolower($type_info['equipment_label']); ?> across all <?php echo strtolower($type_info['type_name']); ?> locations</p>
                </div>
            </div>
        </div>
        <div class="col-md-4 text-end">
            <div class="d-flex gap-2 justify-content-end">
                <a href="locations.php" class="btn btn-outline-light">
                    <i class="fas fa-map-marker-alt me-2"></i>Manage Locations
                </a>
                <?php if ($location_type == 'computer_lab'): ?>
                <a href="computers.php" class="btn btn-light">
                    <i class="fas fa-desktop me-2"></i>Add Equipment
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (empty($locations)): ?>
<!-- No Locations Found -->
<div class="alert alert-info">
    <div class="d-flex align-items-center">
        <i class="fas fa-info-circle fa-2x me-3"></i>
        <div>
            <h5 class="mb-1">No <?php echo htmlspecialchars($type_info['type_name']); ?> Locations Found</h5>
            <p class="mb-0">There are currently no locations of this type in the system. <a href="locations.php">Add a new location</a> to get started.</p>
        </div>
    </div>
</div>
<?php else: ?>

<!-- Statistics Overview -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="mb-2">
                <i class="fas fa-building fa-2x" style="color: <?php echo $type_info['color_primary']; ?>;"></i>
            </div>
            <h3 class="mb-1"><?php echo count($locations); ?></h3>
            <p class="text-muted mb-0">Total Locations</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="mb-2">
                <i class="fas fa-boxes fa-2x text-primary"></i>
            </div>
            <h3 class="mb-1"><?php echo array_sum(array_column($locations, 'equipment_count')); ?></h3>
            <p class="text-muted mb-0">Total <?php echo $type_info['equipment_label']; ?></p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="mb-2">
                <i class="fas fa-check-circle fa-2x text-success"></i>
            </div>
            <h3 class="mb-1"><?php echo array_sum(array_column($locations, 'available_count')); ?></h3>
            <p class="text-muted mb-0">Available</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="mb-2">
                <i class="fas fa-user-check fa-2x text-info"></i>
            </div>
            <h3 class="mb-1"><?php echo array_sum(array_column($locations, 'assigned_count')); ?></h3>
            <p class="text-muted mb-0">Assigned</p>
        </div>
    </div>
</div>

<!-- Locations Grid -->
<div class="row">
    <?php foreach ($locations as $location): ?>
    <div class="col-xl-4 col-lg-6 mb-4">
        <div class="location-card">
            <!-- Location Header -->
            <div class="location-header">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="mb-1"><?php echo htmlspecialchars($location['location_name']); ?></h5>
                        <p class="mb-0 opacity-75"><?php echo htmlspecialchars($location['description'] ?? 'No description'); ?></p>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-light" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-h"></i>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="locations.php">
                                <i class="fas fa-edit me-2"></i>Edit Location
                            </a></li>
                            <?php if ($location_type == 'computer_lab'): ?>
                            <li><a class="dropdown-item" href="computers.php?location_id=<?php echo $location['id']; ?>">
                                <i class="fas fa-desktop me-2"></i>Manage Equipment
                            </a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Location Details -->
            <div class="p-3">
                <!-- Facilitator Info -->
                <?php if ($location['facilitator_name']): ?>
                <div class="d-flex align-items-center mb-3 p-2 bg-light rounded">
                    <div class="me-3">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <?php 
                            $initials = '';
                            $name_parts = explode(' ', $location['facilitator_name']);
                            foreach ($name_parts as $part) {
                                $initials .= strtoupper(substr($part, 0, 1));
                            }
                            echo htmlspecialchars(substr($initials, 0, 2));
                            ?>
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-0"><?php echo htmlspecialchars($location['facilitator_name']); ?></h6>
                        <small class="text-muted"><?php echo htmlspecialchars($location['facilitator_email']); ?></small>
                    </div>
                </div>
                <?php else: ?>
                <div class="text-center mb-3 p-2 bg-warning bg-opacity-10 rounded">
                    <i class="fas fa-user-slash text-warning"></i>
                    <small class="text-muted d-block">No facilitator assigned</small>
                </div>
                <?php endif; ?>
                
                <!-- Equipment Statistics -->
                <div class="row text-center">
                    <div class="col-4">
                        <div class="border-end">
                            <h4 class="mb-0 text-primary"><?php echo $location['equipment_count']; ?></h4>
                            <small class="text-muted">Total</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="border-end">
                            <h4 class="mb-0 text-success"><?php echo $location['available_count']; ?></h4>
                            <small class="text-muted">Available</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <h4 class="mb-0 text-info"><?php echo $location['assigned_count']; ?></h4>
                        <small class="text-muted">Assigned</small>
                    </div>
                </div>
                
                <!-- Capacity Progress -->
                <?php if ($location['capacity'] > 0): ?>
                <div class="mt-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small class="text-muted">Capacity Usage</small>
                        <small class="text-muted">
                            <?php 
                            $usage_percent = ($location['equipment_count'] / $location['capacity']) * 100;
                            echo round($usage_percent, 1);
                            ?>%
                        </small>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <?php 
                        $progress_class = $usage_percent > 80 ? 'bg-danger' : ($usage_percent > 60 ? 'bg-warning' : 'bg-success');
                        ?>
                        <div class="progress-bar <?php echo $progress_class; ?>" 
                             style="width: <?php echo min($usage_percent, 100); ?>%"></div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Action Buttons -->
                <div class="mt-3 d-grid gap-2">
                    <?php if ($location_type == 'computer_lab'): ?>
                    <a href="computers.php?location_id=<?php echo $location['id']; ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-desktop me-2"></i>View Equipment Details
                    </a>
                    <?php else: ?>
                    <a href="inventory.php?location_id=<?php echo $location['id']; ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-boxes me-2"></i>View Equipment Details
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>

<!-- Quick Actions -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <a href="locations.php" class="btn btn-outline-primary w-100 mb-2">
                            <i class="fas fa-plus me-2"></i>Add New Location
                        </a>
                    </div>
                    <?php if ($location_type == 'computer_lab'): ?>
                    <div class="col-md-3">
                        <a href="computers.php" class="btn btn-outline-success w-100 mb-2">
                            <i class="fas fa-desktop me-2"></i>Add Equipment
                        </a>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-3">
                        <a href="location_types.php" class="btn btn-outline-info w-100 mb-2">
                            <i class="fas fa-tags me-2"></i>Manage Types
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="inventory_monitor.php" class="btn btn-outline-secondary w-100 mb-2">
                            <i class="fas fa-chart-bar me-2"></i>Full Overview
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>