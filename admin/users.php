<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get success/error messages from session
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'add':
                // Check if email already exists
                $check = $db->prepare("SELECT id FROM users WHERE email = ?");
                $check->execute([$_POST['email']]);
                if ($check->rowCount() > 0) {
                    throw new Exception("Email address already exists!");
                }
                
                $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $query = "INSERT INTO users (email, password, role, full_name, department, campus, phone, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $_POST['email'], 
                    $hashed_password, 
                    $_POST['role'], 
                    $_POST['full_name'], 
                    $_POST['department'], 
                    $_POST['campus'], // Add campus
                    $_POST['phone']
                ]);
                $_SESSION['success_message'] = "User added successfully!";
                break;
                
            case 'edit':
                // Check if email already exists for another user
                $check = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $check->execute([$_POST['email'], $_POST['id']]);
                if ($check->rowCount() > 0) {
                    throw new Exception("Email address already exists!");
                }
                
                if (!empty($_POST['password'])) {
                    $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $query = "UPDATE users SET email = ?, password = ?, role = ?, full_name = ?, department = ?, campus = ?, phone = ? WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([
                        $_POST['email'], 
                        $hashed_password, 
                        $_POST['role'], 
                        $_POST['full_name'], 
                        $_POST['department'], 
                        $_POST['campus'], // Add campus
                        $_POST['phone'], 
                        $_POST['id']
                    ]);
                } else {
                    $query = "UPDATE users SET email = ?, role = ?, full_name = ?, department = ?, campus = ?, phone = ? WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([
                        $_POST['email'], 
                        $_POST['role'], 
                        $_POST['full_name'], 
                        $_POST['department'], 
                        $_POST['campus'], // Add campus
                        $_POST['phone'], 
                        $_POST['id']
                    ]);
                }
                $_SESSION['success_message'] = "User updated successfully!";
                break;
                    
                case 'delete':
                    // Don't allow deleting own account
                    if ($_POST['id'] == $_SESSION['user_id']) {
                        throw new Exception("You cannot delete your own account!");
                    }
                    
                    // Check if user has any assignments
                    $check = $db->prepare("SELECT COUNT(*) as count FROM assignment_history WHERE user_id = ?");
                    $check->execute([$_POST['id']]);
                    $assignments = $check->fetch(PDO::FETCH_ASSOC);
                    
                    if ($assignments['count'] > 0) {
                        throw new Exception("Cannot delete user with assignment history. Please reassign or delete history first.");
                    }
                    
                    $query = "DELETE FROM users WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$_POST['id']]);
                    $_SESSION['success_message'] = "User deleted successfully!";
                    break;
                    
                case 'toggle_status':
                    $new_status = $_POST['new_status'];
                    $query = "UPDATE users SET status = ? WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$new_status, $_POST['id']]);
                    $_SESSION['success_message'] = "User status updated successfully!";
                    break;
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = $e->getMessage();
        }
        header("Location: users.php");
        exit();
    }
}

// Get all users with statistics - include campus
$query = "SELECT * FROM users ORDER BY 
          CASE 
              WHEN id = ? THEN 0
              WHEN role = 'admin' THEN 1
              ELSE 2
          END, full_name ASC";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics by campus (optional)
$campus_stats = [];
$campus_query = "SELECT campus, COUNT(*) as count FROM users WHERE campus IS NOT NULL AND campus != '' GROUP BY campus";
$campus_stmt = $db->prepare($campus_query);
$campus_stmt->execute();
$campus_stats = $campus_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_users = count($users);
$active_users = count(array_filter($users, fn($u) => $u['status'] == 'active'));
$admin_count = count(array_filter($users, fn($u) => $u['role'] == 'admin'));
$user_count = $total_users - $admin_count;

// Get recent activity (last 30 days)
$recent_query = "SELECT COUNT(*) as recent FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
$stmt = $db->prepare($recent_query);
$stmt->execute();
$recent = $stmt->fetch(PDO::FETCH_ASSOC);

$page_title = "Users Management";
include '../includes/header.php';
?>

<style>
:root {
    --ucc-green-primary: #2E7D32;
    --ucc-green-secondary: #4CAF50;
    --ucc-green-light: #81C784;
    --ucc-green-soft: #E8F5E9;
    --ucc-green-mint: #C8E6C9;
    --ucc-green-dark: #1B5E20;
    --ucc-white: #FFFFFF;
    --ucc-off-white: #F8F9FA;
    --ucc-gray-light: #F1F8E9;
}

/* Page Header */
.page-header {
    background: linear-gradient(135deg, var(--ucc-green-primary) 0%, var(--ucc-green-dark) 100%);
    border-radius: 24px;
    padding: 2rem 2.5rem;
    margin-bottom: 2rem;
    color: white;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(46, 125, 50, 0.15);
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
    color: var(--ucc-green-primary);
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
    border: 1px solid var(--ucc-green-mint);
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

.stat-icon.primary { background: linear-gradient(135deg, var(--ucc-green-primary) 0%, var(--ucc-green-secondary) 100%); }
.stat-icon.success { background: linear-gradient(135deg, #43A047 0%, #66BB6A 100%); }
.stat-icon.info { background: linear-gradient(135deg, #0288D1 0%, #4FC3F7 100%); }
.stat-icon.warning { background: linear-gradient(135deg, #F57C00 0%, #FFB74D 100%); }

.stat-content {
    flex: 1;
}

.stat-content h3 {
    font-size: 2rem;
    font-weight: 800;
    margin: 0;
    line-height: 1.2;
    color: var(--ucc-green-dark);
}

.stat-content p {
    margin: 0;
    color: #6B7280;
    font-size: 0.9rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-trend {
    font-size: 0.75rem;
    color: var(--ucc-green-primary);
    margin-top: 0.3rem;
}

/* Filter Section */
.filter-section {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
    border: 1px solid var(--ucc-green-mint);
}

.filter-section select {
    border-radius: 12px;
    border: 2px solid var(--ucc-green-mint);
    padding: 0.6rem 1rem;
    transition: all 0.3s ease;
}

.filter-section select:focus {
    border-color: var(--ucc-green-primary);
    box-shadow: 0 0 0 0.25rem rgba(46, 125, 50, 0.15);
}

/* Table Container */
.table-container {
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
    border: 1px solid var(--ucc-green-mint);
    overflow: hidden;
    margin-bottom: 2rem;
}

.table-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--ucc-green-mint);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.table-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--ucc-green-dark);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.table-title i {
    color: var(--ucc-green-primary);
}

.table-badge {
    background: var(--ucc-green-soft);
    color: var(--ucc-green-dark);
    padding: 0.3rem 1rem;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-left: 1rem;
}

/* Users Table */
.users-table {
    width: 100%;
    border-collapse: collapse;
}

.users-table thead th {
    background: var(--ucc-green-soft);
    color: var(--ucc-green-dark);
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 1rem;
    border-bottom: 2px solid var(--ucc-green-primary);
}

.users-table tbody td {
    padding: 1rem;
    border-bottom: 1px solid var(--ucc-green-mint);
    vertical-align: middle;
}

.users-table tbody tr {
    transition: all 0.3s ease;
}

.users-table tbody tr:hover {
    background: var(--ucc-green-soft);
}

/* User Avatar */
.user-avatar {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--ucc-green-primary) 0%, var(--ucc-green-secondary) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1rem;
    box-shadow: 0 4px 10px rgba(46, 125, 50, 0.2);
}

.user-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.user-details h6 {
    font-weight: 700;
    color: var(--ucc-green-dark);
    margin: 0 0 0.2rem 0;
}

.user-details small {
    color: #6B7280;
    font-size: 0.7rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.user-details small i {
    color: var(--ucc-green-primary);
    font-size: 0.6rem;
}

/* Contact Info */
.contact-info {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.contact-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #6B7280;
    font-size: 0.8rem;
}

.contact-item i {
    width: 16px;
    color: var(--ucc-green-primary);
    font-size: 0.8rem;
}

.contact-item .value {
    color: #1F2937;
    font-weight: 500;
}

/* Role Badges */
.role-badge {
    padding: 0.4rem 1rem;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.role-badge.admin {
    background: #E8F5E9;
    color: var(--ucc-green-primary);
    border: 1px solid var(--ucc-green-primary);
}

.role-badge.user {
    background: #E3F2FD;
    color: #1976D2;
    border: 1px solid #1976D2;
}

/* Status Badge */
.status-badge {
    padding: 0.3rem 0.8rem;
    border-radius: 50px;
    font-size: 0.7rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.status-badge.active {
    background: #E8F5E9;
    color: var(--ucc-green-primary);
}

.status-badge.inactive {
    background: #FFEBEE;
    color: #D32F2F;
}

/* Date Display */
.date-display {
    display: flex;
    flex-direction: column;
}

.date-main {
    font-weight: 600;
    color: #1F2937;
    font-size: 0.85rem;
    margin-bottom: 0.2rem;
}

.date-ago {
    font-size: 0.7rem;
    color: #6B7280;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 0.3rem;
    justify-content: flex-end;
}

.action-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    transition: all 0.3s ease;
    cursor: pointer;
}

.action-btn.edit { background: var(--ucc-green-soft); color: var(--ucc-green-primary); }
.action-btn.delete { background: #FFEBEE; color: #D32F2F; }
.action-btn.toggle { background: #E3F2FD; color: #1976D2; }

.action-btn:hover {
    transform: translateY(-2px);
    filter: brightness(0.9);
}

.action-btn.edit:hover { background: var(--ucc-green-primary); color: white; }
.action-btn.delete:hover { background: #D32F2F; color: white; }
.action-btn.toggle:hover { background: #1976D2; color: white; }

/* Current User Badge */
.current-user-badge {
    background: var(--ucc-green-soft);
    color: var(--ucc-green-dark);
    padding: 0.3rem 0.8rem;
    border-radius: 50px;
    font-size: 0.7rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    white-space: nowrap;
}

.current-user-badge i {
    color: var(--ucc-green-primary);
}

/* Modal Styling */
.modal-content {
    border-radius: 20px;
    border: none;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

.modal-header {
    background: linear-gradient(135deg, var(--ucc-green-primary) 0%, var(--ucc-green-dark) 100%);
    color: white;
    border-radius: 20px 20px 0 0;
    padding: 1.5rem;
    border: none;
}

.modal-header.bg-danger { background: linear-gradient(135deg, #DC3545 0%, #B02A37 100%) !important; }

.modal-header .modal-title {
    font-weight: 700;
    font-size: 1.3rem;
}

.modal-header .btn-close {
    background: white;
    opacity: 0.8;
    border-radius: 50%;
    padding: 0.5rem;
}

.modal-body {
    padding: 2rem;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--ucc-green-mint);
}

.form-label {
    font-weight: 600;
    color: var(--ucc-green-dark);
    font-size: 0.9rem;
    margin-bottom: 0.3rem;
}

.form-control, .form-select {
    border-radius: 12px;
    border: 2px solid var(--ucc-green-mint);
    padding: 0.6rem 1rem;
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    border-color: var(--ucc-green-primary);
    box-shadow: 0 0 0 0.25rem rgba(46, 125, 50, 0.15);
}

/* Alert Styling */
.alert {
    border-radius: 16px;
    border: none;
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}

.alert-success {
    background: #E8F5E9;
    color: var(--ucc-green-dark);
    border-left: 4px solid var(--ucc-green-primary);
}

.alert-danger {
    background: #FFEBEE;
    color: #B71C1C;
    border-left: 4px solid #D32F2F;
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
    background: var(--ucc-green-soft);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 2rem;
    font-size: 3rem;
    color: var(--ucc-green-primary);
}

/* Responsive */
@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

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
    
    .users-table thead {
        display: none;
    }
    
    .users-table tbody tr {
        display: block;
        margin-bottom: 1rem;
        border: 1px solid var(--ucc-green-mint);
        border-radius: 10px;
    }
    
    .users-table tbody td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.8rem 1rem;
        border-bottom: 1px solid var(--ucc-green-mint);
    }
    
    .users-table tbody td::before {
        content: attr(data-label);
        font-weight: 600;
        color: var(--ucc-green-dark);
        width: 40%;
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

.stat-card, .table-container {
    animation: slideIn 0.5s ease-out forwards;
}
</style>

<!-- Page Header -->
<div class="page-header">
    <div class="header-content">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <div class="header-title">
                    <i class="fas fa-users"></i>
                    <span>User Management</span>
                </div>
                <p class="header-subtitle">
                    Manage system users, their roles, and access permissions. Add new users, update information, and control account status.
                </p>
                <div class="header-stats">
                    <div class="header-stat-item">
                        <span class="header-stat-number"><?php echo $total_users; ?></span>
                        <span class="header-stat-label">Total Users</span>
                    </div>
                    <div class="header-stat-item">
                        <span class="header-stat-number"><?php echo $active_users; ?></span>
                        <span class="header-stat-label">Active</span>
                    </div>
                    <div class="header-stat-item">
                        <span class="header-stat-number"><?php echo $recent['recent']; ?></span>
                        <span class="header-stat-label">New (30d)</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="header-actions">
                    <button type="button" class="btn" data-bs-toggle="modal" data-bs-target="#addModal">
                        <i class="fas fa-plus-circle"></i> Add User
                    </button>
                    <a href="assignment_history.php" class="btn">
                        <i class="fas fa-history"></i> History
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Alert Messages -->
<?php if ($success_message): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i>
    <?php echo htmlspecialchars($success_message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error_message): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i>
    <?php echo htmlspecialchars($error_message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $total_users; ?></h3>
            <p>Total Users</p>
            <div class="stat-trend">
                <i class="fas fa-user-plus"></i> <?php echo $recent['recent']; ?> new
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon success">
            <i class="fas fa-user-check"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $active_users; ?></h3>
            <p>Active Users</p>
            <div class="stat-trend">
                <i class="fas fa-circle" style="color: #2E7D32;"></i> Online now
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon info">
            <i class="fas fa-user-shield"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $admin_count; ?></h3>
            <p>Administrators</p>
            <div class="stat-trend">
                <i class="fas fa-crown"></i> System admins
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon warning">
            <i class="fas fa-user"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $user_count; ?></h3>
            <p>Regular Users</p>
            <div class="stat-trend">
                <i class="fas fa-users"></i> Standard access
            </div>
        </div>
    </div>
</div>

<!-- Filter Section -->
<div class="filter-section" style="background: white; border-radius: 20px; padding: 1.5rem; margin-bottom: 2rem; border: 1px solid var(--user-green-mint);">
    <div class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label small fw-bold text-uppercase" style="color: var(--user-green-dark);">
                <i class="fas fa-filter me-1"></i>Filter by Campus
            </label>
            <select class="form-select" id="campusFilter">
                <option value="">All Campuses</option>
                <option value="South Campus">South Campus</option>
                <option value="Congressional Campus">Congressional Campus</option>
                <option value="Camarin Campus">Camarin Campus</option>
                <option value="Bagong Silang Campus">Bagong Silang Campus</option>
            </select>
        </div>
        
        <div class="col-md-4">
            <label class="form-label small fw-bold text-uppercase" style="color: var(--user-green-dark);">
                <i class="fas fa-building me-1"></i>Filter by Department
            </label>
            <select class="form-select" id="departmentFilter">
                <option value="">All Departments</option>
                <?php
                $dept_filter_query = "SELECT department_name FROM departments WHERE is_active = 1 ORDER BY department_name ASC";
                $dept_filter_stmt = $db->prepare($dept_filter_query);
                $dept_filter_stmt->execute();
                $dept_filter_list = $dept_filter_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($dept_filter_list as $dept):
                ?>
                <option value="<?php echo htmlspecialchars($dept['department_name']); ?>">
                    <?php echo htmlspecialchars($dept['department_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-md-4">
            <label class="form-label small fw-bold text-uppercase" style="color: var(--user-green-dark);">
                <i class="fas fa-tag me-1"></i>Filter by Role
            </label>
            <select class="form-select" id="roleFilter">
                <option value="">All Roles</option>
                <option value="admin">Administrators</option>
                <option value="user">Regular Users</option>
            </select>
        </div>
        
        <div class="col-12 mt-3">
            <div class="d-flex justify-content-end gap-2">
                <button class="btn btn-outline-secondary btn-sm" onclick="clearFilters()">
                    <i class="fas fa-times me-1"></i>Clear Filters
                </button>
                <span class="text-muted small align-self-center" id="filterResultCount"></span>
            </div>
        </div>
    </div>
</div>

<!-- Users Table -->
<div class="table-container">
    <div class="table-header">
        <div class="table-title">
            <i class="fas fa-list"></i>
            System Users
            <span class="table-badge"><?php echo $total_users; ?> registered</span>
        </div>
        <div>
            <span class="text-muted small">
                <i class="fas fa-info-circle me-1"></i>Click on action buttons to manage users
            </span>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="users-table" id="usersTable">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Contact</th>
                    <th>Department</th>
                    <th>Campus</th> <!-- New column -->
                    <th>Role</th>
                    <th>Joined</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                <tr>
                    <td colspan="7" class="text-center py-5">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h5 class="mb-2">No Users Found</h5>
                            <p class="text-muted mb-3">Get started by adding your first user.</p>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                                <i class="fas fa-plus-circle me-2"></i>Add New User
                            </button>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($users as $user): ?>
                <?php 
                    $isCurrentUser = ($user['id'] == $_SESSION['user_id']);
                    $status = $user['status'] ?? 'active';
                ?>
                <tr>
                    <td data-label="User">
                        <div class="user-info">
                            <div class="user-avatar">
                                <?php 
                                $initials = '';
                                $name_parts = explode(' ', $user['full_name']);
                                foreach ($name_parts as $part) {
                                    $initials .= strtoupper(substr($part, 0, 1));
                                }
                                echo htmlspecialchars(substr($initials, 0, 2));
                                ?>
                            </div>
                            <div class="user-details">
                                <h6><?php echo htmlspecialchars($user['full_name']); ?></h6>
                                <small>
                                    <i class="fas fa-id-card"></i>
                                    ID: <?php echo $user['id']; ?>
                                </small>
                            </div>
                        </div>
                    </td>
                    
                    <td data-label="Contact">
                        <div class="contact-info">
                            <span class="contact-item">
                                <i class="fas fa-envelope"></i>
                                <span class="value"><?php echo htmlspecialchars($user['email']); ?></span>
                            </span>
                            <?php if (!empty($user['phone'])): ?>
                            <span class="contact-item">
                                <i class="fas fa-phone-alt"></i>
                                <span class="value"><?php echo htmlspecialchars($user['phone']); ?></span>
                            </span>
                            <?php endif; ?>
                        </div>
                    </td>
                    
                    <td data-label="Department">
                        <?php if (!empty($user['department'])): ?>
                        <span class="contact-item">
                            <i class="fas fa-building"></i>
                            <span class="value"><?php echo htmlspecialchars($user['department']); ?></span>
                        </span>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>

                    <td data-label="Campus"> <!-- New column -->
                        <?php if (!empty($user['campus'])): ?>
                        <span class="contact-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span class="value"><?php echo htmlspecialchars($user['campus']); ?></span>
                        </span>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    
                    <td data-label="Role">
                        <span class="role-badge <?php echo $user['role']; ?>">
                            <i class="fas fa-<?php echo $user['role'] == 'admin' ? 'crown' : 'user'; ?>"></i>
                            <?php echo ucfirst($user['role']); ?>
                        </span>
                    </td>
                    
                    <td data-label="Joined">
                        <div class="date-display">
                            <span class="date-main"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></span>
                            <span class="date-ago">
                                <i class="far fa-clock"></i>
                                <?php 
                                $days_since = floor((time() - strtotime($user['created_at'])) / (60 * 60 * 24));
                                echo $days_since . ' days ago';
                                ?>
                            </span>
                        </div>
                    </td>
                    
                    <td data-label="Status">
                        <span class="status-badge <?php echo $status; ?>">
                            <i class="fas fa-circle"></i>
                            <?php echo ucfirst($status); ?>
                        </span>
                    </td>
                    
                    <td data-label="Actions" class="text-end">
                        <div class="action-buttons">
                            <?php if (!$isCurrentUser): ?>
                            <button type="button" class="action-btn edit" 
                                    data-id="<?php echo $user['id']; ?>"
                                    data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                    data-fullname="<?php echo htmlspecialchars($user['full_name']); ?>"
                                    data-department="<?php echo htmlspecialchars($user['department'] ?? ''); ?>"
                                    data-phone="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                    data-role="<?php echo $user['role']; ?>"
                                    data-bs-toggle="modal" data-bs-target="#editModal"
                                    title="Edit User">
                                <i class="fas fa-edit"></i>
                            </button>
                            
                            <button type="button" class="action-btn delete" 
                                    data-id="<?php echo $user['id']; ?>"
                                    data-name="<?php echo htmlspecialchars($user['full_name']); ?>"
                                    data-bs-toggle="modal" data-bs-target="#deleteModal"
                                    title="Delete User">
                                <i class="fas fa-trash"></i>
                            </button>
                            
                            <?php else: ?>
                            <span class="current-user-badge">
                                <i class="fas fa-user-check"></i>
                                Current User
                            </span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle me-2"></i>
                    Add New User
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addUserForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-user me-2 text-success"></i>Full Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" name="full_name" placeholder="e.g., John Doe" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-envelope me-2 text-success"></i>Email Address <span class="text-danger">*</span>
                            </label>
                            <input type="email" class="form-control" name="email" placeholder="user@example.com" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-lock me-2 text-success"></i>Password <span class="text-danger">*</span>
                            </label>
                            <input type="password" class="form-control" name="password" placeholder="••••••••" required>
                            <small class="text-muted">Minimum 8 characters</small>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-tag me-2 text-success"></i>Role <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" name="role" required>
                                <option value="user">Regular User</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-building me-2 text-success"></i>Department
                            </label>
                            <select class="form-select" name="department" id="add_department">
                                <option value="">-- Select Department --</option>
                                <?php
                                $dept_query = "SELECT department_name FROM departments WHERE is_active = 1 ORDER BY department_name ASC";
                                $dept_stmt = $db->prepare($dept_query);
                                $dept_stmt->execute();
                                $departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($departments as $dept):
                                ?>
                                <option value="<?php echo htmlspecialchars($dept['department_name']); ?>">
                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-map-marker-alt me-2 text-success"></i>Campus
                            </label>
                            <select class="form-select" name="campus" id="add_campus">
                                <option value="">-- Select Campus --</option>
                                <option value="South Campus">South Campus</option>
                                <option value="Congressional Campus">Congressional Campus</option>
                                <option value="Camarin Campus">Camarin Campus</option>
                                <option value="Bagong Silang Campus">Bagong Silang Campus</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-phone me-2 text-success"></i>Phone Number
                            </label>
                            <input type="text" class="form-control" name="phone" placeholder="e.g., 09123456789">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success px-4">
                        <i class="fas fa-save me-2"></i>Add User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>
                    Edit User
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editUserForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-user me-2 text-success"></i>Full Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" name="full_name" id="edit_fullname" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-envelope me-2 text-success"></i>Email Address <span class="text-danger">*</span>
                            </label>
                            <input type="email" class="form-control" name="email" id="edit_email" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-lock me-2 text-success"></i>New Password
                            </label>
                            <input type="password" class="form-control" name="password" id="edit_password" placeholder="Leave blank to keep current">
                            <small class="text-muted">Only fill if you want to change password</small>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-tag me-2 text-success"></i>Role <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" name="role" id="edit_role" required>
                                <option value="user">Regular User</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-building me-2 text-success"></i>Department
                            </label>
                            <select class="form-select" name="department" id="edit_department">
                                <option value="">-- Select Department --</option>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept['department_name']); ?>">
                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-map-marker-alt me-2 text-success"></i>Campus
                            </label>
                            <select class="form-select" name="campus" id="edit_campus">
                                <option value="">-- Select Campus --</option>
                                <option value="South Campus">South Campus</option>
                                <option value="Congressional Campus">Congressional Campus</option>
                                <option value="Camarin Campus">Camarin Campus</option>
                                <option value="Bagong Silang Campus">Bagong Silang Campus</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-phone me-2 text-success"></i>Phone Number
                            </label>
                            <input type="text" class="form-control" name="phone" id="edit_phone">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success px-4">
                        <i class="fas fa-save me-2"></i>Update User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-trash-alt me-2"></i>
                    Delete User
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="deleteUserForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_id">
                    
                    <div class="text-center mb-4">
                        <div class="text-danger mb-3">
                            <i class="fas fa-exclamation-triangle fa-4x"></i>
                        </div>
                        <h5 class="mb-3">Are you absolutely sure?</h5>
                        <p class="text-muted mb-0">
                            This action cannot be undone. This will permanently delete the user
                            <strong class="text-danger" id="delete_user_name"></strong> and remove all their data.
                        </p>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>Warning:</strong> If this user has any equipment assignments, they must be reassigned first.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4">
                        <i class="fas fa-trash-alt me-2"></i>Delete User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Keep all your existing HTML/PHP code exactly as is, just replace the JavaScript section at the bottom -->

<script>
$(document).ready(function() {
    // Initialize DataTable
    if ($.fn.DataTable.isDataTable('#usersTable')) {
        $('#usersTable').DataTable().destroy();
    }

    $('#usersTable').DataTable({
        "pageLength": 25,
        "order": [[4, "desc"]], // Sort by joined date
        "columnDefs": [
            { "orderable": false, "targets": [6] } // Disable sorting on actions column
        ],
        "language": {
            "search": "<i class='fas fa-search me-2'></i>",
            "searchPlaceholder": "Search users...",
            "paginate": {
                "previous": "<i class='fas fa-chevron-left'></i>",
                "next": "<i class='fas fa-chevron-right'></i>"
            }
        }
    });

    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut(500);
    }, 5000);
});

// Filter functionality
function applyFilters() {
    const campusFilter = $('#campusFilter').val();
    const departmentFilter = $('#departmentFilter').val();
    const roleFilter = $('#roleFilter').val();
    
    let visibleCount = 0;
    
    $('#usersTable tbody tr').each(function() {
        const row = $(this);
        
        // Skip empty state row
        if (row.find('td[colspan]').length) return;
        
        let showRow = true;
        
        // Campus filter
        if (campusFilter) {
            const campusCell = row.find('td:eq(3) .value').text(); // Campus is 4th column (index 3)
            if (!campusCell.includes(campusFilter)) {
                showRow = false;
            }
        }
        
        // Department filter
        if (showRow && departmentFilter) {
            const deptCell = row.find('td:eq(2) .value').text(); // Department is 3rd column (index 2)
            if (!deptCell.includes(departmentFilter)) {
                showRow = false;
            }
        }
        
        // Role filter
        if (showRow && roleFilter) {
            const roleCell = row.find('td:eq(4) .role-badge').text().trim().toLowerCase(); // Role is 5th column (index 4)
            if (!roleCell.includes(roleFilter)) {
                showRow = false;
            }
        }
        
        if (showRow) {
            row.show();
            visibleCount++;
        } else {
            row.hide();
        }
    });
    
    $('#filterResultCount').text(`Showing ${visibleCount} users`);
}

function clearFilters() {
    $('#campusFilter').val('');
    $('#departmentFilter').val('');
    $('#roleFilter').val('');
    applyFilters();
}

// Attach filter change events
$('#campusFilter, #departmentFilter, #roleFilter').on('change', applyFilters);

// Update edit button handler to include campus
$(document).on('click', '.action-btn.edit', function(e) {
    e.preventDefault();
    
    const id = $(this).data('id');
    const email = $(this).data('email');
    const fullname = $(this).data('fullname');
    const department = $(this).data('department');
    const campus = $(this).data('campus'); // Add campus data
    const phone = $(this).data('phone');
    const role = $(this).data('role');
    
    console.log('Edit user:', { id, email, fullname, department, campus, phone, role });
    
    $('#edit_id').val(id);
    $('#edit_email').val(email);
    $('#edit_fullname').val(fullname);
    $('#edit_department').val(department || '');
    $('#edit_campus').val(campus || ''); // Set campus value
    $('#edit_phone').val(phone || '');
    $('#edit_role').val(role);
    $('#edit_password').val(''); // Clear password field
});

// Delete button handler - Fixed selector
$(document).on('click', '.action-btn.delete', function(e) {
    e.preventDefault();
    
    const id = $(this).data('id');
    const name = $(this).data('name');
    
    console.log('Delete user:', { id, name });
    
    $('#delete_id').val(id);
    $('#delete_user_name').text(name);
});

// Form validation for Add/Edit
$('#addUserForm, #editUserForm').on('submit', function(e) {
    const password = $(this).find('input[name="password"]').val();
    const email = $(this).find('input[name="email"]').val();
    const fullname = $(this).find('input[name="full_name"]').val();
    
    // Basic validation
    if (!fullname || fullname.trim() === '') {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            text: 'Full name is required.'
        });
        return false;
    }
    
    if (!email || email.trim() === '') {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            text: 'Email address is required.'
        });
        return false;
    }
    
    // Email format validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Invalid Email',
            text: 'Please enter a valid email address.'
        });
        return false;
    }
    
    // Password validation for new users (add form only)
    if ($(this).attr('id') === 'addUserForm') {
        if (!password || password.length < 8) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Invalid Password',
                text: 'Password must be at least 8 characters long.'
            });
            return false;
        }
    } else {
        // For edit form, if password is provided, check length
        if (password && password.length > 0 && password.length < 8) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Invalid Password',
                text: 'Password must be at least 8 characters long.'
            });
            return false;
        }
    }
});

// Email format visual validation
$('input[type="email"]').on('blur', function() {
    const email = $(this).val();
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (email && !regex.test(email)) {
        $(this).addClass('is-invalid');
    } else {
        $(this).removeClass('is-invalid');
    }
});

// Phone number formatting (optional)
$('input[name="phone"]').on('input', function() {
    let phone = $(this).val().replace(/\D/g, ''); // Remove non-digits
    if (phone.length > 11) {
        phone = phone.substr(0, 11);
    }
    $(this).val(phone);
});

// Success/Error message handling with SweetAlert
<?php if ($success_message): ?>
Swal.fire({
    icon: 'success',
    title: 'Success!',
    text: '<?php echo addslashes($success_message); ?>',
    timer: 3000,
    showConfirmButton: false
});
<?php endif; ?>

<?php if ($error_message): ?>
Swal.fire({
    icon: 'error',
    title: 'Error!',
    text: '<?php echo addslashes($error_message); ?>',
    confirmButtonColor: '#d33'
});
<?php endif; ?>

// Clear form when add modal is closed
$('#addModal').on('hidden.bs.modal', function() {
    $(this).find('form')[0].reset();
    $(this).find('.is-invalid').removeClass('is-invalid');
});

// Clear password field when edit modal is closed
$('#editModal').on('hidden.bs.modal', function() {
    $('#edit_password').val('');
    $(this).find('.is-invalid').removeClass('is-invalid');
});
</script>

<?php include '../includes/footer.php'; ?>