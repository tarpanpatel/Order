</div>
</div>

<div class="sidebar-backdrop" id="menuOverlayMask" onclick="toggleLeftMenu(false)"></div>

<script>
function toggleLeftMenu(shouldOpen) {
    const sidebar = document.getElementById("appLeftNavigationMenu");
    const overlay = document.getElementById("menuOverlayMask");
    if (!sidebar) return;

    if (shouldOpen) {
        sidebar.classList.add("is-drawer-open");
        if (overlay) overlay.classList.add("is-active");
    } else {
        sidebar.classList.remove("is-drawer-open");
        if (overlay) overlay.classList.remove("is-active");
    }
}

// Auto-close menu list drawer when clicking any page button inside it
document.querySelectorAll('#appLeftNavigationMenu nav a').forEach(link => {
    link.addEventListener('click', () => toggleLeftMenu(false));
});
</script>
</body>
</html>