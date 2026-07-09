<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

// Then include your existing code...
require_once 'config.php';


// Start session to store form data if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Create the log table first if it doesn't exist
function createLogTable($conn) {
    $log_table = "CREATE TABLE IF NOT EXISTS investigator_action_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        investigator_name VARCHAR(255) NOT NULL,
        badge_id VARCHAR(100),
        action_type VARCHAR(50),
        details TEXT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    return $conn->query($log_table);
}

// Call this immediately to ensure table exists
createLogTable($conn);

// Ensure complainants table has a remarks column to store visitor details/accompanying persons
function ensureComplainantsRemarksColumn($conn) {
    $res = $conn->query("SHOW COLUMNS FROM complainants LIKE 'remarks'");
    if (!$res || $res->num_rows == 0) {
        $conn->query("ALTER TABLE complainants ADD COLUMN remarks TEXT NULL AFTER address");
    }
}

function createComplaintHistoryTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS complaint_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        complainant_id INT,
        complaint_id INT,
        action VARCHAR(100),
        details TEXT,
        performed_by VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    return $conn->query($sql);
}

function createFollowUpsTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS follow_ups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        complainant_id INT NOT NULL,
        complaint_id INT NULL,
        queue_number VARCHAR(50),
        assigned_investigator VARCHAR(255),
        status VARCHAR(50) DEFAULT 'open',
        remarks TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    return $conn->query($sql);
}

// Transfer resolved cases to the follow_ups table for monitoring/tracking
function transferResolvedToFollowUp($conn) {
    // Find complaints marked as resolved but not yet present in follow_ups
    $q = "SELECT c.id as complaint_id, c.complainant_id, c.queue_number, c.case_status, c.case_type, comp.assigned_investigator
          FROM complaints c
          LEFT JOIN complainants comp ON comp.id = c.complainant_id
          LEFT JOIN follow_ups f ON f.complaint_id = c.id
          WHERE c.case_status = 'resolved' AND (f.id IS NULL)";
    $res = $conn->query($q);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $complaint_id = (int)$row['complaint_id'];
            $complainant_id = (int)$row['complainant_id'];
            $queue = $conn->real_escape_string($row['queue_number']);
            $assigned = $conn->real_escape_string($row['assigned_investigator'] ?? '');
            $remarks = "Automatically moved to Follow-Up after resolution";
            $stmt = $conn->prepare("INSERT INTO follow_ups (complainant_id, complaint_id, queue_number, assigned_investigator, status, remarks) VALUES (?, ?, ?, ?, 'open', ?)");
            if ($stmt) {
                $stmt->bind_param('iisss', $complainant_id, $complaint_id, $queue, $assigned, $remarks);
                $stmt->execute();
                $stmt->close();
            }
            // also add history
            $hist = $conn->prepare("INSERT INTO complaint_history (complainant_id, complaint_id, action, details) VALUES (?, ?, 'moved_to_followup', ?)");
            if ($hist) {
                $detail = "Complaint {$queue} moved to follow-up after resolution";
                $hist->bind_param('iis', $complainant_id, $complaint_id, $detail);
                $hist->execute();
                $hist->close();
            }
        }
    }
}

// Ensure helper tables/columns exist on page load
ensureComplainantsRemarksColumn($conn);
createComplaintHistoryTable($conn);
createFollowUpsTable($conn);
transferResolvedToFollowUp($conn);

// Auto-set investigator session if not already set
if (!isset($_SESSION['investigator_details_submitted']) || $_SESSION['investigator_details_submitted'] !== true) {
    $_SESSION['investigator_details_submitted'] = true;
    $_SESSION['investigator_details_submitted_at'] = date('Y-m-d H:i:s');
}

// Clear saved form data after successful submission or when clear is requested
if (isset($_GET['clear_form']) && $_GET['clear_form'] == 1) {
    unset($_SESSION['form_data']);
    // Also clear any success messages to prevent showing old data
    unset($_SESSION['complaint_success']);
    unset($_SESSION['visit_success']);
}

// Function to save form data to session
function saveFormDataToSession($data) {
    $_SESSION['form_data'] = $data;
}

// Function to get saved form data
function getSavedFormData($key, $default = '') {
    return isset($_SESSION['form_data'][$key]) ? htmlspecialchars($_SESSION['form_data'][$key]) : $default;
}

// Clear form data if registration was successful
if (isset($_SESSION['complaint_success']) || isset($_SESSION['visit_success'])) {
    unset($_SESSION['form_data']);
}

// ============================================
// FIXED getNextQueueNumber function for Complaints (C-001, I-002, F-003, etc.)
// ============================================
function getNextQueueNumber($conn, $case_status = null) {
    // Determine prefix based on case status
    $prefix = '';
    if ($case_status) {
        switch(strtolower($case_status)) {
            case 'complaint': $prefix = 'C'; break;
            case 'inquiry': $prefix = 'I'; break;
            case 'follow-up': $prefix = 'F'; break;
            default: $prefix = '';
        }
    }
    
    // Check if complainants table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'complainants'");
    if (!$table_check || $table_check->num_rows == 0) {
        error_log("complainants table does not exist");
        $next_number = 1;
        $formatted_number = str_pad($next_number, 3, '0', STR_PAD_LEFT);
        return !empty($prefix) ? "{$prefix}-{$formatted_number}" : $formatted_number;
    }
    
    // Only look for properly formatted 3-digit queue numbers (C-001, I-002, F-003, etc.)
    $query = "SELECT MAX(CAST(SUBSTRING_INDEX(queue_number, '-', -1) AS UNSIGNED)) as max_queue 
              FROM complainants 
              WHERE queue_number REGEXP '^[CIF]-[0-9]{3}$'";
    $result = $conn->query($query);
    
    if (!$result) {
        error_log("Error getting max queue number: " . $conn->error);
        $next_number = 1;
    } else {
        $row = $result->fetch_assoc();
        $max_queue = $row['max_queue'];
        if ($max_queue && $max_queue > 0) {
            $next_number = $max_queue + 1;
        } else {
            $next_number = 1;
        }
    }
    
    // Ensure we don't exceed 999 (keep 3-digit format)
    if ($next_number > 999) {
        $next_number = 1;
    }
    
    $formatted_number = str_pad($next_number, 3, '0', STR_PAD_LEFT);
    
    if (!empty($prefix)) {
        return "{$prefix}-{$formatted_number}";
    } else {
        return $formatted_number;
    }
}

// ============================================
// NEW FUNCTION: Get next Visit queue number (VISIT-001, VISIT-002, etc.)
// ============================================
function getNextVisitQueueNumber($conn) {
    // Check if complainants table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'complainants'");
    if (!$table_check || $table_check->num_rows == 0) {
        error_log("complainants table does not exist");
        return 'VISIT-001';
    }
    
    // Get the MAX numeric value from VISIT queue numbers
    // Pattern: VISIT-001, VISIT-002, etc.
    $query = "SELECT MAX(CAST(SUBSTRING_INDEX(queue_number, '-', -1) AS UNSIGNED)) as max_queue 
              FROM complainants 
              WHERE queue_number REGEXP '^VISIT-[0-9]{3,}$'";
    $result = $conn->query($query);
    
    if (!$result) {
        error_log("Error getting max visit queue number: " . $conn->error);
        $next_number = 1;
    } else {
        $row = $result->fetch_assoc();
        $max_queue = $row['max_queue'];
        if ($max_queue && $max_queue > 0) {
            $next_number = $max_queue + 1;
        } else {
            $next_number = 1;
        }
    }
    
    $formatted_number = str_pad($next_number, 3, '0', STR_PAD_LEFT);
    return "VISIT-{$formatted_number}";
}

// Define all case types
$case_types_list = [
    "RA 7394 (Consumer Act of the Philippines)",
    "RA 7581 (Price Act)",
    "RA 7610 (Special Protection of Children Against Abuse, Exploitation and Discrimination Act)",
    "RA 8293 (Intellectual Property Code of the Philippines)",
    "RA 8484 (Access Devices Regulation Act of 1998)",
    "RA 9262 (Anti-Violence Against Women and Their Children Act of 2004)",
    "RA 9711 (Foods & Drugs Administration Act)",
    "RA 9775 (Child Pornography)",
    "RA 9995 (Anti-Photo and Video Voyeurism Act of 2009)",
    "RA 10022 (Migrant Workers and Overseas Filipinos Act Of 1995)",
    "RA 10173 (Data Privacy Act)",
    "RA 11313 (Safe Spaces Act)",
    "RA 10364 (Expanded Anti-Trafficking in Persons Act of 2012)",
    "RA 11930 (Anti-Online Sexual Abuse or Exploitation of Children [OSAEC] and Anti-Child Sexual Abuse or Exploitation Materials [CSAEM] Act)",
    "RA 11332 (Mandatory Reporting of Notifiable Disease & Health Events of Public Health Concern Act)",
    "RA 11469 (Bayanihan to Heal as One Act)",
    "PD 1602 as amended by RA 9287 pursuant to RA 10175 (Online Illegal Gambling)",
    "Art. 154 of the RPC (Fake/False Information)",
    "Art. 282 (Grave Threat) of the RPC",
    "Art. 286 (Grave Coercion) of the RPC",
    "Art. 287 (Light coercions) of the RPC",
    "Art. 294 (Robbery with Intimidation of Persons) of the RPC",
    "Art. 315 (Swindling/Estafa) of the RPC",
    "RA 11449 in rel. to sec. 6 of RA 10175",
    "RA 4200 (Anti-Wire Tapping Law)",
    "RA 7183 (An Act Regulating the Sale, Manufacture, Distribution and Use of Firecrackers and Other Pyrotechnic Devices)",
    "PD 1612 (Anti-Fencing Law of 1979)",
    "Art. 364 (Intriguing against honor)",
    "Art. 316 (Other forms of swindling)",
    "Art. 310 (Qualified theft)",
    "Art. 285 (Other light threats)",
    "RA 9147 (Wildlife Resources Conservation and Protection Act)",
    "RA 7277 (Magna Carta for Disabled Persons) in relation to R.A. 10175",
    "Art. 308 (Theft)",
    "Art. 172 (Falsification by private individual and use of falsified documents)",
    "RA 11934 (Subscriber Identity Module (SIM) Registration Act)",
    "RA 6539 (Anti-Carnapping Act of 1972)",
    "RA 11862 (An Act Strengthening the Policies on Anti-Trafficking in Persons…)",
    "RA 12010 (Anti-Financial Account Scamming Act (AFASA))",
    "PD 1727 (Anti-Bomb Joke Law)",
    "Art. 259 (Abortion practiced by a physician or midwife and dispensing of abortive)",
    "RA 4224 (The Medical Act of 1959)",
    "Art. 177 (Usurpation of authority or official functions)",
    "Art. 318 (Other deceits) of the RPC",
    "RA 11032 (Ease of Doing Business and Efficient Government Service Delivery Act of 2018)",
    "Act No. 3846 (Regulation of Radio Stations and Radio Communications)",
    "RA 9175 (Chain Saw Act of 2002)",
    "RA 9211 (Tobacco Regulations Act of 2003)",
    "COMELEC Resolution No. 11067 (COMELEC Gun Ban)",
    "RA 10863 (Customs Modernization and Tariff Act (CMTA))",
    "RA 10591 (Comprehensive Firearms and Ammunition Regulation Act)",
    "PD 705 (Revised Forestry Code of the Philippines)",
    "BP 881 (Omnibus Election Code of the Philippines)",
    "RA 11900 (Vaporized Nicotine and Non-Nicotine Products Regulation Act)",
    "RA 9484 (The Philippine Dental Act of 2007)",
    "Art. 178 (Using Fictitious Name)",
    "Art. 189 (Unfair competition, fraudulent registration of trade-name, trademark…)",
    "Art. 333 (Adultery)",
    "Ordinance No. SP-2744 (Anti-Scalping Ordinance of Quezon City)",
    "PD 1829 (Penalizing Obstruction of Apprehension and Prosecution of Criminal Offenders)",
    "RA 11479 (The Anti-Terrorism Act of 2020)",
    "RA 8799 (The Securities Regulation Code)",
    "RA 10643 (The Graphic Health Warnings Law)",
    "RA 8424 (Tax Reform Act of 1997)",
    "RA 11467 (Further Increases in Sin Taxes, 2020)"
];

sort($case_types_list);

// Attendance and Rotation Functions
function createAttendanceTables($conn) {
    $attendance_table = "CREATE TABLE IF NOT EXISTS investigator_attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        investigator_name VARCHAR(255) NOT NULL,
        attendance_date DATE NOT NULL,
        status ENUM('present', 'absent', 'day_off') DEFAULT 'present',
        notes TEXT,
        marked_by VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_attendance (investigator_name, attendance_date)
    )";
    return $conn->query($attendance_table);
}

function getTodayAttendance($conn, $category = null) {
    $today = date('Y-m-d');
    $attendance = array();
    
    if ($category) {
        if ($category == 'womens_desk') {
            $query = "SELECT DISTINCT i.name, COALESCE(a.status, 'present') as status 
                      FROM investigators i
                      LEFT JOIN investigator_attendance a ON i.name = a.investigator_name AND a.attendance_date = ?
                      WHERE (i.category = ? OR i.category = 'all') 
                      AND i.gender = 'female' AND i.status = 'active'";
            $stmt = $conn->prepare($query);
            if ($stmt) {
                $stmt->bind_param("ss", $today, $category);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $attendance[$row['name']] = $row['status'];
                }
                $stmt->close();
            }
        } else if ($category == 'general_cases') {
            $query = "SELECT DISTINCT i.name, COALESCE(a.status, 'present') as status 
                      FROM investigators i
                      LEFT JOIN investigator_attendance a ON i.name = a.investigator_name AND a.attendance_date = ?
                      WHERE (i.category = ? OR i.category = 'all') 
                      AND i.gender = 'male' AND i.status = 'active'";
            $stmt = $conn->prepare($query);
            if ($stmt) {
                $stmt->bind_param("ss", $today, $category);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $attendance[$row['name']] = $row['status'];
                }
                $stmt->close();
            }
        } else {
            // For visit - get all investigators
            $query = "SELECT DISTINCT i.name, COALESCE(a.status, 'present') as status 
                      FROM investigators i
                      LEFT JOIN investigator_attendance a ON i.name = a.investigator_name AND a.attendance_date = ?
                      WHERE i.status = 'active'";
            $stmt = $conn->prepare($query);
            if ($stmt) {
                $stmt->bind_param("s", $today);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $attendance[$row['name']] = $row['status'];
                }
                $stmt->close();
            }
        }
    } else {
        $query = "SELECT DISTINCT i.name, COALESCE(a.status, 'present') as status 
                  FROM investigators i
                  LEFT JOIN investigator_attendance a ON i.name = a.investigator_name AND a.attendance_date = ?
                  WHERE i.status = 'active'";
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("s", $today);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $attendance[$row['name']] = $row['status'];
            }
            $stmt->close();
        }
    }
    
    return $attendance;
}

function updateAttendance($conn, $investigator_name, $status, $notes = '', $marked_by = '') {
    $today = date('Y-m-d');
    $query = "INSERT INTO investigator_attendance (investigator_name, attendance_date, status, notes, marked_by) 
              VALUES (?, ?, ?, ?, ?) 
              ON DUPLICATE KEY UPDATE status = VALUES(status), notes = VALUES(notes), marked_by = VALUES(marked_by), updated_at = CURRENT_TIMESTAMP";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("sssss", $investigator_name, $today, $status, $notes, $marked_by);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    return false;
}

function getPresentInvestigators($conn, $category) {
    $today = date('Y-m-d');
    $investigators = array();
    
    if ($category == 'womens_desk') {
        $query = "SELECT DISTINCT i.name 
                  FROM investigators i
                  LEFT JOIN investigator_attendance a ON i.name = a.investigator_name AND a.attendance_date = ?
                  WHERE (i.category = ? OR i.category = 'all') 
                  AND i.gender = 'female' AND i.status = 'active'
                  AND (a.status IS NULL OR a.status = 'present')
                  ORDER BY i.name ASC";
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("ss", $today, $category);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $investigators[] = $row['name'];
            }
            $stmt->close();
        }
    } else if ($category == 'general_cases') {
        $query = "SELECT DISTINCT i.name 
                  FROM investigators i
                  LEFT JOIN investigator_attendance a ON i.name = a.investigator_name AND a.attendance_date = ?
                  WHERE (i.category = ? OR i.category = 'all') 
                  AND i.gender = 'male' AND i.status = 'active'
                  AND (a.status IS NULL OR a.status = 'present')
                  ORDER BY i.name ASC";
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("ss", $today, $category);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $investigators[] = $row['name'];
            }
            $stmt->close();
        }
    } else {
        // For visit category - get all investigators regardless of gender
        $query = "SELECT DISTINCT i.name 
                  FROM investigators i
                  LEFT JOIN investigator_attendance a ON i.name = a.investigator_name AND a.attendance_date = ?
                  WHERE i.status = 'active'
                  AND (a.status IS NULL OR a.status = 'present')
                  ORDER BY i.name ASC";
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("s", $today);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $investigators[] = $row['name'];
            }
            $stmt->close();
        }
    }
    return normalizeInvestigatorNames($investigators);
}

function normalizeInvestigatorNames(array $names) {
    $trimmedNames = array_map('trim', $names);
    $uniqueNames = dedupeInvestigatorNames($trimmedNames);
    sort($uniqueNames, SORT_STRING | SORT_FLAG_CASE);
    return array_values($uniqueNames);
}

function getCurrentRotationIndex($conn, $category) {
    // Ensure rotation record exists
    $check_query = "SELECT rotation_index FROM investigator_rotation WHERE category = ?";
    $check_stmt = $conn->prepare($check_query);
    if ($check_stmt) {
        $check_stmt->bind_param("s", $category);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($row = $check_result->fetch_assoc()) {
            $check_stmt->close();
            return (int)$row['rotation_index'];
        }
        $check_stmt->close();
    }
    
    // Initialize rotation for this category
    $insert_query = "INSERT INTO investigator_rotation (category, rotation_index, last_updated) VALUES (?, 0, NOW())";
    $insert_stmt = $conn->prepare($insert_query);
    if ($insert_stmt) {
        $insert_stmt->bind_param("s", $category);
        $insert_stmt->execute();
        $insert_stmt->close();
    }
    return 0;
}

function updateRotationIndex($conn, $category, $total_investigators) {
    if ($total_investigators <= 0) return false;
    
    $current_index = getCurrentRotationIndex($conn, $category);
    $new_index = ($current_index + 1) % $total_investigators;
    
    $query = "UPDATE investigator_rotation SET rotation_index = ?, last_updated = NOW() WHERE category = ?";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("is", $new_index, $category);
        $result = $stmt->execute();
        $stmt->close();
        
        // Log rotation for debugging
        error_log("Rotation updated - Category: $category, Old Index: $current_index, New Index: $new_index, Total: $total_investigators");
        
        return $result;
    }
    return false;
}

function getAllInvestigatorsForCategory($conn, $category) {
    $investigators = array();
    $seen_names = array();
    
    if ($category == 'womens_desk') {
        $query = "SELECT DISTINCT name FROM investigators WHERE (category = ? OR category = 'all') AND gender = 'female' AND status = 'active' ORDER BY name ASC";
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("s", $category);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $normalized = normalizeInvestigatorNameForComparison($row['name']);
                if (!isset($seen_names[$normalized])) {
                    $seen_names[$normalized] = true;
                    $investigators[] = $row['name'];
                }
            }
            $stmt->close();
        }
    } else if ($category == 'general_cases') {
        $query = "SELECT DISTINCT name FROM investigators WHERE (category = ? OR category = 'all') AND gender = 'male' AND status = 'active' ORDER BY name ASC";
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("s", $category);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $normalized = normalizeInvestigatorNameForComparison($row['name']);
                if (!isset($seen_names[$normalized])) {
                    $seen_names[$normalized] = true;
                    $investigators[] = $row['name'];
                }
            }
            $stmt->close();
        }
    } else {
        // For visit - get all active investigators
        $query = "SELECT DISTINCT name FROM investigators WHERE status = 'active' ORDER BY name ASC";
        $result = $conn->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $normalized = normalizeInvestigatorNameForComparison($row['name']);
                if (!isset($seen_names[$normalized])) {
                    $seen_names[$normalized] = true;
                    $investigators[] = $row['name'];
                }
            }
        }
    }
    
    return normalizeInvestigatorNames($investigators);
}

function getCurrentActiveInvestigator($conn, $category) {
    $present_investigators = getPresentInvestigators($conn, $category);
    if (empty($present_investigators)) return null;
    
    $rotation_index = getCurrentRotationIndex($conn, $category);
    $active_index = $rotation_index % count($present_investigators);
    $active_investigator = $present_investigators[$active_index];
    
    return array(
        'name' => $active_investigator,
        'index' => $rotation_index,
        'position' => $active_index + 1,
        'total' => count($present_investigators)
    );
}

function isActiveInvestigator($conn, $category, $investigator_name) {
    $active = getCurrentActiveInvestigator($conn, $category);
    return ($active && $active['name'] === $investigator_name);
}

// Get category from URL parameter
$selected_category = isset($_GET['category']) ? $_GET['category'] : '';
$valid_categories = array('general_cases', 'womens_desk', 'visit');
if (!in_array($selected_category, $valid_categories)) {
    redirect('category_selection.php');
}


$category_title = 'General Case';
$category_color = '#4299e1';
$category_icon = 'bi-briefcase';
$message = '';
$generated_queue = null;
$generated_complainant_id = null;

$male_investigators_list = array(
    'PMSg Madregalijos, Eddie S',
    'PMSg Pacardo, Karlo C',
    'PMSg Bustamante, Paul Christian E',
    'PSSg Magdaraog, Joseph M',
    'PSSg Lumanog, Ryan D',
    'PSSg Mariano, Marc V',
    'Pcpl Abelinde, James Robert T',
    'Pat Balaguer, Efren S',
    'Pat Evangelista, Jay Andrew G',
    'PSMS Floro Bhong, Oida S'
);

$female_investigators_list = array(
    'PSMS Rivera, Leah H',
    'Pcpl Virata, Mergielyn C',
    'PSSg Balin, Levelyn S'
);

$photos_dir = 'complainant_photos/';
if (!is_dir($photos_dir)) mkdir($photos_dir, 0777, true);

function getAllInvestigators($conn) {
    $investigators = array();
    $seen_names = array();
    $query = "SELECT DISTINCT name, category, gender, status FROM investigators WHERE status = 'active' ORDER BY category, name ASC";
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $normalized = normalizeInvestigatorNameForComparison($row['name']);
            if (!isset($seen_names[$normalized])) {
                $seen_names[$normalized] = true;
                $investigators[] = $row;
            }
        }
    }
    return $investigators;
}

function investigatorExists($conn, $name) {
    $check_query = "SELECT id FROM investigators WHERE name = ?";
    $check_stmt = $conn->prepare($check_query);
    if ($check_stmt) {
        $check_stmt->bind_param("s", $name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $exists = $check_result->num_rows > 0;
        $check_stmt->close();
        return $exists;
    }
    return false;
}

function saveInvestigatorToDB($conn, $name, $category, $gender) {
    if (empty($gender)) $gender = ($category == 'womens_desk') ? 'female' : 'male';
    if (investigatorExists($conn, $name)) {
        $update_query = "UPDATE investigators SET category = ?, gender = ?, status = 'active', updated_at = NOW() WHERE name = ?";
        $update_stmt = $conn->prepare($update_query);
        if ($update_stmt) {
            $update_stmt->bind_param("sss", $category, $gender, $name);
            $result = $update_stmt->execute();
            $update_stmt->close();
            return $result;
        }
    } else {
        $insert_query = "INSERT INTO investigators (name, category, gender, status, created_at) VALUES (?, ?, ?, 'active', NOW())";
        $insert_stmt = $conn->prepare($insert_query);
        if ($insert_stmt) {
            $insert_stmt->bind_param("sss", $name, $category, $gender);
            $result = $insert_stmt->execute();
            $insert_stmt->close();
            return $result;
        }
    }
    return false;
}

function normalizeInvestigatorNameForComparison($name) {
    $normalized = trim($name);
    $normalized = str_replace('-', ' ', $normalized);
    $normalized = preg_replace('/\s+/', ' ', $normalized);
    return mb_strtolower($normalized);
}

function dedupeInvestigatorNames(array $names) {
    $seen = array();
    $unique = array();
    foreach ($names as $name) {
        $normalized = normalizeInvestigatorNameForComparison($name);
        if (!isset($seen[$normalized])) {
            $seen[$normalized] = true;
            $unique[] = $name;
        }
    }
    return $unique;
}

function investigatorExistsNormalized($conn, $name) {
    $normalized = normalizeInvestigatorNameForComparison($name);
    $query = "SELECT name FROM investigators";
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (normalizeInvestigatorNameForComparison($row['name']) === $normalized) {
                return true;
            }
        }
    }
    return false;
}

function cleanDuplicateInvestigators($conn) {
    $query = "SELECT id, name FROM investigators ORDER BY name ASC, id ASC";
    $result = $conn->query($query);
    $unique_names = array();
    $duplicates_to_remove = array();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $normalized_name = normalizeInvestigatorNameForComparison($row['name']);
            if (isset($unique_names[$normalized_name])) {
                $duplicates_to_remove[] = $row['id'];
            } else {
                $unique_names[$normalized_name] = true;
            }
        }
    }
    $removed_count = 0;
    foreach ($duplicates_to_remove as $dup_id) {
        $delete_query = "DELETE FROM investigators WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        if ($delete_stmt) {
            $delete_stmt->bind_param("i", $dup_id);
            if ($delete_stmt->execute()) {
                $removed_count++;
            }
            $delete_stmt->close();
        }
    }
    $attendance_query = "DELETE a FROM investigator_attendance a INNER JOIN investigators i ON a.investigator_name = i.name WHERE i.status = 'inactive'";
    $conn->query($attendance_query);
    return $removed_count;
}

function removeInvestigatorFromDB($conn, $name) {
    $query = "UPDATE investigators SET status = 'inactive', updated_at = NOW() WHERE name = ?";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("s", $name);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    return false;
}

function initializeMaleInvestigators($conn) {
    global $male_investigators_list;
    cleanDuplicateInvestigators($conn);
    foreach ($male_investigators_list as $inv) {
        if (!investigatorExistsNormalized($conn, $inv)) {
            saveInvestigatorToDB($conn, $inv, 'all', 'male');
        }
    }
}

function initializeFemaleInvestigators($conn) {
    global $female_investigators_list;
    cleanDuplicateInvestigators($conn);
    foreach ($female_investigators_list as $inv) {
        if (!investigatorExistsNormalized($conn, $inv)) {
            saveInvestigatorToDB($conn, $inv, 'womens_desk', 'female');
        }
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['insert_investigator'])) {
        $investigator_name = trim($_POST['investigator_name']);
        $investigator_category = $_POST['investigator_category'];
        $investigator_gender = isset($_POST['investigator_gender']) ? $_POST['investigator_gender'] : '';
        $errors = array();
        if (empty($investigator_name)) $errors[] = "Investigator name is required";
        if (empty($investigator_gender)) $investigator_gender = ($investigator_category == 'womens_desk') ? 'female' : 'male';
        if ($investigator_category == 'womens_desk' && $investigator_gender != 'female') $errors[] = "Women's Desk investigators must be female";
        if (empty($errors)) {
            if (saveInvestigatorToDB($conn, $investigator_name, $investigator_category, $investigator_gender)) {
                $message = '<div class="alert alert-success">Success! Investigator added.</div>';
            } else {
                $message = '<div class="alert alert-danger">Error! Failed to add investigator.</div>';
            }
        } else {
            $message = '<div class="alert alert-danger">Validation Errors: ' . implode(', ', $errors) . '</div>';
        }
    } elseif (isset($_POST['remove_investigator'])) {
        $investigator_name = $_POST['investigator_name'];
        if (!empty($investigator_name)) {
            if (removeInvestigatorFromDB($conn, $investigator_name)) {
                $message = '<div class="alert alert-success">Investigator removed successfully!</div>';
            } else {
                $message = '<div class="alert alert-danger">Failed to remove investigator.</div>';
            }
        }
    } elseif (isset($_POST['update_attendance'])) {
        $attendance_data = isset($_POST['attendance']) ? $_POST['attendance'] : array();
        $notes_data = isset($_POST['notes']) ? $_POST['notes'] : array();
        $current_user = isset($_SESSION['username']) ? $_SESSION['username'] : 'system';
        foreach ($attendance_data as $investigator => $status) {
            $notes = isset($notes_data[$investigator]) ? $notes_data[$investigator] : '';
            updateAttendance($conn, $investigator, $status, $notes, $current_user);
        }
        $message = '<div class="alert alert-success">Attendance updated successfully!</div>';
        
        // Reset rotation index when attendance changes to maintain proper order
        if ($selected_category !== 'visit') {
            $present_count = count(getPresentInvestigators($conn, $selected_category));
            if ($present_count > 0) {
                // Reset to 0 to start fresh
                $reset_query = "UPDATE investigator_rotation SET rotation_index = 0, last_updated = NOW() WHERE category = ?";
                $reset_stmt = $conn->prepare($reset_query);
                if ($reset_stmt) {
                    $reset_stmt->bind_param("s", $selected_category);
                    $reset_stmt->execute();
                    $reset_stmt->close();
                }
            }
        }
    } elseif (isset($_POST['mark_interview_complete'])) {
        // Endpoint to mark interview as complete -> set status to 'catered' via central endpoint and create follow-up entry
        $complainant_id = isset($_POST['complainant_id']) ? intval($_POST['complainant_id']) : 0;
        $complaint_id = isset($_POST['complaint_id']) ? intval($_POST['complaint_id']) : null;
        $performed_by = isset($_SESSION['investigator_full_name']) ? $_SESSION['investigator_full_name'] : 'system';
        if ($complainant_id > 0) {
            // Fetch queue and assigned investigator for follow-up record
            $q = $conn->prepare("SELECT queue_number, assigned_investigator FROM complainants WHERE id = ?");
            $queue = '';
            $assigned = '';
            if ($q) {
                $q->bind_param('i', $complainant_id);
                $q->execute();
                $r = $q->get_result();
                if ($row = $r->fetch_assoc()) {
                    $queue = $row['queue_number'];
                    $assigned = $row['assigned_investigator'];
                }
                $q->close();
            }

            $update_success = false;

            // If this is associated with a complaint record, use the central update endpoint to mark as catered
            if ($complaint_id && $complaint_id > 0) {
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $url = $protocol . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['REQUEST_URI']), '\\/') . '/update_status.php';

                $postFields = http_build_query([
                    'complaint_id' => $complaint_id,
                    'complainant_id' => $complainant_id,
                    'catered_client' => '1',
                    'queue_number' => $queue,
                    'remarks' => "Marked as catered after interview by {$performed_by}"
                ]);

                if (function_exists('curl_init')) {
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/x-www-form-urlencoded"]);
                    // Pass current PHP session so update_status.php recognizes the logged-in user
                    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
                    curl_setopt($ch, CURLOPT_COOKIE, 'PHPSESSID=' . session_id());
                    $resp = curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    if ($resp !== false) {
                        $decoded = @json_decode($resp, true);
                        if (is_array($decoded) && isset($decoded['success']) && $decoded['success']) {
                            $update_success = true;
                        }
                    }
                } else {
                    // Fallback to file_get_contents with stream context
                    $opts = [
                        'http' => [
                            'method' => 'POST',
                            'header' => "Content-Type: application/x-www-form-urlencoded\r\nCookie: PHPSESSID=" . session_id() . "\r\n",
                            'content' => $postFields,
                            'timeout' => 10
                        ]
                    ];
                    $context = stream_context_create($opts);
                    $resp = @file_get_contents($url, false, $context);
                    if ($resp !== false) {
                        $decoded = @json_decode($resp, true);
                        if (is_array($decoded) && isset($decoded['success']) && $decoded['success']) {
                            $update_success = true;
                        }
                    }
                }
            } else {
                // No complaint record - perform direct complainant update as fallback
                $u = $conn->prepare("UPDATE complainants SET status = 'catered', updated_at = NOW() WHERE id = ?");
                if ($u) {
                    $u->bind_param('i', $complainant_id);
                    $update_success = $u->execute();
                    $u->close();
                }
            }

            // Insert into follow_ups for monitoring
            $ins = $conn->prepare("INSERT INTO follow_ups (complainant_id, complaint_id, queue_number, assigned_investigator, status, remarks) VALUES (?, ?, ?, ?, 'monitoring', ?)");
            if ($ins) {
                $remarks = "Marked catered after interview by {$performed_by}";
                $ins->bind_param('iisss', $complainant_id, $complaint_id, $queue, $assigned, $remarks);
                $ins->execute();
                $ins->close();
            }

            // Insert into complaint_history for audit
            $hist = $conn->prepare("INSERT INTO complaint_history (complainant_id, complaint_id, action, details, performed_by) VALUES (?, ?, 'interview_completed', ?, ?)");
            if ($hist) {
                $detail = "Interview completed for {$queue} - marked as catered";
                $hist->bind_param('iiss', $complainant_id, $complaint_id, $detail, $performed_by);
                $hist->execute();
                $hist->close();
            }

            if ($update_success) {
                $message = '<div class="alert alert-success">Interview marked complete and moved to Follow-Up for monitoring.</div>';
            } else {
                $message = '<div class="alert alert-warning">Interview processed, but automatic status update failed. Follow-Up created.</div>';
            }
        }
    }
}

// For Visit category - simplified form processing (NO rotation, any investigator can be selected)
if ($selected_category === 'visit') {
    $category_title = 'Visit Form';
    $category_color = '#38b2ac';
    $category_icon = 'bi-calendar-check';
    initializeMaleInvestigators($conn);
    initializeFemaleInvestigators($conn);
    
    // Handle Visit submission (no ticket generation, no rotation)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['insert_investigator']) && !isset($_POST['remove_investigator']) && !isset($_POST['update_attendance'])) {
        $errors = array();
        
        if (empty($_POST['captured_image_data'])) $errors[] = "Please capture a photo";
        if (empty($_POST['first_name'])) $errors[] = "First name is required";
        if (empty($_POST['last_name'])) $errors[] = "Last name is required";
        if (empty($_POST['contact_number'])) $errors[] = "Contact number is required";
        if (empty($_POST['address'])) $errors[] = "Address is required";
        if (empty($_POST['visit_reason'])) $errors[] = "Reason for visit is required";
        
        // Validate contact number - must be exactly 11 digits
        $contact_number = preg_replace('/[^0-9]/', '', $_POST['contact_number']);
        if (strlen($contact_number) != 11) {
            $errors[] = "Contact number must be exactly 11 digits (e.g., 09123456789)";
        }
        
        $is_priority = 0; // No priority for visit
                $remarks = isset($_POST['remarks']) ? $conn->real_escape_string(trim($_POST['remarks'])) : null;
        
        if (empty($errors)) {
            try {
                $photo_filename = null;
                if (!empty($_POST['captured_image_data'])) {
                    $image_data = $_POST['captured_image_data'];
                    if (strpos($image_data, 'base64,') !== false) {
                        $image_data = explode('base64,', $image_data);
                        $image_data = end($image_data);
                    }
                    $image_data = str_replace(' ', '+', $image_data);
                    $image_data = base64_decode($image_data);
                    $timestamp = date('Ymd_His');
                    $random = rand(1000, 9999);
                    $photo_filename = "visit_{$timestamp}_{$random}.png";
                    $filepath = $photos_dir . $photo_filename;
                    if (!file_put_contents($filepath, $image_data)) throw new Exception("Failed to save photo");
                }
                
                $first_name = capitalizeWords(trim($_POST['first_name']));
                $middle_name = !empty($_POST['middle_name']) ? capitalizeWords(trim($_POST['middle_name'])) : '';
                $last_name = capitalizeWords(trim($_POST['last_name']));
                $address = capitalizeWords(trim($_POST['address']));
                $visit_reason = $conn->real_escape_string(trim($_POST['visit_reason']));
                $contact_number = $conn->real_escape_string($contact_number);
                $email = isset($_POST['gmail']) && !empty(trim($_POST['gmail'])) ? $conn->real_escape_string(trim($_POST['gmail'])) : null;
                
                $assigned_investigator = '';
                $full_name = trim($first_name . ' ' . ($middle_name ? $middle_name . ' ' : '') . $last_name);
                
                // ============================================
                // FIXED: Generate sequential Visit queue number (VISIT-001, VISIT-002, etc.)
                // ============================================
                $queue_number = getNextVisitQueueNumber($conn);
                
                $current_datetime = date('Y-m-d H:i:s');
                $status = 'completed';
                
                $conn->begin_transaction();
                
                $query = "INSERT INTO complainants (queue_number, name, contact_number, email, address, remarks, visit_reason, category, assigned_investigator, status, photo_path, is_priority, created_at, updated_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $stmt->bind_param("sssssssssssiss", $queue_number, $full_name, $contact_number, $email, $address, $remarks, $visit_reason, $selected_category, $assigned_investigator, $status, $photo_filename, $is_priority, $current_datetime, $current_datetime);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to save visit record: " . $stmt->error);
                }
                $complainant_id = $stmt->insert_id;
                $stmt->close();

                $complaint_type = 'visit';
                $complaint_status = 'visit';
                $description = $conn->real_escape_string($visit_reason);
                $incident_date = date('Y-m-d');
                $incident_time = date('H:i:s');

                $query = "INSERT INTO complaints (complainant_id, queue_number, case_type, case_status, description, date_reported, incident_date, incident_time, created_at, updated_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Prepare failed for complaints: " . $conn->error);
                }
                $stmt->bind_param("isssssssss", $complainant_id, $queue_number, $complaint_type, $complaint_status, $description, $current_datetime, $incident_date, $incident_time, $current_datetime, $current_datetime);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to insert visit complaint: " . $stmt->error);
                }
                $stmt->close();

                if (isset($_SESSION['investigator_full_name'])) {
                    $log_query = "INSERT INTO investigator_action_log (investigator_name, badge_id, action_type, details, ip_address, user_agent, created_at) 
                                  VALUES (?, ?, 'create_visit', ?, ?, ?, NOW())";
                    $log_stmt = $conn->prepare($log_query);
                    if ($log_stmt) {
                        $details = "Created visit record for: {$full_name} - Reason: {$visit_reason} - Queue: {$queue_number}";
                        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
                        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                        $log_stmt->bind_param("sssss", 
                            $_SESSION['investigator_full_name'],
                            $_SESSION['investigator_badge_id'],
                            $details,
                            $ip_address,
                            $user_agent
                        );
                        $log_stmt->execute();
                        $log_stmt->close();
                    }
                }

                // insert history record
                $hist = $conn->prepare("INSERT INTO complaint_history (complainant_id, complaint_id, action, details, performed_by) VALUES (?, ?, 'create_visit', ?, ?)");
                if ($hist) {
                    $details = "Created visit record for {$full_name} (Queue: {$queue_number})";
                    $performed_by = isset($_SESSION['investigator_full_name']) ? $_SESSION['investigator_full_name'] : 'system';
                    $hist->bind_param('iiss', $complainant_id, $complainant_id, $details, $performed_by);
                    $hist->execute();
                    $hist->close();
                }
                
                $conn->commit();
                
                // Clear saved form data after successful submission
                unset($_SESSION['form_data']);
                
                $_SESSION['visit_success'] = "Visit record for {$full_name} has been successfully saved! Queue Number: {$queue_number}";
                redirect("records.php?success=visit_saved#visitRecordsSection");
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                $message = '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        } else {
            $message = '<div class="alert alert-danger"><strong>Validation Errors:</strong><br>' . implode('<br>', array_map('htmlspecialchars', $errors)) . '</div>';
            // Save form data to session on error
            saveFormDataToSession($_POST);
        }
    }
} else {
    // For General Cases and Women's Desk - full complaint form (WITH rotation - only active investigator can be selected)
    switch($selected_category) {
        case 'general_cases':
            $category_title = 'General Cases';
            $category_color = '#4299e1';
            $category_icon = 'bi-briefcase';
            initializeMaleInvestigators($conn);
            break;
        case 'womens_desk':
            $category_title = 'Women\'s Desk';
            $category_color = '#ed64a6';
            $category_icon = 'bi-shield-shaded';
            initializeFemaleInvestigators($conn);
            break;
    }
    
    // Handle complaint submission for General and Women's Desk
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['insert_investigator']) && !isset($_POST['remove_investigator']) && !isset($_POST['update_attendance'])) {
        $errors = array();
        
        if (empty($_POST['captured_image_data'])) $errors[] = "Please capture a photo";
        if (empty($_POST['first_name'])) $errors[] = "First name is required";
        if (empty($_POST['last_name'])) $errors[] = "Last name is required";
        if (empty($_POST['contact_number'])) $errors[] = "Contact number is required";
        if (empty($_POST['address'])) $errors[] = "Address is required";
        if (empty($_POST['case_type'])) $errors[] = "Case type is required";
        if (empty($_POST['case_status'])) $errors[] = "Case status is required";
        if (empty($_POST['assigned_investigator'])) $errors[] = "Assigned investigator is required";
        if (empty($_POST['incident_date'])) $errors[] = "Date of incident is required";
        
        // Validate contact number - must be exactly 11 digits
        $contact_number = preg_replace('/[^0-9]/', '', $_POST['contact_number']);
        if (strlen($contact_number) != 11) {
            $errors[] = "Contact number must be exactly 11 digits (e.g., 09123456789)";
        }
        
        $is_priority = isset($_POST['is_priority']) ? 1 : 0;
        
        // Get current active investigator
        $current_active = getCurrentActiveInvestigator($conn, $selected_category);
        
        // IMPORTANT: Only active investigator can be assigned
        if (!empty($_POST['assigned_investigator'])) {
            if (!$current_active || $_POST['assigned_investigator'] !== $current_active['name']) {
                $errors[] = "You can only assign the currently active investigator: " . ($current_active ? $current_active['name'] : 'None available');
            }
            $present_list = getPresentInvestigators($conn, $selected_category);
            if (!in_array($_POST['assigned_investigator'], $present_list)) {
                $errors[] = "Selected investigator is not present today.";
            }
        }
        
        if (empty($errors)) {
            try {
                $photo_filename = null;
                if (!empty($_POST['captured_image_data'])) {
                    $image_data = $_POST['captured_image_data'];
                    if (strpos($image_data, 'base64,') !== false) {
                        $image_data = explode('base64,', $image_data);
                        $image_data = end($image_data);
                    }
                    $image_data = str_replace(' ', '+', $image_data);
                    $image_data = base64_decode($image_data);
                    $timestamp = date('Ymd_His');
                    $random = rand(1000, 9999);
                    $photo_filename = "complainant_{$timestamp}_{$random}.png";
                    $filepath = $photos_dir . $photo_filename;
                    if (!file_put_contents($filepath, $image_data)) throw new Exception("Failed to save photo");
                }
                
                $first_name = capitalizeWords(trim($_POST['first_name']));
                $middle_name = !empty($_POST['middle_name']) ? capitalizeWords(trim($_POST['middle_name'])) : '';
                $last_name = capitalizeWords(trim($_POST['last_name']));
                $contact_number = $conn->real_escape_string($contact_number);
                $email = isset($_POST['gmail']) && !empty(trim($_POST['gmail'])) ? $conn->real_escape_string(trim($_POST['gmail'])) : null;
                $address = capitalizeWords(trim($_POST['address']));
                
                $case_type_raw = $_POST['case_type'];
                $case_type = $conn->real_escape_string(trim($case_type_raw));
                
                $case_status = $conn->real_escape_string($_POST['case_status']);
                $assigned_investigator = $conn->real_escape_string($_POST['assigned_investigator']);
                $incident_date = $conn->real_escape_string($_POST['incident_date']);
                $full_name = trim($first_name . ' ' . ($middle_name ? $middle_name . ' ' : '') . $last_name);
                $queue_number = getNextQueueNumber($conn, $case_status);
                $generated_queue = $queue_number;
                $current_datetime = date('Y-m-d H:i:s');
                $status = 'pending';
                
                $conn->begin_transaction();
                
                $query = "INSERT INTO complainants (queue_number, name, contact_number, email, address, remarks, category, assigned_investigator, status, photo_path, is_priority, created_at, updated_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Prepare failed for complainants: " . $conn->error);
                }
                // include remarks field
                $remarks = isset($_POST['remarks']) ? $conn->real_escape_string(trim($_POST['remarks'])) : null;
                $stmt->bind_param("ssssssssssiss", $queue_number, $full_name, $contact_number, $email, $address, $remarks, $selected_category, $assigned_investigator, $status, $photo_filename, $is_priority, $current_datetime, $current_datetime);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to insert complainant: " . $stmt->error);
                }
                $complainant_id = $stmt->insert_id;
                $generated_complainant_id = $complainant_id;
                $stmt->close();
                
                $query = "INSERT INTO complaints (complainant_id, queue_number, case_type, case_status, date_reported, incident_date, created_at, updated_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Prepare failed for complaints: " . $conn->error);
                }
                $stmt->bind_param("isssssss", $complainant_id, $queue_number, $case_type, $case_status, $current_datetime, $incident_date, $current_datetime, $current_datetime);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to insert complaint: " . $stmt->error);
                }
                $stmt->close();
                
                if (isset($_SESSION['investigator_full_name'])) {
                    $log_query = "INSERT INTO investigator_action_log (investigator_name, badge_id, action_type, details, ip_address, user_agent, created_at) 
                                  VALUES (?, ?, 'create_complaint', ?, ?, ?, NOW())";
                    $log_stmt = $conn->prepare($log_query);
                    if ($log_stmt) {
                        $details = "Created complaint #{$queue_number} for complainant: {$full_name}";
                        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
                        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                        $log_stmt->bind_param("sssss", 
                            $_SESSION['investigator_full_name'],
                            $_SESSION['investigator_badge_id'],
                            $details,
                            $ip_address,
                            $user_agent
                        );
                        $log_stmt->execute();
                        $log_stmt->close();
                    }
                }
                
                // Update rotation index for next complaint
                $total_present = count(getPresentInvestigators($conn, $selected_category));
                if ($total_present > 0) {
                    updateRotationIndex($conn, $selected_category, $total_present);
                }
                
                $conn->commit();
                
                // Clear saved form data after successful submission
                unset($_SESSION['form_data']);
                
                $_SESSION['complaint_success'] = "Complaint #{$queue_number} has been successfully registered!";
                $_SESSION['last_queue_number'] = $queue_number;
                $_SESSION['last_complainant_id'] = $complainant_id;
                // add history entry
                $hist = $conn->prepare("INSERT INTO complaint_history (complainant_id, complaint_id, action, details, performed_by) VALUES (?, ?, 'create_complaint', ?, ?)");
                if ($hist) {
                    $details = "Registered complaint {$queue_number} for {$full_name}";
                    $performed_by = isset($_SESSION['investigator_full_name']) ? $_SESSION['investigator_full_name'] : 'system';
                    $hist->bind_param('iiss', $complainant_id, $complainant_id, $details, $performed_by);
                    $hist->execute();
                    $hist->close();
                }
                redirect("ticket.php?queue={$queue_number}&id={$complainant_id}");
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                $message = '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        } else {
            $message = '<div class="alert alert-danger"><strong>Validation Errors:</strong><br>' . implode('<br>', array_map('htmlspecialchars', $errors)) . '</div>';
            // Save form data to session on error
            saveFormDataToSession($_POST);
        }
    }
}

// Helper function to capitalize first letter of each word
function capitalizeWords($string) {
    return ucwords(strtolower(trim($string)));
}

// Get common data for all categories
createAttendanceTables($conn);
$conn->query("CREATE TABLE IF NOT EXISTS investigator_rotation (
    id INT AUTO_INCREMENT PRIMARY KEY, 
    category VARCHAR(50) NOT NULL UNIQUE,
    rotation_index INT DEFAULT 0, 
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");

// Get data based on category
if ($selected_category === 'visit') {
    // Get ALL present investigators (both male and female)
    $present_investigators = getPresentInvestigators($conn, 'visit');
    $all_investigators_list = getAllInvestigatorsForCategory($conn, 'visit');
    $today_attendance = getTodayAttendance($conn, 'visit');
    $active_investigator_info = null;
    $current_active_investigator = null;
    $rotation_position = 0;
    $total_present = count($present_investigators);
} else {
    $present_investigators = getPresentInvestigators($conn, $selected_category);
    $all_investigators_list = getAllInvestigatorsForCategory($conn, $selected_category);
    $today_attendance = getTodayAttendance($conn, $selected_category);
    $active_investigator_info = getCurrentActiveInvestigator($conn, $selected_category);
    $current_active_investigator = $active_investigator_info ? $active_investigator_info['name'] : null;
    $rotation_position = $active_investigator_info ? $active_investigator_info['position'] : 0;
    $total_present = $active_investigator_info ? $active_investigator_info['total'] : 0;
}
$all_investigators = getAllInvestigators($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?php echo $selected_category === 'visit' ? 'Visit Form' : 'Complaint Form'; ?> - <?php echo htmlspecialchars($category_title); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        * { box-sizing: border-box; }
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
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
        .main-content { margin-left: 260px; padding: 20px; }
        .form-container { background: white; border-radius: 16px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .category-badge { background: <?php echo $category_color; ?>; color: white; padding: 8px 20px; border-radius: 30px; display: inline-block; margin-bottom: 20px; font-size: 0.9rem; }
        .form-label { font-weight: 600; font-size: 0.85rem; margin-bottom: 5px; }
        .required:after { content: " *"; color: #dc3545; }
        .datetime-info { background: #e8f0fe; border-left: 4px solid #0d6efd; padding: 12px; border-radius: 8px; margin: 20px 0; font-size: 0.85rem; }
        
        .priority-checkbox .form-check-input:checked { background-color: #ff9800; border-color: #ff9800; }
        .priority-checkbox .form-check-label { font-weight: 600; color: #e65100; }
        
        .camera-section { 
            border-radius: 16px; 
            padding: 20px; 
            margin-bottom: 25px; 
            color: white; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
        }
        
        .camera-container { 
            background: #000; 
            border-radius: 12px; 
            overflow: hidden; 
            position: relative; 
            margin-bottom: 15px; 
            min-height: 320px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        #video { 
            width: 100%; 
            height: auto;
            max-height: 420px;
            background: #000; 
            object-fit: cover;
            transform: scaleX(-1);
        }
        
        #canvas { display: none; }
        
        .camera-buttons { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin-top: 10px; }
        .btn-camera { padding: 10px 24px; border-radius: 40px; font-weight: 600; border: none; transition: all 0.2s; font-size: 0.9rem; }
        .btn-capture { background: #28a745; color: white; }
        .btn-capture:hover { background: #218838; transform: scale(1.02); }
        .btn-retake { background: #ffc107; color: #333; }
        .btn-retake:hover { background: #e0a800; }
        
        .photo-preview { 
            text-align: center; 
            padding: 20px; 
            background: rgba(255,255,255,0.95); 
            border-radius: 12px; 
        }
        .photo-preview img { 
            max-width: 280px; 
            width: 100%;
            border-radius: 12px; 
            border: 3px solid <?php echo $category_color; ?>; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            margin-bottom: 10px;
        }
        .camera-status { padding: 8px 12px; border-radius: 8px; margin-top: 12px; text-align: center; font-size: 0.8rem; background: rgba(0,0,0,0.7); color: white; }
        
        .loading-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); display: none; justify-content: center; align-items: center; z-index: 9999; flex-direction: column; }
        .loading-spinner { width: 50px; height: 50px; border: 4px solid #f3f3f3; border-top: 4px solid #667eea; border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .loading-text { margin-top: 15px; color: white; font-size: 1rem; }
        
        .fab-container { position: fixed; bottom: 25px; right: 25px; z-index: 1000; }
        .fab-main { width: 56px; height: 56px; border-radius: 50%; background: <?php echo $category_color; ?>; color: white; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.2); cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .fab-main:hover { transform: scale(1.08); }
        .fab-menu { position: absolute; bottom: 70px; right: 0; display: flex; flex-direction: column; gap: 10px; visibility: hidden; opacity: 0; transition: all 0.2s; }
        .fab-menu.show { visibility: visible; opacity: 1; }
        .fab-item { width: 44px; height: 44px; border-radius: 50%; background: white; color: <?php echo $category_color; ?>; border: 2px solid <?php echo $category_color; ?>; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 18px; transition: all 0.2s; }
        .fab-item:hover { transform: scale(1.08); background: <?php echo $category_color; ?>; color: white; }
        
        .modal-header { background: <?php echo $category_color; ?>; color: white; }
        .investigator-item { display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid #e0e0e0; }
        .investigator-male { border-left: 3px solid #4299e1; background: #f0f7ff; }
        .investigator-female { border-left: 3px solid #ed64a6; background: #fff0f5; }
        .availability-active { background: #d4edda; color: #155724; border-left: 3px solid #28a745; padding: 10px; border-radius: 6px; margin-top: 8px; font-size: 0.85rem; }
        .availability-inactive { background: #f8d7da; color: #721c24; border-left: 3px solid #dc3545; padding: 10px; border-radius: 6px; margin-top: 8px; font-size: 0.85rem; }
        
        .capitalize-input {
            text-transform: capitalize;
        }
        
        /* Contact number styling */
        .contact-number-input {
            letter-spacing: 0.5px;
        }
        
        @media (max-width: 768px) {
            .sidebar { width: 100%; height: auto; position: relative; min-height: auto; }
            .main-content { margin-left: 0; }
            .sidebar .system-badge { position: relative; margin-top: 10px; }
            .form-container { padding: 20px; }
            .fab-container { bottom: 15px; right: 15px; }
            .camera-container { min-height: 280px; }
            .photo-preview img { max-width: 220px; }
        }
        
        .btn-submit { margin-top: 20px; }
        .btn-submit .btn { padding: 12px 30px; font-size: 1rem; border-radius: 40px; margin-right: 10px; }
        
        select.form-control, input.form-control, textarea.form-control { border-radius: 8px; border: 1px solid #ddd; padding: 10px 12px; }
        select.form-control:focus, input.form-control:focus, textarea.form-control:focus { border-color: <?php echo $category_color; ?>; box-shadow: 0 0 0 0.2rem rgba(66,153,225,0.25); }
        
        .rotation-indicator {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Processing...</div>
    </div>
    
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- SIDEBAR -->
            <div class="col-auto p-0 sidebar">
                <div class="sidebar-logo">
                    <div class="logo-frame">
                        <img src="videos/uploads/cyberlogo.png" alt="ACG Logo" class="logo-img" onerror="this.src='https://via.placeholder.com/100?text=ACG'">
                    </div>
                    <div class="logo-text">CSPCRT</div>
                </div>
                <nav>
                    <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
                    <a href="category_selection.php" class="active"><i class="bi bi-plus-circle"></i> New Complaint</a>
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
                    <i class="bi bi-shield-check"></i> PNP ACG · v3.0
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col main-content">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                    <h2 class="mb-2"><?php echo $selected_category === 'visit' ? 'Visit Form' : 'Complaint Form'; ?></h2>
                    <a href="category_selection.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> Back to Categories
                    </a>
                </div>
                
                <div class="category-badge">
                    <i class="bi <?php echo $category_icon; ?>"></i>
                    <?php echo htmlspecialchars($category_title); ?>
                </div>
                
                <?php echo $message; ?>
                
                <!-- Rotation Indicator for General and Women's Desk -->
                <?php if ($selected_category !== 'visit' && $current_active_investigator): ?>
                <div class="rotation-indicator">
                    <i class="bi bi-arrow-repeat"></i> 
                    <strong>Currently Active Investigator:</strong> <?php echo htmlspecialchars($current_active_investigator); ?>
                    <br><small>Position <?php echo $rotation_position; ?> of <?php echo $total_present; ?> in rotation</small>
                </div>
                <?php endif; ?>
                
                <!-- FORM -->
                <div class="form-container">
                    <!-- CAPTURE PHOTO SECTION -->
                    <div class="camera-section">
                        <h5 class="mb-2"><i class="bi bi-camera"></i> Capture Photo <span class="text-danger">*</span></h5>
                        <p class="small opacity-75 mb-3">Position the person's face clearly in front of the camera. Ensure good lighting.</p>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="camera-container">
                                    <video id="video" autoplay playsinline></video>
                                    <canvas id="canvas"></canvas>
                                </div>
                                <div class="camera-buttons">
                                    <button type="button" class="btn-camera btn-capture" id="captureBtn">
                                        <i class="bi bi-camera"></i> Capture Photo
                                    </button>
                                    <button type="button" class="btn-camera btn-retake" id="retakeBtn" style="display: none;">
                                        <i class="bi bi-arrow-repeat"></i> Retake
                                    </button>
                                </div>
                                <div id="cameraStatus" class="camera-status bg-dark">
                                    <i class="bi bi-info-circle"></i> Camera ready
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="photo-preview">
                                    <label class="form-label text-dark">Photo Preview <span class="text-danger">*</span></label>
                                    <div id="photoPreview">
                                        <img id="capturedPhoto" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200' viewBox='0 0 24 24' fill='none' stroke='%23999' stroke-width='1'%3E%3Crect x='2' y='2' width='20' height='20' rx='2'/%3E%3Ccircle cx='8.5' cy='8.5' r='1.5'/%3E%3Cpolyline points='22 15 16 9 6 19 2 15'/%3E%3C/svg%3E" 
                                             style="width: 100%; max-width: 280px; border-radius: 12px;">
                                    </div>
                                    <p class="small text-muted mt-2" id="photoStatus">
                                        <i class="bi bi-exclamation-triangle text-danger"></i> Required: Capture a photo
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Date/Time Info -->
                    <div class="datetime-info">
                        <i class="bi bi-calendar-event"></i> 
                        <strong>Current Date & Time:</strong> <?php echo date('F d, Y h:i A'); ?>
                        <br><small>This will be recorded as the official reporting date/time.</small>
                    </div>
                    
                    <form method="POST" action="" id="complaintForm">
                        <div id="formAlertContainer"></div>
                        <input type="hidden" name="captured_image_data" id="capturedImageData" value="">
                        
                        <?php if ($selected_category === 'visit'): ?>
                        <!-- Visitor Information for Visit Form -->
                        <h5 class="mb-3"><i class="bi bi-person-badge"></i> Visitor Information</h5>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label required">First Name</label>
                                <input type="text" class="form-control capitalize-input" name="first_name" id="first_name" placeholder="Enter first name" required autocomplete="off" value="<?php echo getSavedFormData('first_name'); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Middle Name</label>
                                <input type="text" class="form-control capitalize-input" name="middle_name" id="middle_name" placeholder="Enter middle name" autocomplete="off" value="<?php echo getSavedFormData('middle_name'); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label required">Last Name</label>
                                <input type="text" class="form-control capitalize-input" name="last_name" id="last_name" placeholder="Enter last name" required autocomplete="off" value="<?php echo getSavedFormData('last_name'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Contact Number</label>
                                <input type="tel" class="form-control contact-number-input" name="contact_number" id="contact_number" placeholder="09123456789" required autocomplete="off" maxlength="11" pattern="[0-9]{11}" value="<?php echo getSavedFormData('contact_number'); ?>" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0,11)">
                                <small class="text-muted">Enter exactly 11 digits (e.g., 09123456789)</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label optional">Email Address</label>
                                <input type="email" class="form-control" name="gmail" id="email" placeholder="email@example.com" value="<?php echo getSavedFormData('gmail'); ?>">
                                <small class="text-muted">Optional</small>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label required">Address</label>
                                <textarea class="form-control" name="address" id="address" rows="2" placeholder="Enter complete address" required style="text-transform: capitalize;"><?php echo getSavedFormData('address'); ?></textarea>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Remarks (e.g., accompanying persons)</label>
                                <textarea class="form-control" name="remarks" id="remarks" rows="2" placeholder="Optional remarks, accompanying persons, notes..."><?php echo getSavedFormData('remarks'); ?></textarea>
                                <small class="text-muted">Optional: record visitor details or accompanying persons.</small>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label required">Reason for Visit</label>
                                <textarea class="form-control" name="visit_reason" id="visit_reason" rows="3" placeholder="Please state the reason for the visit..." required><?php echo getSavedFormData('visit_reason'); ?></textarea>
                            </div>
                        </div>
                        
                        <?php else: ?>
                        <!-- Complainant Information for General Cases and Women's Desk -->
                        <h5 class="mb-3"><i class="bi bi-person-badge"></i> Complainant Information</h5>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label required">First Name</label>
                                <input type="text" class="form-control capitalize-input" name="first_name" id="first_name" placeholder="Enter first name" required autocomplete="off" value="<?php echo getSavedFormData('first_name'); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Middle Name</label>
                                <input type="text" class="form-control capitalize-input" name="middle_name" id="middle_name" placeholder="Enter middle name" autocomplete="off" value="<?php echo getSavedFormData('middle_name'); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label required">Last Name</label>
                                <input type="text" class="form-control capitalize-input" name="last_name" id="last_name" placeholder="Enter last name" required autocomplete="off" value="<?php echo getSavedFormData('last_name'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Contact Number</label>
                                <input type="tel" class="form-control contact-number-input" name="contact_number" id="contact_number" placeholder="09123456789" required autocomplete="off" maxlength="11" pattern="[0-9]{11}" value="<?php echo getSavedFormData('contact_number'); ?>" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0,11)">
                                <small class="text-muted">Enter exactly 11 digits (e.g., 09123456789)</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label optional">Email Address</label>
                                <input type="email" class="form-control" name="gmail" id="email" placeholder="email@example.com" value="<?php echo getSavedFormData('gmail'); ?>">
                                <small class="text-muted">Optional</small>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label required">Address</label>
                                <textarea class="form-control" name="address" id="address" rows="2" placeholder="Enter complete address" required style="text-transform: capitalize;"><?php echo getSavedFormData('address'); ?></textarea>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Remarks (e.g., accompanying persons)</label>
                                <textarea class="form-control" name="remarks" id="remarks" rows="2" placeholder="Optional remarks, accompanying persons, notes..."><?php echo getSavedFormData('remarks'); ?></textarea>
                                <small class="text-muted">Optional: record visitor details or accompanying persons.</small>
                            </div>
                        </div>
                        
                        <!-- Case Information (Only for General and Women's Desk) - TWO COLUMN LAYOUT -->
                        <h5 class="mb-3 mt-4"><i class="bi bi-folder"></i> Case Information</h5>
                        <div class="row">
                            <!-- Left Column: Type of Case + Assigned Investigator -->
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label required">Type of Case</label>
                                    <select class="form-control" name="case_type" id="caseTypeSelect" required style="width: 100%;">
                                        <option value="">Search and select case type...</option>
                                        <?php foreach ($case_types_list as $case): ?>
                                            <option value="<?php echo htmlspecialchars($case); ?>" <?php echo (getSavedFormData('case_type') == $case) ? 'selected' : ''; ?>><?php echo htmlspecialchars($case); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Type to search or add custom case type</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label required">Assigned Investigator</label>
                                    <select class="form-control" name="assigned_investigator" id="assignedInvestigator" required>
                                        <option value="">Select investigator...</option>
                                        <?php 
                                        $unique_present_investigators = array_unique($present_investigators);
                                        $saved_investigator = getSavedFormData('assigned_investigator', '');
                                        foreach ($unique_present_investigators as $investigator): 
                                            $is_active = ($investigator == $current_active_investigator);
                                        ?>
                                        <option value="<?php echo htmlspecialchars($investigator); ?>" 
                                            <?php echo ($saved_investigator == $investigator || $is_active) ? 'selected' : ''; ?> 
                                            <?php echo (!$is_active) ? 'disabled' : ''; ?>>
                                            <?php echo htmlspecialchars($investigator); ?> 
                                            <?php echo $is_active ? '(Active - Currently Assigned)' : ''; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($current_active_investigator): ?>
                                    <div class="availability-active mt-2">
                                        <i class="bi bi-check-circle-fill"></i> <strong><?php echo htmlspecialchars($current_active_investigator); ?></strong> is currently active for this category.
                                        <br><small>Only the active investigator can be assigned to new complaints.</small>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (empty($present_investigators)): ?>
                                    <div class="availability-inactive mt-2">
                                        <i class="bi bi-exclamation-triangle-fill"></i> No investigators marked as present today. Please mark attendance first.
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Right Column: Purpose + Date of Incident -->
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label required">Purpose</label>
                                    <select class="form-control" name="case_status" required>
                                        <option value="">Select purpose...</option>
                                        <option value="complaint" <?php echo (getSavedFormData('case_status') == 'complaint') ? 'selected' : ''; ?>>Complaint</option>
                                        <option value="inquiry" <?php echo (getSavedFormData('case_status') == 'inquiry') ? 'selected' : ''; ?>>Inquiry</option>
                                        <option value="follow-up" <?php echo (getSavedFormData('case_status') == 'follow-up') ? 'selected' : ''; ?>>Follow-up</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label required">Date of Incident</label>
                                    <input type="date" class="form-control" name="incident_date" id="incident_date" max="<?php echo date('Y-m-d'); ?>" required value="<?php echo getSavedFormData('incident_date'); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Priority Lane -->
                        <div class="priority-checkbox mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_priority" id="isPriority" value="1" <?php echo (getSavedFormData('is_priority') == '1') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="isPriority">
                                    <strong>Mark as Priority Lane</strong>
                                </label>
                                <p class="small text-muted mt-1 mb-0">Priority cases appear in Priority Lane on TV display</p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Form Actions -->
                        <div class="row btn-submit">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary btn-lg" id="submitBtn" <?php echo empty($present_investigators) ? 'disabled' : ''; ?>>
                                    <i class="bi bi-save"></i> <?php echo $selected_category === 'visit' ? 'Save Visit Record' : 'Register Complaint'; ?>
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-lg" id="resetFormBtn">
                                    <i class="bi bi-eraser"></i> Clear Form
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- FAB MENU -->
    <div class="fab-container">
        <div class="fab-menu" id="fabMenu">
            <button class="fab-item" data-bs-toggle="modal" data-bs-target="#attendanceModal" title="Attendance">
                <i class="bi bi-calendar-check"></i>
            </button>
            <button class="fab-item" data-bs-toggle="modal" data-bs-target="#rotationModal" title="Rotation">
                <i class="bi bi-arrow-repeat"></i>
            </button>
            <button class="fab-item" data-bs-toggle="modal" data-bs-target="#investigatorModal" title="Investigators">
                <i class="bi bi-people-fill"></i>
            </button>
                    <button class="fab-item" title="Follow-Up" onclick="location.href='records.php#followUpSection'">
                        <i class="bi bi-clock-history"></i>
                    </button>
        </div>
        <button class="fab-main" id="fabMain">
            <i class="bi bi-plus-lg"></i>
        </button>
    </div>
    
    <!-- Modals -->
    <div class="modal fade" id="attendanceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-calendar-check"></i> Daily Attendance - <?php echo date('F d, Y'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="update_attendance" value="1">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr><th>Investigator</th><th>Status</th><th>Notes</th></tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    if ($selected_category === 'visit') {
                                        $all_active_investigators = array();
                                        $query = "SELECT DISTINCT name FROM investigators WHERE status = 'active' ORDER BY name ASC";
                                        $result = $conn->query($query);
                                        if ($result) {
                                            while ($row = $result->fetch_assoc()) {
                                                $all_active_investigators[] = $row['name'];
                                            }
                                        }
                                        $investigators_to_show = $all_active_investigators;
                                    } else {
                                        $investigators_to_show = $all_investigators_list;
                                    }
                                    
                                    foreach ($investigators_to_show as $investigator): 
                                        $current_status = isset($today_attendance[$investigator]) ? $today_attendance[$investigator] : 'present';
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($investigator); ?></td>
                                        <td>
                                            <select name="attendance[<?php echo htmlspecialchars($investigator); ?>]" class="form-select form-select-sm">
                                                <option value="present" <?php echo $current_status == 'present' ? 'selected' : ''; ?>>Present</option>
                                                <option value="absent" <?php echo $current_status == 'absent' ? 'selected' : ''; ?>>Absent</option>
                                                <option value="day_off" <?php echo $current_status == 'day_off' ? 'selected' : ''; ?>>Day Off</option>
                                            </select>
                                        </span>
                                        <td><input type="text" name="notes[<?php echo htmlspecialchars($investigator); ?>]" class="form-control form-control-sm" placeholder="Notes"></span>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save Attendance</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="rotationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-arrow-repeat"></i> Investigator Rotation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if ($selected_category === 'visit'): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> Visit records do not use rotation. Any investigator can be assigned.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <strong>Present Today:</strong> <?php echo count($present_investigators); ?> / <?php echo count($all_investigators_list); ?><br>
                            <strong>Current Position:</strong> #<?php echo $rotation_position; ?> of <?php echo $total_present; ?>
                            <br><strong>Active Investigator:</strong> <?php echo $current_active_investigator ? htmlspecialchars($current_active_investigator) : 'None'; ?>
                        </div>
                        <ul class="list-group">
                            <?php 
                            $order_number = 1;
                            foreach ($present_investigators as $investigator):
                                $is_current = ($investigator == $current_active_investigator);
                            ?>
                            <li class="list-group-item <?php echo $is_current ? 'list-group-item-primary fw-bold' : ''; ?>">
                                <?php echo $order_number; ?>. <?php echo htmlspecialchars($investigator); ?>
                                <?php if ($is_current): ?> <span class="badge bg-primary">CURRENTLY ACTIVE</span> <?php endif; ?>
                            </li>
                            <?php $order_number++; endforeach; ?>
                        </ul>
                        <div class="alert alert-secondary mt-3 small">
                            <i class="bi bi-info-circle"></i> After each complaint submission, the active assignment automatically rotates to the next investigator in the list.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="investigatorModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-people-fill"></i> Manage Investigators</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-tabs" id="investigatorTabs">
                        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#viewTab">View All</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#addTab">Add</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#removeTab">Remove</button></li>
                    </ul>
                    <div class="tab-content mt-3">
                        <div class="tab-pane fade show active" id="viewTab">
                            <?php foreach ($all_investigators as $inv): ?>
                                <div class="investigator-item <?php echo $inv['gender'] == 'male' ? 'investigator-male' : 'investigator-female'; ?>">
                                    <div>
                                        <strong><?php echo htmlspecialchars($inv['name']); ?></strong><br>
                                        <small><?php echo ucfirst($inv['gender']); ?> | <?php echo $inv['category']; ?></small>
                                    </div>
                                    <?php if (in_array($inv['name'], $present_investigators)): ?>
                                        <span class="badge bg-success">Present</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="tab-pane fade" id="addTab">
                            <form method="POST">
                                <input type="hidden" name="insert_investigator" value="1">
                                <div class="mb-2"><label>Name</label><input type="text" class="form-control" name="investigator_name" required></div>
                                <div class="mb-2"><label>Category</label>
                                    <select class="form-control" name="investigator_category" id="inv_cat">
                                        <option value="all">General</option>
                                        <option value="womens_desk">Women's Desk</option>
                                    </select>
                                </div>
                                <div class="mb-2"><label>Gender</label>
                                    <select class="form-control" name="investigator_gender" id="inv_gen">
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">Add</button>
                            </form>
                        </div>
                        <div class="tab-pane fade" id="removeTab">
                            <form method="POST" onsubmit="return confirm('Remove this investigator?');">
                                <input type="hidden" name="remove_investigator" value="1">
                                <div class="mb-2"><label>Select</label>
                                    <select class="form-control" name="investigator_name" required>
                                        <?php foreach ($all_investigators as $inv): ?>
                                            <option value="<?php echo htmlspecialchars($inv['name']); ?>"><?php echo htmlspecialchars($inv['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        let stream = null;
        
        function capitalizeWords(input) {
            let value = input.value;
            let words = value.toLowerCase().split(' ');
            for (let i = 0; i < words.length; i++) {
                if (words[i].length > 0) {
                    words[i] = words[i].charAt(0).toUpperCase() + words[i].slice(1);
                }
            }
            input.value = words.join(' ');
        }
        
        function capitalizeTextarea(input) {
            let value = input.value;
            let sentences = value.split(/([.!?]\s+)/);
            for (let i = 0; i < sentences.length; i++) {
                if (sentences[i].trim().length > 0 && !sentences[i].match(/^[.!?]/)) {
                    sentences[i] = sentences[i].charAt(0).toUpperCase() + sentences[i].slice(1).toLowerCase();
                }
            }
            input.value = sentences.join('');
        }
        
        $(document).ready(function() {
            <?php if ($selected_category !== 'visit'): ?>
            $('#caseTypeSelect').select2({
                placeholder: "Search or type custom case type...",
                allowClear: true,
                width: '100%',
                tags: true,
                tokenSeparators: [',', ';'],
                createTag: function(params) {
                    var term = $.trim(params.term);
                    if (term === '') return null;
                    return { id: term, text: term + ' (Custom)', newOption: true };
                }
            });
            <?php endif; ?>
            
            $('#inv_cat').change(function() {
                $('#inv_gen').val($(this).val() === 'womens_desk' ? 'female' : 'male');
                $('#inv_gen').prop('disabled', true);
            }).trigger('change');
            
            $('#first_name, #middle_name, #last_name').on('input', function() {
                capitalizeWords(this);
            });
            
            $('#address').on('input', function() {
                capitalizeTextarea(this);
            });
            
            // Capitalize visit_reason on blur (first letter of each sentence)
            $('#visit_reason').on('input', function() {
                let value = $(this).val();
                let sentences = value.split(/([.!?]\s+)/);
                for (let i = 0; i < sentences.length; i++) {
                    if (sentences[i].trim().length > 0 && !sentences[i].match(/^[.!?]/)) {
                        sentences[i] = sentences[i].charAt(0).toUpperCase() + sentences[i].slice(1).toLowerCase();
                    }
                }
                $(this).val(sentences.join(''));
            });
            
            // Clear session storage when reset button is clicked
            $('#resetFormBtn').on('click', function(e) {
                e.preventDefault();
                if (confirm('Clear all form data? This will also clear saved data.')) {
                    localStorage.removeItem('complaintFormDraft');
                    window.location.href = window.location.pathname + '?category=<?php echo $selected_category; ?>&clear_form=1';
                }
            });

            // Save form state to browser storage so user can refresh or navigate away safely
            function saveComplaintDraft() {
                const draft = {};
                $('#complaintForm').find('input, textarea, select').each(function() {
                    const name = $(this).attr('name');
                    if (!name || name === 'captured_image_data') return;
                    const type = $(this).attr('type');
                    if (type === 'submit' || type === 'button' || type === 'reset' || type === 'file') return;
                    if (this.type === 'checkbox') {
                        draft[name] = this.checked ? $(this).val() : '';
                    } else if (this.type === 'radio') {
                        if (this.checked) draft[name] = $(this).val();
                    } else {
                        draft[name] = $(this).val();
                    }
                });
                localStorage.setItem('complaintFormDraft', JSON.stringify(draft));
            }

            function restoreComplaintDraft() {
                const raw = localStorage.getItem('complaintFormDraft');
                if (!raw) return;
                try {
                    const draft = JSON.parse(raw);
                    Object.keys(draft).forEach(function(key) {
                        const value = draft[key];
                        const field = $('#complaintForm [name="' + key + '"]');
                        if (!field.length) return;
                        if (field.is(':checkbox')) {
                            field.prop('checked', value === '1' || value === 'true');
                        } else if (field.is(':radio')) {
                            $('#complaintForm [name="' + key + '"][value="' + value + '"]').prop('checked', true);
                        } else {
                            field.val(value);
                        }
                    });

                    if (typeof $('#caseTypeSelect').select2 === 'function') {
                        const caseTypeValue = draft['case_type'] || '';
                        if (caseTypeValue) {
                            $('#caseTypeSelect').val(caseTypeValue).trigger('change');
                        }
                    }
                } catch (err) {
                    console.error('Unable to restore complaint draft:', err);
                }
            }

            restoreComplaintDraft();
            $('#complaintForm').on('input change', 'input, textarea, select', saveComplaintDraft);
        });
        
        const fabMain = document.getElementById('fabMain');
        const fabMenu = document.getElementById('fabMenu');
        fabMain.addEventListener('click', () => {
            fabMenu.classList.toggle('show');
            fabMain.querySelector('i').classList.toggle('bi-plus-lg');
            fabMain.querySelector('i').classList.toggle('bi-x-lg');
        });
        document.addEventListener('click', (e) => {
            if (!fabMain.contains(e.target) && !fabMenu.contains(e.target)) {
                fabMenu.classList.remove('show');
                fabMain.querySelector('i').classList.add('bi-plus-lg');
                fabMain.querySelector('i').classList.remove('bi-x-lg');
            }
        });
        
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const captureBtn = document.getElementById('captureBtn');
        const retakeBtn = document.getElementById('retakeBtn');
        const capturedPhoto = document.getElementById('capturedPhoto');
        const capturedImageData = document.getElementById('capturedImageData');
        const cameraStatus = document.getElementById('cameraStatus');
        const photoStatus = document.getElementById('photoStatus');
        
        async function initCamera() {
            try {
                // Stop any existing streams
                if (stream) {
                    stream.getTracks().forEach(t => t.stop());
                    stream = null;
                }
                
                // Request camera with fallback constraints
                let constraints = {
                    video: { 
                        facingMode: 'user',
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    } 
                };
                
                // First try with ideal dimensions, fallback to basic video if that fails
                try {
                    stream = await navigator.mediaDevices.getUserMedia(constraints);
                } catch (e) {
                    console.warn('getUserMedia with ideal dimensions failed, trying basic video constraint', e);
                    stream = await navigator.mediaDevices.getUserMedia({ video: true });
                }
                
                if (!stream) {
                    throw new Error('Failed to obtain media stream');
                }
                
                video.srcObject = stream;
                console.log('Camera stream started successfully');
                
                // Ensure video plays when metadata loads
                video.onloadedmetadata = () => {
                    video.play().catch(err => {
                        console.error('Failed to play video:', err);
                        cameraStatus.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Camera failed to play';
                        cameraStatus.className = 'camera-status bg-danger';
                    });
                };
                
                // Set timeout to check if video is playing
                setTimeout(() => {
                    if (video.readyState === video.HAVE_ENOUGH_DATA) {
                        cameraStatus.innerHTML = '<i class="bi bi-check-circle"></i> Camera ready';
                        cameraStatus.className = 'camera-status bg-success';
                        captureBtn.disabled = false;
                    } else {
                        console.warn('Camera not ready after initialization');
                        cameraStatus.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Camera initializing...';
                        cameraStatus.className = 'camera-status bg-warning';
                    }
                }, 500);
                
            } catch(err) {
                console.error('Camera initialization error:', err.name, err.message);
                cameraStatus.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Camera unavailable';
                cameraStatus.className = 'camera-status bg-danger';
                captureBtn.disabled = true;
                
                // Provide specific error guidance
                if (err.name === 'NotAllowedError') {
                    showFormAlert('Camera permission denied. Please enable camera access in your browser settings.', 'warning');
                } else if (err.name === 'NotFoundError') {
                    showFormAlert('No camera device found. Please connect a camera.', 'warning');
                } else if (err.name === 'NotReadableError') {
                    showFormAlert('Camera is already in use by another application.', 'warning');
                } else {
                    showFormAlert('Camera access failed: ' + err.message, 'warning');
                }
            }
        }
        
        function capturePhoto() {
            if (!video.srcObject) { 
                showFormAlert('Camera not ready', 'warning');
                console.warn('Capture attempted but video stream not active');
                return;
            }
            
            const ctx = canvas.getContext('2d');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            
            const imageData = canvas.toDataURL('image/png');
            capturedImageData.value = imageData;
            capturedPhoto.src = imageData;
            captureBtn.style.display = 'none';
            retakeBtn.style.display = 'inline-block';
            cameraStatus.innerHTML = '<i class="bi bi-check-circle"></i> Photo captured';
            photoStatus.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> Photo captured!';
            
            if (stream) { 
                stream.getTracks().forEach(t => t.stop()); 
                video.srcObject = null; 
                stream = null; 
            }
        }
        
        function retakePhoto() {
            initCamera();
            capturedImageData.value = '';
            capturedPhoto.src = 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'200\' height=\'200\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%23999\'%3E%3Crect x=\'2\' y=\'2\' width=\'20\' height=\'20\' rx=\'2\'/%3E%3Ccircle cx=\'8.5\' cy=\'8.5\' r=\'1.5\'/%3E%3Cpolyline points=\'22 15 16 9 6 19 2 15\'/%3E%3C/svg%3E';
            captureBtn.style.display = 'inline-block';
            retakeBtn.style.display = 'none';
            photoStatus.innerHTML = '<i class="bi bi-exclamation-triangle text-danger"></i> Required: Capture a photo';
        }
        
        captureBtn.addEventListener('click', capturePhoto);
        retakeBtn.addEventListener('click', retakePhoto);
        initCamera();
        
        document.getElementById('complaintForm').addEventListener('submit', function(e) {
            if (!capturedImageData.value) {
                e.preventDefault();
                showFormAlert('Please capture a photo first.', 'warning');
                return false;
            }
            
            let firstName = $('#first_name');
            let middleName = $('#middle_name');
            let lastName = $('#last_name');
            let address = $('#address');
            
            if (firstName.val()) capitalizeWords(firstName[0]);
            if (middleName.val()) capitalizeWords(middleName[0]);
            if (lastName.val()) capitalizeWords(lastName[0]);
            if (address.val()) capitalizeTextarea(address[0]);
            
            let contact = $('#contact_number').val();
            let cleanContact = contact.replace(/[^0-9]/g, '');
            if (cleanContact.length !== 11) {
                e.preventDefault();
                showFormAlert('Please enter a valid 11-digit Philippine mobile number (e.g., 09123456789)', 'warning');
                return false;
            }
            $('#contact_number').val(cleanContact);
            
            <?php if ($selected_category !== 'visit'): ?>
            if (!$('#incident_date').val()) {
                e.preventDefault();
                showFormAlert('Please select the date of incident.', 'warning');
                return false;
            }
            <?php else: ?>
            if (!$('#visit_reason').val()) {
                e.preventDefault();
                showFormAlert('Please enter the reason for visit.', 'warning');
                return false;
            }
            <?php endif; ?>
            
            localStorage.removeItem('complaintFormDraft');
            $('#loadingOverlay').show();
            $('#submitBtn').prop('disabled', true);
        });
        
        window.addEventListener('beforeunload', () => {
            if (stream) stream.getTracks().forEach(t => t.stop());
        });
    </script>
</body>
</html>