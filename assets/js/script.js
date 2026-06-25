document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('userSidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const mainContent = document.querySelector('main');
    const mobileToggleBtn = document.getElementById('mobileSidebarToggle');

    // Load saved state from localStorage
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (isCollapsed && window.innerWidth > 991) {
        applyCollapsedState();
    }

    // Mobile Sidebar Drawer Toggle
    if (mobileToggleBtn) {
        mobileToggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            sidebar.classList.toggle('show-mobile');
            // Check if overlay exists, if not handled by header.php script
            const overlay = document.querySelector('.sidebar-overlay');
            if (overlay) overlay.classList.toggle('active');
        });
    }

    // Desktop Toggle (Icons Only vs Icons+Text)
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            const currentlyCollapsed = sidebar.classList.contains('collapsed');
            if (currentlyCollapsed) {
                applyExpandedState();
                localStorage.setItem('sidebarCollapsed', 'false');
            } else {
                applyCollapsedState();
                localStorage.setItem('sidebarCollapsed', 'true');
            }
        });
    }

    function applyCollapsedState() {
        if (!sidebar) return;
        sidebar.classList.add('collapsed');
        if (mainContent) {
            // Adjust main content to fill space
            mainContent.classList.remove('col-md-9', 'col-lg-10');
            mainContent.classList.add('col');
        }
    }

    function applyExpandedState() {
        if (!sidebar) return;
        sidebar.classList.remove('collapsed');
        if (mainContent) {
            // Restore standard layout
            mainContent.classList.add('col-md-9', 'col-lg-10');
            mainContent.classList.remove('col');
        }
    }

    // Close mobile sidebar when clicking outside
    document.addEventListener('click', function(event) {
        if (!sidebar) return;
        const isClickInside = sidebar.contains(event.target) || 
                             (mobileToggleBtn && mobileToggleBtn.contains(event.target));
        
        if (!isClickInside && sidebar.classList.contains('show-mobile')) {
            sidebar.classList.remove('show-mobile');
            const overlay = document.querySelector('.sidebar-overlay');
            if (overlay) overlay.classList.remove('active');
        }
    });

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth < 992) {
            sidebar.classList.remove('collapsed');
            if (mainContent) {
                mainContent.classList.add('col-md-9', 'col-lg-10');
                mainContent.classList.remove('col');
            }
        } else {
            const savedState = localStorage.getItem('sidebarCollapsed') === 'true';
            if (savedState) applyCollapsedState();
        }
    });
});
