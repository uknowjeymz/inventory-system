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

// Include database connection
require_once '../consumable/config/database.php';
$database = new Database();
$db = $database->getConnection();

// Fetch active departments from the database
$dept_query = "SELECT department_name FROM departments WHERE is_active = 1 ORDER BY department_name ASC";
$dept_stmt = $db->prepare($dept_query);
$dept_stmt->execute();
$departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Create Account - Consumable System";
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
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
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
            background-image: url('assets/UCC_Background.png');
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
        
        .register-container {
            width: 100%;
            max-width: 600px;
            margin: 20px auto;
            position: relative;
            z-index: 1;
        }
        
        .register-card {
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
            margin-bottom: 20px;
        }
        
        .welcome-text h2 {
            font-size: 1.3rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 3px;
        }
        
        .welcome-text p {
            color: #718096;
            font-size: 0.85rem;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-label {
            font-weight: 500;
            color: #4a5568;
            margin-bottom: 5px;
            font-size: 0.85rem;
        }
        
        .input-group {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
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
            padding: 0 12px;
        }
        
        .form-control, .form-select {
            border: none;
            padding: 10px 12px;
            font-size: 0.9rem;
            background: transparent;
        }
        
        .form-control:focus, .form-select:focus {
            box-shadow: none;
            background: transparent;
        }
        
        .row {
            margin: 0 -5px;
        }
        
        .col-md-6 {
            padding: 0 5px;
        }
        
        .btn-register {
            width: 100%;
            padding: 12px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            border: none;
            color: white;
            margin-top: 15px;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(13, 110, 253, 0.3);
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(13, 110, 253, 0.4);
        }
        
        .btn-register:active {
            transform: translateY(0);
        }
        
        .btn-register:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .login-link {
            text-align: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #e2e8f0;
        }
        
        .login-link a {
            color: #0d6efd;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }
        
        .login-link a:hover {
            color: #0b5ed7;
            text-decoration: underline;
        }
        
        .alert {
            border-radius: 12px;
            padding: 10px 12px;
            margin-bottom: 15px;
            border: none;
            font-size: 0.85rem;
        }
        
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .alert-success {
            background: #dcfce7;
            color: #166534;
        }
        
        /* Terms and Conditions Modal Styles */
        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }
        
        .modal-header {
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 15px 20px;
        }
        
        .modal-header h5 {
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .modal-body {
            padding: 20px;
            max-height: 60vh;
            overflow-y: auto;
        }
        
        .terms-section {
            margin-bottom: 20px;
        }
        
        .terms-section h6 {
            color: #0d6efd;
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 8px;
        }
        
        .terms-section p {
            color: #4a5568;
            font-size: 0.85rem;
            line-height: 1.5;
            margin-bottom: 8px;
        }
        
        .terms-section ul {
            padding-left: 20px;
            margin-bottom: 8px;
        }
        
        .terms-section li {
            color: #4a5568;
            font-size: 0.85rem;
            line-height: 1.5;
            margin-bottom: 3px;
        }
        
        .modal-footer {
            border-top: 2px solid #e2e8f0;
            padding: 15px 20px;
        }
        
        .btn-agree {
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .btn-agree:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13, 110, 253, 0.3);
        }
        
        .btn-disagree {
            background: white;
            color: #6c757d;
            border: 2px solid #e2e8f0;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .btn-disagree:hover {
            background: #f8f9fa;
            border-color: #adb5bd;
        }
        
        .terms-checkbox {
            margin-top: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 2px solid #e2e8f0;
        }
        
        .terms-checkbox .form-check-input {
            width: 18px;
            height: 18px;
            margin-right: 8px;
            cursor: pointer;
        }
        
        .terms-checkbox .form-check-label {
            color: #4a5568;
            font-size: 0.85rem;
            cursor: pointer;
        }
        
        .terms-checkbox .form-check-label a {
            color: #0d6efd;
            text-decoration: none;
            font-weight: 600;
        }
        
        .terms-checkbox .form-check-label a:hover {
            text-decoration: underline;
        }
        
        /* Mobile optimizations */
        @media (max-width: 480px) {
            .register-card {
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
            
            .form-control, .form-select {
                padding: 8px 10px;
                font-size: 0.85rem;
            }
            
            .btn-register {
                padding: 10px;
            }
            
            .modal-body {
                max-height: 50vh;
                padding: 15px;
            }
            
            .terms-section h6 {
                font-size: 0.9rem;
            }
            
            .terms-section p, .terms-section li {
                font-size: 0.8rem;
            }
        }
        
        /* Touch-friendly inputs */
        @media (hover: none) and (pointer: coarse) {
            .form-control, .form-select, .btn-register, .btn-agree, .btn-disagree {
                min-height: 44px;
            }
        }
        
        /* Loading spinner */
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card animate__animated animate__fadeIn">
            <!-- Logo and Title Section -->
            <div class="logo-section">
                <img src="assets/UCC_Logo.png" alt="UCC Logo">
                <h1>University of Caloocan City</h1>
                <div class="subtitle">
                    <span>Consumable</span> Management System
                </div>
            </div>
            
            <!-- Welcome Message -->
            <div class="welcome-text">
                <h2>Create Account</h2>
                <p>Register to access the consumable system</p>
            </div>
            
            <!-- Alert Messages -->
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Registration Form -->
            <form action="process_register.php" method="POST" id="registerForm">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-user me-1"></i>First Name
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-user"></i>
                                </span>
                                <input type="text" 
                                       class="form-control" 
                                       name="first_name" 
                                       placeholder="Enter first name"
                                       required 
                                       autocomplete="given-name">
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-user me-1"></i>Last Name
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-user"></i>
                                </span>
                                <input type="text" 
                                       class="form-control" 
                                       name="last_name" 
                                       placeholder="Enter last name"
                                       required 
                                       autocomplete="family-name">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-envelope me-1"></i>Email Address
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
                               autocomplete="email">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-map-marker-alt me-1"></i>Campus
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-map-marker-alt"></i>
                        </span>
                        <select name="campus" class="form-select" required>
                            <option value="">-- Select Campus --</option>
                            <option value="South Campus">South Campus</option>
                            <option value="Congressional Campus">Congressional Campus</option>
                            <option value="Camarin Campus">Camarin Campus</option>
                            <option value="Bagong Silang Campus">Bagong Silang Campus</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-building me-1"></i>Office/Department
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-building"></i>
                        </span>
                        <select name="department" class="form-select" required>
                            <option value="">-- Select Department --</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept['department_name']); ?>">
                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-phone me-1"></i>Phone Number
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-phone"></i>
                        </span>
                        <input type="tel" 
                               class="form-control" 
                               name="phone" 
                               placeholder="Enter phone number"
                               required 
                               pattern="[0-9]{11}"
                               title="Please enter a valid 11-digit phone number"
                               autocomplete="tel">
                    </div>
                    <small class="text-muted">Format: 09123456789 (11 digits)</small>
                </div>
                
                <!-- Terms and Conditions Checkbox -->
                <div class="terms-checkbox">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="termsCheckbox" required>
                        <label class="form-check-label" for="termsCheckbox">
                            I have read and agree to the 
                            <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">
                                Terms and Conditions
                            </a> 
                            and 
                            <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">
                                Data Privacy Act
                            </a>
                        </label>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-register" id="registerBtn" disabled>
                    <i class="fas fa-user-plus me-2"></i>Create Account
                </button>
            </form>
            
            <!-- Login Link -->
            <div class="login-link">
                <a href="login.php">
                    <i class="fas fa-arrow-left me-2"></i>Back to Login
                </a>
            </div>
        </div>
    </div>
    
    <!-- Terms and Conditions Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-file-contract me-2"></i>Terms and Conditions
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="terms-section">
                        <h6>1. Acceptance of Terms</h6>
                        <p>By accessing and using the University of Caloocan City Consumable Management System, you agree to be bound by these Terms and Conditions. If you do not agree with any part of these terms, you may not use our services.</p>
                    </div>
                    
                    <div class="terms-section">
                        <h6>2. Account Registration</h6>
                        <p>To use certain features of the system, you must register for an account. You agree to provide accurate, current, and complete information during the registration process and to update such information to keep it accurate, current, and complete.</p>
                    </div>
                    
                    <div class="terms-section">
                        <h6>3. Account Security</h6>
                        <p>You are responsible for maintaining the confidentiality of your account credentials and for all activities that occur under your account. You agree to immediately notify the system administrator of any unauthorized use of your account.</p>
                    </div>
                    
                    <div class="terms-section">
                        <h6>4. Acceptable Use</h6>
                        <p>You agree to use the system only for lawful purposes and in accordance with university policies. You may not:</p>
                        <ul>
                            <li>Use the system in any way that violates applicable laws or regulations</li>
                            <li>Attempt to gain unauthorized access to any part of the system</li>
                            <li>Interfere with or disrupt the operation of the system</li>
                            <li>Use the system to transmit any harmful code or materials</li>
                        </ul>
                    </div>
                    
                    <div class="terms-section">
                        <h6>5. System Availability</h6>
                        <p>While we strive to maintain high availability of the system, we do not guarantee that the system will be available at all times. We reserve the right to modify, suspend, or discontinue any part of the system without notice.</p>
                    </div>
                    
                    <div class="terms-section">
                        <h6>6. Limitation of Liability</h6>
                        <p>The university shall not be liable for any indirect, incidental, special, consequential, or punitive damages arising out of or relating to your use of the system.</p>
                    </div>
                    
                    <div class="terms-section">
                        <h6>7. Modifications to Terms</h6>
                        <p>We reserve the right to modify these terms at any time. Your continued use of the system after any such changes constitutes your acceptance of the new terms.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-disagree" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Data Privacy Act Modal -->
    <div class="modal fade" id="privacyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-shield-alt me-2"></i>Data Privacy Act of 2012
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="terms-section">
                        <h6>Republic Act No. 10173</h6>
                        <p>An Act Protecting Individual Personal Information in Information and Communications Systems in the Government and the Private Sector, Creating for this Purpose a National Privacy Commission, and for Other Purposes.</p>
                    </div>
                    
                    <div class="terms-section">
                        <h6>1. Collection of Information</h6>
                        <p>The University of Caloocan City collects and processes your personal information for the following purposes:</p>
                        <ul>
                            <li>To create and manage your account</li>
                            <li>To process your requests for consumable items</li>
                            <li>To communicate with you regarding your requests</li>
                            <li>To improve our services and system functionality</li>
                            <li>To comply with legal and regulatory requirements</li>
                        </ul>
                    </div>
                    
                    <div class="terms-section">
                        <h6>2. Types of Information Collected</h6>
                        <p>We collect the following personal information:</p>
                        <ul>
                            <li><strong>Full Name:</strong> For identification purposes</li>
                            <li><strong>Email Address:</strong> For account communication and notifications</li>
                            <li><strong>Department:</strong> To determine your affiliation with the university</li>
                            <li><strong>Campus:</strong> To determine your primary location</li>
                            <li><strong>Phone Number:</strong> For emergency contact and verification</li>
                            <li><strong>System Activity:</strong> Logs of your interactions with the system</li>
                        </ul>
                    </div>
                    
                    <div class="terms-section">
                        <h6>3. Data Protection</h6>
                        <p>We implement appropriate technical and organizational measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction. These measures include:</p>
                        <ul>
                            <li>Secure password hashing</li>
                            <li>Encrypted data transmission</li>
                            <li>Regular security audits</li>
                            <li>Limited access to personal information</li>
                        </ul>
                    </div>
                    
                    <div class="terms-section">
                        <h6>4. Your Rights</h6>
                        <p>Under the Data Privacy Act, you have the following rights:</p>
                        <ul>
                            <li><strong>Right to be Informed:</strong> You have the right to know how your information is being used</li>
                            <li><strong>Right to Access:</strong> You may request a copy of your personal information</li>
                            <li><strong>Right to Correction:</strong> You may request correction of inaccurate information</li>
                            <li><strong>Right to Erasure:</strong> You may request deletion of your information under certain conditions</li>
                            <li><strong>Right to Object:</strong> You may object to the processing of your information</li>
                        </ul>
                    </div>
                    
                    <div class="terms-section">
                        <h6>5. Data Sharing</h6>
                        <p>Your personal information will not be shared with third parties except:</p>
                        <ul>
                            <li>When required by law or legal process</li>
                            <li>With your explicit consent</li>
                            <li>To protect the rights and safety of the university community</li>
                        </ul>
                    </div>
                    
                    <div class="terms-section">
                        <h6>6. Data Retention</h6>
                        <p>Your personal information will be retained for as long as your account is active or as needed to provide you services. We may retain and use your information as necessary to comply with legal obligations, resolve disputes, and enforce our agreements.</p>
                    </div>
                    
                    <div class="terms-section">
                        <h6>7. Contact Information</h6>
                        <p>For any concerns regarding your personal information, you may contact:</p>
                        <p><strong>University of Caloocan City</strong><br>
                        Email: admin@ucc-caloocan.edu.ph<br>
                        Tel: (02) 8528-4654</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-disagree" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Enable/disable register button based on terms checkbox
        document.getElementById('termsCheckbox').addEventListener('change', function() {
            document.getElementById('registerBtn').disabled = !this.checked;
        });
        
        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const firstName = document.querySelector('input[name="first_name"]').value;
            const lastName = document.querySelector('input[name="last_name"]').value;
            const email = document.querySelector('input[name="email"]').value;
            const campus = document.querySelector('select[name="campus"]').value;
            const department = document.querySelector('select[name="department"]').value;
            const phone = document.querySelector('input[name="phone"]').value;
            
            if (!firstName || !lastName || !email || !campus || !department || !phone) {
                e.preventDefault();
                alert('Please fill in all fields');
                return;
            }
            
            // Validate email format
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address');
                return;
            }
            
            // Validate phone number (Philippines format)
            const phoneRegex = /^09[0-9]{9}$/;
            if (!phoneRegex.test(phone)) {
                e.preventDefault();
                alert('Please enter a valid 11-digit Philippine mobile number (starting with 09)');
                return;
            }
            
            // Disable button and show loading
            const btn = document.getElementById('registerBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner me-2"></span>Creating Account...';
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
            document.querySelectorAll('.btn-register, .btn-agree, .btn-disagree').forEach(btn => {
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