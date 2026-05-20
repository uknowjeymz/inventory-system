<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user') {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Get currently assigned equipment
$query = "SELECT ci.*, l.location_name, ah.assigned_date
          FROM computer_inventory ci
          LEFT JOIN locations l ON ci.location_id = l.id
          LEFT JOIN assignment_history ah ON ci.id = ah.computer_id 
          WHERE ci.assigned_to = ? AND ci.status = 'assigned' AND ah.status = 'active'
          ORDER BY ah.assigned_date DESC";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$current_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get assignment history for this user
$query = "SELECT ah.*, ci.item_number, ci.computer_set_description, ci.serial_number,
          l.location_name, ab.full_name as assigned_by_name,
          DATEDIFF(COALESCE(ah.returned_date, NOW()), ah.assigned_date) as days_assigned
          FROM assignment_history ah
          JOIN computer_inventory ci ON ah.computer_id = ci.id
          LEFT JOIN locations l ON ci.location_id = l.id
          JOIN users ab ON ah.assigned_by = ab.id
          WHERE ah.user_id = ?
          ORDER BY ah.assigned_date DESC";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$assignment_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$query = "SELECT 
          COUNT(*) as total_assignments,
          COUNT(CASE WHEN status = 'active' THEN 1 END) as current_assignments,
          COUNT(CASE WHEN status = 'returned' THEN 1 END) as returned_assignments,
          AVG(DATEDIFF(COALESCE(returned_date, NOW()), assigned_date)) as avg_days
          FROM assignment_history 
          WHERE user_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$page_title = "My Equipment Assignments";
include '../includes/header.php';
?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card shadow h-100 py-2 user-stats-card border-left-primary">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Current Assignments</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['current_assignments']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-laptop fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card shadow h-100 py-2 user-stats-card border-left-success">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Assignments</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_assignments']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card shadow h-100 py-2 user-stats-card border-left-info">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Returned Items</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['returned_assignments']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-undo fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card shadow h-100 py-2 user-stats-card border-left-warning">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Avg Days</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo round($stats['avg_days']); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-calendar-alt fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Current Assignments -->
<?php if (count($current_assignments) > 0): ?>
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-laptop"></i> Currently Assigned Equipment</h6>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Below are all the laboratory equipment items currently assigned to you. 
            Please ensure proper care and maintenance of these items.
        </div>
        <div class="table-responsive">
            <table class="table table-bordered" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Item #</th>
                        <th>Description</th>
                        <th>Specifications</th>
                        <th>Peripherals</th>
                        <th>Location</th>
                        <th>Assigned Date</th>
                        <th>Days Assigned</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($current_assignments as $item): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($item['item_number']); ?></strong></td>
                        <td>
                            <strong><?php echo htmlspecialchars($item['computer_set_description']); ?></strong><br>
                            <small class="text-muted"><?php echo htmlspecialchars($item['operating_system']); ?></small>
                        </td>
                        <td>
                            <small>
                                <strong>CPU:</strong> <?php echo htmlspecialchars($item['processor']); ?><br>
                                <strong>RAM:</strong> <?php echo htmlspecialchars($item['ram']); ?><br>
                                <strong>Storage:</strong> <?php echo htmlspecialchars($item['storage']); ?>
                            </small>
                        </td>
                        <td>
                            <small>
                                <span class="badge bg-<?php echo $item['keyboard_status'] == 'OK' ? 'success' : 'danger'; ?>">KB</span>
                                <span class="badge bg-<?php echo $item['mouse_status'] == 'OK' ? 'success' : 'danger'; ?>">MS</span>
                                <span class="badge bg-<?php echo $item['power_cord_status'] == 'OK' ? 'success' : 'danger'; ?>">PWR</span>
                                <span class="badge bg-<?php echo $item['hdmi_status'] == 'OK' ? 'success' : 'danger'; ?>">HDMI</span>
                            </small>
                        </td>
                        <td><?php echo htmlspecialchars($item['location_name']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($item['assigned_date'])); ?></td>
                        <td>
                            <?php 
                            $days = floor((time() - strtotime($item['assigned_date'])) / (60 * 60 * 24));
                            ?>
                            <span class="badge bg-<?php echo $days > 30 ? 'warning' : 'info'; ?>">
                                <?php echo $days; ?> days
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php else: ?>
<div class="card shadow mb-4">
    <div class="card-body text-center py-5">
        <i class="fas fa-inbox fa-4x text-gray-300 mb-4"></i>
        <h4 class="text-gray-600">No Equipment Currently Assigned</h4>
        <p class="text-gray-500 mb-4">You currently have no laboratory equipment assigned to you.</p>
        <p class="text-gray-500">Please contact your administrator if you need equipment assignments for your facilitation duties.</p>
        <a href="dashboard.php" class="btn btn-primary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Assignment History -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-history"></i> Assignment History</h6>
    </div>
    <div class="card-body">
        <?php if (count($assignment_history) > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered data-table" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Item #</th>
                        <th>Description</th>
                        <th>Location</th>
                        <th>Assigned Date</th>
                        <th>Returned Date</th>
                        <th>Days Used</th>
                        <th>Status</th>
                        <th>Assigned By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assignment_history as $history): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($history['item_number']); ?></strong></td>
                        <td>
                            <strong><?php echo htmlspecialchars($history['computer_set_description']); ?></strong><br>
                            <small class="text-muted"><?php echo htmlspecialchars($history['serial_number']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($history['location_name']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($history['assigned_date'])); ?></td>
                        <td>
                            <?php if ($history['returned_date']): ?>
                                <?php echo date('M d, Y', strtotime($history['returned_date'])); ?>
                            <?php else: ?>
                                <span class="text-success">Currently assigned</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $history['days_assigned'] > 30 ? 'warning' : 'info'; ?>">
                                <?php echo $history['days_assigned']; ?> days
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-<?php 
                                echo $history['status'] == 'active' ? 'success' : 
                                    ($history['status'] == 'returned' ? 'secondary' : 'info'); 
                            ?>">
                                <?php echo ucfirst($history['status']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($history['assigned_by_name']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="text-center py-4">
            <i class="fas fa-history fa-3x text-gray-300 mb-3"></i>
            <h6 class="text-muted">No Assignment History</h6>
            <p class="text-muted">You have no previous equipment assignments.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Equipment Care Guidelines -->
<div class="card shadow mb-4 bg-light">
    <div class="card-body">
        <h5 class="card-title"><i class="fas fa-lightbulb"></i> Equipment Care Guidelines</h5>
        <div class="row">
            <div class="col-md-4">
                <h6><i class="fas fa-desktop text-primary"></i> Desktop Computers</h6>
                <ul class="small">
                    <li>Keep peripherals organized and clean</li>
                    <li>Report any hardware issues immediately</li>
                    <li>Ensure proper shutdown procedures</li>
                    <li>Check cable connections regularly</li>
                </ul>
            </div>
            <div class="col-md-4">
                <h6><i class="fas fa-laptop text-success"></i> Laptops</h6>
                <ul class="small">
                    <li>Keep chargers safe and functional</li>
                    <li>Handle with care during transport</li>
                    <li>Monitor battery health</li>
                    <li>Avoid extreme temperatures</li>
                </ul>
            </div>
            <div class="col-md-4">
                <h6><i class="fas fa-tv text-info"></i> All-in-One Systems</h6>
                <ul class="small">
                    <li>Maintain screen cleanliness</li>
                    <li>Keep all accessories together</li>
                    <li>Report display or touch issues</li>
                    <li>Handle with extra care</li>
                </ul>
            </div>
        </div>
        <div class="mt-3">
            <h6><i class="fas fa-exclamation-triangle text-warning"></i> Important Reminders</h6>
            <ul class="small">
                <li>Report any damage or malfunction immediately to your administrator</li>
                <li>Do not attempt repairs yourself - contact technical support</li>
                <li>Keep equipment in designated locations when not in use</li>
                <li>Follow proper login/logout procedures for security</li>
            </ul>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>