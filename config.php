<?php
// Shared configuration for the complaint management system.
// This block is hardened for InfinityFree and similar hosting environments.

$session_path = __DIR__ . '/sessions';
if (!is_dir($session_path) && !@mkdir($session_path, 0755, true) && !is_dir($session_path)) {
    error_log('Unable to create session directory: ' . $session_path);
}
if (is_dir($session_path)) {
    ini_set('session.save_path', $session_path);
    session_save_path($session_path);
}

// Set timezone to Philippine Time
date_default_timezone_set('Asia/Manila');

// Error reporting should be quiet in production but still available in logs.
if (PHP_SAPI !== 'cli') {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/error.log');
}

// Database configuration
$remote_db_host = 'sql103.infinityfree.com';
$remote_db_user = 'if0_42143926';
$remote_db_pass = '28ArYxREXmDf';
$remote_db_name = 'if0_42143926_cybercrime_system';

$local_db_host = getenv('DB_HOST') ?: '127.0.0.1';
$local_db_user = getenv('DB_USER') ?: 'root';
$local_db_pass = getenv('DB_PASS') ?: '';
$local_db_name = getenv('DB_NAME') ?: 'cybercrime_system';

define('DB_HOST', getenv('DB_HOST') ?: $remote_db_host);
define('DB_USER', getenv('DB_USER') ?: $remote_db_user);
define('DB_PASS', getenv('DB_PASS') ?: $remote_db_pass);
define('DB_NAME', getenv('DB_NAME') ?: $remote_db_name);

// Application configuration
define('APP_NAME', 'PNP ACG Complaint System');
define('APP_VERSION', '3.0.0');
$scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')) ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
if ($base_path === '.' || $base_path === '/') {
    $base_path = '';
}
define('SITE_URL', $scheme . $host . $base_path . '/');

// Secure session configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    if ($scheme === 'https://') {
        ini_set('session.cookie_secure', 1);
    }
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// Create required directories and ensure they are writable.
$storage_dirs = [
    __DIR__ . '/complainant_photos',
    __DIR__ . '/videos',
    __DIR__ . '/videos/uploads',
    __DIR__ . '/media/images',
    __DIR__ . '/media/videos',
    __DIR__ . '/sessions',
];
foreach ($storage_dirs as $dir) {
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        error_log('Unable to create storage directory: ' . $dir);
    }
    if (is_dir($dir) && !is_writable($dir)) {
        @chmod($dir, 0755);
    }
}

// ============================================================================
// EMAIL CONFIGURATION - HOSTING SAFE
// ============================================================================

define('MAILER', 'phpmail');
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_FROM_EMAIL', 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
define('SMTP_FROM_NAME', 'PNP ACG System');
define('MAIL_FROM', SMTP_FROM_EMAIL);
define('MAIL_FROM_NAME', 'PNP ACG System');
define('SMTP_SECURE', 'tls');
define('SMTP_DEBUG', 0);
define('ENABLE_FALLBACK', true);

// Regenerate session periodically
if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} else if (time() - $_SESSION['last_regeneration'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Security settings
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 15); // minutes

// File upload settings
define('MAX_FILE_SIZE', 52428800); // 50MB
define('ALLOWED_VIDEO_TYPES', ['mp4', 'webm', 'ogg', 'mov', 'avi']);
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);

// Create database connection with remote-first fallback to local XAMPP
$connection_attempts = [
    [$remote_db_host, $remote_db_user, $remote_db_pass, $remote_db_name],
    [$local_db_host, $local_db_user, $local_db_pass, $local_db_name],
    ['127.0.0.1', 'root', '', 'cybercrime_system'],
];

$conn = null;
$connection_error = '';
foreach ($connection_attempts as $attempt) {
    [$host, $user, $pass, $name] = $attempt;
    $candidate = @new mysqli($host, $user, $pass, $name);
    if (!$candidate->connect_error) {
        $conn = $candidate;
        break;
    }
    $connection_error = $candidate->connect_error;
}

// Check connection
if (!$conn || $conn->connect_error) {
    error_log('Database connection failed: ' . $connection_error);
    if (!headers_sent()) {
        http_response_code(503);
    }
    die('The system is temporarily unavailable. Please contact the administrator.');
}

// Set charset
$conn->set_charset('utf8mb4');

// Set MySQL timezone
$conn->query("SET time_zone = '+08:00'");

// ============================================================================
// EMAIL FUNCTIONS - FIXED FOR XAMPP
// ============================================================================

if (!function_exists('sendMail')) {
    function sendMail($to, $subject, $body, $altBody = '', &$errorMessage = null) {
        $errorMessage = '';

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = 'Invalid recipient email address.';
            return false;
        }

        if (defined('MAILER') && MAILER === 'smtp' && !empty(SMTP_USERNAME) && !empty(SMTP_PASSWORD) && file_exists(__DIR__ . '/vendor/autoload.php')) {
            try {
                require_once __DIR__ . '/vendor/autoload.php';

                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->SMTPDebug = SMTP_DEBUG;
                $mail->Host = SMTP_HOST;
                $mail->Port = SMTP_PORT;
                $mail->SMTPAuth = true;
                $mail->Username = SMTP_USERNAME;
                $mail->Password = SMTP_PASSWORD;
                $mail->SMTPSecure = SMTP_SECURE;
                $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                $mail->addAddress($to);
                $mail->isHTML(true);
                $mail->CharSet = 'UTF-8';
                $mail->Subject = $subject;
                $mail->Body = $body;
                $mail->AltBody = $altBody ?: strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));
                $mail->send();
                return true;
            } catch (Exception $e) {
                $errorMessage = $e->getMessage();
                return false;
            }
        }

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= 'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM_EMAIL . '>\r\n';
        $headers .= 'Reply-To: ' . SMTP_FROM_EMAIL . '\r\n';

        $sent = @mail($to, $subject, $body, $headers);
        if (!$sent) {
            $errorMessage = 'Email delivery failed. Please configure SMTP or contact the administrator.';
            return false;
        }

        return true;
    }
}

// ============================================================================
// AUTHENTICATION FUNCTIONS
// ============================================================================

if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['username']);
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin($conn) {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
}

if (!function_exists('redirect')) {
    function redirect($url) {
        $target = $url;
        if (strpos($target, 'http://') !== 0 && strpos($target, 'https://') !== 0) {
            $target = (defined('SITE_URL') ? SITE_URL : '') . ltrim($target, '/');
        }
        header('Location: ' . $target);
        exit();
    }
}

if (!function_exists('sanitize')) {
    function sanitize($conn, $input) {
        if ($input === null) return '';
        return $conn->real_escape_string(trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8')));
    }
}

// ============================================================================
// QUEUE MANAGEMENT FUNCTIONS
// ============================================================================

if (!function_exists('formatQueueNumber')) {
    function formatQueueNumber($number, $case_status = null) {
        if (empty($number)) {
            return 'N/A';
        }

        $queue = trim((string)$number);

        // Normalize existing prefixed values like c-1, F001, I-04 to F-001, C-001, I-004
        if (preg_match('/^([A-Za-z]+)-?0*(\d+)$/', $queue, $matches)) {
            $prefix = strtoupper($matches[1]);
            $digits = str_pad($matches[2], 3, '0', STR_PAD_LEFT);
            return "{$prefix}-{$digits}";
        }

        if (is_numeric($queue)) {
            $formatted_number = str_pad($queue, 3, '0', STR_PAD_LEFT);
            if ($case_status) {
                switch (strtolower($case_status)) {
                    case 'complaint':
                        $prefix = 'C';
                        break;
                    case 'inquiry':
                        $prefix = 'I';
                        break;
                    case 'follow-up':
                        $prefix = 'F';
                        break;
                    default:
                        $prefix = '';
                }
                return !empty($prefix) ? "{$prefix}-{$formatted_number}" : $formatted_number;
            }
            return $formatted_number;
        }

        return $queue;
    }
}

if (!function_exists('getNextQueueNumber')) {
    function getNextQueueNumber($conn, $case_status = null) {
        $prefix = '';
        if ($case_status) {
            switch(strtolower($case_status)) {
                case 'visit': $prefix = 'V'; break;
                case 'complaint': $prefix = 'C'; break;
                case 'inquiry': $prefix = 'I'; break;
                case 'follow-up': $prefix = 'F'; break;
                default: $prefix = '';
            }
        }
        
        $max_number = 0;
        $query = "SELECT queue_number FROM complainants";
        $result = $conn->query($query);
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $queue = $row['queue_number'];
                if (preg_match('/(\d+)$/', $queue, $matches)) {
                    $num = intval($matches[1]);
                    if ($num > $max_number) $max_number = $num;
                } elseif (is_numeric($queue)) {
                    $num = intval($queue);
                    if ($num > $max_number) $max_number = $num;
                }
            }
        }
        
        $next_number = $max_number + 1;
        
        if (empty($prefix)) {
            return $next_number;
        }
        
        $formatted_number = str_pad($next_number, 3, '0', STR_PAD_LEFT);
        return $prefix . '-' . $formatted_number;
    }
}

if (!function_exists('extractNumericFromQueueNumber')) {
    function extractNumericFromQueueNumber($queue_number) {
        if (empty($queue_number)) return 0;
        preg_match_all('/\d+/', $queue_number, $matches);
        if (!empty($matches[0])) return intval($matches[0][0]);
        return 0;
    }
}

if (!function_exists('getQueueNumberPrefix')) {
    function getQueueNumberPrefix($queue_number) {
        if (empty($queue_number)) return '';
        if (preg_match('/^([A-Z])-/', $queue_number, $matches)) return $matches[1];
        return '';
    }
}

if (!function_exists('logQueueHistory')) {
    function logQueueHistory($conn, $queue_number, $action, $remarks = null) {
        $queue_number_str = (string)$queue_number;
        $action = sanitize($conn, $action);
        $remarks = $remarks ? sanitize($conn, $remarks) : null;
        $user_id = $_SESSION['user_id'] ?? 0;
        
        $stmt = $conn->prepare("INSERT INTO queue_history (queue_number, action, remarks, performed_by, created_at) VALUES (?, ?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("sssi", $queue_number_str, $action, $remarks, $user_id);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        }
        return false;
    }
}

// ============================================================================
// DATE AND TIME FUNCTIONS
// ============================================================================

if (!function_exists('formatDateTime')) {
    function formatDateTime($datetime, $format = 'F d, Y h:i A') {
        if (empty($datetime) || $datetime == '0000-00-00 00:00:00') return 'N/A';
        $timestamp = strtotime($datetime);
        if ($timestamp === false || $timestamp <= 0) return 'N/A';
        return date($format, $timestamp);
    }
}

if (!function_exists('formatDate')) {
    function formatDate($date, $format = 'F d, Y') {
        if (empty($date) || $date == '0000-00-00') return 'N/A';
        $timestamp = strtotime($date);
        if ($timestamp === false || $timestamp <= 0) return 'N/A';
        return date($format, $timestamp);
    }
}

if (!function_exists('getCurrentDateTime')) {
    function getCurrentDateTime() {
        return date('Y-m-d H:i:s');
    }
}

// ============================================================================
// COMPLAINT MANAGEMENT FUNCTIONS
// ============================================================================

if (!function_exists('updateComplaintStatus')) {
    function updateComplaintStatus($conn, $complaint_id, $new_status, $remarks = null) {
        $conn->begin_transaction();
        
        try {
            $stmt = $conn->prepare("SELECT c.complaint_id, c.case_status, comp.queue_number, comp.complainant_id FROM complaints c JOIN complainants comp ON c.complainant_id = comp.complainant_id WHERE c.complaint_id = ?");
            $stmt->bind_param("i", $complaint_id);
            $stmt->execute();
            $current = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$current) throw new Exception("Complaint not found");
            
            $update = $conn->prepare("UPDATE complaints SET case_status = ?, updated_at = NOW() WHERE complaint_id = ?");
            $update->bind_param("si", $new_status, $complaint_id);
            $update->execute();
            $update->close();
            
            if ($new_status == 'processing') {
                $complainant_status = 'processing';
            } elseif ($new_status == 'completed') {
                $complainant_status = 'completed';
            } else {
                $complainant_status = 'pending';
            }
            
            $update_comp = $conn->prepare("UPDATE complainants SET status = ?, updated_at = NOW() WHERE complainant_id = ?");
            $update_comp->bind_param("si", $complainant_status, $current['complainant_id']);
            $update_comp->execute();
            $update_comp->close();
            
            if (in_array($new_status, ['visit', 'complaint', 'inquiry', 'follow-up'])) {
                updateQueueNumberPrefix($conn, $current['complainant_id'], $new_status);
            }
            
            $log_message = "Status changed from {$current['case_status']} to $new_status";
            if ($remarks) $log_message .= ". Remarks: $remarks";
            logQueueHistory($conn, $current['queue_number'], 'status_updated', $log_message);
            
            $conn->commit();
            return true;
        } catch (Exception $e) {
            $conn->rollback();
            return false;
        }
    }
}

if (!function_exists('updateQueueNumberPrefix')) {
    function updateQueueNumberPrefix($conn, $complainant_id, $new_status) {
        $prefix = '';
        switch(strtolower($new_status)) {
            case 'visit': $prefix = 'V'; break;
            case 'complaint': $prefix = 'C'; break;
            case 'inquiry': $prefix = 'I'; break;
            case 'follow-up': $prefix = 'F'; break;
            default: return false;
        }
        
        $query = "SELECT queue_number FROM complainants WHERE complainant_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $complainant_id);
        $stmt->execute();
        $current = $stmt->get_result()->fetch_assoc();
        if (!$current) return false;
        
        $current_queue = $current['queue_number'];
        if (strpos($current_queue, $prefix . '-') === 0) return true;
        
        if (preg_match('/(\d+)$/', $current_queue, $matches)) {
            $number = $matches[1];
        } else {
            $count_query = "SELECT COUNT(*) as count FROM complainants WHERE queue_number LIKE '{$prefix}-%'";
            $count_result = $conn->query($count_query);
            $count_row = $count_result->fetch_assoc();
            $number = str_pad($count_row['count'] + 1, 3, '0', STR_PAD_LEFT);
        }
        
        $new_queue_number = $prefix . '-' . str_pad(ltrim($number, '0'), 3, '0', STR_PAD_LEFT);
        
        $update = "UPDATE complainants SET queue_number = ? WHERE complainant_id = ?";
        $stmt = $conn->prepare($update);
        $stmt->bind_param("si", $new_queue_number, $complainant_id);
        
        if ($stmt->execute()) {
            $update_complaints = "UPDATE complaints SET queue_number = ? WHERE complainant_id = ?";
            $stmt2 = $conn->prepare($update_complaints);
            $stmt2->bind_param("si", $new_queue_number, $complainant_id);
            $stmt2->execute();
            return true;
        }
        return false;
    }
}

// ============================================================================
// STATISTICS FUNCTIONS
// ============================================================================

if (!function_exists('getDashboardStats')) {
    function getDashboardStats($conn) {
        $stats = [];
        $result = $conn->query("SELECT COUNT(*) as count FROM complainants WHERE DATE(created_at) = CURDATE()");
        $stats['today'] = $result ? $result->fetch_assoc()['count'] : 0;
        $result = $conn->query("SELECT COUNT(*) as count FROM complainants WHERE YEARWEEK(created_at) = YEARWEEK(CURDATE())");
        $stats['this_week'] = $result ? $result->fetch_assoc()['count'] : 0;
        $result = $conn->query("SELECT COUNT(*) as count FROM complainants WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
        $stats['this_month'] = $result ? $result->fetch_assoc()['count'] : 0;
        $result = $conn->query("SELECT COUNT(*) as count FROM complainants");
        $stats['total'] = $result ? $result->fetch_assoc()['count'] : 0;
        $result = $conn->query("SELECT COUNT(*) as count FROM complainants WHERE status = 'pending'");
        $stats['pending'] = $result ? $result->fetch_assoc()['count'] : 0;
        $result = $conn->query("SELECT COUNT(*) as count FROM complainants WHERE status = 'processing'");
        $stats['processing'] = $result ? $result->fetch_assoc()['count'] : 0;
        $result = $conn->query("SELECT COUNT(*) as count FROM complainants WHERE status = 'completed'");
        $stats['completed'] = $result ? $result->fetch_assoc()['count'] : 0;
        $result = $conn->query("SELECT COUNT(*) as count FROM complainants WHERE category = 'general_cases'");
        $stats['general'] = $result ? $result->fetch_assoc()['count'] : 0;
        $result = $conn->query("SELECT COUNT(*) as count FROM complainants WHERE category = 'womens_desk'");
        $stats['womens'] = $result ? $result->fetch_assoc()['count'] : 0;
        return $stats;
    }
}

// ============================================================================
// DIRECTORY MANAGEMENT
// ============================================================================

if (!function_exists('ensureDirectories')) {
    function ensureDirectories() {
        $directories = ['complainant_photos/', 'videos/', 'videos/uploads/'];
        foreach ($directories as $dir) {
            if (!is_dir($dir)) mkdir($dir, 0777, true);
        }
    }
}

ensureDirectories();

function getCurrentUser($conn) {
    if (isset($_SESSION['user_id'])) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) return $result->fetch_assoc();
    }
    return null;
}
?>