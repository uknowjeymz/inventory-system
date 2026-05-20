<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Include database connection
require_once '../consumable/config/database.php';

// Create database connection
$database = new Database();
$db = $database->getConnection();

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Get and sanitize form data
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validation
    $errors = [];
    
    if (empty($email)) {
        $errors[] = "Email address is required";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    }
    
    // If no validation errors, attempt login
    if (empty($errors)) {
        try {
            // Query to find user by email
            $query = "SELECT id, full_name, email, password, role, department, phone, status 
                      FROM users 
                      WHERE email = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Check if user exists and password is correct
            if ($user && password_verify($password, $user['password'])) {
                
                // Check if account is active
                if ($user['status'] === 'inactive') {
                    $_SESSION['error'] = "Your account has been deactivated. Please contact the administrator.";
                    header("Location: login.php");
                    exit();
                }
                
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_type'] = $user['role']; // For compatibility
                $_SESSION['logged_in'] = true;
                
                // Update last login timestamp
                $update_query = "UPDATE users SET last_login = NOW() WHERE id = ?";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->execute([$user['id']]);
                
                // Log the login (optional)
                error_log("User logged in: " . $user['email'] . " - " . date('Y-m-d H:i:s'));
                
                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header("Location: ../admin/consumables.php");
                } else {
                    header("Location: dashboard.php");
                }
                exit();
                
            } else {
                // Invalid credentials
                $_SESSION['error'] = "Invalid email or password.";
                header("Location: login.php");
                exit();
            }
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $_SESSION['error'] = "An error occurred. Please try again later.";
            header("Location: login.php");
            exit();
        }
    } else {
        // Validation errors
        $_SESSION['error'] = implode("<br>", $errors);
        header("Location: login.php");
        exit();
    }
    
} else {
    // If someone tries to access this file directly without POST
    header("Location: login.php");
    exit();
}
?>