<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

// Then include your existing code...
require_once 'config.php';


// Save this as hash_generator.php in your project root
// DELETE THIS FILE AFTER USE!

// Put your desired password here (change this!)
$my_password = "racu5camsur123";

// Generate hash using the same method as your system
$hashed_password = password_hash($my_password, PASSWORD_DEFAULT);

// Display the hash
echo "========================================\n";
echo "Your password: " . $my_password . "\n";
echo "========================================\n";
echo "HASH TO COPY:\n";
echo $hashed_password . "\n";
echo "========================================\n";
echo "\n";
echo "Run this SQL to update admin password:\n";
echo "UPDATE users SET password = '" . $hashed_password . "' WHERE user_id = 1;\n";
echo "========================================\n";
?>