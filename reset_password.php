<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

// Then include your existing code...
require_once 'config.php';


$error = '';
$success = '';
// Retrieve reset token from query string and validate it against the database
$token = $_GET['code'] ?? '';

// Validate token
if (empty($token)) {
    redirect('forgot_password.php');
}

// Check if token is valid
$query = "SELECT user_id, username, full_name, email FROM users WHERE reset_token = ? AND reset_expires > NOW()";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows != 1) {
    $error = "Invalid or expired reset link. Please request a new password reset.";
} else {
    $user = $result->fetch_assoc();
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validate password strength
        if (empty($password)) {
            $error = "Please enter a password";
        } elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters long";
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $error = "Password must contain at least one uppercase letter";
        } elseif (!preg_match('/[a-z]/', $password)) {
            $error = "Password must contain at least one lowercase letter";
        } elseif (!preg_match('/[0-9]/', $password)) {
            $error = "Password must contain at least one number";
        } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            $error = "Password must contain at least one special character";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match";
        } else {
            // Update password with secure hashing
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $update_query = "UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE user_id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("si", $hashed_password, $user['user_id']);
            
            if ($update_stmt->execute()) {
                $success = "Password has been reset successfully! You can now login with your new password.";
                // Clear token to prevent reuse
                $token = '';
            } else {
                $error = "An error occurred. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - PNP ACG</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f3f4f5, #001f3f);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', sans-serif;
        }
        .reset-wrapper {
            width: 100%;
            max-width: 500px;
            padding: 20px;
        }
        .reset-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
        }
        .btn-reset {
            width: 100%;
            padding: 12px;
            background: #ce1126;
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: bold;
            transition: 0.3s;
        }
        .btn-reset:hover {
            background: #a50d1e;
        }
        .password-strength {
            font-size: 12px;
            margin-top: 5px;
        }
        .strength-weak { color: #dc3545; }
        .strength-medium { color: #ffc107; }
        .strength-strong { color: #28a745; }
    </style>
</head>
<body>
    <div class="reset-wrapper">
        <div class="reset-card">
            <div class="text-center mb-4">
                <i class="fas fa-lock" style="font-size: 48px; color: #ce1126;"></i>
                <h3 class="mt-3">Reset Password</h3>
                <p class="text-muted">Create a new secure password</p>
            </div>
            
            <?php if (isset($error) && $error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($success) && $success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                    <div class="mt-3">
                        <a href="index.php" class="btn btn-primary">Go to Login</a>
                    </div>
                </div>
            <?php else: ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="password" id="password" class="form-control" required>
                        <div class="password-strength" id="passwordStrength"></div>
                        <small class="text-muted">
                            Password must contain at least 8 characters, uppercase, lowercase, number, and special character.
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                        <div id="passwordMatch" class="password-strength"></div>
                    </div>
                    
                    <button type="submit" class="btn-reset">
                        <i class="fas fa-save"></i> Reset Password
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        const strengthDiv = document.getElementById('passwordStrength');
        const matchDiv = document.getElementById('passwordMatch');
        
        function checkPasswordStrength(pwd) {
            let strength = 0;
            if (pwd.length >= 8) strength++;
            if (pwd.match(/[A-Z]/)) strength++;
            if (pwd.match(/[a-z]/)) strength++;
            if (pwd.match(/[0-9]/)) strength++;
            if (pwd.match(/[!@#$%^&*(),.?":{}|<>]/)) strength++;
            
            if (strength <= 2) return { text: 'Weak', class: 'strength-weak' };
            if (strength <= 4) return { text: 'Medium', class: 'strength-medium' };
            return { text: 'Strong', class: 'strength-strong' };
        }
        
        password.addEventListener('keyup', function() {
            const strength = checkPasswordStrength(this.value);
            strengthDiv.innerHTML = `Password Strength: <span class="${strength.class}">${strength.text}</span>`;
            
            if (confirmPassword.value) {
                checkMatch();
            }
        });
        
        function checkMatch() {
            if (password.value === confirmPassword.value && password.value) {
                matchDiv.innerHTML = '<span class="strength-strong">✓ Passwords match</span>';
            } else if (confirmPassword.value) {
                matchDiv.innerHTML = '<span class="strength-weak">✗ Passwords do not match</span>';
            } else {
                matchDiv.innerHTML = '';
            }
        }
        
        confirmPassword.addEventListener('keyup', checkMatch);
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>