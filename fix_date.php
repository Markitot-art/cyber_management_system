<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

// Then include your existing code...
require_once 'config.php';

// fix_dates.php - Version without login check for first run
require_once 'config.php';

// Comment out or remove the login check for first run
// if (!isLoggedIn() || $_SESSION['role'] != 'admin') {
//     die('Access denied. Admin only.');
// }

// Add a simple password protection instead
$password_protection = true;
if ($password_protection && (!isset($_GET['key']) || $_GET['key'] != 'fix123')) {
    echo "<h2>Database Date Fix Tool</h2>";
    echo "<p>This tool requires a security key to run.</p>";
    echo "<p>To run, add ?key=fix123 to the URL.</p>";
    echo "<p>Example: <a href='?key=fix123'>Click here to run</a></p>";
    exit();
}

echo "<h2>Database Date Fix Tool</h2>";
echo "<div style='background: #fff3cd; padding: 10px; margin-bottom: 20px; border-left: 4px solid #ffc107;'>";
echo "<strong>Warning:</strong> This will modify your database. Make sure you have a backup first!";
echo "</div>";

// Fix complainants table
echo "<h3>Fixing complainants table...</h3>";
$query = "UPDATE complainants SET created_at = NOW() WHERE created_at IS NULL OR created_at = '0000-00-00 00:00:00'";
if ($conn->query($query)) {
    echo "<p style='color: green;'>✓ Fixed " . $conn->affected_rows . " complainants records</p>";
} else {
    echo "<p style='color: red;'>✗ Error: " . $conn->error . "</p>";
}

// Fix complaints table - date_reported
echo "<h3>Fixing complaints table...</h3>";
$query = "UPDATE complaints SET date_reported = created_at WHERE date_reported IS NULL OR date_reported = '0000-00-00' OR date_reported = '0000-00-00 00:00:00'";
if ($conn->query($query)) {
    echo "<p style='color: green;'>✓ Fixed " . $conn->affected_rows . " date_reported fields</p>";
} else {
    echo "<p style='color: red;'>✗ Error: " . $conn->error . "</p>";
}

$query = "UPDATE complaints SET created_at = NOW() WHERE created_at IS NULL OR created_at = '0000-00-00 00:00:00'";
if ($conn->query($query)) {
    echo "<p style='color: green;'>✓ Fixed " . $conn->affected_rows . " created_at fields</p>";
} else {
    echo "<p style='color: red;'>✗ Error: " . $conn->error . "</p>";
}

// Verify the fixes
echo "<h3>Verification:</h3>";
$query = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN date_reported IS NULL OR date_reported = '0000-00-00' OR date_reported = '0000-00-00 00:00:00' THEN 1 ELSE 0 END) as invalid_dates,
            SUM(CASE WHEN created_at IS NULL OR created_at = '0000-00-00 00:00:00' THEN 1 ELSE 0 END) as invalid_created
          FROM complaints";
$result = $conn->query($query);
$row = $result->fetch_assoc();

echo "<p>Total complaints: " . $row['total'] . "</p>";
echo "<p>Invalid date_reported: " . $row['invalid_dates'] . "</p>";
echo "<p>Invalid created_at: " . $row['invalid_created'] . "</p>";

if ($row['invalid_dates'] == 0 && $row['invalid_created'] == 0) {
    echo "<div style='color: green; padding: 10px; background: #d4edda; border-radius: 5px; margin: 10px 0;'>✓ All dates are now valid!</div>";
} else {
    echo "<div style='color: red; padding: 10px; background: #f8d7da; border-radius: 5px; margin: 10px 0;'>⚠ Some records still have invalid dates. Please check manually.</div>";
}

// Show sample data
echo "<h3>Sample Records (last 5):</h3>";
$query = "SELECT queue_number, date_reported, created_at FROM complaints ORDER BY complaint_id DESC LIMIT 5";
$result = $conn->query($query);
echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
echo "<tr style='background: #f0f0f0;'><th>Queue #</th><th>Date Reported</th><th>Created At</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . formatQueueNumber($row['queue_number']) . "</td>";
    echo "<td>" . ($row['date_reported'] ?: 'NULL') . "</td>";
    echo "<td>" . ($row['created_at'] ?: 'NULL') . "</td>";
    echo "</tr>";
}
echo "</table>";

// Add button to return to dashboard
echo "<div style='margin-top: 30px;'>";
echo "<a href='dashboard.php' style='display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Return to Dashboard</a>";
echo "</div>";
?>