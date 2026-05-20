<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection
require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get user info
$user_id = $_SESSION['user_id'];
$user_query = $db->prepare("SELECT * FROM users WHERE id = ?");
$user_query->execute([$user_id]);
$user = $user_query->fetch(PDO::FETCH_ASSOC);

// Check for success message from session (from redirect after submission)
$success_message = $_SESSION['success_message'] ?? null;
$success_group_code = $_SESSION['success_group_code'] ?? null;
unset($_SESSION['success_message'], $_SESSION['success_group_code']);

// Get user's requests
$requests_query = "
    SELECT rg.*, 
           COUNT(ri.id) as item_count,
           SUM(ri.quantity) as total_quantity
    FROM request_groups rg
    LEFT JOIN request_items ri ON rg.id = ri.group_id
    WHERE rg.requested_by = ? OR rg.employee = ?
    GROUP BY rg.id
    ORDER BY rg.created_at DESC
";
$requests_stmt = $db->prepare($requests_query);
$requests_stmt->execute([$user['full_name'], $user['full_name']]);
$requests = $requests_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "My Requests - Consumable System";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title><?php echo $page_title; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background: #f8f9fa;
            min-height: 100vh;
        }
        
        /* Navbar Styles */
        .navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 10px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .navbar-brand img {
            width: 40px;
            height: auto;
            background: white;
            padding: 5px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .brand-text {
            font-weight: 700;
            color: #1e3c72;
        }
        
        .brand-text small {
            font-size: 0.7rem;
            color: #6c757d;
            display: block;
            font-weight: 400;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .user-details {
            display: none;
        }
        
        @media (min-width: 768px) {
            .user-details {
                display: block;
            }
        }
        
        .user-name {
            font-weight: 600;
            color: #1e3c72;
            font-size: 0.9rem;
        }
        
        .user-role {
            font-size: 0.7rem;
            color: #6c757d;
        }
        
        /* Offcanvas Menu */
        .offcanvas {
            border-right: none;
        }
        
        .offcanvas-header {
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            color: white;
        }
        
        .offcanvas-header img {
            width: 35px;
            height: auto;
            background: white;
            padding: 5px;
            border-radius: 8px;
            margin-right: 10px;
        }
        
        .offcanvas-body {
            padding: 0;
        }
        
        .menu-section {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .menu-section-title {
            font-size: 0.7rem;
            text-transform: uppercase;
            color: #6c757d;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }
        
        .menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            color: #495057;
            text-decoration: none;
            transition: all 0.3s ease;
            border-radius: 8px;
            margin-bottom: 2px;
        }
        
        .menu-item:hover {
            background: #f8f9fa;
            color: #0d6efd;
        }
        
        .menu-item i {
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }
        
        .menu-item.active {
            background: #e7f1ff;
            color: #0d6efd;
            font-weight: 500;
        }
        
        .menu-item.logout {
            color: #dc3545;
        }
        
        .menu-item.logout:hover {
            background: #fff5f5;
        }
        
        /* Main Content */
        .main-content {
            padding: 20px;
        }
        
        /* Page Header */
        .page-header {
            background: white;
            padding: 25px;
            border-radius: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .page-header h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e3c72;
            margin: 0;
        }
        
        .page-header h2 i {
            color: #0d6efd;
            margin-right: 10px;
        }
        
        .page-header p {
            color: #6c757d;
            margin: 5px 0 0 0;
        }
        
        .btn-new-request {
            padding: 12px 25px;
            border: none;
            border-radius: 15px;
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            color: white;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-new-request:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13,110,253,0.3);
            color: white;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .stat-icon.pending { background: #fff3cd; color: #856404; }
        .stat-icon.approved { background: #d4edda; color: #155724; }
        .stat-icon.rejected { background: #f8d7da; color: #721c24; }
        
        .stat-content h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e3c72;
            margin: 0;
            line-height: 1.2;
        }
        
        .stat-content p {
            font-size: 0.85rem;
            color: #6c757d;
            margin: 0;
        }
        
        /* Filter Section */
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 8px 20px;
            border: 2px solid #e9ecef;
            border-radius: 50px;
            background: white;
            color: #495057;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .filter-btn:hover {
            border-color: #0d6efd;
            color: #0d6efd;
        }
        
        .filter-btn.active {
            background: #0d6efd;
            border-color: #0d6efd;
            color: white;
        }
        
        /* Requests List */
        .requests-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .request-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .request-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border-color: #0d6efd;
        }
        
        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .request-code {
            font-size: 1.1rem;
            font-weight: 700;
            color: #0d6efd;
            font-family: monospace;
        }
        
        .request-status {
            padding: 6px 15px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-approved {
            background: #d4edda;
            color: #155724;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .request-body {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 15px;
        }
        
        @media (max-width: 768px) {
            .request-body {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        .request-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .request-info i {
            width: 30px;
            height: 30px;
            background: #f8f9fa;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0d6efd;
        }
        
        .request-info-content {
            font-size: 0.85rem;
        }
        
        .request-info-label {
            color: #6c757d;
            font-size: 0.7rem;
            text-transform: uppercase;
        }
        
        .request-info-value {
            font-weight: 600;
            color: #1e3c72;
        }
        
        .request-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 2px solid #e9ecef;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .view-items-btn {
            padding: 8px 20px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            background: white;
            color: #495057;
            font-size: 0.85rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
        }
        
        .view-items-btn:hover {
            border-color: #0d6efd;
            color: #0d6efd;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 20px;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 15px;
        }
        
        .empty-state h3 {
            font-size: 1.3rem;
            color: #495057;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #6c757d;
            margin-bottom: 20px;
        }
        
        /* Modal Styles */
        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        .modal-header {
            border-radius: 20px 20px 0 0;
            padding: 15px 20px;
        }
        
        .modal-header.bg-primary {
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
        }
        
        .modal-title {
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .items-table th {
            background: #f8f9fa;
            padding: 12px 10px;
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: 700;
            color: #495057;
            border-bottom: 2px solid #e9ecef;
        }
        
        .items-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }
        
        .items-table tr:last-child td {
            border-bottom: none;
        }
        
        .status-badge-sm {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-approved-sm {
            background: #d4edda;
            color: #155724;
        }
        
        .status-rejected-sm {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-pending-sm {
            background: #fff3cd;
            color: #856404;
        }
        
        .rejection-reason-box {
            background: #f8f9fa;
            border-left: 3px solid #dc3545;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.8rem;
            margin-top: 2px;
        }
        
        .rejection-reason-box i {
            color: #dc3545;
            margin-right: 5px;
        }
        
        /* Button group styling */
        .d-flex.gap-2 {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        /* Toast Container */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 350px;
            width: 100%;
        }
        
        .toast {
            background: white;
            border-radius: 15px;
            padding: 15px 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            margin-bottom: 10px;
            border-left: 4px solid;
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
            overflow: hidden;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease;
        }
        
        .toast.show {
            opacity: 1;
            transform: translateX(0);
        }
        
        .toast.hide {
            opacity: 0;
            transform: translateX(100%);
        }
        
        .toast::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            background: rgba(0,0,0,0.1);
            animation: progress 3s linear forwards;
        }
        
        @keyframes progress {
            from { width: 100%; }
            to { width: 0%; }
        }
        
        .toast.success {
            border-left-color: #28a745;
        }
        
        .toast.success .toast-icon {
            background: #d4edda;
            color: #28a745;
        }
        
        .toast.error {
            border-left-color: #dc3545;
        }
        
        .toast.error .toast-icon {
            background: #f8d7da;
            color: #dc3545;
        }
        
        .toast.warning {
            border-left-color: #ffc107;
        }
        
        .toast.warning .toast-icon {
            background: #fff3cd;
            color: #ffc107;
        }
        
        .toast.info {
            border-left-color: #17a2b8;
        }
        
        .toast.info .toast-icon {
            background: #d1ecf1;
            color: #17a2b8;
        }
        
        .toast-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .toast-content {
            flex: 1;
        }
        
        .toast-title {
            font-weight: 700;
            font-size: 0.9rem;
            color: #1e3c72;
            margin-bottom: 3px;
        }
        
        .toast-message {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .toast-close {
            position: absolute;
            top: 8px;
            right: 8px;
            background: none;
            border: none;
            color: #adb5bd;
            cursor: pointer;
            font-size: 0.8rem;
            padding: 3px;
            z-index: 10;
        }
        
        .toast-close:hover {
            color: #495057;
        }
        
        /* Highlight for new request */
        @keyframes highlight {
            0% { background-color: #fff3cd; }
            100% { background-color: white; }
        }
        
        .request-card.highlight {
            animation: highlight 2s ease;
        }
        
        @media (max-width: 480px) {
            .d-flex.gap-2 {
                width: 100%;
            }
            
            .view-items-btn {
                flex: 1;
                text-align: center;
                justify-content: center;
            }
            
            .items-table {
                font-size: 0.8rem;
            }
            
            .items-table th, .items-table td {
                padding: 8px 5px;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="container-fluid px-3">
            <div class="navbar-brand">
                <button class="btn btn-link text-dark p-0 me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#menu">
                    <i class="fas fa-bars fa-lg"></i>
                </button>
                <img src="assets/UCC_Logo.png" alt="UCC Logo">
                <div class="brand-text">
                    UCC Consumable
                    <small>Management System</small>
                </div>
            </div>
            
            <div class="user-info">
                <div class="user-details">
                    <div class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                    <div class="user-role"><?php echo ucfirst($user['role'] ?? 'User'); ?></div>
                </div>
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Offcanvas Menu -->
    <div class="offcanvas offcanvas-start" tabindex="-1" id="menu" aria-labelledby="menuLabel">
        <div class="offcanvas-header">
            <div class="d-flex align-items-center">
                <img src="assets/UCC_Logo.png" alt="UCC Logo">
                <div>
                    <h5 class="mb-0">UCC Consumable</h5>
                    <small>v1.0</small>
                </div>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <div class="menu-section">
                <div class="menu-section-title">Main Menu</div>
                <a href="dashboard.php" class="menu-item">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="my_requests.php" class="menu-item active">
                    <i class="fas fa-clipboard-list"></i>
                    <span>My Requests</span>
                </a>
                <a href="cart.php" class="menu-item">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Request Cart</span>
                    <span class="badge bg-primary ms-auto" id="cartBadge">0</span>
                </a>
            </div>
            
            <div class="menu-section">
                <div class="menu-section-title">Account</div>
                <a href="profile.php" class="menu-item">
                    <i class="fas fa-user"></i>
                    <span>My Profile</span>
                </a>
                <a href="settings.php" class="menu-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
                <a href="logout.php" class="menu-item logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
            
            <div class="menu-section">
                <div class="menu-section-title">System Info</div>
                <div class="text-muted small px-3">
                    <p class="mb-1"><i class="fas fa-calendar me-2"></i><?php echo date('F d, Y'); ?></p>
                    <p class="mb-0"><i class="fas fa-clock me-2"></i><?php echo date('h:i A'); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-clipboard-list"></i>
                    My Requests
                </h2>
                <p>Track and manage your consumable requests</p>
            </div>
            <a href="dashboard.php" class="btn-new-request">
                <i class="fas fa-plus-circle"></i>
                New Request
            </a>
        </div>
        
        <?php if (!empty($requests)): 
            // Calculate statistics
            $pending_count = 0;
            $approved_count = 0;
            $rejected_count = 0;
            
            foreach ($requests as $req) {
                if ($req['status'] == 'Pending') $pending_count++;
                elseif ($req['status'] == 'Approved') $approved_count++;
                elseif ($req['status'] == 'Rejected') $rejected_count++;
            }
        ?>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $pending_count; ?></h3>
                    <p>Pending</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon approved">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $approved_count; ?></h3>
                    <p>Approved</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon rejected">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $rejected_count; ?></h3>
                    <p>Rejected</p>
                </div>
            </div>
        </div>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <div class="filter-buttons">
                <button class="filter-btn active" data-filter="all">All Requests</button>
                <button class="filter-btn" data-filter="pending">Pending</button>
                <button class="filter-btn" data-filter="approved">Approved</button>
                <button class="filter-btn" data-filter="rejected">Rejected</button>
            </div>
        </div>
        
        <!-- Requests List -->
        <div class="requests-list" id="requestsList">
            <?php foreach ($requests as $request): 
                $status_class = '';
                $status_text = $request['status'];
                
                if ($request['status'] == 'Pending') {
                    $status_class = 'status-pending';
                } elseif ($request['status'] == 'Approved') {
                    $status_class = 'status-approved';
                } elseif ($request['status'] == 'Rejected') {
                    $status_class = 'status-rejected';
                }
            ?>
            <div class="request-card" data-status="<?php echo strtolower($request['status']); ?>" data-code="<?php echo $request['group_code']; ?>">
                <div class="request-header">
                    <span class="request-code"><?php echo htmlspecialchars($request['group_code']); ?></span>
                    <span class="request-status <?php echo $status_class; ?>">
                        <?php echo $request['status']; ?>
                    </span>
                </div>
                
                <div class="request-body">
                    <div class="request-info">
                        <i class="fas fa-calendar"></i>
                        <div class="request-info-content">
                            <div class="request-info-label">Date</div>
                            <div class="request-info-value"><?php echo date('M d, Y', strtotime($request['request_date'])); ?></div>
                        </div>
                    </div>
                    
                    <div class="request-info">
                        <i class="fas fa-boxes"></i>
                        <div class="request-info-content">
                            <div class="request-info-label">Items</div>
                            <div class="request-info-value"><?php echo $request['item_count']; ?> types</div>
                        </div>
                    </div>
                    
                    <div class="request-info">
                        <i class="fas fa-cubes"></i>
                        <div class="request-info-content">
                            <div class="request-info-label">Total Qty</div>
                            <div class="request-info-value"><?php echo $request['total_quantity']; ?> pcs</div>
                        </div>
                    </div>
                    
                    <div class="request-info">
                        <i class="fas fa-user"></i>
                        <div class="request-info-content">
                            <div class="request-info-label">Requested By</div>
                            <div class="request-info-value"><?php echo htmlspecialchars($request['employee']); ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="request-footer">
                    <div class="d-flex gap-2">
                        <button class="view-items-btn" onclick="viewRequestItems(<?php echo $request['id']; ?>, '<?php echo htmlspecialchars($request['group_code']); ?>')">
                            <i class="fas fa-eye"></i>
                            View Items
                        </button>
                        
                        <?php if ($request['status'] == 'Approved'): ?>
                        <a href="generate_release_report.php?group_id=<?php echo $request['id']; ?>" 
                           target="_blank" 
                           class="view-items-btn" 
                           style="border-color: #28a745; color: #28a745;">
                            <i class="fas fa-file-pdf"></i>
                            Generate Report
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($request['approved_by']) && $request['status'] == 'Approved'): ?>
                    <small class="text-muted">
                        <i class="fas fa-check-circle text-success me-1"></i>
                        Approved by: <?php echo htmlspecialchars($request['approved_by']); ?>
                    </small>
                    <?php elseif (!empty($request['approved_by']) && $request['status'] == 'Rejected'): ?>
                    <small class="text-muted">
                        <i class="fas fa-times-circle text-danger me-1"></i>
                        Rejected by: <?php echo htmlspecialchars($request['approved_by']); ?>
                    </small>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php else: ?>
        <!-- Empty State -->
        <div class="empty-state">
            <i class="fas fa-clipboard-list"></i>
            <h3>No requests yet</h3>
            <p>Start by creating your first consumable request</p>
            <a href="dashboard.php" class="btn-new-request" style="display: inline-flex;">
                <i class="fas fa-plus-circle"></i>
                Browse Items
            </a>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>
    
    <!-- View Items Modal -->
    <div class="modal fade" id="viewItemsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-clipboard-list me-2"></i>
                        Request Items: <span id="modal-request-code"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- Request Summary -->
                    <div class="alert alert-info border-0 mb-3" id="modal-request-summary">
                        <!-- Will be populated by JavaScript -->
                    </div>
                    
                    <!-- Items Table -->
                    <div class="table-responsive">
                        <table class="table items-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th width="100">Quantity</th>
                                    <th width="120">Status</th>
                                    <th>Purpose</th>
                                </tr>
                            </thead>
                            <tbody id="modal-items-body">
                                <tr>
                                    <td colspan="4" class="text-center py-4">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Cart functionality
        let cart = JSON.parse(localStorage.getItem('cart')) || [];
        updateCartBadge();
        
        function updateCartBadge() {
            const totalItems = cart.reduce((sum, item) => sum + (item.quantity || 1), 0);
            const cartBadge = document.getElementById('cartBadge');
            if (cartBadge) {
                cartBadge.textContent = totalItems;
            }
        }
        
        // Toast notification function
        function showToast(type, title, message, groupCode = null) {
            const toastContainer = document.getElementById('toastContainer');
            const toastId = 'toast_' + Date.now();
            
            let icon = '';
            switch(type) {
                case 'success': icon = 'fa-check-circle'; break;
                case 'error': icon = 'fa-exclamation-circle'; break;
                case 'warning': icon = 'fa-exclamation-triangle'; break;
                case 'info': icon = 'fa-info-circle'; break;
                default: icon = 'fa-bell';
            }
            
            const toastHTML = `
                <div id="${toastId}" class="toast ${type} show">
                    <div class="toast-icon">
                        <i class="fas ${icon}"></i>
                    </div>
                    <div class="toast-content">
                        <div class="toast-title">${title}</div>
                        <div class="toast-message">${message}</div>
                        ${groupCode ? `<small class="text-primary fw-bold mt-1 d-block">Reference: ${groupCode}</small>` : ''}
                    </div>
                    <button class="toast-close" onclick="removeToast('${toastId}')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            toastContainer.insertAdjacentHTML('beforeend', toastHTML);
            
            setTimeout(() => {
                removeToast(toastId);
            }, 5000); // Show for 5 seconds
        }
        
        function removeToast(toastId) {
            const toast = document.getElementById(toastId);
            if (toast) {
                toast.classList.remove('show');
                toast.classList.add('hide');
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.remove();
                    }
                }, 300);
            }
        }
        
        // Filter functionality
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                // Update active state
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const filter = this.dataset.filter;
                const requests = document.querySelectorAll('.request-card');
                
                requests.forEach(request => {
                    if (filter === 'all') {
                        request.style.display = 'block';
                    } else {
                        const status = request.dataset.status;
                        request.style.display = status === filter ? 'block' : 'none';
                    }
                });
            });
        });
        
        // View items function
        function viewRequestItems(requestId, groupCode) {
            // Set modal title
            document.getElementById('modal-request-code').textContent = groupCode;
            
            // Show loading state
            document.getElementById('modal-items-body').innerHTML = `
                <tr>
                    <td colspan="4" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </td>
                </tr>
            `;
            
            // Fetch items via AJAX
            fetch(`get_request_items_details.php?group_id=${requestId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update summary
                        document.getElementById('modal-request-summary').innerHTML = `
                            <div class="d-flex align-items-center">
                                <i class="fas fa-info-circle fa-2x me-3"></i>
                                <div>
                                    <strong>${escapeHtml(data.employee)}</strong><br>
                                    <small>${escapeHtml(data.office)} | Request Date: ${escapeHtml(data.request_date)}</small>
                                </div>
                            </div>
                        `;
                        
                        // Build items table
                        let html = '';
                        data.items.forEach(item => {
                            let statusClass = '';
                            let statusText = item.status;
                            
                            if (item.status === 'Approved') {
                                statusClass = 'status-approved-sm';
                            } else if (item.status === 'Rejected') {
                                statusClass = 'status-rejected-sm';
                            } else {
                                statusClass = 'status-pending-sm';
                            }
                            
                            html += `
                                <tr>
                                    <td>
                                        <strong>${escapeHtml(item.item_name)}</strong>
                                        <br>
                                        <small class="text-muted">${escapeHtml(item.brand || 'Generic')} | ${escapeHtml(item.category || 'Uncategorized')}</small>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold">${item.quantity}</span>
                                        <small class="text-muted d-block">${escapeHtml(item.unit || 'pcs')}</small>
                                    </td>
                                    <td>
                                        <span class="status-badge-sm ${statusClass}">${item.status}</span>
                                        ${item.status === 'Rejected' && item.rejection_reason ? `
                                            <div class="rejection-reason-box">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                <small><strong>Reason:</strong> ${escapeHtml(item.rejection_reason)}</small>
                                            </div>
                                        ` : ''}
                                    </td>
                                    <td>
                                        <small>${escapeHtml(item.description || '—')}</small>
                                    </td>
                                </tr>
                            `;
                        });
                        
                        document.getElementById('modal-items-body').innerHTML = html;
                    } else {
                        document.getElementById('modal-items-body').innerHTML = `
                            <tr>
                                <td colspan="4" class="text-center py-4 text-danger">
                                    <i class="fas fa-exclamation-circle fa-2x mb-2"></i>
                                    <p>Failed to load items. Please try again.</p>
                                </td>
                            </tr>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('modal-items-body').innerHTML = `
                        <tr>
                            <td colspan="4" class="text-center py-4 text-danger">
                                <i class="fas fa-exclamation-circle fa-2x mb-2"></i>
                                <p>Error loading items. Please try again.</p>
                            </td>
                        </tr>
                    `;
                });
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('viewItemsModal'));
            modal.show();
        }
        
        // Helper function to escape HTML
        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
        }
        
        // Touch-friendly interactions
        if ('ontouchstart' in window) {
            document.querySelectorAll('.filter-btn, .view-items-btn, .btn-new-request').forEach(el => {
                el.addEventListener('touchstart', function() {
                    this.style.opacity = '0.8';
                });
                el.addEventListener('touchend', function() {
                    this.style.opacity = '1';
                });
            });
        }
        
        // Check for success message from session and show toast
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($success_message && $success_group_code): ?>
            // Show toast notification for successful submission
            showToast('success', 'Request Submitted Successfully!', 
                '<?php echo addslashes($success_message); ?>', 
                '<?php echo addslashes($success_group_code); ?>');
            
            // Highlight the new request
            setTimeout(function() {
                const newRequest = document.querySelector(`.request-card[data-code="<?php echo addslashes($success_group_code); ?>"]`);
                if (newRequest) {
                    newRequest.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    newRequest.classList.add('highlight');
                    setTimeout(() => {
                        newRequest.classList.remove('highlight');
                    }, 2000);
                }
            }, 500);
            <?php endif; ?>
        });
    </script>
</body>
</html>