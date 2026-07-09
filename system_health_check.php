<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

// Then include your existing code...
require_once 'config.php';


echo "<h2>PNP ACG System Health Check</h2>";

$checks = [
    "Database Connection" => $conn->ping(),
    "Sessions Enabled" => session_status() === PHP_SESSION_ACTIVE,
    "Uploads Folder" => is_dir("complainant_photos"),
    "Video Folder" => is_dir("videos"),
];

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Check</th><th>Status</th></tr>";

foreach($checks as $label => $status){
    echo "<tr>";
    echo "<td>{$label}</td>";
    echo "<td>" . ($status ? "OK" : "FAILED") . "</td>";
    echo "</tr>";
}

echo "</table>";
?>