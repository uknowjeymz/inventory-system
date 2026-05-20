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

$page_title = "Request Cart - Consumable System";
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
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/UCC_Logo.ico">
    
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
            padding: 20px;
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
            font-size: 1.3rem;
            font-weight: 600;
            color: #1e3c72;
            margin: 0;
        }
        
        .page-header h2 i {
            color: #0d6efd;
            margin-right: 10px;
        }
        
        .cart-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-continue {
            padding: 10px 20px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            background: white;
            color: #495057;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .btn-continue:hover {
            border-color: #0d6efd;
            color: #0d6efd;
        }
        
        .btn-clear {
            padding: 10px 20px;
            border: none;
            border-radius: 12px;
            background: #dc3545;
            color: white;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .btn-clear:hover {
            background: #bb2d3b;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220,53,69,0.3);
        }
        
        /* Cart Items */
        .cart-container {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .cart-header {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 0.5fr;
            padding: 10px 15px;
            background: #f8f9fa;
            border-radius: 12px;
            font-weight: 600;
            color: #495057;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }
        
        .cart-item {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 0.5fr;
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }
        
        .cart-item.warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        
        .cart-item.danger {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        
        .item-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .item-icon {
            width: 45px;
            height: 45px;
            background: #e7f1ff;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0d6efd;
            font-size: 1.2rem;
        }
        
        .item-icon.warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .item-icon.danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .item-details h4 {
            font-size: 1rem;
            font-weight: 600;
            color: #1e3c72;
            margin-bottom: 3px;
        }
        
        .item-details p {
            font-size: 0.75rem;
            color: #6c757d;
            margin: 0;
        }
        
        .stock-info {
            font-size: 0.7rem;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .stock-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 50px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        
        .stock-badge.available {
            background: #d4edda;
            color: #155724;
        }
        
        .stock-badge.low {
            background: #fff3cd;
            color: #856404;
        }
        
        .stock-badge.critical {
            background: #f8d7da;
            color: #721c24;
        }
        
        .stock-warning {
            color: #dc3545;
            font-size: 0.7rem;
            font-weight: 600;
            margin-top: 3px;
        }
        
        .item-quantity {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .quantity-btn {
            width: 30px;
            height: 30px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            background: white;
            color: #495057;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .quantity-btn:hover:not(:disabled) {
            border-color: #0d6efd;
            color: #0d6efd;
        }
        
        .quantity-btn:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }
        
        .quantity-input {
            width: 50px;
            height: 30px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
        }
        
        .quantity-input:focus {
            outline: none;
            border-color: #0d6efd;
        }
        
        .quantity-input.warning {
            border-color: #ffc107;
            background-color: #fff3cd;
        }
        
        .quantity-input.danger {
            border-color: #dc3545;
            background-color: #f8d7da;
        }
        
        .item-remove {
            color: #dc3545;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .item-remove:hover {
            transform: scale(1.1);
        }
        
        /* Cart Summary */
        .cart-summary {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        .summary-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e3c72;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            color: #495057;
        }
        
        .summary-row.total {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1e3c72;
            border-top: 2px solid #e9ecef;
            padding-top: 15px;
            margin-top: 10px;
        }
        
        .stock-summary {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            margin: 15px 0;
        }
        
        .stock-summary-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .stock-summary-item:last-child {
            border-bottom: none;
        }
        
        .stock-summary-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }
        
        .dot-available { background: #28a745; }
        .dot-low { background: #ffc107; }
        .dot-critical { background: #dc3545; }
        
        .btn-checkout {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 15px;
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            color: white;
            font-weight: 600;
            font-size: 1rem;
            margin-top: 20px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .btn-checkout:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13,110,253,0.3);
        }
        
        .btn-checkout:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Empty Cart */
        .empty-cart {
            text-align: center;
            padding: 50px 20px;
        }
        
        .empty-cart i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 15px;
        }
        
        .empty-cart h3 {
            font-size: 1.2rem;
            color: #495057;
            margin-bottom: 10px;
        }
        
        .empty-cart p {
            color: #6c757d;
            margin-bottom: 20px;
        }
        
        .btn-shop {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-shop:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13,110,253,0.3);
        }
        
        /* Loading Spinner */
        .loading-spinner {
            text-align: center;
            padding: 50px;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 3px solid #e9ecef;
            border-top-color: #0d6efd;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
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
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .cart-header {
                display: none;
            }
            
            .cart-item {
                grid-template-columns: 1fr;
                gap: 10px;
                position: relative;
                padding: 15px;
                border: 1px solid #e9ecef;
                border-radius: 12px;
                margin-bottom: 10px;
            }
            
            .item-info {
                width: 100%;
            }
            
            .item-quantity {
                justify-content: center;
            }
            
            .item-remove {
                position: absolute;
                top: 10px;
                right: 10px;
            }
            
            .cart-actions {
                width: 100%;
            }
            
            .btn-continue, .btn-clear {
                flex: 1;
                text-align: center;
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
                <a href="my_requests.php" class="menu-item">
                    <i class="fas fa-clipboard-list"></i>
                    <span>My Requests</span>
                </a>
                <a href="cart.php" class="menu-item active">
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
            <h2>
                <i class="fas fa-shopping-cart"></i>
                Request Cart
            </h2>
            <div class="cart-actions">
                <a href="dashboard.php" class="btn-continue">
                    <i class="fas fa-arrow-left me-2"></i>Continue Exploring
                </a>
                <button class="btn-clear" onclick="clearCart()">
                    <i class="fas fa-trash-alt me-2"></i>Clear Cart
                </button>
            </div>
        </div>
        
        <!-- Cart Items Container -->
        <div id="cartContent">
            <!-- Will be populated by JavaScript -->
            <div class="loading-spinner">
                <div class="spinner"></div>
                <p>Loading your cart...</p>
            </div>
        </div>
    </div>
    
    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Cart functionality
        let cart = JSON.parse(localStorage.getItem('cart')) || [];
        let itemsData = []; // Will store item details from database
        
        // Update cart badge
        function updateCartBadge() {
            const totalItems = cart.reduce((sum, item) => sum + (item.quantity || 1), 0);
            const cartBadge = document.getElementById('cartBadge');
            if (cartBadge) {
                cartBadge.textContent = totalItems;
            }
        }
        
        // Calculate maximum allowed quantity based on thresholds
        function getMaxAllowedQuantity(itemData, currentCartQuantity = 0) {
            const unit = (itemData.unit || '').toLowerCase();
            const availableStock = itemData.available_stock;
            const requestedTotal = currentCartQuantity;
            const remainingStock = availableStock - requestedTotal;
            
            // Define thresholds based on unit
            let criticalThreshold, lowThreshold;
            
            switch(unit) {
                case 'pcs':
                    criticalThreshold = 30;
                    lowThreshold = 50;
                    break;
                case 'unit':
                    criticalThreshold = 10;
                    lowThreshold = 20;
                    break;
                case 'box':
                case 'ream':
                    criticalThreshold = 10;
                    lowThreshold = 20;
                    break;
                default:
                    criticalThreshold = 10;
                    lowThreshold = 20;
            }
            
            // Calculate maximum allowed to keep stock above thresholds
            let maxAllowed = availableStock;
            
            // If remaining stock is already below thresholds, we can only request up to available stock
            if (remainingStock <= criticalThreshold) {
                // Already critical, can only request up to available stock
                maxAllowed = availableStock - requestedTotal;
            } else if (remainingStock <= lowThreshold) {
                // Currently low, can request up to critical threshold
                maxAllowed = remainingStock - criticalThreshold;
            } else {
                // Currently available, can request down to low threshold
                maxAllowed = remainingStock - lowThreshold;
            }
            
            // Ensure we don't exceed available stock
            return Math.min(maxAllowed, availableStock - requestedTotal);
        }
        
        // Get stock status
        function getStockStatus(quantity, unit) {
            unit = (unit || '').toLowerCase();
            
            if (quantity <= 0) return { class: 'critical', text: 'Critical', icon: 'fa-times-circle' };
            
            switch(unit) {
                case 'pcs':
                    if (quantity <= 30) return { class: 'critical', text: 'Critical', icon: 'fa-times-circle' };
                    if (quantity <= 50) return { class: 'low', text: 'Low Stock', icon: 'fa-exclamation-triangle' };
                    return { class: 'available', text: 'Available', icon: 'fa-check-circle' };
                case 'unit':
                    if (quantity <= 10) return { class: 'critical', text: 'Critical', icon: 'fa-times-circle' };
                    if (quantity <= 20) return { class: 'low', text: 'Low Stock', icon: 'fa-exclamation-triangle' };
                    return { class: 'available', text: 'Available', icon: 'fa-check-circle' };
                case 'box':
                case 'ream':
                    if (quantity <= 10) return { class: 'critical', text: 'Critical', icon: 'fa-times-circle' };
                    if (quantity <= 20) return { class: 'low', text: 'Low Stock', icon: 'fa-exclamation-triangle' };
                    return { class: 'available', text: 'Available', icon: 'fa-check-circle' };
                default:
                    if (quantity <= 10) return { class: 'critical', text: 'Critical', icon: 'fa-times-circle' };
                    if (quantity <= 20) return { class: 'low', text: 'Low Stock', icon: 'fa-exclamation-triangle' };
                    return { class: 'available', text: 'Available', icon: 'fa-check-circle' };
            }
        }
        
        // Load cart items
        async function loadCartItems() {
            const cartContent = document.getElementById('cartContent');
            
            if (cart.length === 0) {
                cartContent.innerHTML = `
                    <div class="empty-cart">
                        <i class="fas fa-shopping-cart"></i>
                        <h3>Your cart is empty</h3>
                        <p>Start adding items from the dashboard</p>
                        <a href="dashboard.php" class="btn-shop">
                            <i class="fas fa-box me-2"></i>Browse Items
                        </a>
                    </div>
                `;
                return;
            }
            
            // Fetch item details from database
            try {
                const itemIds = cart.map(item => item.id).join(',');
                const response = await fetch(`get_cart_items.php?ids=${itemIds}`);
                itemsData = await response.json();
                
                renderCartItems();
            } catch (error) {
                console.error('Error loading cart items:', error);
                cartContent.innerHTML = `
                    <div class="empty-cart">
                        <i class="fas fa-exclamation-circle text-danger"></i>
                        <h3>Error loading cart</h3>
                        <p>Please try again later</p>
                        <button onclick="loadCartItems()" class="btn-shop">
                            <i class="fas fa-sync-alt me-2"></i>Retry
                        </button>
                    </div>
                `;
            }
        }
        
        // Render cart items
        function renderCartItems() {
            let itemsHtml = '';
            let totalQuantity = 0;
            let hasWarnings = false;
            let hasErrors = false;
            
            cart.forEach(cartItem => {
                const itemData = itemsData.find(d => d.id == cartItem.id);
                if (!itemData) return;
                
                totalQuantity += cartItem.quantity;
                
                const unit = (itemData.unit || '').toLowerCase();
                const availableStock = itemData.available_stock;
                const remainingAfterRequest = availableStock - cartItem.quantity;
                const stockStatus = getStockStatus(remainingAfterRequest, unit);
                const maxAllowed = getMaxAllowedQuantity(itemData, 0);
                
                // Determine item warning/error class
                let itemClass = '';
                let iconClass = '';
                let quantityInputClass = '';
                
                if (remainingAfterRequest <= 0) {
                    itemClass = 'danger';
                    iconClass = 'danger';
                    quantityInputClass = 'danger';
                    hasErrors = true;
                } else if (stockStatus.class === 'critical') {
                    itemClass = 'danger';
                    iconClass = 'danger';
                    quantityInputClass = 'danger';
                    hasErrors = true;
                } else if (stockStatus.class === 'low') {
                    itemClass = 'warning';
                    iconClass = 'warning';
                    quantityInputClass = 'warning';
                    hasWarnings = true;
                }
                
                itemsHtml += `
                    <div class="cart-item ${itemClass}" data-id="${cartItem.id}">
                        <div class="item-info">
                            <div class="item-icon ${iconClass}">
                                <i class="fas ${stockStatus.icon}"></i>
                            </div>
                            <div class="item-details">
                                <h4>${itemData.item_name}</h4>
                                <p>${itemData.category || 'Uncategorized'} • ${itemData.brand || 'Generic'}</p>
                                <div class="stock-info">
                                    <span class="stock-badge ${stockStatus.class}">
                                        <i class="fas ${stockStatus.icon} me-1"></i>
                                        ${stockStatus.text}
                                    </span>
                                    <small class="text-muted">
                                        Available: ${availableStock} ${itemData.unit || 'pcs'}
                                    </small>
                                </div>
                                ${remainingAfterRequest <= 0 ? 
                                    `<div class="stock-warning">
                                        <i class="fas fa-exclamation-circle me-1"></i>
                                        Cannot request - would exceed available stock
                                    </div>` : 
                                    stockStatus.class === 'critical' ?
                                    `<div class="stock-warning">
                                        <i class="fas fa-exclamation-circle me-1"></i>
                                        This request would make stock CRITICAL
                                    </div>` :
                                    stockStatus.class === 'low' ?
                                    `<div class="stock-warning" style="color: #856404;">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        This request would make stock LOW
                                    </div>` : ''}
                            </div>
                        </div>
                        <div class="item-quantity">
                            <button class="quantity-btn" onclick="updateQuantity(${cartItem.id}, -1)" 
                                    ${cartItem.quantity <= 1 ? 'disabled' : ''}>
                                <i class="fas fa-minus"></i>
                            </button>
                            <input type="number" class="quantity-input ${quantityInputClass}" value="${cartItem.quantity}" 
                                   min="1" max="${availableStock}" 
                                   onchange="updateQuantityInput(${cartItem.id}, this.value)">
                            <button class="quantity-btn" onclick="updateQuantity(${cartItem.id}, 1)" 
                                    ${cartItem.quantity >= availableStock || cartItem.quantity >= maxAllowed ? 'disabled' : ''}>
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <div class="item-remove" onclick="removeFromCart(${cartItem.id})">
                            <i class="fas fa-times-circle fa-lg"></i>
                        </div>
                    </div>
                `;
            });
            
            // Count items by status
            const criticalItems = cart.filter(item => {
                const data = itemsData.find(d => d.id == item.id);
                if (!data) return false;
                const remaining = data.available_stock - item.quantity;
                const status = getStockStatus(remaining, data.unit);
                return status.class === 'critical';
            }).length;
            
            const lowItems = cart.filter(item => {
                const data = itemsData.find(d => d.id == item.id);
                if (!data) return false;
                const remaining = data.available_stock - item.quantity;
                const status = getStockStatus(remaining, data.unit);
                return status.class === 'low';
            }).length;
            
            document.getElementById('cartContent').innerHTML = `
                <div class="cart-container">
                    <div class="cart-header">
                        <div>Item</div>
                        <div>Quantity</div>
                        <div></div>
                    </div>
                    ${itemsHtml}
                </div>
                
                <div class="cart-summary">
                    <h3 class="summary-title">Request Summary</h3>
                    
                    <div class="stock-summary">
                        <div class="stock-summary-item">
                            <span class="stock-summary-dot dot-available"></span>
                            <span>Items that will remain Available</span>
                            <span class="ms-auto fw-bold">${cart.length - criticalItems - lowItems}</span>
                        </div>
                        <div class="stock-summary-item">
                            <span class="stock-summary-dot dot-low"></span>
                            <span>Items that will become Low Stock</span>
                            <span class="ms-auto fw-bold">${lowItems}</span>
                        </div>
                        <div class="stock-summary-item">
                            <span class="stock-summary-dot dot-critical"></span>
                            <span>Items that will become Critical</span>
                            <span class="ms-auto fw-bold">${criticalItems}</span>
                        </div>
                    </div>
                    
                    <div class="summary-row">
                        <span>Total Items:</span>
                        <span><strong>${totalQuantity}</strong></span>
                    </div>
                    <div class="summary-row total">
                        <span>Number of Item Types:</span>
                        <span><strong>${cart.length}</strong></span>
                    </div>
                    
                    ${hasErrors ? 
                        `<div class="alert alert-danger mt-3 mb-0">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Some items will become CRITICAL. Please adjust quantities.
                        </div>` : 
                        hasWarnings ? 
                        `<div class="alert alert-warning mt-3 mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Some items will become LOW STOCK. Consider reducing quantities.
                        </div>` : 
                        `<div class="alert alert-success mt-3 mb-0">
                            <i class="fas fa-check-circle me-2"></i>
                            All items will remain in Available stock.
                        </div>`
                    }
                    
                    <button class="btn-checkout" onclick="checkout()" ${cart.length === 0 || hasErrors ? 'disabled' : ''}>
                        <i class="fas fa-paper-plane me-2"></i>
                        ${hasErrors ? 'Cannot Submit - Critical Items' : 'Submit Request'}
                    </button>
                </div>
            `;
        }
        
        // Update quantity
        function updateQuantity(itemId, change) {
            const cartItem = cart.find(item => item.id == itemId);
            const itemData = itemsData.find(d => d.id == itemId);
            
            if (cartItem && itemData) {
                const newQuantity = cartItem.quantity + change;
                const maxAllowed = getMaxAllowedQuantity(itemData, 0);
                
                if (newQuantity >= 1 && newQuantity <= itemData.available_stock && newQuantity <= maxAllowed) {
                    cartItem.quantity = newQuantity;
                    localStorage.setItem('cart', JSON.stringify(cart));
                    updateCartBadge();
                    renderCartItems();
                } else if (newQuantity > maxAllowed && maxAllowed > 0) {
                    showToast('warning', 'Quantity Limited', 
                        `Cannot request more than ${maxAllowed} to avoid Critical/Low stock.`);
                }
            }
        }
        
        // Update quantity from input
        function updateQuantityInput(itemId, value) {
            const cartItem = cart.find(item => item.id == itemId);
            const itemData = itemsData.find(d => d.id == itemId);
            const newQuantity = parseInt(value);
            const maxAllowed = getMaxAllowedQuantity(itemData, 0);
            
            if (cartItem && itemData) {
                if (newQuantity >= 1 && newQuantity <= itemData.available_stock && newQuantity <= maxAllowed) {
                    cartItem.quantity = newQuantity;
                    localStorage.setItem('cart', JSON.stringify(cart));
                    updateCartBadge();
                    renderCartItems();
                } else if (newQuantity > maxAllowed && maxAllowed > 0) {
                    showToast('warning', 'Quantity Limited', 
                        `Maximum allowed is ${maxAllowed} to avoid Critical/Low stock.`);
                    // Reset to current value
                    renderCartItems();
                }
            }
        }
        
        // Remove from cart
        function removeFromCart(itemId) {
            cart = cart.filter(item => item.id != itemId);
            localStorage.setItem('cart', JSON.stringify(cart));
            updateCartBadge();
            
            if (cart.length === 0) {
                loadCartItems();
            } else {
                renderCartItems();
            }
            
            showToast('info', 'Removed', 'Item removed from cart');
        }
        
        // Clear cart
        function clearCart() {
            if (confirm('Are you sure you want to clear your cart?')) {
                cart = [];
                localStorage.setItem('cart', JSON.stringify(cart));
                updateCartBadge();
                loadCartItems();
                showToast('info', 'Cart Cleared', 'All items have been removed');
            }
        }
        
        // Checkout
        function checkout() {
            if (cart.length === 0) {
                showToast('error', 'Empty Cart', 'Add items to your cart first');
                return;
            }
            
            // Check for critical items
            const hasCritical = cart.some(item => {
                const data = itemsData.find(d => d.id == item.id);
                if (!data) return false;
                const remaining = data.available_stock - item.quantity;
                const status = getStockStatus(remaining, data.unit);
                return status.class === 'critical';
            });
            
            if (hasCritical) {
                showToast('error', 'Cannot Submit', 
                    'Some items will become CRITICAL. Please adjust quantities.');
                return;
            }
            
            // Redirect to request submission page
            window.location.href = 'submit_request.php';
        }
        
        // Enhanced Toast notification
        function showToast(type, title, message) {
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
                    </div>
                    <button class="toast-close" onclick="removeToast('${toastId}')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            toastContainer.insertAdjacentHTML('beforeend', toastHTML);
            
            setTimeout(() => {
                removeToast(toastId);
            }, 3000);
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
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadCartItems();
            updateCartBadge();
        });
        
        // Touch-friendly interactions
        if ('ontouchstart' in window) {
            document.querySelectorAll('.quantity-btn, .btn-clear, .btn-checkout, .btn-continue').forEach(el => {
                el.addEventListener('touchstart', function() {
                    this.style.opacity = '0.8';
                });
                el.addEventListener('touchend', function() {
                    this.style.opacity = '1';
                });
            });
        }
    </script>
</body>
</html>