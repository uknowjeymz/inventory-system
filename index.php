<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UCC Inventory Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="assets/UCC_Logo.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-green: #2E7D32;
            --secondary-green: #4CAF50;
            --light-green: #81C784;
            --soft-green: #E8F5E9;
            --mint-green: #C8E6C9;
            --dark-green: #1B5E20;
            --custom-green: #5eaf62; /* Your specified green color */
            --pure-white: #FFFFFF;
            --off-white: #F8F9FA;
            --gray-light: #F1F8E9;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
            background: linear-gradient(135deg, var(--soft-green) 0%, var(--pure-white) 100%);
        }

        /* Background Image with Custom Green Overlay */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('assets/UCC_Background.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            filter: brightness(0.9) contrast(1.1);
            z-index: -2;
        }

        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, 
                rgba(94, 175, 98, 0.7) 0%,  /* #5eaf62 with 0.7 opacity */
                rgba(94, 175, 98, 0.5) 50%,
                rgba(94, 175, 98, 0.3) 100%);
            backdrop-filter: blur(3px);
            z-index: -1;
        }

        /* Main Container */
        .login-container {
            min-height: 100vh;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            position: relative;
        }

        /* Login Card - Wider for Desktop */
        .login-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            box-shadow: 
                0 30px 80px rgba(46, 125, 50, 0.4),
                0 15px 40px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.6);
            overflow: hidden;
            max-width: 1000px; /* Increased from 450px to 1000px for wider desktop view */
            width: 90%;
            animation: slideUp 0.6s ease-out;
        }

        /* Split Layout for Desktop */
        .login-split {
            display: flex;
            min-height: 600px; /* Fixed height for desktop */
        }

        /* Left Side - Branding/Info */
        .login-brand {
            flex: 1;
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--custom-green) 100%);
            padding: 3rem 2.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .login-brand::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        .brand-content {
            position: relative;
            z-index: 2;
            color: white;
        }

        .brand-logo {
            margin-bottom: 2.5rem;
        }

        .brand-logo-img {
            width: 100px;
            height: 100px;
            background: var(--pure-white);
            border-radius: 24px;
            padding: 15px;
            margin-bottom: 1.5rem;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
            border: 3px solid var(--light-green);
        }

        .brand-logo-img img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .brand-title {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 0.8rem;
            line-height: 1.2;
        }

        .brand-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 2.5rem;
        }

        .brand-features {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .brand-features li {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.2rem;
            font-size: 1rem;
            opacity: 0.9;
        }

        .brand-features li i {
            width: 24px;
            color: var(--light-green);
            font-size: 1.2rem;
        }

        /* Right Side - Login Form */
        .login-form-side {
            flex: 1;
            padding: 3rem;
            background: var(--pure-white);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Welcome Text */
        .welcome-text {
            margin-bottom: 2rem;
        }

        .welcome-text h3 {
            color: var(--dark-green);
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .welcome-text p {
            color: #6B7280;
            font-size: 1rem;
        }

        .role-badge {
            background: var(--soft-green);
            color: var(--dark-green);
            padding: 0.6rem 1.5rem;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.8rem;
            border: 1px solid var(--light-green);
            margin-top: 0.8rem;
        }

        /* Form Elements - Larger for Desktop */
        .input-group {
            margin-bottom: 1.5rem;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
            transition: all 0.3s ease;
        }

        .input-group:focus-within {
            box-shadow: 0 8px 25px rgba(46, 125, 50, 0.15);
            transform: translateY(-2px);
        }

        .input-group-text {
            background: var(--pure-white);
            border: 2px solid var(--mint-green);
            border-right: none;
            padding: 1rem 1.5rem;
        }

        .input-group-text i {
            color: var(--primary-green);
            font-size: 1.2rem;
        }

        .form-control {
            border: 2px solid var(--mint-green);
            border-left: none;
            border-right: none;
            padding: 1rem 1.2rem;
            font-size: 1rem;
            font-weight: 500;
            color: #1F2937;
            background: var(--pure-white);
            height: auto;
        }

        .form-control:focus {
            box-shadow: none;
            border-color: var(--primary-green);
            background: var(--pure-white);
        }

        .form-control::placeholder {
            color: #9CA3AF;
            font-weight: 400;
        }

        /* Toggle Password Button */
        #togglePassword {
            background: var(--pure-white);
            border: 2px solid var(--mint-green);
            border-left: none;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 1rem 1.5rem;
        }

        #togglePassword:hover {
            background: var(--soft-green);
        }

        #togglePassword i {
            color: var(--primary-green);
            font-size: 1.2rem;
        }

        /* Login Button - Larger */
        .btn-login {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--custom-green) 100%);
            color: var(--pure-white);
            border: none;
            padding: 1.2rem;
            font-weight: 700;
            font-size: 1.1rem;
            border-radius: 18px;
            width: 100%;
            margin: 2rem 0 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 15px 30px rgba(94, 175, 98, 0.3);
            cursor: pointer;
        }

        .btn-login:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 20px 40px rgba(94, 175, 98, 0.4);
            background: linear-gradient(135deg, var(--custom-green) 0%, var(--primary-green) 100%);
        }

        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        /* Links */
        .forgot-link {
            text-align: center;
            margin: 1.2rem 0;
        }

        .forgot-link a {
            color: var(--primary-green);
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .forgot-link a:hover {
            color: var(--dark-green);
            text-decoration: underline;
        }

        /* Register Section */
        .register-section {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px dashed var(--mint-green);
        }

        .register-section p {
            color: #6B7280;
            margin-bottom: 1.2rem;
            font-weight: 500;
            font-size: 1rem;
        }

        .btn-register {
            background: transparent;
            color: var(--primary-green);
            border: 2px solid var(--primary-green);
            padding: 1rem 2.5rem;
            font-weight: 600;
            font-size: 1rem;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-register:hover {
            background: var(--primary-green);
            color: var(--pure-white);
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(46, 125, 50, 0.2);
        }

        /* Alert Messages */
        .alert {
            border-radius: 18px;
            border: none;
            padding: 1.2rem 1.8rem;
            margin-bottom: 2rem;
            animation: slideDown 0.5s ease-out;
            font-size: 0.95rem;
        }

        .alert-success {
            background: var(--soft-green);
            color: var(--dark-green);
            border-left: 4px solid var(--primary-green);
        }

        .alert-danger {
            background: #FFEBEE;
            color: #B71C1C;
            border-left: 4px solid #D32F2F;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Modal Styling */
        .modal-content {
            border-radius: 28px;
            border: none;
            overflow: hidden;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--custom-green) 100%);
            color: white;
            border: none;
            padding: 1.8rem;
        }

        .modal-header .btn-close {
            opacity: 0.8;
            border-radius: 50%;
            padding: 0.6rem;
        }

        .modal-body {
            padding: 2.5rem;
        }

        .modal-footer {
            border-top: 2px solid var(--mint-green);
            padding: 1.8rem;
        }

        /* Developer Modal Icons */
        .dev-icon {
            width: 55px;
            height: 55px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
        }

        .dev-icon.bg-success { background: var(--primary-green); }
        .dev-icon.bg-info { background: #4FC3F7; }
        .dev-icon.bg-warning { background: #FFB74D; }

        /* Footer */
        .footer-copyright {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            color: white;
            font-size: 0.9rem;
            background: rgba(94, 175, 98, 0.8);
            padding: 0.7rem 1.5rem;
            border-radius: 50px;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 100;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            font-weight: 500;
        }

        .footer-copyright:hover {
            background: var(--primary-green);
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(94, 175, 98, 0.4);
        }

        /* Developer Avatar Styles */
        .developer-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-green);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        /* Test DB Link */
        .test-db-link {
            text-align: center;
            margin-top: 1.2rem;
        }

        .test-db-link a {
            color: #9CA3AF;
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }

        .test-db-link a:hover {
            color: var(--primary-green);
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .login-split {
                flex-direction: column;
                min-height: auto;
            }
            
            .login-brand {
                padding: 2.5rem;
            }
            
            .login-form-side {
                padding: 2.5rem;
            }
            
            .login-card {
                max-width: 600px;
            }
        }

        @media (max-width: 576px) {
            .login-container {
                padding: 1rem;
            }
            
            .login-form-side {
                padding: 2rem;
            }
            
            .brand-title {
                font-size: 1.8rem;
            }
            
            .welcome-text h3 {
                font-size: 1.6rem;
            }
            
            .footer-copyright {
                bottom: 1rem;
                right: 1rem;
                font-size: 0.8rem;
                padding: 0.5rem 1.2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Main Container -->
    <div class="login-container">
        <div class="login-card">
            <div class="login-split">
                <!-- Left Side - Branding -->
                <div class="login-brand">
                    <div class="brand-content">
                        <div class="brand-logo">
                            <div class="brand-logo-img">
                                <img src="assets/UCC_Logo.png" alt="UCC Logo">
                            </div>
                        </div>
                        <h1 class="brand-title">UCC Inventory<br>Management System</h1>
                        <p class="brand-subtitle">Laboratory Equipment Management</p>
                        
                        <ul class="brand-features">
                            <li>
                                <i class="fas fa-check-circle"></i>
                                <span>Real-time inventory tracking</span>
                            </li>
                            <li>
                                <i class="fas fa-check-circle"></i>
                                <span>Equipment assignment management</span>
                            </li>
                            <li>
                                <i class="fas fa-check-circle"></i>
                                <span>Maintenance scheduling</span>
                            </li>
                            <li>
                                <i class="fas fa-check-circle"></i>
                                <span>Comprehensive reporting</span>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <!-- Right Side - Login Form -->
                <div class="login-form-side">
                    <!-- Welcome Text -->
                    <div class="welcome-text">
                        <h3>Welcome Back!</h3>
                        <p>Sign in to access your dashboard</p>
                        <div class="role-badge">
                            <i class="fas fa-shield-alt"></i>
                            Role-Based Access Control
                        </div>
                    </div>
                    
                    <!-- Alert Messages -->
                    <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Login Form -->
                    <form action="auth/login.php" method="POST" id="loginForm">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-envelope"></i>
                            </span>
                            <input type="email" class="form-control" name="email" 
                                   placeholder="Email address" required 
                                   value="<?php echo isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : ''; ?>">
                        </div>
                        
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" class="form-control" name="password" 
                                   id="password" placeholder="Password" required>
                            <span class="input-group-text" id="togglePassword">
                                <i class="fas fa-eye" id="eyeIcon"></i>
                            </span>
                        </div>
                        
                        <button type="submit" class="btn-login" id="loginBtn">
                            <i class="fas fa-sign-in-alt"></i>
                            Sign In to Dashboard
                        </button>
                        
                        <div class="forgot-link">
                            <a href="#" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal">
                                <i class="fas fa-key me-1"></i>
                                Forgot your password?
                            </a>
                        </div>
                    </form>
                    
                    <!-- Register Section -->
                    <div class="register-section">
                        <p>New to UCC Inventory System?</p>
                        <a href="auth/register.php" class="btn-register">
                            <i class="fas fa-user-plus"></i>
                            Create an Account
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-key me-2"></i>
                        Reset Password
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <div class="dev-icon bg-success mx-auto mb-3" style="width: 70px; height: 70px;">
                            <i class="fas fa-lock fa-2x text-white"></i>
                        </div>
                        <h6 class="fw-bold fs-5">Forgot Your Password?</h6>
                        <p class="text-muted">Enter your email and we'll send reset instructions</p>
                    </div>
                    <form action="auth/forgot_password.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Email Address</label>
                            <input type="email" class="form-control form-control-lg" 
                                   name="reset_email" placeholder="name@example.com" required>
                        </div>
                        <button type="submit" class="btn-login">
                            <i class="fas fa-paper-plane me-2"></i>
                            Send Reset Link
                        </button>
                    </form>
                </div>
                <div class="modal-footer justify-content-center">
                    <small class="text-muted">
                        Remember your password? 
                        <a href="#" class="text-decoration-none text-success fw-bold" data-bs-dismiss="modal">
                            Back to Login
                        </a>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Developer Modal -->
    <div class="modal fade" id="developerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-laptop-code me-2"></i>
                        Development Team
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="list-group list-group-flush">
                        <div class="list-group-item d-flex align-items-center border-0 py-3">
                            <div class="rounded-circle overflow-hidden me-4" style="width: 80px; height: 80px; border: 3px solid #2E7D32; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                                <img src="assets/matt.jpg" alt="Ryan Mateo" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='assets/default-avatar.png'">
                            </div>
                            <div>
                                <h6 class="mb-1 fw-bold fs-5">Ryan Mateo</h6>
                                <small class="text-muted fs-6">System Analyst / Project Manager</small>
                            </div>
                        </div>
                        <div class="list-group-item d-flex align-items-center border-0 py-3">
                            <div class="rounded-circle overflow-hidden me-4" style="width: 80px; height: 80px; border: 3px solid #2E7D32; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                                <img src="assets/greg.png" alt="James Ryan Gregorio" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='assets/default-avatar.png'">
                            </div>
                            <div>
                                <h6 class="mb-1 fw-bold fs-5">James Ryan Gregorio</h6>
                                <small class="text-muted fs-6">Full Stack Developer</small>
                            </div>
                        </div>
                        <div class="list-group-item d-flex align-items-center border-0 py-3">
                            <div class="rounded-circle overflow-hidden me-4" style="width: 80px; height: 80px; border: 3px solid #4FC3F7; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                                <img src="assets/ureta.png" alt="Jan Ermaine Ureta" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='assets/default-avatar.png'">
                            </div>
                            <div>
                                <h6 class="mb-1 fw-bold fs-5">Jan Ermaine Ureta</h6>
                                <small class="text-muted fs-6">UI/UX Developer</small>
                            </div>
                        </div>
                        <div class="list-group-item d-flex align-items-center border-0 py-3">
                            <div class="rounded-circle overflow-hidden me-4" style="width: 80px; height: 80px; border: 3px solid #2E7D32; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                                <img src="assets/renz.jpg" alt="Renzel Rodriguez" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='assets/default-avatar.png'">
                            </div>
                            <div>
                                <h6 class="mb-1 fw-bold fs-5">Renzel Rodriguez</h6>
                                <small class="text-muted fs-6">Full Stack Developer</small>
                            </div>
                        </div>
                        <div class="list-group-item d-flex align-items-center border-0 py-3">
                            <div class="rounded-circle overflow-hidden me-4" style="width: 80px; height: 80px; border: 3px solid #FFB74D; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                                <img src="assets/ian.jpg" alt="Iankyron Chan" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='assets/default-avatar.png'">
                            </div>
                            <div>
                                <h6 class="mb-1 fw-bold fs-5">Iankyron Chan</h6>
                                <small class="text-muted fs-6">Backend Developer / DBA</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer Copyright -->
    <div class="footer-copyright" data-bs-toggle="modal" data-bs-target="#developerModal">
        <i class="fas fa-code me-1"></i>
        &copy; <?php echo date('Y'); ?> UCC Labtech
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            const loginBtn = document.getElementById('loginBtn');
            const emailInput = document.querySelector('input[name="email"]');
            const passwordInput = document.getElementById('password');
            const togglePassword = document.getElementById('togglePassword');
            const eyeIcon = document.getElementById('eyeIcon');
            
            // Toggle password visibility
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                eyeIcon.classList.toggle('fa-eye');
                eyeIcon.classList.toggle('fa-eye-slash');
            });

            // Form submission with loading state
            if(loginForm) {
                loginForm.addEventListener('submit', function() {
                    loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Signing In...';
                    loginBtn.disabled = true;
                });
            }
            
            // Auto-focus email field
            emailInput.focus();
            
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                document.querySelectorAll('.alert').forEach(function(alert) {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.remove();
                    }, 500);
                });
            }, 5000);
            
            // Add floating effect to logo
            const brandLogo = document.querySelector('.brand-logo-img');
            if(brandLogo) {
                setInterval(function() {
                    brandLogo.style.transform = 'scale(1.02)';
                    setTimeout(function() {
                        brandLogo.style.transform = 'scale(1)';
                    }, 200);
                }, 3000);
            }
        });

        // Debug developer images
        const devImages = ['matt.jpg', 'greg.png', 'ureta.png', 'renzel_rodriguez.jpg', 'iankyron_chan.jpg', 'placeholder.png'];
        devImages.forEach(img => {
            const devImage = new Image();
            devImage.src = `assets/${img}`;
            devImage.onload = function() {
                console.log(`✅ Developer image loaded successfully: ${img}`);
            };
            devImage.onerror = function() {
                console.log(`ℹ️ Using placeholder for: ${img}`);
            };
        });
    </script>
</body>
</html>