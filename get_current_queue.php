<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

// Then include your existing code...
require_once 'config.php';

header('Content-Type: application/json');

// Get current queue for display
$current_queue = $conn->query("
    SELECT 
        c.complaint_id,
        c.queue_number,
        c.case_type,
        c.created_at,
        comp.name,
        comp.category,
        comp.status,
        comp.assigned_investigator
    FROM complaints c
    JOIN complainants comp ON c.complainant_id = comp.complainant_id
    WHERE comp.status IN ('pending', 'processing')
    ORDER BY 
        CASE comp.status 
            WHEN 'processing' THEN 1 
            WHEN 'pending' THEN 2 
        END,
        c.created_at ASC
");

// Get now serving (processing)
$now_serving = $conn->query("
    SELECT 
        queue_number,
        assigned_investigator,
        name as complainant_name
    FROM complainants 
    WHERE status = 'processing' 
    ORDER BY updated_at DESC 
    LIMIT 1
")->fetch_assoc();

// Get waiting count
$waiting_count = $conn->query("
    SELECT COUNT(*) as count 
    FROM complainants 
    WHERE status = 'pending'
")->fetch_assoc()['count'];

// Get processing count
$processing_count = $conn->query("
    SELECT COUNT(*) as count 
    FROM complainants 
    WHERE status = 'processing'
")->fetch_assoc()['count'];

// Build queue array
$queue = [];
if ($current_queue && $current_queue->num_rows > 0) {
    while ($row = $current_queue->fetch_assoc()) {
        $queue[] = [
            'complaint_id' => $row['complaint_id'],
            'queue_number' => $row['queue_number'],
            'name' => $row['name'],
            'category' => $row['category'],
            'case_type' => $row['case_type'],
            'status' => $row['status'],
            'investigator' => $row['assigned_investigator'],
            'created_at' => date('h:i A', strtotime($row['created_at']))
        ];
    }
}

echo json_encode([
    'success' => true,
    'queue' => $queue,
    'now_serving' => $now_serving,
    'waiting_count' => $waiting_count ? $waiting_count : 0,
    'processing_count' => $processing_count ? $processing_count : 0,
    'timestamp' => date('Y-m-d H:i:s')
]);
?>