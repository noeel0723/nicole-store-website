
        </div><!-- /.page-content -->
    </main>
</div><!-- /.app-wrapper -->

<script>
// Sidebar Toggle (Mobile)
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('show');
}

// Close sidebar on overlay click
document.addEventListener('click', function(e) {
    const sidebar = document.getElementById('sidebar');
    const menuToggle = document.querySelector('.menu-toggle');
    if (window.innerWidth <= 768 && sidebar.classList.contains('open') &&
        !sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
        toggleSidebar();
    }
});

// Auto-hide alerts
document.querySelectorAll('.alert').forEach(alert => {
    setTimeout(() => {
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-10px)';
        setTimeout(() => alert.remove(), 300);
    }, 4000);
});
</script>
</body>
</html>
