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

// Handle password change
$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    $errors = [];
    
    if (empty($current_password)) {
        $errors[] = "Current password is required";
    }
    
    if (empty($new_password)) {
        $errors[] = "New password is required";
    } elseif (strlen($new_password) < 8) {
        $errors[] = "New password must be at least 8 characters long";
    }
    
    if (empty($confirm_password)) {
        $errors[] = "Please confirm your new password";
    } elseif ($new_password !== $confirm_password) {
        $errors[] = "New passwords do not match";
    }
    
    if (empty($errors)) {
        try {
            // Verify current password
            if (!password_verify($current_password, $user['password'])) {
                $errors[] = "Current password is incorrect";
            } else {
                // Hash new password and update
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_query = "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->execute([$hashed_password, $user_id]);
                
                $success = "Password changed successfully!";
                
                // Log the password change (optional)
                error_log("Password changed for user: " . $user['email']);
            }
        } catch (Exception $e) {
            $error = "Error changing password: " . $e->getMessage();
        }
    }
    
    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    }
}

$page_title = "Settings - Consumable System";
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
        
        /* Settings Card */
        .settings-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            max-width: 600px;
            margin: 0 auto;
        }
        
        .settings-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 2rem;
            box-shadow: 0 10px 20px rgba(13,110,253,0.2);
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
        
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 10px 15px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13,110,253,0.1);
            outline: none;
        }
        
        .btn-save {
            width: 100%;
            padding: 14px;
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
        
        .btn-back {
            width: 100%;
            padding: 14px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            background: white;
            color: #495057;
            font-weight: 600;
            font-size: 1rem;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .btn-back:hover {
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
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .password-requirements {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .password-requirements i {
            width: 20px;
            color: #0d6efd;
        }
        
        .requirement-met {
            color: #28a745;
        }
        
        .requirement-unmet {
            color: #6c757d;
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
                <a href="settings.php" class="menu-item active">
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
                <i class="fas fa-cog"></i>
                Settings
            </h2>
            <p>Manage your account settings and change password</p>
        </div>
        
        <!-- Settings Card -->
        <div class="settings-card">
            <div class="settings-icon">
                <i class="fas fa-lock"></i>
            </div>
            
            <h4 class="text-center mb-4">Change Password</h4>
            
            <!-- Alert Messages -->
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show"><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show"><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>
            
            <form method="POST" action="settings.php" id="passwordForm">
                <input type="hidden" name="change_password" value="1">
                
                <div class="mb-3">
                    <label class="form-label">Current Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-2 border-end-0">
                            <i class="fas fa-lock text-primary"></i>
                        </span>
                        <input type="password" class="form-control border-2" name="current_password" id="current_password" required>
                        <span class="input-group-text bg-white border-2 border-start-0" onclick="togglePassword('current_password', this)">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-2 border-end-0">
                            <i class="fas fa-key text-primary"></i>
                        </span>
                        <input type="password" class="form-control border-2" name="new_password" id="new_password" required minlength="8">
                        <span class="input-group-text bg-white border-2 border-start-0" onclick="togglePassword('new_password', this)">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">Confirm New Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-2 border-end-0">
                            <i class="fas fa-check-circle text-primary"></i>
                        </span>
                        <input type="password" class="form-control border-2" name="confirm_password" id="confirm_password" required>
                        <span class="input-group-text bg-white border-2 border-start-0" onclick="togglePassword('confirm_password', this)">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>
                
                <!-- Password Requirements -->
                <div class="password-requirements mb-4">
                    <p class="mb-2 fw-bold">Password Requirements:</p>
                    <div id="length-req" class="mb-1">
                        <i class="fas fa-circle"></i> At least 8 characters
                    </div>
                    <div id="match-req">
                        <i class="fas fa-circle"></i> Passwords match
                    </div>
                </div>
                
                <!-- Info Alert -->
                <div class="alert alert-info border-0 small mb-4">
                    <i class="fas fa-info-circle me-2"></i>
                    Choose a strong password that you don't use elsewhere.
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn-save" id="submitBtn">
                        <i class="fas fa-save me-2"></i>Update Password
                    </button>
                    <a href="dashboard.php" class="btn-back mt-2">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
            </form>
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
        
        // Toggle password visibility
        function togglePassword(inputId, element) {
            const input = document.getElementById(inputId);
            const icon = element.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Password validation
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        const lengthReq = document.getElementById('length-req');
        const matchReq = document.getElementById('match-req');
        
        function validatePassword() {
            // Check length
            if (newPassword.value.length >= 8) {
                lengthReq.innerHTML = '<i class="fas fa-check-circle text-success"></i> At least 8 characters ✓';
                lengthReq.classList.add('requirement-met');
            } else {
                lengthReq.innerHTML = '<i class="fas fa-circle"></i> At least 8 characters';
                lengthReq.classList.remove('requirement-met');
            }
            
            // Check match
            if (newPassword.value && confirmPassword.value && newPassword.value === confirmPassword.value) {
                matchReq.innerHTML = '<i class="fas fa-check-circle text-success"></i> Passwords match ✓';
                matchReq.classList.add('requirement-met');
            } else {
                matchReq.innerHTML = '<i class="fas fa-circle"></i> Passwords match';
                matchReq.classList.remove('requirement-met');
            }
        }
        
        newPassword.addEventListener('keyup', validatePassword);
        confirmPassword.addEventListener('keyup', validatePassword);
        
        // Form validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            if (newPassword.value.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long');
            } else if (newPassword.value !== confirmPassword.value) {
                e.preventDefault();
                alert('Passwords do not match');
            }
        });
        
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
            document.querySelectorAll('.btn-save, .btn-back, .menu-item').forEach(el => {
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