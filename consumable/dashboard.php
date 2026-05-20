<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection
require_once '../consumable/config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get user info
$user_id = $_SESSION['user_id'];
$user_query = $db->prepare("SELECT * FROM users WHERE id = ?");
$user_query->execute([$user_id]);
$user = $user_query->fetch(PDO::FETCH_ASSOC);

// Get all consumable items
$items_query = "SELECT * FROM consumables ORDER BY 
                CASE 
                    WHEN quantity <= 0 THEN 1
                    WHEN quantity <= 10 THEN 2
                    ELSE 3
                END, item_name ASC";
$items_stmt = $db->prepare($items_query);
$items_stmt->execute();
$items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics with proper thresholds
$total_items = count($items);
$low_stock = 0;
$critical_stock = 0;
$out_of_stock = 0;

foreach ($items as $item) {
    $unit = strtolower(trim($item['unit'] ?? ''));
    $qty = $item['quantity'];
    
    if ($qty <= 0) {
        $out_of_stock++;
    } else {
        switch ($unit) {
            case 'pcs':
                if ($qty <= 30) {
                    $critical_stock++;
                } elseif ($qty <= 50) {
                    $low_stock++;
                }
                break;
            case 'unit':
                if ($qty <= 10) {
                    $critical_stock++;
                } elseif ($qty <= 20) {
                    $low_stock++;
                }
                break;
            case 'box':
            case 'ream':
                if ($qty <= 10) {
                    $critical_stock++;
                } elseif ($qty <= 20) {
                    $low_stock++;
                }
                break;
            default:
                if ($qty <= 10) {
                    $critical_stock++;
                } elseif ($qty <= 20) {
                    $low_stock++;
                }
        }
    }
}

$page_title = "Dashboard - Consumable System";
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
            position: relative;
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
        
        /* Offcanvas Menu Styles */
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
        
        /* Main Content Styles */
        .main-content {
            padding: 20px;
            padding-bottom: 80px; /* Add padding for floating cart button */
        }
        
        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
            color: white;
            padding: 25px;
            border-radius: 20px;
            margin-bottom: 25px;
            position: relative;
            overflow: hidden;
        }
        
        .welcome-banner::before {
            content: '';
            position: absolute;
            top: -20px;
            right: -20px;
            width: 150px;
            height: 150px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        
        .welcome-banner h2 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .welcome-banner p {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 0;
        }
        
        .date-badge {
            background: rgba(255,255,255,0.2);
            padding: 8px 15px;
            border-radius: 50px;
            font-size: 0.8rem;
            display: inline-block;
            margin-top: 10px;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        
        @media (min-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        
        .stat-card {
            background: white;
            padding: 20px 15px;
            border-radius: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            width: 45px;
            height: 45px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
            font-size: 1.3rem;
        }
        
        .stat-icon.total { background: #e7f1ff; color: #0d6efd; }
        .stat-icon.low { background: #fff3cd; color: #856404; }
        .stat-icon.critical { background: #f8d7da; color: #721c24; }
        .stat-icon.out { background: #e2e3e5; color: #383d41; }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e3c72;
            line-height: 1.2;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Search and Filter */
        .search-section {
            background: white;
            padding: 20px;
            border-radius: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        .search-box {
            position: relative;
            margin-bottom: 15px;
        }
        
        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #adb5bd;
        }
        
        .search-box input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e9ecef;
            border-radius: 15px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13,110,253,0.1);
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 8px 15px;
            border: 2px solid #e9ecef;
            border-radius: 50px;
            background: white;
            color: #495057;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
            flex: 1;
            min-width: 70px;
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
        
        /* Items Grid */
        .items-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        @media (min-width: 640px) {
            .items-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (min-width: 1024px) {
            .items-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        .item-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .item-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .item-card.critical {
            border-left: 4px solid #dc3545;
            opacity: 0.9;
        }
        
        .item-card.low {
            border-left: 4px solid #ffc107;
        }
        
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .item-category {
            background: #f8f9fa;
            padding: 5px 10px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
            color: #495057;
        }
        
        .item-status {
            padding: 5px 10px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .status-critical {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-low {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-available {
            background: #d4edda;
            color: #155724;
        }
        
        .status-out {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .item-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e3c72;
            margin-bottom: 5px;
        }
        
        .item-brand {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 10px;
        }
        
        .item-details {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            padding: 10px 0;
            border-top: 1px solid #e9ecef;
            border-bottom: 1px solid #e9ecef;
        }
        
        .detail-item {
            flex: 1;
            text-align: center;
        }
        
        .detail-label {
            font-size: 0.65rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .detail-value {
            font-size: 1rem;
            font-weight: 600;
            color: #1e3c72;
        }
        
        .item-footer {
            display: flex;
            gap: 10px;
        }
        
        .btn-request {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            color: white;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .btn-request:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13,110,253,0.3);
        }
        
        .btn-request:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: #6c757d;
        }
        
        .btn-request.critical-disabled {
            background: #dc3545;
            opacity: 0.7;
        }
        
        .btn-details {
            width: 45px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            background: white;
            color: #6c757d;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .btn-details:hover {
            border-color: #0d6efd;
            color: #0d6efd;
        }
        
        /* ===== FLOATING CART BUTTON ===== */
        .floating-cart {
            position: fixed;
            bottom: 25px;
            right: 25px;
            width: 65px;
            height: 65px;
            border-radius: 50%;
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4);
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 999;
            text-decoration: none;
            border: 3px solid white;
        }
        
        .floating-cart:hover {
            transform: scale(1.1);
            box-shadow: 0 12px 30px rgba(40, 167, 69, 0.6);
        }
        
        .cart-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 700;
            border: 2px solid white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            background: white;
            border-radius: 20px;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #dee2e6;
            margin-bottom: 15px;
        }
        
        .empty-state h3 {
            font-size: 1.1rem;
            color: #495057;
            margin-bottom: 5px;
        }
        
        .empty-state p {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        /* Loading Skeleton */
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }
        
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        
        /* ===== TOAST NOTIFICATION ===== */
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
        
        /* Critical Stock Alert Tooltip */
        [data-tooltip] {
            position: relative;
            cursor: help;
        }
        
        [data-tooltip]:before {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            margin-bottom: 8px;
            background: #dc3545;
            color: white;
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 0.7rem;
            white-space: nowrap;
            z-index: 10;
            box-shadow: 0 2px 10px rgba(220,53,69,0.3);
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        [data-tooltip]:hover:before {
            opacity: 1;
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
                <a href="dashboard.php" class="menu-item active">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="my_requests.php" class="menu-item">
                    <i class="fas fa-clipboard-list"></i>
                    <span>My Requests</span>
                </a>
                <a href="cart.php" class="menu-item">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Request Cart</span>
                    <span class="badge bg-primary ms-auto" id="menuCartBadge">0</span>
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
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <h2>Welcome back, <?php echo explode(' ', $user['full_name'])[0]; ?>! 👋</h2>
            <p>Browse available consumable items and submit your requests</p>
            <div class="date-badge">
                <i class="far fa-calendar-alt me-2"></i><?php echo date('l, F d, Y'); ?>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon total">
                    <i class="fas fa-box"></i>
                </div>
                <div class="stat-value"><?php echo $total_items; ?></div>
                <div class="stat-label">Total Items</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon low">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-value"><?php echo $low_stock; ?></div>
                <div class="stat-label">Low Stock</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon critical">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-value"><?php echo $critical_stock; ?></div>
                <div class="stat-label">Critical</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon out">
                    <i class="fas fa-ban"></i>
                </div>
                <div class="stat-value"><?php echo $out_of_stock; ?></div>
                <div class="stat-label">Out of Stock</div>
            </div>
        </div>
        
        <!-- Search and Filter -->
        <div class="search-section">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search items by name, brand, or category...">
            </div>
            
            <div class="filter-buttons">
                <button class="filter-btn active" data-filter="all">All</button>
                <button class="filter-btn" data-filter="available">Available</button>
                <button class="filter-btn" data-filter="low">Low Stock</button>
                <button class="filter-btn" data-filter="critical">Critical</button>
                <button class="filter-btn" data-filter="out">Out of Stock</button>
            </div>
        </div>
        
        <!-- Items Grid -->
        <div class="items-grid" id="itemsGrid">
            <?php if (empty($items)): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h3>No items found</h3>
                    <p>There are no consumable items available at the moment.</p>
                </div>
            <?php else: ?>
                <?php foreach ($items as $item): 
                    $unit = strtolower(trim($item['unit'] ?? ''));
                    $qty = $item['quantity'];
                    
                    // Determine if item is Critical
                    $is_critical = false;
                    if ($qty <= 0) {
                        $is_critical = true;
                    } else {
                        switch ($unit) {
                            case 'pcs':
                                if ($qty <= 30) $is_critical = true;
                                break;
                            case 'unit':
                                if ($qty <= 10) $is_critical = true;
                                break;
                            case 'box':
                            case 'ream':
                                if ($qty <= 10) $is_critical = true;
                                break;
                            default:
                                if ($qty <= 10) $is_critical = true;
                        }
                    }
                    
                    // Determine status based on thresholds
                    if ($qty <= 0) {
                        $status_class = 'status-out';
                        $status_text = 'Out of Stock';
                        $card_class = '';
                        $status_icon = 'fa-ban';
                    } else {
                        switch ($unit) {
                            case 'pcs':
                                if ($qty <= 30) {
                                    $status_class = 'status-critical';
                                    $status_text = 'Critical';
                                    $card_class = 'critical';
                                    $status_icon = 'fa-times-circle';
                                } elseif ($qty <= 50) {
                                    $status_class = 'status-low';
                                    $status_text = 'Low Stock';
                                    $card_class = 'low';
                                    $status_icon = 'fa-exclamation-triangle';
                                } else {
                                    $status_class = 'status-available';
                                    $status_text = 'Available';
                                    $card_class = '';
                                    $status_icon = 'fa-check-circle';
                                }
                                break;
                            case 'unit':
                                if ($qty <= 10) {
                                    $status_class = 'status-critical';
                                    $status_text = 'Critical';
                                    $card_class = 'critical';
                                    $status_icon = 'fa-times-circle';
                                } elseif ($qty <= 20) {
                                    $status_class = 'status-low';
                                    $status_text = 'Low Stock';
                                    $card_class = 'low';
                                    $status_icon = 'fa-exclamation-triangle';
                                } else {
                                    $status_class = 'status-available';
                                    $status_text = 'Available';
                                    $card_class = '';
                                    $status_icon = 'fa-check-circle';
                                }
                                break;
                            case 'box':
                            case 'ream':
                                if ($qty <= 10) {
                                    $status_class = 'status-critical';
                                    $status_text = 'Critical';
                                    $card_class = 'critical';
                                    $status_icon = 'fa-times-circle';
                                } elseif ($qty <= 20) {
                                    $status_class = 'status-low';
                                    $status_text = 'Low Stock';
                                    $card_class = 'low';
                                    $status_icon = 'fa-exclamation-triangle';
                                } else {
                                    $status_class = 'status-available';
                                    $status_text = 'Available';
                                    $card_class = '';
                                    $status_icon = 'fa-check-circle';
                                }
                                break;
                            default:
                                if ($qty <= 10) {
                                    $status_class = 'status-critical';
                                    $status_text = 'Critical';
                                    $card_class = 'critical';
                                    $status_icon = 'fa-times-circle';
                                } elseif ($qty <= 20) {
                                    $status_class = 'status-low';
                                    $status_text = 'Low Stock';
                                    $card_class = 'low';
                                    $status_icon = 'fa-exclamation-triangle';
                                } else {
                                    $status_class = 'status-available';
                                    $status_text = 'Available';
                                    $card_class = '';
                                    $status_icon = 'fa-check-circle';
                                }
                        }
                    }
                ?>
                <div class="item-card <?php echo $card_class; ?>" 
                     data-name="<?php echo strtolower($item['item_name']); ?>"
                     data-brand="<?php echo strtolower($item['brand'] ?? ''); ?>"
                     data-category="<?php echo strtolower($item['category'] ?? ''); ?>"
                     data-status="<?php echo $status_text; ?>"
                     data-quantity="<?php echo $item['quantity']; ?>"
                     data-critical="<?php echo $is_critical ? '1' : '0'; ?>">
                    
                    <div class="item-header">
                        <span class="item-category">
                            <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($item['category'] ?? 'Uncategorized'); ?>
                        </span>
                        <span class="item-status <?php echo $status_class; ?>">
                            <i class="fas <?php echo $status_icon; ?> me-1"></i>
                            <?php echo $status_text; ?>
                        </span>
                    </div>
                    
                    <h3 class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></h3>
                    <div class="item-brand">
                        <i class="fas fa-building me-1"></i><?php echo htmlspecialchars($item['brand'] ?? 'Generic'); ?>
                    </div>
                    
                    <div class="item-details">
                        <div class="detail-item">
                            <div class="detail-label">Stock</div>
                            <div class="detail-value <?php echo $status_class; ?>">
                                <?php echo $item['quantity']; ?> <?php echo htmlspecialchars($item['unit'] ?? 'pcs'); ?>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">ID</div>
                            <div class="detail-value">
                                #<?php echo $item['id']; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="item-footer">
                        <?php if ($is_critical): ?>
                            <button class="btn-request critical-disabled" 
                                    disabled
                                    data-tooltip="This item is CRITICAL and cannot be requested"
                                    style="position: relative;">
                                <i class="fas fa-ban me-2"></i>Cannot Request
                            </button>
                        <?php else: ?>
                            <button class="btn-request" 
                                    onclick="addToCart(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['item_name']); ?>', <?php echo $item['quantity']; ?>)">
                                <i class="fas fa-cart-plus me-2"></i>Request
                            </button>
                        <?php endif; ?>
                        <button class="btn-details" onclick="viewDetails(<?php echo $item['id']; ?>)">
                            <i class="fas fa-info"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Floating Cart Button -->
    <a href="cart.php" class="floating-cart" id="floatingCart">
        <i class="fas fa-shopping-cart"></i>
        <span class="cart-badge" id="cartBadge">0</span>
    </a>
    
    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Cart functionality
        let cart = JSON.parse(localStorage.getItem('cart')) || [];
        updateAllCartBadges();

        function updateAllCartBadges() {
            const totalItems = cart.reduce((sum, item) => sum + (item.quantity || 1), 0);
            
            // Update floating cart badge
            const cartBadge = document.getElementById('cartBadge');
            if (cartBadge) {
                cartBadge.textContent = totalItems;
                
                // Hide badge if zero
                if (totalItems === 0) {
                    cartBadge.style.display = 'none';
                } else {
                    cartBadge.style.display = 'flex';
                }
            }
            
            // Update menu cart badge
            const menuCartBadge = document.getElementById('menuCartBadge');
            if (menuCartBadge) {
                menuCartBadge.textContent = totalItems;
            }
        }

        function addToCart(itemId, itemName, availableStock) {
            // Check if item already in cart
            const existingItem = cart.find(item => item.id === itemId);
            
            // Calculate current quantity in cart
            const currentQuantity = existingItem ? existingItem.quantity : 0;
            
            // Check if adding one more would exceed available stock
            if (currentQuantity + 1 > availableStock) {
                showToast('error', 'Cannot Add', `Only ${availableStock} item(s) available in stock.`);
                return;
            }
            
            if (existingItem) {
                // Increase quantity if already in cart
                existingItem.quantity = (existingItem.quantity || 1) + 1;
                showToast('info', 'Quantity Updated', `${itemName} quantity increased to ${existingItem.quantity}`);
            } else {
                // Add new item to cart with quantity 1
                cart.push({
                    id: itemId,
                    name: itemName,
                    quantity: 1,
                    added_at: new Date().toISOString()
                });
                showToast('success', 'Added to Cart', `${itemName} has been added to your request cart.`);
            }
            
            // Save to localStorage
            localStorage.setItem('cart', JSON.stringify(cart));
            updateAllCartBadges();
        }

        // Remove from cart
        function removeFromCart(itemId) {
            cart = cart.filter(item => item.id !== itemId);
            localStorage.setItem('cart', JSON.stringify(cart));
            updateAllCartBadges();
            if (window.location.pathname.includes('cart.php')) {
                loadCartItems(); // Reload cart page if on cart page
            }
        }

        // Update quantity
        function updateCartItemQuantity(itemId, newQuantity) {
            const item = cart.find(item => item.id === itemId);
            if (item) {
                if (newQuantity <= 0) {
                    removeFromCart(itemId);
                } else {
                    item.quantity = newQuantity;
                    localStorage.setItem('cart', JSON.stringify(cart));
                    updateAllCartBadges();
                }
            }
        }

        // Clear entire cart
        function clearCart() {
            if (confirm('Are you sure you want to clear your cart?')) {
                cart = [];
                localStorage.setItem('cart', JSON.stringify(cart));
                updateAllCartBadges();
                if (window.location.pathname.includes('cart.php')) {
                    loadCartItems();
                }
                showToast('info', 'Cart Cleared', 'All items have been removed from your cart.');
            }
        }
        
        // Enhanced Toast notification - FIXED VERSION
        function showToast(type, title, message) {
            const toastContainer = document.getElementById('toastContainer');
            
            // Create unique ID for this toast
            const toastId = 'toast_' + Date.now();
            
            // Set icon based on type
            let icon = '';
            switch(type) {
                case 'success':
                    icon = 'fa-check-circle';
                    break;
                case 'error':
                    icon = 'fa-exclamation-circle';
                    break;
                case 'warning':
                    icon = 'fa-exclamation-triangle';
                    break;
                case 'info':
                    icon = 'fa-info-circle';
                    break;
                default:
                    icon = 'fa-bell';
            }
            
            // Create toast HTML
            const toastHTML = `
                <div id="${toastId}" class="toast ${type} show">
                    <div class="toast-icon">
                        <i class="fas ${icon}"></i>
                    </div>
                    <div class="toast-content">
                        <div class="toast-title">${title}</div>
                        <div class="toast-message">${message}</div>
                    </div>
                    <button class="toast-close" onclick="removeToast('${toastId}')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            // Append to container
            toastContainer.insertAdjacentHTML('beforeend', toastHTML);
            
            // Auto remove after 3 seconds
            setTimeout(() => {
                removeToast(toastId);
            }, 3000);
        }
        
        // Function to remove toast
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
        
        // Search and Filter functionality
        document.getElementById('searchInput').addEventListener('input', filterItems);
        
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                filterItems();
            });
        });
        
        function filterItems() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const activeFilter = document.querySelector('.filter-btn.active').dataset.filter;
            
            document.querySelectorAll('.item-card').forEach(card => {
                const name = card.dataset.name;
                const brand = card.dataset.brand;
                const category = card.dataset.category;
                const status = card.dataset.status.toLowerCase();
                
                // Check search match
                const searchMatch = searchTerm === '' || 
                    name.includes(searchTerm) || 
                    brand.includes(searchTerm) || 
                    category.includes(searchTerm);
                
                // Check filter match
                let filterMatch = true;
                if (activeFilter === 'available') {
                    filterMatch = status === 'available';
                } else if (activeFilter === 'low') {
                    filterMatch = status === 'low stock';
                } else if (activeFilter === 'critical') {
                    filterMatch = status === 'critical';
                } else if (activeFilter === 'out') {
                    filterMatch = status === 'out of stock';
                }
                
                card.style.display = searchMatch && filterMatch ? 'block' : 'none';
            });
        }
        
        // View details
        function viewDetails(itemId) {
            window.location.href = `item_details.php?id=${itemId}`;
        }
        
        // Touch-friendly interactions
        if ('ontouchstart' in window) {
            document.querySelectorAll('.btn-request, .btn-details, .filter-btn, .menu-item').forEach(el => {
                el.addEventListener('touchstart', function() {
                    this.style.opacity = '0.8';
                });
                el.addEventListener('touchend', function() {
                    this.style.opacity = '1';
                });
            });
        }
        
        // Test toast function (you can remove this after testing)
        function testToast() {
            showToast('success', 'Test Success', 'This is a test success message');
            setTimeout(() => showToast('info', 'Test Info', 'This is a test info message'), 1000);
            setTimeout(() => showToast('warning', 'Test Warning', 'This is a test warning message'), 2000);
            setTimeout(() => showToast('error', 'Test Error', 'This is a test error message'), 3000);
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Uncomment the line below to test toasts
            // setTimeout(testToast, 1000);
        });
    </script>
</body>
</html>