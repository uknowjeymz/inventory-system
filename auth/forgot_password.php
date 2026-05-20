<?php
session_start();
// SET TIMEZONE FIRST TO AVOID IMMEDIATE EXPIRATION
date_default_timezone_set('Asia/Manila');

require_once '../config/database.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_email'])) {
    $database = new Database();
    $db = $database->getConnection();
    $email = trim($_POST['reset_email']);

    try {
        // 1. Check if user exists
        $query = "SELECT id, full_name FROM users WHERE email = :email LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // 2. Generate secure token and expiry
            $token = bin2hex(random_bytes(32));
            
            // This now uses Asia/Manila time
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // 3. UPDATE THE DATABASE
            $updateQuery = "UPDATE users SET reset_token = :token, reset_expiry = :expiry WHERE email = :email";
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->bindParam(':token', $token);
            $updateStmt->bindParam(':expiry', $expiry);
            $updateStmt->bindParam(':email', $email);
            
            if ($updateStmt->execute()) {
                // 4. Send Email using PHPMailer
                $mail = new PHPMailer(true);
                
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'ucca91445@gmail.com';
                $mail->Password   = 'uniu qsog orrt wnon';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('system@ucc-inventory.com', 'UCC Inventory System');
                $mail->addAddress($email, $user['full_name']);

                $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/auth/reset_password.php?token=" . $token;

                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Request - UCC Inventory System';
                $mail->Body    = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; border: 1px solid #eee; padding: 20px; border-radius: 15px;'>
                        <h2 style='color: #0d6efd; text-align: center;'>Password Reset</h2>
                        <p>Hello <strong>{$user['full_name']}</strong>,</p>
                        <p>We received a request to reset your password for the UCC Inventory Management System.</p>
                        <div style='text-align: center; margin: 30px 0;'>
                            <a href='{$resetLink}' style='background-color: #0d6efd; color: white; padding: 15px 25px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Reset My Password</a>
                        </div>
                        <p>This link will expire in 1 hour (Expires at: " . date('h:i A', strtotime($expiry)) . ").</p>
                        <p style='color: #777; font-size: 12px;'>If you did not request this, please ignore this email.</p>
                    </div>";

                $mail->send();
                $_SESSION['success'] = "A password reset link has been sent to your email.";
            } else {
                $_SESSION['error'] = "Failed to generate reset token. Please try again.";
            }
        } else {
            $_SESSION['success'] = "If that email exists in our system, a reset link has been sent.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Mailer Error: {$mail->ErrorInfo}";
    }

    header("Location: ../index.php");
    exit();
}