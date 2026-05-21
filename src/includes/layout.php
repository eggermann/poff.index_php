<?php
/**
 * HTML layout structure for the file browser
 */
?>
<script>
(function () {
    try {
        var shellValue = new URLSearchParams(window.location.search).get('shell');
        if (['0', 'false', 'off', 'no'].indexOf(String(shellValue || '').toLowerCase()) !== -1) {
            document.documentElement.classList.add('poff-shell-standalone');
        }
    } catch (error) {
        // Ignore malformed URLs and keep the default shell.
    }
})();
</script>
<style>
    html.poff-shell-standalone #appShell {
        display: none !important;
    }

    html.poff-shell-standalone .preview-shell {
        width: 100%;
    }

    html.poff-shell-standalone .main-content {
        width: 100%;
        margin-left: 0;
    }

    #sidebarLoading,
    #iframeLoading {
        position: fixed !important;
        left: 50%;
        z-index: 9999;
        transform: translateX(-50%);
    }

    #sidebarLoading {
        top: 1rem;
    }

    #iframeLoading {
        top: 4rem;
    }
</style>
<div id="appShell" class="container">
    <button
        id="sidebarToggle"
        class="sidebar-toggle"
        type="button"
        aria-controls="appSidebar"
        aria-expanded="true"
        aria-label="Close navigation"
    >
        <span class="sidebar-toggle__icon" aria-hidden="true">
            <span class="sidebar-toggle__bar"></span>
            <span class="sidebar-toggle__bar"></span>
            <span class="sidebar-toggle__bar"></span>
        </span>
    </button>
    <aside id="appSidebar" class="sidebar" aria-label="Navigation">
        <div class="app-edit-toggle-wrap">
            <details id="editActionsMenu" class="edit-actions-menu">
                <summary id="editActionsMenuToggle" class="edit-actions-menu-toggle" aria-label="Toggle edit actions">
                    <span class="edit-actions-menu-toggle__icon" aria-hidden="true">
                        <span></span>
                        <span></span>
                        <span></span>
                    </span>
                </summary>
                <div class="edit-actions-menu__panel">
                    <details id="editAuthDetails" class="edit-auth-details">
                        <summary id="editToggle" class="edit-toggle">Enable edit mode</summary>
                        <form id="editAuthForm" class="edit-form edit-auth-bubble">
                            <label class="edit-label" for="editAuthPassword">Editor password</label>
                            <input id="editAuthPassword" class="form-input" type="password" name="password" autocomplete="current-password">
                            <button id="editAuthSubmit" class="btn" type="submit">Unlock</button>
                            <div id="editAuthStatus" class="edit-status" aria-live="polite"></div>
                        </form>
                    </details>
                    <button id="editAddWork" class="edit-toggle edit-toggle-secondary" type="button">Add work</button>
                </div>
            </details>
        </div>
        <div id="sidebarLoading" class="loading-row">
            <span class="loader"></span>
            <span class="loader-label">Loading navigation...</span>
        </div>
        <ul id="navList" class="nav-list">
            <!-- NAV_PLACEHOLDER -->
        </ul>
    </aside>

    <!-- Visible app shell: navigation column + edit UI + direct preview surface. -->
    <div class="main-content">
        <div id="editPanel" class="edit-panel" hidden></div>
        <aside id="editDrawer" class="edit-drawer" hidden></aside>
        
        <div id="preview" class="preview-shell" tabindex="-1">
            <div id="iframeLoading" class="loading-row">
                <span class="loader"></span>
                <span class="loader-label">Loading content...</span>
            </div>
            <div id="contentFrame" class="content-frame" role="document" aria-live="polite"></div>
            <div id="promptDock" class="prompt-dock" aria-live="polite"></div>
        </div>
    </div>
</div>
