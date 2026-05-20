<?php
session_start();
date_default_timezone_set('Asia/Manila');

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_type'] === 'admin') {
        header("Location: ../admin/consumables.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}

// Include database connection
require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

$token = $_GET['token'] ?? '';
$valid_token = false;
$user_id = null;
$error = "";
$success = "";

if (empty($token)) {
    $error = "Invalid or missing reset token.";
} else {
    // Verify token
    $stmt = $db->prepare("SELECT * FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW()");
    $stmt->execute([$token]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($reset) {
        $valid_token = true;
        $user_id = $reset['user_id'];
    } else {
        $error = "Invalid or expired reset token. Please request a new password reset.";
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password']) && $valid_token) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $errors = [];
    
    if (empty($password)) {
        $errors[] = "New password is required";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    if (empty($confirm_password)) {
        $errors[] = "Please confirm your password";
    } elseif ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Update user password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $update = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            $update->execute([$hashed_password, $user_id]);
            
            // Mark token as used
            $mark_used = $db->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
            $mark_used->execute([$token]);
            
            $db->commit();
            
            $success = "Password has been reset successfully! You can now login with your new password.";
            
            // Clear token validity to prevent further submissions
            $valid_token = false;
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Password reset error: " . $e->getMessage());
            $error = "An error occurred. Please try again.";
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

$page_title = "Reset Password - Consumable System";
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
            min-height: 100vh;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 15px;
        }
        
        /* Background image with overlay */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('../consumable/assets/UCC_South.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            z-index: -2;
        }
        
        /* Dark overlay */
        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.6) 0%, rgba(0, 0, 0, 0.4) 100%);
            z-index: -1;
        }
        
        .reset-container {
            width: 100%;
            max-width: 450px;
            margin: auto;
            position: relative;
            z-index: 1;
        }
        
        .reset-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 25px;
            padding: 30px 25px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideUp 0.5s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .logo-section {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .logo-section img {
            width: 70px;
            height: auto;
            margin-bottom: 10px;
            background: white;
            padding: 8px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .logo-section h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e3c72;
            margin-bottom: 3px;
            line-height: 1.2;
        }
        
        .logo-section .subtitle {
            font-size: 0.9rem;
            color: #4a5568;
            font-weight: 500;
        }
        
        .logo-section .subtitle span {
            color: #0d6efd;
            font-weight: 700;
        }
        
        .welcome-text {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .welcome-text h2 {
            font-size: 1.3rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 5px;
        }
        
        .welcome-text p {
            color: #718096;
            font-size: 0.9rem;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            font-weight: 500;
            color: #4a5568;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        
        .input-group {
            border: 2px solid #e2e8f0;
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            background: white;
        }
        
        .input-group:focus-within {
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
        }
        
        .input-group-text {
            background: white;
            border: none;
            color: #a0aec0;
            padding: 0 15px;
        }
        
        .form-control {
            border: none;
            padding: 12px 15px;
            font-size: 0.95rem;
            background: transparent;
        }
        
        .form-control:focus {
            box-shadow: none;
            background: transparent;
        }
        
        .password-toggle {
            cursor: pointer;
            background: white;
            border: none;
            color: #a0aec0;
            padding: 0 15px;
            transition: color 0.3s ease;
        }
        
        .password-toggle:hover {
            color: #0d6efd;
        }
        
        .btn-reset {
            width: 100%;
            padding: 14px;
            border-radius: 15px;
            font-weight: 600;
            font-size: 1rem;
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            border: none;
            color: white;
            margin-top: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(13, 110, 253, 0.3);
        }
        
        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(13, 110, 253, 0.4);
        }
        
        .btn-reset:active {
            transform: translateY(0);
        }
        
        .btn-reset:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .btn-login {
            width: 100%;
            padding: 12px;
            border-radius: 15px;
            font-weight: 600;
            font-size: 0.95rem;
            background: white;
            border: 2px solid #0d6efd;
            color: #0d6efd;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            margin-top: 15px;
        }
        
        .btn-login:hover {
            background: #0d6efd;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13, 110, 253, 0.2);
        }
        
        .alert {
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 20px;
            border: none;
            font-size: 0.9rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .password-requirements {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 10px;
        }
        
        .password-requirements i {
            width: 20px;
            color: #0d6efd;
        }
        
        .requirement-met {
            color: #28a745;
        }
        
        /* Mobile optimizations */
        @media (max-width: 480px) {
            .reset-card {
                padding: 20px 15px;
            }
            
            .logo-section img {
                width: 60px;
            }
            
            .logo-section h1 {
                font-size: 1.3rem;
            }
            
            .welcome-text h2 {
                font-size: 1.2rem;
            }
            
            .form-control {
                padding: 10px 12px;
            }
            
            .btn-reset, .btn-login {
                padding: 12px;
            }
        }
        
        /* Touch-friendly inputs */
        @media (hover: none) and (pointer: coarse) {
            .form-control, .btn-reset, .btn-login, .password-toggle {
                min-height: 48px;
            }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-card">
            <!-- Logo and Title Section -->
            <div class="logo-section">
                <img src="../consumable/assets/UCC_Logo.png" alt="UCC Logo">
                <h1>University of Caloocan City</h1>
                <div class="subtitle">
                    <span>Consumable</span> Management System
                </div>
            </div>
            
            <!-- Welcome Message -->
            <div class="welcome-text">
                <h2>Reset Password</h2>
                <p><?php echo $valid_token ? 'Enter your new password' : 'Invalid or expired token'; ?></p>
            </div>
            
            <!-- Alert Messages -->
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($valid_token): ?>
                <!-- Reset Password Form -->
                <form action="reset_password.php?token=<?php echo urlencode($token); ?>" method="POST" id="resetForm">
                    <input type="hidden" name="reset_password" value="1">
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-lock me-2"></i>New Password
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-key"></i>
                            </span>
                            <input type="password" 
                                   class="form-control" 
                                   name="password" 
                                   id="password"
                                   placeholder="Enter new password"
                                   required 
                                   minlength="8">
                            <span class="password-toggle" onclick="togglePassword('password', this)">
                                <i class="fas fa-eye"></i>
                            </span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-check-circle me-2"></i>Confirm Password
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" 
                                   class="form-control" 
                                   name="confirm_password" 
                                   id="confirm_password"
                                   placeholder="Confirm new password"
                                   required>
                            <span class="password-toggle" onclick="togglePassword('confirm_password', this)">
                                <i class="fas fa-eye"></i>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Password Requirements -->
                    <div class="password-requirements">
                        <p class="mb-2 fw-bold">Password Requirements:</p>
                        <div id="length-req" class="mb-1">
                            <i class="fas fa-circle"></i> At least 8 characters
                        </div>
                        <div id="match-req">
                            <i class="fas fa-circle"></i> Passwords match
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-reset" id="submitBtn">
                        <i class="fas fa-save me-2"></i>Reset Password
                    </button>
                </form>
            <?php endif; ?>
            
            <!-- Back to Login -->
            <a href="login.php" class="btn-login">
                <i class="fas fa-arrow-left me-2"></i>Back to Login
            </a>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
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
        
        <?php if ($valid_token): ?>
        // Password validation
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        const lengthReq = document.getElementById('length-req');
        const matchReq = document.getElementById('match-req');
        
        function validatePassword() {
            // Check length
            if (password.value.length >= 8) {
                lengthReq.innerHTML = '<i class="fas fa-check-circle text-success"></i> At least 8 characters ✓';
                lengthReq.classList.add('requirement-met');
            } else {
                lengthReq.innerHTML = '<i class="fas fa-circle"></i> At least 8 characters';
                lengthReq.classList.remove('requirement-met');
            }
            
            // Check match
            if (password.value && confirmPassword.value && password.value === confirmPassword.value) {
                matchReq.innerHTML = '<i class="fas fa-check-circle text-success"></i> Passwords match ✓';
                matchReq.classList.add('requirement-met');
            } else {
                matchReq.innerHTML = '<i class="fas fa-circle"></i> Passwords match';
                matchReq.classList.remove('requirement-met');
            }
        }
        
        password.addEventListener('keyup', validatePassword);
        confirmPassword.addEventListener('keyup', validatePassword);
        
        // Form validation
        document.getElementById('resetForm').addEventListener('submit', function(e) {
            if (password.value.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long');
            } else if (password.value !== confirmPassword.value) {
                e.preventDefault();
                alert('Passwords do not match');
            }
        });
        <?php endif; ?>
        
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
            document.querySelectorAll('.btn-reset, .btn-login, .password-toggle').forEach(el => {
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