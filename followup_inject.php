<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

// Then include your existing code...
require_once 'config.php';

// followup_inject.php
// Injects a Follow-Up nav link and FAB button into pages that include this file.
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    try {
        // Sidebar nav insertion
        var sidebar = document.querySelector('.sidebar');
        if (sidebar) {
            var nav = sidebar.querySelector('nav');
            if (nav && !nav.querySelector('a[href="records.php#followUpSection"]')) {
                var a = document.createElement('a');
                a.href = 'records.php#followUpSection';
                a.innerHTML = '<i class="bi bi-clock-history"></i> Follow-Up';
                nav.appendChild(a);
            }
        }

        // FAB menu insertion
        var fabMenu = document.querySelector('.fab-menu');
        if (fabMenu && !fabMenu.querySelector('button[title="Follow-Up"]')) {
            var btn = document.createElement('button');
            btn.className = 'fab-item';
            btn.title = 'Follow-Up';
            btn.type = 'button';
            btn.onclick = function() { window.location.href = 'records.php#followUpSection'; };
            btn.innerHTML = '<i class="bi bi-clock-history"></i>';
            fabMenu.appendChild(btn);
        }

        // If no FAB exists, add a small floating button in bottom-right
        if (!fabMenu) {
            if (!document.getElementById('followUpQuickBtn')) {
                var quick = document.createElement('div');
                quick.style.position = 'fixed';
                quick.style.right = '18px';
                quick.style.bottom = '18px';
                quick.style.zIndex = '1200';
                quick.innerHTML = '<a id="followUpQuickBtn" href="records.php#followUpSection" class="btn btn-sm btn-outline-primary" title="Follow-Up" style="border-radius:50%;padding:12px 14px;"><i class="bi bi-clock-history"></i></a>';
                document.body.appendChild(quick);
            }
        }
    } catch (e) {
        console.error('followup_inject error', e);
    }
});
</script>
