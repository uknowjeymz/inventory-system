<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user') {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get user's assigned equipment
$user_id = $_SESSION['user_id'];

// Get assigned computers
$query = "SELECT 'Computer' as type, id, description, serial_number, peripherals as extra_info, status 
          FROM computers WHERE assigned_to = ?";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$computers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get assigned laptops
$query = "SELECT 'Laptop' as type, id, description, serial_number, charger as extra_info, status 
          FROM laptops WHERE assigned_to = ?";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$laptops = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get assigned all-in-ones
$query = "SELECT 'All-in-One' as type, id, description, serial_number, peripherals as extra_info, status 
          FROM all_in_ones WHERE assigned_to = ?";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$all_in_ones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Combine all assignments
$assignments = array_merge($computers, $laptops, $all_in_ones);

$page_title = "User Dashboard";
include '../includes/header.php';
?>

<div class="row">
    <div class="col-lg-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Welcome, <?php echo $_SESSION['full_name']; ?>!</h6>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> This is your equipment assignment dashboard. 
                    Here you can view all laboratory equipment assigned to you for facilitation.
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Assigned Computers</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($computers); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-desktop fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Assigned Laptops</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($laptops); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-laptop fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Assigned All-in-Ones</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($all_in_ones); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-tv fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (count($assignments) > 0): ?>
<div class="row">
    <div class="col-lg-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">My Equipment Assignments</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered data-table" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Serial Number</th>
                                <th>Additional Info</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assignments as $item): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $item['type'] == 'Computer' ? 'primary' : 
                                            ($item['type'] == 'Laptop' ? 'success' : 'info'); 
                                    ?>">
                                        <?php echo $item['type']; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($item['description']); ?></td>
                                <td><?php echo htmlspecialchars($item['serial_number']); ?></td>
                                <td>
                                    <?php if ($item['type'] == 'Laptop'): ?>
                                        <span class="badge bg-<?php 
                                            echo $item['extra_info'] == 'included' ? 'success' : 
                                                ($item['extra_info'] == 'missing' ? 'danger' : 'warning'); 
                                        ?>">
                                            Charger: <?php echo ucfirst($item['extra_info']); ?>
                                        </span>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($item['extra_info']); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $item['status'] == 'assigned' ? 'primary' : 
                                            ($item['status'] == 'maintenance' ? 'warning' : 'danger'); 
                                    ?>">
                                        <?php echo ucfirst($item['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<div class="row">
    <div class="col-lg-12">
        <div class="card shadow mb-4">
            <div class="card-body text-center">
                <i class="fas fa-inbox fa-3x text-gray-300 mb-3"></i>
                <h5 class="text-gray-600">No Equipment Assigned</h5>
                <p class="text-gray-500">You currently have no laboratory equipment assigned to you. Please contact your administrator if you need equipment assignments.</p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h5><i class="fas fa-clipboard-list"></i> View All Assignments</h5>
                                <p>See detailed information about all your equipment assignments.</p>
                                <a href="my_assignments.php" class="btn btn-primary btn-sm">View Assignments</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h5><i class="fas fa-exclamation-triangle"></i> Report Issues</h5>
                                <p>Contact your administrator if you encounter any equipment issues.</p>
                                <button class="btn btn-warning btn-sm" disabled>Coming Soon</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>