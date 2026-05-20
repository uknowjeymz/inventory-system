<?php
session_start();
require_once '../config/database.php';
$db = (new Database())->getConnection();

$id = $_GET['id'];
$type = $_GET['type'];
$table_map = [
    'computer' => 'computer_inventory', 'computer_lab' => 'computer_inventory',
    'kitchen' => 'kitchen_equipment', 'office' => 'office_equipment',
    'lab' => 'lab_equipment', 'regular_lab' => 'lab_equipment',
    'general' => 'general_equipment'
];
$table = $table_map[$type];

$stmt = $db->prepare("SELECT * FROM $table WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

// Parse the full name from remarks into components (Last Name, First Name, Middle Initial)
$lastname = '';
$firstname = '';
$middle = '';

if (!empty($item['remarks'])) {
    $fullname = $item['remarks'];
    
    if (strpos($fullname, ',') !== false) {
        $parts = explode(',', $fullname, 2);
        $lastname = trim($parts[0]);
        
        if (isset($parts[1])) {
            $first_part = trim($parts[1]);
            $name_parts = explode(' ', $first_part);
            if (count($name_parts) > 1) {
                $firstname = trim($name_parts[0]);
                $possible_middle = trim($name_parts[count($name_parts) - 1]);
                if (strlen($possible_middle) <= 3 && (strpos($possible_middle, '.') !== false || ctype_upper($possible_middle))) {
                    $middle = $possible_middle;
                    array_pop($name_parts);
                    $firstname = trim(implode(' ', $name_parts));
                }
            } else {
                $firstname = $first_part;
            }
        }
    } else {
        $firstname = $fullname;
    }
}

$exclude = ['id', 'created_at', 'updated_at', 'is_condemned', 'condemned_date', 'condemned_reason', 'condemned_by', 'remarks'];
$image_path = $item['image_path'] ?? null;

$categories = [
    'identification' => ['item_number', 'property_no', 'serial_number', 'article', 'equipment_name', 'computer_set_description'],
    'specifications' => ['brand', 'model', 'unit', 'processor', 'ram', 'storage', 'device_type', 'operating_system', 'capacity', 'power_rating', 'specifications', 'description'],
    'status' => ['status', 'condition_status', 'keyboard_status', 'mouse_status', 'power_cord_status', 'hdmi_status'],
    'dates' => ['purchase_date', 'calibration_date', 'warranty_expiry'],
    'assignment' => ['campus', 'location_id']
];

$locations = [];
try {
    $loc_stmt = $db->prepare("SELECT id, location_name FROM locations ORDER BY location_name");
    $loc_stmt->execute();
    $locations = $loc_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

function getSelectOptions($field, $currentValue) {
    $options = '';
    
    if ($field === 'unit') {
        $units = ['unit', 'box', 'pcs', 'lot'];
        foreach ($units as $unit) {
            $selected = ($currentValue == $unit) ? 'selected' : '';
            $options .= "<option value=\"$unit\" $selected>" . ucfirst($unit) . "</option>";
        }
    } elseif ($field === 'status') {
        $statuses = ['available', 'maintenance', 'damaged', 'retired'];
        foreach ($statuses as $status) {
            $selected = ($currentValue == $status) ? 'selected' : '';
            $options .= "<option value=\"$status\" $selected>" . ucfirst($status) . "</option>";
        }
    } elseif ($field === 'condition_status') {
        $conditions = ['Excellent', 'Good', 'Fair', 'Poor', 'Damaged'];
        foreach ($conditions as $condition) {
            $selected = ($currentValue == $condition) ? 'selected' : '';
            $options .= "<option value=\"$condition\" $selected>$condition</option>";
        }
    } elseif ($field === 'device_type') {
        $types = ['Desktop', 'Laptop', 'All-in-One'];
        foreach ($types as $deviceType) {
            $selected = ($currentValue == $deviceType) ? 'selected' : '';
            $options .= "<option value=\"$deviceType\" $selected>$deviceType</option>";
        }
    } elseif (in_array($field, ['keyboard_status', 'mouse_status', 'power_cord_status', 'hdmi_status'])) {
        $statuses = ['OK', 'Missing', 'Damaged', 'Needs Repair'];
        foreach ($statuses as $status) {
            $selected = ($currentValue == $status) ? 'selected' : '';
            $options .= "<option value=\"$status\" $selected>$status</option>";
        }
    }
    
    return $options;
}

// Convert equipment type for article selection
function getArticleType($type) {
    $type_map = [
        'computer' => 'computer',
        'computer_lab' => 'computer',
        'kitchen' => 'kitchen',
        'office' => 'office',
        'lab' => 'lab',
        'regular_lab' => 'lab',
        'general' => 'general'
    ];
    return $type_map[$type] ?? 'general';
}

$articleType = getArticleType($type);
$currentArticle = htmlspecialchars($item['article'] ?? '');
?>

<!-- Improved Modal UI -->
<div class="container-fluid p-0">
    <!-- Header with Equipment Icon and Title -->
    <div class="bg-light p-4 border-bottom">
        <div class="d-flex align-items-center">
            <div class="equipment-icon-large me-3">
                <?php
                $icon = 'fa-box';
                $color = 'secondary';
                switch($type) {
                    case 'computer':
                    case 'computer_lab':
                        $icon = 'fa-desktop';
                        $color = 'primary';
                        break;
                    case 'kitchen':
                        $icon = 'fa-utensils';
                        $color = 'success';
                        break;
                    case 'office':
                        $icon = 'fa-briefcase';
                        $color = 'warning';
                        break;
                    case 'lab':
                    case 'regular_lab':
                        $icon = 'fa-flask';
                        $color = 'danger';
                        break;
                    case 'general':
                        $icon = 'fa-tools';
                        $color = 'secondary';
                        break;
                }
                ?>
                <div class="rounded-circle bg-<?php echo $color; ?> bg-opacity-10 p-3">
                    <i class="fas <?php echo $icon; ?> fa-2x text-<?php echo $color; ?>"></i>
                </div>
            </div>
            <div>
                <h4 class="mb-1 fw-bold">Edit Equipment Details</h4>
                <p class="text-muted mb-0">
                    <span class="badge bg-<?php echo $color; ?> me-2"><?php echo ucfirst(str_replace('_', ' ', $type)); ?></span>
                    Item #: <strong><?php echo htmlspecialchars($item['item_number'] ?? 'N/A'); ?></strong>
                </p>
            </div>
        </div>
    </div>

    <!-- Edit Form -->
    <form action="update_equipment_action.php" method="POST" enctype="multipart/form-data" class="p-4">
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        <input type="hidden" name="table" value="<?php echo $table; ?>">
        <input type="hidden" name="equipment_type" value="<?php echo $type; ?>">

        <!-- Image Upload Section -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h6 class="card-title fw-bold mb-3">
                    <i class="fas fa-camera me-2 text-primary"></i>Equipment Photo
                </h6>
                <div class="row">
                    <div class="col-md-8">
                        <?php if (!empty($image_path)): ?>
                        <div class="current-image mb-3">
                            <p class="small text-muted mb-2">Current Image:</p>
                            <img src="../<?php echo htmlspecialchars($image_path); ?>" 
                                 class="img-thumbnail rounded" 
                                 style="max-height: 150px; max-width: 100%; object-fit: contain;">
                        </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Upload New Image (optional)</label>
                            <input type="file" class="form-control" name="equipment_photo" accept="image/*">
                            <div class="form-text">Leave empty to keep current image. Max file size: 5MB</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Accountable Person Section -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h6 class="card-title fw-bold mb-3">
                    <i class="fas fa-user me-2 text-success"></i>Accountable Person
                </h6>
                <div class="row">
                    <div class="col-md-5 mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Last Name</label>
                        <input type="text" class="form-control" name="accountable_lastname" 
                               value="<?php echo htmlspecialchars($lastname); ?>" 
                               placeholder="Enter last name">
                    </div>
                    <div class="col-md-5 mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">First Name</label>
                        <input type="text" class="form-control" name="accountable_firstname" 
                               value="<?php echo htmlspecialchars($firstname); ?>" 
                               placeholder="Enter first name">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">M.I.</label>
                        <input type="text" class="form-control" name="accountable_middle" 
                               value="<?php echo htmlspecialchars($middle); ?>" 
                               placeholder="M.I." maxlength="5">
                    </div>
                </div>
                <div class="alert alert-info border-0 bg-light small mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Name will be saved as: <strong><?php 
                        $display_name = '';
                        if (!empty($lastname)) {
                            $display_name = $lastname;
                            if (!empty($firstname)) {
                                $display_name .= ', ' . $firstname;
                            }
                            if (!empty($middle)) {
                                $display_name .= ' ' . $middle;
                            }
                        }
                        echo !empty($display_name) ? htmlspecialchars($display_name) : '[Not Set]'; 
                    ?></strong>
                </div>
                <input type="hidden" name="remarks" value="<?php echo htmlspecialchars($item['remarks'] ?? ''); ?>" id="original_remarks">
            </div>
        </div>

        <!-- Tabs Navigation -->
        <ul class="nav nav-tabs mb-4" id="editTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="identification-tab" data-bs-toggle="tab" data-bs-target="#identification" type="button" role="tab">
                    <i class="fas fa-tag me-2"></i>Identification
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="specs-tab" data-bs-toggle="tab" data-bs-target="#specs" type="button" role="tab">
                    <i class="fas fa-microchip me-2"></i>Specifications
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="status-tab" data-bs-toggle="tab" data-bs-target="#status" type="button" role="tab">
                    <i class="fas fa-clipboard-check me-2"></i>Status & Condition
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="dates-tab" data-bs-toggle="tab" data-bs-target="#dates" type="button" role="tab">
                    <i class="fas fa-calendar-alt me-2"></i>Dates
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="assignment-tab" data-bs-toggle="tab" data-bs-target="#assignment" type="button" role="tab">
                    <i class="fas fa-map-marker-alt me-2"></i>Assignment
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="editTabsContent">
            <!-- Identification Tab -->
            <div class="tab-pane fade show active" id="identification" role="tabpanel">
                <div class="row">
                    <!-- Item Number -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Item Number</label>
                        <input type="text" class="form-control" name="item_number" value="<?php echo htmlspecialchars($item['item_number'] ?? ''); ?>" readonly>
                    </div>
                    
                    <!-- Property No -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Property No.</label>
                        <input type="text" class="form-control" name="property_no" value="<?php echo htmlspecialchars($item['property_no'] ?? ''); ?>">
                    </div>
                    
                    <!-- Article - Dynamic Dropdown with Hardcoded Options -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Article</label>
                        <select class="form-select" name="article" id="dynamic_article_select_edit">
                            <option value="" <?php echo empty($currentArticle) ? 'selected' : ''; ?>>-- Select Article --</option>
                            <?php
                            // Define articles based on equipment type
                            $article_options = [];
                            if ($articleType === 'computer') {
                                $article_options = ['Laptop', 'All-in-One', 'Computer Package'];
                            } elseif ($articleType === 'general') {
                                $article_options = ['Aircon', 'Copier', 'Projector', 'Scanner', 'Whiteboard', 'Board', 'Camera', 'TV', 'Sound System'];
                            } elseif ($articleType === 'kitchen') {
                                $article_options = ['Refrigerator', 'Stove', 'Microwave', 'Oven', 'Blender'];
                            } elseif ($articleType === 'office') {
                                $article_options = ['Chair', 'Table', 'Cabinet', 'Filing Cabinet', 'Desk'];
                            } elseif ($articleType === 'lab') {
                                $article_options = ['Microscope', 'Centrifuge', 'Incubator', 'Spectrophotometer', 'pH Meter'];
                            } else {
                                $article_options = ['Default Article'];
                            }
                            
                            foreach ($article_options as $article) {
                                $selected = ($currentArticle === $article) ? 'selected' : '';
                                $hasDualSerial = ($article === 'Computer Package') ? '1' : '0';
                                echo "<option value=\"" . htmlspecialchars($article) . "\" data-has-dual-serial=\"$hasDualSerial\" $selected>" . htmlspecialchars($article) . "</option>";
                            }
                            ?>
                        </select>
                        
                        <!-- Dual Serial Container (for Computer Package - Computer type only) -->
                        <div id="editDualSerialContainer" style="display: none; margin-top: 15px;">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="small text-muted">SERIAL NUMBER (MONITOR) <span class="text-danger">*</span></label>
                                    <input type="text" name="serial_number_monitor" class="form-control" id="edit_serial_monitor" value="<?php echo htmlspecialchars($item['serial_number_monitor'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="small text-muted">SERIAL NUMBER (SYSTEM UNIT) <span class="text-danger">*</span></label>
                                    <input type="text" name="serial_number_system" class="form-control" id="edit_serial_system" value="<?php echo htmlspecialchars($item['serial_number_system'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Single Serial Container -->
                        <div id="editSingleSerialContainer">
                            <div class="mt-2">
                                <label class="small text-muted">SERIAL NUMBER</label>
                                <input type="text" name="serial_number" class="form-control" id="edit_single_serial" value="<?php echo htmlspecialchars($item['serial_number'] ?? ''); ?>" placeholder="Enter Serial Number">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Equipment Name -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Equipment Name</label>
                        <input type="text" class="form-control" name="equipment_name" value="<?php echo htmlspecialchars($item['equipment_name'] ?? ''); ?>">
                    </div>
                    
                    <!-- Computer Set Description -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Computer Set Description</label>
                        <input type="text" class="form-control" name="computer_set_description" value="<?php echo htmlspecialchars($item['computer_set_description'] ?? ''); ?>">
                    </div>
                    
                    <!-- Cost Field -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Cost (₱)</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" step="0.01" class="form-control" name="cost" value="<?php echo htmlspecialchars($item['cost'] ?? ''); ?>" placeholder="0.00">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Specifications Tab -->
            <div class="tab-pane fade" id="specs" role="tabpanel">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Brand</label>
                        <input type="text" class="form-control" name="brand" value="<?php echo htmlspecialchars($item['brand'] ?? ''); ?>">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Model</label>
                        <input type="text" class="form-control" name="model" value="<?php echo htmlspecialchars($item['model'] ?? ''); ?>">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Unit</label>
                        <select class="form-select" name="unit">
                            <?php echo getSelectOptions('unit', $item['unit'] ?? ''); ?>
                        </select>
                    </div>
                    
                    <?php if ($type === 'computer' || $type === 'computer_lab'): ?>
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Processor</label>
                        <input type="text" class="form-control" name="processor" value="<?php echo htmlspecialchars($item['processor'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">RAM</label>
                        <input type="text" class="form-control" name="ram" value="<?php echo htmlspecialchars($item['ram'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Storage</label>
                        <input type="text" class="form-control" name="storage" value="<?php echo htmlspecialchars($item['storage'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Device Type</label>
                        <select class="form-select" name="device_type">
                            <?php echo getSelectOptions('device_type', $item['device_type'] ?? ''); ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Operating System</label>
                        <input type="text" class="form-control" name="operating_system" value="<?php echo htmlspecialchars($item['operating_system'] ?? ''); ?>">
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($type === 'kitchen'): ?>
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Capacity</label>
                        <input type="text" class="form-control" name="capacity" value="<?php echo htmlspecialchars($item['capacity'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Power Rating</label>
                        <input type="text" class="form-control" name="power_rating" value="<?php echo htmlspecialchars($item['power_rating'] ?? ''); ?>">
                    </div>
                    <?php endif; ?>
                    
                    <div class="col-md-12 mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Specifications</label>
                        <textarea class="form-control" name="specifications" rows="3"><?php echo htmlspecialchars($item['specifications'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="col-md-12 mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Description</label>
                        <textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Status Tab -->
            <div class="tab-pane fade" id="status" role="tabpanel">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Status</label>
                        <select class="form-select" name="status">
                            <?php echo getSelectOptions('status', $item['status'] ?? 'available'); ?>
                        </select>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Condition Status</label>
                        <select class="form-select" name="condition_status">
                            <?php echo getSelectOptions('condition_status', $item['condition_status'] ?? 'Good'); ?>
                        </select>
                    </div>
                    
                    <?php if ($type === 'computer' || $type === 'computer_lab'): ?>
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Keyboard Status</label>
                        <select class="form-select" name="keyboard_status">
                            <?php echo getSelectOptions('keyboard_status', $item['keyboard_status'] ?? 'OK'); ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Mouse Status</label>
                        <select class="form-select" name="mouse_status">
                            <?php echo getSelectOptions('mouse_status', $item['mouse_status'] ?? 'OK'); ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Power Cord Status</label>
                        <select class="form-select" name="power_cord_status">
                            <?php echo getSelectOptions('power_cord_status', $item['power_cord_status'] ?? 'OK'); ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">HDMI Status</label>
                        <select class="form-select" name="hdmi_status">
                            <?php echo getSelectOptions('hdmi_status', $item['hdmi_status'] ?? 'OK'); ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Dates Tab -->
            <div class="tab-pane fade" id="dates" role="tabpanel">
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Purchase Date</label>
                        <div class="purchase-date-container border rounded p-3 bg-light">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="purchase_date_option" id="purchase_date_yes" value="yes" <?php echo (!empty($item['purchase_date']) && $item['purchase_date'] !== '0000-00-00') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="purchase_date_yes">
                                            <i class="fas fa-calendar-check text-success me-1"></i> Have Date of Purchase
                                        </label>
                                    </div>
                                    <div class="mt-2" id="purchase_date_picker">
                                        <?php 
                                        $dateValue = (!empty($item['purchase_date']) && $item['purchase_date'] !== '0000-00-00') ? date('Y-m-d', strtotime($item['purchase_date'])) : '';
                                        ?>
                                        <input type="date" class="form-control" name="purchase_date" id="purchase_date_input" value="<?php echo $dateValue; ?>" max="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="purchase_date_option" id="purchase_date_no" value="no" <?php echo (empty($item['purchase_date']) || $item['purchase_date'] === '0000-00-00') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="purchase_date_no">
                                            <i class="fas fa-times-circle text-danger me-1"></i> No Date
                                        </label>
                                    </div>
                                    <div class="mt-2 text-muted" id="purchase_date_na" <?php echo (!empty($item['purchase_date']) && $item['purchase_date'] !== '0000-00-00') ? 'style="display: none;"' : ''; ?>>
                                        <span class="badge bg-secondary"><i class="fas fa-clock me-1"></i> Will be saved as NULL</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (isset($item['calibration_date'])): ?>
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Calibration Date</label>
                        <input type="date" class="form-control" name="calibration_date" value="<?php echo (!empty($item['calibration_date']) && $item['calibration_date'] !== '0000-00-00') ? date('Y-m-d', strtotime($item['calibration_date'])) : ''; ?>">
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($item['warranty_expiry'])): ?>
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Warranty Expiry</label>
                        <input type="date" class="form-control" name="warranty_expiry" value="<?php echo (!empty($item['warranty_expiry']) && $item['warranty_expiry'] !== '0000-00-00') ? date('Y-m-d', strtotime($item['warranty_expiry'])) : ''; ?>">
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Assignment Tab -->
            <div class="tab-pane fade" id="assignment" role="tabpanel">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Campus</label>
                        <select class="form-select" name="campus">
                            <option value="">-- Select Campus --</option>
                            <option value="South Campus" <?php echo ($item['campus'] ?? '') == 'South Campus' ? 'selected' : ''; ?>>South Campus</option>
                            <option value="Congressional Campus" <?php echo ($item['campus'] ?? '') == 'Congressional Campus' ? 'selected' : ''; ?>>Congressional Campus</option>
                            <option value="Bagong Silang Campus" <?php echo ($item['campus'] ?? '') == 'Bagong Silang Campus' ? 'selected' : ''; ?>>Bagong Silang Campus</option>
                            <option value="Camarin Campus" <?php echo ($item['campus'] ?? '') == 'Camarin Campus' ? 'selected' : ''; ?>>Camarin Campus</option>
                            <option value="Main Campus" <?php echo ($item['campus'] ?? '') == 'Main Campus' ? 'selected' : ''; ?>>Main Campus</option>
                        </select>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Location</label>
                        <select class="form-select" name="location_id">
                            <option value="">-- Unassigned / Storage --</option>
                            <?php foreach ($locations as $loc): ?>
                            <option value="<?php echo $loc['id']; ?>" <?php echo (($item['location_id'] ?? '') == $loc['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($loc['location_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

<!-- JavaScript for Dual Serial Toggle -->
        <script>
        (function() {
            // Purchase date toggle
            const radioYes = document.getElementById('purchase_date_yes');
            const radioNo = document.getElementById('purchase_date_no');
            const datePicker = document.getElementById('purchase_date_picker');
            const naDiv = document.getElementById('purchase_date_na');
            const dateInput = document.getElementById('purchase_date_input');
            
            if (radioYes && radioNo && datePicker && naDiv && dateInput) {
                function updatePurchaseDateDisplay() {
                    if (radioYes.checked) {
                        datePicker.style.display = 'block';
                        naDiv.style.display = 'none';
                        dateInput.disabled = false;
                        dateInput.name = 'purchase_date';
                    } else {
                        datePicker.style.display = 'none';
                        naDiv.style.display = 'block';
                        dateInput.disabled = true;
                        dateInput.removeAttribute('name');
                    }
                }
                
                radioYes.addEventListener('change', updatePurchaseDateDisplay);
                radioNo.addEventListener('change', updatePurchaseDateDisplay);
                updatePurchaseDateDisplay();
            }
            
            // ========== DUAL SERIAL TOGGLE FOR EDIT MODAL ==========
            const articleSelect = document.getElementById('dynamic_article_select_edit');
            const dualContainer = document.getElementById('editDualSerialContainer');
            const singleContainer = document.getElementById('editSingleSerialContainer');
            const monitorInput = document.getElementById('edit_serial_monitor');
            const systemInput = document.getElementById('edit_serial_system');
            const singleSerialInput = document.getElementById('edit_single_serial');
            
            if (!articleSelect) return;

            const equipmentType = '<?php echo $articleType; ?>';
            
            function updateSerialFieldsVisibility() {
                const selectedOption = articleSelect.options[articleSelect.selectedIndex];
                const hasDualSerial = selectedOption && selectedOption.getAttribute('data-has-dual-serial') === '1';
                const isComputerType = equipmentType === 'computer';
                
                if (dualContainer && singleContainer) {
                    if (isComputerType && hasDualSerial) {
                        dualContainer.style.display = 'block';
                        singleContainer.style.display = 'none';
                        if (monitorInput) monitorInput.setAttribute('required', 'required');
                        if (systemInput) systemInput.setAttribute('required', 'required');
                        if (singleSerialInput) singleSerialInput.removeAttribute('required');
                    } else {
                        dualContainer.style.display = 'none';
                        singleContainer.style.display = 'block';
                        if (monitorInput) monitorInput.removeAttribute('required');
                        if (systemInput) systemInput.removeAttribute('required');
                        if (singleSerialInput) singleSerialInput.setAttribute('required', 'required');
                    }
                }
            }
            
            // Re-run on article change
            articleSelect.addEventListener('change', updateSerialFieldsVisibility);
            
            // Run immediately — script is injected after DOM exists, no need to wait
            updateSerialFieldsVisibility();
        })();
        </script>

        <!-- Form Actions -->
        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
            <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">
                <i class="fas fa-times me-2"></i>Cancel
            </button>
            <button type="submit" class="btn btn-primary px-4">
                <i class="fas fa-save me-2"></i>Save Changes
            </button>
        </div>
    </form>
</div>

<style>
.nav-tabs .nav-link {
    color: #495057;
    font-weight: 500;
    padding: 0.75rem 1.25rem;
    border: none;
    border-bottom: 2px solid transparent;
}

.nav-tabs .nav-link:hover {
    border-bottom-color: #dee2e6;
}

.nav-tabs .nav-link.active {
    color: #0d6efd;
    background: none;
    border-bottom: 2px solid #0d6efd;
}

.nav-tabs .nav-link i {
    font-size: 0.9rem;
}

.equipment-icon-large .rounded-circle {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.form-label {
    font-size: 0.75rem;
    margin-bottom: 0.25rem;
}

.card {
    transition: all 0.2s ease;
}

.card:hover {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08) !important;
}

.purchase-date-container {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    transition: all 0.3s ease;
    margin-bottom: 10px;
    padding: 15px;
    border-radius: 8px;
}

.purchase-date-container:hover {
    border-color: #0d6efd;
    box-shadow: 0 2px 8px rgba(13, 110, 253, 0.1);
}

.form-check-input:checked + .form-check-label {
    font-weight: 600;
    color: #0d6efd;
}

@media (max-width: 768px) {
    .nav-tabs .nav-link {
        padding: 0.5rem 0.75rem;
        font-size: 0.85rem;
    }
    
    .nav-tabs .nav-link i {
        margin-right: 0.25rem;
    }
}
</style>