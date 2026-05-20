<?php
session_start();
require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

$id = $_GET['id'] ?? 0;
$type = $_GET['type'] ?? '';

$table_map = [
    'computer' => 'computer_inventory', 'computer_lab' => 'computer_inventory',
    'kitchen' => 'kitchen_equipment',
    'office' => 'office_equipment',
    'lab' => 'lab_equipment', 'regular_lab' => 'lab_equipment',
    'general' => 'general_equipment'
];

$table = $table_map[$type] ?? '';

if ($table && $id) {
    $stmt = $db->prepare("SELECT t.*, l.location_name FROM $table t LEFT JOIN locations l ON t.location_id = l.id WHERE t.id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($item) {
        $title = $item['computer_set_description'] ?? $item['article'] ?? $item['equipment_name'] ?? 'Item Details';
        $status_color = ($item['status'] == 'available') ? 'success' : (($item['status'] == 'maintenance') ? 'warning' : 'danger');
        ?>
        
        <div class="row g-0">
            <div class="col-md-4 bg-light p-4 border-end">
                <div class="text-center mb-4">
                    <?php if (!empty($item['image_path'])): ?>
                        <div class="img-container shadow-sm rounded border bg-white p-2">
                            <img src="../<?php echo htmlspecialchars($item['image_path']); ?>" class="img-fluid rounded" style="max-height: 250px; width: 100%; object-fit: contain;">
                        </div>
                    <?php else: ?>
                        <div class="bg-white rounded d-flex flex-column align-items-center justify-content-center border shadow-sm mx-auto" style="height: 250px; width: 100%;">
                            <i class="fas fa-image fa-4x text-muted mb-2 opacity-25"></i>
                            <span class="text-muted small">No Image Available</span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="quick-status-cards">
                    <div class="card border-0 shadow-sm mb-2">
                        <div class="card-body py-2 px-3 d-flex align-items-center">
                            <div class="rounded-circle bg-<?php echo $status_color; ?> p-2 me-3" style="width:10px; height:10px;"></div>
                            <div>
                                <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 0.65rem;">System Status</small>
                                <span class="fw-bold text-<?php echo $status_color; ?> text-uppercase"><?php echo htmlspecialchars($item['status']); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card border-0 shadow-sm mb-2">
                        <div class="card-body py-2 px-3">
                            <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 0.65rem;">Item Identification</small>
                            <span class="fw-bold text-dark">#<?php echo htmlspecialchars($item['item_number'] ?: 'N/A'); ?></span>
                        </div>
                    </div>

                    <?php if(isset($item['cost']) && $item['cost'] > 0): ?>
                    <div class="card border-primary border-opacity-25 shadow-sm bg-primary bg-opacity-10">
                        <div class="card-body py-2 px-3 text-center">
                            <small class="text-primary d-block text-uppercase fw-bold" style="font-size: 0.65rem;">Acquisition Cost</small>
                            <h5 class="fw-bold mb-0 text-primary">₱ <?php echo number_format($item['cost'], 2); ?></h5>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-8 p-4 bg-white">
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div>
                        <h3 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($title); ?></h3>
                        <span class="badge bg-secondary rounded-pill px-3"><?php echo ucwords(str_replace('_', ' ', $type)); ?></span>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-warning shadow-sm fw-bold px-3 border-0" 
                                onclick="openEditModal(<?php echo $id; ?>, '<?php echo $type; ?>')">
                            <i class="fas fa-pencil-alt me-2"></i>Edit Item
                        </button>
                        <button type="button" class="btn btn-danger shadow-sm fw-bold px-3 border-0" 
                                onclick="confirmDeleteEquipment(<?php echo $id; ?>, '<?php echo $type; ?>', '<?php echo addslashes($title); ?>')">
                            <i class="fas fa-trash me-2"></i>Delete Item
                        </button>
                    </div>
                </div>

                <div class="detail-grid">
                    <h6 class="text-primary border-bottom pb-2 mb-3 fw-bold"><i class="fas fa-cog me-2"></i>Specifications & Location</h6>
                    <div class="row g-3">
                        <?php 
                        // 1. PROPERTY NUMBER: Always shown at the top for every item
                        ?>
                        <div class="col-md-6 mb-2">
                            <div class="p-2 rounded hover-bg-light border-start border-primary border-3">
                                <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 0.7rem;">Property Number</small>
                                <span class="text-dark fw-bold"><?php echo htmlspecialchars($item['property_no'] ?? 'N/A'); ?></span>
                            </div>
                        </div>

                        <?php 
                        // 1.5. SERIAL NUMBER: Show for all equipment types
                        ?>
                        <div class="col-md-6 mb-2">
                            <div class="p-2 rounded hover-bg-light border-start border-success border-3">
                                <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 0.7rem;">Serial Number</small>
                                <span class="text-dark fw-bold"><?php echo htmlspecialchars($item['serial_number'] ?? 'N/A'); ?></span>
                            </div>
                        </div>

                        <?php 
                        // 1.6. UNIT: Show unit for all equipment types
                        if (isset($item['unit']) && !empty($item['unit'])): 
                        ?>
                        <div class="col-md-6 mb-2">
                            <div class="p-2 rounded hover-bg-light border-start border-info border-3">
                                <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 0.7rem;">Unit</small>
                                <span class="text-dark fw-bold">
                                    <?php 
                                    $unit = htmlspecialchars($item['unit']);
                                    $unit_icon = '';
                                    switch($unit) {
                                        case 'unit': $unit_icon = 'fa-cube'; break;
                                        case 'box': $unit_icon = 'fa-box'; break;
                                        case 'pcs': $unit_icon = 'fa-puzzle-piece'; break;
                                        case 'lot': $unit_icon = 'fa-layer-group'; break;
                                    }
                                    ?>
                                    <i class="fas <?php echo $unit_icon; ?> me-1 text-info"></i>
                                    <?php echo strtoupper($unit); ?>
                                </span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php 
                        // 1.7. PURCHASE DATE: Show purchase date for all equipment types
                        if (isset($item['purchase_date']) && !empty($item['purchase_date']) && $item['purchase_date'] !== '0000-00-00'): 
                            $purchaseDate = date('M d, Y', strtotime($item['purchase_date']));
                            $purchaseYear = date('Y', strtotime($item['purchase_date']));
                        ?>
                        <div class="col-md-6 mb-2">
                            <div class="p-2 rounded hover-bg-light border-start border-warning border-3">
                                <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 0.7rem;">Purchase Date</small>
                                <span class="text-dark fw-bold"><?php echo $purchaseDate; ?></span>
                                <small class="text-muted d-block">Year: <?php echo $purchaseYear; ?></small>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php 
                        // 2. BRAND AND MODEL (if available)
                        if (isset($item['brand']) && !empty($item['brand'])): 
                        ?>
                        <div class="col-md-6 mb-2">
                            <div class="p-2 rounded hover-bg-light">
                                <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 0.7rem;">Brand</small>
                                <span class="text-dark"><?php echo htmlspecialchars($item['brand']); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (isset($item['model']) && !empty($item['model'])): ?>
                        <div class="col-md-6 mb-2">
                            <div class="p-2 rounded hover-bg-light">
                                <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 0.7rem;">Model</small>
                                <span class="text-dark"><?php echo htmlspecialchars($item['model']); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php 
                        // 3. MAIN SPECIFICATIONS (dynamically generated)
                        $main_fields = [
                            'campus' => 'Assigned Campus',
                            'location_name' => 'Specific Room',
                            'condition_status' => 'Current Condition'
                        ];

                        // Add computer-specific fields
                        if ($table === 'computer_inventory') {
                            $main_fields['device_type'] = 'Device Type';
                            $main_fields['processor'] = 'Processor';
                            $main_fields['ram'] = 'RAM Memory';
                            $main_fields['storage'] = 'Storage Capacity';
                            $main_fields['operating_system'] = 'Operating System';
                        }
                        
                        // Add description for kitchen, office, and lab equipment
                        if (in_array($table, ['kitchen_equipment', 'office_equipment', 'lab_equipment'])) {
                            $main_fields['description'] = 'Description';
                        }

                        // Add calibration date for lab equipment
                        if ($table === 'lab_equipment' && isset($item['calibration_date'])) {
                            $main_fields['calibration_date'] = 'Calibration Date';
                        }

                        // Add capacity for kitchen equipment
                        if ($table === 'kitchen_equipment' && isset($item['capacity'])) {
                            $main_fields['capacity'] = 'Capacity';
                        }

                        // Add power rating for kitchen equipment
                        if ($table === 'kitchen_equipment' && isset($item['power_rating'])) {
                            $main_fields['power_rating'] = 'Power Rating';
                        }

                        // Add specifications for office/lab
                        if (($table === 'office_equipment' || $table === 'lab_equipment') && isset($item['specifications'])) {
                            $main_fields['specifications'] = 'Specifications';
                        }

                        // Add description for general equipment
                        if ($table === 'general_equipment' && isset($item['description'])) {
                            $main_fields['description'] = 'Description';
                        }

                        foreach ($main_fields as $key => $label):
                            if (isset($item[$key]) && !empty($item[$key])):
                        ?>
                            <div class="col-md-6 mb-2">
                                <div class="p-2 rounded hover-bg-light">
                                    <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 0.7rem;"><?php echo $label; ?></small>
                                    <span class="text-dark"><?php echo htmlspecialchars($item[$key] ?: '---'); ?></span>
                                </div>
                            </div>
                        <?php 
                            endif;
                        endforeach;
                        ?>

                        <?php if ($table === 'computer_inventory' && ($item['article'] ?? '') === 'Computer Package'): ?>
                            <div class="col-md-6 mb-2">
                                <div class="p-2 rounded hover-bg-light border-start border-info border-3">
                                    <small class="text-info d-block text-uppercase fw-bold" style="font-size: 0.7rem;">Serial Number (Monitor)</small>
                                    <span class="text-dark fw-bold"><?php echo htmlspecialchars($item['serial_number_monitor'] ?? 'N/A'); ?></span>
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="p-2 rounded hover-bg-light border-start border-info border-3">
                                    <small class="text-info d-block text-uppercase fw-bold" style="font-size: 0.7rem;">Serial Number (System Unit)</small>
                                    <span class="text-dark fw-bold"><?php echo htmlspecialchars($item['serial_number_system'] ?? 'N/A'); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($table === 'general_equipment' && isset($item['article']) && $item['article'] === 'Smartboard'): ?>
                            <div class="col-12 mt-3 mb-2">
                                <h6 class="text-info border-bottom pb-2 mb-3 fw-bold"><i class="fas fa-video me-2"></i>Projector Components</h6>
                            </div>
                            
                            <?php if (isset($item['projector_brand']) && !empty($item['projector_brand'])): ?>
                            <div class="col-md-4 mb-2">
                                <div class="p-2 rounded hover-bg-light border-start border-info border-3">
                                    <small class="text-info d-block text-uppercase fw-bold" style="font-size: 0.7rem;">Projector Brand</small>
                                    <span class="text-dark"><?php echo htmlspecialchars($item['projector_brand']); ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (isset($item['projector_model']) && !empty($item['projector_model'])): ?>
                            <div class="col-md-4 mb-2">
                                <div class="p-2 rounded hover-bg-light border-start border-info border-3">
                                    <small class="text-info d-block text-uppercase fw-bold" style="font-size: 0.7rem;">Projector Model</small>
                                    <span class="text-dark"><?php echo htmlspecialchars($item['projector_model']); ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (isset($item['projector_serial_number']) && !empty($item['projector_serial_number'])): ?>
                            <div class="col-md-4 mb-2">
                                <div class="p-2 rounded hover-bg-light border-start border-info border-3">
                                    <small class="text-info d-block text-uppercase fw-bold" style="font-size: 0.7rem;">Projector Serial</small>
                                    <span class="text-dark"><?php echo htmlspecialchars($item['projector_serial_number']); ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php 
                        // 4. ACCOUNTABLE PERSON (Remarks)
                        if (isset($item['remarks']) && !empty($item['remarks'])): 
                        ?>
                        <div class="col-12 mt-2 mb-2">
                            <div class="p-3 rounded bg-light border-start border-warning border-3">
                                <small class="text-muted d-block text-uppercase fw-bold mb-1" style="font-size: 0.7rem;">Accountable Person</small>
                                <span class="text-dark fw-bold"><?php echo htmlspecialchars($item['remarks']); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="mt-4 text-end">
                        <small class="text-muted ultra-small">Record Created: <?php echo date('M d, Y', strtotime($item['created_at'])); ?> | Last Modified: <?php echo date('M d, Y', strtotime($item['updated_at'])); ?></small>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .hover-bg-light:hover { background-color: #f8f9fa; transition: 0.2s; }
            .ultra-small { font-size: 0.65rem; }
            .detail-grid label { letter-spacing: 0.5px; }
            .img-container { overflow: hidden; background: #fff; }
        </style>

        <script>
        // Delete equipment function with confirmation
        function confirmDeleteEquipment(id, type, name) {
            Swal.fire({
                title: 'Delete Equipment?',
                html: `Are you sure you want to delete <strong>${escapeHtml(name)}</strong>?<br><br>This action cannot be undone. All assignment history for this equipment will also be deleted.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-trash me-2"></i>Yes, delete it!',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancel',
                reverseButtons: true,
                customClass: {
                    confirmButton: 'btn btn-danger',
                    cancelButton: 'btn btn-secondary'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Get table name based on type
                    let tableName = '';
                    switch(type) {
                        case 'computer':
                        case 'computer_lab':
                            tableName = 'computer_inventory';
                            break;
                        case 'kitchen':
                            tableName = 'kitchen_equipment';
                            break;
                        case 'office':
                            tableName = 'office_equipment';
                            break;
                        case 'lab':
                        case 'regular_lab':
                            tableName = 'lab_equipment';
                            break;
                        case 'general':
                            tableName = 'general_equipment';
                            break;
                        default:
                            tableName = type;
                    }
                    
                    // Show loading state
                    Swal.fire({
                        title: 'Deleting...',
                        text: 'Please wait while the equipment is being deleted.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Create form and submit via AJAX for better handling
                    const formData = new FormData();
                    formData.append('id', id);
                    formData.append('type', type);
                    formData.append('table', tableName);
                    formData.append('action', 'delete_equipment');
                    
                    fetch('delete_equipment.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Deleted!',
                                text: data.message,
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.href = 'all_equipment.php';
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: data.message
                            });
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'An error occurred while deleting the equipment.'
                        });
                        console.error('Error:', error);
                    });
                }
            });
        }

        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
        }
        </script>

        <?php
    } else {
        echo '<div class="p-5 text-center text-muted"><i class="fas fa-search fa-3x mb-3"></i><p>Equipment not found.</p></div>';
    }
} else {
    echo '<div class="p-5 text-center text-danger">Invalid request details.</div>';
}
?>