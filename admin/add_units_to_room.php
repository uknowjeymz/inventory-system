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
$category = $_GET['category'] ?? '';

if (empty($room_id)) {
    header("Location: inventory_categories.php");
    exit();
}

// Get room details
$room_query = "SELECT l.*, lt.type_name, lt.type_code, lt.color_primary, lt.color_secondary 
               FROM locations l 
               LEFT JOIN location_types lt ON l.location_type_id = lt.id
               WHERE l.id = ?";
$room_stmt = $db->prepare($room_query);
$room_stmt->execute([$room_id]);
$room = $room_stmt->fetch(PDO::FETCH_ASSOC);

if (!$room) {
    header("Location: inventory_categories.php");
    exit();
}

// Handle form submission
if ($_POST && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'assign_equipment':
                $equipment_ids = $_POST['equipment_ids'] ?? [];
                $assigned_count = 0;
                
                foreach ($equipment_ids as $equipment_id) {
                    $update_query = "UPDATE computer_inventory SET location_id = ?, status = 'available' WHERE id = ? AND (location_id IS NULL OR location_id != ?)";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->execute([$room_id, $equipment_id, $room_id]);
                    
                    if ($update_stmt->rowCount() > 0) {
                        $assigned_count++;
                    }
                }
                
                $success = "{$assigned_count} equipment items have been assigned to " . htmlspecialchars($room['location_name']);
                break;
                
            case 'remove_equipment':
                $equipment_ids = $_POST['equipment_ids'] ?? [];
                $removed_count = 0;
                
                foreach ($equipment_ids as $equipment_id) {
                    $update_query = "UPDATE computer_inventory SET location_id = NULL, status = 'available' WHERE id = ? AND location_id = ?";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->execute([$equipment_id, $room_id]);
                    
                    if ($update_stmt->rowCount() > 0) {
                        $removed_count++;
                    }
                }
                
                $success = "{$removed_count} equipment items have been removed from " . htmlspecialchars($room['location_name']);
                break;
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get unassigned equipment (available for assignment)
$unassigned_query = "SELECT * FROM computer_inventory 
                     WHERE (location_id IS NULL OR location_id != ?) 
                     AND (is_condemned IS NULL OR is_condemned = FALSE)
                     ORDER BY item_number";
$unassigned_stmt = $db->prepare($unassigned_query);
$unassigned_stmt->execute([$room_id]);
$unassigned_equipment = $unassigned_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get equipment currently in this room
$room_equipment_query = "SELECT * FROM computer_inventory 
                        WHERE location_id = ? 
                        AND (is_condemned IS NULL OR is_condemned = FALSE)
                        ORDER BY item_number";
$room_equipment_stmt = $db->prepare($room_equipment_query);
$room_equipment_stmt->execute([$room_id]);
$room_equipment = $room_equipment_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Add Units to " . htmlspecialchars($room['location_name']);
include '../includes/header.php';
?>
<link rel="icon" type="image/x-icon" href="..\assets\UCC_Logo.ico">
<style>
.room-header {
    background: linear-gradient(135deg, <?php echo $room['color_primary'] ?? '#007bff'; ?> 0%, <?php echo $room['color_secondary'] ?? '#0056b3'; ?> 100%);
    color: white;
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 2rem;
}

.equipment-card {
    border: 1px solid #e9ecef;
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
    cursor: pointer;
}

.equipment-card:hover {
    border-color: #007bff;
    box-shadow: 0 4px 15px rgba(0, 123, 255, 0.1);
}

.equipment-card.selected {
    border-color: #007bff;
    background-color: rgba(0, 123, 255, 0.05);
}

.equipment-checkbox {
    transform: scale(1.2);
}

.breadcrumb-nav {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 10px;
    margin-bottom: 1.5rem;
}

.section-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid #f1f3f4;
    margin-bottom: 2rem;
}

.section-header {
    background: #f8f9fa;
    padding: 1.5rem;
    border-bottom: 1px solid #e9ecef;
    border-radius: 15px 15px 0 0;
}

.section-body {
    padding: 1.5rem;
}
</style>

<!-- Breadcrumb Navigation -->
<div class="breadcrumb-nav">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="inventory_categories.php">
                    <i class="fas fa-warehouse me-1"></i>Inventory Categories
                </a>
            </li>
            <?php if ($category): ?>
            <li class="breadcrumb-item">
                <a href="inventory_rooms.php?category=<?php echo urlencode($category); ?>">
                    <i class="fas fa-door-open me-1"></i>
                    <?php echo htmlspecialchars($room['type_name'] ?? 'Rooms'); ?>
                </a>
            </li>
            <?php endif; ?>
            <li class="breadcrumb-item">
                <a href="inventory_room_detail.php?room_id=<?php echo $room['id']; ?>&category=<?php echo urlencode($category); ?>">
                    <?php echo htmlspecialchars($room['location_name']); ?>
                </a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">Add Units</li>
        </ol>
    </nav>
</div>

<!-- Room Header -->
<div class="room-header">
    <div class="row align-items-center">
        <div class="col-md-8">
            <div class="d-flex align-items-center">
                <div class="me-4">
                    <i class="fas fa-plus-circle fa-4x"></i>
                </div>
                <div>
                    <h1 class="mb-2">Add Units to Room</h1>
                    <p class="mb-1 fs-5"><?php echo htmlspecialchars($room['location_name']); ?></p>
                    <small class="opacity-75">Assign or remove equipment from this room</small>
                </div>
            </div>
        </div>
        <div class="col-md-4 text-end">
            <a href="inventory_room_detail.php?room_id=<?php echo $room['id']; ?>&category=<?php echo urlencode($category); ?>" class="btn btn-outline-light">
                <i class="fas fa-arrow-left me-2"></i>Back to Room Details
            </a>
        </div>
    </div>
</div>

<?php if (isset($success)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <!-- Available Equipment -->
    <div class="col-lg-6">
        <div class="section-card">
            <div class="section-header">
                <h5 class="mb-0">
                    <i class="fas fa-plus me-2 text-success"></i>
                    Available Equipment (<?php echo count($unassigned_equipment); ?>)
                </h5>
                <small class="text-muted">Select equipment to assign to this room</small>
            </div>
            <div class="section-body">
                <?php if (!empty($unassigned_equipment)): ?>
                <form method="POST" id="assignForm">
                    <input type="hidden" name="action" value="assign_equipment">
                    
                    <div class="mb-3">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAll('assign')">
                            <i class="fas fa-check-square me-1"></i>Select All
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectNone('assign')">
                            <i class="fas fa-square me-1"></i>Select None
                        </button>
                    </div>
                    
                    <div class="equipment-list" style="max-height: 500px; overflow-y: auto;">
                        <?php foreach ($unassigned_equipment as $equipment): ?>
                        <div class="equipment-card" onclick="toggleEquipment(this, 'assign_<?php echo $equipment['id']; ?>')">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <input type="checkbox" class="form-check-input equipment-checkbox" 
                                           name="equipment_ids[]" value="<?php echo $equipment['id']; ?>" 
                                           id="assign_<?php echo $equipment['id']; ?>">
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-<?php echo $equipment['device_type'] === 'Laptop' ? 'laptop' : 'desktop'; ?> fa-2x text-primary"></i>
                                </div>
                                <div class="col">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($equipment['computer_set_description']); ?></h6>
                                    <div class="d-flex gap-2 mb-1">
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($equipment['item_number']); ?></span>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($equipment['device_type']); ?></span>
                                        <span class="badge bg-success"><?php echo ucfirst($equipment['status']); ?></span>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($equipment['processor']); ?> • 
                                        <?php echo htmlspecialchars($equipment['ram']); ?> • 
                                        <?php echo htmlspecialchars($equipment['storage']); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" class="btn btn-success" id="assignBtn" disabled>
                            <i class="fas fa-plus me-2"></i>Assign Selected to Room
                        </button>
                    </div>
                </form>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-inbox text-muted fa-3x mb-3"></i>
                    <h6 class="text-muted">No Available Equipment</h6>
                    <p class="text-muted">All equipment is already assigned to rooms.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Current Room Equipment -->
    <div class="col-lg-6">
        <div class="section-card">
            <div class="section-header">
                <h5 class="mb-0">
                    <i class="fas fa-minus me-2 text-warning"></i>
                    Current Room Equipment (<?php echo count($room_equipment); ?>)
                </h5>
                <small class="text-muted">Equipment currently in <?php echo htmlspecialchars($room['location_name']); ?></small>
            </div>
            <div class="section-body">
                <?php if (!empty($room_equipment)): ?>
                <form method="POST" id="removeForm">
                    <input type="hidden" name="action" value="remove_equipment">
                    
                    <div class="mb-3">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAll('remove')">
                            <i class="fas fa-check-square me-1"></i>Select All
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectNone('remove')">
                            <i class="fas fa-square me-1"></i>Select None
                        </button>
                    </div>
                    
                    <div class="equipment-list" style="max-height: 500px; overflow-y: auto;">
                        <?php foreach ($room_equipment as $equipment): ?>
                        <div class="equipment-card" onclick="toggleEquipment(this, 'remove_<?php echo $equipment['id']; ?>')">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <input type="checkbox" class="form-check-input equipment-checkbox" 
                                           name="equipment_ids[]" value="<?php echo $equipment['id']; ?>" 
                                           id="remove_<?php echo $equipment['id']; ?>">
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-<?php echo $equipment['device_type'] === 'Laptop' ? 'laptop' : 'desktop'; ?> fa-2x text-primary"></i>
                                </div>
                                <div class="col">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($equipment['computer_set_description']); ?></h6>
                                    <div class="d-flex gap-2 mb-1">
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($equipment['item_number']); ?></span>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($equipment['device_type']); ?></span>
                                        <span class="badge bg-<?php echo $equipment['status'] === 'available' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($equipment['status']); ?>
                                        </span>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($equipment['processor']); ?> • 
                                        <?php echo htmlspecialchars($equipment['ram']); ?> • 
                                        <?php echo htmlspecialchars($equipment['storage']); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" class="btn btn-warning" id="removeBtn" disabled>
                            <i class="fas fa-minus me-2"></i>Remove Selected from Room
                        </button>
                    </div>
                </form>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-desktop text-muted fa-3x mb-3"></i>
                    <h6 class="text-muted">No Equipment in Room</h6>
                    <p class="text-muted">This room doesn't have any equipment assigned yet.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function toggleEquipment(card, checkboxId) {
    const checkbox = document.getElementById(checkboxId);
    checkbox.checked = !checkbox.checked;
    
    if (checkbox.checked) {
        card.classList.add('selected');
    } else {
        card.classList.remove('selected');
    }
    
    updateButtons();
}

function selectAll(type) {
    const checkboxes = document.querySelectorAll(`input[id^="${type}_"]`);
    const cards = document.querySelectorAll('.equipment-card');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
    
    cards.forEach(card => {
        card.classList.add('selected');
    });
    
    updateButtons();
}

function selectNone(type) {
    const checkboxes = document.querySelectorAll(`input[id^="${type}_"]`);
    const cards = document.querySelectorAll('.equipment-card');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    
    cards.forEach(card => {
        card.classList.remove('selected');
    });
    
    updateButtons();
}

function updateButtons() {
    const assignCheckboxes = document.querySelectorAll('input[id^="assign_"]:checked');
    const removeCheckboxes = document.querySelectorAll('input[id^="remove_"]:checked');
    
    const assignBtn = document.getElementById('assignBtn');
    const removeBtn = document.getElementById('removeBtn');
    
    if (assignBtn) {
        assignBtn.disabled = assignCheckboxes.length === 0;
    }
    
    if (removeBtn) {
        removeBtn.disabled = removeCheckboxes.length === 0;
    }
}

// Initialize button states
document.addEventListener('DOMContentLoaded', function() {
    updateButtons();
    
    // Add event listeners to all checkboxes
    const checkboxes = document.querySelectorAll('.equipment-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateButtons);
    });
});
</script>

<?php include '../includes/footer.php'; ?>