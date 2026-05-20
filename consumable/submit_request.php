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

$page_title = "Submit Request - Consumable System";
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
            padding: 20px;
            border-radius: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
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
        
        .page-header p {
            color: #6c757d;
            font-size: 0.9rem;
            margin: 5px 0 0 0;
        }
        
        /* Request Form */
        .request-container {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .form-section {
            margin-bottom: 25px;
        }
        
        .form-section-title {
            font-size: 1rem;
            font-weight: 600;
            color: #1e3c72;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .form-section-title i {
            color: #0d6efd;
            margin-right: 8px;
        }
        
        .form-label {
            font-weight: 500;
            color: #495057;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 10px 15px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13,110,253,0.1);
            outline: none;
        }
        
        .form-control-plaintext {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 10px 15px;
            font-size: 0.9rem;
            border: 2px solid #e9ecef;
            color: #1e3c72;
        }
        
        .badge-campus {
            background: #e7f1ff;
            color: #0d6efd;
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
            margin-left: 10px;
        }
        
        /* Cart Items Review */
        .cart-items-review {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .review-header {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            padding: 10px;
            font-weight: 600;
            color: #495057;
            font-size: 0.85rem;
            text-transform: uppercase;
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 10px;
        }
        
        .review-item {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            padding: 10px;
            border-bottom: 1px solid #e9ecef;
            align-items: center;
        }
        
        .review-item:last-child {
            border-bottom: none;
        }
        
        .review-item-name {
            font-weight: 500;
            color: #1e3c72;
        }
        
        .review-item-category {
            font-size: 0.75rem;
            color: #6c757d;
        }
        
        .review-item-quantity {
            font-weight: 600;
            color: #0d6efd;
        }
        
        /* Purpose Field */
        .purpose-field {
            margin-top: 15px;
        }
        
        .optional-badge {
            background: #6c757d;
            color: white;
            font-size: 0.6rem;
            padding: 2px 8px;
            border-radius: 50px;
            margin-left: 8px;
            vertical-align: middle;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        .btn-submit {
            flex: 1;
            padding: 14px;
            border: none;
            border-radius: 15px;
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            color: white;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13,110,253,0.3);
        }
        
        .btn-back {
            flex: 1;
            padding: 14px;
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
        
        .btn-back:hover {
            border-color: #0d6efd;
            color: #0d6efd;
        }
        
        /* Empty State */
        .empty-cart {
            text-align: center;
            padding: 40px 20px;
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
        
        /* Signatories Section */
        .signatories-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .signatory-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
        }
        
        .signatory-item i {
            width: 30px;
            height: 30px;
            background: #e7f1ff;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0d6efd;
        }
        
        .signatory-name {
            font-weight: 600;
            color: #1e3c72;
        }
        
        .signatory-title {
            font-size: 0.75rem;
            color: #6c757d;
            margin-left: 5px;
        }
        
        /* Info Card */
        .info-card {
            background: #e7f1ff;
            border-radius: 12px;
            padding: 12px 15px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-card i {
            color: #0d6efd;
            font-size: 1.1rem;
        }
        
        .info-card p {
            margin: 0;
            color: #1e3c72;
            font-size: 0.85rem;
        }

        .optional-badge {
            background: #6c757d;
            color: white;
            font-size: 0.6rem;
            padding: 2px 8px;
            border-radius: 50px;
            margin-left: 8px;
            vertical-align: middle;
        }

        .info-card {
            background: #e7f1ff;
            border-radius: 12px;
            padding: 12px 15px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-card i {
            color: #0d6efd;
            font-size: 1.1rem;
        }

        .info-card p {
            margin: 0;
            color: #1e3c72;
            font-size: 0.85rem;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
            }
            
            .review-header {
                display: none;
            }
            
            .review-item {
                grid-template-columns: 1fr;
                gap: 5px;
                border: 1px solid #e9ecef;
                border-radius: 10px;
                margin-bottom: 10px;
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
            <h2>
                <i class="fas fa-paper-plane"></i>
                Submit Request
            </h2>
            <p>Review your cart items and submit your request for approval</p>
        </div>
        
        <!-- Request Form Container -->
        <div id="requestContent">
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
        let itemsData = [];
        
        // Update cart badge
        function updateCartBadge() {
            const totalItems = cart.reduce((sum, item) => sum + (item.quantity || 1), 0);
            const cartBadge = document.getElementById('cartBadge');
            if (cartBadge) {
                cartBadge.textContent = totalItems;
            }
        }
        
        // Load cart items
        async function loadCartItems() {
            const requestContent = document.getElementById('requestContent');
            
            if (cart.length === 0) {
                requestContent.innerHTML = `
                    <div class="empty-cart">
                        <i class="fas fa-shopping-cart"></i>
                        <h3>Your cart is empty</h3>
                        <p>Add items to your cart before submitting a request</p>
                        <a href="dashboard.php" class="btn-shop" style="background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%); color: white; padding: 12px 30px; text-decoration: none; border-radius: 50px; font-weight: 600; display: inline-block;">
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
                
                renderRequestForm();
            } catch (error) {
                console.error('Error loading cart items:', error);
                requestContent.innerHTML = `
                    <div class="empty-cart">
                        <i class="fas fa-exclamation-circle text-danger"></i>
                        <h3>Error loading cart</h3>
                        <p>Please try again later</p>
                        <button onclick="loadCartItems()" class="btn-shop" style="background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%); color: white; padding: 12px 30px; text-decoration: none; border-radius: 50px; font-weight: 600; display: inline-block; border: none;">
                            <i class="fas fa-sync-alt me-2"></i>Retry
                        </button>
                    </div>
                `;
            }
        }
        
        // Render request form
        function renderRequestForm() {
            let itemsHtml = '';
            let totalQuantity = 0;
            
            cart.forEach(cartItem => {
                const itemData = itemsData.find(d => d.id == cartItem.id);
                if (!itemData) return;
                
                totalQuantity += cartItem.quantity;
                
                itemsHtml += `
                    <div class="review-item">
                        <div>
                            <div class="review-item-name">${itemData.item_name}</div>
                            <div class="review-item-category">${itemData.category || 'Uncategorized'} • ${itemData.brand || 'Generic'}</div>
                            <small class="text-muted">Available: ${itemData.available_stock} ${itemData.unit || 'pcs'}</small>
                        </div>
                        <div class="review-item-quantity">${cartItem.quantity} ${itemData.unit || 'pcs'}</div>
                        <div>
                            <input type="text" class="form-control form-control-sm" 
                                placeholder="Purpose (e.g., Office use)" 
                                id="purpose_${cartItem.id}">
                        </div>
                    </div>
                `;
            });
            
            document.getElementById('requestContent').innerHTML = `
                <div class="request-container">
                    <form id="submitRequestForm" onsubmit="submitRequest(event)">
                        <!-- Cart Items Review -->
                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="fas fa-clipboard-list"></i>
                                Items to Request
                            </div>
                            <div class="info-card mb-3">
                                <i class="fas fa-info-circle"></i>
                                <p>Purpose for each item is optional, but recommended to provide an purpose of requested item.</p>
                            </div>
                            <div class="cart-items-review">
                                <div class="review-header">
                                    <div>Item</div>
                                    <div>Quantity</div>
                                    <div>Purpose</div>
                                </div>
                                ${itemsHtml}
                            </div>
                        </div>
                        
                        <!-- Request Details -->
                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="fas fa-info-circle"></i>
                                Request Details
                            </div>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Requested By</label>
                                    <input type="text" class="form-control-plaintext" 
                                        value="<?php echo htmlspecialchars($user['full_name']); ?>" 
                                        readonly>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Office/Department</label>
                                    <input type="text" class="form-control-plaintext" 
                                        value="<?php echo htmlspecialchars($user['department'] ?? 'Not Specified'); ?>" 
                                        readonly>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Campus</label>
                                    <input type="text" class="form-control-plaintext" 
                                        value="<?php echo htmlspecialchars($user['campus'] ?? 'Not Specified'); ?>" 
                                        readonly>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Request Date</label>
                                    <input type="text" class="form-control-plaintext" 
                                        value="<?php echo date('F d, Y'); ?>" 
                                        readonly>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Contact Number</label>
                                    <input type="text" class="form-control-plaintext" 
                                        value="<?php echo htmlspecialchars($user['phone'] ?? 'Not Specified'); ?>" 
                                        readonly>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Overall Purpose (Optional) -->
                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="fas fa-pen"></i>
                                Overall Purpose <span class="optional-badge">OPTIONAL</span>
                            </div>
                            <div class="info-card">
                                <i class="fas fa-info-circle"></i>
                                <p>You can provide an overall purpose for this request, or leave it blank</p>
                            </div>
                            <textarea class="form-control purpose-field" id="overallPurpose" 
                                    rows="3" placeholder="Enter the overall purpose of this request (optional)..."></textarea>
                        </div>
                        
                        <!-- Signatories (Read-only) -->
                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="fas fa-signature"></i>
                                For Official Use Only
                            </div>
                            <div class="signatories-section">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="signatory-item">
                                            <i class="fas fa-check-circle"></i>
                                            <div>
                                                <span class="signatory-name">REYNALDO H. CARANDANG JR.</span>
                                                <span class="signatory-title">Approved By</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="signatory-item">
                                            <i class="fas fa-truck"></i>
                                            <div>
                                                <span class="signatory-name">MARVIN Z. GERVACIO</span>
                                                <span class="signatory-title">Supply Officer</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="action-buttons">
                            <a href="cart.php" class="btn-back">
                                <i class="fas fa-arrow-left me-2"></i>Back to Cart
                            </a>
                            <button type="submit" class="btn-submit">
                                <i class="fas fa-paper-plane me-2"></i>Submit Request
                            </button>
                        </div>
                    </form>
                </div>
            `;
        }
        
        // Submit request
        async function submitRequest(event) {
            event.preventDefault();
            
            // Collect item purposes (now optional)
            const items = [];
            for (const cartItem of cart) {
                const purposeInput = document.getElementById(`purpose_${cartItem.id}`);
                const purpose = purposeInput ? purposeInput.value.trim() : '';
                
                items.push({
                    id: cartItem.id,
                    quantity: cartItem.quantity,
                    purpose: purpose // Now optional, can be empty
                });
            }
            
            // Get overall purpose (optional)
            const overallPurpose = document.getElementById('overallPurpose').value.trim();
            
            // Prepare data for submission
            const requestData = {
                user_id: <?php echo $user_id; ?>,
                requested_by: '<?php echo addslashes($user['full_name']); ?>',
                department: '<?php echo addslashes($user['department'] ?? ''); ?>',
                campus: '<?php echo addslashes($user['campus'] ?? ''); ?>',
                overall_purpose: overallPurpose,
                items: items,
                approved_by: 'REYNALDO H. CARANDANG JR.',
                supply_officer: 'MARVIN Z. GERVACIO'
            };
            
            // Show loading state
            const submitBtn = document.querySelector('.btn-submit');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="spinner" style="width: 20px; height: 20px; margin: 0;"></span> Submitting...';
            submitBtn.disabled = true;
            
            try {
                // Send request to server
                const response = await fetch('process_request.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(requestData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Clear cart on success
                    cart = [];
                    localStorage.setItem('cart', JSON.stringify(cart));
                    updateCartBadge();
                    
                    // Show success message
                    showToast('success', 'Success!', result.message);
                    
                    // Redirect to my requests page after 2 seconds
                    setTimeout(() => {
                        window.location.href = 'my_requests.php?highlight=' + encodeURIComponent(result.group_code);
                    }, 2000);
                } else {
                    showToast('error', 'Error', result.message || 'Failed to submit request');
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            } catch (error) {
                console.error('Error submitting request:', error);
                showToast('error', 'Error', 'An error occurred. Please try again.');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
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
    </script>
</body>
</html>