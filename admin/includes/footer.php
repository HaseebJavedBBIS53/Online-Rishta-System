        </div> <!-- end p-4 -->
    </div> <!-- end #content -->
</div> <!-- end #wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('toggleSidebar').addEventListener('click', function() {
    const sidebar = document.getElementById('sidebar');
    const content = document.getElementById('content');
    
    sidebar.classList.toggle('collapsed');
    content.classList.toggle('expanded');
    
    // Save state via AJAX for persistent experience
    const isCollapsed = sidebar.classList.contains('collapsed');
    fetch('toggle_sidebar.php?collapsed=' + (isCollapsed ? 1 : 0));
});
</script>
</body>
</html>
