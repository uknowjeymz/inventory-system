<?php
session_start();

// This endpoint extends the user's session
if (isset($_SESSION['user_id'])) {
    // Regenerate session ID for security
    session_regenerate_id(true);
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    
    // Return success response
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Session extended']);
} else {
    // No active session
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No active session']);
}
?>