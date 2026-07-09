<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

require_once 'config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$complaint_id = $_GET['id'] ?? 0;

// Define investigators lists - UPDATED
$male_investigators = [
    'Pat - Balaguer, Efren S',
    'Pat - Evangelista, Jay Andrew G',
    'Pcpl - Abelinde, James Robert T',
    'PMSg - Madregalijos, Eddie S',
    'PMSg - Bustamante, Paul Christian E',
    'PMSg - Pacardo, Karlo C',
    'PSMS - Floro Bhong, Oida S',
    'PSSg - Lumanog, Ryan D',
    'PSSg - Magdaraog, Joseph M',
    'PSSg - Mariano, Marc V'
];

$female_investigators = [
    'Pcpl - Virata, Mergielyn C',
    'PSMS - Rivera, Leah H',
    'PSSg - Balin, Levelyn S'
];

$all_investigators = array_merge($male_investigators, $female_investigators);

// Handle investigator update
$update_message = '';
$update_status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_investigator'])) {
    $new_investigator = $_POST['new_investigator'] ?? '';
    $change_remarks = $_POST['change_remarks'] ?? '';
    $old_investigator = $_POST['old_investigator'] ?? '';
    
    if ($new_investigator && $new_investigator !== $old_investigator) {
        $update_query = "
            UPDATE complainants comp 
            JOIN complaints c ON comp.complainant_id = c.complainant_id 
            SET comp.assigned_investigator = ? 
            WHERE c.complaint_id = ?
        ";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("si", $new_investigator, $complaint_id);
        
        if ($stmt->execute()) {
            // Log the change
            $log_query = "INSERT INTO investigator_changes (complaint_id, old_investigator, new_investigator, remarks, changed_by, changed_at) VALUES (?, ?, ?, ?, ?, NOW())";
            $log_stmt = $conn->prepare($log_query);
            $changed_by = $_SESSION['username'] ?? 'System';
            $log_stmt->bind_param("issss", $complaint_id, $old_investigator, $new_investigator, $change_remarks, $changed_by);
            $log_stmt->execute();
            
            $update_status = 'success';
            $update_message = 'Investigator reassigned successfully!';
            
            // Refresh complaint data
            $query = "
                SELECT 
                    c.*,
                    comp.name as complainant_name,
                    comp.address,
                    comp.contact_number,
                    comp.category,
                    comp.status as complainant_status,
                    comp.assigned_investigator,
                    comp.created_at as filing_date,
                    comp.photo_path,
                    u.full_name as reported_by_name,
                    u.rank as reported_by_rank
                FROM complaints c
                JOIN complainants comp ON c.complainant_id = comp.complainant_id
                LEFT JOIN users u ON c.reported_by = u.user_id
                WHERE c.complaint_id = '$complaint_id'
            ";
            $result = $conn->query($query);
            $complaint = $result->fetch_assoc();
        } else {
            $update_status = 'error';
            $update_message = 'Failed to update investigator.';
        }
        $stmt->close();
    }
}

// Get complaint details with all information including photo_path
$query = "
    SELECT 
        c.*,
        comp.name as complainant_name,
        comp.address,
        comp.contact_number,
        comp.category,
        comp.status as complainant_status,
        comp.assigned_investigator,
        comp.created_at as filing_date,
        comp.photo_path,
        u.full_name as reported_by_name,
        u.rank as reported_by_rank
    FROM complaints c
    JOIN complainants comp ON c.complainant_id = comp.complainant_id
    LEFT JOIN users u ON c.reported_by = u.user_id
    WHERE c.complaint_id = '$complaint_id'
";

$result = $conn->query($query);
$complaint = $result->fetch_assoc();

if (!$complaint) {
    redirect('queue.php');
}

// Get the most recent previous investigator (last change before current)
$previous_query = "
    SELECT old_investigator, new_investigator, changed_at, remarks 
    FROM investigator_changes 
    WHERE complaint_id = ? 
    ORDER BY changed_at DESC 
    LIMIT 1
";
$prev_stmt = $conn->prepare($previous_query);
$prev_stmt->bind_param("i", $complaint_id);
$prev_stmt->execute();
$prev_result = $prev_stmt->get_result();
$previous_change = $prev_result->fetch_assoc();

// Helper function to get photo path
function getPhotoPathView($photo_path) {
    if (!empty($photo_path) && file_exists('complainant_photos/' . $photo_path)) {
        return 'complainant_photos/' . $photo_path;
    }
    return 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'150\' height=\'150\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%23999\' stroke-width=\'1\' stroke-linecap=\'round\' stroke-linejoin=\'round\'%3E%3Crect x=\'2\' y=\'2\' width=\'20\' height=\'20\' rx=\'2.18\' ry=\'2.18\'%3E%3C/rect%3E%3Ccircle cx=\'8.5\' cy=\'8.5\' r=\'1.5\'%3E%3C/circle%3E%3Cpolyline points=\'22 15 16 9 6 19 2 15\'%3E%3C/polyline%3E%3C/svg%3E';
}

$photo_src = getPhotoPathView($complaint['photo_path'] ?? '');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Complaint - <?php echo $complaint['queue_number']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            overflow-x: hidden;
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
        
        .logo-img {
            width: 120%;
            height: 120%;
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
            margin-left: 280px;
            padding: 20px;
            min-height: 100vh;
        }
        .detail-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .detail-label {
            font-weight: 600;
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .detail-value {
            font-size: 1.1rem;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        /* Photo styles */
        .complainant-photo {
            text-align: center;
            margin-bottom: 20px;
        }
        .complainant-photo img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #667eea;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            cursor: pointer;
            transition: transform 0.3s;
        }
        .complainant-photo img:hover {
            transform: scale(1.05);
        }
        .photo-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            border: 4px solid #667eea;
        }
        .photo-placeholder i {
            font-size: 60px;
            color: #999;
        }
        
        /* Modal for larger photo view */
        .photo-modal-img {
            max-width: 100%;
            max-height: 80vh;
            border-radius: 10px;
        }
        
        .btn-edit-investigator {
            margin-left: 10px;
            padding: 2px 8px;
            font-size: 0.75rem;
        }
        
        .investigator-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        
        /* Previous investigator styles - lower opacity */
        .previous-investigator {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 5px;
            padding-top: 5px;
            opacity: 0.7;
            border-top: 1px dashed #e9ecef;
        }
        
        .previous-investigator small {
            font-size: 0.75rem;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                min-height: auto;
            }
            .main-content {
                margin-left: 0;
            }
            .complainant-photo img {
                width: 100px;
                height: 100px;
            }
        }
    </style>
</head>
<body>
    <!-- SIDEBAR -->
    <div class="sidebar">
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
        <div class="system-badge">
            <i class="bi bi-shield-check"></i> PNP ACG · v2.0
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-eye"></i> Complaint Details</h2>
            <div>
                <a href="queue.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Queue
                </a>
                <button onclick="window.print()" class="btn btn-info">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
        </div>
        
        <!-- Update Status Message -->
        <?php if ($update_message): ?>
        <div class="alert alert-<?php echo $update_status == 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <?php echo $update_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Queue Number Banner -->
        <div class="alert alert-primary text-center">
            <h3 class="mb-0">Queue Number: <strong><?php echo htmlspecialchars($complaint['queue_number']); ?></strong></h3>
        </div>
        
        <div class="row">
            <!-- Left Column -->
            <div class="col-md-6">
                <!-- Complainant Photo -->
                <div class="detail-card">
                    <h5 class="mb-3"><i class="bi bi-camera"></i> Complainant Photo</h5>
                    <div class="complainant-photo">
                        <img src="<?php echo $photo_src; ?>" 
                             alt="Complainant Photo" 
                             data-bs-toggle="modal" 
                             data-bs-target="#photoModal"
                             data-photo-src="<?php echo $photo_src; ?>"
                             data-complainant-name="<?php echo htmlspecialchars($complaint['complainant_name']); ?>"
                             data-queue-number="<?php echo htmlspecialchars($complaint['queue_number']); ?>"
                             onclick="showPhotoModal(this)"
                             style="cursor: pointer;">
                    </div>
                </div>
                
                <!-- Complainant Information -->
                <div class="detail-card">
                    <h5 class="mb-3"><i class="bi bi-person-badge"></i> Complainant Information</h5>
                    <div class="detail-label">Full Name</div>
                    <div class="detail-value"><?php echo htmlspecialchars($complaint['complainant_name']); ?></div>
                    
                    <div class="detail-label">Contact Number</div>
                    <div class="detail-value"><?php echo htmlspecialchars($complaint['contact_number'] ?? 'Not provided'); ?></div>
                    
                    <div class="detail-label">Address</div>
                    <div class="detail-value"><?php echo htmlspecialchars($complaint['address'] ?: 'Not provided'); ?></div>
                    
                    <div class="detail-label">Category</div>
                    <div class="detail-value">
                        <span class="badge bg-<?php echo ($complaint['category'] == 'womens_desk') ? 'danger' : 'primary'; ?> p-2">
                            <i class="bi <?php echo ($complaint['category'] == 'womens_desk') ? 'bi-shield-shaded' : 'bi-briefcase'; ?>"></i>
                            <?php echo ($complaint['category'] == 'womens_desk') ? 'Women\'s Desk' : 'General Cases'; ?>
                        </span>
                    </div>
                    
                    <div class="detail-label">Filing Date</div>
                    <div class="detail-value"><?php echo date('F d, Y h:i A', strtotime($complaint['filing_date'])); ?></div>
                </div>
            </div>
            
            <!-- Right Column -->
            <div class="col-md-6">
                <!-- Assignment Information -->
                <div class="detail-card">
                    <h5 class="mb-3"><i class="bi bi-shield"></i> Assignment Information</h5>
                    
                    <div class="detail-label">Assigned Investigator</div>
                    <div class="detail-value">
                        <div class="investigator-row">
                            <strong><?php echo htmlspecialchars($complaint['assigned_investigator'] ?: 'Not assigned'); ?></strong>
                            <button type="button" class="btn btn-sm btn-outline-primary btn-edit-investigator" data-bs-toggle="modal" data-bs-target="#editInvestigatorModal">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                        </div>
                        <?php if ($previous_change && $previous_change['old_investigator']): ?>
                        <div class="previous-investigator">
                            <small><i class="bi bi-arrow-return-left"></i> Previously: <?php echo htmlspecialchars($previous_change['old_investigator']); ?></small>
                            <?php if ($previous_change['remarks']): ?>
                            <br><small class="text-muted">Reason: <?php echo htmlspecialchars($previous_change['remarks']); ?></small>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="detail-label">Complainant Status</div>
                    <div class="detail-value">
                        <span class="badge bg-<?php 
                            // Changed: 'pending' now shows as 'waiting' with warning color
                            $status_display = ($complaint['complainant_status'] == 'pending') ? 'waiting' : $complaint['complainant_status'];
                            echo ($complaint['complainant_status'] == 'pending') ? 'warning' : 
                                (($complaint['complainant_status'] == 'processing') ? 'info' : 
                                (($complaint['complainant_status'] == 'completed') ? 'success' : 'danger')); 
                        ?>">
                            <?php echo ucfirst($status_display); ?>
                        </span>
                    </div>
                    
                    <div class="detail-label">Reported By</div>
                    <div class="detail-value">
                        <?php echo $complaint['reported_by_name'] ? htmlspecialchars($complaint['reported_by_rank'] . ' ' . $complaint['reported_by_name']) : 'System'; ?>
                    </div>
                </div>
                
                <!-- Case Information -->
                <div class="detail-card">
                    <h5 class="mb-3"><i class="bi bi-folder"></i> Case Information</h5>
                    
                    <div class="detail-label">Case Type</div>
                    <div class="detail-value"><?php echo ucwords(str_replace('_', ' ', $complaint['case_type'])); ?></div>
                    
                    <div class="detail-label">Case Status</div>
                    <div class="detail-value">
                        <span class="badge bg-<?php 
                            echo ($complaint['case_status'] == 'completed') ? 'success' : 
                                (($complaint['case_status'] == 'processing') ? 'warning' : 
                                (($complaint['case_status'] == 'complaint') ? 'success' : 
                                (($complaint['case_status'] == 'visit') ? 'info' : 'secondary'))); 
                        ?> status-badge">
                            <i class="bi <?php 
                                echo ($complaint['case_status'] == 'completed') ? 'bi-check-circle' : 
                                    (($complaint['case_status'] == 'processing') ? 'bi-arrow-repeat' : 
                                    (($complaint['case_status'] == 'complaint') ? 'bi-file-text' : 'bi-info-circle')); 
                            ?>"></i>
                            <?php echo ucwords(str_replace('-', ' ', $complaint['case_status'])); ?>
                        </span>
                    </div>
                    
                    <div class="detail-label">Date Reported</div>
                    <div class="detail-value"><?php echo date('F d, Y', strtotime($complaint['date_reported'])); ?></div>
                    
                    <div class="detail-label">Incident Date</div>
                    <div class="detail-value"><?php echo $complaint['incident_date'] ? date('F d, Y', strtotime($complaint['incident_date'])) : 'Not specified'; ?></div>
                    
                    <?php if ($complaint['description']): ?>
                    <div class="detail-label">Description</div>
                    <div class="detail-value"><?php echo nl2br(htmlspecialchars($complaint['description'])); ?></div>
                    <?php endif; ?>
                    
                    <?php if ($complaint['suspect_info']): ?>
                    <div class="detail-label">Suspect Information</div>
                    <div class="detail-value"><?php echo nl2br(htmlspecialchars($complaint['suspect_info'])); ?></div>
                    <?php endif; ?>
                </div>
                
                <!-- Evidence -->
                <?php if ($complaint['evidence_path']): ?>
                <div class="detail-card">
                    <h5 class="mb-3"><i class="bi bi-paperclip"></i> Evidence</h5>
                    <a href="<?php echo $complaint['evidence_path']; ?>" target="_blank" class="btn btn-outline-primary">
                        <i class="bi bi-file-earmark"></i> View Evidence File
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Edit Investigator Modal -->
    <div class="modal fade" id="editInvestigatorModal" tabindex="-1" aria-labelledby="editInvestigatorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <h5 class="modal-title" id="editInvestigatorModalLabel">
                        <i class="bi bi-person-badge"></i> Change Assigned Investigator
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="update_investigator" value="1">
                        <input type="hidden" name="old_investigator" value="<?php echo htmlspecialchars($complaint['assigned_investigator']); ?>">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Current Investigator</label>
                            <div class="alert alert-info mb-0">
                                <i class="bi bi-person-badge"></i> <?php echo htmlspecialchars($complaint['assigned_investigator'] ?: 'Not assigned'); ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">New Investigator <span class="text-danger">*</span></label>
                            <select name="new_investigator" class="form-select" required>
                                <option value="">-- Select Investigator --</option>
                                <optgroup label="Male Investigators">
                                    <?php foreach ($male_investigators as $investigator): ?>
                                    <option value="<?php echo htmlspecialchars($investigator); ?>" <?php echo ($complaint['assigned_investigator'] == $investigator) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($investigator); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="Female Investigators">
                                    <?php foreach ($female_investigators as $investigator): ?>
                                    <option value="<?php echo htmlspecialchars($investigator); ?>" <?php echo ($complaint['assigned_investigator'] == $investigator) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($investigator); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Reason for Change <span class="text-danger">*</span></label>
                            <textarea name="change_remarks" class="form-control" rows="3" placeholder="e.g., Investigator on leave, Case reassigned, etc." required></textarea>
                            <small class="text-muted">Please provide a reason for changing the assigned investigator.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Photo Modal for larger view -->
    <div class="modal fade" id="photoModal" tabindex="-1" aria-labelledby="photoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <h5 class="modal-title" id="photoModalLabel">
                        <i class="bi bi-camera"></i> Complainant Photo
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalPhoto" src="" alt="Complainant Photo" class="photo-modal-img">
                    <div class="mt-3">
                        <p><strong>Complainant:</strong> <span id="modalComplainantName"></span></p>
                        <p><strong>Queue Number:</strong> <span id="modalQueueNumber"></span></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Create investigator_changes table if not exists -->
    <?php
    // Create investigator_changes table if it doesn't exist
    $create_table = "
    CREATE TABLE IF NOT EXISTS investigator_changes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        complaint_id INT NOT NULL,
        old_investigator VARCHAR(255),
        new_investigator VARCHAR(255),
        remarks TEXT,
        changed_by VARCHAR(100),
        changed_at DATETIME,
        INDEX idx_complaint_id (complaint_id)
    )";
    $conn->query($create_table);
    ?>
    
    <script>
        // Show photo modal
        function showPhotoModal(element) {
            const photoSrc = element.getAttribute('data-photo-src');
            const complainantName = element.getAttribute('data-complainant-name');
            const queueNumber = element.getAttribute('data-queue-number');
            
            document.getElementById('modalPhoto').src = photoSrc;
            document.getElementById('modalComplainantName').textContent = complainantName;
            document.getElementById('modalQueueNumber').textContent = queueNumber;
        }
    </script>
</body>
</html>
<?php include 'followup_inject.php'; ?>