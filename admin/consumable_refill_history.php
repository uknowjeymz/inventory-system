<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id) {
    header("Location: consumables.php");
    exit();
}

// Get item details
$item_stmt = $db->prepare("SELECT * FROM consumables WHERE id = ?");
$item_stmt->execute([$id]);
$item = $item_stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    header("Location: consumables.php");
    exit();
}

// Get all refill history
$history_stmt = $db->prepare("SELECT cr.*, u.full_name 
                              FROM consumable_refills cr
                              LEFT JOIN users u ON cr.refilled_by = u.id
                              WHERE cr.consumable_id = ?
                              ORDER BY cr.refill_date DESC");
$history_stmt->execute([$id]);
$history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Refill History - " . $item['item_name'];
include '../includes/header.php';
?>

<style>
.page-header {
    background: linear-gradient(135deg, var(--consumable-success) 0%, #157347 100%);
    border-radius: 24px;
    padding: 2rem;
    margin-bottom: 2rem;
    color: white;
}
</style>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="fw-bold mb-2">
                <i class="fas fa-history me-2"></i>
                Refill History: <?php echo htmlspecialchars($item['item_name']); ?>
            </h2>
            <p class="mb-0 opacity-75">
                Current Stock: <?php echo $item['quantity']; ?> <?php echo $item['unit']; ?> | 
                Max Stock: <?php echo $item['max_stock'] ?? 'Not set'; ?>
            </p>
        </div>
        <a href="consumables.php" class="btn btn-light">
            <i class="fas fa-arrow-left me-2"></i>Back to Consumables
        </a>
    </div>
</div>

<div class="section-card">
    <div class="section-header">
        <div class="section-title">
            <i class="fas fa-clock"></i>
            <span>All Refill Records</span>
        </div>
        <span class="section-badge"><?php echo count($history); ?> records</span>
    </div>
    
    <div class="table-responsive">
        <table class="table table-hover">
            <thead class="table-light">
                <tr>
                    <th>Date & Time</th>
                    <th>Previous Quantity</th>
                    <th>Quantity Added</th>
                    <th>New Quantity</th>
                    <th>Change</th>
                    <th>Refilled By</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($history)): ?>
                <tr>
                    <td colspan="7" class="text-center py-5">
                        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                        <p class="mb-0">No refill history found for this item.</p>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($history as $record): ?>
                <tr>
                    <td>
                        <?php echo date('M d, Y h:i A', strtotime($record['refill_date'])); ?>
                    </td>
                    <td class="text-center">
                        <?php echo $record['previous_quantity']; ?> <?php echo $item['unit']; ?>
                    </td>
                    <td class="text-center">
                        <span class="text-success fw-bold">+<?php echo $record['refill_quantity']; ?></span>
                    </td>
                    <td class="text-center">
                        <span class="fw-bold"><?php echo $record['new_quantity']; ?> <?php echo $item['unit']; ?></span>
                    </td>
                    <td class="text-center">
                        <?php 
                        $diff = $record['new_quantity'] - $record['previous_quantity'];
                        if ($diff > 0) {
                            echo '<span class="badge bg-success">+' . $diff . '</span>';
                        } else {
                            echo '<span class="badge bg-danger">' . $diff . '</span>';
                        }
                        ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($record['full_name'] ?? 'System'); ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($record['remarks'] ?? '—'); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>