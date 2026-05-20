<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$campus_filter = $_GET['campus'] ?? '';

// Query to fetch history with details
$query = "SELECT th.*, u.full_name as processor_name 
          FROM transfer_history th
          LEFT JOIN users u ON th.transferred_by = u.id
          WHERE 1=1";

$params = [];

if ($search) {
    $query .= " AND (th.new_accountable LIKE :search 
                OR th.from_campus LIKE :search 
                OR th.to_campus LIKE :search 
                OR th.equipment_type LIKE :search
                OR th.previous_accountable LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($date_from) {
    $query .= " AND DATE(th.transfer_date) >= :date_from";
    $params[':date_from'] = $date_from;
}

if ($date_to) {
    $query .= " AND DATE(th.transfer_date) <= :date_to";
    $params[':date_to'] = $date_to;
}

if ($campus_filter) {
    $query .= " AND (th.from_campus = :campus OR th.to_campus = :campus)";
    $params[':campus'] = $campus_filter;
}

$query .= " ORDER BY th.transfer_date DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique campuses for filter dropdown
$campus_query = "SELECT DISTINCT from_campus as campus FROM transfer_history 
                 UNION 
                 SELECT DISTINCT to_campus FROM transfer_history 
                 ORDER BY campus";
$campus_stmt = $db->prepare($campus_query);
$campus_stmt->execute();
$campuses = $campus_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_transfers = count($history);
$unique_equipment = count(array_unique(array_column($history, 'equipment_type')));
$unique_accountable = count(array_unique(array_column($history, 'new_accountable')));

$page_title = "Transfer History";
include '../includes/header.php';
?>

<style>
:root {
    --th-green-primary: #2E7D32;
    --th-green-secondary: #4CAF50;
    --th-green-light: #81C784;
    --th-green-soft: #E8F5E9;
    --th-green-mint: #C8E6C9;
    --th-green-dark: #1B5E20;
    --th-white: #FFFFFF;
    --th-off-white: #F8F9FA;
    --th-gray-light: #F1F8E9;
    --th-gray: #6B7280;
    --th-border: #E0E0E0;
    --th-shadow: 0 10px 30px rgba(46, 125, 50, 0.1);
}

/* Page Header */
.page-header {
    background: linear-gradient(135deg, var(--th-green-primary) 0%, var(--th-green-dark) 100%);
    border-radius: 24px;
    padding: 2rem 2.5rem;
    margin-bottom: 2rem;
    color: white;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(46, 125, 50, 0.2);
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
    color: var(--th-green-primary);
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: var(--th-shadow);
    border: 1px solid var(--th-green-mint);
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

.stat-icon.primary { background: linear-gradient(135deg, var(--th-green-primary) 0%, var(--th-green-secondary) 100%); }
.stat-icon.success { background: linear-gradient(135deg, #43A047 0%, #66BB6A 100%); }
.stat-icon.warning { background: linear-gradient(135deg, #F57C00 0%, #FFB74D 100%); }

.stat-content h3 {
    font-size: 2rem;
    font-weight: 800;
    margin: 0;
    line-height: 1.2;
    color: var(--th-green-dark);
}

.stat-content p {
    margin: 0;
    color: var(--th-gray);
    font-size: 0.9rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-trend {
    font-size: 0.75rem;
    color: var(--th-green-primary);
    margin-top: 0.3rem;
}

/* Filter Card */
.filter-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: var(--th-shadow);
    border: 1px solid var(--th-green-mint);
}

.filter-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--th-green-dark);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-title i {
    color: var(--th-green-primary);
}

.filter-badge {
    background: var(--th-green-soft);
    color: var(--th-green-dark);
    padding: 0.3rem 1rem;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-left: 1rem;
}

/* Table Container */
.table-container {
    background: white;
    border-radius: 20px;
    box-shadow: var(--th-shadow);
    border: 1px solid var(--th-green-mint);
    overflow: hidden;
    margin-bottom: 2rem;
}

.table-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--th-green-mint);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.table-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--th-green-dark);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.table-title i {
    color: var(--th-green-primary);
}

.table-badge {
    background: var(--th-green-soft);
    color: var(--th-green-dark);
    padding: 0.3rem 1rem;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-left: 1rem;
}

/* Transfer Table */
.transfer-table {
    width: 100%;
    border-collapse: collapse;
}

.transfer-table thead th {
    background: var(--th-green-soft);
    color: var(--th-green-dark);
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 1rem;
    border-bottom: 2px solid var(--th-green-primary);
}

.transfer-table tbody td {
    padding: 1rem;
    border-bottom: 1px solid var(--th-green-mint);
    vertical-align: middle;
}

.transfer-table tbody tr {
    transition: all 0.3s ease;
}

.transfer-table tbody tr:hover {
    background: var(--th-green-soft);
}

/* Date Display */
.date-display {
    display: flex;
    flex-direction: column;
}

.date-main {
    font-weight: 700;
    color: var(--th-green-dark);
    font-size: 0.95rem;
}

.date-time {
    font-size: 0.75rem;
    color: var(--th-gray);
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.date-time i {
    color: var(--th-green-primary);
    font-size: 0.6rem;
}

/* Equipment Type Badge */
.type-badge {
    padding: 0.4rem 1rem;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: var(--th-green-soft);
    color: var(--th-green-primary);
    border: 1px solid var(--th-green-primary);
}

.type-badge i {
    font-size: 0.8rem;
}

/* Movement Indicator */
.movement-container {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.campus-from {
    color: #D32F2F;
    font-weight: 600;
    font-size: 0.85rem;
    background: #FFEBEE;
    padding: 0.3rem 0.8rem;
    border-radius: 50px;
    white-space: nowrap;
}

.campus-to {
    color: var(--th-green-primary);
    font-weight: 600;
    font-size: 0.85rem;
    background: #E8F5E9;
    padding: 0.3rem 0.8rem;
    border-radius: 50px;
    white-space: nowrap;
}

.movement-arrow {
    color: var(--th-gray);
    font-size: 1rem;
}

/* Accountable Info */
.accountable-info {
    display: flex;
    flex-direction: column;
}

.accountable-new {
    font-weight: 700;
    color: var(--th-green-dark);
    font-size: 0.9rem;
    margin-bottom: 0.2rem;
}

.accountable-prev {
    font-size: 0.7rem;
    color: var(--th-gray);
}

.accountable-prev strong {
    color: #D32F2F;
    font-weight: 600;
}

/* Processor Info */
.processor-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.processor-avatar {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--th-green-primary) 0%, var(--th-green-secondary) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 0.9rem;
}

.processor-details {
    display: flex;
    flex-direction: column;
}

.processor-name {
    font-weight: 600;
    color: var(--th-green-dark);
    font-size: 0.9rem;
}

.processor-role {
    font-size: 0.65rem;
    color: var(--th-gray);
}

/* Action Button */
.btn-report {
    background: #FFEBEE;
    color: #D32F2F;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-report:hover {
    background: #D32F2F;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(211, 47, 47, 0.3);
}

/* PAR Button */
.btn-par {
    background: #E8F5E9;
    color: var(--th-green-primary);
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
}

.btn-par:hover {
    background: var(--th-green-primary);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(46, 125, 50, 0.3);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-state-icon {
    width: 120px;
    height: 120px;
    border-radius: 60px;
    background: var(--th-green-soft);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 2rem;
    font-size: 3rem;
    color: var(--th-green-primary);
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
    
    .filter-row {
        flex-direction: column;
    }
    
    .transfer-table thead {
        display: none;
    }
    
    .transfer-table tbody tr {
        display: block;
        margin-bottom: 1rem;
        border: 1px solid var(--th-green-mint);
        border-radius: 12px;
    }
    
    .transfer-table tbody td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.8rem 1rem;
        border-bottom: 1px solid var(--th-green-mint);
    }
    
    .transfer-table tbody td::before {
        content: attr(data-label);
        font-weight: 600;
        color: var(--th-green-dark);
        width: 40%;
    }
    
    .movement-container {
        flex-wrap: wrap;
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

.stat-card, .filter-card, .table-container {
    animation: slideIn 0.5s ease-out forwards;
}

/* Print styles */
@media print {
    .page-header, .stats-grid, .filter-card, .header-actions, .btn-report {
        display: none;
    }
    
    .table-container {
        box-shadow: none;
        border: 1px solid #ddd;
    }
}
</style>

<!-- Page Header -->
<div class="page-header">
    <div class="header-content">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <div class="header-title">
                    <i class="fas fa-history"></i>
                    <span>Transfer History</span>
                </div>
                <p class="header-subtitle">
                    Track all equipment movements between campuses. Monitor transfer dates, accountable persons, and generate detailed reports.
                </p>
                <div class="header-stats">
                    <div class="header-stat-item">
                        <span class="header-stat-number"><?php echo $total_transfers; ?></span>
                        <span class="header-stat-label">Total Transfers</span>
                    </div>
                    <div class="header-stat-item">
                        <span class="header-stat-number"><?php echo $unique_equipment; ?></span>
                        <span class="header-stat-label">Equipment Types</span>
                    </div>
                    <div class="header-stat-item">
                        <span class="header-stat-number"><?php echo $unique_accountable; ?></span>
                        <span class="header-stat-label">Accountable Persons</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="header-actions">
                    <a href="all_equipment.php" class="btn">
                        <i class="fas fa-arrow-left me-2"></i>Back to Inventory
                    </a>
                    <button class="btn" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Print Report
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary">
            <i class="fas fa-exchange-alt"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $total_transfers; ?></h3>
            <p>Total Transfers</p>
            <div class="stat-trend">
                <i class="fas fa-calendar"></i> All time
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon success">
            <i class="fas fa-boxes"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $unique_equipment; ?></h3>
            <p>Equipment Types</p>
            <div class="stat-trend">
                <i class="fas fa-tag"></i> Unique categories
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon warning">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $unique_accountable; ?></h3>
            <p>Accountable Persons</p>
            <div class="stat-trend">
                <i class="fas fa-user-check"></i> New assignees
            </div>
        </div>
    </div>
</div>

<!-- Filter Card -->
<div class="filter-card">
    <div class="filter-title">
        <i class="fas fa-filter"></i>
        Filter Transfer Records
        <?php if ($search || $date_from || $date_to || $campus_filter): ?>
        <span class="filter-badge">Filtered</span>
        <?php endif; ?>
    </div>
    
    <form method="GET" class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Search</label>
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0">
                    <i class="fas fa-search text-muted"></i>
                </span>
                <input type="text" name="search" class="form-control border-start-0" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Name, campus, equipment...">
            </div>
        </div>
        
        <div class="col-md-2">
            <label class="form-label">From Date</label>
            <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
        </div>
        
        <div class="col-md-2">
            <label class="form-label">To Date</label>
            <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
        </div>
        
        <div class="col-md-2">
            <label class="form-label">Campus</label>
            <select name="campus" class="form-select">
                <option value="">All Campuses</option>
                <?php foreach ($campuses as $campus): ?>
                <option value="<?php echo htmlspecialchars($campus['campus']); ?>" 
                    <?php echo $campus_filter == $campus['campus'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($campus['campus']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-md-2 d-flex align-items-end">
            <div class="d-flex gap-2 w-100">
                <button type="submit" class="btn btn-success flex-grow-1">
                    <i class="fas fa-search me-2"></i>Filter
                </button>
                <a href="transfer_history.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </div>
    </form>
</div>

<!-- Transfer History Table -->
<div class="table-container">
    <div class="table-header">
        <div class="table-title">
            <i class="fas fa-list"></i>
            Transfer Records
            <span class="table-badge"><?php echo count($history); ?> records</span>
        </div>
        <div>
            <span class="text-muted small">
                <i class="fas fa-info-circle me-1"></i>Click report button to view detailed PDF
            </span>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="transfer-table" id="transferTable">
            <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>Equipment Type</th>
                    <th>Movement</th>
                    <th>Accountable Person</th>
                    <th>Processed By</th>
                    <th class="text-center">Report</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($history)): ?>
                <tr>
                    <td colspan="6" class="text-center py-5">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-history"></i>
                            </div>
                            <h5 class="mb-2">No Transfer Records Found</h5>
                            <p class="text-muted mb-3">Try adjusting your filters or make your first equipment transfer.</p>
                            <a href="all_equipment.php" class="btn btn-success">
                                <i class="fas fa-exchange-alt me-2"></i>Go to Equipment
                            </a>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($history as $row): ?>
                <tr>
                    <td data-label="Date & Time">
                        <div class="date-display">
                            <span class="date-main"><?php echo date('M d, Y', strtotime($row['transfer_date'])); ?></span>
                            <span class="date-time">
                                <i class="fas fa-circle"></i>
                                <?php echo date('h:i A', strtotime($row['transfer_date'])); ?>
                            </span>
                        </div>
                    </td>
                    
                    <td data-label="Equipment Type">
                        <span class="type-badge">
                            <i class="fas <?php 
                                echo $row['equipment_type'] == 'computer_lab' ? 'fa-desktop' : 
                                    ($row['equipment_type'] == 'kitchen' ? 'fa-utensils' : 
                                    ($row['equipment_type'] == 'office' ? 'fa-briefcase' : 
                                    ($row['equipment_type'] == 'regular_lab' ? 'fa-flask' : 
                                    ($row['equipment_type'] == 'general' ? 'fa-tools' : 'fa-box')))); 
                            ?>"></i>
                            <?php echo strtoupper(str_replace('_', ' ', $row['equipment_type'])); ?>
                        </span>
                    </td>
                    
                    <td data-label="Movement">
                        <div class="movement-container">
                            <span class="campus-from">
                                <i class="fas fa-map-marker-alt me-1"></i>
                                <?php echo htmlspecialchars($row['from_campus']); ?>
                            </span>
                            <i class="fas fa-long-arrow-alt-right movement-arrow"></i>
                            <span class="campus-to">
                                <i class="fas fa-map-marker-alt me-1"></i>
                                <?php echo htmlspecialchars($row['to_campus']); ?>
                            </span>
                        </div>
                    </td>
                    
                    <td data-label="Accountable Person">
                        <div class="accountable-info">
                            <span class="accountable-new">
                                <i class="fas fa-user-check text-success me-1"></i>
                                <?php echo htmlspecialchars($row['new_accountable']); ?>
                            </span>
                            <?php if (!empty($row['previous_accountable'])): ?>
                            <span class="accountable-prev">
                                <strong>Previous:</strong> <?php echo htmlspecialchars($row['previous_accountable']); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </td>
                    
                    <td data-label="Processed By">
                        <div class="processor-info">
                            <div class="processor-avatar">
                                <?php echo strtoupper(substr($row['processor_name'] ?? 'U', 0, 1)); ?>
                            </div>
                            <div class="processor-details">
                                <span class="processor-name">
                                    <?php echo htmlspecialchars($row['processor_name'] ?? 'Unknown'); ?>
                                </span>
                                <span class="processor-role">Administrator</span>
                            </div>
                        </div>
                    </td>
                    
                    <td data-label="Report" class="text-center">
                        <div class="d-flex gap-2 justify-content-center">
                            <a href="generate_transfer_report.php?id=<?php echo $row['id']; ?>" 
                            class="btn-report" target="_blank" title="Generate Transfer Report">
                                <i class="fas fa-file-pdf"></i>
                                <span>Report</span>
                            </a>
                            <a href="generate_par_from_transfer_history.php?transfer_id=<?php echo $row['id']; ?>" 
                            class="btn-par" target="_blank" title="Generate PAR Report">
                                <i class="fas fa-file-signature"></i>
                                <span>PAR</span>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable for better sorting and searching
    if ($('#transferTable').length && !$('#transferTable').hasClass('dataTable')) {
        $('#transferTable').DataTable({
            "pageLength": 25,
            "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            "order": [[0, "desc"]],
            "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>t<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            "language": {
                "lengthMenu": "Show _MENU_ entries",
                "search": "<i class='fas fa-search me-2'></i>",
                "searchPlaceholder": "Search records...",
                "paginate": {
                    "previous": "<i class='fas fa-chevron-left'></i>",
                    "next": "<i class='fas fa-chevron-right'></i>"
                }
            },
            "initComplete": function() {
                $('.dataTables_filter input').addClass('form-control form-control-sm').attr('placeholder', 'Search...');
                $('.dataTables_length select').addClass('form-select form-select-sm');
            }
        });
    }
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut(500);
    }, 5000);
});
</script>

<?php include '../includes/footer.php'; ?>