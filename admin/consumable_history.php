<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';
require_once 'consumables_history_logger.php';

$database = new Database();
$db = $database->getConnection();
$logger = new ConsumablesHistoryLogger($db);

// Get filter parameters
$consumable_filter = $_GET['consumable'] ?? '';
$action_filter = $_GET['action'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$user_filter = $_GET['user'] ?? '';

// Build filters array
$filters = [];
if (!empty($consumable_filter)) $filters['consumable_id'] = $consumable_filter;
if (!empty($action_filter)) $filters['action_type'] = $action_filter;
if (!empty($date_from)) $filters['date_from'] = $date_from;
if (!empty($date_to)) $filters['date_to'] = $date_to;
if (!empty($user_filter)) $filters['performed_by'] = $user_filter;

// Get history
$history = $logger->getAllHistory($filters, 200);

// Get consumables for filter dropdown
$consumables_query = "SELECT id, item_name, category FROM consumables ORDER BY item_name";
$consumables_stmt = $db->query($consumables_query);
$consumables = $consumables_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get users for filter dropdown
$users_query = "SELECT id, full_name FROM users WHERE role = 'admin' ORDER BY full_name";
$users_stmt = $db->query($users_query);
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Consumables History";
include '../includes/header.php';
?>

<style>
:root {
    --history-green: #28a745;
    --history-green-dark: #1e7e34;
    --history-green-light: #d4edda;
    --history-green-soft: #e8f5e9;
}

.page-header {
    background: linear-gradient(135deg, var(--history-green) 0%, var(--history-green-dark) 100%);
    border-radius: 24px;
    padding: 2rem 2.5rem;
    margin-bottom: 2rem;
    color: white;
    box-shadow: 0 20px 40px rgba(40, 167, 69, 0.2);
}

.header-title {
    font-size: 2rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.filter-section {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
}

.history-table-container {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
}

.action-badge {
    padding: 0.4rem 1rem;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.action-badge.refill { background: #d4edda; color: #155724; }
.action-badge.deduction { background: #f8d7da; color: #721c24; }
.action-badge.adjustment { background: #fff3cd; color: #856404; }
.action-badge.initial { background: #d1ecf1; color: #0c5460; }
.action-badge.edit { background: #e2e3e5; color: #383d41; }

.quantity-change {
    font-weight: 700;
    font-size: 1.1rem;
}

.quantity-change.positive { color: #28a745; }
.quantity-change.negative { color: #dc3545; }

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-state-icon {
    width: 120px;
    height: 120px;
    border-radius: 60px;
    background: var(--history-green-soft);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 2rem;
    font-size: 3rem;
    color: var(--history-green);
}
</style>

<!-- Page Header -->
<div class="page-header">
    <div class="header-title">
        <i class="fas fa-history"></i>
        <span>Consumables History</span>
    </div>
    <p style="opacity: 0.9; margin: 0;">Track all consumable stock changes, refills, and deductions</p>
</div>

<!-- Filter Section -->
<div class="filter-section">
    <h5 class="mb-3">
        <i class="fas fa-filter me-2"></i>Filter History
    </h5>
    
    <form method="GET" class="row g-3">
        <div class="col-md-3">
            <label class="form-label">Consumable Item</label>
            <select class="form-select" name="consumable">
                <option value="">All Items</option>
                <?php foreach ($consumables as $item): ?>
                <option value="<?php echo $item['id']; ?>" <?php echo $consumable_filter == $item['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($item['item_name']); ?> (<?php echo htmlspecialchars($item['category']); ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-md-2">
            <label class="form-label">Action Type</label>
            <select class="form-select" name="action">
                <option value="">All Actions</option>
                <option value="refill" <?php echo $action_filter == 'refill' ? 'selected' : ''; ?>>Refill</option>
                <option value="deduction" <?php echo $action_filter == 'deduction' ? 'selected' : ''; ?>>Deduction</option>
                <option value="adjustment" <?php echo $action_filter == 'adjustment' ? 'selected' : ''; ?>>Adjustment</option>
                <option value="initial" <?php echo $action_filter == 'initial' ? 'selected' : ''; ?>>Initial</option>
                <option value="edit" <?php echo $action_filter == 'edit' ? 'selected' : ''; ?>>Edit</option>
            </select>
        </div>
        
        <div class="col-md-2">
            <label class="form-label">Performed By</label>
            <select class="form-select" name="user">
                <option value="">All Users</option>
                <?php foreach ($users as $user): ?>
                <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($user['full_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-md-2">
            <label class="form-label">From Date</label>
            <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
        </div>
        
        <div class="col-md-2">
            <label class="form-label">To Date</label>
            <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
        </div>
        
        <div class="col-md-1">
            <label class="form-label">&nbsp;</label>
            <button type="submit" class="btn btn-success w-100">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </form>
    
    <?php if (!empty($filters)): ?>
    <div class="mt-3">
        <a href="consumable_history.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-times me-1"></i>Clear Filters
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- History Table -->
<div class="history-table-container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>History Records
            <span class="badge bg-success ms-2"><?php echo count($history); ?> records</span>
        </h5>
        <button class="btn btn-outline-success btn-sm" onclick="window.print()">
            <i class="fas fa-print me-1"></i>Print
        </button>
    </div>
    
    <?php if (empty($history)): ?>
    <div class="empty-state">
        <div class="empty-state-icon">
            <i class="fas fa-history"></i>
        </div>
        <h3>No History Found</h3>
        <p class="text-muted">There are no history records matching your criteria.</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead class="table-light">
                <tr>
                    <th>Date & Time</th>
                    <th>Item</th>
                    <th>Action</th>
                    <th>Previous Qty</th>
                    <th>Change</th>
                    <th>New Qty</th>
                    <th>Performed By</th>
                    <th>Reference</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $record): ?>
                <tr>
                    <td>
                        <div><?php echo date('M d, Y', strtotime($record['action_date'])); ?></div>
                        <small class="text-muted"><?php echo date('h:i A', strtotime($record['action_date'])); ?></small>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($record['consumable_name']); ?></strong>
                        <br><small class="text-muted"><?php echo htmlspecialchars($record['consumable_category']); ?></small>
                    </td>
                    <td>
                        <span class="action-badge <?php echo $record['action_type']; ?>">
                            <?php 
                            $icons = [
                                'refill' => 'fa-plus-circle',
                                'deduction' => 'fa-minus-circle',
                                'adjustment' => 'fa-edit',
                                'initial' => 'fa-star',
                                'edit' => 'fa-pen'
                            ];
                            ?>
                            <i class="fas <?php echo $icons[$record['action_type']] ?? 'fa-circle'; ?>"></i>
                            <?php echo ucfirst($record['action_type']); ?>
                        </span>
                    </td>
                    <td><?php echo number_format($record['previous_quantity']); ?></td>
                    <td>
                        <span class="quantity-change <?php echo $record['quantity_change'] >= 0 ? 'positive' : 'negative'; ?>">
                            <?php echo $record['quantity_change'] >= 0 ? '+' : ''; ?><?php echo number_format($record['quantity_change']); ?>
                        </span>
                    </td>
                    <td><strong><?php echo number_format($record['new_quantity']); ?></strong></td>
                    <td>
                        <?php if ($record['performed_by_name']): ?>
                            <div><?php echo htmlspecialchars($record['performed_by_name']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($record['performed_by_email']); ?></small>
                        <?php else: ?>
                            <span class="text-muted">System</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($record['reference_type']): ?>
                            <small class="badge bg-secondary">
                                <?php echo htmlspecialchars($record['reference_type']); ?>
                                <?php if ($record['reference_id']): ?>
                                    #<?php echo $record['reference_id']; ?>
                                <?php endif; ?>
                            </small>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($record['remarks']): ?>
                            <small><?php echo htmlspecialchars($record['remarks']); ?></small>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
