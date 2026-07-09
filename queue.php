<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

require_once 'config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

// Handle AJAX refresh request
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    // Re-run the queries for AJAX refresh
    $all_queue = $conn->query("
        SELECT 
            c.complaint_id,
            c.queue_number,
            c.case_type,
            c.case_status,
            c.created_at,
            comp.name,
            comp.category,
            comp.status,
            comp.assigned_investigator,
            comp.is_priority
        FROM complaints c
        JOIN complainants comp ON c.complainant_id = comp.complainant_id
        WHERE comp.status = 'pending' AND (comp.is_priority = 0 OR comp.is_priority IS NULL)
        ORDER BY CAST(COALESCE(NULLIF(REGEXP_REPLACE(c.queue_number, '[^0-9]', ''), ''), '0') AS UNSIGNED) ASC
    ");
    
    $pending_queue = $conn->query("
        SELECT 
            c.complaint_id,
            c.queue_number,
            c.case_type,
            c.case_status,
            c.created_at,
            comp.name,
            comp.category,
            comp.status,
            comp.assigned_investigator,
            comp.is_priority
        FROM complaints c
        JOIN complainants comp ON c.complainant_id = comp.complainant_id
        WHERE comp.status = 'pending' AND (comp.is_priority = 0 OR comp.is_priority IS NULL)
        ORDER BY CAST(COALESCE(NULLIF(REGEXP_REPLACE(c.queue_number, '[^0-9]', ''), ''), '0') AS UNSIGNED) ASC
    ");
    
    $priority_queue = $conn->query("
        SELECT 
            c.complaint_id,
            c.queue_number,
            c.case_type,
            c.case_status,
            c.created_at,
            comp.name,
            comp.category,
            comp.status,
            comp.assigned_investigator,
            comp.is_priority
        FROM complaints c
        JOIN complainants comp ON c.complainant_id = comp.complainant_id
        WHERE comp.status = 'pending' AND comp.is_priority = 1
        ORDER BY CAST(COALESCE(NULLIF(REGEXP_REPLACE(c.queue_number, '[^0-9]', ''), ''), '0') AS UNSIGNED) ASC
    ");
    
    $being_served = $conn->query("
        SELECT 
            c.complaint_id,
            c.queue_number,
            c.case_type,
            c.case_status,
            c.created_at,
            comp.name,
            comp.category,
            comp.status,
            comp.assigned_investigator,
            comp.is_priority
        FROM complaints c
        JOIN complainants comp ON c.complainant_id = comp.complainant_id
        WHERE comp.status = 'processing'
        ORDER BY c.created_at ASC
    ");
    
    // Get counts for badges
    $count_all = $conn->query("SELECT COUNT(*) as count FROM complainants WHERE status IN ('pending', 'processing')")->fetch_assoc()['count'];
    $count_pending = $conn->query("SELECT COUNT(*) as count FROM complainants WHERE status = 'pending'")->fetch_assoc()['count'];
    $count_processing = $conn->query("SELECT COUNT(*) as count FROM complainants WHERE status = 'processing'")->fetch_assoc()['count'];
    
    $male_investigators = [
        'PCPT - Babagay, Angelo A',
        'PMSg - Madregalijos, Eddie S',
        'PMSg - Pacardo, Karlo C',
        'PMSg - Bustamante, Paul Christian E',
        'PSSg - Balin, Levelyn S',
        'PSSg - Magdaraog, Joseph M',
        'PSSg - Lumanog, Ryan D',
        'PSSg - Mariano, Marc V',
        'Pcpl - Abelinde, James Robert T',
        'Pat - Balaguer, Efren S',
        'Pat - Evangelista, Jay Andrew G'
    ];

    $female_investigators = [
        'PSMS - Rivera, Leah H',
        'PSMS - Floro Bhong, Oida S',
        'Pcpl - Virata, Mergielyn C'
    ];
    
    function getInvestigatorCategory($investigator_name, $male_investigators, $female_investigators) {
        if (in_array($investigator_name, $male_investigators)) return 'male';
        if (in_array($investigator_name, $female_investigators)) return 'female';
        return 'unknown';
    }
    
    // Output JSON with all updated content
    header('Content-Type: application/json');
    
    // Build All Queue HTML
    ob_start();
    if ($all_queue && $all_queue->num_rows > 0):
        while ($row = $all_queue->fetch_assoc()): 
            $investigator_type = getInvestigatorCategory($row['assigned_investigator'], $male_investigators, $female_investigators);
        ?>
        <tr data-complaint-id="<?php echo (int)$row['complaint_id']; ?>">
            <td><span class="queue-number"><?php echo htmlspecialchars($row['queue_number']); ?></span></td>
            <td><?php echo htmlspecialchars($row['name']); ?></td>
            <td><span class="category-badge category-<?php echo ($row['category'] == 'womens_desk') ? 'womens' : 'general'; ?>"><i class="bi <?php echo ($row['category'] == 'womens_desk') ? 'bi-shield-shaded' : 'bi-briefcase'; ?>"></i> <?php echo ($row['category'] == 'womens_desk') ? 'WD' : 'GC'; ?></span></td>
            <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $row['case_type']))); ?></td>
            <td><span class="investigator-badge investigator-<?php echo $investigator_type; ?>"><i class="bi bi-person-badge"></i> <?php echo htmlspecialchars($row['assigned_investigator']); ?></span></td>
            <td><small class="text-muted"><?php echo date('h:i A', strtotime($row['created_at'])); ?></small></td>
            <td class="text-center-actions">
                <a href="view_complaint.php?id=<?php echo (int)$row['complaint_id']; ?>" class="btn btn-sm btn-info" title="View"><i class="bi bi-eye"></i></a>
                <button onclick="announceAndMoveToServing(<?php echo (int)$row['complaint_id']; ?>, '<?php echo htmlspecialchars(addslashes($row['queue_number'])); ?>', '<?php echo htmlspecialchars(addslashes($row['assigned_investigator'])); ?>', '<?php echo htmlspecialchars(addslashes($row['name'])); ?>', '<?php echo htmlspecialchars(addslashes($row['category'])); ?>', '0', event)" class="btn btn-sm btn-announce" title="Announce & Start Service (2x)"><i class="bi bi-megaphone"></i> Announce</button>
            </span>
        </tr>
        <?php endwhile;
    else: ?>
        <tr id="noAllQueueData"><td colspan="7" class="text-center py-5"><i class="bi bi-inbox" style="font-size:48px;color:#ccc;"></i><p class="text-muted mt-2 mb-0">No active items in queue</p></tr>
    <?php endif;
    $allQueueHtml = ob_get_clean();
    
    // Build Waiting Queue HTML
    ob_start();
    if ($pending_queue && $pending_queue->num_rows > 0):
        while ($row = $pending_queue->fetch_assoc()): 
            $investigator_type = getInvestigatorCategory($row['assigned_investigator'], $male_investigators, $female_investigators);
        ?>
        <tr data-complaint-id="<?php echo (int)$row['complaint_id']; ?>">
            <td><span class="queue-number"><?php echo htmlspecialchars($row['queue_number']); ?></span></td>
            <td><?php echo htmlspecialchars($row['name']); ?></td>
            <td><span class="category-badge category-<?php echo ($row['category'] == 'womens_desk') ? 'womens' : 'general'; ?>"><i class="bi <?php echo ($row['category'] == 'womens_desk') ? 'bi-shield-shaded' : 'bi-briefcase'; ?>"></i> <?php echo ($row['category'] == 'womens_desk') ? 'WD' : 'GC'; ?></span></td>
            <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $row['case_type']))); ?></td>
            <td><span class="investigator-badge investigator-<?php echo $investigator_type; ?>"><i class="bi bi-person-badge"></i> <?php echo htmlspecialchars($row['assigned_investigator']); ?></span></td>
            <td><small class="text-muted"><?php echo date('h:i A', strtotime($row['created_at'])); ?></small></td>
            <td class="text-center-actions">
                <a href="view_complaint.php?id=<?php echo (int)$row['complaint_id']; ?>" class="btn btn-sm btn-info"><i class="bi bi-eye"></i></a>
                <button onclick="announceAndMoveToServing(<?php echo (int)$row['complaint_id']; ?>, '<?php echo htmlspecialchars(addslashes($row['queue_number'])); ?>', '<?php echo htmlspecialchars(addslashes($row['assigned_investigator'])); ?>', '<?php echo htmlspecialchars(addslashes($row['name'])); ?>', '<?php echo htmlspecialchars(addslashes($row['category'])); ?>', '0', event)" class="btn btn-sm btn-announce" title="Announce & Start Service (2x)"><i class="bi bi-megaphone"></i> Announce</button>
            </span>
        </tr>
        <?php endwhile;
    else: ?>
        <tr id="noWaitingData"><td colspan="7" class="text-center py-5"><i class="bi bi-inbox" style="font-size:48px;color:#ccc;"></i><p class="text-muted mt-2 mb-0">No waiting items</p></tr>
    <?php endif;
    $waitingQueueHtml = ob_get_clean();
    
    // Build Priority Queue HTML
    ob_start();
    if ($priority_queue && $priority_queue->num_rows > 0):
        while ($row = $priority_queue->fetch_assoc()): 
            $investigator_type = getInvestigatorCategory($row['assigned_investigator'], $male_investigators, $female_investigators);
        ?>
        <tr data-complaint-id="<?php echo (int)$row['complaint_id']; ?>">
            <td>
                <span class="queue-number priority-number"><?php echo htmlspecialchars($row['queue_number']); ?></span>
                <span class="priority-badge ms-1"><i class="bi bi-star-fill"></i> PRIORITY</span>
            </td>
            <td><?php echo htmlspecialchars($row['name']); ?></td>
            <td><span class="category-badge category-<?php echo ($row['category'] == 'womens_desk') ? 'womens' : 'general'; ?>"><i class="bi <?php echo ($row['category'] == 'womens_desk') ? 'bi-shield-shaded' : 'bi-briefcase'; ?>"></i> <?php echo ($row['category'] == 'womens_desk') ? 'WD' : 'GC'; ?></span></td>
            <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $row['case_type']))); ?></td>
            <td><span class="investigator-badge investigator-<?php echo $investigator_type; ?>"><i class="bi bi-person-badge"></i> <?php echo htmlspecialchars($row['assigned_investigator']); ?></span></td>
            <td><small class="text-muted"><?php echo date('h:i A', strtotime($row['created_at'])); ?></small></td>
            <td class="text-center-actions">
                <a href="view_complaint.php?id=<?php echo (int)$row['complaint_id']; ?>" class="btn btn-sm btn-info" title="View"><i class="bi bi-eye"></i></a>
                <button onclick="announceAndMoveToServing(<?php echo (int)$row['complaint_id']; ?>, '<?php echo htmlspecialchars(addslashes($row['queue_number'])); ?>', '<?php echo htmlspecialchars(addslashes($row['assigned_investigator'])); ?>', '<?php echo htmlspecialchars(addslashes($row['name'])); ?>', '<?php echo htmlspecialchars(addslashes($row['category'])); ?>', '1', event)" class="btn btn-sm btn-announce" title="Announce & Start Service (2x)"><i class="bi bi-megaphone"></i> Announce</button>
            </span>
        </tr>
        <?php endwhile;
    endif;
    $priorityQueueHtml = ob_get_clean();
    
    // Build Serving Table HTML
    ob_start();
    if ($being_served && $being_served->num_rows > 0):
        while ($row = $being_served->fetch_assoc()): 
            $investigator_type = getInvestigatorCategory($row['assigned_investigator'], $male_investigators, $female_investigators);
        ?>
        <tr data-complaint-id="<?php echo (int)$row['complaint_id']; ?>" data-category="<?php echo htmlspecialchars($row['category']); ?>" data-is-priority="<?php echo $row['is_priority']; ?>">
            <td>
                <span class="queue-number"><?php echo htmlspecialchars($row['queue_number']); ?></span>
                <?php if ($row['is_priority'] == 1): ?>
                    <span class="priority-badge ms-1"><i class="bi bi-star-fill"></i> PRIORITY</span>
                <?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($row['name']); ?></td>
            <td><span class="category-badge category-<?php echo ($row['category'] == 'womens_desk') ? 'womens' : 'general'; ?>"><i class="bi <?php echo ($row['category'] == 'womens_desk') ? 'bi-shield-shaded' : 'bi-briefcase'; ?>"></i> <?php echo ($row['category'] == 'womens_desk') ? 'Women\'s Desk' : 'General'; ?></span></td>
            <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $row['case_type']))); ?></td>
            <td><span class="investigator-badge investigator-<?php echo $investigator_type; ?>"><i class="bi bi-person-badge"></i> <?php echo htmlspecialchars($row['assigned_investigator']); ?></span></td>
            <td><small class="text-muted"><?php echo date('h:i A', strtotime($row['created_at'])); ?></small></td>
            <td class="text-center-actions">
                <a href="view_complaint.php?id=<?php echo (int)$row['complaint_id']; ?>" class="btn btn-sm btn-info" title="View"><i class="bi bi-eye"></i></a>
                <button onclick="markAsCatered(<?php echo (int)$row['complaint_id']; ?>, '<?php echo htmlspecialchars(addslashes($row['queue_number'])); ?>', '<?php echo htmlspecialchars(addslashes($row['category'])); ?>', '<?php echo $row['is_priority']; ?>', event)" class="btn btn-sm btn-catered" title="Mark as Catered"><i class="bi bi-check-circle"></i> Catered</button>
            </td>
        </tr>
        <?php endwhile;
    else: ?>
        <tr id="noServingData"><td colspan="7" class="text-center py-4"><i class="bi bi-person-standing" style="font-size:32px;color:#ccc;"></i><p class="text-muted mt-2 mb-0">No clients are currently being served</p></tr>
    <?php endif;
    $servingQueueHtml = ob_get_clean();
    
    echo json_encode([
        'allQueueHtml' => $allQueueHtml,
        'waitingQueueHtml' => $waitingQueueHtml,
        'priorityQueueHtml' => $priorityQueueHtml,
        'servingQueueHtml' => $servingQueueHtml,
        'countAll' => $count_all,
        'countPending' => $count_pending,
        'countProcessing' => $count_processing
    ]);
    exit;
}

// Define investigators lists
$male_investigators = [
    'PCPT - Babagay, Angelo A',
    'PMSg - Madregalijos, Eddie S',
    'PMSg - Pacardo, Karlo C',
    'PMSg - Bustamante, Paul Christian E',
    'PSSg - Balin, Levelyn S',
    'PSSg - Magdaraog, Joseph M',
    'PSSg - Lumanog, Ryan D',
    'PSSg - Mariano, Marc V',
    'Pcpl - Abelinde, James Robert T',
    'Pat - Balaguer, Efren S',
    'Pat - Evangelista, Jay Andrew G'
];

$female_investigators = [
    'PSMS - Rivera, Leah H',
    'PSMS - Floro Bhong, Oida S',
    'Pcpl - Virata, Mergielyn C'
];

// Get counts
$count_all = $conn->query("SELECT COUNT(*) as count FROM complainants WHERE status IN ('pending', 'processing')")->fetch_assoc()['count'];
$count_pending = $conn->query("SELECT COUNT(*) as count FROM complainants WHERE status = 'pending'")->fetch_assoc()['count'];
$count_processing = $conn->query("SELECT COUNT(*) as count FROM complainants WHERE status = 'processing'")->fetch_assoc()['count'];
$count_priority = $conn->query("SELECT COUNT(*) as count FROM complainants WHERE is_priority = 1 AND status IN ('pending', 'processing')")->fetch_assoc()['count'];

// Get all complaints EXCLUDING priority cases (priority cases shown separately)
$all_queue = $conn->query("
    SELECT 
        c.complaint_id,
        c.queue_number,
        c.case_type,
        c.case_status,
        c.created_at,
        comp.name,
        comp.category,
        comp.status,
        comp.assigned_investigator,
        comp.is_priority
    FROM complaints c
    JOIN complainants comp ON c.complainant_id = comp.complainant_id
    WHERE comp.status = 'pending' AND (comp.is_priority = 0 OR comp.is_priority IS NULL)
    ORDER BY CAST(COALESCE(NULLIF(REGEXP_REPLACE(c.queue_number, '[^0-9]', ''), ''), '0') AS UNSIGNED) ASC
");

// Get pending complaints EXCLUDING priority cases (changed to waiting)
$pending_queue = $conn->query("
    SELECT 
        c.complaint_id,
        c.queue_number,
        c.case_type,
        c.case_status,
        c.created_at,
        comp.name,
        comp.category,
        comp.status,
        comp.assigned_investigator,
        comp.is_priority
    FROM complaints c
    JOIN complainants comp ON c.complainant_id = comp.complainant_id
    WHERE comp.status = 'pending' AND (comp.is_priority = 0 OR comp.is_priority IS NULL)
    ORDER BY CAST(COALESCE(NULLIF(REGEXP_REPLACE(c.queue_number, '[^0-9]', ''), ''), '0') AS UNSIGNED) ASC
");

// Get priority complaints for separate display (only waiting ones, not processing)
$priority_queue = $conn->query("
    SELECT 
        c.complaint_id,
        c.queue_number,
        c.case_type,
        c.case_status,
        c.created_at,
        comp.name,
        comp.category,
        comp.status,
        comp.assigned_investigator,
        comp.is_priority
    FROM complaints c
    JOIN complainants comp ON c.complainant_id = comp.complainant_id
    WHERE comp.status = 'pending' AND comp.is_priority = 1
    ORDER BY CAST(COALESCE(NULLIF(REGEXP_REPLACE(c.queue_number, '[^0-9]', ''), ''), '0') AS UNSIGNED) ASC
");

// Get clients currently being served (processing status)
$being_served = $conn->query("
    SELECT 
        c.complaint_id,
        c.queue_number,
        c.case_type,
        c.case_status,
        c.created_at,
        comp.name,
        comp.category,
        comp.status,
        comp.assigned_investigator,
        comp.is_priority
    FROM complaints c
    JOIN complainants comp ON c.complainant_id = comp.complainant_id
    WHERE comp.status = 'processing'
    ORDER BY c.created_at ASC
");

function getInvestigatorCategory($investigator_name, $male_investigators, $female_investigators) {
    if (in_array($investigator_name, $male_investigators)) return 'male';
    if (in_array($investigator_name, $female_investigators)) return 'female';
    return 'unknown';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Queue Management - Cybercrime System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
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
        .main-content { margin-left: 250px; padding: 20px; max-width: calc(100vw - 280px); width: calc(100% - 270px); }
        .status-badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; }
        .table-responsive { overflow-x: auto !important; }
        .table { width: 100%; table-layout: auto; }
        .table th, .table td { background: #f8f9fa; white-space: normal; word-break: break-word; }
        .table th { background: #f8f9fa; }
        .table td { vertical-align: middle; }
        .table th:nth-child(6), .table td:nth-child(6) { width: 95px; }
        .table th:nth-child(7), .table td:nth-child(7) { width: 125px; }
        .queue-number { font-family: monospace; font-weight: bold; font-size: 1rem; background: #f8f9fa; padding: 4px 8px; border-radius: 5px; display: inline-block; }
        .priority-number { background: #fff9c4; color: #856404; }
        .investigator-badge { font-size: 0.75rem; padding: 3px 8px; border-radius: 15px; display: inline-flex; white-space: normal; flex-wrap: wrap; max-width: 160px; }
        .category-badge { padding: 4px 8px; border-radius: 5px; font-size: 0.7rem; white-space: normal; }
        .investigator-male { background-color: #4299e1; color: white; }
        .investigator-female { background-color: #ed64a6; color: white; }
        .investigator-unknown { background-color: #a0aec0; color: white; }
        .btn-catered { background-color: #28a745; border-color: #28a745; color: white; padding: 4px 8px; font-size: 0.75rem; }
        .btn-catered:hover { background-color: #218838; color: white; }
        .btn-announce { background-color: #ffc107; border-color: #ffc107; color: #333; padding: 4px 8px; font-size: 0.75rem; }
        .btn-announce:hover { background-color: #e0a800; color: #333; }
        .btn-announce:disabled { opacity: 0.6; cursor: not-allowed; }
        .category-badge { padding: 4px 8px; border-radius: 5px; font-size: 0.7rem; white-space: nowrap; }
        .category-general { background-color: #4299e1; color: white; }
        .category-womens { background-color: #ed64a6; color: white; }
        .priority-badge { background-color: #fff9c4; color: #856404; padding: 3px 8px; border-radius: 15px; font-size: 0.7rem; font-weight: bold; display: inline-flex; align-items: center; gap: 4px; border: 1px solid #ffe082; }
        .toast-notification { position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 350px; animation: slideIn 0.5s ease-out; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        
        .priority-card-header {
            background: #fff9c4;
            color: #856404;
            border-bottom: 1px solid #ffe082;
        }
        .priority-table tbody tr {
            background-color: #fffef7;
            border-left: 3px solid #ffc107;
        }
        .priority-table tbody tr:hover {
            background-color: #fff9e6;
        }
        
        .serving-card-header {
            background: #e3f2fd;
            color: #1565c0;
            border-bottom: 1px solid #bbdefb;
        }
        .serving-table tbody tr {
            background-color: #f3f8ff;
            border-left: 3px solid #4299e1;
        }
        .serving-table tbody tr:hover {
            background-color: #e3f2fd;
        }
        
        .card {
            margin-bottom: 35px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .card-header {
            border-radius: 12px 12px 0 0 !important;
        }
        
        .floating-voice-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            z-index: 1000;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            transition: transform 0.2s;
        }
        .floating-voice-btn:hover {
            transform: scale(1.05);
        }
        .floating-voice-btn i {
            font-size: 28px;
        }
        
        @media (max-width: 768px) { 
            .main-content { margin-left: 0; } 
            .table { font-size: 0.7rem; } 
            .btn-catered, .btn-announce { padding: 2px 5px; font-size: 0.65rem; } 
            .queue-number { font-size: 0.8rem; }
            .floating-voice-btn {
                width: 50px;
                height: 50px;
                bottom: 20px;
                right: 20px;
            }
            .floating-voice-btn i {
                font-size: 24px;
            }
        }
        
        .text-center-actions {
            text-align: center;
            white-space: nowrap;
        }
    </style>
</head>
<body>
<div class="container-fluid p-0">
    <div class="row g-0">
        <!-- SIDEBAR -->
        <div class="col-auto p-0 sidebar">
            <div class="sidebar-logo">
                <div class="logo-frame"><img src="videos/uploads/cyberlogo.png" alt="ACG NAGA Logo" class="logo-img" onerror="this.src='https://via.placeholder.com/100?text=PNP'"></div>
                <div class="logo-text">CSPCRT</div>
            </div>
            <nav>
                <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
                <a href="category_selection.php"><i class="bi bi-plus-circle"></i> New Complaint</a>
                <a href="queue.php" class="active"><i class="bi bi-list-ol"></i> Queue Management</a>
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

        <!-- Main Content -->
        <div class="col main-content">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <h2><i class="bi bi-list-ol"></i> Queue Management</h2>
                <div>
                    <a href="tv_display.php" target="_blank" class="btn btn-danger btn-sm me-2"><i class="bi bi-tv"></i> TV Display</a>
                    <a href="category_selection.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle"></i> New Complaint</a>
                </div>
            </div>
            
            <!-- Priority Queue Section -->
            <?php if ($priority_queue && $priority_queue->num_rows > 0): ?>
            <div class="card priority-card">
                <div class="card-header priority-card-header">
                    <h6 class="mb-0"><i class="bi bi-star-fill" style="color: #ffc107;"></i> Priority Lane Queue <span class="badge" style="background: #ffc107; color: #856404; margin-left: 8px;"><?php echo $priority_queue->num_rows; ?> priority cases</span></h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 priority-table">
                            <thead class="table-light">
                                <tr><th>Queue #</th><th>Complainant</th><th>Category</th><th>Case Type</th><th>Investigator</th><th>Time</th><th class="text-center-actions">Actions</th></tr>
                            </thead>
                            <tbody id="priorityQueueBody">
                                <?php while ($row = $priority_queue->fetch_assoc()): 
                                    $investigator_type = getInvestigatorCategory($row['assigned_investigator'], $male_investigators, $female_investigators);
                                ?>
                                <tr data-complaint-id="<?php echo (int)$row['complaint_id']; ?>">
                                    <td>
                                        <span class="queue-number priority-number"><?php echo htmlspecialchars($row['queue_number']); ?></span>
                                        <span class="priority-badge ms-1"><i class="bi bi-star-fill"></i> PRIORITY</span>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><span class="category-badge category-<?php echo ($row['category'] == 'womens_desk') ? 'womens' : 'general'; ?>"><i class="bi <?php echo ($row['category'] == 'womens_desk') ? 'bi-shield-shaded' : 'bi-briefcase'; ?>"></i> <?php echo ($row['category'] == 'womens_desk') ? 'WD' : 'GC'; ?></span></td>
                                    <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $row['case_type']))); ?></td>
                                    <td><span class="investigator-badge investigator-<?php echo $investigator_type; ?>"><i class="bi bi-person-badge"></i> <?php echo htmlspecialchars($row['assigned_investigator']); ?></span></td>
                                    <td><span class="text-dark fw-semibold"><?php echo date('h:i A', strtotime($row['created_at'])); ?></span></td>
                                    <td class="text-center-actions">
                                        <a href="view_complaint.php?id=<?php echo (int)$row['complaint_id']; ?>" class="btn btn-sm btn-info" title="View"><i class="bi bi-eye"></i></a>
                                        <button onclick="announceAndMoveToServing(<?php echo (int)$row['complaint_id']; ?>, '<?php echo htmlspecialchars(addslashes($row['queue_number'])); ?>', '<?php echo htmlspecialchars(addslashes($row['assigned_investigator'])); ?>', '<?php echo htmlspecialchars(addslashes($row['name'])); ?>', '<?php echo htmlspecialchars(addslashes($row['category'])); ?>', '1', event)" class="btn btn-sm btn-announce" title="Announce & Start Service (2x)"><i class="bi bi-megaphone"></i> Announce</button>
                                    </span>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- General Queue Table -->
            <div class="card general-queue-card">
                <div class="card-header bg-white">
                    <ul class="nav nav-tabs card-header-tabs" role="tablist">
                        <li class="nav-item"><a class="nav-link active" id="all-tab" data-bs-toggle="tab" href="#all">All Queue <span class="badge bg-secondary" id="allCountBadge"><?php echo (int)$count_all; ?></span></a></li>
                        <li class="nav-item"><a class="nav-link" id="waiting-tab" data-bs-toggle="tab" href="#waiting">Waiting <span class="badge bg-warning" id="waitingCountBadge"><?php echo (int)$count_pending; ?></span></a></li>
                    </ul>
                </div>
                <div class="card-body p-0">
                    <div class="tab-content">
                        <!-- All Tab -->
                        <div class="tab-pane fade show active" id="all">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="allQueueTable">
                                    <thead class="table-light">
                                        <tr><th>Queue #</th><th>Complainant</th><th>Category</th><th>Case Type</th><th>Investigator</th><th>Time</th><th class="text-center-actions">Actions</th></tr>
                                    </thead>
                                    <tbody id="allQueueBody">
                                        <?php if ($all_queue && $all_queue->num_rows > 0): ?>
                                            <?php while ($row = $all_queue->fetch_assoc()): 
                                                $investigator_type = getInvestigatorCategory($row['assigned_investigator'], $male_investigators, $female_investigators);
                                            ?>
                                            <tr data-complaint-id="<?php echo (int)$row['complaint_id']; ?>">
                                                <td><span class="queue-number"><?php echo htmlspecialchars($row['queue_number']); ?></span></td>
                                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                                <td><span class="category-badge category-<?php echo ($row['category'] == 'womens_desk') ? 'womens' : 'general'; ?>"><i class="bi <?php echo ($row['category'] == 'womens_desk') ? 'bi-shield-shaded' : 'bi-briefcase'; ?>"></i> <?php echo ($row['category'] == 'womens_desk') ? 'WD' : 'GC'; ?></span></td>
                                                <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $row['case_type']))); ?></td>
                                                <td><span class="investigator-badge investigator-<?php echo $investigator_type; ?>"><i class="bi bi-person-badge"></i> <?php echo htmlspecialchars($row['assigned_investigator']); ?></span></td>
                                                <td><span class="text-dark fw-semibold"><?php echo date('h:i A', strtotime($row['created_at'])); ?></span></td>
                                                <td class="text-center-actions">
                                                    <a href="view_complaint.php?id=<?php echo (int)$row['complaint_id']; ?>" class="btn btn-sm btn-info" title="View"><i class="bi bi-eye"></i></a>
                                                    <button onclick="announceAndMoveToServing(<?php echo (int)$row['complaint_id']; ?>, '<?php echo htmlspecialchars(addslashes($row['queue_number'])); ?>', '<?php echo htmlspecialchars(addslashes($row['assigned_investigator'])); ?>', '<?php echo htmlspecialchars(addslashes($row['name'])); ?>', '<?php echo htmlspecialchars(addslashes($row['category'])); ?>', '0', event)" class="btn btn-sm btn-announce" title="Announce & Start Service (2x)"><i class="bi bi-megaphone"></i> Announce</button>
                                                </span>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr id="noAllQueueData"><td colspan="7" class="text-center py-5"><i class="bi bi-inbox" style="font-size:48px;color:#ccc;"></i><p class="text-muted mt-2 mb-0">No active items in queue</p></td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Waiting Tab (formerly Pending) -->
                        <div class="tab-pane fade" id="waiting">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="waitingQueueTable">
                                    <thead class="table-light">
                                        <tr><th>Queue #</th><th>Complainant</th><th>Category</th><th>Case Type</th><th>Investigator</th><th>Time</th><th class="text-center-actions">Actions</th></tr>
                                    </thead>
                                    <tbody id="waitingQueueBody">
                                        <?php if ($pending_queue && $pending_queue->num_rows > 0): ?>
                                            <?php while ($row = $pending_queue->fetch_assoc()): 
                                                $investigator_type = getInvestigatorCategory($row['assigned_investigator'], $male_investigators, $female_investigators);
                                            ?>
                                            <tr data-complaint-id="<?php echo (int)$row['complaint_id']; ?>">
                                                <td><span class="queue-number"><?php echo htmlspecialchars($row['queue_number']); ?></span></td>
                                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                                <td><span class="category-badge category-<?php echo ($row['category'] == 'womens_desk') ? 'womens' : 'general'; ?>"><i class="bi <?php echo ($row['category'] == 'womens_desk') ? 'bi-shield-shaded' : 'bi-briefcase'; ?>"></i> <?php echo ($row['category'] == 'womens_desk') ? 'WD' : 'GC'; ?></span></td>
                                                <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $row['case_type']))); ?></td>
                                                <td><span class="investigator-badge investigator-<?php echo $investigator_type; ?>"><i class="bi bi-person-badge"></i> <?php echo htmlspecialchars($row['assigned_investigator']); ?></span></td>
                                                <td><small class="text-muted"><?php echo date('h:i A', strtotime($row['created_at'])); ?></small></td>
                                                <td class="text-center-actions">
                                                    <a href="view_complaint.php?id=<?php echo (int)$row['complaint_id']; ?>" class="btn btn-sm btn-info"><i class="bi bi-eye"></i></a>
                                                    <button onclick="announceAndMoveToServing(<?php echo (int)$row['complaint_id']; ?>, '<?php echo htmlspecialchars(addslashes($row['queue_number'])); ?>', '<?php echo htmlspecialchars(addslashes($row['assigned_investigator'])); ?>', '<?php echo htmlspecialchars(addslashes($row['name'])); ?>', '<?php echo htmlspecialchars(addslashes($row['category'])); ?>', '0', event)" class="btn btn-sm btn-announce" title="Announce & Start Service (2x)"><i class="bi bi-megaphone"></i> Announce</button>
                                                </span>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr id="noWaitingData"><td colspan="7" class="text-center py-5"><i class="bi bi-inbox" style="font-size:48px;color:#ccc;"></i><p class="text-muted mt-2 mb-0">No waiting items</p></td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Currently Being Served / In Consultation Section -->
            <div class="card being-served-card">
                <div class="card-header serving-card-header">
                    <h6 class="mb-0"><i class="bi bi-person-check-fill" style="color: #4299e1;"></i> Currently Being Served / In Consultation 
                        <span class="badge" style="background: #4299e1; color: white; margin-left: 8px;" id="servingCountBadge"><?php echo $being_served->num_rows; ?> clients</span>
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 serving-table" id="servingTable">
                            <thead class="table-light">
                                <tr><th>Queue #</th><th>Complainant</th><th>Category</th><th>Case Type</th><th>Investigator</th><th>Started At</th><th class="text-center-actions">Actions</th></tr>
                            </thead>
                            <tbody id="servingBody">
                                <?php if ($being_served && $being_served->num_rows > 0): ?>
                                    <?php while ($row = $being_served->fetch_assoc()): 
                                        $investigator_type = getInvestigatorCategory($row['assigned_investigator'], $male_investigators, $female_investigators);
                                    ?>
                                    <tr data-complaint-id="<?php echo (int)$row['complaint_id']; ?>" data-category="<?php echo htmlspecialchars($row['category']); ?>" data-is-priority="<?php echo $row['is_priority']; ?>">
                                        <td>
                                            <span class="queue-number"><?php echo htmlspecialchars($row['queue_number']); ?></span>
                                            <?php if ($row['is_priority'] == 1): ?>
                                                <span class="priority-badge ms-1"><i class="bi bi-star-fill"></i> PRIORITY</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td>
                                            <span class="category-badge category-<?php echo ($row['category'] == 'womens_desk') ? 'womens' : 'general'; ?>">
                                                <i class="bi <?php echo ($row['category'] == 'womens_desk') ? 'bi-shield-shaded' : 'bi-briefcase'; ?>"></i> 
                                                <?php echo ($row['category'] == 'womens_desk') ? 'Women\'s Desk' : 'General'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $row['case_type']))); ?></td>
                                        <td><span class="investigator-badge investigator-<?php echo $investigator_type; ?>"><i class="bi bi-person-badge"></i> <?php echo htmlspecialchars($row['assigned_investigator']); ?></span></td>
                                        <td><span class="text-dark fw-semibold"><?php echo date('h:i A', strtotime($row['created_at'])); ?></span></td>
                                        <td class="text-center-actions">
                                            <a href="view_complaint.php?id=<?php echo (int)$row['complaint_id']; ?>" class="btn btn-sm btn-info" title="View"><i class="bi bi-eye"></i></a>
                                            <button onclick="markAsCatered(<?php echo (int)$row['complaint_id']; ?>, '<?php echo htmlspecialchars(addslashes($row['queue_number'])); ?>', '<?php echo htmlspecialchars(addslashes($row['category'])); ?>', '<?php echo $row['is_priority']; ?>', event)" class="btn btn-sm btn-catered" title="Mark as Catered"><i class="bi bi-check-circle"></i> Catered</button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr id="noServingData">
                                        <td colspan="7" class="text-center py-4">
                                            <i class="bi bi-person-standing" style="font-size:32px;color:#ccc;"></i>
                                            <p class="text-muted mt-2 mb-0">No clients are currently being served</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Floating Voice Button -->
<button class="floating-voice-btn" data-bs-toggle="modal" data-bs-target="#voiceSettingsModal">
    <i class="bi bi-mic"></i>
</button>

<!-- Voice Settings Modal -->
<div class="modal fade voice-settings-modal" id="voiceSettingsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title"><i class="bi bi-mic"></i> Announcement Voice Settings</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Select Voice:</label>
                    <select id="voiceSelect" class="form-select">
                        <option value="">-- Loading voices --</option>
                    </select>
                    <small class="text-muted">Choose from available voices in your browser</small>
                </div>
                <div class="row mb-3">
                    <div class="col-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="slowRate">
                            <label class="form-check-label" for="slowRate"><i class="bi bi-speedometer2"></i> Slower Speech</label>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="highPitch">
                            <label class="form-check-label" for="highPitch"><i class="bi bi-arrow-up"></i> Higher Pitch</label>
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" onclick="testVoice()" class="btn btn-outline-primary flex-grow-1"><i class="bi bi-play-circle"></i> Test Voice</button>
                    <button type="button" onclick="saveVoice()" class="btn btn-primary flex-grow-1"><i class="bi bi-save"></i> Save Settings</button>
                </div>
                <hr>
                <div class="text-muted small">
                    <i class="bi bi-info-circle"></i> Your selected voice will be saved and used for all queue announcements.
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let selectedVoice = null, availableVoices = [], speechRate = 0.85, speechPitch = 1;

function loadVoices() {
    if ('speechSynthesis' in window) {
        availableVoices = window.speechSynthesis.getVoices();
        if (availableVoices.length === 0) { setTimeout(loadVoices, 100); return; }
        const select = document.getElementById('voiceSelect');
        select.innerHTML = '<option value="">-- Select a voice --</option>';
        const enVoices = availableVoices.filter(v => v.lang.startsWith('en'));
        const otherVoices = availableVoices.filter(v => !v.lang.startsWith('en'));
        if (enVoices.length > 0) { 
            const group = document.createElement('optgroup'); 
            group.label = '🇺🇸 English Voices'; 
            enVoices.forEach(voice => { 
                const option = document.createElement('option'); 
                option.value = voice.name; 
                option.textContent = `${voice.name} (${voice.lang})`; 
                group.appendChild(option); 
            }); 
            select.appendChild(group); 
        }
        if (otherVoices.length > 0) { 
            const group = document.createElement('optgroup'); 
            group.label = 'Other Languages'; 
            otherVoices.forEach(voice => { 
                const option = document.createElement('option'); 
                option.value = voice.name; 
                option.textContent = `${voice.name} (${voice.lang})`; 
                group.appendChild(option); 
            }); 
            select.appendChild(group); 
        }
        const savedVoice = localStorage.getItem('selected_voice');
        const savedRate = localStorage.getItem('speech_rate');
        const savedPitch = localStorage.getItem('speech_pitch');
        if (savedRate) speechRate = parseFloat(savedRate);
        if (savedPitch) speechPitch = parseFloat(savedPitch);
        if (savedVoice) { 
            select.value = savedVoice; 
            selectedVoice = availableVoices.find(v => v.name === savedVoice); 
        }
        const slowRateCheck = document.getElementById('slowRate');
        const highPitchCheck = document.getElementById('highPitch');
        if (slowRateCheck) slowRateCheck.checked = speechRate < 0.85;
        if (highPitchCheck) highPitchCheck.checked = speechPitch > 1;
    }
}

const voiceSelect = document.getElementById('voiceSelect');
if (voiceSelect) voiceSelect.addEventListener('change', function(e) { 
    const voiceName = e.target.value; 
    if (voiceName) selectedVoice = availableVoices.find(v => v.name === voiceName); 
});

function testVoice() { 
    speakMessage("This is a test announcement. The voice system is working properly."); 
}

function saveVoice() { 
    if (selectedVoice) { 
        localStorage.setItem('selected_voice', selectedVoice.name); 
        localStorage.setItem('speech_rate', speechRate); 
        localStorage.setItem('speech_pitch', speechPitch); 
        showToastMessage('success', 'Voice preference saved!'); 
        const modal = bootstrap.Modal.getInstance(document.getElementById('voiceSettingsModal')); 
        if (modal) modal.hide(); 
    } else { 
        showToastMessage('warning', 'Please select a voice first'); 
    } 
}

function updateSpeechSettings() { 
    const slowRate = document.getElementById('slowRate'); 
    const highPitch = document.getElementById('highPitch'); 
    speechRate = slowRate && slowRate.checked ? 0.7 : 0.85; 
    speechPitch = highPitch && highPitch.checked ? 1.2 : 1; 
}

const slowRateElem = document.getElementById('slowRate'); 
const highPitchElem = document.getElementById('highPitch');
if (slowRateElem) slowRateElem.addEventListener('change', updateSpeechSettings);
if (highPitchElem) highPitchElem.addEventListener('change', updateSpeechSettings);

function openTVDisplayWithoutInterrupt() {
    var tvWindow = window.open('tv_display.php', 'TV_Display_Window');
    if (tvWindow && !tvWindow.closed) {
        try {
            tvWindow.blur();
            window.focus();
        } catch(e) {
            console.log("Could not blur TV window due to browser restrictions");
        }
    }
}

function speakMessage(message) {
    if ('speechSynthesis' in window) {
        window.speechSynthesis.cancel();
        const utterance = new SpeechSynthesisUtterance(message);
        if (selectedVoice) utterance.voice = selectedVoice;
        utterance.rate = speechRate;
        utterance.pitch = speechPitch;
        utterance.volume = 1;
        window.speechSynthesis.speak(utterance);
    } else {
        alert('Speech synthesis not supported.');
    }
}

function sendAnnouncementEndSignal() {
    const endSignal = {
        type: 'ANNOUNCEMENT_END',
        timestamp: Date.now()
    };
    localStorage.setItem('tv_mute_signal', JSON.stringify(endSignal));
    if (typeof BroadcastChannel !== 'undefined') {
        try {
            const channel = new BroadcastChannel('queue_updates');
            channel.postMessage(endSignal);
            setTimeout(() => channel.close(), 100);
        } catch(e) { console.log('BroadcastChannel error:', e); }
    }
    try {
        window.dispatchEvent(new CustomEvent('tv_announcement', { detail: endSignal }));
    } catch(e) {}
}

function sendAnnouncementStartSignal() {
    const startSignal = {
        type: 'ANNOUNCEMENT_START',
        timestamp: Date.now()
    };
    localStorage.setItem('tv_mute_signal', JSON.stringify(startSignal));
    if (typeof BroadcastChannel !== 'undefined') {
        try {
            const channel = new BroadcastChannel('queue_updates');
            channel.postMessage(startSignal);
            setTimeout(() => channel.close(), 100);
        } catch(e) { console.log('BroadcastChannel error:', e); }
    }
    try {
        window.dispatchEvent(new CustomEvent('tv_announcement', { detail: startSignal }));
    } catch(e) {}
}

function announceWithTVBackground(message, callback) {
    if (!('speechSynthesis' in window)) {
        openTVDisplayWithoutInterrupt();
        if (callback) callback();
        return;
    }
    
    let speechCount = 0;
    
    function speakNext() {
        if (speechCount >= 2) {
            sendAnnouncementEndSignal();
            if (callback) callback();
            return;
        }
        
        const utterance = new SpeechSynthesisUtterance(message);
        if (selectedVoice) utterance.voice = selectedVoice;
        utterance.rate = speechRate;
        utterance.pitch = speechPitch;
        utterance.volume = 1;
        
        utterance.onend = function() {
            speechCount++;
            setTimeout(speakNext, 200);
        };
        
        utterance.onerror = function() {
            speechCount = 2;
            sendAnnouncementEndSignal();
            if (callback) callback();
        };
        
        window.speechSynthesis.speak(utterance);
    }
    
    window.speechSynthesis.cancel();
    speakNext();
}

// FIXED: Send clear signal with queue number to verify on TV
function sendClearServingSignal(category, isPriority, queueNumber) {
    let clearCategory = '';
    if (isPriority == 1) {
        clearCategory = 'priority';
    } else if (category === 'womens_desk') {
        clearCategory = 'womens';
    } else {
        clearCategory = 'general';
    }
    
    const clearSignal = {
        type: 'CLEAR_SERVING',
        category: clearCategory,
        queueNumber: queueNumber,
        timestamp: Date.now()
    };
    
    localStorage.setItem('tv_clear_signal_' + Date.now(), JSON.stringify(clearSignal));
    
    if (typeof BroadcastChannel !== 'undefined') {
        try {
            const channel = new BroadcastChannel('queue_updates');
            channel.postMessage(clearSignal);
            setTimeout(() => channel.close(), 100);
        } catch(e) {}
    }
}

// FIXED: markAsCatered now passes queueNumber to sendClearServingSignal
function markAsCatered(complaintId, queueNumber, category, isPriority, event) {
    // Show modal confirmation before marking as catered to avoid accidental status changes
    const modalEl = document.getElementById('confirmCateredModal');
    if (!modalEl) {
        // Fallback to confirm if modal is unavailable
        if (!confirm('Confirm marking this client as CATERED? This should only be done after interview completion.')) {
            if (event) { event.stopPropagation(); event.preventDefault(); }
            return;
        }
        if (event) { event.stopPropagation(); event.preventDefault(); }
        doMarkAsCatered(complaintId, queueNumber, category, isPriority, event);
        return;
    }

    // Populate modal data attributes and show
    modalEl.dataset.complaintId = complaintId;
    modalEl.dataset.queueNumber = queueNumber;
    modalEl.dataset.category = category;
    modalEl.dataset.isPriority = isPriority;
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
    if (event) { event.stopPropagation(); event.preventDefault(); }
    

function doMarkAsCatered(complaintId, queueNumber, category, isPriority, event) {
    const button = event ? event.target.closest('.btn-catered') : document.querySelector(`.btn-catered[onclick*="${complaintId}"]`);
    if (!button) return;
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    const formData = new FormData();
    formData.append('complaint_id', complaintId);
    formData.append('new_status', 'completed');
    formData.append('queue_number', queueNumber);
    formData.append('catered_client', '1');
    formData.append('remarks', 'Marked as catered');

    fetch('update_status.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const row = document.querySelector('#servingBody tr[data-complaint-id="' + complaintId + '"]');
                if (row) { row.remove(); }
                const servingRows = document.querySelectorAll('#servingBody tr:not([id="noServingData"])');
                const servingCount = servingRows.length;
                const servingBadge = document.querySelector('#servingCountBadge');
                if (servingBadge) servingBadge.textContent = servingCount;
                if (servingCount === 0 && !document.querySelector('#servingBody #noServingData')) {
                    document.querySelector('#servingBody').innerHTML = '<tr id="noServingData"><td colspan="7" class="text-center py-4"><i class="bi bi-person-standing" style="font-size:32px;color:#ccc;"></i><p class="text-muted mt-2 mb-0">No clients are currently being served</p></table>';
                }
                showToastMessage('success', 'Client marked as catered and removed from queue');
                sendClearServingSignal(category, isPriority, queueNumber);
                refreshAllTables();
            } else {
                showToastMessage('warning', data.message || 'Failed to mark client as catered');
                button.disabled = false;
                button.innerHTML = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToastMessage('warning', 'An error occurred. Please try again.');
            button.disabled = false;
            button.innerHTML = originalText;
        });
}
    const button = event ? event.target.closest('.btn-catered') : document.querySelector(`.btn-catered[onclick*="${complaintId}"]`);
    if (!button) return;
    
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    
    const formData = new FormData();
    formData.append('complaint_id', complaintId);
    formData.append('new_status', 'completed');
    formData.append('queue_number', queueNumber);
    formData.append('catered_client', '1');
    formData.append('remarks', 'Marked as catered');
    
    fetch('update_status.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const row = document.querySelector('#servingBody tr[data-complaint-id="' + complaintId + '"]');
                if (row) {
                    row.remove();
                }
                const servingRows = document.querySelectorAll('#servingBody tr:not([id="noServingData"])');
                const servingCount = servingRows.length;
                const servingBadge = document.querySelector('#servingCountBadge');
                if (servingBadge) servingBadge.textContent = servingCount;
                if (servingCount === 0 && !document.querySelector('#servingBody #noServingData')) {
                    document.querySelector('#servingBody').innerHTML = '<tr id="noServingData"><td colspan="7" class="text-center py-4"><i class="bi bi-person-standing" style="font-size:32px;color:#ccc;"></i><p class="text-muted mt-2 mb-0">No clients are currently being served</p></table>';
                }
                showToastMessage('success', 'Client marked as catered and removed from queue');
                
                sendClearServingSignal(category, isPriority, queueNumber);
                
                refreshAllTables();
                
            } else {
                showToastMessage('warning', data.message || 'Failed to mark client as catered');
                button.disabled = false;
                button.innerHTML = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToastMessage('warning', 'An error occurred. Please try again.');
            button.disabled = false;
            button.innerHTML = originalText;
        });
}

function removeClientFromQueueTables(complaintId) {
    const allQueueRow = document.querySelector('#allQueueBody tr[data-complaint-id="' + complaintId + '"]');
    if (allQueueRow && allQueueRow.parentNode) allQueueRow.remove();
    const waitingRow = document.querySelector('#waitingQueueBody tr[data-complaint-id="' + complaintId + '"]');
    if (waitingRow && waitingRow.parentNode) waitingRow.remove();
    const priorityRow = document.querySelector('#priorityQueueBody tr[data-complaint-id="' + complaintId + '"]');
    if (priorityRow && priorityRow.parentNode) priorityRow.remove();
    updateQueueCounts();
}

function updateQueueCounts() {
    const allQueueRows = document.querySelectorAll('#allQueueBody tr:not([id="noAllQueueData"])');
    const waitingRows = document.querySelectorAll('#waitingQueueBody tr:not([id="noWaitingData"])');
    const priorityRows = document.querySelectorAll('#priorityQueueBody tr');
    const allCount = allQueueRows.length;
    const waitingCount = waitingRows.length;
    const priorityCount = priorityRows.length;
    const allBadge = document.querySelector('#allCountBadge');
    const waitingBadge = document.querySelector('#waitingCountBadge');
    const priorityBadge = document.querySelector('.priority-card-header .badge');
    if (allBadge) allBadge.textContent = allCount;
    if (waitingBadge) waitingBadge.textContent = waitingCount;
    if (priorityBadge) priorityBadge.textContent = priorityCount + ' priority cases';
    if (allCount === 0 && !document.querySelector('#allQueueBody #noAllQueueData')) {
        document.querySelector('#allQueueBody').innerHTML = '<tr id="noAllQueueData"><td colspan="7" class="text-center py-5"><i class="bi bi-inbox" style="font-size:48px;color:#ccc;"></i><p class="text-muted mt-2 mb-0">No active items in queue</p>';
    } else if (allCount > 0 && document.querySelector('#allQueueBody #noAllQueueData')) {
        document.querySelector('#allQueueBody #noAllQueueData').remove();
    }
    if (waitingCount === 0 && !document.querySelector('#waitingQueueBody #noWaitingData')) {
        document.querySelector('#waitingQueueBody').innerHTML = '<tr id="noWaitingData"><td colspan="7" class="text-center py-5"><i class="bi bi-inbox" style="font-size:48px;color:#ccc;"></i><p class="text-muted mt-2 mb-0">No waiting items</p>';
    } else if (waitingCount > 0 && document.querySelector('#waitingQueueBody #noWaitingData')) {
        document.querySelector('#waitingQueueBody #noWaitingData').remove();
    }
}

function updateTVDisplayInstant(queueNumber, assignedInvestigator, complainantName, category, isPriority) {
    const updateData = {
        type: 'QUEUE_UPDATE',
        data: {
            queueNumber: queueNumber,
            investigator: assignedInvestigator,
            complainantName: complainantName,
            category: category,
            isPriority: isPriority == 1
        }
    };
    localStorage.setItem('queue_update_' + Date.now(), JSON.stringify(updateData));
    if (typeof BroadcastChannel !== 'undefined') {
        try {
            const channel = new BroadcastChannel('queue_updates');
            channel.postMessage(updateData);
        } catch(e) { console.log('BroadcastChannel error:', e); }
    }
    let servingData = {};
    try {
        const saved = localStorage.getItem('tv_serving_data');
        if (saved) servingData = JSON.parse(saved);
    } catch(e) {}
    if (isPriority == 1) {
        servingData.priority = { queueNumber: queueNumber, investigator: assignedInvestigator, complainantName: complainantName };
    } else if (category === 'womens_desk') {
        servingData.womens = { queueNumber: queueNumber, investigator: assignedInvestigator, complainantName: complainantName };
    } else {
        servingData.general = { queueNumber: queueNumber, investigator: assignedInvestigator, complainantName: complainantName };
    }
    localStorage.setItem('tv_serving_data', JSON.stringify(servingData));
    localStorage.setItem('tv_serving_update', JSON.stringify(servingData));
}

function refreshAllTables() {
    fetch(window.location.href + '?ajax=1&t=' + Date.now())
        .then(response => response.json())
        .then(data => {
            const allQueueBody = document.querySelector('#allQueueBody');
            if (allQueueBody && data.allQueueHtml) {
                allQueueBody.innerHTML = data.allQueueHtml;
            }
            
            const waitingQueueBody = document.querySelector('#waitingQueueBody');
            if (waitingQueueBody && data.waitingQueueHtml) {
                waitingQueueBody.innerHTML = data.waitingQueueHtml;
            }
            
            const priorityQueueBody = document.querySelector('#priorityQueueBody');
            if (priorityQueueBody && data.priorityQueueHtml) {
                priorityQueueBody.innerHTML = data.priorityQueueHtml;
            }
            
            const servingBody = document.querySelector('#servingBody');
            if (servingBody && data.servingQueueHtml) {
                servingBody.innerHTML = data.servingQueueHtml;
            }
            
            if (data.countAll !== undefined) {
                const allBadge = document.querySelector('#allCountBadge');
                if (allBadge) allBadge.textContent = data.countAll;
            }
            if (data.countPending !== undefined) {
                const waitingBadge = document.querySelector('#waitingCountBadge');
                if (waitingBadge) waitingBadge.textContent = data.countPending;
            }
            if (data.countProcessing !== undefined) {
                const servingBadge = document.querySelector('#servingCountBadge');
                if (servingBadge) servingBadge.textContent = data.countProcessing;
            }
            
            const priorityRows = document.querySelectorAll('#priorityQueueBody tr');
            const priorityCount = priorityRows.length;
            const priorityBadge = document.querySelector('.priority-card-header .badge');
            if (priorityBadge) priorityBadge.textContent = priorityCount + ' priority cases';
        })
        .catch(error => {
            console.error('Refresh error:', error);
            location.reload();
        });
}

function announceAndMoveToServing(complaintId, queueNumber, assignedInvestigator, complainantName, category, isPriority, event) {
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }
    
    let button = null;
    if (event && event.target) {
        button = event.target.closest('.btn-announce');
    }
    if (!button) {
        button = document.querySelector(`.btn-announce[onclick*="${complaintId}"]`);
    }
    
    if (button) {
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Announcing...';
    }
    
    const announcementMessage = `Attention please. Queue number ${queueNumber}, please proceed to your assigned investigator for assistance. Thank you!`;
    
    sendAnnouncementStartSignal();
    showAnnouncementToast(queueNumber, assignedInvestigator, complainantName);
    openTVDisplayWithoutInterrupt();
    
    updateTVDisplayInstant(queueNumber, assignedInvestigator, complainantName, category, isPriority);
    
    setTimeout(function() {
        updateTVDisplayInstant(queueNumber, assignedInvestigator, complainantName, category, isPriority);
    }, 100);
    setTimeout(function() {
        updateTVDisplayInstant(queueNumber, assignedInvestigator, complainantName, category, isPriority);
    }, 500);
    
    announceWithTVBackground(announcementMessage, function() {
        const formData = new FormData();
        formData.append('complaint_id', complaintId);
        formData.append('new_status', 'processing');
        formData.append('queue_number', queueNumber);
        formData.append('assigned_investigator', assignedInvestigator);
        
        fetch('update_status.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    refreshAllTables();
                    showToastMessage('success', `${complainantName} (${queueNumber}) is now being served by ${assignedInvestigator}`);
                } else {
                    showToastMessage('warning', data.message || 'Failed to update status');
                    refreshAllTables();
                }
            })
            .catch(error => {
                console.error('Error updating status:', error);
                showToastMessage('warning', 'Error updating status, but announcement was made');
                refreshAllTables();
            });
    });
}

function showAnnouncementToast(queueNumber, assignedInvestigator, complainantName) { 
    const toastDiv = document.createElement('div'); 
    toastDiv.className = 'toast-notification alert alert-warning alert-dismissible fade show'; 
    toastDiv.style.background = 'linear-gradient(135deg, #ffc107, #ff9800)'; 
    toastDiv.style.color = '#333'; 
    toastDiv.innerHTML = `<div class="d-flex align-items-center"><i class="bi bi-megaphone-fill" style="font-size:24px;margin-right:15px;"></i><div><strong>📢 ANNOUNCEMENT (2x)</strong><br>Queue #${queueNumber}<br>${complainantName} → ${assignedInvestigator}</div><button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="alert"></button></div>`; 
    document.body.appendChild(toastDiv); 
    setTimeout(() => toastDiv.remove(), 6000); 
}

function showToastMessage(type, message) { 
    const existingToasts = document.querySelectorAll('.toast-notification'); 
    existingToasts.forEach(toast => toast.remove()); 
    const toastDiv = document.createElement('div'); 
    toastDiv.className = `toast-notification alert alert-${type === 'success' ? 'success' : 'warning'} alert-dismissible fade show`; 
    toastDiv.style.position = 'fixed'; 
    toastDiv.style.top = '20px'; 
    toastDiv.style.right = '20px'; 
    toastDiv.style.zIndex = '9999'; 
    toastDiv.style.minWidth = '300px'; 
    toastDiv.innerHTML = `<i class="bi bi-${type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill'} me-2"></i> ${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`; 
    document.body.appendChild(toastDiv); 
    setTimeout(() => toastDiv.remove(), 3000); 
}

let refreshInterval;
function startAutoRefresh() { 
    if (refreshInterval) clearInterval(refreshInterval); 
    refreshInterval = setInterval(function() { 
        if (!document.hidden) refreshAllTables(); 
    }, 60000); 
}
document.addEventListener('visibilitychange', function() { if (!document.hidden) startAutoRefresh(); });
if (window.speechSynthesis) { window.speechSynthesis.onvoiceschanged = loadVoices; }
setTimeout(loadVoices, 500);
startAutoRefresh();
</script>
<!-- Confirmation Modal for marking catered -->
<div class="modal fade" id="confirmCateredModal" tabindex="-1" aria-labelledby="confirmCateredModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmCateredModalLabel">Confirm Catered</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to mark this client as <strong>CATERED</strong>? This should only be done after the interview is completed.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="confirmCateredBtn" class="btn btn-primary">Confirm</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
        var modalEl = document.getElementById('confirmCateredModal');
        if (!modalEl) return;
        var confirmBtn = document.getElementById('confirmCateredBtn');
        confirmBtn.addEventListener('click', function() {
                var complaintId = modalEl.dataset.complaintId;
                var queueNumber = modalEl.dataset.queueNumber;
                var category = modalEl.dataset.category;
                var isPriority = modalEl.dataset.isPriority;
                var modal = bootstrap.Modal.getInstance(modalEl);
                modal.hide();
                doMarkAsCatered(complaintId, queueNumber, category, isPriority, null);
        });
});
</script>
</body>
</html>
