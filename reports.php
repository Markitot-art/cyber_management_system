<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

require_once 'config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

// Get filter parameters with proper sanitization
$date_from = isset($_GET['date_from']) && !empty($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) && !empty($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Validate dates
$date_from = date('Y-m-d', strtotime($date_from));
$date_to = date('Y-m-d', strtotime($date_to));

// Ensure date_from is not greater than date_to
if ($date_from > $date_to) {
    $temp = $date_from;
    $date_from = $date_to;
    $date_to = $temp;
}

// Build query conditions with prepared statements
$conditions = ["DATE(c.date_reported) BETWEEN ? AND ?"];
$params = [$date_from, $date_to];
$types = "ss";

if (!empty($category_filter)) {
    $conditions[] = "comp.category = ?";
    $params[] = $category_filter;
    $types .= "s";
}
if (!empty($status_filter)) {
    $conditions[] = "c.case_status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$where_clause = implode(' AND ', $conditions);

// Get ALL complaints with details (NO STATUS RESTRICTION - includes all records)
$query = "
    SELECT 
        c.complaint_id,
        c.queue_number,
        c.case_type,
        c.case_status,
        c.date_reported,
        c.incident_date,
        c.incident_time,
        c.created_at,
        comp.name as complainant_name, 
        comp.contact_number,
        comp.address,
        comp.category,
        comp.assigned_investigator,
        comp.created_at as filing_date,
        comp.photo_path,
        comp.is_priority,
        u.full_name as reported_by_name
    FROM complaints c
    JOIN complainants comp ON c.complainant_id = comp.complainant_id
    LEFT JOIN users u ON c.reported_by = u.user_id
    WHERE $where_clause AND comp.category != 'visit'
    ORDER BY c.date_reported DESC, c.created_at DESC
";

$reports = null;
$stmt = $conn->prepare($query);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $reports = $stmt->get_result();
} else {
    $reports = $conn->query($query);
}

// Get VISIT RECORDS only
$visit_query = "
    SELECT 
        c.complaint_id,
        c.queue_number,
        c.date_reported,
        c.created_at,
        comp.name as visitor_name, 
        comp.contact_number,
        comp.address,
        comp.category,
        comp.assigned_investigator,
        comp.photo_path,
        comp.visit_reason,
        u.full_name as reported_by_name
    FROM complaints c
    JOIN complainants comp ON c.complainant_id = comp.complainant_id
    LEFT JOIN users u ON c.reported_by = u.user_id
    WHERE $where_clause AND comp.category = 'visit'
    ORDER BY c.date_reported DESC, c.created_at DESC
";

$visit_reports = null;
$stmt = $conn->prepare($visit_query);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $visit_reports = $stmt->get_result();
} else {
    $visit_reports = $conn->query($visit_query);
}

// Get summary statistics with prepared statement
$stats_query = "
    SELECT 
        COUNT(*) as total_cases,
        SUM(CASE WHEN comp.category = 'general_cases' THEN 1 ELSE 0 END) as general_cases,
        SUM(CASE WHEN comp.category = 'womens_desk' THEN 1 ELSE 0 END) as womens_cases,
        SUM(CASE WHEN comp.category = 'visit' THEN 1 ELSE 0 END) as visit_count,
        SUM(CASE WHEN c.case_status = 'complaint' THEN 1 ELSE 0 END) as complaint_count,
        SUM(CASE WHEN c.case_status = 'inquiry' THEN 1 ELSE 0 END) as inquiry_count,
        SUM(CASE WHEN c.case_status = 'follow-up' THEN 1 ELSE 0 END) as followup_count
    FROM complaints c
    JOIN complainants comp ON c.complainant_id = comp.complainant_id
    WHERE $where_clause
";

$stats = [];
$stmt = $conn->prepare($stats_query);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $stats_result = $stmt->get_result();
    $stats = $stats_result->fetch_assoc();
} else {
    $stats_result = $conn->query($stats_query);
    $stats = $stats_result ? $stats_result->fetch_assoc() : [];
}

// Set default stats if empty
if (!$stats) {
    $stats = [
        'total_cases' => 0,
        'general_cases' => 0,
        'womens_cases' => 0,
        'visit_count' => 0,
        'complaint_count' => 0,
        'inquiry_count' => 0,
        'followup_count' => 0
    ];
}

// Get detailed breakdown for Total Cases modal (all case types, excluding visit)
$total_breakdown_query = "
    SELECT 
        c.case_type,
        COUNT(*) as case_count
    FROM complaints c
    JOIN complainants comp ON c.complainant_id = comp.complainant_id
    WHERE $where_clause AND comp.category != 'visit' AND c.case_type IS NOT NULL AND c.case_type != ''
    GROUP BY c.case_type
    ORDER BY case_count DESC
";
$total_breakdown = null;
$stmt = $conn->prepare($total_breakdown_query);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $total_breakdown = $stmt->get_result();
} else {
    $total_breakdown = $conn->query($total_breakdown_query);
}

// Get detailed breakdown for General Cases modal
$general_breakdown_query = "
    SELECT 
        c.case_type,
        COUNT(*) as case_count
    FROM complaints c
    JOIN complainants comp ON c.complainant_id = comp.complainant_id
    WHERE $where_clause AND comp.category = 'general_cases' AND c.case_type IS NOT NULL AND c.case_type != ''
    GROUP BY c.case_type
    ORDER BY case_count DESC
";
$general_breakdown = null;
$stmt = $conn->prepare($general_breakdown_query);
if ($stmt) {
    $gen_params = array_merge([$date_from, $date_to], !empty($status_filter) ? [$status_filter] : []);
    $gen_types = "ss" . (!empty($status_filter) ? "s" : "");
    if (!empty($status_filter)) {
        $stmt->bind_param($gen_types, $date_from, $date_to, $status_filter);
    } else {
        $stmt->bind_param($gen_types, $date_from, $date_to);
    }
    $stmt->execute();
    $general_breakdown = $stmt->get_result();
} else {
    $general_breakdown = $conn->query($general_breakdown_query);
}

// Get detailed breakdown for Women's Desk modal
$womens_breakdown_query = "
    SELECT 
        c.case_type,
        COUNT(*) as case_count
    FROM complaints c
    JOIN complainants comp ON c.complainant_id = comp.complainant_id
    WHERE $where_clause AND comp.category = 'womens_desk' AND c.case_type IS NOT NULL AND c.case_type != ''
    GROUP BY c.case_type
    ORDER BY case_count DESC
";
$womens_breakdown = null;
$stmt = $conn->prepare($womens_breakdown_query);
if ($stmt) {
    $women_params = array_merge([$date_from, $date_to], !empty($status_filter) ? [$status_filter] : []);
    $women_types = "ss" . (!empty($status_filter) ? "s" : "");
    if (!empty($status_filter)) {
        $stmt->bind_param($women_types, $date_from, $date_to, $status_filter);
    } else {
        $stmt->bind_param($women_types, $date_from, $date_to);
    }
    $stmt->execute();
    $womens_breakdown = $stmt->get_result();
} else {
    $womens_breakdown = $conn->query($womens_breakdown_query);
}

// Get investigators performance with daily, weekly, monthly breakdown
$investigators_query = "
    SELECT 
        comp.assigned_investigator,
        COUNT(*) as total_cases,
        SUM(CASE WHEN DATE(c.date_reported) = CURDATE() THEN 1 ELSE 0 END) as daily_cases,
        SUM(CASE WHEN YEARWEEK(c.date_reported, 1) = YEARWEEK(CURDATE(), 1) THEN 1 ELSE 0 END) as weekly_cases,
        SUM(CASE WHEN MONTH(c.date_reported) = MONTH(CURDATE()) AND YEAR(c.date_reported) = YEAR(CURDATE()) THEN 1 ELSE 0 END) as monthly_cases
    FROM complaints c
    JOIN complainants comp ON c.complainant_id = comp.complainant_id
    WHERE $where_clause AND comp.assigned_investigator IS NOT NULL AND comp.assigned_investigator != ''
    GROUP BY comp.assigned_investigator
    ORDER BY total_cases DESC
";

$investigators_perf = null;
$stmt = $conn->prepare($investigators_query);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $investigators_perf = $stmt->get_result();
} else {
    $investigators_perf = $conn->query($investigators_query);
}

// Get daily trend data for chart
$daily_trend_query = "
    SELECT 
        DATE(c.date_reported) as report_date,
        COUNT(*) as daily_count,
        SUM(CASE WHEN comp.category = 'general_cases' THEN 1 ELSE 0 END) as general_daily,
        SUM(CASE WHEN comp.category = 'womens_desk' THEN 1 ELSE 0 END) as womens_daily
    FROM complaints c
    JOIN complainants comp ON c.complainant_id = comp.complainant_id
    WHERE $where_clause
    GROUP BY DATE(c.date_reported)
    ORDER BY report_date ASC
";

$daily_trend = null;
$stmt = $conn->prepare($daily_trend_query);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $daily_trend = $stmt->get_result();
} else {
    $daily_trend = $conn->query($daily_trend_query);
}

// Helper function for safe date formatting
function safeDateFormat($date, $format = 'Y-m-d') {
    if (empty($date) || $date == '0000-00-00' || $date == '0000-00-00 00:00:00') {
        return 'N/A';
    }
    try {
        $timestamp = strtotime($date);
        if ($timestamp === false || $timestamp <= 0) {
            return 'N/A';
        }
        return date($format, $timestamp);
    } catch (Exception $e) {
        return 'N/A';
    }
}

// Helper function to format queue number
function formatQueueNumberReport($queue_number) {
    if (empty($queue_number)) {
        return 'N/A';
    }
    if (is_numeric($queue_number)) {
        return str_pad($queue_number, 3, '0', STR_PAD_LEFT);
    }
    if (preg_match('/(\d+)/', $queue_number, $matches)) {
        return str_pad($matches[1], 3, '0', STR_PAD_LEFT);
    }
    return $queue_number;
}

// Helper function to get photo path
function getPhotoPathReport($photo_path) {
    if (!empty($photo_path) && file_exists('complainant_photos/' . $photo_path)) {
        return 'complainant_photos/' . $photo_path;
    }
    return 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'50\' height=\'50\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%23999\' stroke-width=\'1\' stroke-linecap=\'round\' stroke-linejoin=\'round\'%3E%3Crect x=\'2\' y=\'2\' width=\'20\' height=\'20\' rx=\'2.18\' ry=\'2.18\'%3E%3C/rect%3E%3Ccircle cx=\'8.5\' cy=\'8.5\' r=\'1.5\'%3E%3C/circle%3E%3Cpolyline points=\'22 15 16 9 6 19 2 15\'%3E%3C/polyline%3E%3C/svg%3E';
}

// Get category title for display
$category_display = '';
if ($category_filter == 'general_cases') {
    $category_display = 'General Cases';
} elseif ($category_filter == 'womens_desk') {
    $category_display = "Women's Desk";
}

// Get status display
$status_display = '';
if (!empty($status_filter)) {
    $status_display = ucwords(str_replace('-', ' ', $status_filter));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Cybercrime System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .sidebar {
            background: linear-gradient(165deg, #4d86c7 0%, #17314a 100%);
            min-height: 100vh;
            color: white;
            position: fixed;
            width: 250px;
            box-shadow: 4px 0 20px rgba(0,20,40,0.2);
            z-index: 1050;
        }
        .sidebar-logo {
            padding: 20px 24px 10px 24px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 10px;
        }
        .logo-frame {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            padding: 5px;
            background: rgba(255, 255, 255, 0.05);
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 8px auto;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: none;
        }
        .logo-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        .logo-text {
            font-size: 0.8rem;
            opacity: 0.7;
            letter-spacing: 1px;
        }
        .sidebar a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.25s;
            border-left: 4px solid transparent;
        }
        .sidebar a i {
            font-size: 1.25rem;
            width: 24px;
        }
        .sidebar a:hover, .sidebar a.active {
            background: rgba(255,255,255,0.12);
            color: white;
            border-left-color: #ffc107;
        }
        .sidebar .system-badge {
            position: absolute;
            bottom: 25px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 0.7rem;
            opacity: 0.5;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .report-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 20px;
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
        }
        .stat-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }
        .stat-box h3 {
            font-size: 32px;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .stat-box p {
            margin-bottom: 0;
            opacity: 0.9;
            font-size: 14px;
        }
        .stat-box.general {
            background: #4299e1;
        }
        .stat-box.womens {
            background: #ed64a6;
        }
        .stat-box.visit {
            background: #38b2ac;
        }
        .badge-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: normal;
        }
        .queue-number {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            background: #f8f9fa;
            padding: 3px 8px;
            border-radius: 5px;
            display: inline-block;
        }
        .progress {
            height: 25px;
            margin-bottom: 5px;
        }
        .progress-bar {
            line-height: 25px;
            color: #333;
            font-weight: bold;
        }
        .photo-thumbnail {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #667eea;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .photo-thumbnail:hover {
            transform: scale(1.1);
        }
        .photo-modal-img {
            max-width: 100%;
            max-height: 80vh;
            border-radius: 10px;
        }
        
        .case-types-container {
            max-height: 200px;
            overflow-y: auto;
        }
        .case-types-container .table {
            margin-bottom: 0;
        }
        
        .visit-reason-preview {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Print styles */
        @media print {
            .no-print, .sidebar, .btn, .see-more-btn, .modal, .modal-backdrop {
                display: none !important;
            }
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            .report-card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
                break-inside: avoid;
                page-break-inside: avoid;
            }
            .stat-box {
                background: #f0f0f0 !important;
                color: black !important;
                border: 1px solid #ddd !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .badge-status {
                border: 1px solid #000 !important;
                color: #000 !important;
                background: #fff !important;
            }
            .table {
                font-size: 9pt;
            }
            @page {
                size: A4 landscape;
                margin: 1cm;
            }
            .print-header, .print-footer {
                display: block !important;
                text-align: center;
                margin-bottom: 20px;
            }
            .print-footer {
                margin-top: 20px;
                font-size: 9pt;
                color: #666;
            }
        }
        .print-header, .print-footer {
            display: none;
        }
        
        .trend-row-hidden, .case-row-hidden, .visit-row-hidden {
            display: none;
        }
        .see-more-btn {
            cursor: pointer;
            color: #0d6efd;
            text-decoration: none;
            font-weight: 500;
        }
        .see-more-btn:hover {
            text-decoration: underline;
        }
        
        .breakdown-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .breakdown-list li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .breakdown-list li:last-child {
            border-bottom: none;
        }
        .breakdown-type {
            font-weight: 500;
            color: #333;
        }
        .breakdown-count {
            background: #667eea;
            color: white;
            padding: 2px 10px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
        }
        
        /* Modal checkbox styles */
        .modal-checkbox-group {
            margin-bottom: 12px;
        }
        .modal-checkbox-group .form-check {
            margin-bottom: 8px;
        }
        .section-group {
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }
        .section-group:last-child {
            border-bottom: none;
        }

        /* Professional Date Search Bar Styles */
        .date-search-card {
            background: white;
            border-radius: 12px;
            padding: 18px 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
        }
        .date-search-label {
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #4a5568;
            margin-bottom: 8px;
        }
        .date-search-input {
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            padding: 10px 15px;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        .date-search-input:focus {
            border-color: #4d86c7;
            box-shadow: 0 0 0 3px rgba(77, 134, 199, 0.1);
            outline: none;
        }
        .search-btn {
            background: linear-gradient(135deg, #4d86c7 0%, #17314a 100%);
            border: none;
            border-radius: 8px;
            padding: 10px 25px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .search-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(77, 134, 199, 0.3);
        }
        .reset-btn {
            background: #6c757d;
            border: none;
            border-radius: 8px;
            padding: 10px 25px;
            font-weight: 500;
        }
        .reset-btn:hover {
            background: #5a6268;
        }
        .date-range-badge {
            background: #e9ecef;
            border-radius: 20px;
            padding: 5px 12px;
            font-size: 0.8rem;
            color: #2d3748;
        }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- SIDEBAR -->
            <div class="col-auto p-0 sidebar">
                <div class="sidebar-logo">
                    <div class="logo-frame">
                        <img src="videos/uploads/cyberlogo.png" alt="ACG NAGA Logo" class="logo-img" onerror="this.src='https://via.placeholder.com/100?text=ACG'">
                    </div>
                    <div class="logo-text">CSPCRT</div>
                </div>
                <nav>
                    <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
                    <a href="category_selection.php"><i class="bi bi-plus-circle"></i> New Complaint</a>
                    <a href="queue.php"><i class="bi bi-list-ol"></i> Queue Management</a>
                    <a href="records.php"><i class="bi bi-folder"></i> Records</a>
                    <a href="reports.php" class="active"><i class="bi bi-bar-chart"></i> Reports</a>
                    <a href="#videoSubmenu" data-bs-toggle="collapse" class="d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-camera-reels"></i> Video Manager</span>
                        <i class="bi bi-chevron-down"></i>
                    </a>
                    <div class="collapse" id="videoSubmenu">
                        <a href="video_manager.php"><i class="bi bi-upload"></i> Upload Videos</a>
                        <a href="tv_display.php" target="_blank"><i class="bi bi-play-circle"></i> View Display</a>
                    </div>
                    <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
                </nav>
                <div class="system-badge">
                    <i class="bi bi-shield-check"></i> PNP ACG · v2.0
                </div>
            </div>

            <!-- Main Content -->
            <div class="col main-content">
                <!-- Print Header -->
                <div class="print-header">
                    <h2>PHILIPPINE NATIONAL POLICE</h2>
                    <h4>Anti-Cybercrime Group</h4>
                    <h5>Case Report</h5>
                    <p>Period: <?php echo date('F d, Y', strtotime($date_from)); ?> - <?php echo date('F d, Y', strtotime($date_to)); ?></p>
                    <?php if (!empty($category_display)): ?>
                    <p>Category: <?php echo $category_display; ?></p>
                    <?php endif; ?>
                    <?php if (!empty($status_display)): ?>
                    <p>Status: <?php echo $status_display; ?></p>
                    <?php endif; ?>
                    <hr>
                </div>
                
                <!-- ========== PROFESSIONAL DATE SEARCH BAR ========== -->
                <div class="date-search-card no-print">
                    <form method="GET" action="" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <div class="date-search-label">FROM DATE</div>
                            <input type="text" class="form-control date-search-input" id="date_from_picker" name="date_from" 
                                   value="<?php echo htmlspecialchars($date_from); ?>" placeholder="Select start date" autocomplete="off">
                        </div>
                        <div class="col-md-4">
                            <div class="date-search-label">TO DATE</div>
                            <input type="text" class="form-control date-search-input" id="date_to_picker" name="date_to" 
                                   value="<?php echo htmlspecialchars($date_to); ?>" placeholder="Select end date" autocomplete="off">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn search-btn text-white w-100">
                                <i class="bi bi-search me-1"></i> Search
                            </button>
                        </div>
                        <div class="col-md-2">
                            <a href="reports.php" class="btn reset-btn text-white w-100">
                                <i class="bi bi-arrow-repeat me-1"></i> Reset
                            </a>
                        </div>
                    </form>
                    <div class="mt-3 d-flex justify-content-end">
                        <span class="date-range-badge">
                            <i class="bi bi-calendar-range me-1"></i> 
                            <?php echo date('M d, Y', strtotime($date_from)); ?> - <?php echo date('M d, Y', strtotime($date_to)); ?>
                        </span>
                    </div>
                </div>
                
                <!-- HEADER WITH BUTTONS - ON TOP (Selection panel removed, now opens modal) -->
                <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                    <h2><i class="bi bi-bar-chart"></i> Reports & Analytics</h2>
                    <div>
                        <button onclick="openPrintModal()" class="btn btn-primary" style="background-color: #0d6efd; border-color: #0d6efd;">
                            <i class="bi bi-printer"></i> Customize Print/Export
                        </button>
                        <button onclick="window.print()" class="btn btn-secondary ms-2">
                            <i class="bi bi-printer"></i> Print All
                        </button>
                    </div>
                </div>
                
                <!-- ========== SECTION 1: Summary Statistics ========== -->
                <div class="row mb-4" id="summaryStatsSection">
                    <div class="col-md-3">
                        <div class="stat-box" onclick="showBreakdownModal('total', 'Total Cases', <?php echo $stats['total_cases'] ?? 0; ?>)">
                            <h3><?php echo number_format($stats['total_cases'] ?? 0); ?></h3>
                            <p>Total Cases</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box general" onclick="showBreakdownModal('general', 'General Cases', <?php echo $stats['general_cases'] ?? 0; ?>)">
                            <h3><?php echo number_format($stats['general_cases'] ?? 0); ?></h3>
                            <p>General Cases</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box womens" onclick="showBreakdownModal('womens', 'Women\'s Desk', <?php echo $stats['womens_cases'] ?? 0; ?>)">
                            <h3><?php echo number_format($stats['womens_cases'] ?? 0); ?></h3>
                            <p>Women's Desk</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box visit">
                            <h3><?php echo number_format($stats['visit_count'] ?? 0); ?></h3>
                            <p>Visits</p>
                        </div>
                    </div>
                </div>
                
                <!-- ========== SECTION 2 & 3: Status Breakdown & Case Types ========== -->
                <div class="row">
                    <div class="col-md-6" id="statusBreakdownSection">
                        <div class="report-card">
                            <h5 class="mb-3"><i class="bi bi-pie-chart"></i> Status Breakdown</h5>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <tbody>
                                        <?php
                                        $status_items = [
                                            'complaint' => ['label' => 'Complaint', 'count' => $stats['complaint_count'], 'color' => 'success'],
                                            'inquiry' => ['label' => 'Inquiry', 'count' => $stats['inquiry_count'], 'color' => 'secondary'],
                                            'follow-up' => ['label' => 'Follow-up', 'count' => $stats['followup_count'], 'color' => 'primary']
                                        ];
                                        
                                        $total_for_percent = ($stats['complaint_count'] + $stats['inquiry_count'] + $stats['followup_count']);
                                        $total_for_percent = $total_for_percent > 0 ? $total_for_percent : 1;
                                        
                                        foreach ($status_items as $key => $item):
                                            $percent = round(($item['count'] / $total_for_percent) * 100);
                                        ?>
                                        <tr>
                                            <td width="30%"><?php echo $item['label']; ?></td>
                                            <td>
                                                <div class="progress">
                                                    <div class="progress-bar bg-<?php echo $item['color']; ?>" style="width: <?php echo $percent; ?>%">
                                                        <?php echo $item['count']; ?> (<?php echo $percent; ?>%)
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6" id="caseTypesSection">
                        <div class="report-card">
                            <h5 class="mb-3"><i class="bi bi-bar-chart"></i> Case Types</h5>
                            <div class="case-types-container">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Case Type</th>
                                                <th>Count</th>
                                                <th>Percentage</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $case_types_fix_query = "
                                                SELECT 
                                                    c.case_type,
                                                    COUNT(*) as case_count
                                                FROM complaints c
                                                JOIN complainants comp ON c.complainant_id = comp.complainant_id
                                                WHERE $where_clause AND comp.category != 'visit' AND c.case_type IS NOT NULL AND c.case_type != ''
                                                GROUP BY c.case_type
                                                ORDER BY case_count DESC
                                            ";
                                            $case_types_fix = null;
                                            $stmt = $conn->prepare($case_types_fix_query);
                                            if ($stmt) {
                                                if (!empty($params)) {
                                                    $stmt->bind_param($types, ...$params);
                                                }
                                                $stmt->execute();
                                                $case_types_fix = $stmt->get_result();
                                            } else {
                                                $case_types_fix = $conn->query($case_types_fix_query);
                                            }
                                            
                                            $total_non_visit_cases = 0;
                                            $case_types_array_fix = [];
                                            if ($case_types_fix && $case_types_fix->num_rows > 0) {
                                                while($type = $case_types_fix->fetch_assoc()) {
                                                    $case_types_array_fix[] = $type;
                                                    $total_non_visit_cases += $type['case_count'];
                                                }
                                            }
                                            
                                            if (!empty($case_types_array_fix)):
                                                foreach($case_types_array_fix as $type):
                                                    $percent = $total_non_visit_cases > 0 ? round(($type['case_count'] / $total_non_visit_cases) * 100, 1) : 0;
                                            ?>
                                            <tr>
                                                <td><?php echo str_replace('_', ' ', ucwords($type['case_type'] ?? 'N/A')); ?></td>
                                                <td><?php echo $type['case_count']; ?></td>
                                                <td>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar bg-info" style="width: <?php echo $percent; ?>%">
                                                            <?php echo $percent; ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php 
                                                endforeach;
                                            else:
                                            ?>
                                            <tr>
                                                <td colspan="3" class="text-center">No case types available</td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- ========== SECTION 4: Daily Trend ========== -->
                <?php 
                $daily_trend_data = [];
                if ($daily_trend && $daily_trend->num_rows > 0) {
                    while($trend = $daily_trend->fetch_assoc()) {
                        $daily_trend_data[] = $trend;
                    }
                }
                $total_trend_rows = count($daily_trend_data);
                $show_trend_limit = 5;
                $has_more_trend = $total_trend_rows > $show_trend_limit;
                ?>
                <?php if ($total_trend_rows > 0): ?>
                <div class="report-card" id="dailyTrendSection">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"><i class="bi bi-graph-up"></i> Daily Case Trend</h5>
                        <?php if ($has_more_trend): ?>
                        <a href="javascript:void(0)" class="see-more-btn" onclick="toggleTrendTable()">
                            <i class="bi bi-eye"></i> See More
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered" id="trendTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Total Cases</th>
                                    <th>General Cases</th>
                                    <th>Women's Desk</th>
                                    <th>Trend</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $max_daily = 0;
                                foreach($daily_trend_data as $trend) {
                                    if ($trend['daily_count'] > $max_daily) $max_daily = $trend['daily_count'];
                                }
                                $row_index = 0;
                                foreach($daily_trend_data as $trend):
                                    $trend_percent = $max_daily > 0 ? round(($trend['daily_count'] / $max_daily) * 100) : 0;
                                    $is_hidden = $has_more_trend && $row_index >= $show_trend_limit;
                                ?>
                                <tr class="trend-row <?php echo $is_hidden ? 'trend-row-hidden' : ''; ?>">
                                    <td><?php echo safeDateFormat($trend['report_date'], 'M d, Y'); ?></td>
                                    <td><strong><?php echo $trend['daily_count']; ?></strong></td>
                                    <td><?php echo $trend['general_daily']; ?></td>
                                    <td><?php echo $trend['womens_daily']; ?></td>
                                    <td width="30%">
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-primary" style="width: <?php echo $trend_percent; ?>%">
                                                <?php echo $trend_percent; ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php 
                                $row_index++;
                                endforeach; 
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- ========== SECTION 5: Investigator Case Handled ========== -->
                <div class="report-card" id="investigatorSection">
                    <h5 class="mb-3"><i class="bi bi-people"></i> Investigator Case Handled</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Investigator</th>
                                    <th>Total Cases</th>
                                    <th>Daily</th>
                                    <th>Weekly</th>
                                    <th>Monthly</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($investigators_perf && $investigators_perf->num_rows > 0): ?>
                                    <?php while($inv = $investigators_perf->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($inv['assigned_investigator'] ?? 'Unassigned'); ?></strong></td>
                                        <td><?php echo $inv['total_cases']; ?></td>
                                        <td><span class="badge bg-info"><?php echo $inv['daily_cases'] ?? 0; ?></span></td>
                                        <td><span class="badge bg-primary"><?php echo $inv['weekly_cases'] ?? 0; ?></span></td>
                                        <td><span class="badge bg-secondary"><?php echo $inv['monthly_cases'] ?? 0; ?></span></td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No data available</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- ========== SECTION 6: Detailed Cases Table ========== -->
                <?php 
                $detailed_cases_data = [];
                if ($reports && $reports->num_rows > 0) {
                    while($row = $reports->fetch_assoc()) {
                        $detailed_cases_data[] = $row;
                    }
                }
                $total_cases_rows = count($detailed_cases_data);
                $show_cases_limit = 5;
                $has_more_cases = $total_cases_rows > $show_cases_limit;
                ?>
                <div class="report-card" id="detailedCasesSection">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"><i class="bi bi-list-ul"></i> Detailed Case List (All Records)</h5>
                        <?php if ($has_more_cases): ?>
                        <a href="javascript:void(0)" class="see-more-btn" onclick="toggleCasesTable()">
                            <i class="bi bi-eye"></i> See More
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="casesTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Photo</th>
                                    <th>Queue #</th>
                                    <th>Date Reported</th>
                                    <th>Complainant</th>
                                    <th>Category</th>
                                    <th>Case Type</th>
                                    <th>Status</th>
                                    <th>Investigator</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($total_cases_rows > 0): ?>
                                    <?php 
                                    $case_index = 0;
                                    foreach($detailed_cases_data as $row): 
                                        $photo_src = getPhotoPathReport($row['photo_path'] ?? '');
                                        $is_hidden = $has_more_cases && $case_index >= $show_cases_limit;
                                    ?>
                                    <tr class="case-row <?php echo $is_hidden ? 'case-row-hidden' : ''; ?>">
                                        <td class="text-center">
                                            <img src="<?php echo $photo_src; ?>" 
                                                 class="photo-thumbnail" 
                                                 alt="Complainant Photo"
                                                 data-bs-toggle="modal" 
                                                 data-bs-target="#photoModal"
                                                 data-photo-src="<?php echo $photo_src; ?>"
                                                 data-complainant-name="<?php echo htmlspecialchars($row['complainant_name']); ?>"
                                                 data-queue-number="<?php echo formatQueueNumberReport($row['queue_number']); ?>"
                                                 onclick="showPhotoModal(this)"
                                                 style="cursor: pointer;">
                                        </td>
                                        <td>
                                            <span class="queue-number">
                                                <?php echo formatQueueNumberReport($row['queue_number']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo safeDateFormat($row['date_reported'] ?? $row['created_at'] ?? 'now', 'Y-m-d H:i'); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['complainant_name']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($row['contact_number']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo ($row['category'] == 'womens_desk') ? 'danger' : 'primary'; ?> badge-status">
                                                <?php echo ($row['category'] == 'womens_desk') ? "Women's Desk" : 'General'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars(substr($row['case_type'] ?? 'N/A', 0, 50)); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo ($row['case_status'] == 'complaint') ? 'success' : 
                                                    (($row['case_status'] == 'follow-up') ? 'warning' : 
                                                    (($row['case_status'] == 'resolved' || $row['case_status'] == 'closed') ? 'secondary' : 'info')); 
                                            ?> badge-status">
                                                <?php echo ucwords(str_replace('-', ' ', $row['case_status'] ?? 'N/A')); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['assigned_investigator'] ?? 'Unassigned'); ?></td>
                                    </tr>
                                    <?php 
                                    $case_index++;
                                    endforeach; 
                                    ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                                            <p class="mt-2">No case records found for the selected criteria</p>
                                            <small class="text-muted">Try adjusting your filter settings</small>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- ========== SECTION 7: Visit Records List Table ========== -->
                <?php 
                $visit_records_data = [];
                if ($visit_reports && $visit_reports->num_rows > 0) {
                    while($row = $visit_reports->fetch_assoc()) {
                        $visit_records_data[] = $row;
                    }
                }
                $total_visit_rows = count($visit_records_data);
                $show_visit_limit = 5;
                $has_more_visits = $total_visit_rows > $show_visit_limit;
                ?>
                <div class="report-card" id="visitRecordsSection">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"><i class="bi bi-person-walking"></i> Visit Records List</h5>
                        <?php if ($has_more_visits): ?>
                        <a href="javascript:void(0)" class="see-more-btn" onclick="toggleVisitTable()">
                            <i class="bi bi-eye"></i> See More
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="visitTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Photo</th>
                                    <th>Queue #</th>
                                    <th>Date of Visit</th>
                                    <th>Visitor Name</th>
                                    <th>Contact</th>
                                    <th>Address</th>
                                    <th>Reason for Visit</th>
                                    <th>Assigned To</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($total_visit_rows > 0): ?>
                                    <?php 
                                    $visit_index = 0;
                                    foreach($visit_records_data as $row): 
                                        $photo_src = getPhotoPathReport($row['photo_path'] ?? '');
                                        $is_hidden = $has_more_visits && $visit_index >= $show_visit_limit;
                                    ?>
                                    <tr class="visit-row <?php echo $is_hidden ? 'visit-row-hidden' : ''; ?>">
                                        <td class="text-center">
                                            <img src="<?php echo $photo_src; ?>" 
                                                 class="photo-thumbnail" 
                                                 alt="Visitor Photo"
                                                 data-bs-toggle="modal" 
                                                 data-bs-target="#photoModal"
                                                 data-photo-src="<?php echo $photo_src; ?>"
                                                 data-complainant-name="<?php echo htmlspecialchars($row['visitor_name']); ?>"
                                                 data-queue-number="<?php echo formatQueueNumberReport($row['queue_number']); ?>"
                                                 onclick="showPhotoModal(this)"
                                                 style="cursor: pointer;">
                                        </td>
                                        <td>
                                            <span class="queue-number">
                                                <?php echo formatQueueNumberReport($row['queue_number']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo safeDateFormat($row['date_reported'] ?? $row['created_at'] ?? 'now', 'Y-m-d H:i'); ?></td>
                                        <td><strong><?php echo htmlspecialchars($row['visitor_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['contact_number']); ?></td>
                                        <td><?php echo htmlspecialchars($row['address']); ?></td>
                                        <td class="visit-reason-preview" title="<?php echo htmlspecialchars($row['visit_reason']); ?>">
                                            <?php echo nl2br(htmlspecialchars(substr($row['visit_reason'] ?? '', 0, 100))) . (strlen($row['visit_reason'] ?? '') > 100 ? '...' : ''); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['assigned_investigator'] ?? 'Unassigned'); ?></td>
                                    </tr>
                                    <?php 
                                    $visit_index++;
                                    endforeach; 
                                    ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <i class="bi bi-calendar-x" style="font-size: 2rem;"></i>
                                            <p class="mt-2">No visit records found for the selected criteria</p>
                                            <small class="text-muted">Visit records will appear here when submitted</small>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Print Footer -->
                <div class="print-footer">
                    <hr>
                    <p>Generated on: <?php echo date('F d, Y h:i A'); ?> | PNP Anti-Cybercrime Group</p>
                    <p><em>This report is computer-generated and valid without signature</em></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ========== SELECTION MODAL (POPUP) ========== -->
    <div class="modal fade" id="printSelectionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <h5 class="modal-title">
                        <i class="bi bi-check2-square"></i> Select Sections to Print/Export
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="section-group">
                        <div class="modal-checkbox-group">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="modal_chk_summary" checked>
                                <label class="form-check-label" for="modal_chk_summary">
                                    📊 Summary Statistics
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="modal_chk_status" checked>
                                <label class="form-check-label" for="modal_chk_status">
                                    📈 Status Breakdown
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="modal_chk_caseTypes" checked>
                                <label class="form-check-label" for="modal_chk_caseTypes">
                                    📋 Case Types
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="modal_chk_dailyTrend" checked>
                                <label class="form-check-label" for="modal_chk_dailyTrend">
                                    📉 Daily Trend
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="modal_chk_investigators" checked>
                                <label class="form-check-label" for="modal_chk_investigators">
                                    👥 Investigator Cases
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="modal_chk_detailedCases" checked>
                                <label class="form-check-label" for="modal_chk_detailedCases">
                                    📑 Detailed Cases
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="modal_chk_visitRecords" checked>
                                <label class="form-check-label" for="modal_chk_visitRecords">
                                    🚶 Visit Records
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="javascript:void(0)" onclick="modalSelectAll(true)" class="text-decoration-none me-3">✅ Select All</a>
                        <a href="javascript:void(0)" onclick="modalSelectAll(false)" class="text-decoration-none">❌ Deselect All</a>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="printFromModal()">
                        <i class="bi bi-printer"></i> Print Selected
                    </button>
                    <button type="button" class="btn btn-success" onclick="exportFromModal()">
                        <i class="bi bi-file-excel"></i> Export Selected
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Breakdown Modal -->
    <div class="modal fade" id="breakdownModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <h5 class="modal-title">
                        <i class="bi bi-pie-chart"></i> <span id="modalTitle">Case Breakdown</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Total Cases:</strong> <span id="modalTotalCount">0</span></p>
                    <hr>
                    <h6>Case Types Breakdown:</h6>
                    <ul class="breakdown-list" id="breakdownList">
                        <li>Loading...</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Photo Modal -->
    <div class="modal fade" id="photoModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <h5 class="modal-title">
                        <i class="bi bi-camera"></i> Photo
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalPhoto" src="" alt="Photo" class="photo-modal-img">
                    <div class="mt-3">
                        <p><strong>Name:</strong> <span id="modalComplainantName"></span></p>
                        <p><strong>Queue Number:</strong> <span id="modalQueueNumber"></span></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Flatpickr for professional date pickers
        flatpickr("#date_from_picker", {
            dateFormat: "Y-m-d",
            maxDate: new Date(),
            onChange: function(selectedDates, dateStr, instance) {
                // Update to date min if needed
                const toPicker = flatpickr("#date_to_picker", {});
                if (toPicker) {
                    toPicker.set('minDate', dateStr);
                }
            }
        });
        
        flatpickr("#date_to_picker", {
            dateFormat: "Y-m-d",
            maxDate: new Date(),
            onChange: function(selectedDates, dateStr, instance) {
                // Update from date max if needed
                const fromPicker = flatpickr("#date_from_picker", {});
                if (fromPicker) {
                    fromPicker.set('maxDate', dateStr);
                }
            }
        });
        
        // Store breakdown data as JSON
        const breakdownData = {
            total: <?php 
                $total_data = [];
                if ($total_breakdown && $total_breakdown->num_rows > 0) {
                    while($row = $total_breakdown->fetch_assoc()) {
                        $total_data[] = $row;
                    }
                }
                echo json_encode($total_data);
            ?>,
            general: <?php 
                $general_data = [];
                if ($general_breakdown && $general_breakdown->num_rows > 0) {
                    while($row = $general_breakdown->fetch_assoc()) {
                        $general_data[] = $row;
                    }
                }
                echo json_encode($general_data);
            ?>,
            womens: <?php 
                $womens_data = [];
                if ($womens_breakdown && $womens_breakdown->num_rows > 0) {
                    while($row = $womens_breakdown->fetch_assoc()) {
                        $womens_data[] = $row;
                    }
                }
                echo json_encode($womens_data);
            ?>
        };
        
        // Modal selection tracking
        let modalSelections = {
            summary: true,
            status: true,
            caseTypes: true,
            dailyTrend: true,
            investigators: true,
            detailedCases: true,
            visitRecords: true
        };
        
        // Update modal selections when checkboxes change
        function updateModalSelections() {
            modalSelections.summary = document.getElementById('modal_chk_summary').checked;
            modalSelections.status = document.getElementById('modal_chk_status').checked;
            modalSelections.caseTypes = document.getElementById('modal_chk_caseTypes').checked;
            modalSelections.dailyTrend = document.getElementById('modal_chk_dailyTrend').checked;
            modalSelections.investigators = document.getElementById('modal_chk_investigators').checked;
            modalSelections.detailedCases = document.getElementById('modal_chk_detailedCases').checked;
            modalSelections.visitRecords = document.getElementById('modal_chk_visitRecords').checked;
        }
        
        // Select/Deselect all in modal
        function modalSelectAll(checked) {
            const checkboxes = ['modal_chk_summary', 'modal_chk_status', 'modal_chk_caseTypes', 'modal_chk_dailyTrend', 'modal_chk_investigators', 'modal_chk_detailedCases', 'modal_chk_visitRecords'];
            checkboxes.forEach(id => {
                const cb = document.getElementById(id);
                if (cb) cb.checked = checked;
            });
            updateModalSelections();
        }
        
        // Open the print selection modal
        function openPrintModal() {
            // Reset to current selections
            document.getElementById('modal_chk_summary').checked = modalSelections.summary;
            document.getElementById('modal_chk_status').checked = modalSelections.status;
            document.getElementById('modal_chk_caseTypes').checked = modalSelections.caseTypes;
            document.getElementById('modal_chk_dailyTrend').checked = modalSelections.dailyTrend;
            document.getElementById('modal_chk_investigators').checked = modalSelections.investigators;
            document.getElementById('modal_chk_detailedCases').checked = modalSelections.detailedCases;
            document.getElementById('modal_chk_visitRecords').checked = modalSelections.visitRecords;
            
            const modal = new bootstrap.Modal(document.getElementById('printSelectionModal'));
            modal.show();
        }
        
        // Print from modal
        function printFromModal() {
            updateModalSelections();
            bootstrap.Modal.getInstance(document.getElementById('printSelectionModal')).hide();
            
            // Small delay to let modal close
            setTimeout(() => {
                performPrint(modalSelections);
            }, 300);
        }
        
        // Export from modal
        function exportFromModal() {
            updateModalSelections();
            bootstrap.Modal.getInstance(document.getElementById('printSelectionModal')).hide();
            
            setTimeout(() => {
                performExport(modalSelections);
            }, 300);
        }
        
        // Perform print with selected sections
        function performPrint(selections) {
            const sections = {
                summary: document.getElementById('summaryStatsSection'),
                status: document.getElementById('statusBreakdownSection'),
                caseTypes: document.getElementById('caseTypesSection'),
                dailyTrend: document.getElementById('dailyTrendSection'),
                investigators: document.getElementById('investigatorSection'),
                detailedCases: document.getElementById('detailedCasesSection'),
                visitRecords: document.getElementById('visitRecordsSection')
            };
            
            const originalDisplays = {};
            
            for (const [key, element] of Object.entries(sections)) {
                if (element) {
                    originalDisplays[key] = element.style.display;
                    if (!selections[key]) {
                        element.style.display = 'none';
                    } else {
                        element.style.display = '';
                    }
                }
            }
            
            window.print();
            
            setTimeout(() => {
                for (const [key, element] of Object.entries(sections)) {
                    if (element) {
                        element.style.display = originalDisplays[key] || '';
                    }
                }
            }, 1000);
        }
        
        // Perform export with selected sections
        function performExport(selections) {
            let htmlContent = '<html><head><meta charset="UTF-8"><title>PNP ACG Case Report</title>';
            htmlContent += '<style>';
            htmlContent += 'body { font-family: Arial, sans-serif; margin: 20px; }';
            htmlContent += 'h2 { color: #2c3e50; }';
            htmlContent += 'h3 { color: #34495e; margin-top: 20px; background: #ecf0f1; padding: 8px; }';
            htmlContent += 'table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }';
            htmlContent += 'th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }';
            htmlContent += 'th { background-color: #3498db; color: white; }';
            htmlContent += '.stat-box { display: inline-block; width: 23%; margin: 5px; padding: 15px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 5px; text-align: center; }';
            htmlContent += '.badge-status { display: inline-block; padding: 2px 8px; border-radius: 12px; background: #e9ecef; }';
            htmlContent += '.queue-number { font-family: monospace; font-weight: bold; }';
            htmlContent += '</style>';
            htmlContent += '</head><body>';
            
            htmlContent += '<h2>PHILIPPINE NATIONAL POLICE</h2>';
            htmlContent += '<h4>Anti-Cybercrime Group</h4>';
            htmlContent += '<h5>Case Report</h5>';
            htmlContent += '<p><strong>Period:</strong> <?php echo date('F d, Y', strtotime($date_from)); ?> - <?php echo date('F d, Y', strtotime($date_to)); ?></p>';
            <?php if (!empty($category_display)): ?>
            htmlContent += '<p><strong>Category:</strong> <?php echo $category_display; ?></p>';
            <?php endif; ?>
            <?php if (!empty($status_display)): ?>
            htmlContent += '<p><strong>Status:</strong> <?php echo $status_display; ?></p>';
            <?php endif; ?>
            htmlContent += '<p><strong>Generated on:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>';
            htmlContent += '<hr>';
            
            if (selections.summary) {
                htmlContent += '<h3>📊 Summary Statistics</h3>';
                htmlContent += '<div>';
                htmlContent += '<div class="stat-box"><strong>Total Cases</strong><br><?php echo number_format($stats['total_cases'] ?? 0); ?></div>';
                htmlContent += '<div class="stat-box"><strong>General Cases</strong><br><?php echo number_format($stats['general_cases'] ?? 0); ?></div>';
                htmlContent += '<div class="stat-box"><strong>Women\'s Desk</strong><br><?php echo number_format($stats['womens_cases'] ?? 0); ?></div>';
                htmlContent += '<div class="stat-box"><strong>Visits</strong><br><?php echo number_format($stats['visit_count'] ?? 0); ?></div>';
                htmlContent += '</div><br>';
            }
            
            if (selections.status) {
                htmlContent += '<h3>📈 Status Breakdown</h3>';
                htmlContent += '<table><thead><tr><th>Status</th><th>Count</th><th>Percentage</th></tr></thead><tbody>';
                const totalStatus = <?php echo $stats['complaint_count'] + $stats['inquiry_count'] + $stats['followup_count']; ?>;
                const totalForPercent = totalStatus > 0 ? totalStatus : 1;
                const complaintPercent = Math.round((<?php echo $stats['complaint_count']; ?> / totalForPercent) * 100);
                const inquiryPercent = Math.round((<?php echo $stats['inquiry_count']; ?> / totalForPercent) * 100);
                const followupPercent = Math.round((<?php echo $stats['followup_count']; ?> / totalForPercent) * 100);
                htmlContent += `<tr><td>Complaint</td><td><?php echo $stats['complaint_count']; ?></td><td>${complaintPercent}%</td></tr>`;
                htmlContent += `<tr><td>Inquiry</td><td><?php echo $stats['inquiry_count']; ?></td><td>${inquiryPercent}%</td></tr>`;
                htmlContent += `<tr><td>Follow-up</td><td><?php echo $stats['followup_count']; ?></td><td>${followupPercent}%</td></tr>`;
                htmlContent += '</tbody></table><br>';
            }
            
            if (selections.detailedCases) {
                const table = document.getElementById('casesTable');
                if (table) {
                    htmlContent += '<h3>📑 Detailed Case List</h3>';
                    htmlContent += table.outerHTML;
                }
            }
            
            if (selections.visitRecords) {
                const visitTable = document.getElementById('visitTable');
                if (visitTable && visitTable.querySelector('tbody tr') && visitTable.querySelector('tbody tr').innerText !== 'No visit records found') {
                    htmlContent += '<h3>🚶 Visit Records</h3>';
                    htmlContent += visitTable.outerHTML;
                }
            }
            
            htmlContent += '<hr><p><em>Generated by PNP Anti-Cybercrime Group System</em></p>';
            htmlContent += '</body></html>';
            
            const blob = new Blob([htmlContent], { type: 'application/vnd.ms-excel' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'cybercrime_report_<?php echo date('Ymd_His'); ?>.xls';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
        
        // Show breakdown modal
        function showBreakdownModal(type, title, totalCount) {
            document.getElementById('modalTitle').innerHTML = title + ' Breakdown';
            document.getElementById('modalTotalCount').innerText = totalCount;
            
            const data = breakdownData[type] || [];
            const listContainer = document.getElementById('breakdownList');
            
            if (data.length === 0) {
                listContainer.innerHTML = '<li>No case type data available</li>';
            } else {
                let html = '';
                data.forEach(item => {
                    let caseTypeName = item.case_type ? item.case_type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) : 'N/A';
                    html += `
                        <li>
                            <span class="breakdown-type">${caseTypeName}</span>
                            <span class="breakdown-count">${item.case_count}</span>
                        </li>
                    `;
                });
                listContainer.innerHTML = html;
            }
            
            new bootstrap.Modal(document.getElementById('breakdownModal')).show();
        }
        
        // Show photo modal
        function showPhotoModal(element) {
            const photoSrc = element.getAttribute('data-photo-src');
            const complainantName = element.getAttribute('data-complainant-name');
            const queueNumber = element.getAttribute('data-queue-number');
            
            document.getElementById('modalPhoto').src = photoSrc;
            document.getElementById('modalComplainantName').textContent = complainantName;
            document.getElementById('modalQueueNumber').textContent = queueNumber;
        }
        
        // Toggle functions
        let trendExpanded = false;
        function toggleTrendTable() {
            const hiddenRows = document.querySelectorAll('.trend-row-hidden');
            const btn = event.target.closest('.see-more-btn');
            
            if (!trendExpanded) {
                hiddenRows.forEach(row => row.classList.remove('trend-row-hidden'));
                btn.innerHTML = '<i class="bi bi-eye-slash"></i> See Less';
                trendExpanded = true;
            } else {
                const limit = 5;
                const allRows = document.querySelectorAll('#trendTable tbody tr');
                allRows.forEach((row, index) => {
                    if (index >= limit) row.classList.add('trend-row-hidden');
                });
                btn.innerHTML = '<i class="bi bi-eye"></i> See More';
                trendExpanded = false;
            }
        }
        
        let casesExpanded = false;
        function toggleCasesTable() {
            const hiddenRows = document.querySelectorAll('.case-row-hidden');
            const btn = event.target.closest('.see-more-btn');
            
            if (!casesExpanded) {
                hiddenRows.forEach(row => row.classList.remove('case-row-hidden'));
                btn.innerHTML = '<i class="bi bi-eye-slash"></i> See Less';
                casesExpanded = true;
            } else {
                const limit = 5;
                const allRows = document.querySelectorAll('#casesTable tbody tr');
                allRows.forEach((row, index) => {
                    if (index >= limit) row.classList.add('case-row-hidden');
                });
                btn.innerHTML = '<i class="bi bi-eye"></i> See More';
                casesExpanded = false;
            }
        }
        
        let visitExpanded = false;
        function toggleVisitTable() {
            const hiddenRows = document.querySelectorAll('.visit-row-hidden');
            const btn = event.target.closest('.see-more-btn');
            
            if (!visitExpanded) {
                hiddenRows.forEach(row => row.classList.remove('visit-row-hidden'));
                btn.innerHTML = '<i class="bi bi-eye-slash"></i> See Less';
                visitExpanded = true;
            } else {
                const limit = 5;
                const allRows = document.querySelectorAll('#visitTable tbody tr');
                allRows.forEach((row, index) => {
                    if (index >= limit) row.classList.add('visit-row-hidden');
                });
                btn.innerHTML = '<i class="bi bi-eye"></i> See More';
                visitExpanded = false;
            }
        }
    </script>
</body>
</html>
