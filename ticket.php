<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

require_once 'config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

// Get complaint data from URL parameters
$queue_number = $_GET['queue'] ?? '';
$complainant_id = $_GET['id'] ?? '';

if (!$queue_number || !$complainant_id) {
    redirect('dashboard.php');
}

// Fetch complaint details
$query = "SELECT c.*, comp.name as complainant_name, comp.contact_number, comp.address, comp.category,
          comp.assigned_investigator, comp.email, comp.queue_number
          FROM complaints c
          JOIN complainants comp ON c.complainant_id = comp.complainant_id
          WHERE c.complainant_id = '$complainant_id' AND c.queue_number = '$queue_number'";
$result = $conn->query($query);
$data = $result->fetch_assoc();

if (!$data) {
    redirect('dashboard.php');
}

// Use the formatQueueNumber function from config.php (already defined there)
$formatted_queue = formatQueueNumber($queue_number, $data['case_status'] ?? null);
$current_datetime = date('Y-m-d H:i:s');
$issued_date = date('F d, Y'); // Format: April 20, 2026

// Define quotes array for PHP (will be used to pick ONE unique quote per ticket)
$quotes_array = [
    "🔐 Think before you click — hindi lahat safe online.",
    "🛡️ Cybercrime is real — ingat palagi, bawat click may peligro.",
    "📱 Protect your data, kasi yan ang digital identity mo.",
    "💻 Hackers don't break doors, they break passwords.",
    "⚠️ Isang click lang, pwedeng mawala lahat.",
    "👀 Stay alert — may banta kahit saan online.",
    "🚫 Don't trust everything you see online.",
    "🔗 Fake links = real problems.",
    "🧠 Awareness is your best protection.",
    "🤔 Think twice, click once.",
    "🌐 Cybercrime is silent pero delikado.",
    "🔑 Wag basta magbigay ng OTP kahit kanino.",
    "💎 Your info is valuable — guard it like treasure.",
    "🧩 Stay smart, stay safe online.",
    "🌙 Cyber threats never sleep — ikaw din dapat alert."
];

// Generate unique quote based on queue number (deterministic, one per ticket)
$seed = $queue_number . $complainant_id;
$hash = crc32($seed);
$unique_quote_index = abs($hash) % count($quotes_array);
$selected_quote = $quotes_array[$unique_quote_index];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Queue Ticket - <?php echo htmlspecialchars($formatted_queue); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @media print {
            @page {
                size: 80mm auto;
                margin: 2mm;
            }
            body {
                background: white;
                margin: 0;
                padding: 0;
                font-family: 'Courier New', monospace;
            }
            .no-print {
                display: none !important;
            }
            .ticket-container {
                margin: 0;
                padding: 3mm;
                box-shadow: none;
                border: none;
                background: white;
            }
            .queue-number {
                break-inside: avoid;
                page-break-inside: avoid;
            }
            .investigator-info, .contact-hotline, .info-row {
                break-inside: avoid;
            }
            .oic-line {
                border-bottom: 1px dotted #000;
                min-width: 150px;
                display: inline-block;
            }
        }
        
        body {
            background: linear-gradient(135deg, #bfc2d1 0%, #2a1e3c 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Courier New', 'Segoe UI', monospace;
            padding: 20px;
        }
        
        .ticket-wrapper {
            max-width: 480px;
            width: 100%;
            margin: 0 auto;
        }
        
        .ticket-container {
            background: white;
            padding: 20px 15px 25px 20px;
            border-radius: 24px;
            box-shadow: 0 25px 45px rgba(0,0,0,0.25);
            margin-bottom: 25px;
            transition: all 0.2s;
            text-align: center;
        }
        
        .ticket-header {
            text-align: center;
            border-bottom: 3px solid #1e2a5e;
            padding-bottom: 16px;
            margin-bottom: 18px;
        }
        
        .ticket-header h1 {
            font-size: 16px;
            font-weight: 800;
            margin-bottom: 6px;
            color: #0a2f44;
            letter-spacing: 1px;
        }
        
        .ticket-header h3 {
            font-size: 11px;
            font-weight: 700;
            color: #2c3e8f;
            margin-bottom: 8px;
        }
        
        .badge-category {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 40px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.5px;
            margin-top: 8px;
        }
        
        .queue-number {
            text-align: center;
            font-size: 45px;
            font-weight: 900;
            background: linear-gradient(135deg, #ffffff, #f4f4f4);
            color: black;
            border-radius: 20px;
            padding: 20px 12px;
            margin: 18px 0;
            letter-spacing: 6px;
            box-shadow: 0 6px 14px rgba(0,0,0,0.2);
            font-family: 'Courier New', monospace;
        }
        
        .investigator-info {
            background: #f0f2fa;
            padding: 12px;
            border-radius: 20px;
            margin: 16px 0;
            text-align: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }
        
        .investigator-info div:first-child {
            font-size: 12px;
            font-weight: 800;
            color: #2c3e66;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .investigator-name {
            font-size: 15px;
            font-weight: 800;
            margin: 6px 0;
            color: #0a1c3a;
        }
        
        .oic-row {
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px dashed #ccc;
            font-size: 12px;
            font-weight: 600;
            color: #2c3e66;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .oic-label {
            font-weight: 800;
            letter-spacing: 0.5px;
        }
        
        .oic-line {
            border-bottom: 1px dotted #333;
            min-width: 160px;
            display: inline-block;
            margin-left: 5px;
        }
        
        .oic-line-print {
            border-bottom: 1px dotted #000;
            min-width: 160px;
            display: inline-block;
        }
        
        .info-row {
            display: flex;
            justify-content: center;
            align-items: baseline;
            flex-wrap: wrap;
            margin-bottom: 12px;
            padding: 5px 0;
            border-bottom: 1px dotted #ddd;
            gap: 6px;
        }
        
        .info-label {
            font-weight: 800;
            font-size: 13px;
            color: #1e2f5a;
            min-width: 85px;
            text-align: right;
        }
        
        .info-value {
            font-weight: 600;
            font-size: 13px;
            color: #222;
            text-align: left;
            flex: 1;
        }
        
        .quote-container {
            margin: 12px 0 8px 0;
        }
        
        #quote-text {
            font-weight: 700;
            font-style: italic;
            font-size: 13px;
            text-align: center;
            background: #fef7e0;
            padding: 12px 10px;
            border-radius: 40px;
            color: #b45309;
            display: inline-block;
            width: 100%;
        }
        
        .ticket-footer {
            text-align: center;
            font-size: 11px;
            font-weight: 900;
            color: #2a2a4a;
            margin-top: 20px;
            padding-top: 12px;
            border-top: 2px dashed #aaa;
        }
        
        .ticket-footer p {
            margin-bottom: 5px;
        }
        
        .centered-warning {
            color: #cb9d9d;
            font-weight: 800;
            font-size: 10px;
            margin-top: 12px;
            text-align: center;
            background: #ffe6e6;
            padding: 6px;
            border-radius: 30px;
        }
        
        .issued-date {
            color: #b91c1c;
            font-weight: 800;
            font-size: 10px;
            margin-top: 8px;
            text-align: center;
        }
        
        .button-group {
            text-align: center;
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn-custom {
            border: none;
            padding: 12px 22px;
            font-weight: 700;
            border-radius: 50px;
            transition: all 0.2s;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-download {
            background: linear-gradient(135deg, #2b6e3c, #1f8a4c);
            color: white;
        }
        
        .btn-print {
            background: linear-gradient(135deg, #0f6b7c, #0b5b6b);
            color: white;
        }
        
        .btn-dashboard {
            background: linear-gradient(135deg, #4b5563, #374151);
            color: white;
        }
        
        .btn-new {
            background: linear-gradient(135deg, #5e4b8b, #3f2e6b);
            color: white;
        }
        
        .btn-custom:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 18px rgba(0,0,0,0.2);
            color: white;
        }
        
        .success-animation {
            text-align: center;
            margin-bottom: 18px;
        }
        
        .success-icon {
            font-size: 58px;
            color: #2ecc71;
            animation: bounce 0.5s ease;
        }
        
        #quote-text {
            font-size: 15px;
            font-weight: 500;
            line-height: 1.5;
        }
        
        @keyframes bounce {
            0%,100%{transform:scale(1);}
            50%{transform:scale(1.15);}
        }
        .queue-number {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            justify-content: center;
        }
        
        .queue-label {
            font-size: 15px;
            color: #6c757d;
            letter-spacing: 0.5px;
        }
        
        .queue-value {
            font-size: 40px;
            font-weight: bold;
            color: #007bff;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>
<div class="ticket-wrapper">
    <div class="success-animation no-print">
        <i class="bi bi-check-circle-fill success-icon"></i>
        <h4 class="mt-2 text-white fw-bold">✓ Ticket Issued Successfully</h4>
    </div>

    <div class="ticket-container" id="ticket-content">
        <div class="ticket-header">
            <h3>Camarines Sur Provincial Cyber Response Team</h3>
            <div class="badge-category" style="background: <?php echo ($data['category'] == 'womens_desk') ? '#d53f8c' : '#2b6cb0'; ?>; color: white;">
                <i class="bi <?php echo ($data['category'] == 'womens_desk') ? 'bi-shield-shaded' : 'bi-shield-lock'; ?>"></i>
                <?php echo ($data['category'] == 'womens_desk') ? 'WOMEN\'S DESK' : 'GENERAL CASES'; ?>
            </div>
        </div>
        
        <div class="queue-number">
            <span class="queue-label">Your Queue Number Is</span>
            <span class="queue-value"><?php echo htmlspecialchars($formatted_queue); ?></span>
        </div>

        <div class="investigator-info">
            <div><i class="bi bi-person-badge"></i> ASSIGNED INVESTIGATOR</div>
            <div class="investigator-name"><?php echo htmlspecialchars($data['assigned_investigator'] ?? 'To be assigned'); ?></div>
            <div class="oic-row">
                <span class="oic-label"><i class="bi bi-pencil-square"></i> OIC:</span>
                <span class="oic-line">________________________________________</span>
            </div>
        </div>

        <p class="centered-warning">
            ⚠ WARNING: Do not capture or screenshot this ticket.
        </p>
        <p class="issued-date">Issued on: <?php echo $issued_date; ?></p>
    </div>

    <div class="button-group no-print">
        <button onclick="downloadAsPDF()" class="btn-custom btn-download"><i class="bi bi-download"></i> Download PDF</button>
        <button onclick="printTicket()" class="btn-custom btn-print"><i class="bi bi-printer"></i> Print Ticket</button>
        <a href="dashboard.php" class="btn-custom btn-dashboard"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="category_selection.php" class="btn-custom btn-new"><i class="bi bi-plus-circle"></i> New Complaint</a>
    </div>
</div>

<script>
    function downloadAsPDF() {
        const element = document.getElementById('ticket-content');
        const opt = {
            margin: [0.4, 0.4, 0.4, 0.4],
            filename: 'ticket_<?php echo htmlspecialchars($formatted_queue); ?>.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, letterRendering: true },
            jsPDF: { unit: 'in', format: 'a6', orientation: 'portrait' }
        };
        
        const downloadBtn = document.querySelector('.btn-download');
        if(downloadBtn) {
            const originalHTML = downloadBtn.innerHTML;
            downloadBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Generating PDF...';
            downloadBtn.disabled = true;
            
            html2pdf().set(opt).from(element).save()
                .then(() => {
                    downloadBtn.innerHTML = originalHTML;
                    downloadBtn.disabled = false;
                    showNotification('PDF downloaded successfully!', 'success');
                })
                .catch(err => {
                    console.error(err);
                    downloadBtn.innerHTML = originalHTML;
                    downloadBtn.disabled = false;
                    showNotification('Error generating PDF.', 'error');
                });
        }
    }

    function printTicket() {
        const originalContent = document.getElementById('ticket-content').cloneNode(true);
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Ticket_<?php echo htmlspecialchars($formatted_queue); ?></title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
                <style>
                    @media print {
                        @page { size: 80mm auto; margin: 2mm; }
                        body { background: white; margin:0; padding:0; font-family: 'Courier New', monospace; }
                        .ticket-container { margin:0; padding: 3mm; box-shadow: none; border: none; background: white; text-align: center; }
                        .queue-number { background: linear-gradient(135deg, #1e2a5e, #3b2b6b); -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                        .investigator-info, .contact-hotline { break-inside: avoid; }
                        .oic-line { border-bottom: 1px dotted #000; min-width: 160px; display: inline-block; }
                    }
                    body {
                        background: white;
                        font-family: 'Courier New', monospace;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        min-height: 100vh;
                        padding: 12px;
                    }
                    .ticket-container {
                        max-width: 420px;
                        margin: 0 auto;
                        background: white;
                        padding: 20px;
                        border-radius: 20px;
                        text-align: center;
                    }
                    .queue-number {
                        text-align: center;
                        font-size: 52px;
                        font-weight: 900;
                        padding: 18px;
                        background: white;
                        color: black;
                        border-radius: 20px;
                        letter-spacing: 5px;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                    .info-row {
                        display: flex;
                        justify-content: center;
                        gap: 8px;
                        flex-wrap: wrap;
                    }
                    .info-label { font-weight: 800; }
                    #quote-text { font-weight: bold; background: #fef7e0; border-radius: 40px; padding: 8px; }
                    .centered-warning { font-weight: 800; text-align: center; }
                    .oic-row {
                        margin-top: 10px;
                        padding-top: 8px;
                        border-top: 1px dashed #ccc;
                        font-size: 12px;
                        font-weight: 600;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        gap: 8px;
                        flex-wrap: wrap;
                    }
                    .oic-line {
                        border-bottom: 1px dotted #000;
                        min-width: 160px;
                        display: inline-block;
                    }
                </style>
            </head>
            <body>
                ${originalContent.outerHTML}
                <script>
                    window.onload = function() {
                        window.print();
                        setTimeout(() => window.close(), 800);
                    };
                <\/script>
            </body>
            </html>
        `);
        printWindow.document.close();
    }

    function showNotification(message, type) {
        const notif = document.createElement('div');
        notif.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show`;
        notif.style.position = 'fixed';
        notif.style.top = '20px';
        notif.style.right = '20px';
        notif.style.zIndex = '9999';
        notif.style.minWidth = '280px';
        notif.style.fontWeight = 'bold';
        notif.style.boxShadow = '0 6px 14px rgba(0,0,0,0.2)';
        notif.innerHTML = `
            <i class="bi bi-${type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill'}"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        document.body.appendChild(notif);
        setTimeout(() => notif.remove(), 3000);
    }
</script>
</body>
</html>