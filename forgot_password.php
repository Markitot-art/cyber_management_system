<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

// Then include your existing code...
require_once 'config.php';

$error = '';
$success = '';
// FOR TESTING - Show direct link (change to true for testing without email)
$show_direct_link = true; // Set to true for testing (shows link directly)

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = isset($_POST['email']) ? sanitize($conn, $_POST['email']) : '';
    
    if (empty($email)) {
        $error = "Please enter your email address or username";
    } else {
        // Check if email or username exists
        $query = "SELECT user_id, username, full_name, email FROM users WHERE email = ? OR username = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $email, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            if (empty($user['email'])) {
                $error = "This account has no email address on file. Contact the system administrator.";
            } else {
                // Generate secure reset token
                $token = bin2hex(random_bytes(16));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Store reset data in database
                $update_query = "UPDATE users SET reset_token = ?, reset_expires = ? WHERE user_id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("ssi", $token, $expires, $user['user_id']);
                
                if ($update_stmt->execute()) {
                    $reset_link = SITE_URL . 'reset_password.php?code=' . urlencode($token);
                    
                    // FOR TESTING - Show direct link
                    if ($show_direct_link) {
                        $success = "Forgot password link generated: <br><a href='{$reset_link}' class='btn btn-primary mt-2' target='_blank'>Click here to reset password</a>";
                        $success .= "<br><small class='text-muted'>Email sending is disabled for testing.</small>";
                    } else {
                        // Send email
                        $subject = 'PNP ACG Password Reset Request';
                        $body = "
                        <html>
                        <head>
                            <style>
                                body { font-family: Arial, sans-serif; }
                                .container { padding: 20px; background: #f4f4f4; }
                                .content { background: white; padding: 20px; border-radius: 5px; }
                                .button { 
                                    display: inline-block; 
                                    padding: 10px 20px; 
                                    background: #ce1126; 
                                    color: white; 
                                    text-decoration: none; 
                                    border-radius: 5px;
                                    margin: 10px 0;
                                }
                            </style>
                        </head>
                        <body>
                            <div class='container'>
                                <div class='content'>
                                    <h2>Password Reset Request</h2>
                                    <p>Hi " . htmlspecialchars($user['full_name']) . ",</p>
                                    <p>We received a request to reset your password. Click the button below to choose a new password:</p>
                                    <p><a href='{$reset_link}' class='button'>Reset Your Password</a></p>
                                    <p>Or copy this link: <br>{$reset_link}</p>
                                    <p>If you did not request this, please ignore this email.</p>
                                    <p>This link will expire in 1 hour.</p>
                                    <hr>
                                    <small>PNP ACG System</small>
                                </div>
                            </div>
                        </body>
                        </html>
                        ";
                        
                        $altBody = "Password Reset Request\n\n";
                        $altBody .= "Hi " . $user['full_name'] . ",\n\n";
                        $altBody .= "We received a request to reset your password. Click the link below to choose a new password:\n\n";
                        $altBody .= $reset_link . "\n\n";
                        $altBody .= "If you did not request this, please ignore this email.\n";
                        $altBody .= "This link will expire in 1 hour.\n";
                        
                        $mailError = '';
                        if (sendMail($user['email'], $subject, $body, $altBody, $mailError)) {
                            $success = "A password reset email has been sent to your email address. Please check your inbox.";
                        } else {
                            $error = "Unable to send password reset email. Error: " . htmlspecialchars($mailError);
                        }
                    }
                } else {
                    $error = "An error occurred. Please try again later.";
                }
                $update_stmt->close();
            }
        } else {
            $error = "Email address or username not found in our records.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - PNP ACG</title>
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
            background: #003366;
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: bold;
            transition: 0.3s;
        }
        .btn-reset:hover {
            background: #ce1126;
        }
        .btn-login {
            background: #6c757d;
        }
        .btn-login:hover {
            background: #5a6268;
        }
        .form-group {
            position: relative;
            margin-bottom: 20px;
        }
        .form-control {
            padding: 12px 15px 12px 45px;
            border-radius: 10px;
        }
        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        .alert a {
            color: white;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="reset-wrapper">
        <div class="reset-card">
            <div class="text-center mb-4">
                <i class="fas fa-key" style="font-size: 48px; color: #003366;"></i>
                <h3 class="mt-3">Forgot Password?</h3>
                <p class="text-muted">Enter your email address or username to reset your password</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!$success): ?>
            <form method="POST">
                <div class="form-group">
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="text" name="email" class="form-control" placeholder="Email address or username" required autofocus>
                </div>
                
                <button type="submit" class="btn-reset">
                    <i class="fas fa-paper-plane"></i> Send Reset Link
                </button>
            </form>
            <?php endif; ?>
            
            <div class="text-center mt-4">
                <a href="index.php" class="text-decoration-none">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>