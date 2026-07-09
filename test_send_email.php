<?php
require_once __DIR__ . '/config.php';

$to = SMTP_USERNAME;
$subject = 'PNP ACG SMTP Test Message';
$body = '<p>This is a test message from the local PNP ACG system.</p>';

$mailError = '';
$result = sendMail($to, $subject, $body, '', $mailError);

echo "Send result: " . ($result ? "SUCCESS" : "FAILURE") . PHP_EOL;
if (!$result) {
    echo "Error: " . $mailError . PHP_EOL;
}

// Exit code 0 for success, 1 for failure
exit($result ? 0 : 1);
