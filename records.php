<?php
/**
 * Complaint Records Management System
 * 
 * MODIFIED: Search-first approach - records hidden until user performs a search
 * Features: Search, Filters, Statistics, and PRINT functionality
 */


error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

require_once 'config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

// ============================================
// HANDLE CUSTOM PRINT REQUEST
// ============================================
if (isset($_GET['custom_print']) && $_GET['custom_print'] == 1) {
    ob_start();
    header('Content-Type: text/html');
    
    // Get filters from request
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
    $date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
    $queue_no = isset($_GET['queue_no']) ? trim($_GET['queue_no']) : '';
    $status_filter = isset($_GET['status_filter']) ? trim($_GET['status_filter']) : '';
    $case_type_filter = isset($_GET['case_type_filter']) ? trim($_GET['case_type_filter']) : '';
    
    $print_case_records = isset($_GET['case_records']) && $_GET['case_records'] == '1';
    $print_visit_records = isset($_GET['visit_records']) && $_GET['visit_records'] == '1';
    
    // Build WHERE clause for printing
    $params = [];
    $types = "";
    $where_clause = "1=1";
    
    if (!empty($search)) {
        $search_term = "%$search%";
        $where_clause .= " AND (comp.name LIKE ? OR comp.contact_number LIKE ? OR comp.address LIKE ? OR c.queue_number LIKE ? OR c.case_type LIKE ? OR c.case_status LIKE ? OR comp.visit_reason LIKE ?)";
        for ($i = 0; $i < 7; $i++) {
            $params[] = $search_term;
            $types .= "s";
        }
    }
    
    if (!empty($queue_no)) {
        $queue_term = "%$queue_no%";
        $where_clause .= " AND c.queue_number LIKE ?";
        $params[] = $queue_term;
        $types .= "s";
    }
    
    if (!empty($status_filter)) {
        $where_clause .= " AND c.case_status = ?";
        $params[] = $status_filter;
        $types .= "s";
    }
    
    if (!empty($case_type_filter)) {
        $where_clause .= " AND c.case_type = ?";
        $params[] = $case_type_filter;
        $types .= "s";
    }
    
    if (!empty($date_from) && !empty($date_to)) {
        $where_clause .= " AND DATE(c.date_reported) BETWEEN ? AND ?";
        $params[] = $date_from;
        $params[] = $date_to;
        $types .= "ss";
    } elseif (!empty($date_from)) {
        $where_clause .= " AND DATE(c.date_reported) >= ?";
        $params[] = $date_from;
        $types .= "s";
    } elseif (!empty($date_to)) {
        $where_clause .= " AND DATE(c.date_reported) <= ?";
        $params[] = $date_to;
        $types .= "s";
    }
    
    // Fetch all records for print (NO LIMIT)
    $print_query = "
        SELECT 
            c.complaint_id,
            c.queue_number,
            c.case_type,
            c.case_status,
            c.date_reported,
            c.incident_date,
            c.incident_time,
            comp.name as complainant_name, 
            comp.contact_number,
            comp.address,
            comp.category,
            comp.assigned_investigator,
            comp.photo_path,
            comp.visit_reason,
            CASE 
                WHEN comp.category = 'visit' THEN 'Visit'
                WHEN comp.category = 'general_cases' THEN 'General Case'
                WHEN comp.category = 'womens_desk' THEN \"Women's Desk\"
                ELSE 'Other'
            END as record_type
        FROM complaints c
        JOIN complainants comp ON c.complainant_id = comp.complainant_id
        WHERE $where_clause
        ORDER BY c.date_reported DESC
    ";
    
    $print_records = [];
    if (!empty($params)) {
        $stmt = $conn->prepare($print_query);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $print_records[] = $row;
            }
            $stmt->close();
        }
    } else {
        $result = $conn->query($print_query);
        while ($row = $result->fetch_assoc()) {
            $print_records[] = $row;
        }
    }
    
    // Separate case and visit records
    $print_case_data = array_filter($print_records, function($row) {
        return $row['category'] != 'visit';
    });
    $print_visit_data = array_filter($print_records, function($row) {
        return $row['category'] == 'visit';
    });
    
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Print Preview - Records</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 20px;
                font-size: 12px;
            }
            h2, h3 {
                text-align: center;
                margin-bottom: 10px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            th, td {
                border: 1px solid #000;
                padding: 8px;
                text-align: left;
                vertical-align: top;
            }
            th {
                background-color: #f0f0f0;
                font-weight: bold;
            }
            .print-header {
                text-align: center;
                margin-bottom: 20px;
                border-bottom: 2px solid #000;
                padding-bottom: 10px;
            }
            .print-footer {
                text-align: center;
                margin-top: 20px;
                font-size: 10px;
                border-top: 1px solid #ccc;
                padding-top: 10px;
            }
            .filter-info {
                margin: 10px 0;
                padding: 10px;
                background: #f9f9f9;
                border: 1px solid #ddd;
                font-size: 11px;
            }
            @page {
                size: A4 landscape;
                margin: 1cm;
            }
        </style>
    </head>
    <body>
        <div class="print-header">
            <h2>PHILIPPINE NATIONAL POLICE</h2>
            <h4>Anti-Cybercrime Group</h4>
            <h5>Records Report</h5>
            <p>Generated on: <?php echo date('F d, Y h:i A'); ?></p>
            <?php if (!empty($search)): ?>
            <div class="filter-info">
                <strong>Search Filter:</strong> "<?php echo htmlspecialchars($search); ?>"
            </div>
            <?php endif; ?>
            <?php if (!empty($date_from) || !empty($date_to)): ?>
            <div class="filter-info">
                <strong>Date Range:</strong> <?php echo $date_from ?: 'Start'; ?> to <?php echo $date_to ?: 'Today'; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if ($print_case_records && !empty($print_case_data)): ?>
        <h3>Case Records (<?php echo count($print_case_data); ?> records)</h3>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Queue #</th>
                    <th>Date Reported</th>
                    <th>Complainant Name</th>
                    <th>Contact</th>
                    <th>Address</th>
                    <th>Category</th>
                    <th>Case Type</th>
                    <th>Status</th>
                    <th>Investigator</th>
                </tr>
            </thead>
            <tbody>
                <?php $counter = 1; foreach ($print_case_data as $row): ?>
                <tr>
                    <td><?php echo $counter++; ?></td>
                    <td><?php echo htmlspecialchars($row['queue_number']); ?></td>
                    <td><?php echo date('Y-m-d', strtotime($row['date_reported'])); ?></td>
                    <td><?php echo htmlspecialchars($row['complainant_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['contact_number']); ?></td>
                    <td><?php echo htmlspecialchars(substr($row['address'], 0, 50)); ?></td>
                    <td><?php echo $row['category'] == 'general_cases' ? 'General' : "Women's Desk"; ?></td>
                    <td><?php echo htmlspecialchars(str_replace('_', ' ', ucwords($row['case_type'] ?? 'N/A'))); ?></td>
                    <td><?php echo ucwords(str_replace('-', ' ', $row['case_status'] ?? 'N/A')); ?></td>
                    <td><?php echo htmlspecialchars($row['assigned_investigator'] ?? 'Unassigned'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        
        <?php if ($print_visit_records && !empty($print_visit_data)): ?>
        <h3>Visit Records (<?php echo count($print_visit_data); ?> records)</h3>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Queue #</th>
                    <th>Date of Visit</th>
                    <th>Visitor Name</th>
                    <th>Contact</th>
                    <th>Address</th>
                    <th>Reason for Visit</th>
                </tr>
            </thead>
            <tbody>
                <?php $counter = 1; foreach ($print_visit_data as $row): ?>
                <tr>
                    <td><?php echo $counter++; ?></td>
                    <td><?php echo htmlspecialchars($row['queue_number']); ?></td>
                    <td><?php echo date('Y-m-d', strtotime($row['date_reported'])); ?></td>
                    <td><?php echo htmlspecialchars($row['complainant_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['contact_number']); ?></td>
                    <td><?php echo htmlspecialchars(substr($row['address'], 0, 50)); ?></td>
                    <td><?php echo nl2br(htmlspecialchars(substr($row['visit_reason'] ?? '', 0, 100))); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        
        <div class="print-footer">
            <p>Generated by PNP Anti-Cybercrime Group System | <?php echo date('Y-m-d h:i A'); ?></p>
        </div>
        <script>
            window.onload = function() { window.print(); }
        </script>
    </body>
    </html>
    <?php
    exit();
}

// Get list of investigators for print filter
$investigator_list = [];
$inv_query = "SELECT DISTINCT assigned_investigator FROM complainants WHERE assigned_investigator IS NOT NULL AND assigned_investigator != '' ORDER BY assigned_investigator ASC";
$inv_result = $conn->query($inv_query);
if ($inv_result) {
    while ($row = $inv_result->fetch_assoc()) {
        $investigator_list[] = $row['assigned_investigator'];
    }
}

$success_message = '';
if (isset($_GET['success']) && $_GET['success'] === 'visit_saved') {
    $success_message = 'Visit record saved successfully.';
}

// ============================================
// CHECK IF SEARCH HAS BEEN PERFORMED
// ============================================
$has_performed_search = false;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$queue_no = isset($_GET['queue_no']) ? trim($_GET['queue_no']) : '';
$status_filter = isset($_GET['status_filter']) ? trim($_GET['status_filter']) : '';
$case_type_filter = isset($_GET['case_type_filter']) ? trim($_GET['case_type_filter']) : '';

// A search is performed if ANY filter has a value
$has_performed_search = !empty($search) || !empty($date_from) || !empty($date_to) || 
                        !empty($queue_no) || !empty($status_filter) || !empty($case_type_filter);

// Pagination settings (only used when search is performed)
$records_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// Visit search parameters
$visit_search = isset($_GET['visit_search']) ? trim($_GET['visit_search']) : '';
$visit_date_from = isset($_GET['visit_date_from']) ? trim($_GET['visit_date_from']) : '';
$visit_date_to = isset($_GET['visit_date_to']) ? trim($_GET['visit_date_to']) : '';

$has_performed_visit_search = !empty($visit_search) || !empty($visit_date_from) || !empty($visit_date_to);

// ============================================
// CASE RECORDS QUERY (ONLY IF SEARCH PERFORMED)
// ============================================
$params = [];
$types = "";
$where_clause = "1=1 AND comp.category IN ('general_cases', 'womens_desk')";
$total_records = 0;
$total_pages = 0;
$reports = null;

if ($has_performed_search) {
    // Add search condition for name/contact/address/queue/case_type/status
    if (!empty($search)) {
        $search_term = "%$search%";
        $where_clause .= " AND (comp.name LIKE ? OR 
                               comp.contact_number LIKE ? OR 
                               comp.address LIKE ? OR 
                               c.queue_number LIKE ? OR
                               c.case_type LIKE ? OR
                               c.case_status LIKE ?)";
        for ($i = 0; $i < 6; $i++) {
            $params[] = $search_term;
            $types .= "s";
        }
    }
    
    // Add queue number filter
    if (!empty($queue_no)) {
        $queue_term = "%$queue_no%";
        $where_clause .= " AND c.queue_number LIKE ?";
        $params[] = $queue_term;
        $types .= "s";
    }
    
    // Add status filter
    if (!empty($status_filter)) {
        $where_clause .= " AND c.case_status = ?";
        $params[] = $status_filter;
        $types .= "s";
    }
    
    // Add case type filter
    if (!empty($case_type_filter)) {
        $where_clause .= " AND c.case_type = ?";
        $params[] = $case_type_filter;
        $types .= "s";
    }
    
    // Add date range filter
    if (!empty($date_from) && !empty($date_to)) {
        $where_clause .= " AND DATE(c.date_reported) BETWEEN ? AND ?";
        $params[] = $date_from;
        $params[] = $date_to;
        $types .= "ss";
    } elseif (!empty($date_from)) {
        $where_clause .= " AND DATE(c.date_reported) >= ?";
        $params[] = $date_from;
        $types .= "s";
    } elseif (!empty($date_to)) {
        $where_clause .= " AND DATE(c.date_reported) <= ?";
        $params[] = $date_to;
        $types .= "s";
    }

    // Get total count for case records pagination
    $count_query = "
        SELECT COUNT(*) as total
        FROM complaints c
        JOIN complainants comp ON c.complainant_id = comp.complainant_id
        WHERE $where_clause
    ";

    if (!empty($params)) {
        $stmt = $conn->prepare($count_query);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $count_result = $stmt->get_result();
            $total_records = $count_result->fetch_assoc()['total'];
            $stmt->close();
        }
    } else {
        $count_result = $conn->query($count_query);
        $total_records = $count_result ? $count_result->fetch_assoc()['total'] : 0;
    }

    $total_pages = ceil($total_records / $records_per_page);

    // Prepare main query for case records
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
            u.full_name as reported_by_name
        FROM complaints c
        JOIN complainants comp ON c.complainant_id = comp.complainant_id
        LEFT JOIN users u ON c.reported_by = u.user_id
        WHERE $where_clause
        ORDER BY c.date_reported DESC, c.created_at DESC
        LIMIT ? OFFSET ?
    ";

    // Add pagination parameters
    $params[] = $records_per_page;
    $params[] = $offset;
    $types .= "ii";

    // Execute main query with prepared statement
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $reports = $stmt->get_result();
        $stmt->close();
    }
}

// ============================================
// VISIT RECORDS QUERY (ONLY IF SEARCH PERFORMED)
// ============================================
$visit_params = [];
$visit_types = "";
$visit_where = "1=1 AND comp.category = 'visit'";
$total_visit_records = 0;
$total_visit_pages = 0;
$visit_reports = null;

if ($has_performed_visit_search) {
    // Add visit search condition
    if (!empty($visit_search)) {
        $visit_search_term = "%$visit_search%";
        $visit_where .= " AND (comp.name LIKE ? OR 
                               comp.contact_number LIKE ? OR 
                               comp.address LIKE ? OR 
                               c.queue_number LIKE ? OR
                               comp.visit_reason LIKE ?)";
        for ($i = 0; $i < 5; $i++) {
            $visit_params[] = $visit_search_term;
            $visit_types .= "s";
        }
    }
    
    // Add date range for visits
    if (!empty($visit_date_from) && !empty($visit_date_to)) {
        $visit_where .= " AND DATE(c.date_reported) BETWEEN ? AND ?";
        $visit_params[] = $visit_date_from;
        $visit_params[] = $visit_date_to;
        $visit_types .= "ss";
    } elseif (!empty($visit_date_from)) {
        $visit_where .= " AND DATE(c.date_reported) >= ?";
        $visit_params[] = $visit_date_from;
        $visit_types .= "s";
    } elseif (!empty($visit_date_to)) {
        $visit_where .= " AND DATE(c.date_reported) <= ?";
        $visit_params[] = $visit_date_to;
        $visit_types .= "s";
    }

    // Get total count for visit records pagination
    $visit_count_query = "
        SELECT COUNT(*) as total
        FROM complaints c
        JOIN complainants comp ON c.complainant_id = comp.complainant_id
        WHERE $visit_where
    ";

    if (!empty($visit_params)) {
        $visit_stmt = $conn->prepare($visit_count_query);
        if ($visit_stmt) {
            $visit_stmt->bind_param($visit_types, ...$visit_params);
            $visit_stmt->execute();
            $visit_count_result = $visit_stmt->get_result();
            $total_visit_records = $visit_count_result->fetch_assoc()['total'];
            $visit_stmt->close();
        }
    } else {
        $visit_count_result = $conn->query($visit_count_query);
        $total_visit_records = $visit_count_result ? $visit_count_result->fetch_assoc()['total'] : 0;
    }

    $total_visit_pages = ceil($total_visit_records / $records_per_page);

    // Prepare main query for visit records
    $visit_query = "
        SELECT 
            c.complaint_id,
            c.queue_number,
            c.date_reported,
            comp.name as visitor_name, 
            comp.contact_number,
            comp.address,
            comp.photo_path,
            comp.visit_reason
        FROM complaints c
        JOIN complainants comp ON c.complainant_id = comp.complainant_id
        WHERE $visit_where
        ORDER BY c.date_reported DESC
        LIMIT ? OFFSET ?
    ";

    // Add pagination parameters for visits
    $visit_params[] = $records_per_page;
    $visit_params[] = $offset;
    $visit_types .= "ii";

    // Execute visit query with prepared statement
    $visit_stmt = $conn->prepare($visit_query);
    if ($visit_stmt) {
        $visit_stmt->bind_param($visit_types, ...$visit_params);
        $visit_stmt->execute();
        $visit_reports = $visit_stmt->get_result();
        $visit_stmt->close();
    }
}

// ============================================
// STATISTICS
// ============================================

// Get total counts for today
$today_date = date('Y-m-d');
$today_cases_query = "
    SELECT COUNT(*) as today_count
    FROM complaints c
    JOIN complainants comp ON c.complainant_id = comp.complainant_id
    WHERE comp.category IN ('general_cases', 'womens_desk')
        AND DATE(c.date_reported) = ?
";
$stmt = $conn->prepare($today_cases_query);
$stmt->bind_param("s", $today_date);
$stmt->execute();
$today_count = $stmt->get_result()->fetch_assoc()['today_count'] ?? 0;
$stmt->close();

$today_visits_query = "
    SELECT COUNT(*) as today_count
    FROM complaints c
    JOIN complainants comp ON c.complainant_id = comp.complainant_id
    WHERE comp.category = 'visit'
        AND DATE(c.date_reported) = ?
";
$stmt = $conn->prepare($today_visits_query);
$stmt->bind_param("s", $today_date);
$stmt->execute();
$today_visits = $stmt->get_result()->fetch_assoc()['today_count'] ?? 0;
$stmt->close();

// Get pending cases count
$pending_query = "
    SELECT COUNT(*) as pending_count
    FROM complaints c
    JOIN complainants comp ON c.complainant_id = comp.complainant_id
    WHERE comp.category IN ('general_cases', 'womens_desk')
        AND c.case_status IN ('complaint', 'pending', 'follow-up')
";
$pending_result = $conn->query($pending_query);
$pending_cases = $pending_result ? $pending_result->fetch_assoc()['pending_count'] ?? 0 : 0;

/**
 * Helper functions
 */
function formatDateTime($datetime, $format = 'Y-m-d H:i:s') {
    if (empty($datetime) || $datetime == '0000-00-00' || $datetime == '0000-00-00 00:00:00') {
        return 'N/A';
    }
    $timestamp = strtotime($datetime);
    if ($timestamp === false || $timestamp <= 0) {
        return 'N/A';
    }
    return date($format, $timestamp);
}

function getStatusBadgeClass($status) {
    $status_map = [
        'complaint' => 'warning',
        'inquiry' => 'info',
        'follow-up' => 'secondary',
        'resolved' => 'success',
        'closed' => 'dark',
        'pending' => 'warning',
        'dismissed' => 'danger',
        'completed' => 'success'
    ];
    return $status_map[$status] ?? 'secondary';
}

function getCategoryDisplayName($category) {
    $category_map = [
        'general_cases' => 'General',
        'womens_desk' => 'Women\'s Desk',
        'visit' => 'Visit'
    ];
    return $category_map[$category] ?? ucfirst(str_replace('_', ' ', $category));
}

function getPhotoPath($photo_path) {
    if (!empty($photo_path) && file_exists('complainant_photos/' . $photo_path)) {
        return 'complainant_photos/' . $photo_path;
    }
    return 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'150\' height=\'150\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%23999\' stroke-width=\'1\' stroke-linecap=\'round\' stroke-linejoin=\'round\'%3E%3Crect x=\'2\' y=\'2\' width=\'20\' height=\'20\' rx=\'2.18\' ry=\'2.18\'%3E%3C/rect%3E%3Ccircle cx=\'8.5\' cy=\'8.5\' r=\'1.5\'%3E%3C/circle%3E%3Cpolyline points=\'22 15 16 9 6 19 2 15\'%3E%3C/polyline%3E%3C/svg%3E';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Records - Cybercrime Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-dark: #0B1E33;
            --gradient-start: #667eea;
            --gradient-end: #764ba2;
        }
        
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
            margin-left: 260px;
            padding: 20px;
            min-height: 100vh;
        }
        
        .report-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .search-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid #e9ecef;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: transform 0.3s;
        }
        
        .stat-card.visits {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        
        .stat-card.pending {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .stat-card.results {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
        }
        
        .btn-search {
            background: linear-gradient(135deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
            border: none;
            padding: 10px 25px;
            font-weight: 600;
        }
        
        .btn-print {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            padding: 10px 25px;
            font-weight: 600;
        }
        
        .table th {
            background: #f8f9fa;
            white-space: nowrap;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: #f8f9fa;
            border-radius: 12px;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #cbd5e0;
            margin-bottom: 20px;
        }
        
        .photo-thumbnail {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            cursor: pointer;
        }
        
        .queue-badge {
            background: #f0f0f0;
            padding: 4px 8px;
            border-radius: 6px;
            font-family: monospace;
            font-weight: bold;
            font-size: 0.8rem;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .stat-number {
                font-size: 1.3rem;
            }
        }
        
        @media print {
            .no-print, .sidebar, .search-section, .btn, .pagination-container, .stat-card {
                display: none !important;
            }
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- Sidebar -->
            <div class="col-auto p-0 sidebar" id="sidebar">
                <div class="sidebar-logo">
                    <div class="logo-frame">
                        <img src="videos/uploads/cyberlogo.png" alt="Logo" class="logo-img" 
                             onerror="this.src='https://via.placeholder.com/100?text=ACG'">
                    </div>
                    <div class="logo-text">CSPCRT</div>
                </div>
                <nav>
                    <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
                    <a href="category_selection.php"><i class="bi bi-plus-circle"></i> New Complaint</a>
                    <a href="queue.php"><i class="bi bi-list-ol"></i> Queue Management</a>
                    <a href="records.php"class="active"><i class="bi bi-folder"></i> Records</a>
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

            <!-- Main Content -->
            <div class="col main-content">
                <!-- Mobile Menu Toggle -->
                <button class="btn btn-primary d-md-none mb-3 no-print" onclick="toggleSidebar()">
                    <i class="bi bi-list"></i> Menu
                </button>
                
                <!-- Header with Print Button -->
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <h2 class="mb-0"><i class="bi bi-archive"></i> Records Management</h2>
                    <?php if ($has_performed_search && ($total_records > 0 || $total_visit_records > 0)): ?>
                    <button class="btn btn-print text-white" onclick="openPrintModal()">
                        <i class="bi bi-printer"></i> Print Records
                    </button>
                    <?php endif; ?>
                </div>
                
                <!-- Statistics Cards -->
                <div class="row mb-4 no-print">
                    <div class="col-md-3 mb-3">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $today_count; ?></div>
                            <div class="stat-label">Cases Today</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card visits">
                            <div class="stat-number"><?php echo $today_visits; ?></div>
                            <div class="stat-label">Visits Today</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card pending">
                            <div class="stat-number"><?php echo $pending_cases; ?></div>
                            <div class="stat-label">Pending Cases</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card results">
                            <div class="stat-number"><?php echo $total_records; ?></div>
                            <div class="stat-label">Search Results</div>
                        </div>
                    </div>
                    </div>

                        <!-- Follow-Up Monitoring Section -->
                        <div class="report-card mt-3" id="followUpSection">
                            <h5 class="mb-3"><i class="bi bi-clock-history"></i> Follow-Up Monitoring</h5>
                            <?php
                            $followups = [];
                            $fu_q = "SELECT f.*, comp.name as complainant_name, comp.queue_number as original_queue FROM follow_ups f LEFT JOIN complainants comp ON comp.complainant_id = f.complainant_id ORDER BY f.updated_at DESC LIMIT 200";
                            $fu_res = $conn->query($fu_q);
                            if ($fu_res && $fu_res->num_rows > 0) {
                                echo '<div class="table-responsive"><table class="table table-bordered table-hover"><thead><tr><th>#</th><th>Queue</th><th>Name</th><th>Assigned</th><th>Status</th><th>Remarks</th><th>Updated</th><th class="no-print">Action</th></tr></thead><tbody>';
                                $i = 1;
                                while ($r = $fu_res->fetch_assoc()) {
                                    $qnum = htmlspecialchars($r['queue_number'] ?: $r['original_queue']);
                                    $name = htmlspecialchars($r['complainant_name'] ?: 'Unknown');
                                    $assigned = htmlspecialchars($r['assigned_investigator'] ?: '');
                                    $status = htmlspecialchars($r['status']);
                                    $remarks = nl2br(htmlspecialchars(substr($r['remarks'] ?? '', 0, 150)));
                                    $updated = date('Y-m-d H:i', strtotime($r['updated_at']));
                                    $complaintId = $r['complaint_id'];
                                    $viewLink = $complaintId ? 'view_complaint.php?id=' . intval($complaintId) : 'records.php?queue=' . urlencode($qnum);
                                    echo "<tr>
                                        <td>{$i}</td>
                                        <td><span class=\"queue-badge\">{$qnum}</span></td>
                                        <td>{$name}</td>
                                        <td>{$assigned}</td>
                                        <td>{$status}</td>
                                        <td>{$remarks}</td>
                                        <td>{$updated}</td>
                                        <td class=\"no-print\"><a href=\"{$viewLink}\" class=\"btn btn-sm btn-info\"><i class=\"bi bi-eye\"></i></a></td>
                                    </tr>";
                                    $i++;
                                }
                                echo '</tbody></table></div>';
                            } else {
                                echo '<div class="empty-state"><i class="bi bi-clock-history"></i><h5>No Follow-Up entries</h5><p>Resolved cases moved to follow-up will appear here for monitoring.</p></div>';
                            }
                            ?>
                        </div>
                
                <!-- Search Section -->
                <div class="search-section no-print">
                    <h5><i class="bi bi-search"></i> Search Case Records</h5>
                    <form method="GET" action="" id="filterForm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Date From</label>
                                <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Date To</label>
                                <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Queue Number</label>
                                <input type="text" class="form-control" name="queue_no" placeholder="e.g., 2024-001" value="<?php echo htmlspecialchars($queue_no); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Name</label>
                                <input type="text" class="form-control" name="search" placeholder="Search by name..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Purpose</label>
                                <select class="form-select" name="status_filter">
                                    <option value="">All Purposes</option>
                                    <option value="complaint" <?php echo $status_filter == 'complaint' ? 'selected' : ''; ?>>Complaint</option>
                                    <option value="inquiry" <?php echo $status_filter == 'inquiry' ? 'selected' : ''; ?>>Inquiry</option>
                                    <option value="follow-up" <?php echo $status_filter == 'follow-up' ? 'selected' : ''; ?>>Follow-up</option>
                                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="resolved" <?php echo $status_filter == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                    <option value="closed" <?php echo $status_filter == 'closed' ? 'selected' : ''; ?>>Closed</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Case Type</label>
                                <select class="form-select" name="case_type_filter">
                                    <option value="">All Types</option>
                                    <option value="theft" <?php echo $case_type_filter == 'theft' ? 'selected' : ''; ?>>Theft</option>
                                    <option value="cybercrime" <?php echo $case_type_filter == 'cybercrime' ? 'selected' : ''; ?>>Cybercrime</option>
                                    <option value="fraud" <?php echo $case_type_filter == 'fraud' ? 'selected' : ''; ?>>Fraud</option>
                                    <option value="violence" <?php echo $case_type_filter == 'violence' ? 'selected' : ''; ?>>Violence</option>
                                </select>
                            </div>
                            <div class="col-md-8 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-search text-white">
                                    <i class="bi bi-search"></i> Search Records
                                </button>
                                <a href="records.php" class="btn btn-secondary text-white">
                                    <i class="bi bi-x-circle"></i> Clear All
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Case Records Results -->
                <div class="report-card">
                    <h5 class="mb-3">
                        <i class="bi bi-briefcase"></i> Case Records Results
                        <?php if ($has_performed_search && $total_records > 0): ?>
                            <span class="badge bg-primary ms-2"><?php echo $total_records; ?> found</span>
                        <?php endif; ?>
                    </h5>
                    
                    <?php if (!$has_performed_search): ?>
                        <div class="empty-state">
                            <i class="bi bi-folder2-open"></i>
                            <h5>No Records to Display</h5>
                            <p class="text-muted">Please use the search form above to find case records.<br>
                            You can search by date range, queue number, name, status, or case type.</p>
                        </div>
                    <?php elseif ($has_performed_search && $reports && $reports->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Photo</th>
                                        <th>Queue #</th>
                                        <th>Date</th>
                                        <th>Complainant</th>
                                        <th>Contact</th>
                                        <th>Category</th>
                                        <th>Case Type</th>
                                        <th>Status</th>
                                        <th>Investigator</th>
                                        <th class="no-print">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $row_num = $offset + 1; ?>
                                    <?php while ($row = $reports->fetch_assoc()): 
                                        $photo_src = getPhotoPath($row['photo_path'] ?? '');
                                        $category_display = getCategoryDisplayName($row['category']);
                                        $category_class = $row['category'] == 'general_cases' ? 'category-general' : 'category-womens';
                                        $status_class = getStatusBadgeClass($row['case_status']);
                                    ?>
                                    <tr>
                                        <td><?php echo $row_num++; ?></td>
                                        <td><img src="<?php echo $photo_src; ?>" class="photo-thumbnail" onclick="showPhoto(this, '<?php echo htmlspecialchars($row['complainant_name']); ?>', '<?php echo htmlspecialchars($row['queue_number']); ?>')"></td>
                                        <td><span class="queue-badge"><?php echo htmlspecialchars($row['queue_number']); ?></span></td>
                                        <td><?php echo formatDateTime($row['date_reported'], 'Y-m-d'); ?></td>
                                        <td><?php echo htmlspecialchars($row['complainant_name']); ?><br><small><?php echo htmlspecialchars(substr($row['address'], 0, 30)); ?></small></td>
                                        <td><?php echo htmlspecialchars($row['contact_number']); ?></td>
                                        <td><span class="badge bg-<?php echo $row['category'] == 'general_cases' ? 'primary' : 'danger'; ?>"><?php echo $category_display; ?></span></td>
                                        <td><?php echo htmlspecialchars(str_replace('_', ' ', ucwords($row['case_type'] ?? 'N/A'))); ?></td>
                                        <td><span class="badge bg-<?php echo $status_class; ?>"><?php echo ucwords(str_replace('-', ' ', $row['case_status'] ?? 'N/A')); ?></span></td>
                                        <td><?php echo htmlspecialchars($row['assigned_investigator'] ?? 'Unassigned'); ?></td>
                                        <td class="no-print"><a href="view_complaint.php?id=<?php echo $row['complaint_id']; ?>" class="btn btn-sm btn-info"><i class="bi bi-eye"></i></a></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if ($total_pages > 1): ?>
                        <div class="pagination-container mt-3">
                            <nav>
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&per_page=<?php echo $records_per_page; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&queue_no=<?php echo urlencode($queue_no); ?>&status_filter=<?php echo urlencode($status_filter); ?>&case_type_filter=<?php echo urlencode($case_type_filter); ?>">Previous</a>
                                    </li>
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&per_page=<?php echo $records_per_page; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&queue_no=<?php echo urlencode($queue_no); ?>&status_filter=<?php echo urlencode($status_filter); ?>&case_type_filter=<?php echo urlencode($case_type_filter); ?>"><?php echo $i; ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&per_page=<?php echo $records_per_page; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&queue_no=<?php echo urlencode($queue_no); ?>&status_filter=<?php echo urlencode($status_filter); ?>&case_type_filter=<?php echo urlencode($case_type_filter); ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                        
                    <?php elseif ($has_performed_search): ?>
                        <div class="empty-state">
                            <i class="bi bi-search"></i>
                            <h5>No Records Found</h5>
                            <p>No case records match your search criteria.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Visit Records Section -->
                <div class="search-section no-print mt-3">
                    <h5><i class="bi bi-calendar-check"></i> Search Visit Records</h5>
                    <form method="GET" action="" id="visitFilterForm">
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label fw-bold">Date From</label>
                                <input type="date" class="form-control" name="visit_date_from" value="<?php echo htmlspecialchars($visit_date_from); ?>">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-bold">Date To</label>
                                <input type="date" class="form-control" name="visit_date_to" value="<?php echo htmlspecialchars($visit_date_to); ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-search text-white w-100">
                                    <i class="bi bi-search"></i> Search
                                </button>
                            </div>
                            <div class="col-12">
                                <input type="text" class="form-control" name="visit_search" placeholder="Search by name, contact, queue, or reason..." value="<?php echo htmlspecialchars($visit_search); ?>">
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Visit Records Results -->
                <div class="report-card">
                    <h5 class="mb-3">
                        <i class="bi bi-people"></i> Visit Records Results
                        <?php if ($has_performed_visit_search && $total_visit_records > 0): ?>
                            <span class="badge bg-info ms-2"><?php echo $total_visit_records; ?> found</span>
                        <?php endif; ?>
                    </h5>
                    
                    <?php if (!$has_performed_visit_search): ?>
                        <div class="empty-state">
                            <i class="bi bi-calendar-x"></i>
                            <h5>No Visit Records to Display</h5>
                            <p>Please use the search form above to find visit records.</p>
                        </div>
                    <?php elseif ($has_performed_visit_search && $visit_reports && $visit_reports->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Photo</th>
                                        <th>Queue #</th>
                                        <th>Date</th>
                                        <th>Visitor Name</th>
                                        <th>Contact</th>
                                        <th>Reason for Visit</th>
                                        <th class="no-print">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $visit_num = 1; ?>
                                    <?php while ($row = $visit_reports->fetch_assoc()): 
                                        $photo_src = getPhotoPath($row['photo_path'] ?? '');
                                    ?>
                                    <tr>
                                        <td><?php echo $visit_num++; ?></td>
                                        <td><img src="<?php echo $photo_src; ?>" class="photo-thumbnail" onclick="showPhoto(this, '<?php echo htmlspecialchars($row['visitor_name']); ?>', '<?php echo htmlspecialchars($row['queue_number']); ?>')"></td>
                                        <td><span class="queue-badge"><?php echo htmlspecialchars($row['queue_number']); ?></span></td>
                                        <td><?php echo formatDateTime($row['date_reported'], 'Y-m-d'); ?></td>
                                        <td><?php echo htmlspecialchars($row['visitor_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['contact_number']); ?></td>
                                        <td><?php echo nl2br(htmlspecialchars(substr($row['visit_reason'] ?? '', 0, 80))); ?></td>
                                        <td class="no-print"><a href="view_complaint.php?id=<?php echo $row['complaint_id']; ?>" class="btn btn-sm btn-info"><i class="bi bi-eye"></i></a></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php elseif ($has_performed_visit_search): ?>
                        <div class="empty-state">
                            <i class="bi bi-calendar-x"></i>
                            <h5>No Visit Records Found</h5>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Print Modal -->
    <div class="modal fade" id="printModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white;">
                    <h5 class="modal-title"><i class="bi bi-printer"></i> Print Options</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select sections to print:</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="printCaseRecords" checked>
                            <label class="form-check-label" for="printCaseRecords">Case Records (<?php echo $total_records; ?> records)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="printVisitRecords" checked>
                            <label class="form-check-label" for="printVisitRecords">Visit Records (<?php echo $total_visit_records; ?> records)</label>
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Print will include all records matching your current search filters.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="printRecords()">
                        <i class="bi bi-printer"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Photo Modal -->
    <div class="modal fade" id="photoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <h5 class="modal-title"><i class="bi bi-camera"></i> Photo</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalPhoto" src="" alt="Photo" style="max-width: 100%; max-height: 60vh; border-radius: 10px;">
                    <div class="mt-3">
                        <p><strong>Name:</strong> <span id="modalName"></span></p>
                        <p><strong>Queue Number:</strong> <span id="modalQueue"></span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }
        
        function showPhoto(imgElement, name, queue) {
            document.getElementById('modalPhoto').src = imgElement.src;
            document.getElementById('modalName').textContent = name;
            document.getElementById('modalQueue').textContent = queue;
            new bootstrap.Modal(document.getElementById('photoModal')).show();
        }
        
        function openPrintModal() {
            new bootstrap.Modal(document.getElementById('printModal')).show();
        }
        
        function printRecords() {
            const printCase = document.getElementById('printCaseRecords').checked ? '1' : '0';
            const printVisit = document.getElementById('printVisitRecords').checked ? '1' : '0';
            
            // Get current search parameters
            const params = new URLSearchParams(window.location.search);
            params.append('custom_print', '1');
            params.append('case_records', printCase);
            params.append('visit_records', printVisit);
            
            bootstrap.Modal.getInstance(document.getElementById('printModal')).hide();
            window.location.href = 'records.php?' + params.toString();
        }
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const menuBtn = document.querySelector('.btn-primary.d-md-none');
            if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('show')) {
                if (!sidebar.contains(event.target) && !menuBtn?.contains(event.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });
    </script>
</body>
</html>
