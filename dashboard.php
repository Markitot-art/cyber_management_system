<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

require_once 'config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

// Get comprehensive statistics
$stats = [];

// Today's statistics
$today = date('Y-m-d');
$queries = [
    'total_today' => "SELECT COUNT(*) as count FROM complainants WHERE DATE(created_at) = CURDATE()",
    'pending' => "SELECT COUNT(*) as count FROM complainants WHERE status = 'pending'",
    // Total Visits - count of visit records
    'total_visits' => "SELECT COUNT(*) as count FROM complainants WHERE category = 'visit'",
    'completed' => "SELECT COUNT(*) as count FROM complainants WHERE status = 'completed'",
    'general_cases' => "SELECT COUNT(*) as count FROM complainants WHERE category = 'general_cases'",
    'womens_desk' => "SELECT COUNT(*) as count FROM complainants WHERE category = 'womens_desk'",
    'total_complainants' => "SELECT COUNT(*) as count FROM complainants",
    'total_priority' => "SELECT COUNT(*) as count FROM complainants WHERE is_priority = 1",
];

foreach ($queries as $key => $query) {
    $result = $conn->query($query);
    if ($result) {
        $stats[$key] = $result->fetch_assoc()['count'];
    } else {
        $stats[$key] = 0;
    }
}

// Get case type distribution for each category
$caseTypeDistribution = [];

// Get case types for Today's Complaints
$todayCaseTypes = $conn->query("
    SELECT c.case_type, COUNT(*) as count
    FROM complaints c 
    JOIN complainants comp ON c.complainant_id = comp.complainant_id 
    WHERE DATE(c.created_at) = CURDATE() 
    GROUP BY c.case_type
    ORDER BY count DESC
");
$caseTypeDistribution['total_today'] = $todayCaseTypes ? $todayCaseTypes->fetch_all(MYSQLI_ASSOC) : [];

// Get case types for Waiting Cases (formerly Pending)
$pendingCaseTypes = $conn->query("
    SELECT c.case_type, COUNT(*) as count
    FROM complaints c 
    JOIN complainants comp ON c.complainant_id = comp.complainant_id 
    WHERE comp.status = 'pending'
    GROUP BY c.case_type
    ORDER BY count DESC
");
$caseTypeDistribution['pending'] = $pendingCaseTypes ? $pendingCaseTypes->fetch_all(MYSQLI_ASSOC) : [];

// Get case types for Total Visits
$visitCaseTypes = $conn->query("
    SELECT c.case_type, COUNT(*) as count
    FROM complaints c 
    JOIN complainants comp ON c.complainant_id = comp.complainant_id 
    WHERE comp.category = 'visit'
    GROUP BY c.case_type
    ORDER BY count DESC
");
$caseTypeDistribution['total_visits'] = $visitCaseTypes ? $visitCaseTypes->fetch_all(MYSQLI_ASSOC) : [];

// Get case types for Completed Cases
$completedCaseTypes = $conn->query("
    SELECT c.case_type, COUNT(*) as count
    FROM complaints c 
    JOIN complainants comp ON c.complainant_id = comp.complainant_id 
    WHERE comp.status = 'completed'
    GROUP BY c.case_type
    ORDER BY count DESC
");
$caseTypeDistribution['completed'] = $completedCaseTypes ? $completedCaseTypes->fetch_all(MYSQLI_ASSOC) : [];

// Get case types for General Cases
$generalCaseTypes = $conn->query("
    SELECT c.case_type, COUNT(*) as count
    FROM complaints c 
    JOIN complainants comp ON c.complainant_id = comp.complainant_id 
    WHERE comp.category = 'general_cases'
    GROUP BY c.case_type
    ORDER BY count DESC
");
$caseTypeDistribution['general_cases'] = $generalCaseTypes ? $generalCaseTypes->fetch_all(MYSQLI_ASSOC) : [];

// Get case types for Women's Desk
$womensCaseTypes = $conn->query("
    SELECT c.case_type, COUNT(*) as count
    FROM complaints c 
    JOIN complainants comp ON c.complainant_id = comp.complainant_id 
    WHERE comp.category = 'womens_desk'
    GROUP BY c.case_type
    ORDER BY count DESC
");
$caseTypeDistribution['womens_desk'] = $womensCaseTypes ? $womensCaseTypes->fetch_all(MYSQLI_ASSOC) : [];

// Get case types for Total Complainants
$allComplainantTypes = $conn->query("
    SELECT category as case_type, COUNT(*) as count
    FROM complainants 
    GROUP BY category
    ORDER BY count DESC
");
$caseTypeDistribution['total_complainants'] = $allComplainantTypes ? $allComplainantTypes->fetch_all(MYSQLI_ASSOC) : [];

// Get case types for Total Priority
$totalPriorityTypes = $conn->query("
    SELECT comp.category as case_type, COUNT(*) as count
    FROM complainants comp
    WHERE comp.is_priority = 1
    GROUP BY comp.category
    ORDER BY count DESC
");
$caseTypeDistribution['total_priority'] = $totalPriorityTypes ? $totalPriorityTypes->fetch_all(MYSQLI_ASSOC) : [];

// Get calendar data - total complaints per day (for accurate event count)
$calendar_total = $conn->query("
    SELECT 
        DATE(c.created_at) as date,
        COUNT(*) as total_count
    FROM complaints c
    JOIN complainants comp ON c.complainant_id = comp.complainant_id
    WHERE c.created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE(c.created_at)
    ORDER BY date ASC
");

// Get calendar data with daily complaints grouped by case type (for popup details)
$calendar_data = $conn->query("
    SELECT 
        DATE(c.created_at) as date,
        c.case_type,
        COUNT(*) as count
    FROM complaints c
    JOIN complainants comp ON c.complainant_id = comp.complainant_id
    WHERE c.created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE(c.created_at), c.case_type
    ORDER BY date ASC, c.case_type ASC
");

// Store total counts per date
$dailyTotals = [];
if ($calendar_total && $calendar_total->num_rows > 0) {
    while ($row = $calendar_total->fetch_assoc()) {
        $dailyTotals[$row['date']] = $row['total_count'];
    }
}

// Get all complaints for calendar with details
$calendar_details = $conn->query("
    SELECT 
        DATE(c.created_at) as date,
        c.case_type,
        c.case_status,
        comp.name as complainant_name,
        comp.queue_number,
        c.complaint_id
    FROM complaints c
    JOIN complainants comp ON c.complainant_id = comp.complainant_id
    WHERE c.created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    ORDER BY c.created_at DESC
");

$dailyComplaints = [];
$dailyCaseTypes = [];

if ($calendar_details && $calendar_details->num_rows > 0) {
    while ($row = $calendar_details->fetch_assoc()) {
        $date = $row['date'];
        if (!isset($dailyComplaints[$date])) {
            $dailyComplaints[$date] = [];
        }
        $dailyComplaints[$date][] = [
            'case_type' => $row['case_type'],
            'case_status' => $row['case_status'],
            'complainant_name' => $row['complainant_name'],
            'queue_number' => $row['queue_number'],
            'complaint_id' => $row['complaint_id']
        ];
    }
}

// Group by date and case type for summary
if ($calendar_data && $calendar_data->num_rows > 0) {
    $calendar_data->data_seek(0);
    while ($row = $calendar_data->fetch_assoc()) {
        $date = $row['date'];
        $caseType = $row['case_type'];
        $count = $row['count'];
        
        if (!isset($dailyCaseTypes[$date])) {
            $dailyCaseTypes[$date] = [];
        }
        $dailyCaseTypes[$date][] = [
            'case_type' => $caseType,
            'count' => $count
        ];
    }
}

// Get recent complaints - EXCLUDE VISIT RECORDS (only General Cases and Women's Desk)
$all_recent = $conn->query("
    SELECT 
        c.complaint_id,
        c.queue_number,
        c.case_type,
        c.case_status,
        c.date_reported,
        c.created_at,
        comp.name,
        comp.category,
        comp.status as complainant_status,
        comp.assigned_investigator,
        comp.is_priority
    FROM complaints c
    JOIN complainants comp ON c.complainant_id = comp.complainant_id
    WHERE comp.category IN ('general_cases', 'womens_desk')
    ORDER BY c.created_at DESC
");

$recent_complaints = [];
if ($all_recent && $all_recent->num_rows > 0) {
    while ($row = $all_recent->fetch_assoc()) {
        $recent_complaints[] = $row;
    }
}

// Get status distribution from complainants table - MODIFIED: Only Complaint, Inquire, Follow up
$status_distribution = $conn->query("
    SELECT 
        status as case_status,
        COUNT(*) as count
    FROM complainants
    WHERE status IN ('complaint', 'inquire', 'follow up')
    GROUP BY status
    ORDER BY FIELD(status, 'complaint', 'inquire', 'follow up')
");

// If no results from complainants, try complaints table with filtered statuses
if (!$status_distribution || $status_distribution->num_rows == 0) {
    $status_distribution = $conn->query("
        SELECT 
            case_status,
            COUNT(*) as count
        FROM complaints
        WHERE case_status IN ('complaint', 'inquire', 'follow up')
        GROUP BY case_status
        ORDER BY FIELD(case_status, 'complaint', 'inquire', 'follow up')
    ");
}

// Get investigator case handled (top investigators by caseload)
$investigator_cases = $conn->query("
    SELECT 
        assigned_investigator,
        COUNT(*) as case_count,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_cases
    FROM complainants
    WHERE assigned_investigator IS NOT NULL AND assigned_investigator != ''
    GROUP BY assigned_investigator
    ORDER BY case_count DESC
    LIMIT 5
");

// Define card configurations with modal info
$statCards = [
    'total_today' => ['title' => "Today's Complaints", 'icon' => 'bi-calendar-check', 'trend' => 'New today', 'color' => '#1e3f5f', 'modalTitle' => 'Today\'s Complaints by Type', 'emptyMsg' => 'No complaints filed today.'],
    'pending' => ['title' => 'Waiting', 'icon' => 'bi-clock', 'trend' => 'Awaiting processing', 'color' => '#ff9800', 'modalTitle' => 'Waiting Cases by Type', 'emptyMsg' => 'No waiting cases at the moment.'],
    'total_visits' => ['title' => 'Total Visits', 'icon' => 'bi-person-walking', 'trend' => 'Walk-in visitors', 'color' => '#38b2ac', 'modalTitle' => 'Visit Records by Type', 'emptyMsg' => 'No visit records found.'],
    'completed' => ['title' => 'Completed', 'icon' => 'bi-check-circle', 'trend' => 'Resolved cases', 'color' => '#4caf50', 'modalTitle' => 'Completed Cases by Type', 'emptyMsg' => 'No completed cases yet.'],
    'total_complainants' => ['title' => 'Total Complainants', 'icon' => 'bi-people', 'trend' => 'Registered individuals', 'color' => '#673ab7', 'modalTitle' => 'Complainants by Category', 'emptyMsg' => 'No complainants registered yet.'],
    'total_priority' => ['title' => 'Total Priority', 'icon' => 'bi-star-fill', 'trend' => 'Priority cases', 'color' => '#ff9800', 'modalTitle' => 'Priority Cases by Category', 'emptyMsg' => 'No priority cases filed yet.'],
    'general_cases' => ['title' => 'General Cases', 'icon' => 'bi-briefcase', 'trend' => 'Regular complaints', 'color' => '#4299e1', 'modalTitle' => 'General Cases by Type', 'emptyMsg' => 'No general cases filed.'],
    'womens_desk' => ['title' => "Women's Desk", 'icon' => 'bi-shield-shaded', 'trend' => 'Women & children', 'color' => '#ed64a6', 'modalTitle' => "Women's Desk Cases by Type", 'emptyMsg' => 'No cases filed under Women\'s Desk.']
];

// Format case type names for display
function formatCaseType($type) {
    $types = [
        'online_scam' => 'Online Scam',
        'hacking' => 'Hacking',
        'cyber_libel' => 'Cyber Libel',
        'identity_theft' => 'Identity Theft',
        'online_fraud' => 'Online Fraud',
        'cyber_bullying' => 'Cyber Bullying',
        'child_pornography' => 'Child Pornography',
        'illegal_online_gambling' => 'Illegal Gambling',
        'data_privacy' => 'Data Privacy',
        'general_cases' => 'General Cases',
        'womens_desk' => 'Women\'s Desk'
    ];
    return isset($types[$type]) ? $types[$type] : ucwords(str_replace('_', ' ', $type));
}

// Get monthly summary data for a specific month
function getMonthlySummary($conn, $year, $month) {
    $summary = [
        'total_cases' => 0,
        'case_types' => []
    ];
    
    // Get total cases for the month
    $query = "SELECT COUNT(*) as total FROM complaints c 
              JOIN complainants comp ON c.complainant_id = comp.complainant_id 
              WHERE YEAR(c.created_at) = ? AND MONTH(c.created_at) = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $year, $month);
    $stmt->execute();
    $result = $stmt->get_result();
    $summary['total_cases'] = $result->fetch_assoc()['total'];
    
    // Get case type breakdown
    $query = "SELECT c.case_type, COUNT(*) as count FROM complaints c 
              JOIN complainants comp ON c.complainant_id = comp.complainant_id 
              WHERE YEAR(c.created_at) = ? AND MONTH(c.created_at) = ?
              GROUP BY c.case_type
              ORDER BY count DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $year, $month);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $summary['case_types'][] = $row;
    }
    
    return $summary;
}

// Get available months with data (for the month selector)
$available_months = $conn->query("
    SELECT DISTINCT 
        YEAR(c.created_at) as year, 
        MONTH(c.created_at) as month,
        DATE_FORMAT(c.created_at, '%Y-%m') as month_key,
        DATE_FORMAT(c.created_at, '%M %Y') as month_name
    FROM complaints c
    ORDER BY year DESC, month DESC
");

$months_list = [];
if ($available_months && $available_months->num_rows > 0) {
    while ($row = $available_months->fetch_assoc()) {
        $months_list[] = $row;
    }
}

// Default to current month
$selected_year = isset($_GET['summary_year']) ? (int)$_GET['summary_year'] : date('Y');
$selected_month = isset($_GET['summary_month']) ? (int)$_GET['summary_month'] : date('m');
$monthly_summary = getMonthlySummary($conn, $selected_year, $selected_month);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cybercrime Dashboard · NAGA City ACG</title>
    <!-- Bootstrap & icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- FullCalendar for Calendar view -->
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    <style>
        body {
            background-color: #f0f4f8;
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
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
        }
        
        .logo-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
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
        .main-content {
            margin-left: 250px;
            padding: 12px 16px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            flex: 1;
        }
        /* MINIMIZED HERO BANNER */
        .hero-banner {
            background: linear-gradient(112deg, #102a44 0%, #2b4f73 80%);
            border-radius: 16px;
            padding: 14px 20px;
            margin-bottom: 14px;
            color: white;
            box-shadow: 0 6px 15px -6px rgba(0,30,50,0.3);
            position: relative;
            overflow: hidden;
        }
        .hero-banner::after {
            content: "⚡ ACG";
            font-size: 4rem;
            font-weight: 800;
            position: absolute;
            bottom: -20px;
            right: 10px;
            opacity: 0.04;
            color: white;
            letter-spacing: 5px;
            pointer-events: none;
        }
        .hero-banner h2 {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 2px;
            text-shadow: 0 2px 5px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .hero-banner h2 img {
            width: 32px !important;
            height: 32px !important;
        }
        .hero-banner p {
            font-size: 0.8rem;
            opacity: 0.8;
            margin-bottom: 2px;
        }
        .hero-banner .badge-float {
            position: absolute;
            bottom: 12px;
            right: 20px;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(4px);
            padding: 4px 16px;
            border-radius: 40px;
            font-weight: 500;
            font-size: 0.7rem;
            border: 1px solid rgba(255,255,255,0.15);
        }
        /* MINIMIZED STAT CARDS */
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 10px 14px;
            margin-bottom: 10px;
            box-shadow: 0 4px 12px -3px rgba(0,0,0,0.04);
            transition: all 0.2s;
            border: 1px solid rgba(0,0,0,0.02);
            position: relative;
            cursor: pointer;
        }
        .stat-card:hover {
            box-shadow: 0 8px 20px -6px rgba(20, 60, 120, 0.15);
            border-color: rgba(102, 126, 234, 0.2);
            transform: translateY(-2px);
        }
        .stat-icon {
            font-size: 2rem;
            opacity: 0.08;
            position: absolute;
            right: 12px;
            bottom: 8px;
        }
        .stat-card h6 { 
            color: #4a5f73; 
            font-weight: 600; 
            letter-spacing: 0.2px; 
            font-size: 0.65rem; 
            margin-bottom: 1px;
            text-transform: uppercase;
        }
        .stat-card h2 { 
            font-size: 1.6rem; 
            font-weight: 700; 
            color: #0f2b40; 
            margin-bottom: 1px;
            line-height: 1.3;
        }
        .trend { 
            font-size: 0.6rem; 
            color: #2e7d5e; 
            background: #e8f3ed; 
            padding: 1px 8px; 
            border-radius: 20px; 
            display: inline-block; 
        }
        .click-hint { 
            position: absolute; 
            bottom: 4px; 
            right: 14px; 
            font-size: 0.5rem; 
            color: #aaa;
            opacity: 0.4;
        }
        .card-dashboard { 
            border: none; 
            border-radius: 16px; 
            box-shadow: 0 4px 16px -6px rgba(0,30,50,0.06); 
            background: white; 
            margin-bottom: 14px; 
        }
        .card-header-dashboard { 
            background: white; 
            border-bottom: 1px solid #eef2f6; 
            padding: 10px 16px; 
            border-radius: 16px 16px 0 0 !important; 
            font-weight: 600; 
            font-size: 0.85rem; 
        }
        .card-body {
            padding: 10px 14px !important;
        }
        .table-dashboard th { 
            color: #4f6b8a; 
            font-weight: 600; 
            font-size: 0.65rem; 
            border-bottom-width: 1px; 
            padding: 4px 6px !important;
        }
        .table-dashboard td {
            padding: 4px 6px !important;
            font-size: 0.75rem;
        }
        .badge-custom { 
            padding: 2px 8px; 
            border-radius: 30px; 
            font-weight: 500; 
            font-size: 0.6rem; 
        }
        .progress-custom { height: 4px; border-radius: 10px; background-color: #e3eaf1; }
        .progress-bar-custom { background: linear-gradient(90deg, #306c9f, #638fc9); border-radius: 10px; }
        .detail-table-container { max-height: 500px; overflow-y: auto; }
        .case-type-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
            border-bottom: 1px solid #eef2f6;
        }
        .case-type-item:last-child {
            border-bottom: none;
        }
        .case-type-name {
            font-size: 0.85rem;
            color: #4a5f73;
        }
        .case-type-badge {
            background: #e8f3ed;
            color: #2e7d5e;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            min-width: 40px;
            text-align: center;
        }
        .compact-table {
            font-size: 0.75rem;
        }
        .compact-table td {
            padding: 4px 8px;
        }
        .calendar-container {
            padding: 8px 10px;
            min-height: 300px;
            position: relative;
        }
        .fc {
            max-width: 100%;
            font-size: 0.7rem;
        }
        .fc .fc-toolbar-title {
            font-size: 0.9rem;
        }
        .fc .fc-button {
            padding: 0.15rem 0.4rem;
            font-size: 0.65rem;
        }
        .fc .fc-daygrid-day-number {
            font-size: 0.65rem;
        }
        .fc .fc-daygrid-day-frame {
            min-height: 40px;
        }
        .fc-day-today {
            background-color: #e8f0fe !important;
        }
        .fc-event {
            cursor: pointer;
            font-size: 0.55rem;
            padding: 0px 2px;
        }
        .fc .fc-toolbar.fc-header-toolbar {
            margin-right: 120px;
            margin-bottom: 4px;
        }
        .chart-container {
            max-height: 200px;
            margin: 0 auto;
        }
        .status-chart-wrapper {
            max-width: 300px;
            margin: 0 auto;
        }
        .see-more-btn {
            cursor: pointer;
            color: #4299e1;
            font-weight: 500;
            text-decoration: none;
            font-size: 0.75rem;
        }
        .see-more-btn:hover {
            text-decoration: underline;
        }
        .complaint-row-hidden {
            display: none;
        }
        .calendar-popup-content {
            max-height: 400px;
            overflow-y: auto;
        }
        .complaint-item {
            padding: 6px;
            border-bottom: 1px solid #eef2f6;
            font-size: 0.75rem;
        }
        .complaint-item:last-child {
            border-bottom: none;
        }
        .case-type-list {
            list-style: none;
            padding-left: 0;
            margin-bottom: 0;
        }
        .case-type-list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #eef2f6;
        }
        .case-type-list-item:last-child {
            border-bottom: none;
        }
        .case-type-name {
            font-size: 0.85rem;
            color: #333;
            font-weight: 500;
        }
        .case-type-count {
            background: #e8f3ed;
            color: #2e7d5e;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            min-width: 40px;
            text-align: center;
        }
        
        /* MINIMIZED Monthly Summary Box */
        .calendar-summary-overlay {
            position: absolute;
            top: -2px;
            right: 6px;
            z-index: 10;
            background: white;
            border-radius: 6px;
            padding: 2px 8px;
            min-width: 110px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            border-left: 3px solid #4299e1;
            cursor: pointer;
            transition: all 0.2s ease;
            background: rgba(255, 255, 255, 0.98);
        }
        .calendar-summary-overlay:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(0,0,0,0.12);
            background: white;
        }
        .calendar-summary-overlay .summary-title {
            font-size: 0.5rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.2px;
            margin-bottom: 0px;
        }
        .calendar-summary-overlay .summary-total {
            font-size: 0.8rem;
            font-weight: 700;
            color: #2b4f73;
            line-height: 1.1;
            margin-bottom: 0px;
        }
        .calendar-summary-overlay .summary-total small {
            font-size: 0.5rem;
            font-weight: normal;
            color: #6c757d;
        }
        .calendar-summary-overlay .summary-items {
            font-size: 0.5rem;
            color: #6c757d;
        }
        .calendar-summary-overlay .summary-items span {
            color: #4299e1;
            font-weight: 600;
        }
        
        .monthly-modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .monthly-modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        .monthly-summary-modal-list {
            list-style: none;
            padding-left: 0;
            margin-bottom: 0;
        }
        .monthly-summary-modal-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #eef2f6;
        }
        .monthly-summary-modal-item:last-child {
            border-bottom: none;
        }
        .monthly-summary-modal-name {
            font-size: 0.9rem;
            color: #333;
            font-weight: 500;
        }
        .monthly-summary-modal-count {
            background: #e8f3ed;
            color: #2e7d5e;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            min-width: 40px;
            text-align: center;
        }
        .month-selector {
            background: #f8f9fa;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.7rem;
        }
        .month-selector select {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 2px 6px;
            font-size: 0.7rem;
        }

        /* MINIMIZED Footer */
        .dashboard-footer {
            margin-top: auto;
            background: linear-gradient(112deg, #102a44 0%, #2b4f73 80%);
            border-radius: 12px;
            padding: 8px 16px;
            text-align: center;
            font-size: 0.65rem;
            color: rgba(255,255,255,0.85);
            box-shadow: 0 -2px 8px rgba(0,0,0,0.06);
        }
        .dashboard-footer a {
            color: #ffc107;
            text-decoration: none;
        }
        .dashboard-footer a:hover {
            text-decoration: underline;
            color: white;
        }
        
        .full-width-calendar {
            width: 100%;
        }
        
        /* MINIMIZED row gaps */
        .row.g-4 {
            --bs-gutter-y: 0.6rem;
            --bs-gutter-x: 0.6rem;
        }
        .row.g-4 > * {
            padding-right: calc(var(--bs-gutter-x) * 0.5);
            padding-left: calc(var(--bs-gutter-x) * 0.5);
            margin-top: var(--bs-gutter-y);
        }
        .mb-4 {
            margin-bottom: 0.6rem !important;
        }
        .mb-3 {
            margin-bottom: 0.4rem !important;
        }
        .mt-4 {
            margin-top: 0.6rem !important;
        }
        
        .col-md-3 {
            flex: 0 0 auto;
            width: 25%;
        }
        
        /* Investigator section minimized */
        .investigator-item {
            margin-bottom: 6px !important;
        }
        .investigator-item .fw-bold {
            font-size: 0.75rem !important;
        }
        .investigator-item .badge {
            font-size: 0.6rem !important;
            padding: 2px 8px !important;
        }
        .investigator-item small {
            font-size: 0.6rem !important;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .col-md-3 {
                width: 33.333%;
            }
        }
        @media (max-width: 768px) {
            .fc .fc-toolbar.fc-header-toolbar {
                margin-right: 0;
                flex-wrap: wrap;
                gap: 4px;
            }
            .calendar-summary-overlay {
                position: relative;
                top: 0;
                right: 0;
                margin-bottom: 4px;
                display: inline-block;
                width: auto;
            }
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                padding: 10px;
            }
            .col-md-3 {
                width: 50%;
            }
            .hero-banner h2 {
                font-size: 1rem;
            }
            .hero-banner h2 img {
                width: 24px !important;
                height: 24px !important;
            }
            .hero-banner .badge-float {
                display: none;
            }
        }
        @media (max-width: 480px) {
            .col-md-3 {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- SIDEBAR (UNCHANGED) -->
            <div class="col-auto p-0 sidebar" id="sidebar">
                <div class="sidebar-logo">
                    <div class="logo-frame">
                        <img src="videos/uploads/cyberlogo.png" alt="ACG NAGA Logo" class="logo-img" onerror="this.src='https://via.placeholder.com/120x120?text=PNP'">
                    </div>
                    <div class="logo-text">CSPCRT</div>
                </div>
                <nav>
                    <a href="dashboard.php" class="active"><i class="bi bi-speedometer2"></i> Dashboard</a>
                    <a href="category_selection.php"><i class="bi bi-plus-circle"></i> New Complaint</a>
                    <a href="queue.php"><i class="bi bi-list-ol"></i> Queue Management</a>
                    <a href="records.php"><i class="bi bi-folder"></i> Records</a>
                    <a href="reports.php"><i class="bi bi-bar-chart"></i> Reports</a>
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
                
            </div>

            <!-- Main Content - MINIMIZED -->
            <div class="col main-content">
                <!-- Mobile Menu Toggle -->
                <button class="btn btn-primary d-md-none mb-2" onclick="toggleSidebar()" style="z-index: 1060; padding: 4px 10px; font-size: 0.8rem;">
                    <i class="bi bi-list"></i> Menu
                </button>
                
                <!-- Hero / Welcome Section - MINIMIZED -->
                <div class="hero-banner">
                    <h2>
                        <img src="videos/uploads/icon1.webp" 
                             alt="Icon" 
                             style="width: 32px; height: 32px; border-radius: 50%; border: 2px solid #ffc107; padding: 2px;" 
                             onerror="this.style.display='none'">
                        Camarines Sur Provincial Cyber Response Team
                    </h2>
                    <p>Queuing Management System</p>
                    <span class="badge-float"><i class="bi bi-calendar"></i> <?php echo date('M d, Y'); ?></span>
                </div>

                <!-- Statistics Cards Grid - MINIMIZED -->
                <div class="row g-4 mb-4">
                    <?php foreach ($statCards as $key => $card): ?>
                        <div class="col-md-3 col-sm-6">
                            <div class="stat-card" style="border-left: 4px solid <?php echo $card['color']; ?>;" 
                                 data-bs-toggle="modal" 
                                 data-bs-target="#detailModal" 
                                 data-key="<?php echo $key; ?>"
                                 data-title="<?php echo $card['modalTitle']; ?>"
                                 data-count="<?php echo isset($stats[$key]) ? $stats[$key] : 0; ?>">
                                <h6><?php echo $card['title']; ?></h6>
                                <h2><?php echo isset($stats[$key]) ? $stats[$key] : 0; ?></h2>
                                <span class="trend"><?php echo $card['trend']; ?></span>
                                <div class="stat-icon" style="color: <?php echo $card['color']; ?>;">
                                    <i class="bi <?php echo $card['icon']; ?>"></i>
                                </div>
                                <div class="click-hint">
                                    <i class="bi bi-hand-index"></i> view
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Calendar View - MINIMIZED -->
                <div class="row g-4 mb-4">
                    <div class="col-12">
                        <div class="card card-dashboard shadow-sm">
                            <div class="card-header-dashboard"><i class="bi bi-calendar"></i> Calendar</div>
                            <div class="card-body calendar-container">
                                <div class="calendar-summary-overlay" data-bs-toggle="modal" data-bs-target="#monthlySummaryModal">
                                    <div class="summary-title">
                                        <i class="bi bi-calendar-month"></i> 
                                        <span id="summaryMonthName"><?php echo date('M Y', strtotime("$selected_year-$selected_month-01")); ?></span>
                                    </div>
                                    <div class="summary-total">
                                        <?php echo $monthly_summary['total_cases']; ?> <small>cases</small>
                                    </div>
                                    <div class="summary-items">
                                        <?php 
                                        $type_count = count($monthly_summary['case_types']);
                                        if ($type_count > 0) {
                                            echo $type_count . ' type' . ($type_count > 1 ? 's' : '');
                                        } else {
                                            echo 'No cases';
                                        }
                                        ?>
                                        <span><i class="bi bi-chevron-right"></i></span>
                                    </div>
                                </div>
                                <div id="calendar" class="full-width-calendar"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Complaints & Investigator - MINIMIZED -->
                <div class="row g-4">
                    <div class="col-md-8">
                        <div class="card card-dashboard shadow-sm">
                            <div class="card-header-dashboard d-flex justify-content-between align-items-center">
                                <div><i class="bi bi-clock-history"></i> Recent</div>
                                <a href="records.php" class="btn btn-sm btn-outline-primary" style="font-size: 0.65rem; padding: 2px 8px;">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-dashboard table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Complainant</th>
                                                <th>Type</th>
                                                <th>Status</th>
                                                <th>Investigator</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="recentComplaintsBody">
                                            <?php 
                                            $display_count = 0;
                                            foreach ($recent_complaints as $index => $row): 
                                                $display_count++;
                                                $hidden_class = ($index >= 5) ? 'complaint-row-hidden' : '';
                                                $is_priority = isset($row['is_priority']) && $row['is_priority'] == 1;
                                            ?>
                                                <tr class="<?php echo $hidden_class; ?>">
                                                    <td><strong><?php echo htmlspecialchars($row['queue_number']); ?></strong></td>
                                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo ($row['category'] == 'womens_desk') ? 'danger' : 'primary'; ?> badge-custom">
                                                            <?php echo ($row['category'] == 'womens_desk') ? 'WD' : 'GC'; ?>
                                                        </span>
                                                        <?php if ($is_priority): ?>
                                                            <span class="badge bg-warning text-dark badge-custom" style="font-size: 0.5rem;">★</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $display_status = $row['complainant_status'] ?: $row['case_status'];
                                                        $badge_color = 'secondary';
                                                        if (strtolower($display_status) == 'complaint') {
                                                            $badge_color = 'danger';
                                                        } elseif (strtolower($display_status) == 'inquire') {
                                                            $badge_color = 'info';
                                                        } elseif (strtolower($display_status) == 'follow up') {
                                                            $badge_color = 'warning';
                                                        }
                                                        ?>
                                                        <span class="badge bg-<?php echo $badge_color; ?> badge-custom">
                                                            <?php echo ucfirst(htmlspecialchars($display_status)); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($row['assigned_investigator'] ?: '—'); ?></td>
                                                    <td>
                                                        <a href="view_complaint.php?id=<?php echo $row['complaint_id']; ?>" class="btn btn-sm btn-info" style="padding: 1px 6px; font-size: 0.6rem;" title="View"><i class="bi bi-eye"></i></a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($recent_complaints)): ?>
                                                <tr>
                                                    <td colspan="6" class="text-center py-2">
                                                        <span class="text-muted" style="font-size: 0.75rem;">No recent complaints</span>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if (count($recent_complaints) > 5): ?>
                                <div class="text-center py-1 border-top">
                                    <a href="javascript:void(0)" class="see-more-btn" id="seeMoreBtn">
                                        <i class="bi bi-chevron-down"></i> See More (<?php echo count($recent_complaints) - 5; ?>)
                                    </a>
                                    <a href="javascript:void(0)" class="see-more-btn" id="seeLessBtn" style="display: none;">
                                        <i class="bi bi-chevron-up"></i> See Less
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card card-dashboard shadow-sm">
                            <div class="card-header-dashboard"><i class="bi bi-person-badge"></i> Investigators</div>
                            <div class="card-body">
                                <?php if ($investigator_cases && $investigator_cases->num_rows > 0): ?>
                                    <?php while ($inv = $investigator_cases->fetch_assoc()): ?>
                                    <div class="investigator-item mb-2">
                                        <div class="d-flex justify-content-between align-items-center mb-0">
                                            <span class="fw-bold" style="font-size: 0.75rem;"><?php echo htmlspecialchars($inv['assigned_investigator']); ?></span>
                                            <span class="badge bg-primary" style="font-size: 0.6rem;"><?php echo $inv['case_count']; ?></span>
                                        </div>
                                        <div class="progress progress-custom">
                                            <?php 
                                            $completion_rate = $inv['case_count'] > 0 ? round(($inv['completed_cases'] / $inv['case_count']) * 100) : 0;
                                            ?>
                                            <div class="progress-bar progress-bar-custom" style="width: <?php echo $completion_rate; ?>%"></div>
                                        </div>
                                        <small class="text-muted" style="font-size: 0.55rem;"><?php echo $inv['completed_cases']; ?> done (<?php echo $completion_rate; ?>%)</small>
                                    </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <p class="text-muted text-center" style="font-size: 0.75rem;">No data available</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer - MINIMIZED -->
                <div class="dashboard-footer mt-3">
                    <div class="row align-items-center">
                        <div class="col-md-6 text-md-start mb-1 mb-md-0">
                            <i class="bi bi-shield-check"></i> PNP ACG · CSPCRT Naga
                        </div>
                        <div class="col-md-6 text-md-end">
                            <span>&copy; <?php echo date('Y'); ?> v1.5 | <a href="#" data-bs-toggle="modal" data-bs-target="#aboutModal">About</a></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for displaying detailed information -->
    <div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(112deg, #102a44 0%, #2b4f73 80%); color: white;">
                    <h5 class="modal-title" id="detailModalLabel">
                        <i class="bi bi-info-circle-fill"></i> <span id="modalTitle">Details</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <i class="bi bi-bar-chart"></i> Total Count: <strong id="totalCount">0</strong>
                    </div>
                    <div id="modalTableContainer" class="detail-table-container">
                        <!-- Dynamic table will be inserted here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Calendar Day Click Modal -->
    <div class="modal fade" id="calendarModal" tabindex="-1" aria-labelledby="calendarModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(112deg, #102a44 0%, #2b4f73 80%); color: white;">
                    <h5 class="modal-title" id="calendarModalLabel">
                        <i class="bi bi-calendar-date"></i> Complaints for <span id="selectedDate"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <i class="bi bi-bar-chart"></i> Total Count: <strong id="totalComplaintsDate">0</strong>
                    </div>
                    <div id="calendarModalContent" class="calendar-popup-content">
                        <!-- Dynamic content will be inserted here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Summary Modal with Month Selector -->
    <div class="modal fade" id="monthlySummaryModal" tabindex="-1" aria-labelledby="monthlySummaryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header monthly-modal-header">
                    <h5 class="modal-title" id="monthlySummaryModalLabel">
                        <i class="bi bi-calendar-month"></i> Monthly Case Summary
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="month-selector mb-3 d-flex justify-content-between align-items-center">
                        <label class="fw-bold" style="font-size: 0.8rem;">Select Month:</label>
                        <form method="GET" action="" id="monthSelectForm" class="d-flex gap-2">
                            <select name="summary_year" id="summary_year" class="form-select form-select-sm" style="width: auto; font-size: 0.7rem;">
                                <?php 
                                $current_year_display = date('Y');
                                for ($y = $current_year_display; $y >= $current_year_display - 2; $y--): 
                                ?>
                                    <option value="<?php echo $y; ?>" <?php echo $selected_year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                            <select name="summary_month" id="summary_month" class="form-select form-select-sm" style="width: auto; font-size: 0.7rem;">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo $selected_month == $m ? 'selected' : ''; ?>><?php echo date('M', mktime(0, 0, 0, $m, 1)); ?></option>
                                <?php endfor; ?>
                            </select>
                            <button type="submit" class="btn btn-sm btn-primary" style="font-size: 0.7rem; padding: 2px 8px;">Go</button>
                        </form>
                    </div>
                    <div class="alert alert-info mb-3">
                        <i class="bi bi-bar-chart"></i> Total Cases: <strong><?php echo $monthly_summary['total_cases']; ?></strong>
                    </div>
                    <div id="monthlyModalContent">
                        <?php if (count($monthly_summary['case_types']) > 0): ?>
                            <div class="monthly-summary-modal-list">
                                <?php foreach ($monthly_summary['case_types'] as $case): ?>
                                    <div class="monthly-summary-modal-item">
                                        <span class="monthly-summary-modal-name"><?php echo formatCaseType($case['case_type']); ?></span>
                                        <span class="monthly-summary-modal-count"><?php echo $case['count']; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-inbox" style="font-size: 48px;"></i>
                                <p class="mt-3 mb-0">No cases filed in <?php echo date('F Y', strtotime("$selected_year-$selected_month-01")); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- About Modal -->
    <div class="modal fade" id="aboutModal" tabindex="-1" aria-labelledby="aboutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(112deg, #102a44 0%, #2b4f73 80%); color: white;">
                    <h5 class="modal-title" id="aboutModalLabel"><i class="bi bi-info-circle"></i> About</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <img src="videos/uploads/logocyber.png" alt="ACG Logo" style="width: 60px; height: 60px; border-radius: 50%;" onerror="this.style.display='none'">
                        <h6 class="mt-2">PNP ACG CSPCRT</h6>
                        <p class="text-muted small">Queuing Management System</p>
                    </div>
                    <p style="font-size: 0.85rem;"><strong>Version:</strong> 1.5.0</p>
                    <p style="font-size: 0.85rem;"><strong>Developer:</strong> PNP ACG ICTMS</p>
                    <p style="font-size: 0.85rem;"><strong>Description:</strong> Cybercrime complaint management system for CSPCRT.</p>
                    <hr>
                    <p class="text-muted small mb-0">&copy; <?php echo date('Y'); ?> PNP - Anti-Cybercrime Group.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden JSON data for modal -->
    <script type="text/javascript">
        const caseTypeData = <?php 
            $jsonData = [];
            foreach ($caseTypeDistribution as $key => $data) {
                $jsonData[$key] = $data;
            }
            echo json_encode($jsonData);
        ?>;
        
        const statConfigs = <?php echo json_encode($statCards); ?>;
        
        const dailyCaseTypesData = <?php 
            echo json_encode($dailyCaseTypes);
        ?>;
        
        const dailyTotalsData = <?php 
            echo json_encode($dailyTotals);
        ?>;
        
        const dailyComplaintsData = <?php 
            echo json_encode($dailyComplaints);
        ?>;
        
        function formatCaseType(type) {
            const types = {
                'online_scam': 'Online Scam',
                'hacking': 'Hacking',
                'cyber_libel': 'Cyber Libel',
                'identity_theft': 'Identity Theft',
                'online_fraud': 'Online Fraud',
                'cyber_bullying': 'Cyber Bullying',
                'child_pornography': 'Child Pornography',
                'illegal_online_gambling': 'Illegal Gambling',
                'data_privacy': 'Data Privacy',
                'general_cases': 'General Cases',
                'womens_desk': 'Women\'s Desk'
            };
            return types[type] || type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        }
        
        const calendarEvents = [];
        for (const [date, totalCount] of Object.entries(dailyTotalsData)) {
            calendarEvents.push({
                title: totalCount + ' complaint' + (totalCount > 1 ? 's' : ''),
                start: date,
                allDay: true,
                backgroundColor: '#4299e1',
                borderColor: '#4299e1',
                extendedProps: {
                    date: date,
                    totalCount: totalCount
                }
            });
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('show');
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        const calendarEl = document.getElementById('calendar');
        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,dayGridWeek'
            },
            events: calendarEvents,
            height: 'auto',
            contentHeight: 300,
            dateClick: function(info) {
                const clickedDate = info.dateStr;
                const caseTypes = dailyCaseTypesData[clickedDate] || [];
                const totalCount = dailyTotalsData[clickedDate] || 0;
                
                document.getElementById('selectedDate').innerText = clickedDate;
                document.getElementById('totalComplaintsDate').innerText = totalCount;
                
                let contentHtml = '';
                
                if (caseTypes.length === 0) {
                    contentHtml = '<div class="alert alert-warning text-center" style="font-size: 0.85rem;">No complaints filed on this date.</div>';
                } else {
                    contentHtml = '<div class="case-type-list">';
                    for (const ct of caseTypes) {
                        contentHtml += `
                            <div class="case-type-list-item">
                                <span class="case-type-name">${formatCaseType(ct.case_type)}</span>
                                <span class="case-type-count">${ct.count}</span>
                            </div>
                        `;
                    }
                    contentHtml += '</div>';
                }
                
                document.getElementById('calendarModalContent').innerHTML = contentHtml;
                
                const calendarModal = new bootstrap.Modal(document.getElementById('calendarModal'));
                calendarModal.show();
            },
            eventClick: function(info) {
                const date = info.event.startStr;
                const caseTypes = dailyCaseTypesData[date] || [];
                const totalCount = dailyTotalsData[date] || 0;
                
                document.getElementById('selectedDate').innerText = date;
                document.getElementById('totalComplaintsDate').innerText = totalCount;
                
                let contentHtml = '';
                
                if (caseTypes.length === 0) {
                    contentHtml = '<div class="alert alert-warning text-center" style="font-size: 0.85rem;">No complaints filed on this date.</div>';
                } else {
                    contentHtml = '<div class="case-type-list">';
                    for (const ct of caseTypes) {
                        contentHtml += `
                            <div class="case-type-list-item">
                                <span class="case-type-name">${formatCaseType(ct.case_type)}</span>
                                <span class="case-type-count">${ct.count}</span>
                            </div>
                        `;
                    }
                    contentHtml += '</div>';
                }
                
                document.getElementById('calendarModalContent').innerHTML = contentHtml;
                
                const calendarModal = new bootstrap.Modal(document.getElementById('calendarModal'));
                calendarModal.show();
            }
        });
        calendar.render();
    });
    </script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const detailModal = document.getElementById('detailModal');
        
        detailModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const key = button.getAttribute('data-key');
            const title = button.getAttribute('data-title');
            const count = button.getAttribute('data-count');
            
            document.getElementById('modalTitle').innerText = title;
            document.getElementById('totalCount').innerText = count;
            
            const data = caseTypeData[key] || [];
            const config = statConfigs[key];
            
            let html = '';
            
            if (data.length === 0) {
                html = `<div class="alert alert-warning text-center" style="font-size: 0.9rem;">
                                <i class="bi bi-exclamation-triangle"></i> 
                                ${config ? config.emptyMsg : 'No data available'}
                            </div>`;
            } else {
                html = '<div class="list-group list-group-flush">';
                for (let item of data) {
                    let caseType = item.case_type || item.category || 'Unknown';
                    let itemCount = item.count || 0;
                    let displayName = formatCaseType(caseType);
                    
                    html += `
                        <div class="case-type-item">
                            <span class="case-type-name">${displayName}</span>
                            <span class="case-type-badge">${itemCount}</span>
                        </div>
                    `;
                }
                html += '</div>';
            }
            
            document.getElementById('modalTableContainer').innerHTML = html;
        });
        
        const seeMoreBtn = document.getElementById('seeMoreBtn');
        const seeLessBtn = document.getElementById('seeLessBtn');
        
        if (seeMoreBtn) {
            seeMoreBtn.addEventListener('click', function() {
                const hiddenRows = document.querySelectorAll('#recentComplaintsBody .complaint-row-hidden');
                hiddenRows.forEach(row => {
                    row.classList.remove('complaint-row-hidden');
                });
                seeMoreBtn.style.display = 'none';
                seeLessBtn.style.display = 'inline-block';
            });
        }
        
        if (seeLessBtn) {
            seeLessBtn.addEventListener('click', function() {
                const rows = document.querySelectorAll('#recentComplaintsBody tr');
                rows.forEach((row, index) => {
                    if (index >= 5) {
                        row.classList.add('complaint-row-hidden');
                    }
                });
                seeMoreBtn.style.display = 'inline-block';
                seeLessBtn.style.display = 'none';
            });
        }
        
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const menuBtn = document.querySelector('.btn-primary.d-md-none');
            
            if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('show')) {
                if (!sidebar.contains(event.target) && !menuBtn?.contains(event.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });
    });
    </script>
    
    <script>
    setTimeout(function() {
        location.reload();
    }, 60000);
    </script>
</body>
</html>
