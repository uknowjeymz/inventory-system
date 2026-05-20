<?php
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    if ($_SESSION['user_type'] === 'admin') {
        header("Location: ../admin/consumables.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}

$page_title = "Login - Consumable System";
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
            background-image: url('../consumable/assets/UCC_Background.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            z-index: -2;
        }
        
        /* Dark overlay - removed filter from background and applied here */
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
        
        .login-container {
            width: 100%;
            max-width: 450px;
            margin: auto;
            position: relative;
            z-index: 1;
        }
        
        .login-card {
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
            margin-bottom: 25px;
        }
        
        .logo-section img {
            width: 90px;
            height: auto;
            margin-bottom: 15px;
            background: white;
            padding: 10px;
            border-radius: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .logo-section h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1e3c72;
            margin-bottom: 5px;
            line-height: 1.2;
        }
        
        .logo-section .subtitle {
            font-size: 1rem;
            color: #4a5568;
            font-weight: 500;
            letter-spacing: 1px;
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
            font-size: 1.5rem;
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
        
        .btn-login {
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
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(13, 110, 253, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .create-account {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
        }
        
        .create-account p {
            color: #718096;
            margin-bottom: 10px;
            font-size: 0.9rem;
        }
        
        .btn-create {
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
        }
        
        .btn-create:hover {
            background: #0d6efd;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13, 110, 253, 0.2);
        }
        
        .btn-create:active {
            transform: translateY(0);
        }
        
        .alert {
            border-radius: 15px;
            padding: 12px 15px;
            margin-bottom: 20px;
            border: none;
            font-size: 0.9rem;
        }
        
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .alert-success {
            background: #dcfce7;
            color: #166534;
        }
        
        .forgot-password {
            text-align: right;
            margin-top: 5px;
        }
        
        .forgot-password a {
            color: #718096;
            font-size: 0.85rem;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .forgot-password a:hover {
            color: #0d6efd;
        }
        
        /* Developer Section */
        .developer-section {
            text-align: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px dashed #e2e8f0;
        }
        
        .developer-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #718096;
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            cursor: pointer;
            padding: 8px 15px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.5);
        }
        
        .developer-link i {
            font-size: 1rem;
            transition: transform 0.3s ease;
        }
        
        .developer-link:hover {
            color: #0d6efd;
            background: rgba(13, 110, 253, 0.1);
        }
        
        .developer-link:hover i {
            transform: rotate(360deg);
        }
        
        /* Developer Modal */
        .modal-content {
            border-radius: 25px;
            border: none;
            overflow: hidden;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            color: white;
            border-bottom: none;
            padding: 20px;
        }
        
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
            opacity: 0.8;
        }
        
        .modal-header .btn-close:hover {
            opacity: 1;
        }
        
        .modal-title {
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-body {
            padding: 30px;
        }
        
        .developers-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .developer-card {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 15px;
            transition: all 0.3s ease;
        }
        
        .developer-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(13, 110, 253, 0.2);
        }
        
        .developer-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #fff;
            box-shadow: 0 5px 15px rgba(13, 110, 253, 0.3);
            margin-bottom: 15px;
        }
        
        .developer-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1e3c72;
            margin-bottom: 5px;
        }
        
        .developer-role {
            font-size: 0.85rem;
            color: #0d6efd;
            font-weight: 500;
            padding: 5px 10px;
            background: rgba(13, 110, 253, 0.1);
            border-radius: 20px;
            display: inline-block;
        }
        
        .developer-role i {
            margin-right: 5px;
            font-size: 0.8rem;
        }
        
        .team-section {
            margin-top: 30px;
            text-align: center;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 20px;
            border-radius: 15px;
        }
        
        .team-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e3c72;
            margin-bottom: 10px;
        }
        
        .team-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: white;
            padding: 8px 20px;
            border-radius: 30px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }
        
        .team-badge i {
            color: #0d6efd;
        }
        
        .modal-footer {
            border-top: 2px solid #e2e8f0;
            padding: 15px 20px;
            background: #f8f9fa;
        }
        
        .btn-close-modal {
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-close-modal:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13, 110, 253, 0.3);
        }
        
        /* Mobile optimizations */
        @media (max-width: 480px) {
            .login-card {
                padding: 20px 15px;
            }
            
            .logo-section img {
                width: 70px;
            }
            
            .logo-section h1 {
                font-size: 1.5rem;
            }
            
            .logo-section .subtitle {
                font-size: 0.9rem;
            }
            
            .welcome-text h2 {
                font-size: 1.3rem;
            }
            
            .form-control {
                padding: 10px 12px;
            }
            
            .btn-login, .btn-create {
                padding: 12px;
            }
            
            .developers-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .developer-avatar {
                width: 100px;
                height: 100px;
            }
        }
        
        /* Touch-friendly inputs */
        @media (hover: none) and (pointer: coarse) {
            .form-control, .btn-login, .btn-create, .password-toggle, .developer-link {
                min-height: 48px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <!-- Logo and Title Section -->
            <div class="logo-section">
                <img src="../consumable/assets/UCC_Logo.png" alt="UCC Logo" onerror="this.src='https://via.placeholder.com/90?text=UCC'">
                <h1>University of Caloocan City</h1>
                <div class="subtitle">
                    <span>Consumable</span> Management System
                </div>
            </div>
            
            <!-- Welcome Message -->
            <div class="welcome-text">
                <h2>Welcome Back!</h2>
                <p>Please login to your account</p>
            </div>
            
            <!-- Login Form -->
            <form action="authenticate.php" method="POST" id="loginForm">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-envelope me-2"></i>Email Address
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-envelope"></i>
                        </span>
                        <input type="email" 
                               class="form-control" 
                               name="email" 
                               placeholder="Enter your email"
                               required 
                               autocomplete="email"
                               autofocus>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-lock me-2"></i>Password
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" 
                               class="form-control" 
                               name="password" 
                               id="password"
                               placeholder="Enter your password"
                               required 
                               autocomplete="current-password">
                        <span class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </span>
                    </div>
                </div>
                
                <div class="forgot-password">
                    <a href="forgot_password.php">Forgot Password?</a>
                </div>
                
                <button type="submit" class="btn btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </button>
            </form>
            
            <!-- Create Account Section -->
            <div class="create-account">
                <p>Don't have an account yet?</p>
                <a href="register.php" class="btn-create">
                    <i class="fas fa-user-plus me-2"></i>Create Account
                </a>
            </div>
            
            <!-- Developer Section -->
            <div class="developer-section">
                <a class="developer-link" data-bs-toggle="modal" data-bs-target="#developerModal">
                    <i class="fas fa-code"></i>
                    <span>Development Team</span>
                    <i class="fas fa-chevron-right"></i>
                </a>
            </div>
            
            <!-- System Info -->
            <div class="text-center mt-3">
                <small class="text-muted">
                    <i class="fas fa-shield-alt me-1"></i>Secure System • v1.0
                </small>
            </div>
        </div>
    </div>
    
    <!-- Developer Modal -->
    <div class="modal fade" id="developerModal" tabindex="-1" aria-labelledby="developerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="developerModalLabel">
                        <i class="fas fa-code"></i>
                        Development Team
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="developers-grid">
                        <!-- Ryan Mateo - Project Manager -->
                        <div class="developer-card">
                            <img src="../consumable/assets/matt.jpg" alt="Ryan Mateo" class="developer-avatar" onerror="this.src='https://via.placeholder.com/120?text=RM'">
                            <h3 class="developer-name">Ryan Mateo</h3>
                            <div class="developer-role">
                                <i class="fas fa-tasks"></i> Project Manager
                            </div>
                        </div>
                        
                        <!-- James Ryan Gregorio -->
                        <div class="developer-card">
                            <img src="../consumable/assets/greg.png" alt="James Ryan Gregorio" class="developer-avatar" onerror="this.src='https://via.placeholder.com/120?text=JG'">
                            <h3 class="developer-name">James Ryan Gregorio</h3>
                            <div class="developer-role">
                                <i class="fas fa-crown"></i> Full Stack Developer
                            </div>
                        </div>
                        
                        <!-- Jan Ermaine Ureta -->
                        <div class="developer-card">
                            <img src="../consumable/assets/ureta.png" alt="Jan Ermaine Ureta" class="developer-avatar" onerror="this.src='https://via.placeholder.com/120?text=JU'">
                            <h3 class="developer-name">Jan Ermaine Ureta</h3>
                            <div class="developer-role">
                                <i class="fas fa-paint-brush"></i> Front-end Developer
                            </div>
                        </div>
                        
                        <!-- Renzel Rodriguez -->
                        <div class="developer-card">
                            <img src="../consumable/assets/renz.jpg" alt="Renzel Rodriguez" class="developer-avatar" onerror="this.src='https://via.placeholder.com/120?text=RR'">
                            <h3 class="developer-name">Renzel Rodriguez</h3>
                            <div class="developer-role">
                                <i class="fas fa-laptop-code"></i> Full Stack Developer
                            </div>
                        </div>
                        
                        <!-- Iankyron Chan -->
                        <div class="developer-card">
                            <img src="../consumable/assets/ian.jpg" alt="Iankyron Chan" class="developer-avatar" onerror="this.src='https://via.placeholder.com/120?text=IC'">
                            <h3 class="developer-name">Iankyron Chan</h3>
                            <div class="developer-role">
                                <i class="fas fa-database"></i> Backend Developer / DBA
                            </div>
                        </div>
                    </div>
                    
                    <div class="team-section">
                        <div class="team-title">
                            <i class="fas fa-users me-2"></i>UCC LabTech Development Team
                        </div>
                        <div class="team-badge">
                            <i class="fas fa-check-circle"></i>
                            <span>Consumable Management System v1.0</span>
                        </div>
                        <p class="text-muted mt-3 mb-0 small">
                            <i class="fas fa-calendar-alt me-1"></i> February 2026
                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-close-modal" data-bs-dismiss="modal">
                        <i class="fas fa-check-circle me-2"></i>Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
        
        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.querySelector('input[name="email"]').value;
            const password = document.querySelector('input[name="password"]').value;
            
            if (!email || !password) {
                e.preventDefault();
                alert('Please fill in all fields');
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
        
        // Add touch-friendly feedback
        if ('ontouchstart' in window) {
            document.querySelectorAll('.btn-login, .btn-create, .password-toggle, .developer-link').forEach(btn => {
                btn.addEventListener('touchstart', function() {
                    this.style.opacity = '0.8';
                });
                btn.addEventListener('touchend', function() {
                    this.style.opacity = '1';
                });
            });
        }
        
        // Debug background image
        window.addEventListener('load', function() {
            const bgImage = new Image();
            bgImage.src = '../consumable/assets/UCC_Background.png';
            bgImage.onload = function() {
                console.log('✅ Background image loaded successfully: UCC_Background.png');
            };
            bgImage.onerror = function() {
                console.error('❌ Failed to load background image: UCC_Background.png');
                console.log('Please check:');
                console.log('1. File exists at: ../assets/UCC_Background.png');
                console.log('2. File permissions');
                console.log('3. File name case sensitivity (UCC_Background.png)');
                
                // Apply fallback gradient
                document.body.style.background = 'linear-gradient(135deg, #1e3c72 0%, #2a5298 100%)';
            };
            
            // Debug developer images
            const devImages = ['greg.png', 'ureta.png', 'placeholder.png'];
            devImages.forEach(img => {
                const devImage = new Image();
                devImage.src = `../consumable/assets/${img}`;
                devImage.onload = function() {
                    console.log(`✅ Developer image loaded successfully: ${img}`);
                };
                devImage.onerror = function() {
                    console.log(`ℹ️ Using placeholder for: ${img}`);
                };
            });
        });
    </script>
</body>
</html>