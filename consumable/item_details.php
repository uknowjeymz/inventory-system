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

// Get item ID from URL
$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($item_id === 0) {
    header("Location: dashboard.php");
    exit();
}

// Get user info
$user_id = $_SESSION['user_id'];
$user_query = $db->prepare("SELECT * FROM users WHERE id = ?");
$user_query->execute([$user_id]);
$user = $user_query->fetch(PDO::FETCH_ASSOC);

// Get item details
$item_query = $db->prepare("SELECT * FROM consumables WHERE id = ?");
$item_query->execute([$item_id]);
$item = $item_query->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    header("Location: dashboard.php");
    exit();
}

// ============================================
// DETERMINE STATUS BASED ON THRESHOLDS
// ============================================
$unit = strtolower(trim($item['unit'] ?? ''));
$qty = $item['quantity'];

// Initialize variables with default values
$status_class = 'status-available';
$status_text = 'Available';
$status_icon = 'fa-check-circle';

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

// Determine status based on thresholds (same as admin)
if ($qty <= 0) {
    $status_class = 'status-out';
    $status_text = 'Out of Stock';
    $status_icon = 'fa-ban';
} else {
    switch ($unit) {
        case 'pcs':
            if ($qty <= 30) {
                $status_class = 'status-critical';
                $status_text = 'Critical';
                $status_icon = 'fa-times-circle';
            } elseif ($qty <= 50) {
                $status_class = 'status-low';
                $status_text = 'Low Stock';
                $status_icon = 'fa-exclamation-triangle';
            } else {
                $status_class = 'status-available';
                $status_text = 'Available';
                $status_icon = 'fa-check-circle';
            }
            break;
        case 'unit':
            if ($qty <= 10) {
                $status_class = 'status-critical';
                $status_text = 'Critical';
                $status_icon = 'fa-times-circle';
            } elseif ($qty <= 20) {
                $status_class = 'status-low';
                $status_text = 'Low Stock';
                $status_icon = 'fa-exclamation-triangle';
            } else {
                $status_class = 'status-available';
                $status_text = 'Available';
                $status_icon = 'fa-check-circle';
            }
            break;
        case 'box':
        case 'ream':
            if ($qty <= 10) {
                $status_class = 'status-critical';
                $status_text = 'Critical';
                $status_icon = 'fa-times-circle';
            } elseif ($qty <= 20) {
                $status_class = 'status-low';
                $status_text = 'Low Stock';
                $status_icon = 'fa-exclamation-triangle';
            } else {
                $status_class = 'status-available';
                $status_text = 'Available';
                $status_icon = 'fa-check-circle';
            }
            break;
        default:
            if ($qty <= 10) {
                $status_class = 'status-critical';
                $status_text = 'Critical';
                $status_icon = 'fa-times-circle';
            } elseif ($qty <= 20) {
                $status_class = 'status-low';
                $status_text = 'Low Stock';
                $status_icon = 'fa-exclamation-triangle';
            } else {
                $status_class = 'status-available';
                $status_text = 'Available';
                $status_icon = 'fa-check-circle';
            }
    }
}

$page_title = $item['item_name'] . " - Details";
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
        
        /* Back Button */
        .back-btn {
            display: inline-block;
            margin-bottom: 20px;
            color: #6c757d;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }
        
        .back-btn:hover {
            color: #0d6efd;
        }
        
        .back-btn i {
            margin-right: 5px;
        }
        
        /* Item Details Card */
        .details-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        .details-header {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .details-header i {
            font-size: 3rem;
            margin-bottom: 10px;
        }
        
        .details-header h1 {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .details-header p {
            font-size: 1rem;
            opacity: 0.9;
            margin: 0;
        }
        
        .details-body {
            padding: 30px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 480px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .info-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 15px;
        }
        
        .info-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: #6c757d;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e3c72;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .status-available {
            background: #d4edda;
            color: #155724;
        }
        
        .status-low {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-critical {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-out {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .description-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .description-box h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #1e3c72;
            margin-bottom: 10px;
        }
        
        .description-box p {
            color: #495057;
            line-height: 1.6;
            margin: 0;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
        }
        
        .btn-add-to-cart {
            flex: 1;
            padding: 15px;
            border: none;
            border-radius: 15px;
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            color: white;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .btn-add-to-cart:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13,110,253,0.3);
        }
        
        .btn-add-to-cart:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: #6c757d;
        }
        
        .btn-add-to-cart.critical-disabled {
            background: #dc3545;
            opacity: 0.7;
        }
        
        .btn-back-dash {
            flex: 1;
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 15px;
            background: white;
            color: #495057;
            font-weight: 600;
            font-size: 1rem;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .btn-back-dash:hover {
            border-color: #0d6efd;
            color: #0d6efd;
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
        
        /* Threshold Guide Styles */
        .threshold-guide {
            background: #e7f1ff;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .threshold-guide h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #1e3c72;
            margin-bottom: 15px;
        }
        
        .threshold-item {
            text-align: center;
            padding: 10px;
            border-radius: 10px;
        }
        
        .threshold-item.critical { background: rgba(220, 53, 69, 0.1); }
        .threshold-item.low { background: rgba(255, 193, 7, 0.1); }
        .threshold-item.available { background: rgba(40, 167, 69, 0.1); }
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
        <!-- Back Button -->
        <a href="javascript:history.back()" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        
        <!-- Item Details Card -->
        <div class="details-card">
            <div class="details-header">
                <?php
                // Determine icon based on category
                $icon = 'fa-box';
                if (strpos(strtolower($item['category'] ?? ''), 'computer') !== false) {
                    $icon = 'fa-desktop';
                } elseif (strpos(strtolower($item['category'] ?? ''), 'technical') !== false) {
                    $icon = 'fa-microchip';
                } elseif (strpos(strtolower($item['category'] ?? ''), 'paper') !== false) {
                    $icon = 'fa-file';
                }
                ?>
                <i class="fas <?php echo $icon; ?>"></i>
                <h1><?php echo htmlspecialchars($item['item_name']); ?></h1>
                <p><?php echo htmlspecialchars($item['category'] ?? 'Uncategorized'); ?></p>
            </div>
            
            <div class="details-body">
                <!-- Info Grid -->
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Brand</div>
                        <div class="info-value"><?php echo htmlspecialchars($item['brand'] ?? 'Generic'); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Category</div>
                        <div class="info-value"><?php echo htmlspecialchars($item['category'] ?? 'Uncategorized'); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Current Stock</div>
                        <div class="info-value"><?php echo $item['quantity']; ?> <?php echo htmlspecialchars($item['unit'] ?? 'pcs'); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Status</div>
                        <div class="info-value">
                            <span class="status-badge <?php echo $status_class; ?>">
                                <i class="fas <?php echo $status_icon; ?> me-1"></i>
                                <?php echo $status_text; ?>
                            </span>
                        </div>
                    </div>
                    
                    <?php if (!empty($item['supplier'])): ?>
                    <div class="info-item">
                        <div class="info-label">Supplier</div>
                        <div class="info-value"><?php echo htmlspecialchars($item['supplier']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="info-item">
                        <div class="info-label">Item ID</div>
                        <div class="info-value">#<?php echo str_pad($item['id'], 4, '0', STR_PAD_LEFT); ?></div>
                    </div>
                </div>
                
                <!-- OPTIONAL: Stock Level Guide -->
                <div class="threshold-guide">
                    <h3><i class="fas fa-info-circle me-2"></i>Stock Level Guide</h3>
                    <div class="row g-2">
                        <?php
                        $unit_display = $item['unit'] ?? 'units';
                        switch($unit) {
                            case 'pcs':
                                $critical = "≤ 30 {$unit_display}";
                                $low = "31 - 50 {$unit_display}";
                                $available = "≥ 51 {$unit_display}";
                                break;
                            case 'unit':
                                $critical = "≤ 10 {$unit_display}";
                                $low = "11 - 20 {$unit_display}";
                                $available = "≥ 21 {$unit_display}";
                                break;
                            case 'box':
                            case 'ream':
                                $critical = "≤ 10 {$unit_display}";
                                $low = "11 - 20 {$unit_display}";
                                $available = "≥ 21 {$unit_display}";
                                break;
                            default:
                                $critical = "≤ 10 {$unit_display}";
                                $low = "11 - 20 {$unit_display}";
                                $available = "≥ 21 {$unit_display}";
                        }
                        ?>
                        <div class="col-4">
                            <div class="threshold-item critical">
                                <span class="badge bg-danger mb-2">Critical</span>
                                <div class="small fw-bold"><?php echo $critical; ?></div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="threshold-item low">
                                <span class="badge bg-warning text-dark mb-2">Low</span>
                                <div class="small fw-bold"><?php echo $low; ?></div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="threshold-item available">
                                <span class="badge bg-success mb-2">Available</span>
                                <div class="small fw-bold"><?php echo $available; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Description -->
                <?php if (!empty($item['description'])): ?>
                <div class="description-box">
                    <h3>Description</h3>
                    <p><?php echo nl2br(htmlspecialchars($item['description'])); ?></p>
                </div>
                <?php endif; ?>
                
                <!-- Action Buttons -->
                <div class="action-buttons">
                    <?php if ($is_critical): ?>
                        <button class="btn-add-to-cart critical-disabled" 
                                disabled
                                data-tooltip="This item is CRITICAL and cannot be requested">
                            <i class="fas fa-ban me-2"></i>Cannot Request
                        </button>
                    <?php else: ?>
                        <button class="btn-add-to-cart" 
                                onclick="addToCart(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['item_name']); ?>', <?php echo $item['quantity']; ?>)">
                            <i class="fas fa-cart-plus me-2"></i>Add to Cart
                        </button>
                    <?php endif; ?>
                    <a href="dashboard.php" class="btn-back-dash">
                        <i class="fas fa-th-large me-2"></i>Dashboard
                    </a>
                </div>
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
        updateCartBadge();
        
        function updateCartBadge() {
            const totalItems = cart.reduce((sum, item) => sum + (item.quantity || 1), 0);
            const cartBadge = document.getElementById('cartBadge');
            if (cartBadge) {
                cartBadge.textContent = totalItems;
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
            updateCartBadge();
        }
        
        // Enhanced Toast notification
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
    </script>
</body>
</html>