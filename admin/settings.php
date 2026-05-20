<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        // ==================== DEPARTMENT ACTIONS ====================
        
        // Add new department
        if ($_POST['action'] === 'add_department') {
            $department_name = trim($_POST['department_name']);
            $description = trim($_POST['description'] ?? '');
            
            $check_stmt = $db->prepare("SELECT id FROM departments WHERE department_name = ?");
            $check_stmt->execute([$department_name]);
            
            if ($check_stmt->rowCount() > 0) {
                $_SESSION['error_message'] = "Department already exists!";
            } else {
                $insert_stmt = $db->prepare("INSERT INTO departments (department_name, description) VALUES (?, ?)");
                $insert_stmt->execute([$department_name, $description]);
                $_SESSION['success_message'] = "Department added successfully!";
            }
            header("Location: settings.php?tab=departments");
            exit();
        }
        
        // Edit department
        if ($_POST['action'] === 'edit_department') {
            $department_id = $_POST['department_id'];
            $department_name = trim($_POST['department_name']);
            $description = trim($_POST['description'] ?? '');
            
            $update_stmt = $db->prepare("UPDATE departments SET department_name = ?, description = ? WHERE id = ?");
            $update_stmt->execute([$department_name, $description, $department_id]);
            
            $_SESSION['success_message'] = "Department updated successfully!";
            header("Location: settings.php?tab=departments");
            exit();
        }
        
        // Toggle department status
        if ($_POST['action'] === 'toggle_department') {
            $department_id = $_POST['department_id'];
            $current_status = $_POST['current_status'];
            $new_status = $current_status ? 0 : 1;
            
            $update_stmt = $db->prepare("UPDATE departments SET is_active = ? WHERE id = ?");
            $update_stmt->execute([$new_status, $department_id]);
            
            $_SESSION['success_message'] = "Department status updated successfully!";
            header("Location: settings.php?tab=departments");
            exit();
        }
        
        // Delete department
        if ($_POST['action'] === 'delete_department') {
            $department_id = $_POST['department_id'];
            
            $check_usage = $db->prepare("SELECT COUNT(*) as count FROM request_groups WHERE office = (SELECT department_name FROM departments WHERE id = ?)");
            $check_usage->execute([$department_id]);
            $usage = $check_usage->fetch(PDO::FETCH_ASSOC);
            
            if ($usage['count'] > 0) {
                $_SESSION['error_message'] = "Cannot delete department because it is being used in request history. You can deactivate it instead.";
            } else {
                $delete_stmt = $db->prepare("DELETE FROM departments WHERE id = ?");
                $delete_stmt->execute([$department_id]);
                $_SESSION['success_message'] = "Department deleted successfully!";
            }
            header("Location: settings.php?tab=departments");
            exit();
        }
        
        // ==================== ARTICLE ACTIONS ====================
        
        // Add new article
        if ($_POST['action'] === 'add_article') {
            $article_name = trim($_POST['article_name']);
            $equipment_type = $_POST['equipment_type'];
            $has_dual_serial = isset($_POST['has_dual_serial']) ? 1 : 0;
            $display_order = intval($_POST['display_order']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            // Check if article already exists for this equipment type
            $check_stmt = $db->prepare("SELECT id FROM equipment_articles WHERE article_name = ? AND equipment_type = ?");
            $check_stmt->execute([$article_name, $equipment_type]);
            
            if ($check_stmt->rowCount() > 0) {
                $_SESSION['error_message'] = "Article already exists for this equipment type!";
            } else {
                $insert_stmt = $db->prepare("INSERT INTO equipment_articles (article_name, equipment_type, has_dual_serial, display_order, is_active) VALUES (?, ?, ?, ?, ?)");
                $insert_stmt->execute([$article_name, $equipment_type, $has_dual_serial, $display_order, $is_active]);
                $_SESSION['success_message'] = "Article added successfully!";
            }
            header("Location: settings.php?tab=articles");
            exit();
        }
        
        // Edit article
        if ($_POST['action'] === 'edit_article') {
            $article_id = $_POST['article_id'];
            $article_name = trim($_POST['article_name']);
            $equipment_type = $_POST['equipment_type'];
            $has_dual_serial = isset($_POST['has_dual_serial']) ? 1 : 0;
            $display_order = intval($_POST['display_order']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            // Check if article already exists for this equipment type (excluding current)
            $check_stmt = $db->prepare("SELECT id FROM equipment_articles WHERE article_name = ? AND equipment_type = ? AND id != ?");
            $check_stmt->execute([$article_name, $equipment_type, $article_id]);
            
            if ($check_stmt->rowCount() > 0) {
                $_SESSION['error_message'] = "Article already exists for this equipment type!";
            } else {
                $update_stmt = $db->prepare("UPDATE equipment_articles SET article_name = ?, equipment_type = ?, has_dual_serial = ?, display_order = ?, is_active = ? WHERE id = ?");
                $update_stmt->execute([$article_name, $equipment_type, $has_dual_serial, $display_order, $is_active, $article_id]);
                $_SESSION['success_message'] = "Article updated successfully!";
            }
            header("Location: settings.php?tab=articles");
            exit();
        }
        
        // Delete article
        if ($_POST['action'] === 'delete_article') {
            $article_id = $_POST['article_id'];
            
            // Check if article is being used in any equipment tables
            $article_info = $db->prepare("SELECT article_name, equipment_type FROM equipment_articles WHERE id = ?");
            $article_info->execute([$article_id]);
            $article = $article_info->fetch(PDO::FETCH_ASSOC);
            
            if ($article) {
                $table_name = '';
                switch ($article['equipment_type']) {
                    case 'computer':
                        $table_name = 'computer_inventory';
                        break;
                    case 'general':
                        $table_name = 'general_equipment';
                        break;
                    default:
                        $table_name = null;
                }
                
                if ($table_name) {
                    $check_usage = $db->prepare("SELECT COUNT(*) as count FROM {$table_name} WHERE article = ?");
                    $check_usage->execute([$article['article_name']]);
                    $usage = $check_usage->fetch(PDO::FETCH_ASSOC);
                    
                    if ($usage['count'] > 0) {
                        $_SESSION['error_message'] = "Cannot delete article because it is being used in {$usage['count']} equipment records. You can deactivate it instead.";
                        header("Location: settings.php?tab=articles");
                        exit();
                    }
                }
            }
            
            $delete_stmt = $db->prepare("DELETE FROM equipment_articles WHERE id = ?");
            $delete_stmt->execute([$article_id]);
            $_SESSION['success_message'] = "Article deleted successfully!";
            header("Location: settings.php?tab=articles");
            exit();
        }
        
        // Bulk reorder articles
        if ($_POST['action'] === 'reorder_articles') {
            $orders = json_decode($_POST['orders'], true);
            if (is_array($orders)) {
                foreach ($orders as $order_data) {
                    $update_stmt = $db->prepare("UPDATE equipment_articles SET display_order = ? WHERE id = ?");
                    $update_stmt->execute([$order_data['display_order'], $order_data['id']]);
                }
                $_SESSION['success_message'] = "Articles reordered successfully!";
            }
            header("Location: settings.php?tab=articles");
            exit();
        }
        
        // ==================== ARCHIVE ACTIONS ====================
        
        // Restore from archive
        if ($_POST['action'] === 'restore_from_archive') {
            $archive_id = $_POST['archive_id'];
            
            try {
                $db->beginTransaction();
                
                $get_stmt = $db->prepare("SELECT * FROM archive_items WHERE id = ?");
                $get_stmt->execute([$archive_id]);
                $archived_item = $get_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($archived_item) {
                    $restore_query = "INSERT INTO condemned_equipment (
                        model, category, serial_number, equipment_type, reason_condemned,
                        condemned_date, condemned_by, disposal_status, estimated_value, remarks
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)";
                    
                    $restore_stmt = $db->prepare($restore_query);
                    $restore_stmt->execute([
                        $archived_item['model'],
                        $archived_item['category'],
                        $archived_item['serial_number'],
                        $archived_item['equipment_type'],
                        $archived_item['reason_condemned'],
                        $archived_item['condemned_date'],
                        $archived_item['condemned_by'],
                        $archived_item['estimated_value'],
                        $archived_item['remarks']
                    ]);
                    
                    $delete_stmt = $db->prepare("DELETE FROM archive_items WHERE id = ?");
                    $delete_stmt->execute([$archive_id]);
                    
                    $db->commit();
                    $_SESSION['success_message'] = "Item successfully restored from archive!";
                } else {
                    $_SESSION['error_message'] = "Archived item not found.";
                }
            } catch (Exception $e) {
                $db->rollBack();
                $_SESSION['error_message'] = "Error restoring item: " . $e->getMessage();
            }
            
            header("Location: settings.php?tab=archive");
            exit();
        }
        
        // Permanently delete from archive
        if ($_POST['action'] === 'delete_from_archive') {
            $archive_id = $_POST['archive_id'];
            
            try {
                $delete_stmt = $db->prepare("DELETE FROM archive_items WHERE id = ?");
                $delete_stmt->execute([$archive_id]);
                
                $_SESSION['success_message'] = "Item permanently deleted from archive!";
            } catch (Exception $e) {
                $_SESSION['error_message'] = "Error deleting item: " . $e->getMessage();
            }
            
            header("Location: settings.php?tab=archive");
            exit();
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        header("Location: settings.php?tab=departments");
        exit();
    }
}

// Get success/error messages
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Get active tab
$active_tab = $_GET['tab'] ?? 'general';

// Get all departments
$departments_query = "SELECT * FROM departments ORDER BY department_name ASC";
$departments_stmt = $db->prepare($departments_query);
$departments_stmt->execute();
$departments = $departments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all articles grouped by equipment type
$articles_query = "SELECT * FROM equipment_articles ORDER BY 
                    CASE equipment_type
                        WHEN 'computer' THEN 1
                        WHEN 'general' THEN 2
                        WHEN 'kitchen' THEN 3
                        WHEN 'office' THEN 4
                        WHEN 'lab' THEN 5
                        ELSE 6
                    END, display_order ASC, article_name ASC";
$articles_stmt = $db->prepare($articles_query);
$articles_stmt->execute();
$all_articles = $articles_stmt->fetchAll(PDO::FETCH_ASSOC);

// Group articles by type
$articles_by_type = [
    'computer' => [],
    'general' => [],
    'kitchen' => [],
    'office' => [],
    'lab' => []
];

foreach ($all_articles as $article) {
    if (isset($articles_by_type[$article['equipment_type']])) {
        $articles_by_type[$article['equipment_type']][] = $article;
    }
}

// Get archive items count for badge
$archive_count_query = "SELECT COUNT(*) as count FROM archive_items";
$archive_count_stmt = $db->prepare($archive_count_query);
$archive_count_stmt->execute();
$archive_count = $archive_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get archive items with user names
$archive_items_query = "SELECT 
    ai.*,
    u_condemned.full_name as condemned_by_name,
    u_archived.full_name as archived_by_name
    FROM archive_items ai
    LEFT JOIN users u_condemned ON ai.condemned_by = u_condemned.id
    LEFT JOIN users u_archived ON ai.archived_by = u_archived.id
    ORDER BY ai.archived_date DESC";
$archive_items_stmt = $db->prepare($archive_items_query);
$archive_items_stmt->execute();
$archive_items = $archive_items_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "System Settings";
include '../includes/header.php';
?>

<style>
.settings-container {
    max-width: 1200px;
    margin: 0 auto;
}

.settings-header {
    background: linear-gradient(135deg, var(--ucc-green-primary) 0%, var(--ucc-green-dark) 100%);
    border-radius: 24px;
    padding: 2rem;
    margin-bottom: 2rem;
    color: white;
    box-shadow: 0 20px 40px rgba(46, 125, 50, 0.2);
}

.settings-tabs {
    background: white;
    border-radius: 16px;
    padding: 0.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
    border: 1px solid #e9ecef;
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.settings-tab {
    padding: 0.8rem 1.5rem;
    border-radius: 12px;
    font-weight: 600;
    color: #6B7280;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.settings-tab:hover {
    background: #f8f9fa;
    color: var(--ucc-green-primary);
}

.settings-tab.active {
    background: linear-gradient(135deg, var(--ucc-green-primary) 0%, var(--ucc-green-dark) 100%);
    color: white;
}

.settings-tab i {
    font-size: 1rem;
}

.settings-card {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
    border: 1px solid #e9ecef;
}

.settings-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: #1F2937;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e9ecef;
}

.settings-title i {
    color: var(--ucc-green-primary);
}

/* Department List Styles */
.department-list {
    margin-top: 2rem;
}

.department-item {
    background: #f8fafc;
    border-radius: 16px;
    padding: 1.2rem;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: all 0.3s ease;
    border: 1px solid #e9ecef;
}

.department-item:hover {
    transform: translateX(5px);
    border-color: var(--ucc-green-primary);
    box-shadow: 0 5px 15px rgba(46, 125, 50, 0.1);
}

.department-info h4 {
    font-weight: 700;
    color: #1F2937;
    margin: 0 0 0.3rem 0;
}

.department-info p {
    color: #6B7280;
    font-size: 0.85rem;
    margin: 0;
}

.department-badge {
    padding: 0.3rem 1rem;
    border-radius: 50px;
    font-size: 0.7rem;
    font-weight: 600;
}

.badge-active {
    background: #d1e7dd;
    color: #0a5e3a;
}

.badge-inactive {
    background: #f8d7da;
    color: #9a1c2a;
}

.department-actions {
    display: flex;
    gap: 0.5rem;
}

/* Article Management Styles */
.article-group {
    margin-bottom: 2rem;
    border: 1px solid #e9ecef;
    border-radius: 16px;
    overflow: hidden;
}

.article-group-header {
    background: linear-gradient(135deg, var(--ucc-green-primary) 0%, var(--ucc-green-dark) 100%);
    color: white;
    padding: 1rem 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
}

.article-group-header h5 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.8rem;
}

.article-group-header i {
    font-size: 1.2rem;
}

.article-group-body {
    padding: 1.5rem;
    display: none;
}

.article-group-body.expanded {
    display: block;
}

.article-item {
    background: #f8fafc;
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 0.8rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: all 0.3s ease;
    border: 1px solid #e9ecef;
    cursor: move;
}

.article-item:hover {
    transform: translateX(5px);
    border-color: var(--ucc-green-primary);
    box-shadow: 0 2px 8px rgba(46, 125, 50, 0.1);
}

.article-item.dragging {
    opacity: 0.5;
}

.article-info {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.drag-handle {
    cursor: move;
    color: #9ca3af;
    transition: color 0.3s ease;
}

.drag-handle:hover {
    color: var(--ucc-green-primary);
}

.article-name {
    font-weight: 700;
    color: #1F2937;
    font-size: 1rem;
}

.article-badge {
    padding: 0.2rem 0.8rem;
    border-radius: 50px;
    font-size: 0.7rem;
    font-weight: 600;
}

.badge-dual-serial {
    background: #FEE2E2;
    color: #DC2626;
}

.badge-single-serial {
    background: #D1FAE5;
    color: #10B981;
}

.badge-active-status {
    background: #d1e7dd;
    color: #0a5e3a;
}

.badge-inactive-status {
    background: #f8d7da;
    color: #9a1c2a;
}

.article-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    transition: all 0.3s ease;
    cursor: pointer;
}

.btn-icon.edit {
    background: #e7f1ff;
    color: #0d6efd;
}

.btn-icon.delete {
    background: #f8d7da;
    color: #dc3545;
}

.btn-icon.edit:hover {
    background: #0d6efd;
    color: white;
}

.btn-icon.delete:hover {
    background: #dc3545;
    color: white;
}

/* Archive Table Styles */
.table-responsive {
    margin-top: 1.5rem;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table thead th {
    background: #f8fafc;
    color: #1F2937;
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 1rem;
    border-bottom: 2px solid #e9ecef;
}

.table tbody td {
    padding: 1rem;
    border-bottom: 1px solid #e9ecef;
    vertical-align: middle;
}

.table tbody tr:hover {
    background: #f8fafc;
}

/* Category Badges */
.category-badge {
    padding: 0.3rem 0.8rem;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.category-badge.system-unit { background: #FEE2E2; color: #DC2626; }
.category-badge.monitor { background: #DBEAFE; color: #3B82F6; }
.category-badge.keyboard { background: #D1FAE5; color: #10B981; }
.category-badge.avr { background: #FFF3E0; color: #F97316; }
.category-badge.other { background: #F3F4F6; color: #6B7280; }

/* Action Buttons for Archive */
.action-buttons {
    display: flex;
    gap: 0.3rem;
    justify-content: center;
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

.action-btn.view { background: #DBEAFE; color: #3B82F6; }
.action-btn.deploy { background: #D1FAE5; color: #10B981; }
.action-btn.delete { background: #FEE2E2; color: #DC2626; }

.action-btn:hover {
    transform: translateY(-2px);
    filter: brightness(0.9);
}

.action-btn.view:hover { background: #3B82F6; color: white; }
.action-btn.deploy:hover { background: #10B981; color: white; }
.action-btn.delete:hover { background: #DC2626; color: white; }

/* Modal Styles */
.modal-content {
    border-radius: 20px;
    border: none;
}

.modal-header {
    background: linear-gradient(135deg, var(--ucc-green-primary) 0%, var(--ucc-green-dark) 100%);
    color: white;
    border-radius: 20px 20px 0 0;
    border: none;
}

.modal-header.bg-secondary {
    background: linear-gradient(135deg, #6B7280 0%, #4B5563 100%) !important;
}

.modal-header .btn-close {
    filter: brightness(0) invert(1);
}

.modal-footer {
    border-top: 1px solid #e9ecef;
    padding: 1.5rem;
}

.form-label {
    font-weight: 600;
    color: #1F2937;
    font-size: 0.9rem;
    margin-bottom: 0.3rem;
}

.form-control, .form-select {
    border-radius: 12px;
    border: 2px solid #e9ecef;
    padding: 0.6rem 1rem;
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    border-color: var(--ucc-green-primary);
    box-shadow: 0 0 0 0.25rem rgba(46, 125, 50, 0.15);
}

textarea.form-control {
    min-height: 80px;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    background: #f8fafc;
    border-radius: 16px;
}

.empty-state-icon {
    width: 80px;
    height: 80px;
    border-radius: 40px;
    background: #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    font-size: 2rem;
    color: #9ca3af;
}

/* Detail View */
.detail-section {
    background: #F9FAFB;
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 1rem;
}

.detail-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    color: #6B7280;
    font-weight: 600;
    letter-spacing: 0.5px;
    margin-bottom: 0.2rem;
}

.detail-value {
    font-size: 1rem;
    font-weight: 600;
    color: #1F2937;
}

.reason-box {
    background: #FEE2E2;
    border-left: 4px solid #DC2626;
    padding: 1rem;
    border-radius: 8px;
}

/* Info Alert */
.info-alert {
    background: #F0F9FF;
    border-left: 4px solid #0ea5e9;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
}
</style>

<div class="settings-container">
    <!-- Settings Header -->
    <div class="settings-header">
        <div class="d-flex align-items-center gap-3">
            <i class="fas fa-cog fa-3x opacity-75"></i>
            <div>
                <h1 class="display-5 fw-bold mb-2">System Settings</h1>
                <p class="mb-0 fs-5 opacity-90">Configure and manage system preferences, departments, articles, and other settings.</p>
            </div>
        </div>
    </div>

    <!-- Settings Tabs -->
    <div class="settings-tabs">
        <a href="settings.php?tab=general" class="settings-tab <?php echo $active_tab === 'general' ? 'active' : ''; ?>">
            <i class="fas fa-sliders-h"></i>
            <span>General</span>
        </a>
        <a href="settings.php?tab=departments" class="settings-tab <?php echo $active_tab === 'departments' ? 'active' : ''; ?>">
            <i class="fas fa-building"></i>
            <span>Departments</span>
        </a>
        <a href="settings.php?tab=articles" class="settings-tab <?php echo $active_tab === 'articles' ? 'active' : ''; ?>">
            <i class="fas fa-tags"></i>
            <span>Articles</span>
        </a>
        <a href="settings.php?tab=users" class="settings-tab <?php echo $active_tab === 'users' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>Users</span>
        </a>
        <a href="settings.php?tab=backup" class="settings-tab <?php echo $active_tab === 'backup' ? 'active' : ''; ?>">
            <i class="fas fa-database"></i>
            <span>Backup</span>
        </a>
        <a href="settings.php?tab=archive" class="settings-tab <?php echo $active_tab === 'archive' ? 'active' : ''; ?>">
            <i class="fas fa-archive"></i>
            <span>Archive</span>
            <?php if ($archive_count > 0): ?>
            <span class="badge bg-danger ms-1"><?php echo $archive_count; ?></span>
            <?php endif; ?>
        </a>
    </div>

    <!-- Alert Messages -->
    <?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?php echo $success_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?php echo $error_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- ==================== DEPARTMENTS TAB ==================== -->
    <?php if ($active_tab === 'departments'): ?>
    <div class="settings-card">
        <div class="settings-title">
            <i class="fas fa-building"></i>
            Department Management
            <button class="btn btn-success ms-auto" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                <i class="fas fa-plus me-2"></i>Add Department
            </button>
        </div>

        <div class="department-list">
            <?php if (empty($departments)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-building"></i>
                </div>
                <h5 class="mb-2">No Departments Found</h5>
                <p class="text-muted mb-3">Get started by adding your first department.</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                    <i class="fas fa-plus me-2"></i>Add Department
                </button>
            </div>
            <?php else: ?>
                <?php foreach ($departments as $dept): ?>
                <div class="department-item">
                    <div class="department-info">
                        <h4><?php echo htmlspecialchars($dept['department_name']); ?></h4>
                        <p><?php echo htmlspecialchars($dept['description'] ?: 'No description'); ?></p>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <span class="department-badge <?php echo $dept['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                            <?php echo $dept['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                        <div class="department-actions">
                            <button class="btn-icon edit" onclick="editDepartment(<?php echo $dept['id']; ?>, '<?php echo htmlspecialchars($dept['department_name']); ?>', '<?php echo htmlspecialchars($dept['description']); ?>')" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Toggle department status?')">
                                <input type="hidden" name="action" value="toggle_department">
                                <input type="hidden" name="department_id" value="<?php echo $dept['id']; ?>">
                                <input type="hidden" name="current_status" value="<?php echo $dept['is_active']; ?>">
                                <button type="submit" class="btn-icon toggle" title="<?php echo $dept['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                    <i class="fas <?php echo $dept['is_active'] ? 'fa-toggle-on' : 'fa-toggle-off'; ?>"></i>
                                </button>
                            </form>
                            
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this department? This action cannot be undone.')">
                                <input type="hidden" name="action" value="delete_department">
                                <input type="hidden" name="department_id" value="<?php echo $dept['id']; ?>">
                                <button type="submit" class="btn-icon delete" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Department Modal -->
    <div class="modal fade" id="addDepartmentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus-circle me-2"></i>
                        Add New Department
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_department">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Department Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="department_name" required placeholder="e.g., IT Department">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Description</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Enter department description"></textarea>
                        </div>
                        
                        <div class="alert alert-info border-0 small">
                            <i class="fas fa-info-circle me-2"></i>
                            This department will be available in the Request Items form dropdown.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Save Department
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Department Modal -->
    <div class="modal fade" id="editDepartmentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>
                        Edit Department
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_department">
                        <input type="hidden" name="department_id" id="edit_department_id">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Department Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="department_name" id="edit_department_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Description</label>
                            <textarea class="form-control" name="description" id="edit_department_description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Department
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ==================== ARTICLES TAB ==================== -->
    <?php elseif ($active_tab === 'articles'): ?>
    <div class="settings-card">
        <div class="settings-title">
            <i class="fas fa-tags"></i>
            Equipment Articles Management
            <button class="btn btn-success ms-auto" data-bs-toggle="modal" data-bs-target="#addArticleModal">
                <i class="fas fa-plus me-2"></i>Add Article
            </button>
            <button class="btn btn-outline-secondary ms-2" id="saveOrderBtn" style="display: none;">
                <i class="fas fa-save me-2"></i>Save Order
            </button>
        </div>

        <div class="info-alert">
            <i class="fas fa-info-circle me-2 text-info"></i>
            <strong>Note:</strong> Articles marked with <span class="badge-dual-serial badge px-2 py-1 ms-1">Dual Serial</span> will show both Monitor and System Unit serial fields when "Computer Package" is selected. Drag and drop to reorder articles.
        </div>

        <?php
        $equipment_type_labels = [
            'computer' => ['label' => 'Computer Equipment', 'icon' => 'fa-desktop', 'color' => 'primary'],
            'general' => ['label' => 'General Equipment', 'icon' => 'fa-tools', 'color' => 'secondary'],
            'kitchen' => ['label' => 'Kitchen Equipment', 'icon' => 'fa-utensils', 'color' => 'warning'],
            'office' => ['label' => 'Office Equipment', 'icon' => 'fa-briefcase', 'color' => 'info'],
            'lab' => ['label' => 'Lab Equipment', 'icon' => 'fa-flask', 'color' => 'danger']
        ];
        ?>

        <?php foreach ($equipment_type_labels as $type_key => $type_info): ?>
        <div class="article-group" data-type="<?php echo $type_key; ?>">
            <div class="article-group-header" onclick="toggleArticleGroup(this)">
                <h5>
                    <i class="fas <?php echo $type_info['icon']; ?>"></i>
                    <?php echo $type_info['label']; ?>
                    <span class="badge bg-white text-dark ms-2"><?php echo count($articles_by_type[$type_key]); ?> articles</span>
                </h5>
                <i class="fas fa-chevron-down"></i>
            </div>
            <div class="article-group-body" id="group-<?php echo $type_key; ?>">
                <?php if (empty($articles_by_type[$type_key])): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas <?php echo $type_info['icon']; ?>"></i>
                    </div>
                    <p class="text-muted mb-0">No articles found for <?php echo $type_info['label']; ?>.</p>
                    <button class="btn btn-sm btn-outline-primary mt-3" data-bs-toggle="modal" data-bs-target="#addArticleModal" 
                            onclick="document.getElementById('equipment_type').value='<?php echo $type_key; ?>'">
                        <i class="fas fa-plus me-1"></i>Add Article
                    </button>
                </div>
                <?php else: ?>
                    <?php foreach ($articles_by_type[$type_key] as $article): ?>
                    <div class="article-item" data-id="<?php echo $article['id']; ?>" data-order="<?php echo $article['display_order']; ?>">
                        <div class="article-info">
                            <i class="fas fa-grip-vertical drag-handle"></i>
                            <span class="article-name"><?php echo htmlspecialchars($article['article_name']); ?></span>
                            <?php if ($article['has_dual_serial']): ?>
                            <span class="article-badge badge-dual-serial">
                                <i class="fas fa-exchange-alt me-1"></i>Dual Serial
                            </span>
                            <?php else: ?>
                            <span class="article-badge badge-single-serial">
                                <i class="fas fa-hashtag me-1"></i>Single Serial
                            </span>
                            <?php endif; ?>
                            <span class="article-badge <?php echo $article['is_active'] ? 'badge-active-status' : 'badge-inactive-status'; ?>">
                                <?php echo $article['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                            <small class="text-muted">Order: <?php echo $article['display_order']; ?></small>
                        </div>
                        <div class="article-actions">
                            <button class="btn-icon edit" onclick="editArticle(<?php echo $article['id']; ?>, '<?php echo htmlspecialchars($article['article_name']); ?>', '<?php echo $article['equipment_type']; ?>', <?php echo $article['has_dual_serial']; ?>, <?php echo $article['display_order']; ?>, <?php echo $article['is_active']; ?>)" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this article? This will remove it from all equipment dropdowns.')">
                                <input type="hidden" name="action" value="delete_article">
                                <input type="hidden" name="article_id" value="<?php echo $article['id']; ?>">
                                <button type="submit" class="btn-icon delete" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Add Article Modal -->
    <div class="modal fade" id="addArticleModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus-circle me-2"></i>
                        Add New Article
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="addArticleForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_article">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Equipment Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="equipment_type" id="add_equipment_type" required>
                                <option value="computer">Computer Equipment</option>
                                <option value="general">General Equipment</option>
                                <option value="kitchen">Kitchen Equipment</option>
                                <option value="office">Office Equipment</option>
                                <option value="lab">Lab Equipment</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Article Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="article_name" required placeholder="e.g., Computer Package">
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="has_dual_serial" id="has_dual_serial" value="1" style="cursor: pointer; width: 3em; height: 1.5em;">
                                <label class="form-check-label fw-semibold" for="has_dual_serial">
                                    <i class="fas fa-exchange-alt me-2 text-warning"></i>Has Dual Serial (Monitor + System Unit)
                                </label>
                            </div>
                            <small class="text-muted">Check this if the article requires both Monitor and System Unit serial numbers (e.g., Computer Package).</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Display Order</label>
                            <input type="number" class="form-control" name="display_order" value="0" placeholder="0 = auto">
                            <small class="text-muted">Lower numbers appear first. Leave 0 for auto-assignment.</small>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" checked style="cursor: pointer; width: 3em; height: 1.5em;">
                                <label class="form-check-label fw-semibold" for="is_active">
                                    <i class="fas fa-check-circle me-2 text-success"></i>Active
                                </label>
                            </div>
                            <small class="text-muted">Inactive articles will not appear in dropdowns.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Save Article
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Article Modal -->
    <div class="modal fade" id="editArticleModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>
                        Edit Article
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editArticleForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_article">
                        <input type="hidden" name="article_id" id="edit_article_id">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Equipment Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="equipment_type" id="edit_equipment_type" required>
                                <option value="computer">Computer Equipment</option>
                                <option value="general">General Equipment</option>
                                <option value="kitchen">Kitchen Equipment</option>
                                <option value="office">Office Equipment</option>
                                <option value="lab">Lab Equipment</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Article Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="article_name" id="edit_article_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="has_dual_serial" id="edit_has_dual_serial" value="1" style="cursor: pointer; width: 3em; height: 1.5em;">
                                <label class="form-check-label fw-semibold" for="edit_has_dual_serial">
                                    <i class="fas fa-exchange-alt me-2 text-warning"></i>Has Dual Serial (Monitor + System Unit)
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Display Order</label>
                            <input type="number" class="form-control" name="display_order" id="edit_display_order" value="0">
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active" value="1" style="cursor: pointer; width: 3em; height: 1.5em;">
                                <label class="form-check-label fw-semibold" for="edit_is_active">
                                    <i class="fas fa-check-circle me-2 text-success"></i>Active
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Article
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Hidden form for reordering -->
    <form method="POST" id="reorderForm" style="display: none;">
        <input type="hidden" name="action" value="reorder_articles">
        <input type="hidden" name="orders" id="reorderOrders">
    </form>

    <!-- ==================== GENERAL TAB ==================== -->
    <?php elseif ($active_tab === 'general'): ?>
    <div class="settings-card">
        <div class="settings-title">
            <i class="fas fa-sliders-h"></i>
            General Settings
        </div>
        <div class="text-center py-5 text-muted">
            <i class="fas fa-tools fa-4x mb-3"></i>
            <h5>General settings coming soon...</h5>
            <p>This section is under development.</p>
        </div>
    </div>

    <!-- ==================== USERS TAB ==================== -->
    <?php elseif ($active_tab === 'users'): ?>
    <div class="settings-card">
        <div class="settings-title">
            <i class="fas fa-users"></i>
            User Management
        </div>
        <div class="text-center py-5 text-muted">
            <i class="fas fa-tools fa-4x mb-3"></i>
            <h5>User management coming soon...</h5>
            <p>This section is under development.</p>
        </div>
    </div>

    <!-- ==================== BACKUP TAB ==================== -->
    <?php elseif ($active_tab === 'backup'): ?>
    <div class="settings-card">
        <div class="settings-title">
            <i class="fas fa-database"></i>
            Backup & Restore
        </div>
        <div class="text-center py-5 text-muted">
            <i class="fas fa-tools fa-4x mb-3"></i>
            <h5>Backup features coming soon...</h5>
            <p>This section is under development.</p>
        </div>
    </div>

    <!-- ==================== ARCHIVE TAB ==================== -->
    <?php elseif ($active_tab === 'archive'): ?>
    <div class="settings-card">
        <div class="settings-title">
            <i class="fas fa-archive"></i>
            Archived Items
            <span class="badge bg-secondary ms-2"><?php echo count($archive_items); ?> items</span>
        </div>

        <?php if (empty($archive_items)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="fas fa-archive"></i>
            </div>
            <h5 class="mb-2">No Archived Items</h5>
            <p class="text-muted mb-3">Archived condemned items will appear here.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover" id="archiveTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Model</th>
                        <th>Category</th>
                        <th>Serial Number</th>
                        <th>Archived Date</th>
                        <th>Archived By</th>
                        <th>Archive Reason</th>
                        <th>Original Condemned Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($archive_items as $item): ?>
                    <tr>
                        <td><code>#<?php echo $item['id']; ?></code></td>
                        <td>
                            <strong><?php echo htmlspecialchars($item['model']); ?></strong>
                        </td>
                        <td>
                            <span class="category-badge <?php 
                                $catClass = 'other';
                                if (strpos($item['category'], 'System') !== false) $catClass = 'system-unit';
                                else if (strpos($item['category'], 'Monitor') !== false) $catClass = 'monitor';
                                else if (strpos($item['category'], 'Keyboard') !== false) $catClass = 'keyboard';
                                else if (strpos($item['category'], 'AVR') !== false) $catClass = 'avr';
                                echo $catClass;
                            ?>">
                                <?php echo htmlspecialchars($item['category']); ?>
                            </span>
                        </td>
                        <td><code><?php echo htmlspecialchars($item['serial_number']) ?: 'N/A'; ?></code></td>
                        <td>
                            <?php echo date('M d, Y', strtotime($item['archived_date'])); ?>
                            <small class="text-muted d-block"><?php echo date('h:i A', strtotime($item['archived_date'])); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($item['archived_by_name'] ?? 'Unknown'); ?></td>
                        <td><?php echo htmlspecialchars($item['archive_reason']); ?></td>
                        <td>
                            <?php echo $item['condemned_date'] ? date('M d, Y', strtotime($item['condemned_date'])) : 'N/A'; ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button type="button" class="action-btn view" 
                                        onclick="viewArchivedItem(<?php echo $item['id']; ?>)" 
                                        title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Restore this item to condemned list?');">
                                    <input type="hidden" name="action" value="restore_from_archive">
                                    <input type="hidden" name="archive_id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" class="action-btn deploy" title="Restore to Condemned">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                </form>
                                
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Permanently delete this item from archive? This cannot be undone.');">
                                    <input type="hidden" name="action" value="delete_from_archive">
                                    <input type="hidden" name="archive_id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" class="action-btn delete" title="Permanently Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- View Archived Item Modal -->
    <div class="modal fade" id="viewArchiveModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-secondary">
                    <h5 class="modal-title text-white">
                        <i class="fas fa-search me-2"></i>
                        Archived Item Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="archiveViewModalBody">
                    <div class="text-center py-4">
                        <div class="spinner-border text-secondary mb-3"></div>
                        <p class="text-muted">Loading details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Auto-hide alerts after 5 seconds
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(alert => {
        alert.style.transition = 'opacity 0.5s ease';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    });
}, 5000);

// Department functions
function editDepartment(id, name, description) {
    document.getElementById('edit_department_id').value = id;
    document.getElementById('edit_department_name').value = name;
    document.getElementById('edit_department_description').value = description;
    new bootstrap.Modal(document.getElementById('editDepartmentModal')).show();
}

// Article functions
function toggleArticleGroup(header) {
    const body = header.nextElementSibling;
    body.classList.toggle('expanded');
    const icon = header.querySelector('i:last-child');
    if (body.classList.contains('expanded')) {
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-up');
    } else {
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
    }
}

function editArticle(id, name, type, hasDualSerial, displayOrder, isActive) {
    document.getElementById('edit_article_id').value = id;
    document.getElementById('edit_article_name').value = name;
    document.getElementById('edit_equipment_type').value = type;
    document.getElementById('edit_has_dual_serial').checked = hasDualSerial === 1 || hasDualSerial === true;
    document.getElementById('edit_display_order').value = displayOrder;
    document.getElementById('edit_is_active').checked = isActive === 1 || isActive === true;
    new bootstrap.Modal(document.getElementById('editArticleModal')).show();
}

// Drag and drop reordering
let draggedItem = null;
let saveOrderBtn = document.getElementById('saveOrderBtn');
let orderChanged = false;

function initDragAndDrop() {
    const items = document.querySelectorAll('.article-item');
    
    items.forEach(item => {
        item.setAttribute('draggable', 'true');
        
        item.addEventListener('dragstart', function(e) {
            draggedItem = this;
            this.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        
        item.addEventListener('dragend', function(e) {
            this.classList.remove('dragging');
            orderChanged = true;
            if (saveOrderBtn) saveOrderBtn.style.display = 'inline-block';
        });
        
        item.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        });
        
        item.addEventListener('drop', function(e) {
            e.preventDefault();
            if (draggedItem && draggedItem !== this && draggedItem.parentNode === this.parentNode) {
                const parent = this.parentNode;
                const children = Array.from(parent.children);
                const draggedIndex = children.indexOf(draggedItem);
                const targetIndex = children.indexOf(this);
                
                if (draggedIndex < targetIndex) {
                    this.parentNode.insertBefore(draggedItem, this.nextSibling);
                } else {
                    this.parentNode.insertBefore(draggedItem, this);
                }
                
                // Update display order attributes
                updateDisplayOrders(parent);
            }
        });
    });
}

function updateDisplayOrders(container) {
    const items = container.querySelectorAll('.article-item');
    items.forEach((item, index) => {
        const newOrder = index + 1;
        item.setAttribute('data-order', newOrder);
        const orderSpan = item.querySelector('.text-muted');
        if (orderSpan) {
            orderSpan.textContent = `Order: ${newOrder}`;
        }
    });
}

function saveOrders() {
    const orders = [];
    const groups = document.querySelectorAll('.article-group-body');
    
    groups.forEach(group => {
        if (group.classList.contains('expanded')) {
            const items = group.querySelectorAll('.article-item');
            items.forEach((item, index) => {
                orders.push({
                    id: parseInt(item.getAttribute('data-id')),
                    display_order: index + 1
                });
            });
        }
    });
    
    if (orders.length > 0) {
        document.getElementById('reorderOrders').value = JSON.stringify(orders);
        document.getElementById('reorderForm').submit();
    }
}

// View archived item function
function viewArchivedItem(id) {
    const modal = new bootstrap.Modal(document.getElementById('viewArchiveModal'));
    const body = document.getElementById('archiveViewModalBody');
    
    body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-secondary mb-3"></div><p class="text-muted">Loading details...</p></div>';
    modal.show();
    
    fetch('get_archived_item_details.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                body.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                return;
            }
            
            let categoryClass = 'other';
            if (data.category.includes('System')) categoryClass = 'system-unit';
            else if (data.category.includes('Monitor')) categoryClass = 'monitor';
            else if (data.category.includes('Keyboard')) categoryClass = 'keyboard';
            else if (data.category.includes('AVR')) categoryClass = 'avr';
            
            body.innerHTML = `
                <div class="detail-section">
                    <div class="detail-label">Equipment Model</div>
                    <div class="detail-value">${escapeHtml(data.model)}</div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="detail-label">Category</div>
                        <span class="category-badge ${categoryClass}">${escapeHtml(data.category)}</span>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-label">Serial Number</div>
                        <code>${escapeHtml(data.serial_number) || 'N/A'}</code>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="detail-label">Archived Date</div>
                        <div class="detail-value">${escapeHtml(data.archived_date)}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-label">Archived By</div>
                        <div class="detail-value">${escapeHtml(data.archived_by_name)}</div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="detail-label">Archive Reason</div>
                    <p class="bg-light p-2 rounded">${escapeHtml(data.archive_reason)}</p>
                </div>
                
                <div class="reason-box mb-3">
                    <div class="detail-label text-danger">Original Condemnation Reason</div>
                    <p class="mb-0">${escapeHtml(data.reason_condemned)}</p>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="detail-label">Condemned Date</div>
                        <div class="detail-value">${escapeHtml(data.condemned_date)}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-label">Condemned By</div>
                        <div class="detail-value">${escapeHtml(data.condemned_by_name)}</div>
                    </div>
                </div>
                
                <div class="mt-3">
                    <div class="detail-label">Accountable Person (at condemnation)</div>
                    <p class="bg-light p-2 rounded">${escapeHtml(data.remarks) || 'N/A'}</p>
                </div>
            `;
        })
        .catch(error => {
            body.innerHTML = '<div class="alert alert-danger">Error loading details.</div>';
            console.error(error);
        });
}

function escapeHtml(text) {
    if (!text) return 'N/A';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
}

// Initialize
$(document).ready(function() {
    // Initialize DataTable for archive
    if ($('#archiveTable').length && $.fn.DataTable) {
        $('#archiveTable').DataTable({
            "pageLength": 25,
            "order": [[4, "desc"]],
            "language": {
                "search": "<i class='fas fa-search me-2'></i>",
                "searchPlaceholder": "Search archive...",
                "paginate": {
                    "previous": "<i class='fas fa-chevron-left'></i>",
                    "next": "<i class='fas fa-chevron-right'></i>"
                }
            }
        });
    }
    
    // Initialize drag and drop
    initDragAndDrop();
    
    // Save order button
    if (saveOrderBtn) {
        saveOrderBtn.addEventListener('click', saveOrders);
    }
    
    // Expand first group by default
    const firstGroup = document.querySelector('.article-group-header');
    if (firstGroup) {
        toggleArticleGroup(firstGroup);
    }
    
    // Set equipment type when opening add modal from empty state
    $('#addArticleModal').on('show.bs.modal', function(e) {
        const trigger = e.relatedTarget;
        if (trigger && trigger.getAttribute('data-type')) {
            document.getElementById('add_equipment_type').value = trigger.getAttribute('data-type');
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>