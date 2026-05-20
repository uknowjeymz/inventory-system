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

// Handle profile update
$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $campus = trim($_POST['campus'] ?? ''); // New campus field
    $phone = trim($_POST['phone'] ?? '');
    
    // Validation
    $errors = [];
    
    if (empty($full_name)) {
        $errors[] = "Full name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email address is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
    }
    
    // Check if email already exists (but not for current user)
    if (empty($errors)) {
        $check_stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check_stmt->execute([$email, $user_id]);
        if ($check_stmt->rowCount() > 0) {
            $errors[] = "Email address already used by another account";
        }
    }
    
    if (empty($errors)) {
        try {
            $update_query = "UPDATE users SET full_name = ?, email = ?, department = ?, campus = ?, phone = ?, updated_at = NOW() WHERE id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([$full_name, $email, $department, $campus, $phone, $user_id]);
            
            // Update session
            $_SESSION['full_name'] = $full_name;
            $_SESSION['campus'] = $campus; // Update campus in session
            
            $success = "Profile updated successfully!";
            
            // Refresh user data
            $user_query->execute([$user_id]);
            $user = $user_query->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $error = "Error updating profile: " . $e->getMessage();
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

$page_title = "My Profile - Consumable System";
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
        
        /* Profile Card */
        .profile-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .profile-avatar-large {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 2.5rem;
            box-shadow: 0 10px 20px rgba(13,110,253,0.2);
        }
        
        .profile-title h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e3c72;
            margin-bottom: 5px;
        }
        
        .profile-title p {
            color: #6c757d;
            margin: 0;
        }
        
        .badge-role {
            background: #e7f1ff;
            color: #0d6efd;
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .badge-campus {
            background: #28a745;
            color: white;
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
            margin-left: 8px;
        }
        
        .form-section-title {
            font-size: 1rem;
            font-weight: 600;
            color: #1e3c72;
            margin-bottom: 20px;
            padding-bottom: 10px;
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
        
        .form-control:read-only {
            background-color: #f8f9fa;
            cursor: not-allowed;
        }
        
        .btn-save {
            padding: 12px 30px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            color: white;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13,110,253,0.3);
        }
        
        .btn-cancel {
            padding: 12px 30px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            background: white;
            color: #495057;
            font-weight: 600;
            font-size: 1rem;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .btn-cancel:hover {
            border-color: #0d6efd;
            color: #0d6efd;
        }
        
        .alert {
            border-radius: 12px;
            padding: 12px 15px;
            margin-bottom: 20px;
            border: none;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* Info Row */
        .info-row {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .info-label {
            width: 150px;
            font-weight: 500;
            color: #6c757d;
        }
        
        .info-value {
            flex: 1;
            font-weight: 500;
            color: #1e3c72;
        }
        
        .campus-badge-display {
            background: #28a745;
            color: white;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            display: inline-block;
            margin-left: 5px;
        }
        
        @media (max-width: 480px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .info-row {
                flex-direction: column;
                gap: 5px;
            }
            
            .info-label {
                width: auto;
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
                <a href="profile.php" class="menu-item active">
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
                <i class="fas fa-user-circle"></i>
                My Profile
            </h2>
            <p>View and manage your personal information</p>
        </div>
        
        <!-- Alert Messages -->
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show"><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show"><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        
        <!-- Profile Card -->
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar-large">
                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                </div>
                <div class="profile-title">
                    <h3>
                        <?php echo htmlspecialchars($user['full_name']); ?>
                        <?php if (!empty($user['campus'])): ?>
                            <span class="badge-campus">
                                <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($user['campus']); ?>
                            </span>
                        <?php endif; ?>
                    </h3>
                    <p><span class="badge-role"><?php echo ucfirst($user['role']); ?></span></p>
                    <p class="text-muted small mb-0">Member since <?php echo date('F d, Y', strtotime($user['created_at'])); ?></p>
                </div>
            </div>
            
            <!-- Profile Information Display (Read-only) -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-envelope me-2 text-primary"></i>Email:</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-building me-2 text-primary"></i>Department:</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['department'] ?? 'Not specified'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-map-marker-alt me-2 text-primary"></i>Campus:</span>
                        <span class="info-value">
                            <?php if (!empty($user['campus'])): ?>
                                <?php echo htmlspecialchars($user['campus']); ?>
                            <?php else: ?>
                                <span class="text-muted">Not specified</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-phone me-2 text-primary"></i>Phone:</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['phone'] ?? 'Not specified'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-clock me-2 text-primary"></i>Last Login:</span>
                        <span class="info-value"><?php echo $user['last_login'] ? date('M d, Y h:i A', strtotime($user['last_login'])) : 'First login'; ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Edit Profile Button (Triggers Edit Mode) -->
            <div class="text-end">
                <button class="btn-save" onclick="toggleEditMode()" id="editButton">
                    <i class="fas fa-edit me-2"></i>Edit Profile
                </button>
            </div>
        </div>
        
        <!-- Edit Profile Form (Hidden by default) -->
        <div class="profile-card" id="editProfileForm" style="display: none;">
            <div class="form-section-title">
                <i class="fas fa-pen"></i>
                Edit Profile Information
            </div>
            
            <form method="POST" action="profile.php" id="profileForm">
                <input type="hidden" name="update_profile" value="1">
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Department / Office</label>
                        <input type="text" class="form-control" name="department" value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Campus</label>
                        <select name="campus" class="form-select">
                            <option value="">-- Select Campus --</option>
                            <option value="South Campus" <?php echo ($user['campus'] == 'South Campus') ? 'selected' : ''; ?>>South Campus</option>
                            <option value="Congressional Campus" <?php echo ($user['campus'] == 'Congressional Campus') ? 'selected' : ''; ?>>Congressional Campus</option>
                            <option value="Camarin Campus" <?php echo ($user['campus'] == 'Camarin Campus') ? 'selected' : ''; ?>>Camarin Campus</option>
                            <option value="Bagong Silang Campus" <?php echo ($user['campus'] == 'Bagong Silang Campus') ? 'selected' : ''; ?>>Bagong Silang Campus</option>
                        </select>
                        <small class="text-muted">Select your primary campus</small>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                               pattern="[0-9]{11}" title="Please enter a valid 11-digit phone number">
                        <small class="text-muted">Format: 09123456789 (11 digits)</small>
                    </div>
                    
                    <div class="col-12 mt-4">
                        <div class="alert alert-info border-0">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> To change your password, please go to <a href="settings.php" class="alert-link">Settings</a>.
                        </div>
                    </div>
                    
                    <div class="col-12 text-end mt-3">
                        <button type="button" class="btn-cancel me-2" onclick="toggleEditMode()">Cancel</button>
                        <button type="submit" class="btn-save">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Account Statistics -->
        <div class="profile-card">
            <div class="form-section-title">
                <i class="fas fa-chart-bar"></i>
                Account Statistics
            </div>
            
            <?php
            // Get request statistics for this user
            $stats_query = $db->prepare("
                SELECT 
                    COUNT(*) as total_requests,
                    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_requests,
                    SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved_requests,
                    SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected_requests
                FROM request_groups
                WHERE requested_by = ? OR employee = ?
            ");
            $stats_query->execute([$user['full_name'], $user['full_name']]);
            $stats = $stats_query->fetch(PDO::FETCH_ASSOC);
            ?>
            
            <div class="row">
                <div class="col-md-3 col-6 mb-3">
                    <div class="stat-card-mini">
                        <div class="stat-icon-mini bg-primary bg-opacity-10">
                            <i class="fas fa-file-alt text-primary"></i>
                        </div>
                        <div class="stat-number-mini"><?php echo $stats['total_requests'] ?? 0; ?></div>
                        <div class="stat-label-mini">Total Requests</div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="stat-card-mini">
                        <div class="stat-icon-mini bg-warning bg-opacity-10">
                            <i class="fas fa-clock text-warning"></i>
                        </div>
                        <div class="stat-number-mini"><?php echo $stats['pending_requests'] ?? 0; ?></div>
                        <div class="stat-label-mini">Pending</div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="stat-card-mini">
                        <div class="stat-icon-mini bg-success bg-opacity-10">
                            <i class="fas fa-check-circle text-success"></i>
                        </div>
                        <div class="stat-number-mini"><?php echo $stats['approved_requests'] ?? 0; ?></div>
                        <div class="stat-label-mini">Approved</div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="stat-card-mini">
                        <div class="stat-icon-mini bg-danger bg-opacity-10">
                            <i class="fas fa-times-circle text-danger"></i>
                        </div>
                        <div class="stat-number-mini"><?php echo $stats['rejected_requests'] ?? 0; ?></div>
                        <div class="stat-label-mini">Rejected</div>
                    </div>
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
        
        // Toggle edit mode
        function toggleEditMode() {
            const editForm = document.getElementById('editProfileForm');
            const editButton = document.getElementById('editButton');
            
            if (editForm.style.display === 'none') {
                editForm.style.display = 'block';
                editButton.innerHTML = '<i class="fas fa-times me-2"></i>Cancel Edit';
                editButton.classList.add('btn-cancel');
                editButton.classList.remove('btn-save');
                
                // Scroll to edit form
                setTimeout(() => {
                    editForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 100);
            } else {
                editForm.style.display = 'none';
                editButton.innerHTML = '<i class="fas fa-edit me-2"></i>Edit Profile';
                editButton.classList.add('btn-save');
                editButton.classList.remove('btn-cancel');
            }
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
        
        // Touch-friendly interactions
        if ('ontouchstart' in window) {
            document.querySelectorAll('.btn-save, .btn-cancel, .menu-item').forEach(el => {
                el.addEventListener('touchstart', function() {
                    this.style.opacity = '0.8';
                });
                el.addEventListener('touchend', function() {
                    this.style.opacity = '1';
                });
            });
        }
    </script>
    
    <style>
        .stat-card-mini {
            background: white;
            padding: 15px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.03);
            border: 1px solid #e9ecef;
        }
        
        .stat-icon-mini {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 1.2rem;
        }
        
        .stat-number-mini {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1e3c72;
            line-height: 1.2;
        }
        
        .stat-label-mini {
            font-size: 0.7rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>
</body>
</html>