</div> <!-- Close main-content wrapper[cite: 4] -->
</div> <!-- Close app-container wrapper[cite: 4] -->

<div class="sidebar-backdrop" id="menuOverlayMask" onclick="toggleLeftMenu(false)"></div>[cite: 4]

<script>
// ==========================================================================
// 1. MOBILE RESPONSIVE DRAWER MECHANICS[cite: 4]
// ==========================================================================
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

// Auto-close menu list drawer when clicking any page link inside it[cite: 4]
document.querySelectorAll('#appLeftNavigationMenu nav a').forEach(link => {
    link.addEventListener('click', () => toggleLeftMenu(false));
});

// ==========================================================================
// 2. FIXED SYSTEM-WIDE AJAX SINGLE-PAGE INTERCEPTOR ENGINE[cite: 4]
// ==========================================================================
document.addEventListener('DOMContentLoaded', () => {
    // Initial dynamic state check on fresh page load to update list values immediately
    if (typeof window.refreshSidebarDropdownState === 'function') {
        window.refreshSidebarDropdownState();
    }

    document.body.addEventListener('click', (e) => {
        const link = e.target.closest('a');
        if (!link) return;
        
        const urlStr = link.getAttribute('href');
        if (!urlStr || urlStr.startsWith('#') || urlStr.startsWith('javascript:') || urlStr === 'logout.php') return;
        if (link.getAttribute('target') === '_blank') return;

        e.preventDefault();

        fetch(urlStr)
            .then(response => {
                if (!response.ok) throw new Error('Network error');
                return response.text();
            })
            .then(htmlMarkup => {
                const parser = new DOMParser();
                const freshDoc = parser.parseFromString(htmlMarkup, 'text/html');
                
                const oldContent = document.querySelector('.main-content');
                const newContent = freshDoc.querySelector('.main-content');
                
                if (oldContent && newContent) {
                    oldContent.innerHTML = newContent.innerHTML;
                    
                    // Highlight the active selection link inside the navigation drawer menu sidebar[cite: 4]
                    document.querySelectorAll('.nav-link').forEach(el => el.classList.remove('active'));
                    const targetFileBase = urlStr.split('?')[0];
                    const targetNavLink = document.querySelector(`.nav-link[href^="${targetFileBase}"]`);
                    if (targetNavLink) targetNavLink.classList.add('active');

                    // --- CRITICAL RE-SYNC INTERCEPTOR HOOK ---
                    // Force the background script to dynamically query the live ledger statuses
                    if (typeof window.refreshSidebarDropdownState === 'function') {
                        window.refreshSidebarDropdownState();
                    }

                    // FIX: Extract, copy, and evaluate script tags globally so functions exist in window context[cite: 4]
                    newContent.querySelectorAll('script').forEach(oldScript => {
                        const freshScript = document.createElement('script');
                        Array.from(oldScript.attributes).forEach(attr => freshScript.setAttribute(attr.name, attr.value));
                        
                        if (oldScript.src) {
                            freshScript.src = oldScript.src;
                        } else {
                            freshScript.appendChild(document.createTextNode(oldScript.innerHTML));
                        }
                        
                        document.body.appendChild(freshScript);
                        freshScript.remove(); // Clean up script node container softly from the DOM tree[cite: 4]
                    });

                    // Keep track of internal workflow paths softly in browser addresses[cite: 4]
                    history.pushState({ url: urlStr }, '', urlStr);
                    window.scrollTo({ top: 0, behavior: 'instant' });
                }
            })
            .catch(err => {
                window.location.href = urlStr;
            });
    });

    window.addEventListener('popstate', () => {
        location.reload();
    });
});
</script>
</body>
</html>