<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

// Then include your existing code...
require_once 'config.php';



// Create directories if they don't exist
$media_dir = 'media/';
$video_dir = $media_dir . 'videos/';
$image_dir = $media_dir . 'images/';

if (!is_dir($media_dir)) {
    mkdir($media_dir, 0777, true);
}
if (!is_dir($video_dir)) {
    mkdir($video_dir, 0777, true);
}
if (!is_dir($image_dir)) {
    mkdir($image_dir, 0777, true);
}

// Handle media upload
$upload_message = '';
$upload_status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['media_file'])) {
    $file = $_FILES['media_file'];
    $file_name = basename($file['name']);
    $file_type = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Allowed file types
    $allowed_video = ['mp4', 'webm', 'ogg', 'mov', 'avi'];
    $allowed_images = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
    
    if (in_array($file_type, $allowed_video)) {
        // It's a video
        $target_file = $video_dir . $file_name;
        
        if ($file['size'] <= 100 * 1024 * 1024) { // 100MB for videos
            if (move_uploaded_file($file['tmp_name'], $target_file)) {
                $upload_status = 'success';
                $upload_message = 'Video uploaded successfully!';
            } else {
                $upload_status = 'error';
                $upload_message = 'Error uploading video.';
            }
        } else {
            $upload_status = 'error';
            $upload_message = 'Video too large. Max 100MB.';
        }
    } elseif (in_array($file_type, $allowed_images)) {
        // It's an image
        $target_file = $image_dir . $file_name;
        
        if ($file['size'] <= 10 * 1024 * 1024) { // 10MB for images
            if (move_uploaded_file($file['tmp_name'], $target_file)) {
                $upload_status = 'success';
                $upload_message = 'Image uploaded successfully!';
            } else {
                $upload_status = 'error';
                $upload_message = 'Error uploading image.';
            }
        } else {
            $upload_status = 'error';
            $upload_message = 'Image too large. Max 10MB.';
        }
    } else {
        $upload_status = 'error';
        $upload_message = 'Invalid file type. Allowed: MP4, WebM, MOV, AVI, JPG, PNG, GIF';
    }
}

// Handle media deletion
if (isset($_GET['delete'])) {
    $delete_file = basename($_GET['delete']);
    $delete_type = isset($_GET['type']) ? $_GET['type'] : '';
    
    if ($delete_type == 'video') {
        $file_path = $video_dir . $delete_file;
        if (file_exists($file_path)) {
            unlink($file_path);
            $upload_status = 'success';
            $upload_message = 'Video deleted successfully!';
        }
    } elseif ($delete_type == 'image') {
        $file_path = $image_dir . $delete_file;
        if (file_exists($file_path)) {
            unlink($file_path);
            $upload_status = 'success';
            $upload_message = 'Image deleted successfully!';
        }
    }
}

// Get list of videos
$video_files = [];
if (is_dir($video_dir)) {
    $files = scandir($video_dir);
    foreach ($files as $file) {
        if (preg_match('/\.(mp4|webm|ogg|mov|avi)$/i', $file)) {
            $video_files[] = $file;
        }
    }
}

// Get list of images
$image_files = [];
if (is_dir($image_dir)) {
    $files = scandir($image_dir);
    foreach ($files as $file) {
        if (preg_match('/\.(jpg|jpeg|png|gif|bmp|webp)$/i', $file)) {
            $image_files[] = $file;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Media Manager - Cybercrime System</title>
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
        }
        .sidebar a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
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
            min-height: 100vh;
        }
        .media-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .media-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .media-preview {
            width: 100%;
            height: 150px;
            background: #f0f0f0;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 10px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .media-preview video, .media-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .media-type-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.6);
            color: white;
            padding: 4px 8px;
            border-radius: 5px;
            font-size: 11px;
            z-index: 5;
        }
        .media-info {
            padding: 10px 0;
        }
        .media-name {
            font-weight: 600;
            margin-bottom: 5px;
            word-break: break-all;
            font-size: 13px;
        }
        .media-meta {
            font-size: 11px;
            color: #6c757d;
        }
        .upload-area {
            border: 3px dashed #dee2e6;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 30px;
        }
        .upload-area:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }
        .upload-area i {
            font-size: 48px;
            color: #667eea;
            margin-bottom: 15px;
        }
        .btn-delete {
            color: #dc3545;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-delete:hover {
            color: #bd2130;
            transform: scale(1.1);
        }
        .nav-tabs .nav-link {
            color: #495057;
        }
        .nav-tabs .nav-link.active {
            font-weight: 600;
            color: #4d86c7;
            border-bottom-color: #4d86c7;
        }
        .tab-content {
            padding-top: 20px;
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
                        <img src="videos/uploads/cyberlogo.png" alt="ACG NAGA Logo" class="logo-img" onerror="this.src='https://via.placeholder.com/100?text=PNP'">
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
                        <span><i class="bi bi-camera-reels"></i> Media Manager</span>
                        <i class="bi bi-chevron-down"></i>
                    </a>
                    <div class="collapse show" id="videoSubmenu">
                        <a href="video_manager.php" class="active"><i class="bi bi-upload"></i> Upload Media</a>
                        <a href="tv_display.php" target="_blank"><i class="bi bi-play-circle"></i> View Display</a>
                    </div>
                    <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
                </nav>
                <div class="system-badge"><i class="bi bi-shield-check"></i> PNP ACG · v2.0</div>
            </div>
            
            <!-- Main Content -->
            <div class="main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-camera-reels"></i> Media Manager</h2>
                    <a href="tv_display.php" target="_blank" class="btn btn-danger">
                        <i class="bi bi-tv"></i> View TV Display
                    </a>
                </div>
                
                <!-- Upload Status Message -->
                <?php if ($upload_message): ?>
                <div class="alert alert-<?php echo $upload_status == 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                    <?php echo $upload_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <!-- Upload Area -->
                <form action="video_manager.php" method="POST" enctype="multipart/form-data" id="uploadForm">
                    <div class="upload-area" onclick="document.getElementById('media_file').click()">
                        <i class="bi bi-cloud-upload"></i>
                        <h4>Click or Drag to Upload Media</h4>
                        <p>Support: Videos (MP4, WebM, MOV, AVI up to 100MB) | Images (JPG, PNG, GIF up to 10MB)</p>
                        <input type="file" name="media_file" id="media_file" accept="video/*,image/*" style="display: none;" onchange="document.getElementById('uploadForm').submit()">
                    </div>
                </form>
                
                <!-- Tabs for Videos and Images -->
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#videos">
                            <i class="bi bi-camera-reels"></i> Videos <span class="badge bg-secondary ms-1"><?php echo count($video_files); ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#images">
                            <i class="bi bi-image"></i> Images <span class="badge bg-secondary ms-1"><?php echo count($image_files); ?></span>
                        </a>
                    </li>
                </ul>
                
                <div class="tab-content">
                    <!-- VIDEOS TAB -->
                    <div class="tab-pane fade show active" id="videos">
                        <?php if (empty($video_files)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-camera-video-off" style="font-size: 48px; color: #ccc;"></i>
                            <h4 class="mt-3">No Videos Uploaded</h4>
                            <p class="text-muted">Click the upload area above to add videos</p>
                        </div>
                        <?php else: ?>
                        <div class="row mt-3">
                            <?php foreach ($video_files as $index => $video): 
                                $video_path = $video_dir . $video;
                                $file_size = filesize($video_path);
                                $file_size_formatted = round($file_size / (1024 * 1024), 2) . ' MB';
                                $file_date = date("F d, Y H:i", filemtime($video_path));
                            ?>
                            <div class="col-md-3">
                                <div class="media-card">
                                    <div class="media-preview">
                                        <video src="<?php echo $video_dir . $video; ?>" preload="metadata"></video>
                                        <span class="media-type-badge"><i class="bi bi-camera-reels"></i> VIDEO</span>
                                    </div>
                                    <div class="media-info">
                                        <div class="media-name"><?php echo htmlspecialchars($video); ?></div>
                                        <div class="media-meta">
                                            <i class="bi bi-file-earmark"></i> <?php echo $file_size_formatted; ?><br>
                                            <i class="bi bi-calendar"></i> <?php echo $file_date; ?>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <span class="badge bg-info">#<?php echo $index + 1; ?></span>
                                        <div>
                                            <a href="tv_display.php?preview=<?php echo urlencode($video); ?>&type=video" target="_blank" class="btn btn-sm btn-outline-primary me-2" title="Preview">
                                                <i class="bi bi-play-circle"></i>
                                            </a>
                                            <a href="?delete=<?php echo urlencode($video); ?>&type=video" class="btn-delete" onclick="return confirm('Delete this video?')" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- IMAGES TAB -->
                    <div class="tab-pane fade" id="images">
                        <?php if (empty($image_files)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-image" style="font-size: 48px; color: #ccc;"></i>
                            <h4 class="mt-3">No Images Uploaded</h4>
                            <p class="text-muted">Click the upload area above to add images (JPG, PNG, GIF)</p>
                        </div>
                        <?php else: ?>
                        <div class="row mt-3">
                            <?php foreach ($image_files as $index => $image): 
                                $image_path = $image_dir . $image;
                                $file_size = filesize($image_path);
                                $file_size_formatted = round($file_size / 1024, 2) . ' KB';
                                $file_date = date("F d, Y H:i", filemtime($image_path));
                            ?>
                            <div class="col-md-3">
                                <div class="media-card">
                                    <div class="media-preview">
                                        <img src="<?php echo $image_dir . $image; ?>" alt="<?php echo htmlspecialchars($image); ?>">
                                        <span class="media-type-badge"><i class="bi bi-image"></i> IMAGE</span>
                                    </div>
                                    <div class="media-info">
                                        <div class="media-name"><?php echo htmlspecialchars($image); ?></div>
                                        <div class="media-meta">
                                            <i class="bi bi-file-earmark"></i> <?php echo $file_size_formatted; ?><br>
                                            <i class="bi bi-calendar"></i> <?php echo $file_date; ?>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <span class="badge bg-info">#<?php echo $index + 1; ?></span>
                                        <div>
                                            <a href="tv_display.php?preview=<?php echo urlencode($image); ?>&type=image" target="_blank" class="btn btn-sm btn-outline-primary me-2" title="Preview">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="?delete=<?php echo urlencode($image); ?>&type=image" class="btn-delete" onclick="return confirm('Delete this image?')" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Media Settings - Image Display Duration Removed -->
                <div class="card mt-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-gear"></i> Display Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Playback Order</label>
                                    <select class="form-control" id="playback_order">
                                        <option value="sequential" selected>Sequential</option>
                                        <option value="random">Random</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Transition Effect</label>
                                    <select class="form-control" id="transition_effect">
                                        <option value="fade" selected>Fade</option>
                                        <option value="slide">Slide</option>
                                        <option value="none">None</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <button class="btn btn-primary" onclick="saveSettings()">Save Settings</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function saveSettings() {
        const playbackOrder = document.getElementById('playback_order').value;
        const transitionEffect = document.getElementById('transition_effect').value;
        
        localStorage.setItem('playback_order', playbackOrder);
        localStorage.setItem('transition_effect', transitionEffect);
        
        alert('Settings saved!');
    }
    
    // Load saved settings
    function loadSettings() {
        const savedOrder = localStorage.getItem('playback_order');
        const savedTransition = localStorage.getItem('transition_effect');
        
        if (savedOrder) {
            const select = document.getElementById('playback_order');
            if (select) select.value = savedOrder;
        }
        if (savedTransition) {
            const select = document.getElementById('transition_effect');
            if (select) select.value = savedTransition;
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        loadSettings();
    });
    
    // Drag and drop upload
    const uploadArea = document.querySelector('.upload-area');
    
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        uploadArea.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    ['dragenter', 'dragover'].forEach(eventName => {
        uploadArea.addEventListener(eventName, highlight, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        uploadArea.addEventListener(eventName, unhighlight, false);
    });
    
    function highlight() {
        uploadArea.style.borderColor = '#667eea';
        uploadArea.style.background = '#f8f9ff';
    }
    
    function unhighlight() {
        uploadArea.style.borderColor = '#dee2e6';
        uploadArea.style.background = 'white';
    }
    
    uploadArea.addEventListener('drop', handleDrop, false);
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        
        if (files.length > 0) {
            document.getElementById('media_file').files = files;
            document.getElementById('uploadForm').submit();
        }
    }
    </script>
</body>
</html>