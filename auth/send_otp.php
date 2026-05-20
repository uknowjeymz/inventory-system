<?php
// Clear any previous output
ob_clean();
session_start();
header('Content-Type: application/json');

// Error handling
error_reporting(0);
ini_set('display_errors', 0);

// Prevent session fixation
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
        exit;
    }

    // 1. Generate 6-digit OTP
    $otp = sprintf("%06d", rand(100000, 999999));
    $_SESSION['otp'] = $otp;
    $_SESSION['otp_email'] = $email;
    $_SESSION['otp_time'] = time();

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'ucca91445@gmail.com';
        $mail->Password   = 'uniu qsog orrt wnon';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Disable SSL verification for local development
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Recipients
        $mail->setFrom('ucca91445@gmail.com', 'UCC Inventory System');
        $mail->addAddress($email);
        $mail->addReplyTo('ucca91445@gmail.com', 'UCC Inventory System');

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your Registration Verification Code - UCC Inventory';
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; border: 1px solid #e0e0e0; border-radius: 15px; overflow: hidden;'>
                <div style='background: linear-gradient(135deg, #2E7D32, #5eaf62); padding: 30px; text-align: center;'>
                    <h1 style='color: white; margin: 0; font-size: 24px;'>UCC Inventory System</h1>
                    <p style='color: rgba(255,255,255,0.9); margin: 5px 0 0;'>University of Caloocan City</p>
                </div>
                <div style='padding: 30px;'>
                    <h2 style='color: #2E7D32;'>Account Verification</h2>
                    <p>Hello,</p>
                    <p>Thank you for registering with the UCC Inventory Management System. Please use the verification code below to complete your registration:</p>
                    <div style='background: #f5f5f5; padding: 25px; text-align: center; border-radius: 10px; margin: 20px 0;'>
                        <span style='font-size: 36px; font-weight: bold; color: #2E7D32; letter-spacing: 8px;'>{$otp}</span>
                    </div>
                    <p style='color: #666; font-size: 14px;'>This code will expire in 10 minutes. If you did not request this code, please ignore this email.</p>
                    <hr style='border: 1px solid #e0e0e0; margin: 20px 0;'>
                    <p style='color: #999; font-size: 12px; text-align: center;'>This is an automated message from the UCC Inventory Management System. Please do not reply to this email.</p>
                </div>
            </div>";

        $mail->AltBody = "Your verification code is: {$otp}\n\nThis code will expire in 10 minutes.";

        $mail->send();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Verification code sent to ' . $email,
            'email' => $email
        ]);
        
    } catch (Exception $e) {
        error_log("OTP Email Error: " . $e->getMessage());
        
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to send verification code. Please try again later.'
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
exit;
?>