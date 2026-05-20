<?php
session_start();
require_once '../config/database.php';

if ($_POST) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        if (!$db) {
            $_SESSION['error'] = "Database connection failed!";
            header("Location: ../index.php");
            exit();
        }
        
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        
        // Validate input
        if (empty($email) || empty($password)) {
            $_SESSION['error'] = "Email and password are required!";
            header("Location: ../index.php");
            exit();
        }
        
        // Find user by email and get their role automatically
        $query = "SELECT id, email, password, role, full_name, campus FROM users WHERE email = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$email]);

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verify password
            if (password_verify($password, $row['password'])) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['email'] = $row['email'];
                $_SESSION['role'] = $row['role'];
                $_SESSION['full_name'] = $row['full_name'];
                $_SESSION['campus'] = !empty($row['campus']) ? $row['campus'] : 'Not Assigned'; // Changed this line
                
                // Clear any previous error messages
                unset($_SESSION['error']);
                
                // Debug log
                error_log("Login successful - User: " . $row['full_name'] . ", Campus: " . $_SESSION['campus']);
                
                // Redirect based on user's role
                if ($row['role'] == 'admin') {
                    header("Location: ../admin/dashboard.php");
                } else {
                    header("Location: ../user/dashboard.php");
                }
                exit();
            } else {
                $_SESSION['error'] = "Invalid password! Please check your credentials.";
            }
        } else {
            $_SESSION['error'] = "Email not found! Please check your email or register a new account.";
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Login error: " . $e->getMessage();
        error_log("Login error: " . $e->getMessage());
    }
    
    header("Location: ../index.php");
    exit();
}
?>