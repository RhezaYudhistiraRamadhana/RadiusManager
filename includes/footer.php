    </div><!-- /.content -->
</div><!-- /#main -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips for sidebar items (visible when collapsed)
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('#sidebar [data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function(el) {
        new bootstrap.Tooltip(el, {
            trigger: 'hover',
            customClass: 'sidebar-tooltip',
            fallbackPlacements: ['bottom', 'top']
        });
    });

    // Sidebar collapse / expand toggle
    var collapseBtn = document.getElementById('sidebarCollapseBtn');
    if (collapseBtn) {
        collapseBtn.addEventListener('click', function(e) {
            e.preventDefault();
            var isCollapsed = document.body.classList.toggle('sidebar-collapsed');
            try {
                localStorage.setItem('cendana_sidebar_collapsed', isCollapsed ? 'true' : 'false');
            } catch (err) {}

            // Trigger window resize so Chart.js charts and datatables adapt immediately
            setTimeout(function() {
                window.dispatchEvent(new Event('resize'));
            }, 220);
        });
    }
});
</script>
<?php if (isset($extra_js)) echo $extra_js; ?>
</body>
</html>
