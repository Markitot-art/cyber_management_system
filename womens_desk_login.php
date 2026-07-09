<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

// Then include your existing code...
require_once 'config.php';


redirect('complaint_form.php?category=womens_desk');
exit;


