<?php
// AJAX endpoint to check if email is available
require_once '../config/database.php';

if (isset($_POST['email'])) {
    $database = new Database();
    $db = $database->getConnection();
    
    $email = trim($_POST['email']);
    
    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $query = "SELECT id FROM users WHERE email = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['available' => false, 'message' => 'Email already exists']);
        } else {
            echo json_encode(['available' => true, 'message' => 'Email is available']);
        }
    } else {
        echo json_encode(['available' => false, 'message' => 'Please enter a valid email address']);
    }
} else {
    echo json_encode(['available' => false, 'message' => 'Invalid request']);
}
?>