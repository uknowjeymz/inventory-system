<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Check if vendor/autoload.php exists
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    die('Error: Composer dependencies not installed. Please run "composer require phpmailer/phpmailer" in the consumable folder.');
}

// Include Composer's autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Include database connection
require_once '../consumable/config/database.php';
$database = new Database();
$db = $database->getConnection();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Function to generate password (First Name + 4 random numbers)
function generatePassword($firstName) {
    // Clean the first name (remove spaces and special characters)
    $cleanName = preg_replace('/[^a-zA-Z]/', '', $firstName);
    // Take first 4 letters if longer, or use the whole name
    $namePart = substr($cleanName, 0, 4);
    // If name part is empty, use 'USER'
    if (empty($namePart)) {
        $namePart = 'USER';
    }
    // Generate 4 random numbers
    $numbers = sprintf("%04d", mt_rand(0, 9999));
    return ucfirst(strtolower($namePart)) . $numbers;
}

// Function to send email with credentials
function sendCredentialsEmail($email, $fullName, $password, $campus, $department) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'ucca91445@gmail.com';
        $mail->Password   = 'bsgt xoeq qdhb apdp'; // This is an app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->SMTPDebug  = 0; // Set to 2 for debugging, 0 for production
        
        // Recipients
        $mail->setFrom('noreply@ucc.edu.ph', 'UCC Consumable System');
        $mail->addAddress($email, $fullName);
        $mail->addReplyTo('support@ucc.edu.ph', 'UCC Support');
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your Account Credentials - UCC Consumable System';
        
        // Get base URL dynamically
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $base_url = $protocol . '://' . $host . '/consumable';
        
        // Email body
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: 'Inter', sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border: 2px solid #e9ecef; border-top: none; border-radius: 0 0 10px 10px; }
                .credentials { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #0d6efd; }
                .password { font-size: 24px; font-weight: bold; color: #0d6efd; letter-spacing: 2px; font-family: monospace; }
                .footer { margin-top: 20px; font-size: 12px; color: #6c757d; text-align: center; }
                .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px; }
                .button { background: #0d6efd; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: 600; margin-top: 10px; }
                .button:hover { background: #0b5ed7; }
                .info-box { background: #e7f1ff; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #0d6efd; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>University of Caloocan City</h2>
                    <p>Consumable Management System</p>
                </div>
                <div class='content'>
                    <h3>Welcome, $fullName!</h3>
                    <p>Your account has been successfully created in the UCC Consumable Management System.</p>
                    
                    <div class='credentials'>
                        <p><strong>Email:</strong> $email</p>
                        <p><strong>Password:</strong> <span class='password'>$password</span></p>
                    </div>
                    
                    <div class='info-box'>
                        <p><strong>📋 Your Details:</strong></p>
                        <p>Campus: $campus</p>
                        <p>Department: $department</p>
                    </div>
                    
                    <div class='warning'>
                        <strong>Important Security Notice:</strong>
                        <ul style='margin-top: 10px; margin-bottom: 5px;'>
                            <li>Please change your password immediately after first login</li>
                            <li>Never share your password with anyone</li>
                            <li>The system administrator will never ask for your password</li>
                        </ul>
                    </div>
                    
                    <p style='text-align: center;'>
                        <a href='{$base_url}/login.php' class='button'>Login to System</a>
                    </p>
                </div>
                <div class='footer'>
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <p>&copy; " . date('Y') . " University of Caloocan City. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Plain text alternative
        $mail->AltBody = "Welcome to UCC Consumable System!\n\n" .
                        "Your account has been created.\n" .
                        "Email: $email\n" .
                        "Password: $password\n\n" .
                        "Campus: $campus\n" .
                        "Department: $department\n\n" .
                        "Please change your password after first login.\n\n" .
                        "Login at: {$base_url}/login.php\n\n" .
                        "Important: Never share your password with anyone.";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Get and sanitize form data
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $campus = trim($_POST['campus'] ?? ''); // New campus field
    $department = trim($_POST['department'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    // Validation
    $errors = [];
    
    if (empty($first_name)) {
        $errors[] = "First name is required";
    } elseif (strlen($first_name) < 2) {
        $errors[] = "First name must be at least 2 characters";
    }
    
    if (empty($last_name)) {
        $errors[] = "Last name is required";
    } elseif (strlen($last_name) < 2) {
        $errors[] = "Last name must be at least 2 characters";
    }
    
    if (empty($email)) {
        $errors[] = "Email address is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
    }
    
    if (empty($campus)) {
        $errors[] = "Campus is required";
    }
    
    if (empty($department)) {
        $errors[] = "Department is required";
    }
    
    if (empty($phone)) {
        $errors[] = "Phone number is required";
    } elseif (!preg_match('/^09[0-9]{9}$/', $phone)) {
        $errors[] = "Please enter a valid 11-digit Philippine mobile number (e.g., 09123456789)";
    }
    
    // Check if email already exists in database
    if (empty($errors)) {
        try {
            $check_stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $check_stmt->execute([$email]);
            if ($check_stmt->rowCount() > 0) {
                $errors[] = "Email address already registered";
            }
        } catch (Exception $e) {
            error_log("Database error: " . $e->getMessage());
            $errors[] = "System error. Please try again later.";
        }
    }
    
    // If there are errors, redirect back with error messages
    if (!empty($errors)) {
        $_SESSION['error'] = implode("<br>", $errors);
        header("Location: register.php");
        exit();
    }
    
    // Generate password
    $password = generatePassword($first_name);
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Full name
    $full_name = $first_name . ' ' . $last_name;
    
    try {
        // Begin transaction
        $db->beginTransaction();
        
        // Insert user into database - UPDATED to include campus
        $query = "INSERT INTO users (full_name, email, password, department, campus, phone, role, status, created_at) 
                  VALUES (?, ?, ?, ?, ?, ?, 'user', 'active', NOW())";
        $stmt = $db->prepare($query);
        $stmt->execute([$full_name, $email, $hashed_password, $department, $campus, $phone]);
        
        $user_id = $db->lastInsertId();
        
        // Log the registration in database (optional)
        $log_query = "INSERT INTO consumable_logs (action, remarks, performed_by, created_at) 
                      VALUES ('user_registered', ?, ?, NOW())";
        $log_stmt = $db->prepare($log_query);
        $log_stmt->execute(["New user registered: $full_name from $campus - $department", $user_id]);
        
        // Send email with credentials - UPDATED to include campus
        $email_sent = sendCredentialsEmail($email, $full_name, $password, $campus, $department);
        
        if (!$email_sent) {
            // If email fails, still create account but notify user
            $_SESSION['success'] = "Account created successfully! However, we couldn't send the email. Please contact the administrator for your credentials.";
            // Log email failure
            error_log("Email sending failed for user: $email");
        } else {
            $_SESSION['success'] = "Account created successfully! Your credentials have been sent to: <strong>$email</strong>";
        }
        
        $db->commit();
        
        // Store user info for potential auto-login (optional)
        $_SESSION['registered_email'] = $email;
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Registration error: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred during registration. Please try again later.";
    }
    
    header("Location: register.php");
    exit();
    
} else {
    // If someone tries to access this file directly
    header("Location: register.php");
    exit();
}
?>