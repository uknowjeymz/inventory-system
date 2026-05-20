<?php
session_start();
require_once '../config/database.php';

$error = '';
$token = $_GET['token'] ?? '';

if (empty($token)) {
    $_SESSION['error'] = "No reset token provided.";
    header("Location: ../index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Check if token matches and is not expired
$query = "SELECT id FROM users WHERE reset_token = ? AND reset_expiry > NOW() LIMIT 1";
$stmt = $db->prepare($query);
$stmt->execute([$token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $_SESSION['error'] = "Link is invalid or has expired.";
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        // Update password and clear token
        $update = "UPDATE users SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE id = ?";
        $stmt = $db->prepare($update);
        if ($stmt->execute([$hashed, $user['id']])) {
            $_SESSION['success'] = "Password updated! You can now login.";
            header("Location: ../index.php");
            exit();
        } else {
            $error = "Database error. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - UCC Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="..\assets\UCC_Logo.ico">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .reset-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border: none;
        }
        .form-control {
            border-radius: 10px;
            padding: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card reset-card">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                                <i class="fas fa-lock-open fa-lg"></i>
                            </div>
                            <h3 class="fw-bold">New Password</h3>
                            <p class="text-muted">Create a secure password for your account</p>
                        </div>

                        <?php if($error): ?>
                            <div class="alert alert-danger small border-0 shadow-sm">
                                <i class="fas fa-times-circle me-2"></i><?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted text-uppercase">New Password</label>
                                <input type="password" name="password" class="form-control" placeholder="Min. 6 characters" required autofocus>
                            </div>
                            <div class="mb-4">
                                <label class="form-label small fw-bold text-muted text-uppercase">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" placeholder="Repeat your password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 py-3 fw-bold rounded-3 shadow-sm">
                                Reset Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>