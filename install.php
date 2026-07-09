<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $full_name = trim($_POST['full_name'] ?? 'Administrator');

    if ($username === '' || $password === '') {
        die('Username and password are required.');
    }

    $stmt = $conn->prepare('SELECT user_id FROM users WHERE username = ?');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($exists) {
        die('That username already exists.');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('ssss', $username, $hash, $full_name, $role);
    $role = 'admin';
    $stmt->execute();
    $stmt->close();

    echo 'Administrator created successfully. Please delete install.php after setup.';
    exit;
}
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Install Admin</title></head>
<body>
<form method="post">
    <h2>Create Admin Account</h2>
    <input name="username" placeholder="Username" required><br>
    <input name="password" type="password" placeholder="Password" required><br>
    <input name="full_name" placeholder="Full Name" value="Administrator"><br>
    <button type="submit">Create</button>
</form>
</body>
</html>
