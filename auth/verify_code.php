<?php
// Clear any previous output
ob_clean();
session_start();
header('Content-Type: application/json');

// Disable error display for clean JSON output
error_reporting(0);
ini_set('display_errors', 0);

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['valid' => false, 'message' => 'Invalid request method']);
    exit;
}

$code = trim($_POST['code'] ?? '');

// Validate input
if (empty($code)) {
    echo json_encode(['valid' => false, 'message' => 'Verification code is required']);
    exit;
}

if (!is_numeric($code) || strlen($code) !== 6) {
    echo json_encode(['valid' => false, 'message' => 'Invalid code format. Please enter a 6-digit number.']);
    exit;
}

// Check if OTP session exists
if (!isset($_SESSION['otp'])) {
    echo json_encode(['valid' => false, 'message' => 'No verification code found. Please request a new code.']);
    exit;
}

// Check if OTP has expired (10 minutes)
if (isset($_SESSION['otp_time'])) {
    $elapsed = time() - $_SESSION['otp_time'];
    if ($elapsed > 600) { // 10 minutes = 600 seconds
        // Clear expired OTP
        unset($_SESSION['otp']);
        unset($_SESSION['otp_time']);
        unset($_SESSION['otp_email']);
        echo json_encode(['valid' => false, 'message' => 'Verification code has expired. Please request a new code.']);
        exit;
    }
}

// Verify the code
if ((string)$code === (string)$_SESSION['otp']) {
    // Set verification flag
    $_SESSION['email_verified'] = true;
    $_SESSION['verified_email'] = $_SESSION['otp_email'] ?? '';
    
    echo json_encode([
        'valid' => true, 
        'message' => 'Email verified successfully!'
    ]);
} else {
    // Debug info
    error_log("OTP Mismatch: Expected " . $_SESSION['otp'] . ", Got " . $code);
    
    echo json_encode([
        'valid' => false, 
        'message' => 'Invalid verification code. Please try again.'
    ]);
}
exit;
?>