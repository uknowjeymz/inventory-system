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

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';

$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot_password'])) {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = "Please enter your email address";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        // Check if email exists
        $stmt = $db->prepare("SELECT id, full_name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Generate unique token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store token in database
            $insert = $db->prepare("INSERT INTO password_resets (user_id, token, expires_at, created_at) VALUES (?, ?, ?, NOW())");
            $insert->execute([$user['id'], $token, $expires]);
            
            // Send email with reset link
            $reset_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                          "://$_SERVER[HTTP_HOST]/consumable/reset_password.php?token=" . $token;
            
            $mail = new PHPMailer(true);
            
            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'ucca91445@gmail.com';
                $mail->Password   = 'bsgt xoeq qdhb apdp';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                
                // Recipients
                $mail->setFrom('noreply@ucc.edu.ph', 'UCC Consumable System');
                $mail->addAddress($email, $user['full_name']);
                
                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Request - UCC Consumable System';
                
                $mail->Body = "
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        body { font-family: 'Inter', sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                        .content { background: #f8f9fa; padding: 30px; border: 2px solid #e9ecef; border-top: none; border-radius: 0 0 10px 10px; }
                        .button { background: #0d6efd; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: 600; margin: 20px 0; }
                        .button:hover { background: #0b5ed7; }
                        .footer { margin-top: 20px; font-size: 12px; color: #6c757d; text-align: center; }
                        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>University of Caloocan City</h2>
                            <p>Consumable Management System</p>
                        </div>
                        <div class='content'>
                            <h3>Hello, " . htmlspecialchars($user['full_name']) . "!</h3>
                            <p>We received a request to reset your password for your UCC Consumable System account.</p>
                            
                            <div style='text-align: center;'>
                                <a href='$reset_link' class='button'>Reset Password</a>
                            </div>
                            
                            <p><strong>This link will expire in 1 hour.</strong></p>
                            
                            <div class='warning'>
                                <strong>⚠️ Security Notice:</strong>
                                <p style='margin-top: 5px;'>If you did not request a password reset, please ignore this email or contact the system administrator if you have concerns.</p>
                            </div>
                            
                            <p style='margin-top: 20px;'>If the button doesn't work, copy and paste this link into your browser:</p>
                            <p style='font-size: 12px; word-break: break-all; background: #e9ecef; padding: 10px; border-radius: 5px;'>$reset_link</p>
                        </div>
                        <div class='footer'>
                            <p>This is an automated message. Please do not reply to this email.</p>
                            <p>&copy; " . date('Y') . " University of Caloocan City. All rights reserved.</p>
                        </div>
                    </div>
                </body>
                </html>
                ";
                
                $mail->AltBody = "Hello " . $user['full_name'] . ",\n\n" .
                                 "We received a request to reset your password for your UCC Consumable System account.\n\n" .
                                 "Click this link to reset your password: $reset_link\n\n" .
                                 "This link will expire in 1 hour.\n\n" .
                                 "If you did not request this, please ignore this email.\n\n" .
                                 "Thank you,\nUCC Consumable System";
                
                $mail->send();
                $message = "Password reset instructions have been sent to your email address.";
            } catch (Exception $e) {
                error_log("Mailer Error: " . $mail->ErrorInfo);
                $error = "Failed to send email. Please try again later or contact administrator.";
            }
        } else {
            // Don't reveal that email doesn't exist (security)
            $message = "If your email exists in our system, you will receive password reset instructions.";
        }
    }
}

$page_title = "Forgot Password - Consumable System";
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
        
        .forgot-container {
            width: 100%;
            max-width: 450px;
            margin: auto;
            position: relative;
            z-index: 1;
        }
        
        .forgot-card {
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
        
        .btn-back {
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
        
        .btn-back:hover {
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
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }
        
        .info-box {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            margin-top: 20px;
            font-size: 0.85rem;
            color: #6c757d;
            border: 1px solid #e9ecef;
        }
        
        .info-box i {
            color: #0d6efd;
            margin-right: 8px;
        }
        
        /* Mobile optimizations */
        @media (max-width: 480px) {
            .forgot-card {
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
            
            .btn-reset, .btn-back {
                padding: 12px;
            }
        }
        
        /* Touch-friendly inputs */
        @media (hover: none) and (pointer: coarse) {
            .form-control, .btn-reset, .btn-back {
                min-height: 48px;
            }
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="forgot-card">
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
                <h2>Forgot Password?</h2>
                <p>Enter your email to reset your password</p>
            </div>
            
            <!-- Alert Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <!-- Forgot Password Form -->
            <form action="forgot_password.php" method="POST" id="forgotForm">
                <input type="hidden" name="forgot_password" value="1">
                
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
                               placeholder="Enter your registered email"
                               required 
                               autocomplete="email"
                               autofocus>
                    </div>
                </div>
                
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <strong>Note:</strong> A password reset link will be sent to your email address. The link will expire in 1 hour.
                </div>
                
                <button type="submit" class="btn-reset">
                    <i class="fas fa-paper-plane me-2"></i>Send Reset Link
                </button>
            </form>
            
            <!-- Back to Login -->
            <a href="login.php" class="btn-back">
                <i class="fas fa-arrow-left me-2"></i>Back to Login
            </a>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Form validation
        document.getElementById('forgotForm').addEventListener('submit', function(e) {
            const email = document.querySelector('input[name="email"]').value;
            
            if (!email) {
                e.preventDefault();
                alert('Please enter your email address');
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
            document.querySelectorAll('.btn-reset, .btn-back').forEach(btn => {
                btn.addEventListener('touchstart', function() {
                    this.style.opacity = '0.8';
                });
                btn.addEventListener('touchend', function() {
                    this.style.opacity = '1';
                });
            });
        }
    </script>
</body>
</html>