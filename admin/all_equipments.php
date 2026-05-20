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

if (empty($room_id) || empty($category)) {
    header("Location: inventory_categories.php");
    exit();
}

// Get category details
$category_query = "SELECT * FROM location_types WHERE type_code = ? AND is_active = 1";
$category_stmt = $db->prepare($category_query);
$category_stmt->execute([$category]);
$category_info = $category_stmt->fetch(PDO::FETCH_ASSOC);

if (!$category_info) {
    header("Location: inventory_categories.php");
    exit();
}

// Get room details with manager info
$room_query = "SELECT l.*, 
               f.full_name as manager_name, 
               f.email as manager_email,
               lt.type_name as location_type_name,
               lt.type_code as location_type_code,
               lt.icon_class as location_type_icon,
               lt.color_primary as location_type_color_primary,
               lt.color_secondary as location_type_color_secondary,
               lt.equipment_label as location_type_equipment_label
               FROM locations l 
               LEFT JOIN users f ON l.facilitator_id = f.id
               LEFT JOIN location_types lt ON l.location_type_id = lt.id
               WHERE l.id = ? AND (l.location_type = ? OR lt.type_code = ?)";
$room_stmt = $db->prepare($room_query);
$room_stmt->execute([$room_id, $category, $category]);
$room = $room_stmt->fetch(PDO::FETCH_ASSOC);

if (!$room) {
    header("Location: inventory_rooms.php?category=" . urlencode($category));
    exit();
}

// Handle equipment edit form submission
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'edit_equipment') {
    try {
        $equipment_id = $_POST['equipment_id'];
        $equipment_type = $_POST['equipment_type']; // Add this to know which table to update
        
        // Determine which table to update based on equipment_type
        $table_name = '';
        switch ($equipment_type) {
            case 'computer':
                $table_name = 'computer_inventory';
                break;
            case 'kitchen':
                $table_name = 'kitchen_equipment'; // Assuming this table exists
                break;
            case 'general':
                $table_name = 'general_equipment'; // Assuming this table exists
                break;
            default:
                $table_name = 'computer_inventory';
        }
        
        // Generic update query that should work for most equipment types
        $update_query = "UPDATE $table_name SET 
                        item_number = ?, 
                        description = ?, 
                        status = ?, 
                        condition_status = ?, 
                        remarks = ?,
                        updated_at = NOW()
                        WHERE id = ?";
        
        $update_stmt = $db->prepare($update_query);
        $update_stmt->execute([
            $_POST['item_number'],
            $_POST['description'],
            $_POST['status'],
            $_POST['condition_status'],
            $_POST['remarks'],
            $equipment_id
        ]);
        
        $success = "Equipment updated successfully!";
    } catch (Exception $e) {
        $error = "Error updating equipment: " . $e->getMessage();
    }
}

// Define available equipment tables with their details
$equipment_tables = [
    'computer_inventory' => [
        'name' => 'Computer Equipment',
        'icon' => 'fa-desktop',
        'label_singular' => 'Computer',
        'status_column' => 'status',
        'condition_column' => 'condition_status'
    ],
    'kitchen_equipment' => [
        'name' => 'Kitchen Equipment',
        'icon' => 'fa-utensils',
        'label_singular' => 'Kitchen Item',
        'status_column' => 'status',
        'condition_column' => 'condition'
    ],
    'general_equipment' => [
        'name' => 'General Equipment',
        'icon' => 'fa-box',
        'label_singular' => 'General Item',
        'status_column' => 'status',
        'condition_column' => 'condition_status'
    ]
    // Add more tables as needed
];

// Get all equipment from ALL tables for this location
$all_equipment = [];
$equipment_count = 0;
$available_count = 0;
$assigned_count = 0;

foreach ($equipment_tables as $table_name => $table_info) {
    try {
        // Check if table exists
        $check_table = $db->query("SHOW TABLES LIKE '$table_name'")->fetch();
        
        if ($check_table) {
            // Get column names to build dynamic query
            $columns_query = "SHOW COLUMNS FROM $table_name";
            $columns_stmt = $db->query($columns_query);
            $columns = $columns_stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Check if table has location_id column
            if (in_array('location_id', $columns)) {
                // Build SELECT query based on available columns
                $select_columns = [];
                $base_columns = [
                    'id',
                    'item_number',
                    'description',
                    'status',
                    'condition_status',
                    'condition', // Some tables might use different column names
                    'remarks',
                    'updated_at',
                    'location_id'
                ];
                
                foreach ($base_columns as $col) {
                    if (in_array($col, $columns)) {
                        $select_columns[] = $col;
                    }
                }
                
                if (in_array('computer_name', $columns)) {
                    $select_columns[] = 'computer_name';
                }
                if (in_array('assigned_to', $columns)) {
                    $select_columns[] = 'assigned_to';
                }
                if (in_array('serial_number', $columns)) {
                    $select_columns[] = 'serial_number';
                }
                if (in_array('device_type', $columns)) {
                    $select_columns[] = 'device_type';
                }
                
                $select_query = "SELECT " . implode(', ', $select_columns) . " FROM $table_name WHERE location_id = ? AND (is_condemned IS NULL OR is_condemned = FALSE OR is_condemned = 0)";
                
                $stmt = $db->prepare($select_query);
                $stmt->execute([$room_id]);
                $equipment_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Add table information to each item
                foreach ($equipment_items as &$item) {
                    $item['equipment_type'] = $table_name;
                    $item['table_name'] = $table_info['name'];
                    $item['icon'] = $table_info['icon'];
                    $item['label_singular'] = $table_info['label_singular'];
                    
                    // Get assigned user name if available
                    if (isset($item['assigned_to']) && $item['assigned_to']) {
                        $user_query = "SELECT full_name FROM users WHERE id = ?";
                        $user_stmt = $db->prepare($user_query);
                        $user_stmt->execute([$item['assigned_to']]);
                        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                        $item['assigned_to_name'] = $user['full_name'] ?? null;
                    }
                }
                
                $all_equipment = array_merge($all_equipment, $equipment_items);
                
                // Get counts for this table
                $count_query = "SELECT 
                               COUNT(*) as total,
                               SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
                               SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as assigned
                               FROM $table_name 
                               WHERE location_id = ? AND (is_condemned IS NULL OR is_condemned = FALSE OR is_condemned = 0)";
                
                $count_stmt = $db->prepare($count_query);
                $count_stmt->execute([$room_id]);
                $counts = $count_stmt->fetch(PDO::FETCH_ASSOC);
                
                $equipment_count += $counts['total'] ?? 0;
                $available_count += $counts['available'] ?? 0;
                $assigned_count += $counts['assigned'] ?? 0;
            }
        }
    } catch (Exception $e) {
        // Table might not exist, skip it
        continue;
    }
}

// Sort all equipment by updated_at (most recent first)
usort($all_equipment, function($a, $b) {
    $dateA = strtotime($a['updated_at'] ?? '1970-01-01');
    $dateB = strtotime($b['updated_at'] ?? '1970-01-01');
    return $dateB - $dateA;
});

// Get recent activities (first 10 items)
$recent_activities = array_slice($all_equipment, 0, 10);

$page_title = htmlspecialchars($room['location_name']) . " - " . htmlspecialchars($category_info['type_name']) . " Room Details";
include '../includes/header.php';
?>

<!-- ... (Keep your existing HTML structure until the equipment list section) ... -->

<!-- Equipment List -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-boxes me-2"></i>
                    All Equipment in this Room (<?php echo count($all_equipment); ?>)
                </h5>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-primary" onclick="toggleView('grid')">
                        <i class="fas fa-th me-1"></i>Grid
                    </button>
                    <button class="btn btn-sm btn-primary" onclick="toggleView('list')">
                        <i class="fas fa-list me-1"></i>List
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($all_equipment)): ?>
                
                <!-- List View (Default) -->
                <div id="listView" class="equipment-list">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Type</th>
                                    <th>Equipment Name</th>
                                    <th>Item Number</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                    <th>Condition</th>
                                    <th>Assigned To</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_equipment as $equipment): ?>
                                <?php 
                                $status_color = $equipment['status'] === 'available' ? 'success' : 
                                              ($equipment['status'] === 'assigned' ? 'warning' : 
                                              ($equipment['status'] === 'maintenance' ? 'info' : 'secondary'));
                                
                                $condition = $equipment['condition_status'] ?? $equipment['condition'] ?? 'Unknown';
                                $condition_color = $condition === 'Excellent' ? 'success' : 
                                                 ($condition === 'Good' ? 'primary' : 
                                                 ($condition === 'Fair' ? 'warning' : 
                                                 ($condition === 'Poor' ? 'danger' : 'secondary')));
                                ?>
                                <tr class="equipment-row" style="cursor: pointer;" onclick="showEquipmentDetails(<?php echo $equipment['id']; ?>, '<?php echo $equipment['equipment_type']; ?>')">
                                    <td>
                                        <span class="badge bg-primary">
                                            <i class="fas <?php echo $equipment['icon']; ?> me-1"></i>
                                            <?php echo htmlspecialchars($equipment['label_singular']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="equipment-icon me-3">
                                                <i class="fas <?php echo $equipment['icon']; ?> fa-lg text-primary"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($equipment['computer_name'] ?? $equipment['item_number'] ?? 'Equipment #' . $equipment['id']); ?></h6>
                                                <?php if (isset($equipment['device_type'])): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($equipment['device_type']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($equipment['item_number'] ?? 'N/A'); ?></span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars(substr($equipment['description'] ?? 'No description', 0, 50)); ?>
                                        <?php if (strlen($equipment['description'] ?? '') > 50): ?>...<?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $status_color; ?>">
                                            <?php echo ucfirst($equipment['status'] ?? 'Unknown'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $condition_color; ?>">
                                            <?php echo ucfirst($condition); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($equipment['assigned_to_name'])): ?>
                                            <span class="text-success">
                                                <i class="fas fa-user me-1"></i>
                                                <?php echo htmlspecialchars($equipment['assigned_to_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">
                                                <i class="fas fa-user-slash me-1"></i>
                                                Unassigned
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation(); showEquipmentDetails(<?php echo $equipment['id']; ?>, '<?php echo $equipment['equipment_type']; ?>')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Grid View -->
                <div id="gridView" class="equipment-grid" style="display: none;">
                    <div class="row">
                        <?php foreach ($all_equipment as $equipment): ?>
                        <?php 
                        $status_color = $equipment['status'] === 'available' ? 'success' : 
                                      ($equipment['status'] === 'assigned' ? 'warning' : 
                                      ($equipment['status'] === 'maintenance' ? 'info' : 'secondary'));
                        
                        $condition = $equipment['condition_status'] ?? $equipment['condition'] ?? 'Unknown';
                        $condition_color = $condition === 'Excellent' ? 'success' : 
                                         ($condition === 'Good' ? 'primary' : 
                                         ($condition === 'Fair' ? 'warning' : 
                                         ($condition === 'Poor' ? 'danger' : 'secondary')));
                        ?>
                        <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
                            <div class="equipment-card" onclick="showEquipmentDetails(<?php echo $equipment['id']; ?>, '<?php echo $equipment['equipment_type']; ?>')">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <div class="mb-2">
                                            <span class="badge bg-primary">
                                                <i class="fas <?php echo $equipment['icon']; ?> me-1"></i>
                                                <?php echo htmlspecialchars($equipment['label_singular']); ?>
                                            </span>
                                        </div>
                                        <div class="equipment-icon mb-3">
                                            <i class="fas <?php echo $equipment['icon']; ?> fa-3x text-primary"></i>
                                        </div>
                                        <h6 class="card-title"><?php echo htmlspecialchars($equipment['computer_name'] ?? $equipment['item_number'] ?? 'Equipment #' . $equipment['id']); ?></h6>
                                        <p class="card-text">
                                            <small class="text-muted"><?php echo htmlspecialchars(substr($equipment['description'] ?? 'No description', 0, 50)); ?></small><br>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($equipment['item_number'] ?? 'N/A'); ?></span>
                                        </p>
                                        <div class="d-flex justify-content-center gap-2 mb-2">
                                            <span class="badge bg-<?php echo $status_color; ?>">
                                                <?php echo ucfirst($equipment['status'] ?? 'Unknown'); ?>
                                            </span>
                                            <span class="badge bg-<?php echo $condition_color; ?>">
                                                <?php echo ucfirst($condition); ?>
                                            </span>
                                        </div>
                                        <?php if (!empty($equipment['assigned_to_name'])): ?>
                                            <small class="text-success">
                                                <i class="fas fa-user me-1"></i>
                                                <?php echo htmlspecialchars($equipment['assigned_to_name']); ?>
                                            </small>
                                        <?php else: ?>
                                            <small class="text-muted">
                                                <i class="fas fa-user-slash me-1"></i>
                                                Unassigned
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-boxes text-muted fa-4x mb-3"></i>
                    <h5 class="text-muted">No Equipment Found</h5>
                    <p class="text-muted">There are no equipment items assigned to this room yet.</p>
                    <a href="add_units_to_room.php?room_id=<?php echo $room['id']; ?>&category=<?php echo urlencode($category); ?>" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add Equipment
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Equipment Details Modal -->
<div class="modal fade" id="equipmentDetailsModal" tabindex="-1" aria-labelledby="equipmentDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="equipmentDetailsModalLabel">Equipment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="equipmentDetailsContent">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="editEquipmentBtn">
                    <i class="fas fa-edit me-2"></i>Edit Equipment
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Equipment details modal functionality
function showEquipmentDetails(equipmentId, equipmentType) {
    const modal = new bootstrap.Modal(document.getElementById('equipmentDetailsModal'));
    const modalContent = document.getElementById('equipmentDetailsContent');
    const editBtn = document.getElementById('editEquipmentBtn');
    
    // Show loading spinner
    modalContent.innerHTML = `
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `;
    
    modal.show();
    
    // Make AJAX call to get equipment details
    fetch(`get_equipment_details.php?id=${equipmentId}&type=${equipmentType}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const equipment = data.data;
                
                // Build detailed view
                const statusColor = equipment.status === 'available' ? 'success' : 
                                   (equipment.status === 'assigned' ? 'warning' : 
                                   (equipment.status === 'maintenance' ? 'info' : 'secondary'));
                
                const condition = equipment.condition_status || equipment.condition || 'Unknown';
                const conditionColor = condition === 'Excellent' ? 'success' : 
                                     (condition === 'Good' ? 'primary' : 
                                     (condition === 'Fair' ? 'warning' : 
                                     (condition === 'Poor' ? 'danger' : 'secondary')));
                
                modalContent.innerHTML = `
                    <div class="row">
                        <div class="col-md-4 text-center">
                            <div class="equipment-icon-large mb-3">
                                <i class="fas ${equipment.icon || 'fa-box'} fa-5x text-primary"></i>
                            </div>
                            <h5>${equipment.computer_name || equipment.item_number || 'Equipment #' + equipment.id}</h5>
                            <span class="badge bg-primary fs-6">
                                <i class="fas ${equipment.icon || 'fa-box'} me-1"></i>
                                ${equipment.label_singular || 'Equipment'}
                            </span>
                        </div>
                        <div class="col-md-8">
                            <div class="row">
                                <div class="col-sm-6 mb-3">
                                    <label class="form-label fw-bold">Status</label>
                                    <div><span class="badge bg-${statusColor}">${equipment.status ? equipment.status.charAt(0).toUpperCase() + equipment.status.slice(1) : 'Unknown'}</span></div>
                                </div>
                                <div class="col-sm-6 mb-3">
                                    <label class="form-label fw-bold">Condition</label>
                                    <div><span class="badge bg-${conditionColor}">${condition}</span></div>
                                </div>
                                <div class="col-sm-6 mb-3">
                                    <label class="form-label fw-bold">Item Number</label>
                                    <div>${equipment.item_number || 'N/A'}</div>
                                </div>
                                <div class="col-sm-6 mb-3">
                                    <label class="form-label fw-bold">Assigned To</label>
                                    <div>${equipment.assigned_to_name ? `<span class="text-success"><i class="fas fa-user me-1"></i>${equipment.assigned_to_name}</span>` : '<span class="text-muted"><i class="fas fa-user-slash me-1"></i>Unassigned</span>'}</div>
                                </div>
                            </div>
                            
                            ${equipment.description ? `
                            <div class="mb-3">
                                <label class="form-label fw-bold">Description</label>
                                <div class="alert alert-light">${equipment.description}</div>
                            </div>
                            ` : ''}
                            
                            <div class="row">
                                ${equipment.device_type ? `
                                <div class="col-sm-6 mb-3">
                                    <label class="form-label fw-bold">Device Type</label>
                                    <div>${equipment.device_type}</div>
                                </div>
                                ` : ''}
                                
                                ${equipment.serial_number ? `
                                <div class="col-sm-6 mb-3">
                                    <label class="form-label fw-bold">Serial Number</label>
                                    <div><code>${equipment.serial_number}</code></div>
                                </div>
                                ` : ''}
                                
                                ${equipment.processor ? `
                                <div class="col-sm-6 mb-3">
                                    <label class="form-label fw-bold">Processor</label>
                                    <div>${equipment.processor}</div>
                                </div>
                                ` : ''}
                                
                                ${equipment.ram ? `
                                <div class="col-sm-6 mb-3">
                                    <label class="form-label fw-bold">RAM</label>
                                    <div>${equipment.ram}</div>
                                </div>
                                ` : ''}
                            </div>
                            
                            ${equipment.updated_at ? `
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <label class="form-label fw-bold">Last Updated</label>
                                    <div>${new Date(equipment.updated_at).toLocaleDateString()}</div>
                                </div>
                            </div>
                            ` : ''}
                            
                            ${equipment.remarks ? `
                            <div class="row">
                                <div class="col-12">
                                    <label class="form-label fw-bold">Remarks</label>
                                    <div class="alert alert-info">${equipment.remarks}</div>
                                </div>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                `;
                
                // Store current equipment data for edit modal
                currentEquipment = {
                    id: equipmentId,
                    type: equipmentType,
                    ...equipment
                };
                
                // Set up edit button
                editBtn.onclick = function() {
                    showEditModal();
                };
                
            } else {
                modalContent.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        ${data.message || 'Equipment details could not be loaded.'}
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            modalContent.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Error loading equipment details.
                </div>
            `;
        });
}

// View toggle functionality (keep existing)
function toggleView(viewType) {
    const listView = document.getElementById('listView');
    const gridView = document.getElementById('gridView');
    const listBtn = document.querySelector('button[onclick="toggleView(\'list\')"]');
    const gridBtn = document.querySelector('button[onclick="toggleView(\'grid\')"]');
    
    if (viewType === 'list') {
        listView.style.display = 'block';
        gridView.style.display = 'none';
        listBtn.classList.remove('btn-outline-primary');
        listBtn.classList.add('btn-primary');
        gridBtn.classList.remove('btn-primary');
        gridBtn.classList.add('btn-outline-primary');
    } else {
        listView.style.display = 'none';
        gridView.style.display = 'block';
        gridBtn.classList.remove('btn-outline-primary');
        gridBtn.classList.add('btn-primary');
        listBtn.classList.remove('btn-primary');
        listBtn.classList.add('btn-outline-primary');
    }
    
    // Save preference to localStorage
    localStorage.setItem('equipmentViewPreference', viewType);
}
</script>