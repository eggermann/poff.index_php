<?php
/**
 * HTML layout structure for the file browser
 */
?>
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
        <details id="editAuthDetails" class="app-edit-toggle-wrap edit-auth-details">
            <summary id="editToggle" class="edit-toggle">Enable edit mode</summary>
            <form id="editAuthForm" class="edit-form">
                <label class="edit-label" for="editAuthPassword">Editor password</label>
                <input id="editAuthPassword" class="form-input" type="password" name="password" autocomplete="current-password">
                <button id="editAuthSubmit" class="btn" type="submit">Unlock</button>
                <div id="editAuthStatus" class="edit-status" aria-live="polite"></div>
            </form>
        </details>
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
