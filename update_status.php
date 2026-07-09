<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

// Then include your existing code...
require_once 'config.php';


// Helper function to log queue history
function logQueueHistory($conn, $queue_number, $action, $message) {
    $sql = "INSERT INTO queue_history (queue_number, action, message, created_at) VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("sss", $queue_number, $action, $message);
        return $stmt->execute();
    }
    return false;
}

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // FIXED: Added missing '=>' operator
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get and sanitize inputs
$complaint_id = isset($_POST['complaint_id']) ? intval($_POST['complaint_id']) : 0;
$new_status = isset($_POST['new_status']) ? trim($_POST['new_status']) : '';
$remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
$call_next = isset($_POST['call_next']) && $_POST['call_next'] == '1';
$catered_client = isset($_POST['catered_client']) ? intval($_POST['catered_client']) : 0;
$queue_number = isset($_POST['queue_number']) ? trim($_POST['queue_number']) : '';
$assigned_investigator = isset($_POST['assigned_investigator']) ? trim($_POST['assigned_investigator']) : '';

// If catered client is set, override status to 'completed'
if ($catered_client == 1) {
    $new_status = 'completed';
}

// Validate required fields
if (!$complaint_id) {
    echo json_encode(['success' => false, 'message' => 'Complaint ID is required']);
    exit();
}

if (!$new_status) {
    echo json_encode(['success' => false, 'message' => 'New status is required']);
    exit();
}

try {
    // Get current complaint data
    $query = "SELECT c.complaint_id, c.case_status, comp.complainant_id, comp.name, comp.assigned_investigator, comp.status as complainant_status, comp.queue_number
              FROM complaints c 
              JOIN complainants comp ON c.complainant_id = comp.complainant_id 
              WHERE c.complaint_id = ?";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $complaint_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current = $result->fetch_assoc();
    $stmt->close();
    
    if (!$current) {
        throw new Exception('Complaint not found');
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    // Determine the status to set based on catered or regular update
    if ($catered_client == 1) {
        // For catered clients, set both to appropriate completed/dismissed status
        $complaint_status_db = 'dismissed';
        $complainant_status = 'completed';
        
        // Update complaints table - removed resolved_at column if it doesn't exist
        $query = "UPDATE complaints SET case_status = ?, updated_at = NOW() WHERE complaint_id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("si", $complaint_status_db, $complaint_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update complaint: " . $stmt->error);
        }
        $stmt->close();
        
        // Update complainants table - removed catered_by column if it doesn't exist
        $update_complainant = "UPDATE complainants SET status = ?, updated_at = NOW() WHERE complainant_id = ?";
        $stmt = $conn->prepare($update_complainant);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("si", $complainant_status, $current['complainant_id']);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update complainant: " . $stmt->error);
        }
        $stmt->close();
        
    } else {
        // Regular status update (non-catered)
        // Map UI status to DB status
        $status_mapping = [
            'pending' => 'under_review',
            'processing' => 'under_review',
            'complaint' => 'complaint',
            'visit' => 'visit',
            'inquiry' => 'inquiry',
            'follow-up' => 'follow-up',
            'dismissed' => 'dismissed',
            'completed' => 'dismissed'
        ];
        
        $complaint_status_db = isset($status_mapping[$new_status]) ? $status_mapping[$new_status] : $new_status;
        
        // Validate against DB enum values
        $valid_case_statuses = ['visit', 'complaint', 'inquiry', 'follow-up', 'under_review', 'dismissed'];
        if (!in_array($complaint_status_db, $valid_case_statuses)) {
            throw new Exception('Invalid status value: ' . $complaint_status_db);
        }
        
        // Map complainant status
        $complainant_status = 'pending';
        switch($new_status) {
            case 'processing':
                $complainant_status = 'processing';
                break;
            case 'completed':
                $complainant_status = 'completed';
                break;
            default:
                $complainant_status = 'pending';
        }
        
        // Update complaints table
        $query = "UPDATE complaints SET case_status = ?, updated_at = NOW() WHERE complaint_id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("si", $complaint_status_db, $complaint_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update complaint: " . $stmt->error);
        }
        $stmt->close();
        
        // Update complainants table
        $update_complainant = "UPDATE complainants SET status = ?, updated_at = NOW() WHERE complainant_id = ?";
        $stmt = $conn->prepare($update_complainant);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("si", $complainant_status, $current['complainant_id']);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update complainant: " . $stmt->error);
        }
        $stmt->close();
    }
    
    // Insert into status_history if table exists (check first)
    $table_check = $conn->query("SHOW TABLES LIKE 'status_history'");
    if ($table_check && $table_check->num_rows > 0) {
        $history_sql = "INSERT INTO status_history (complaint_id, status, remarks, created_at) VALUES (?, ?, ?, NOW())";
        $history_stmt = $conn->prepare($history_sql);
        if ($history_stmt) {
            $history_stmt->bind_param("iss", $complaint_id, $new_status, $remarks);
            $history_stmt->execute();
            $history_stmt->close();
        }
    }
    
    // Log to queue history
    $log_message = $catered_client ? 
        "Client marked as CATERED. Queue number: {$current['queue_number']}. Investigator: {$current['assigned_investigator']}. Remarks: $remarks" :
        "Status changed to $new_status. Remarks: $remarks";
    
    logQueueHistory($conn, $current['queue_number'], $catered_client ? 'catered' : 'status_updated', $log_message);
    
    // Commit transaction
    $conn->commit();
    
    // Prepare response
    $message = $catered_client ? "Client marked as catered successfully!" : "Status updated successfully!";
    
    $response = [
        'success' => true,
        'message' => $message,
        'data' => [
            'complaint_id' => $complaint_id,
            'queue_number' => $current['queue_number'],
            'complainant_name' => $current['name'],
            'catered' => $catered_client == 1
        ]
    ];
    
    // Check if we need to announce
    if ($call_next) {
        $response['call_next'] = true;
        $response['queue_number'] = $current['queue_number'];
        $response['assigned_investigator'] = $current['assigned_investigator'];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn) && $conn->connect_errno === 0) {
        $conn->rollback();
    }
    
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>