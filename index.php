<?php
// login.php - Login Page with Security Features
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
$warning = '';

// Check for too many attempts
$identifier = $_SERVER['REMOTE_ADDR'];

// FIXED: Properly prepare and execute the login attempts query
$attempt_check = $conn->prepare("SELECT COUNT(*) as attempts FROM login_attempts WHERE identifier = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL ? MINUTE)");
if ($attempt_check) {
    $lockout_minutes = LOGIN_LOCKOUT_TIME;
    $attempt_check->bind_param("si", $identifier, $lockout_minutes);
    $attempt_check->execute();
    $attempt_result = $attempt_check->get_result();
    $attempts = $attempt_result->fetch_assoc()['attempts'];
    $attempt_check->close();
} else {
    $attempts = 0;
}

if ($attempts >= MAX_LOGIN_ATTEMPTS) {
    $warning = "Too many failed attempts. Please try again after " . LOGIN_LOCKOUT_TIME . " minutes.";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$warning) {
    $username = sanitize($conn, $_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!empty($username) && !empty($password)) {
        // Log this attempt
        $log_attempt = $conn->prepare("INSERT INTO login_attempts (identifier, attempt_time) VALUES (?, NOW())");
        if ($log_attempt) {
            $log_attempt->bind_param("s", $identifier);
            $log_attempt->execute();
            $log_attempt->close();
        }
        
        // Check user
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                
                // Verify password (supports both bcrypt and MD5 for migration)
                if (password_verify($password, $user['password']) || md5($password) === $user['password']) {
                    // Upgrade MD5 to bcrypt if needed
                    if (md5($password) === $user['password']) {
                        $new_hash = password_hash($password, PASSWORD_DEFAULT);
                        $update = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                        if ($update) {
                            $update->bind_param("si", $new_hash, $user['user_id']);
                            $update->execute();
                            $update->close();
                        }
                    }
                    
                    // Clear login attempts
                    $clear = $conn->prepare("DELETE FROM login_attempts WHERE identifier = ?");
                    if ($clear) {
                        $clear->bind_param("s", $identifier);
                        $clear->execute();
                        $clear->close();
                    }
                    
                    // Set session
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['rank'] = $user['rank'];
                    $_SESSION['role'] = $user['role'];
                    
                    // Debug: Verify session was set
                    error_log("Login successful for user: " . $username . ", Session ID: " . $_SESSION['user_id']);
                    
                    redirect('dashboard.php');
                } else {
                    $error = "Invalid username or password";
                }
            } else {
                $error = "Invalid username or password";
            }
            $stmt->close();
        } else {
            $error = "Database error: " . $conn->error;
        }
    } else {
        $error = "Please enter username and password";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PNP ACG Login</title>

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

/* WRAPPER */
.login-wrapper {
    width: 100%;
    max-width: 950px;
    padding: 20px;
}

/* CARD */
.login-card {
    display: flex;
    border-radius: 20px;
    overflow: hidden;
    background: white;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

/* LEFT SIDE */
.login-header {
    width: 45%;
    background: linear-gradient(135deg, #001f3f, #003366);
    color: white;
    padding: 40px;
    text-align: center;
    display: flex;
    flex-direction: column;
    justify-content: center;
    border-right: 3px solid #ce1126;
    font-size: 25px;
}

.pnp-logo {
    width: 150px;
    height: 150px;
    background: white;
    border-radius: 50%;
    margin: 0 auto 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 3px solid #fcd116;
    overflow: hidden;
}

.pnp-logo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* RIGHT SIDE */
.login-form {
    width: 55%;
    padding: 40px;
}

/* CONTACT */
.contact-bar {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 20px;
    font-size: 13px;
    color: #003366;
}

/* INPUTS */
.form-control {
    padding: 12px 15px 12px 40px;
    border-radius: 10px;
}

.input-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
}

/* PASSWORD TOGGLE */
.toggle-password {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: #666;
}

/* BUTTON */
.btn-login {
    width: 100%;
    padding: 12px;
    background: #ce1126;
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: bold;
    transition: 0.3s;
}

.btn-login:hover {
    background: #a50d1e;
}

/* FOOTER */
.footer-links {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-top: 20px;
    font-size: 13px;
    flex-wrap: wrap;
}

.footer-links a {
    color: #003366;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 500;
}

.footer-links a:hover {
    color: #ce1126;
}

/* ALERT */
.alert {
    font-size: 14px;
}

/* HEADER BAR */
.login-topbar {
    background: #f8f9fc;
    padding: 10px 15px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.contact-inline {
    margin: 0;
    font-size: 13px;
    color: #003366;
    text-align: center;
}

.contact-inline i {
    color: #ce1126;
    margin-right: 5px;
}

.forgot-link {
    text-align: right;
    margin-bottom: 15px;
}

.forgot-link a {
    color: #003366;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
}

.forgot-link a:hover {
    color: #ce1126;
}

/* RESPONSIVE */
@media (max-width: 768px) {
    .login-card {
        flex-direction: column;
    }
    .login-header, .login-form {
        width: 100%;
    }
}
</style>
</head>
<body>

<div class="login-wrapper">
<div class="login-card">

   <!-- LEFT SIDE -->
<div class="login-header">
    <div class="pnp-logo">
        <img src="videos/uploads/cyberlogo.png" alt="PNP Logo">
    </div>
    
    <p>Camarines Sur Provincial Cyber Response Team</p>
</div>

    <!-- RIGHT SIDE -->
<div class="login-form">

    <header class="login-topbar">
    <p class="contact-inline">
        <i class="fas fa-phone"></i> 0919-073-9765
        &nbsp; | &nbsp;
        <i class="fas fa-envelope"></i> racu5camsur@gmail.com
        &nbsp; | &nbsp;
        <i class="fab fa-facebook"></i> RACU5 CamSur Official
    </p>
</header>
       
        <h4 class="mb-3 text-center fw-bold">
    <img src="videos/uploads/icon.png" alt="" style="width: 30px; height: 30px; margin-right: 8px;">
    Cyber Response Login
</h4>
<p class="text-center text-muted" style="font-size:13px;">
    Only admin user is allowed to login.
</p>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($warning): ?>
            <div class="alert alert-warning">
                <i class="fas fa-clock"></i> <?php echo htmlspecialchars($warning); ?>
            </div>
        <?php endif; ?>

        <form method="POST">

            <div class="mb-3 position-relative">
                <i class="fas fa-user input-icon"></i>
                <input type="text" name="username" class="form-control" placeholder="Username" required <?php echo $warning ? 'disabled' : ''; ?>>
            </div>

            <div class="mb-3 position-relative">
                <i class="fas fa-lock input-icon"></i>
                <input type="password" name="password" id="password" class="form-control" placeholder="Password" required <?php echo $warning ? 'disabled' : ''; ?>>
                <i class="fas fa-eye toggle-password" onclick="togglePassword()"></i>
            </div>
            
            <!-- Forgot Password Link -->
            <div class="forgot-link">
                <a href="forgot_password.php">
                    <i class="fas fa-key"></i> Forgot Password?
                </a>
            </div>

            <button type="submit" class="btn-login" <?php echo $warning ? 'disabled' : ''; ?>>
                <i class="fas fa-sign-in-alt"></i> LOGIN
            </button>

        </form>

        <!-- FOOTER LINKS -->
        <div class="footer-links">
            <a href="https://www.facebook.com/racu5nagacamsur" target="_blank">
                <i class="fab fa-facebook"></i> @racu5.camsur
            </a>
        </div>

    </div>

</div>
</div>

<script>
function togglePassword() {
    const pass = document.getElementById("password");
    const icon = document.querySelector(".toggle-password");
    if (pass.type === "password") {
        pass.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
    } else {
        pass.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    }
}

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>