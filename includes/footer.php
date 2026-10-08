    </div><!-- /.content -->
</div><!-- /#main -->

<script src="assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<?php if (($current_page ?? '') === 'dashboard' || !empty($include_chartjs)): ?>
<script src="assets/vendor/chartjs/chart.umd.min.js"></script>
<?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips for sidebar items (visible when collapsed)
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('#sidebar [data-bs-toggle="tooltip"]'));
    var tooltips = tooltipTriggerList.map(function(el) {
        return new bootstrap.Tooltip(el, {
            trigger: 'hover',
            customClass: 'sidebar-tooltip',
            fallbackPlacements: ['bottom', 'top']
        });
    });

    function toggleSidebar() {
        var isCollapsed = document.body.classList.toggle('sidebar-collapsed');
        try {
            localStorage.setItem('cendana_sidebar_collapsed', isCollapsed ? 'true' : 'false');
        } catch (err) {}

        // Update titles on all toggle buttons
        document.querySelectorAll('.sidebar-collapse-btn, #sidebarCollapseBtn').forEach(function(btn) {
            btn.setAttribute('title', isCollapsed ? 'Expand Sidebar' : 'Minimize Sidebar');
            btn.setAttribute('aria-label', isCollapsed ? 'Expand Sidebar' : 'Minimize Sidebar');
        });

        // Trigger window resize so Chart.js charts and datatables adapt immediately
        setTimeout(function() {
            window.dispatchEvent(new Event('resize'));
        }, 220);
    }

    // Bind all sidebar collapse trigger buttons (header, footer, topbar)
    document.querySelectorAll('.sidebar-collapse-trigger').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            toggleSidebar();
        });
    });

    // Also clicking brand when collapsed expands it
    var brand = document.querySelector('.sidebar-brand');
    if (brand) {
        brand.addEventListener('click', function(e) {
            if (document.body.classList.contains('sidebar-collapsed')) {
                e.preventDefault();
                toggleSidebar();
            }
        });
    }
});
</script>
<?php if (isset($extra_js)) echo $extra_js; ?>
</body>
</html>
