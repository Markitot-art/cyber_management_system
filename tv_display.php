<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

// Then include your existing code...
require_once 'config.php';


// No login required for TV display - it's public viewing

// Handle AJAX count requests
if (isset($_GET['get_counts']) && $_GET['get_counts'] == 1) {
    header('Content-Type: application/json');
    
    $general_waiting = 0;
    $general_count = $conn->query("SELECT COUNT(*) as count FROM complainants WHERE status = 'pending' AND category = 'general_cases' AND (is_priority = 0 OR is_priority IS NULL)");
    if ($general_count) $general_waiting = $general_count->fetch_assoc()['count'];
    
    $womens_waiting = 0;
    $womens_count = $conn->query("SELECT COUNT(*) as count FROM complainants WHERE status = 'pending' AND category = 'womens_desk' AND (is_priority = 0 OR is_priority IS NULL)");
    if ($womens_count) $womens_waiting = $womens_count->fetch_assoc()['count'];
    
    $priority_waiting = 0;
    $priority_count = $conn->query("SELECT COUNT(*) as count FROM complainants WHERE status = 'pending' AND is_priority = 1");
    if ($priority_count) $priority_waiting = $priority_count->fetch_assoc()['count'];
    
    echo json_encode([
        'general' => $general_waiting,
        'womens' => $womens_waiting,
        'priority' => $priority_waiting
    ]);
    exit;
}

// Get MOST RECENT CALL for each category
function getCurrentServing($conn, $category, $isPriority = false) {
    $sql = "
        SELECT 
            c.queue_number,
            comp.assigned_investigator,
            comp.name as complainant_name,
            comp.updated_at
        FROM complainants comp
        LEFT JOIN complaints c ON comp.complainant_id = c.complainant_id
        WHERE comp.status = 'processing' 
    ";
    
    if ($isPriority) {
        $sql .= " AND comp.is_priority = 1";
    } else {
        $sql .= " AND comp.category = '$category' AND (comp.is_priority = 0 OR comp.is_priority IS NULL)";
    }
    
    $sql .= " ORDER BY comp.updated_at DESC, comp.complainant_id DESC LIMIT 1";
    
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

$general_current = getCurrentServing($conn, 'general_cases', false);
$womens_current = getCurrentServing($conn, 'womens_desk', false);
$priority_current = getCurrentServing($conn, '', true);

// Get waiting counts
$general_waiting = 0;
$general_count = $conn->query("SELECT COUNT(*) as count FROM complainants WHERE status = 'pending' AND category = 'general_cases' AND (is_priority = 0 OR is_priority IS NULL)");
if ($general_count) $general_waiting = $general_count->fetch_assoc()['count'];

$womens_waiting = 0;
$womens_count = $conn->query("SELECT COUNT(*) as count FROM complainants WHERE status = 'pending' AND category = 'womens_desk' AND (is_priority = 0 OR is_priority IS NULL)");
if ($womens_count) $womens_waiting = $womens_count->fetch_assoc()['count'];

$priority_waiting = 0;
$priority_count = $conn->query("SELECT COUNT(*) as count FROM complainants WHERE status = 'pending' AND is_priority = 1");
if ($priority_count) $priority_waiting = $priority_count->fetch_assoc()['count'];

$has_pending = ($general_waiting + $womens_waiting + $priority_waiting) > 0;

// Get media files
$media_files = [];
$video_dir = 'media/videos/';
$image_dir = 'media/images/';

if (is_dir($video_dir)) {
    $files = scandir($video_dir);
    foreach ($files as $file) {
        if (preg_match('/\.(mp4|webm|ogg|mov|avi)$/i', $file)) {
            $media_files[] = ['type' => 'video', 'file' => $file, 'path' => $video_dir . $file];
        }
    }
}

if (is_dir($image_dir)) {
    $files = scandir($image_dir);
    foreach ($files as $file) {
        if (preg_match('/\.(jpg|jpeg|png|gif|bmp|webp)$/i', $file)) {
            $media_files[] = ['type' => 'image', 'file' => $file, 'path' => $image_dir . $file];
        }
    }
}

// Check if we have saved playback order (using cookies, not localStorage which is JS-only)
$saved_order = null;
if (isset($_GET['reset_playback'])) {
    setcookie('tv_playlist_order', '', time() - 3600, '/');
}

// Use saved order or shuffle
if (!isset($_GET['reset'])) {
    $saved_order_json = isset($_COOKIE['tv_playlist_order']) ? $_COOKIE['tv_playlist_order'] : null;
    if ($saved_order_json) {
        $saved_order = json_decode($saved_order_json, true);
        if (is_array($saved_order) && count($saved_order) === count($media_files)) {
            $media_files = $saved_order;
        } else {
            shuffle($media_files);
        }
    } else {
        shuffle($media_files);
    }
} else {
    shuffle($media_files);
}

// Save the order to cookie for persistence
if (!empty($media_files)) {
    setcookie('tv_playlist_order', json_encode($media_files), time() + 86400 * 30, '/');
}

$taglines = [
    "Mag-isip bago pindot. Baka scam 'yan.",
    "Ang pag-click sa unknown link, pagsuko ng iyong datos.",
    "\"Pa-share naman ng OTP\"—red flag agad 'yan.",
    "Huwag magpa-budol sa fake discount. Cybercrime 'yan!",
    "Ang nagmamadaling panalo, kadalasan ay pagkatalo.",
    "Kapag masyadong maganda ang alok, isip-isip muna.",
    "One click, goodbye savings.",
    "Ang iyong password, hindi regalo—huwag ipamigay."
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>PNP ACG - Queue Display</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            background: #ffffff; 
            font-family: 'Segoe UI', 'Poppins', 'Roboto', sans-serif; 
            height: 100vh;
            width: 100vw;
            overflow: hidden;
            margin: 0;
            padding: 0;
        }
        .tv-container { 
            width: 100%; 
            height: 100vh;
            margin: 0; 
            padding: 12px;
            display: flex; 
            flex-direction: column; 
            gap: 10px;
            overflow: hidden;
        }
        .tv-header { 
            text-align: center; 
            padding: 8px 20px; 
            background: #f8f9fa; 
            border-radius: 16px; 
            border: 3px solid #c0c0c0;
            flex-shrink: 0;
        }
        .pnp-title { font-size: 0.9rem; font-weight: 500; letter-spacing: 2px; color: #16232e; margin-bottom: 2px; }
        .team-title { font-size: 1.3rem; font-weight: 500; letter-spacing: 1px; color: #16232e; }
        .qms-title { font-size: 0.65rem; color: #16232e; margin-top: 2px; }
        .announcement-bar { 
            background: #1a3b5c; 
            padding: 6px 20px; 
            border-radius: 40px; 
            overflow: hidden; 
            white-space: nowrap; 
            position: relative; 
            border: 2px solid #ffc107;
            flex-shrink: 0;
        }
        .announcement-bar span { 
            display: inline-block; 
            font-size: 0.8rem; 
            color: #ffffff; 
            font-weight: 500; 
            animation: marquee 45s linear infinite; 
            padding-left: 100%; 
        }
        @keyframes marquee { 0% { transform: translateX(0); } 100% { transform: translateX(-100%); } }
        .tv-main { 
            display: flex; 
            gap: 12px; 
            flex: 1;
            min-height: 0;
            overflow: hidden;
        }
        .media-section { 
            flex: 1; 
            background: #f5f5f5; 
            border-radius: 16px; 
            overflow: hidden; 
            border: 3px solid #c0c0c0; 
            display: flex; 
            flex-direction: column;
            min-height: 0;
        }
        .queue-section { 
            flex: 1; 
            display: flex; 
            flex-direction: column; 
            gap: 10px;
            min-height: 0;
        }
        .queue-top-row {
            display: flex;
            gap: 12px;
            justify-content: center;
            align-items: center;
            flex: 1;
            min-height: 0;
        }
        .queue-bottom-row {
            display: flex;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 0;
            padding-top: 0;
        }
        .queue-card { 
            background: #ffffff; 
            border-radius: 16px; 
            padding: 10px; 
            border: 3px solid #c0c0c0; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.05); 
            display: flex;
            flex-direction: column;
            overflow: hidden;
            width: 60%;
            min-width: 280px;
            max-width: 500px;
        }
        .queue-top-row .queue-card {
            margin: 0 auto;
        }
        .general-card { border-top: 6px solid #3b82f6; }
        .womens-card { border-top: 6px solid #ec4899; }
        .priority-card { border-top: 6px solid #ffc107; }
        .card-header { 
            text-align: center; 
            margin-bottom: 8px; 
            padding-bottom: 6px; 
            border-bottom: 2px solid #e0e0e0; 
            flex-shrink: 0;
        }
        .card-header h3 { font-size: 0.9rem; font-weight: 700; margin: 0; letter-spacing: 1px; }
        .general-card .card-header h3 { color: #3b82f6; }
        .womens-card .card-header h3 { color: #ec4899; }
        .priority-card .card-header h3 { color: #ffc107; }
        .currently-serving-box { 
            background: #f8f9fa; 
            border-radius: 16px; 
            padding: 10px; 
            text-align: center; 
            margin-bottom: 8px; 
            border: 2px solid #e0e0e0; 
            flex-shrink: 0;
        }
        .serving-number { 
            font-size: 3rem; 
            font-weight: 800; 
            font-family: 'Courier New', monospace; 
            line-height: 1.2; 
            margin: 5px 0; 
            letter-spacing: 2px; 
        }
        .serving-name { font-size: 0.75rem; color: #555; margin-bottom: 3px; font-weight: 500; }
        .general-card .serving-number { color: #3b82f6; }
        .womens-card .serving-number { color: #ec4899; }
        .priority-card .serving-number { color: #ffc107; }
        .stats-row { 
            display: flex; 
            gap: 8px; 
            flex-shrink: 0;
        }
        .stat-box-card { 
            flex: 1; 
            background: #f8f9fa; 
            border-radius: 12px; 
            padding: 6px; 
            text-align: center; 
            border: 2px solid #e0e0e0; 
        }
        .stat-label-card { font-size: 0.5rem; text-transform: uppercase; letter-spacing: 1px; color: #6c757d; margin-bottom: 3px; }
        .stat-value-card { font-size: 0.7rem; font-weight: 600; color: #333; word-break: break-word; line-height: 1.2; }
        .waiting-number { font-size: 1.2rem; font-weight: 700; color: #000000; }
        .media-container { 
            flex: 1; 
            position: relative; 
            background: #000; 
            display: flex; 
            align-items: center; 
            justify-content: center;
            min-height: 0;
        }
        #queueVideo, #queueImage { width: 100%; height: 100%; object-fit: contain; display: none; }
        .media-timer { 
            position: absolute; 
            bottom: 15px; 
            right: 15px; 
            background: rgba(0,0,0,0.7); 
            padding: 5px 12px; 
            border-radius: 20px; 
            font-size: 0.75rem; 
            font-family: monospace; 
            color: white; 
            z-index: 10; 
            border: 1px solid #ffc107; 
        }
        .media-progress { position: absolute; bottom: 0; left: 0; height: 4px; background: #ffc107; width: 0%; transition: width 0.1s linear; z-index: 10; }
        .media-controls { 
            display: flex; 
            justify-content: flex-end;
            align-items: center; 
            padding: 6px 12px; 
            background: #f8f9fa; 
            font-size: 0.65rem; 
            border-top: 3px solid #c0c0c0;
            flex-shrink: 0;
        }
        .media-indicators { display: flex; gap: 6px; }
        .media-indicators .indicator { width: 6px; height: 6px; border-radius: 50%; background: #ccc; transition: all 0.3s; }
        .media-indicators .indicator.active { background: #ffc107; transform: scale(1.3); }
        .waiting-message { 
            background: #e8f4fd; 
            padding: 5px 12px; 
            text-align: center; 
            font-size: 0.65rem; 
            color: #1a3b5c; 
            border-top: 3px solid #c0c0c0;
            flex-shrink: 0;
        }
        .tagline-flash { 
            background: #f0f7ff; 
            border: 3px solid #c0c0c0; 
            border-radius: 50px; 
            padding: 6px 20px; 
            text-align: center;
            flex-shrink: 0;
        }
        .tagline-text { font-size: 0.8rem; font-weight: bold; font-style: italic; color: #1a3b5c; animation: fadeSlide 1.5s ease-in-out; }
        @keyframes fadeSlide { 0% { opacity: 0; transform: translateY(8px); } 15% { opacity: 1; transform: translateY(0); } 85% { opacity: 1; transform: translateY(0); } 100% { opacity: 0; transform: translateY(-8px); } }
        .tv-footer { 
            text-align: center; 
            padding: 5px; 
            font-size: 0.6rem; 
            color: #6c757d; 
            border-top: 3px solid #c0c0c0;
            flex-shrink: 0;
        }
        .datetime-footer { font-size: 0.65rem; color: #1a3b5c; margin-bottom: 2px; font-weight: 500; }
        .empty-state { text-align: center; padding: 40px; color: #adb5bd; }
        .empty-state i { font-size: 3rem; margin-bottom: 15px; }
        
        .mute-indicator {
            position: absolute;
            bottom: 15px;
            left: 15px;
            background: rgba(0,0,0,0.7);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            color: #ffc107;
            z-index: 10;
            display: none;
            align-items: center;
            gap: 5px;
        }
        .mute-indicator.show {
            display: flex;
        }
        
        @media (max-width: 800px) {
            .tv-main { flex-direction: column; }
            .queue-top-row { flex-direction: column; align-items: center; }
            .queue-card { width: 90%; }
            .serving-number { font-size: 2.5rem; }
        }
        @media (max-width: 600px) {
            .serving-number { font-size: 2rem; }
            .team-title { font-size: 1rem; }
            .card-header h3 { font-size: 0.75rem; }
            .queue-card { width: 95%; }
        }
    </style>
</head>
<body>
    <div class="tv-container">
        <div class="tv-header">
            <div class="pnp-title">PNP – ACG</div>
            <div class="team-title">CAMARINES SUR CYBERCRIME RESPONSE TEAM</div>
            <div class="qms-title">QUEUING MANAGEMENT SYSTEM</div>
        </div>
        
        <div class="announcement-bar">
            <span><i class="bi bi-megaphone"></i> Please proceed to your assigned investigator when your number is called. Thank you for your cooperation. &nbsp;&nbsp;&nbsp; <i class="bi bi-dot"></i> &nbsp;&nbsp;&nbsp; General Cases - Blue Section &nbsp;&nbsp;&nbsp; <i class="bi bi-dot"></i> &nbsp;&nbsp;&nbsp; Women's Desk - Pink Section &nbsp;&nbsp;&nbsp; <i class="bi bi-dot"></i> &nbsp;&nbsp;&nbsp; Priority Lane - Yellow Section &nbsp;&nbsp;&nbsp; <i class="bi bi-megaphone"></i></span>
        </div>
        
        <div class="tv-main">
            <div class="media-section">
                <div class="media-container">
                    <?php if (!empty($media_files)): ?>
                    <video id="queueVideo" autoplay playsinline style="display: none;"><source src="" type="video/mp4" id="videoSource"></video>
                    <img id="queueImage" src="" alt="Display" style="display: none;">
                    <div class="media-timer" id="mediaTimer">00:00 / 00:00</div>
                    <div class="media-progress" id="mediaProgress"></div>
                    <div class="mute-indicator" id="muteIndicator">
                        <i class="bi bi-volume-mute-fill"></i> Announcement in progress
                    </div>
                    <?php else: ?>
                    <div class="empty-state" style="height: 100%; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                        <i class="bi bi-camera-video-off"></i>
                        <h6>No Media Available</h6>
                        <p class="small">Please upload videos or images in the Media Manager</p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($media_files)): ?>
                <div class="media-controls">
                    <div class="media-indicators" id="mediaIndicators"></div>
                </div>
                <?php endif; ?>
                <?php if ($has_pending): ?>
                <div class="waiting-message"><i class="bi bi-hourglass-split"></i> Please wait, you will be called soon. Enjoy this short presentation.</div>
                <?php endif; ?>
            </div>
            
            <div class="queue-section">
                <div class="queue-top-row">
                    <div class="queue-card general-card" id="generalCard">
                        <div class="card-header"><h3><i class="bi bi-briefcase"></i> GENERAL CASES</h3></div>
                        <div class="currently-serving-box" id="generalServingBox">
                            <div class="serving-label"><i class="bi bi-megaphone-fill"></i> NOW SERVING</div>
                            <div class="serving-number" id="generalQueueNumber" data-current-queue="<?php echo $general_current ? htmlspecialchars($general_current['queue_number']) : '---'; ?>"><?php echo $general_current ? htmlspecialchars($general_current['queue_number']) : '---'; ?></div>
                            <div class="serving-name" id="generalComplainant"><?php echo $general_current ? htmlspecialchars($general_current['complainant_name'] ?: 'Client') : 'No active call'; ?></div>
                        </div>
                        <div class="stats-row">
                            <div class="stat-box-card"><div class="stat-label-card"><i class="bi bi-clock-history"></i> WAITING</div><div class="waiting-number" id="generalWaiting"><?php echo $general_waiting; ?></div></div>
                            <div class="stat-box-card"><div class="stat-label-card"><i class="bi bi-person-badge"></i> INVESTIGATOR</div><div class="stat-value-card" id="generalInvestigator"><?php echo $general_current ? htmlspecialchars($general_current['assigned_investigator'] ?: 'Not Assigned') : ($general_waiting > 0 ? 'Will be assigned' : 'No Active Queue'); ?></div></div>
                        </div>
                    </div>
                    
                    <div class="queue-card womens-card" id="womensCard">
                        <div class="card-header"><h3><i class="bi bi-shield-heart"></i> WOMEN'S DESK</h3></div>
                        <div class="currently-serving-box" id="womensServingBox">
                            <div class="serving-label"><i class="bi bi-megaphone-fill"></i> NOW SERVING</div>
                            <div class="serving-number" id="womensQueueNumber" data-current-queue="<?php echo $womens_current ? htmlspecialchars($womens_current['queue_number']) : '---'; ?>"><?php echo $womens_current ? htmlspecialchars($womens_current['queue_number']) : '---'; ?></div>
                            <div class="serving-name" id="womensComplainant"><?php echo $womens_current ? htmlspecialchars($womens_current['complainant_name'] ?: 'Client') : 'No active call'; ?></div>
                        </div>
                        <div class="stats-row">
                            <div class="stat-box-card"><div class="stat-label-card"><i class="bi bi-clock-history"></i> WAITING</div><div class="waiting-number" id="womensWaiting"><?php echo $womens_waiting; ?></div></div>
                            <div class="stat-box-card"><div class="stat-label-card"><i class="bi bi-person-badge"></i> INVESTIGATOR</div><div class="stat-value-card" id="womensInvestigator"><?php echo $womens_current ? htmlspecialchars($womens_current['assigned_investigator'] ?: 'Not Assigned') : ($womens_waiting > 0 ? 'Will be assigned' : 'No Active Queue'); ?></div></div>
                        </div>
                    </div>
                </div>
                
                <div class="queue-bottom-row">
                    <div class="queue-card priority-card" id="priorityCard">
                        <div class="card-header"><h3><i class="bi bi-star-fill"></i> PRIORITY LANE</h3></div>
                        <div class="currently-serving-box" id="priorityServingBox">
                            <div class="serving-label"><i class="bi bi-megaphone-fill"></i> NOW SERVING</div>
                            <div class="serving-number" id="priorityQueueNumber" data-current-queue="<?php echo $priority_current ? htmlspecialchars($priority_current['queue_number']) : '---'; ?>"><?php echo $priority_current ? htmlspecialchars($priority_current['queue_number']) : '---'; ?></div>
                            <div class="serving-name" id="priorityComplainant"><?php echo $priority_current ? htmlspecialchars($priority_current['complainant_name'] ?: 'Client') : 'No active call'; ?></div>
                        </div>
                        <div class="stats-row">
                            <div class="stat-box-card"><div class="stat-label-card"><i class="bi bi-clock-history"></i> WAITING</div><div class="waiting-number" id="priorityWaiting"><?php echo $priority_waiting; ?></div></div>
                            <div class="stat-box-card"><div class="stat-label-card"><i class="bi bi-person-badge"></i> INVESTIGATOR</div><div class="stat-value-card" id="priorityInvestigator"><?php echo $priority_current ? htmlspecialchars($priority_current['assigned_investigator'] ?: 'Not Assigned') : ($priority_waiting > 0 ? 'Will be assigned' : 'No Active Queue'); ?></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="tagline-flash">
            <div class="tagline-text" id="rotatingTagline">Mag-isip bago pindot. Baka scam 'yan.</div>
        </div>
        
        <div class="tv-footer">
            <div class="datetime-footer" id="currentDateTime"></div>
            <div>© 2026 All Rights Reserved. PNP-ACG</div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js"></script>
    
    <script>
        const mediaFiles = <?php echo json_encode($media_files); ?>;
        const IMAGE_DISPLAY_DURATION = 60;
        const VIDEO_MAX_DURATION = 180;
        let currentMediaIndex = 0;
        let imageTimer = null;
        let videoCutoffTimer = null;
        let countdownInterval = null;
        let videoElement = document.getElementById('queueVideo');
        let imageElement = document.getElementById('queueImage');
        let videoSource = document.getElementById('videoSource');
        let mediaTimer = document.getElementById('mediaTimer');
        let mediaProgress = document.getElementById('mediaProgress');
        let muteIndicator = document.getElementById('muteIndicator');
        
        const taglines = <?php echo json_encode($taglines); ?>;
        let currentTaglineIndex = 0;
        
        let isMutedForAnnouncement = false;
        let announcementActive = false;
        
        function savePlaybackState() {
            if (mediaFiles.length === 0) return;
            const state = {
                mediaIndex: currentMediaIndex,
                timestamp: Date.now(),
                mediaType: mediaFiles[currentMediaIndex]?.type
            };
            if (videoElement && videoElement.style.display !== 'none' && videoElement.currentTime) {
                state.currentTime = videoElement.currentTime;
            }
            localStorage.setItem('tv_playback_state', JSON.stringify(state));
        }
        
        function loadPlaybackState() {
            const savedState = localStorage.getItem('tv_playback_state');
            if (!savedState) return null;
            try {
                const state = JSON.parse(savedState);
                if (Date.now() - state.timestamp < 300000) {
                    return state;
                }
            } catch(e) {}
            return null;
        }
        
        setInterval(savePlaybackState, 5000);
        
        function muteVideoForAnnouncement() {
            if (videoElement && videoElement.style.display !== 'none') {
                videoElement.muted = true;
                isMutedForAnnouncement = true;
                announcementActive = true;
                if (muteIndicator) muteIndicator.classList.add('show');
                console.log('Video muted for announcement');
                
                // Safety timeout - force unmute after 20 seconds if end signal never arrives
                setTimeout(function() {
                    if (announcementActive) {
                        console.log('Safety timeout: Forcing unmute');
                        videoElement.muted = false;
                        isMutedForAnnouncement = false;
                        announcementActive = false;
                        if (muteIndicator) muteIndicator.classList.remove('show');
                    }
                }, 20000);
            }
        }
        
        function unmuteVideoAfterAnnouncement() {
            if (videoElement && videoElement.style.display !== 'none' && isMutedForAnnouncement) {
                // Add a small delay to ensure announcement audio is completely finished
                setTimeout(function() {
                    if (announcementActive) {
                        videoElement.muted = false;
                        isMutedForAnnouncement = false;
                        announcementActive = false;
                        if (muteIndicator) muteIndicator.classList.remove('show');
                        console.log('Video unmuted after announcement delay');
                    }
                }, 1500);
            }
        }
        
        function handleAnnouncementStart() {
            console.log('Announcement START signal received - muting video');
            muteVideoForAnnouncement();
        }
        
        function handleAnnouncementEnd() {
            console.log('Announcement END signal received - will unmute after delay');
            unmuteVideoAfterAnnouncement();
        }
        
        function checkForMuteSignal() {
            const signalData = localStorage.getItem('tv_mute_signal');
            if (signalData) {
                try {
                    const signal = JSON.parse(signalData);
                    if (signal.type === 'ANNOUNCEMENT_START') {
                        if (Date.now() - signal.timestamp < 3000) {
                            handleAnnouncementStart();
                        }
                        localStorage.removeItem('tv_mute_signal');
                    } else if (signal.type === 'ANNOUNCEMENT_END') {
                        if (Date.now() - signal.timestamp < 3000) {
                            handleAnnouncementEnd();
                        }
                        localStorage.removeItem('tv_mute_signal');
                    }
                } catch(e) {}
            }
        }
        
        setInterval(checkForMuteSignal, 300);
        
        window.addEventListener('tv_announcement', function(event) {
            if (event.detail && event.detail.type === 'ANNOUNCEMENT_START') {
                handleAnnouncementStart();
            } else if (event.detail && event.detail.type === 'ANNOUNCEMENT_END') {
                handleAnnouncementEnd();
            }
        });
        
        function formatTime(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        }
        
        function showMedia(index, restoreTime = null) {
            if (mediaFiles.length === 0) return;
            if (imageTimer) clearTimeout(imageTimer);
            if (videoCutoffTimer) clearTimeout(videoCutoffTimer);
            if (countdownInterval) clearInterval(countdownInterval);
            
            const media = mediaFiles[index];
            
            if (media.type === 'video') {
                videoElement.style.display = 'block';
                imageElement.style.display = 'none';
                videoSource.src = media.path;
                videoElement.load();
                
                if (restoreTime !== null && restoreTime > 0) {
                    videoElement.addEventListener('loadedmetadata', function onLoaded() {
                        videoElement.currentTime = Math.min(restoreTime, videoElement.duration - 1);
                        videoElement.removeEventListener('loadedmetadata', onLoaded);
                    });
                }
                
                videoElement.play().catch(e => console.log('Autoplay prevented:', e));
                
                videoCutoffTimer = setTimeout(() => {
                    if (videoElement && !videoElement.paused) {
                        console.log('Video reached 3 minute limit, moving to next media');
                        nextMedia();
                    }
                }, VIDEO_MAX_DURATION * 1000);
                
                updateMediaIndicators(index);
            } else if (media.type === 'image') {
                videoElement.style.display = 'none';
                imageElement.style.display = 'block';
                imageElement.src = media.path;
                
                if (mediaTimer) mediaTimer.textContent = formatTime(0) + ' / ' + formatTime(IMAGE_DISPLAY_DURATION);
                if (mediaProgress) mediaProgress.style.width = '0%';
                
                updateMediaIndicators(index);
                startImageCountdown();
                imageTimer = setTimeout(() => nextMedia(), IMAGE_DISPLAY_DURATION * 1000);
            }
        }
        
        function startImageCountdown() {
            let elapsed = 0;
            if (countdownInterval) clearInterval(countdownInterval);
            countdownInterval = setInterval(() => {
                elapsed++;
                if (elapsed <= IMAGE_DISPLAY_DURATION) {
                    const progressPercent = (elapsed / IMAGE_DISPLAY_DURATION) * 100;
                    if (mediaProgress) mediaProgress.style.width = progressPercent + '%';
                    if (mediaTimer) mediaTimer.textContent = formatTime(elapsed) + ' / ' + formatTime(IMAGE_DISPLAY_DURATION);
                }
            }, 1000);
        }
        
        function updateVideoProgress() {
            if (videoElement && videoElement.duration && videoElement.style.display !== 'none') {
                const currentTime = videoElement.currentTime;
                const duration = videoElement.duration;
                const progress = (currentTime / duration) * 100;
                if (mediaProgress) mediaProgress.style.width = progress + '%';
                if (mediaTimer) mediaTimer.textContent = formatTime(currentTime) + ' / ' + formatTime(duration);
            }
        }
        
        function onVideoEnd() { 
            if (videoCutoffTimer) clearTimeout(videoCutoffTimer);
            nextMedia(); 
        }
        
        function nextMedia() { 
            currentMediaIndex = (currentMediaIndex + 1) % mediaFiles.length; 
            showMedia(currentMediaIndex);
            savePlaybackState();
        }
        
        function updateMediaIndicators(currentIndex) {
            const indicatorsContainer = document.getElementById('mediaIndicators');
            if (!indicatorsContainer || mediaFiles.length === 0) return;
            let html = '';
            for (let i = 0; i < Math.min(mediaFiles.length, 8); i++) {
                html += `<span class="indicator ${i === currentIndex ? 'active' : ''}"></span>`;
            }
            indicatorsContainer.innerHTML = html;
        }
        
        // FIXED: updateTVDisplay - Only clear if queue numbers match
        function updateTVDisplay(data) {
            if (data.type === 'QUEUE_UPDATE') {
                const update = data.data;
                console.log('Received instant update for category:', update.category, 'isPriority:', update.isPriority, 'queueNumber:', update.queueNumber);
                
                if (update.isPriority) {
                    const queueElement = document.getElementById('priorityQueueNumber');
                    const nameElement = document.getElementById('priorityComplainant');
                    const investigatorElement = document.getElementById('priorityInvestigator');
                    
                    if (queueElement && update.queueNumber) {
                        queueElement.textContent = update.queueNumber;
                        queueElement.setAttribute('data-current-queue', update.queueNumber);
                        if (nameElement) nameElement.textContent = update.complainantName || 'Client';
                        if (investigatorElement) investigatorElement.textContent = update.investigator;
                    }
                } else if (update.category === 'womens_desk') {
                    const queueElement = document.getElementById('womensQueueNumber');
                    const nameElement = document.getElementById('womensComplainant');
                    const investigatorElement = document.getElementById('womensInvestigator');
                    
                    if (queueElement && update.queueNumber) {
                        queueElement.textContent = update.queueNumber;
                        queueElement.setAttribute('data-current-queue', update.queueNumber);
                        if (nameElement) nameElement.textContent = update.complainantName || 'Client';
                        if (investigatorElement) investigatorElement.textContent = update.investigator;
                    }
                } else {
                    const queueElement = document.getElementById('generalQueueNumber');
                    const nameElement = document.getElementById('generalComplainant');
                    const investigatorElement = document.getElementById('generalInvestigator');
                    
                    if (queueElement && update.queueNumber) {
                        queueElement.textContent = update.queueNumber;
                        queueElement.setAttribute('data-current-queue', update.queueNumber);
                        if (nameElement) nameElement.textContent = update.complainantName || 'Client';
                        if (investigatorElement) investigatorElement.textContent = update.investigator;
                    }
                }
            } 
            else if (data.type === 'CLEAR_SERVING') {
                console.log('Received clear signal for:', data.category, 'QueueNumber:', data.queueNumber);
                
                let currentQueueElement = null;
                let currentDisplayedQueue = null;
                
                if (data.category === 'general') {
                    currentQueueElement = document.getElementById('generalQueueNumber');
                    if (currentQueueElement) {
                        currentDisplayedQueue = currentQueueElement.textContent;
                        const storedQueue = currentQueueElement.getAttribute('data-current-queue');
                        if (storedQueue && storedQueue !== '---') {
                            currentDisplayedQueue = storedQueue;
                        }
                    }
                    
                    if (currentDisplayedQueue === data.queueNumber) {
                        console.log('Match found! Clearing general serving box for queue:', data.queueNumber);
                        const queueElement = document.getElementById('generalQueueNumber');
                        const nameElement = document.getElementById('generalComplainant');
                        const investigatorElement = document.getElementById('generalInvestigator');
                        if (queueElement) {
                            queueElement.textContent = '---';
                            queueElement.setAttribute('data-current-queue', '---');
                        }
                        if (nameElement) nameElement.textContent = 'No active call';
                        if (investigatorElement) investigatorElement.textContent = 'No Active Queue';
                    } else {
                        console.log('No match. Current displayed:', currentDisplayedQueue, 'Catered:', data.queueNumber, '- Keeping display');
                    }
                } 
                else if (data.category === 'womens') {
                    currentQueueElement = document.getElementById('womensQueueNumber');
                    if (currentQueueElement) {
                        currentDisplayedQueue = currentQueueElement.textContent;
                        const storedQueue = currentQueueElement.getAttribute('data-current-queue');
                        if (storedQueue && storedQueue !== '---') {
                            currentDisplayedQueue = storedQueue;
                        }
                    }
                    
                    if (currentDisplayedQueue === data.queueNumber) {
                        console.log('Match found! Clearing womens serving box for queue:', data.queueNumber);
                        const queueElement = document.getElementById('womensQueueNumber');
                        const nameElement = document.getElementById('womensComplainant');
                        const investigatorElement = document.getElementById('womensInvestigator');
                        if (queueElement) {
                            queueElement.textContent = '---';
                            queueElement.setAttribute('data-current-queue', '---');
                        }
                        if (nameElement) nameElement.textContent = 'No active call';
                        if (investigatorElement) investigatorElement.textContent = 'No Active Queue';
                    } else {
                        console.log('No match. Current displayed:', currentDisplayedQueue, 'Catered:', data.queueNumber, '- Keeping display');
                    }
                } 
                else if (data.category === 'priority') {
                    currentQueueElement = document.getElementById('priorityQueueNumber');
                    if (currentQueueElement) {
                        currentDisplayedQueue = currentQueueElement.textContent;
                        const storedQueue = currentQueueElement.getAttribute('data-current-queue');
                        if (storedQueue && storedQueue !== '---') {
                            currentDisplayedQueue = storedQueue;
                        }
                    }
                    
                    if (currentDisplayedQueue === data.queueNumber) {
                        console.log('Match found! Clearing priority serving box for queue:', data.queueNumber);
                        const queueElement = document.getElementById('priorityQueueNumber');
                        const nameElement = document.getElementById('priorityComplainant');
                        const investigatorElement = document.getElementById('priorityInvestigator');
                        if (queueElement) {
                            queueElement.textContent = '---';
                            queueElement.setAttribute('data-current-queue', '---');
                        }
                        if (nameElement) nameElement.textContent = 'No active call';
                        if (investigatorElement) investigatorElement.textContent = 'No Active Queue';
                    } else {
                        console.log('No match. Current displayed:', currentDisplayedQueue, 'Catered:', data.queueNumber, '- Keeping display');
                    }
                }
            }
        }
        
        // FIXED: restoreSavedServingData with data-current-queue attribute
        function restoreSavedServingData() {
            try {
                const savedData = localStorage.getItem('tv_serving_data');
                if (savedData) {
                    const servingData = JSON.parse(savedData);
                    if (servingData.general && servingData.general.queueNumber) {
                        const queueElement = document.getElementById('generalQueueNumber');
                        if (queueElement && queueElement.textContent !== servingData.general.queueNumber) {
                            queueElement.textContent = servingData.general.queueNumber;
                            queueElement.setAttribute('data-current-queue', servingData.general.queueNumber);
                            document.getElementById('generalComplainant').textContent = servingData.general.complainantName || 'Client';
                            document.getElementById('generalInvestigator').textContent = servingData.general.investigator;
                        }
                    }
                    if (servingData.womens && servingData.womens.queueNumber) {
                        const queueElement = document.getElementById('womensQueueNumber');
                        if (queueElement && queueElement.textContent !== servingData.womens.queueNumber) {
                            queueElement.textContent = servingData.womens.queueNumber;
                            queueElement.setAttribute('data-current-queue', servingData.womens.queueNumber);
                            document.getElementById('womensComplainant').textContent = servingData.womens.complainantName || 'Client';
                            document.getElementById('womensInvestigator').textContent = servingData.womens.investigator;
                        }
                    }
                    if (servingData.priority && servingData.priority.queueNumber) {
                        const queueElement = document.getElementById('priorityQueueNumber');
                        if (queueElement && queueElement.textContent !== servingData.priority.queueNumber) {
                            queueElement.textContent = servingData.priority.queueNumber;
                            queueElement.setAttribute('data-current-queue', servingData.priority.queueNumber);
                            document.getElementById('priorityComplainant').textContent = servingData.priority.complainantName || 'Client';
                            document.getElementById('priorityInvestigator').textContent = servingData.priority.investigator;
                        }
                    }
                }
            } catch(e) { console.log('Restore saved data error:', e); }
        }
        
        window.addEventListener('storage', function(e) {
            if (e.key && e.key.startsWith('queue_update_')) {
                try {
                    const data = JSON.parse(e.newValue);
                    updateTVDisplay(data);
                } catch(e) { console.log('Parse error:', e); }
            }
            if (e.key === 'tv_serving_update') {
                try {
                    const servingData = JSON.parse(e.newValue);
                    if (servingData.general && servingData.general.queueNumber) {
                        const queueElement = document.getElementById('generalQueueNumber');
                        if (queueElement && queueElement.textContent !== servingData.general.queueNumber) {
                            queueElement.textContent = servingData.general.queueNumber;
                            queueElement.setAttribute('data-current-queue', servingData.general.queueNumber);
                            document.getElementById('generalComplainant').textContent = servingData.general.complainantName || 'Client';
                            document.getElementById('generalInvestigator').textContent = servingData.general.investigator;
                        }
                    }
                    if (servingData.womens && servingData.womens.queueNumber) {
                        const queueElement = document.getElementById('womensQueueNumber');
                        if (queueElement && queueElement.textContent !== servingData.womens.queueNumber) {
                            queueElement.textContent = servingData.womens.queueNumber;
                            queueElement.setAttribute('data-current-queue', servingData.womens.queueNumber);
                            document.getElementById('womensComplainant').textContent = servingData.womens.complainantName || 'Client';
                            document.getElementById('womensInvestigator').textContent = servingData.womens.investigator;
                        }
                    }
                    if (servingData.priority && servingData.priority.queueNumber) {
                        const queueElement = document.getElementById('priorityQueueNumber');
                        if (queueElement && queueElement.textContent !== servingData.priority.queueNumber) {
                            queueElement.textContent = servingData.priority.queueNumber;
                            queueElement.setAttribute('data-current-queue', servingData.priority.queueNumber);
                            document.getElementById('priorityComplainant').textContent = servingData.priority.complainantName || 'Client';
                            document.getElementById('priorityInvestigator').textContent = servingData.priority.investigator;
                        }
                    }
                } catch(e) { console.log('Parse error:', e); }
            }
            if (e.key && e.key.startsWith('tv_clear_signal_')) {
                try {
                    const clearSignal = JSON.parse(e.newValue);
                    if (clearSignal && clearSignal.type === 'CLEAR_SERVING') {
                        updateTVDisplay(clearSignal);
                    }
                } catch(e) { console.log('Clear signal parse error:', e); }
            }
        });
        
        if (typeof BroadcastChannel !== 'undefined') {
            try {
                const channel = new BroadcastChannel('queue_updates');
                channel.onmessage = function(event) {
                    const data = event.data;
                    if (data && data.type === 'ANNOUNCEMENT_START') {
                        handleAnnouncementStart();
                    } else if (data && data.type === 'ANNOUNCEMENT_END') {
                        handleAnnouncementEnd();
                    } else {
                        updateTVDisplay(data);
                    }
                };
                const queueChannel = new BroadcastChannel('queue_channel');
                queueChannel.onmessage = function(event) {
                    const data = event.data;
                    if (data && data.type === 'QUEUE_UPDATE') {
                        updateTVDisplay(data);
                    } else if (data && data.type === 'ANNOUNCEMENT_START') {
                        handleAnnouncementStart();
                    } else if (data && data.type === 'ANNOUNCEMENT_END') {
                        handleAnnouncementEnd();
                    } else if (data && data.type === 'CLEAR_SERVING') {
                        updateTVDisplay(data);
                    }
                };
            } catch(e) { console.log('BroadcastChannel not supported'); }
        }
        
        function rotateTagline() {
            const taglineElement = document.getElementById('rotatingTagline');
            if (taglineElement && taglines.length > 0) {
                currentTaglineIndex = (currentTaglineIndex + 1) % taglines.length;
                taglineElement.style.animation = 'none';
                setTimeout(() => {
                    taglineElement.textContent = taglines[currentTaglineIndex];
                    taglineElement.style.animation = 'fadeSlide 1.5s ease-in-out';
                }, 50);
            }
        }
        
        function updateDateTime() {
            const now = new Date();
            const options = { month: 'long', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' };
            const dateTimeElement = document.getElementById('currentDateTime');
            if (dateTimeElement) dateTimeElement.innerHTML = now.toLocaleDateString('en-US', options);
        }
        
        if (mediaFiles.length > 0) {
            const savedState = loadPlaybackState();
            if (savedState && savedState.mediaIndex !== undefined && savedState.mediaIndex < mediaFiles.length) {
                currentMediaIndex = savedState.mediaIndex;
                if (savedState.mediaType === 'video' && savedState.currentTime) {
                    showMedia(currentMediaIndex, savedState.currentTime);
                } else {
                    showMedia(currentMediaIndex);
                }
            } else {
                showMedia(0);
            }
            
            videoElement.addEventListener('timeupdate', updateVideoProgress);
            videoElement.addEventListener('ended', onVideoEnd);
            videoElement.addEventListener('loadedmetadata', function() {
                if (mediaTimer && this.duration) mediaTimer.textContent = '00:00 / ' + formatTime(this.duration);
            });
        }
        
        setInterval(updateDateTime, 1000);
        updateDateTime();
        if (taglines.length > 0) setInterval(rotateTagline, 6000);
        
        restoreSavedServingData();
        
        setInterval(function() {
            fetch(window.location.href + '?get_counts=1&t=' + Date.now())
                .then(response => response.json())
                .then(data => {
                    if (data.general !== undefined) document.getElementById('generalWaiting').textContent = data.general;
                    if (data.womens !== undefined) document.getElementById('womensWaiting').textContent = data.womens;
                    if (data.priority !== undefined) document.getElementById('priorityWaiting').textContent = data.priority;
                })
                .catch(e => console.log('Count fetch error:', e));
        }, 10000);
        
        window.addEventListener('beforeunload', function() {
            savePlaybackState();
        });
    </script>
</body>
</html>