<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

// Then include your existing code...
require_once 'config.php';


// REMOVED: All password configuration and validation
// Now just directly redirect to complaint form when a category is selected

// Initialize variables
$message = '';

// Handle category selection - DIRECT ACCESS WITHOUT PASSWORD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_category'])) {
    $selected_category = $_POST['select_category'];
    
    // Validate category
    $valid_categories = ['general_cases', 'womens_desk', 'visit'];
    if (in_array($selected_category, $valid_categories)) {
        // REMOVED: Password check - Direct access granted
        redirect("complaint_form.php?category={$selected_category}");
    } else {
        $message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill"></i> 
                        <strong>Error!</strong> Invalid category selected.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
    }
}

// REMOVED: Session authorization checks
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Category - Cybercrime System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #0B1E33 0%, #1a3b5c 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .category-container {
            text-align: center;
            padding: 20px;
        }
        .category-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            margin: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            width: 320px;
            display: inline-block;
            position: relative;
            overflow: hidden;
        }
        .category-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: var(--category-color);
        }
        .category-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 25px 45px rgba(0,0,0,0.3);
        }
        .category-card i {
            font-size: 40px;
            margin-bottom: 20px;
            transition: transform 0.3s;
        }
        .category-card:hover i {
            transform: scale(1.1);
        }
        .category-card h3 {
            color: #333;
            margin-bottom: 15px;
            font-weight: 600;
        }
        .category-card p {
            color: #666;
            margin-bottom: 0;
        }
        .general-cases {
            --category-color: #4299e1;
        }
        .general-cases i { color: #4299e1; }
        .womens-desk {
            --category-color: #ed64a6;
        }
        .womens-desk i { color: #ed64a6; }
        .visit-case {
            --category-color: #38b2ac;
        }
        .visit-case i, .visit-case img {
            color: #38b2ac;
        }
        
        h1 {
            color: white;
            margin-bottom: 40px;
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        .btn-light {
            border-radius: 50px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-light:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        /* REMOVED: Password modal styles */
        
        .security-note {
            background: #f8f9fa;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin-top: 30px;
            border-radius: 10px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .category-card {
            animation: fadeIn 0.5s ease-out;
        }
        
        @media (max-width: 768px) {
            .category-card {
                width: 280px;
                padding: 30px;
                margin: 15px;
            }
            .category-card i {
                font-size: 48px;
            }
            h1 {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="category-container">
        <h1><i class=""></i> Select Complaint Category</h1>
        
        <?php echo $message; ?>
        
        <form method="POST" action="" id="categoryForm">
            <div class="category-card general-cases" onclick="submitCategory('general_cases')">
                <img src="icon3.png" 
                     alt="Queuing Management System Icon"
                     style="width: 90px; height: 90px; margin-bottom: 8px;">
                <h3>General Cases</h3>
                <p>All cybercrime complaints and cases</p>
                <!-- REMOVED: Password badge -->
            </div>
            
            <div class="category-card womens-desk" onclick="location.href='womens_desk_login.php'">
                <img src="icon2.png" 
                     alt="Queuing Management System Icon"
                     style="width: 90px; height: 90px; margin-bottom: 8px;">
                <h3>Women's Desk</h3>
                <p>Cases involving women and children</p>
                <small class="text-muted">Login required before access</small>
            </div>

            <div class="category-card visit-case" onclick="submitCategory('visit')">
                <img src="icon1.webp" 
                     alt="Visit Icon"
                     style="width: 90px; height: 90px; margin-bottom: 8px;">
                <h3>Visit</h3>
                <p>Visit-only queue for client appointments</p>
            </div>
            <input type="hidden" name="select_category" id="selectedCategory">
        </form>
        
        <div class="security-note">
            <i class="bi bi-info-circle-fill text-success"></i>
            <strong>Access Notice:</strong> Select a category to proceed with filing a complaint.
            <br><small class="text-muted mt-2 d-block">
                <i class="bi bi-clock"></i> All complaints will be processed and assigned to appropriate investigators
            </small>
        </div>
        
        <div style="margin-top: 30px;">
            <a href="dashboard.php" class="btn btn-light">
                <i class="bi bi-speedometer2"></i> Go to Dashboard
            </a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to submit category directly without password
        function submitCategory(category) {
            document.getElementById('selectedCategory').value = category;
            document.getElementById('categoryForm').submit();
        }
        
        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
        
        // Add hover effect to cards with smooth animation
        const cards = document.querySelectorAll('.category-card');
        cards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-10px)';
            });
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
        
        // Optional: Add keyboard shortcut (press G for General Cases, W for Women's Desk)
        document.addEventListener('keydown', function(e) {
            if (e.key === 'g' || e.key === 'G') {
                e.preventDefault();
                submitCategory('general_cases');
            } else if (e.key === 'w' || e.key === 'W') {
                e.preventDefault();
                window.location.href = 'womens_desk_login.php';
            } else if (e.key === 'v' || e.key === 'V') {
                e.preventDefault();
                submitCategory('visit');
            }
        });
        
        // Add loading state when form is submitted
        document.getElementById('categoryForm')?.addEventListener('submit', function() {
            // Optional: Add loading indicator
            const cards = document.querySelectorAll('.category-card');
            cards.forEach(card => {
                card.style.opacity = '0.6';
                card.style.pointerEvents = 'none';
            });
        });
    </script>
</body>
</html>
