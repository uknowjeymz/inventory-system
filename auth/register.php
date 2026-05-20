<?php
session_start();
require_once '../config/database.php';

$error = '';
$success = '';

if ($_POST) {
    $database = new Database();
    $db = $database->getConnection();
    
    $email = trim($_POST['email']);
    $entered_code = $_POST['verification_code'];
    $password = $_POST['password'];
    $full_name = trim($_POST['full_name']);
    $department = trim($_POST['department'] ?? '');
    $campus = trim($_POST['campus'] ?? ''); // Add campus field
    $phone = trim($_POST['phone'] ?? '');
    
    // Server-side validation of OTP
    if (!isset($_SESSION['otp']) || $entered_code != $_SESSION['otp']) {
        $error = "Verification failed. Please get a new code.";
    } elseif ($_SESSION['otp_email'] !== $email) {
        $error = "Email mismatch. Please verify your email again.";
    } else {
        // Check if email exists
        $query = "SELECT id FROM users WHERE email = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            $error = "Email already registered!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Update query to include campus
            $query = "INSERT INTO users (email, password, role, full_name, department, campus, phone) VALUES (?, ?, 'admin', ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            
            if ($stmt->execute([$email, $hashed_password, $full_name, $department, $campus, $phone])) {
                unset($_SESSION['otp']);
                unset($_SESSION['otp_email']);
                $_SESSION['success'] = "Account created! Welcome to the UCC Inventory Management System.";
                header("Location: ../index.php");
                exit();
            } else {
                $error = "Registration failed!";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - UCC Inventory Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="../assets/UCC_Logo.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary-green: #2E7D32;
            --secondary-green: #4CAF50;
            --light-green: #81C784;
            --soft-green: #E8F5E9;
            --mint-green: #C8E6C9;
            --dark-green: #1B5E20;
            --custom-green: #5eaf62;
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
        }

        /* Background Image with Custom Green Overlay */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('../assets/UCC_Background.png');
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
                rgba(94, 175, 98, 0.7) 0%,
                rgba(94, 175, 98, 0.5) 50%,
                rgba(94, 175, 98, 0.3) 100%);
            backdrop-filter: blur(3px);
            z-index: -1;
        }

        /* Main Container */
        .register-container {
            min-height: 100vh;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            position: relative;
        }

        /* Register Card - Wider for Desktop */
        .register-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            box-shadow: 
                0 30px 80px rgba(46, 125, 50, 0.4),
                0 15px 40px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.6);
            overflow: hidden;
            max-width: 1200px; /* Even wider for registration form */
            width: 95%;
            animation: slideUp 0.6s ease-out;
        }

        /* Split Layout for Desktop */
        .register-split {
            display: flex;
            min-height: 700px; /* Taller for registration form */
        }

        /* Left Side - Branding/Info */
        .register-brand {
            flex: 1;
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--custom-green) 100%);
            padding: 3rem 2.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .register-brand::before {
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
            transition: all 0.3s ease;
        }

        .brand-logo-img:hover {
            transform: scale(1.05) rotate(5deg);
            box-shadow: 0 20px 50px rgba(76, 175, 80, 0.4);
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

        /* Right Side - Registration Form */
        .register-form-side {
            flex: 1.2; /* Slightly larger for form */
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

        /* Progress Steps */
        .progress-steps {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 2rem;
            gap: 0.5rem;
        }

        .step-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            max-width: 100px;
        }

        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--soft-green);
            border: 2px solid var(--mint-green);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark-green);
            font-weight: 700;
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }

        .step-circle.active {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--custom-green) 100%);
            border-color: var(--pure-white);
            color: white;
            box-shadow: 0 5px 15px rgba(94, 175, 98, 0.3);
        }

        .step-circle.completed {
            background: var(--custom-green);
            border-color: var(--custom-green);
            color: white;
        }

        .step-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--dark-green);
            text-transform: uppercase;
            letter-spacing: 0.5px;
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

        /* Form Elements */
        .form-group {
            margin-bottom: 1.2rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--dark-green);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-label i {
            color: var(--custom-green);
            font-size: 0.9rem;
        }

        .input-group-custom {
            display: flex;
            align-items: center;
            background: var(--pure-white);
            border: 2px solid var(--mint-green);
            border-radius: 16px;
            padding: 0.3rem 0.3rem 0.3rem 1rem;
            transition: all 0.3s ease;
        }

        .input-group-custom:focus-within {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 4px rgba(46, 125, 50, 0.1);
            transform: translateY(-2px);
        }

        .input-group-custom i {
            color: var(--primary-green);
            font-size: 1rem;
            width: 20px;
        }

        .input-group-custom input {
            border: none;
            outline: none;
            padding: 0.8rem 0.8rem 0.8rem 0.5rem;
            width: 100%;
            font-size: 0.95rem;
            font-weight: 500;
            color: #1F2937;
            background: transparent;
        }

        .input-group-custom input::placeholder {
            color: #9CA3AF;
            font-weight: 400;
        }

        .input-group-custom input:read-only {
            background: var(--soft-green);
            color: var(--dark-green);
            font-weight: 600;
            border-radius: 12px;
        }

        /* Get Code Button */
        .btn-get-code {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--custom-green) 100%);
            color: white;
            border: none;
            padding: 0.5rem 1.2rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            white-space: nowrap;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(94, 175, 98, 0.2);
        }

        .btn-get-code:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(94, 175, 98, 0.3);
        }

        .btn-get-code:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        /* Code Status */
        .code-status {
            margin-left: 0.5rem;
            font-size: 1.1rem;
        }

        .code-status .valid {
            color: var(--custom-green);
        }

        .code-status .invalid {
            color: #DC3545;
        }

        /* Verification Hint */
        .verification-hint {
            font-size: 0.75rem;
            color: #6B7280;
            margin-top: 0.3rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .verification-hint i {
            color: var(--custom-green);
            font-size: 0.7rem;
        }

        /* Disabled Group */
        .disabled-group {
            opacity: 0.6;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }

        .disabled-group.active {
            opacity: 1;
            pointer-events: all;
        }

        /* Terms Checkbox */
        .terms-check {
            margin: 1.5rem 0;
        }

        .form-check-input {
            width: 1.2rem;
            height: 1.2rem;
            border: 2px solid var(--mint-green);
            border-radius: 6px;
            cursor: pointer;
        }

        .form-check-input:checked {
            background-color: var(--custom-green);
            border-color: var(--custom-green);
        }

        .form-check-label {
            color: #4B5563;
            font-size: 0.9rem;
            cursor: pointer;
        }

        .form-check-label a {
            color: var(--primary-green);
            font-weight: 600;
            text-decoration: none;
        }

        .form-check-label a:hover {
            text-decoration: underline;
        }

        /* Register Button */
        .btn-register {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--custom-green) 100%);
            color: white;
            border: none;
            padding: 1.2rem;
            font-weight: 700;
            font-size: 1.1rem;
            border-radius: 18px;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 15px 30px rgba(94, 175, 98, 0.3);
        }

        .btn-register:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 20px 40px rgba(94, 175, 98, 0.4);
            background: linear-gradient(135deg, var(--custom-green) 0%, var(--primary-green) 100%);
        }

        .btn-register:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Alert Messages */
        .alert {
            border-radius: 16px;
            border: none;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
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

        /* Login Link */
        .login-link {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 2px dashed var(--mint-green);
        }

        .login-link p {
            color: #6B7280;
            font-size: 0.95rem;
            margin-bottom: 0;
        }

        .login-link a {
            color: var(--primary-green);
            font-weight: 700;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .login-link a:hover {
            color: var(--dark-green);
            text-decoration: underline;
        }

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

        /* Responsive Design */
        @media (max-width: 992px) {
            .register-split {
                flex-direction: column;
                min-height: auto;
            }
            
            .register-brand {
                padding: 2.5rem;
            }
            
            .register-form-side {
                padding: 2.5rem;
            }
            
            .register-card {
                max-width: 700px;
            }
        }

        @media (max-width: 576px) {
            .register-container {
                padding: 1rem;
            }
            
            .register-form-side {
                padding: 2rem;
            }
            
            .brand-title {
                font-size: 1.8rem;
            }
            
            .welcome-text h3 {
                font-size: 1.6rem;
            }
            
            .progress-steps {
                gap: 0.2rem;
            }
            
            .step-circle {
                width: 35px;
                height: 35px;
                font-size: 1rem;
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
    <div class="register-container">
        <div class="register-card">
            <div class="register-split">
                <!-- Left Side - Branding -->
                <div class="register-brand">
                    <div class="brand-content">
                        <div class="brand-logo">
                            <div class="brand-logo-img">
                                <img src="../assets/UCC_Logo.png" alt="UCC Logo">
                            </div>
                        </div>
                        <h1 class="brand-title">Join UCC<br>Inventory System</h1>
                        <p class="brand-subtitle">Create your account in minutes</p>
                        
                        <ul class="brand-features">
                            <li>
                                <i class="fas fa-check-circle"></i>
                                <span>Track laboratory equipment</span>
                            </li>
                            <li>
                                <i class="fas fa-check-circle"></i>
                                <span>Manage assignments & returns</span>
                            </li>
                            <li>
                                <i class="fas fa-check-circle"></i>
                                <span>Receive maintenance alerts</span>
                            </li>
                            <li>
                                <i class="fas fa-check-circle"></i>
                                <span>Generate reports & analytics</span>
                            </li>
                            <li>
                                <i class="fas fa-check-circle"></i>
                                <span>Role-based access control</span>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <!-- Right Side - Registration Form -->
                <div class="register-form-side">
                    <!-- Progress Steps -->
                    <div class="progress-steps">
                        <div class="step-item">
                            <div class="step-circle active" id="step1">1</div>
                            <span class="step-label">Email</span>
                        </div>
                        <div class="step-item">
                            <div class="step-circle" id="step2">2</div>
                            <span class="step-label">Verify</span>
                        </div>
                        <div class="step-item">
                            <div class="step-circle" id="step3">3</div>
                            <span class="step-label">Details</span>
                        </div>
                    </div>

                    <!-- Welcome Text -->
                    <div class="welcome-text">
                        <h3>Create Account</h3>
                        <p>Fill in your details to get started</p>
                    </div>
                    
                    <!-- Alert Messages -->
                    <?php if($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Registration Form -->
                    <form method="POST" id="registrationForm">
                        <!-- Step 1: Email -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-envelope"></i>
                                Email Address
                            </label>
                            <div class="input-group-custom">
                                <i class="fas fa-envelope"></i>
                                <input type="email" name="email" id="email" 
                                       placeholder="your.name@ucc.edu.ph" required
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                <button class="btn-get-code" type="button" id="btnGetCode">
                                    <i class="fas fa-paper-plane me-1"></i> Get Code
                                </button>
                            </div>
                        </div>

                        <!-- Step 2: Verification Code (Hidden by default) -->
                        <div id="verificationSection" style="display: none;">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-key"></i>
                                    Verification Code
                                </label>
                                <div class="input-group-custom">
                                    <i class="fas fa-key"></i>
                                    <input type="text" name="verification_code" id="vCode" 
                                           placeholder="6-digit OTP" maxlength="6">
                                    <span class="code-status" id="codeStatus"></span>
                                </div>
                                <div class="verification-hint">
                                    <i class="fas fa-info-circle"></i>
                                    <span>Enter the 6-digit code sent to your email</span>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: Remaining Fields (Initially Disabled) -->
                        <div id="remainingFields" class="disabled-group">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-user"></i>
                                    Full Name
                                </label>
                                <div class="input-group-custom">
                                    <i class="fas fa-user"></i>
                                    <input type="text" name="full_name" 
                                           placeholder="e.g., Juan Dela Cruz" required
                                           value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-building"></i>
                                    Department <span class="text-danger">*</span>
                                </label>
                                <div class="input-group-custom">
                                    <i class="fas fa-building"></i>
                                    <select name="department" id="departmentSelect" class="form-select" required style="border: none; outline: none; padding: 0.8rem 0.8rem 0.8rem 0.5rem; width: 100%; font-size: 0.95rem; font-weight: 500; color: #1F2937; background: transparent;">
                                        <option value="">-- Select Department --</option>
                                        <?php
                                        // Fetch departments from database
                                        $database = new Database();
                                        $db = $database->getConnection();
                                        $dept_query = "SELECT department_name FROM departments WHERE is_active = 1 ORDER BY department_name ASC";
                                        $dept_stmt = $db->prepare($dept_query);
                                        $dept_stmt->execute();
                                        $departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        foreach ($departments as $dept):
                                        ?>
                                        <option value="<?php echo htmlspecialchars($dept['department_name']); ?>">
                                            <?php echo htmlspecialchars($dept['department_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-map-marker-alt"></i>
                                    Campus <span class="text-danger">*</span>
                                </label>
                                <div class="input-group-custom">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <select name="campus" id="campusSelect" class="form-select" required style="border: none; outline: none; padding: 0.8rem 0.8rem 0.8rem 0.5rem; width: 100%; font-size: 0.95rem; font-weight: 500; color: #1F2937; background: transparent;">
                                        <option value="">-- Select Campus --</option>
                                        <option value="South Campus">South Campus</option>
                                        <option value="Congressional Campus">Congressional Campus</option>
                                        <option value="Camarin Campus">Camarin Campus</option>
                                        <option value="Bagong Silang Campus">Bagong Silang Campus</option>
                                    </select>
                                </div>
                                <div class="verification-hint">
                                    <i class="fas fa-info-circle"></i>
                                    <span>Select your primary campus assignment</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-phone-alt"></i>
                                    Phone Number <span class="text-muted" style="font-size: 0.7rem; font-weight: normal;">(Optional)</span>
                                </label>
                                <div class="input-group-custom">
                                    <i class="fas fa-phone-alt"></i>
                                    <input type="text" name="phone" id="phoneInput"
                                        placeholder="e.g., 09123456789 (optional)"
                                        value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                                </div>
                                <div class="verification-hint">
                                    <i class="fas fa-info-circle"></i>
                                    <span>Phone number is optional but recommended for account recovery</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-lock"></i>
                                    Password
                                </label>
                                <div class="input-group-custom">
                                    <i class="fas fa-lock"></i>
                                    <input type="password" name="password" id="password" 
                                        placeholder="Minimum 6 characters" required>
                                    <i class="fas fa-eye-slash" id="togglePassword" style="cursor: pointer; margin-right: 15px; color: #9CA3AF;"></i>
                                </div>
                                <div class="verification-hint">
                                    <i class="fas fa-shield-alt"></i>
                                    <span>Use at least 6 characters for security</span>
                                </div>
                            </div>

                            <div class="terms-check">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="terms" required>
                                    <label class="form-check-label" for="terms">
                                        I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms & Conditions</a> 
                                        and <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Policy</a>
                                    </label>
                                </div>
                            </div>

                            <button type="submit" class="btn-register" id="submitBtn" disabled>
                                <i class="fas fa-user-plus"></i>
                                Create Account
                            </button>
                        </div>
                    </form>

                    <!-- Login Link -->
                    <div class="login-link">
                        <p>
                            Already have an account? 
                            <a href="../index.php">
                                Sign In <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Terms & Conditions Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-file-contract me-2"></i>
                        Terms & Conditions
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <h6 class="fw-bold mb-3">UCC Inventory Management System Terms of Use</h6>
                    <p class="text-muted small mb-3">Last updated: <?php echo date('F d, Y'); ?></p>
                    <div style="max-height: 300px; overflow-y: auto;">
                        <p class="small">By accessing and using the UCC Inventory Management System, you agree to comply with and be bound by the following terms and conditions:</p>
                        <ol class="small">
                            <li class="mb-2">You must provide accurate and complete information during registration.</li>
                            <li class="mb-2">You are responsible for maintaining the confidentiality of your account credentials.</li>
                            <li class="mb-2">The system is for official UCC laboratory equipment management purposes only.</li>
                            <li class="mb-2">Misuse of the system may result in account suspension or disciplinary action.</li>
                            <li class="mb-2">All equipment assignments must be properly documented and approved.</li>
                            <li class="mb-2">Users are responsible for equipment under their care and must report damages immediately.</li>
                            <li class="mb-2">The system administrators reserve the right to modify or terminate accounts as necessary.</li>
                        </ol>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">
                        <i class="fas fa-check me-2"></i>I Understand
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Privacy Policy Modal -->
    <div class="modal fade" id="privacyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-shield-alt me-2"></i>
                        Privacy Policy
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <h6 class="fw-bold mb-3">Data Privacy Notice</h6>
                    <p class="text-muted small mb-3">Effective Date: <?php echo date('F d, Y'); ?></p>
                    <div style="max-height: 300px; overflow-y: auto;">
                        <p class="small">Your privacy is important to us. This policy outlines how we collect, use, and protect your information:</p>
                        <ul class="small">
                            <li class="mb-2">We collect personal information (name, email, department, phone) for system access and communication.</li>
                            <li class="mb-2">Your data is stored securely and only accessible to authorized personnel.</li>
                            <li class="mb-2">We do not share your personal information with third parties without consent.</li>
                            <li class="mb-2">System usage may be logged for security and audit purposes.</li>
                            <li class="mb-2">You may request access to or deletion of your personal data.</li>
                            <li class="mb-2">By using this system, you consent to the collection and use of information as described.</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">
                        <i class="fas fa-check me-2"></i>I Agree
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer Copyright -->
    <div class="footer-copyright" data-bs-toggle="modal" data-bs-target="#developerModal">
        <i class="fas fa-code me-1"></i>
        &copy; <?php echo date('Y'); ?> UCC Labtech
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
                                <img src="../assets/matt.jpg" alt="Ryan Mateo" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='assets/default-avatar.png'">
                            </div>
                            <div>
                                <h6 class="mb-1 fw-bold fs-5">Ryan Mateo</h6>
                                <small class="text-muted fs-6">System Analyst / Project Manager</small>
                            </div>
                        </div>
                        <div class="list-group-item d-flex align-items-center border-0 py-3">
                            <div class="rounded-circle overflow-hidden me-4" style="width: 80px; height: 80px; border: 3px solid #2E7D32; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                                <img src="../assets/greg.png" alt="James Ryan Gregorio" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='assets/default-avatar.png'">
                            </div>
                            <div>
                                <h6 class="mb-1 fw-bold fs-5">James Ryan Gregorio</h6>
                                <small class="text-muted fs-6">Full Stack Developer</small>
                            </div>
                        </div>
                        <div class="list-group-item d-flex align-items-center border-0 py-3">
                            <div class="rounded-circle overflow-hidden me-4" style="width: 80px; height: 80px; border: 3px solid #4FC3F7; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                                <img src="../assets/ureta.png" alt="Jan Ermaine Ureta" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='assets/default-avatar.png'">
                            </div>
                            <div>
                                <h6 class="mb-1 fw-bold fs-5">Jan Ermaine Ureta</h6>
                                <small class="text-muted fs-6">UI/UX Developer</small>
                            </div>
                        </div>
                        <div class="list-group-item d-flex align-items-center border-0 py-3">
                            <div class="rounded-circle overflow-hidden me-4" style="width: 80px; height: 80px; border: 3px solid #2E7D32; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                                <img src="../assets/renz.jpg" alt="Renzel Rodriguez" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='assets/default-avatar.png'">
                            </div>
                            <div>
                                <h6 class="mb-1 fw-bold fs-5">Renzel Rodriguez</h6>
                                <small class="text-muted fs-6">Full Stack Developer</small>
                            </div>
                        </div>
                        <div class="list-group-item d-flex align-items-center border-0 py-3">
                            <div class="rounded-circle overflow-hidden me-4" style="width: 80px; height: 80px; border: 3px solid #FFB74D; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                                <img src="../assets/ian.jpg" alt="Iankyron Chan" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='assets/default-avatar.png'">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnGetCode = document.getElementById('btnGetCode');
    const vSection = document.getElementById('verificationSection');
    const remaining = document.getElementById('remainingFields');
    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');
    const step3 = document.getElementById('step3');
    const submitBtn = document.getElementById('submitBtn');
    const termsCheck = document.getElementById('terms');
    const emailInput = document.getElementById('email');
    const vCodeInput = document.getElementById('vCode');
    // Toggle Password Visibility
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');

    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function() {
            // Toggle the type attribute
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle the eye icon
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    }
    
    // Flag to prevent duplicate verification
    let isVerifying = false;
    
    // Get Code Button Handler
    btnGetCode.addEventListener('click', function() {
        const email = emailInput.value.trim();
        
        // Validate email
        if(!email) {
            Swal.fire({
                icon: 'error',
                title: 'Email Required',
                text: 'Please enter your email address.',
                confirmButtonColor: '#2E7D32'
            });
            return;
        }
        
        if(!email.includes('@') || !email.includes('.')) {
            Swal.fire({
                icon: 'error',
                title: 'Invalid Email',
                text: 'Please enter a valid email address.',
                confirmButtonColor: '#2E7D32'
            });
            return;
        }
        
        // Show loading state
        const originalText = btnGetCode.innerHTML;
        btnGetCode.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Sending...';
        btnGetCode.disabled = true;
        
        // Send OTP request
        fetch('send_otp.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'email=' + encodeURIComponent(email)
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                // Show verification section
                vSection.style.display = 'block';
                emailInput.readOnly = true;
                
                // Update button
                btnGetCode.innerHTML = '<i class="fas fa-check me-1"></i> Code Sent';
                btnGetCode.classList.add('btn-success');
                
                // Update steps
                step1.classList.remove('active');
                step1.classList.add('completed');
                step2.classList.add('active');
                
                // Auto-focus verification code
                vCodeInput.focus();
                
                // Show success message
                Swal.fire({
                    icon: 'success',
                    title: 'Verification Code Sent!',
                    text: data.message || 'Please check your email for the 6-digit OTP.',
                    timer: 3000,
                    showConfirmButton: false,
                    background: '#E8F5E9',
                    iconColor: '#2E7D32'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'Failed to send verification code.',
                    confirmButtonColor: '#2E7D32'
                });
                btnGetCode.innerHTML = originalText;
                btnGetCode.disabled = false;
            }
        })
        .catch(error => {
            console.error('Fetch Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Connection Error',
                text: 'Unable to connect to the server. Please check your connection and try again.',
                confirmButtonColor: '#2E7D32'
            });
            btnGetCode.innerHTML = originalText;
            btnGetCode.disabled = false;
        });
    });
    
    // Verification Code Input Handler - FIXED VERSION
    vCodeInput.addEventListener('input', function() {
        const code = this.value.trim();
        const status = document.getElementById('codeStatus');
        
        // Clear previous status if code is empty
        if (code.length === 0) {
            status.innerHTML = '';
            return;
        }
        
        // Only verify when we have exactly 6 digits and not already verifying
        if (code.length === 6 && !isVerifying) {
            isVerifying = true;
            status.innerHTML = '<i class="fas fa-spinner fa-spin text-warning"></i>';
            
            fetch('verify_code.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Cache-Control': 'no-cache, no-store, must-revalidate',
                    'Pragma': 'no-cache'
                },
                body: 'code=' + encodeURIComponent(code)
            })
            .then(response => response.json())
            .then(data => {
                isVerifying = false;
                
                if (data.valid) {
                    // Show success status
                    status.innerHTML = '<i class="fas fa-check-circle text-success"></i>';
                    
                    // Enable remaining fields
                    remaining.classList.remove('disabled-group');
                    remaining.classList.add('active');
                    
                    // Make code input readonly
                    vCodeInput.readOnly = true;
                    
                    // Update steps
                    step2.classList.remove('active');
                    step2.classList.add('completed');
                    step3.classList.add('active');
                    
                    // Auto-focus full name
                    const fullNameInput = document.querySelector('input[name="full_name"]');
                    if (fullNameInput) {
                        fullNameInput.focus();
                    }
                    
                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: 'Email Verified!',
                        text: 'Please complete your registration details.',
                        timer: 2000,
                        showConfirmButton: false,
                        background: '#E8F5E9',
                        iconColor: '#2E7D32'
                    });
                } else {
                    // Show invalid status
                    status.innerHTML = '<i class="fas fa-times-circle text-danger"></i>';
                    
                    // Keep fields disabled
                    remaining.classList.add('disabled-group');
                    remaining.classList.remove('active');
                    
                    // Shake the input
                    vCodeInput.style.animation = 'shake 0.5s';
                    setTimeout(() => {
                        vCodeInput.style.animation = '';
                    }, 500);
                    
                    // Show error message
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid Code',
                        text: data.message || 'The verification code you entered is incorrect. Please try again.',
                        confirmButtonColor: '#2E7D32'
                    });
                }
            })
            .catch(error => {
                console.error('Verification error:', error);
                isVerifying = false;
                status.innerHTML = '<i class="fas fa-exclamation-triangle text-danger"></i>';
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to verify code. Please try again.',
                    confirmButtonColor: '#2E7D32'
                });
            });
        } else if (code.length > 0 && code.length < 6) {
            // Show that we're waiting for more digits
            status.innerHTML = '<i class="fas fa-ellipsis-h text-muted"></i>';
            // Keep fields disabled while typing
            remaining.classList.add('disabled-group');
            remaining.classList.remove('active');
        } else {
            status.innerHTML = '';
        }
    });
    
    // Terms Checkbox Handler
    termsCheck.addEventListener('change', function() {
        updateSubmitButton();
    });
    
    // Check if all required fields are filled
    function checkAllFieldsFilled() {
        const fullName = document.querySelector('input[name="full_name"]');
        const password = document.querySelector('input[name="password"]');
        const department = document.querySelector('#departmentSelect');
        const campus = document.querySelector('#campusSelect');
        
        // Phone is NOT required, so don't check it
        return fullName && fullName.value.trim() !== '' && 
            password && password.value.trim().length >= 6 &&
            department && department.value !== '' &&
            campus && campus.value !== '';
    }
    
    function updateSubmitButton() {
        const allFieldsFilled = checkAllFieldsFilled();
        submitBtn.disabled = !(termsCheck.checked && allFieldsFilled && 
                               remaining.classList.contains('active'));
    }
    
    // Monitor field changes
    document.querySelectorAll('#remainingFields input, #remainingFields select').forEach(input => {
        input.addEventListener('input', updateSubmitButton);
        input.addEventListener('change', updateSubmitButton);
    });
    
    // Form submission handler
    document.getElementById('registrationForm').addEventListener('submit', function(e) {
        const password = document.querySelector('input[name="password"]').value;
        const department = document.querySelector('#departmentSelect').value;
        const campus = document.querySelector('#campusSelect').value;
        
        let errors = [];
        
        if(password.length < 6) {
            errors.push('Password must be at least 6 characters long.');
        }
        
        if(!department) {
            errors.push('Please select your department.');
        }
        
        if(!campus) {
            errors.push('Please select your campus.');
        }
        
        if(errors.length > 0) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                html: errors.join('<br>'),
                confirmButtonColor: '#2E7D32'
            });
            return;
        }
        
        // Show loading state
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Creating Account...';
        submitBtn.disabled = true;
    });
    
    // Phone number formatting - now optional
    const phoneInput = document.querySelector('input[name="phone"]');
    if (phoneInput) {
        phoneInput.addEventListener('input', function() {
            // Only format if user enters something
            if (this.value.trim() !== '') {
                let phone = this.value.replace(/\D/g, '');
                if(phone.length > 11) {
                    phone = phone.substr(0, 11);
                }
                this.value = phone;
            }
        });
    }
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        document.querySelectorAll('.alert').forEach(function(alert) {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(function() {
                if (alert.parentNode) {
                    alert.remove();
                }
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
    
    // Add shake animation
    const shakeStyle = document.createElement('style');
    shakeStyle.textContent = `
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
    `;
    document.head.appendChild(shakeStyle);
});
</script>
</body>
</html>