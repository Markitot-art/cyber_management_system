<?php
require_once 'config.php';

echo "<h2>Email Test</h2>";

$test_email = "your-email@gmail.com"; // CHANGE THIS TO YOUR EMAIL

echo "<p>Sending test to: <strong>$test_email</strong></p>";

$error = '';
if (sendMail($test_email, "Test Email", "<h3>Test</h3><p>Email works! Time: " . date('H:i:s') . "</p>", "Test email", $error)) {
    echo "<p style='color:green; font-weight:bold;'>✓ EMAIL SENT SUCCESSFULLY!</p>";
} else {
    echo "<p style='color:red; font-weight:bold;'>✗ EMAIL FAILED!</p>";
    echo "<p>Error: " . htmlspecialchars($error) . "</p>";
}
?>