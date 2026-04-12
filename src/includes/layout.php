<?php
/**
 * HTML layout structure for the file browser
 */
?>
<div class="container">
    <aside class="sidebar" aria-label="Navigation">
        <div class="app-edit-toggle-wrap">
            <button id="editToggle" class="edit-toggle" type="button">Edit mode</button>
        </div>
        <div id="sidebarLoading" class="loading-row">
            <span class="loader"></span>
            <span class="loader-label">Loading navigation...</span>
        </div>
        <ul id="navList">
            <!-- NAV_PLACEHOLDER -->
        </ul>
    </aside>

    <!-- Visible app shell: navigation column + edit UI + direct preview surface. -->
    <div class="main-content">
        <div id="editPanel" class="edit-panel" hidden></div>
        <aside id="editDrawer" class="edit-drawer" hidden></aside>
        <div id="iframeLoading" class="loading-row">
            <span class="loader"></span>
            <span class="loader-label">Loading content...</span>
        </div>
        <div id="contentFrame" class="content-frame" role="document" aria-live="polite"></div>
    </div>
</div>
