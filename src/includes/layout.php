<?php
/**
 * HTML layout structure for the file browser
 */
?>
<div class="container">
    <div class="app-edit-toggle-wrap">
        <button id="editToggle" class="edit-toggle" type="button">Edit mode</button>
    </div>
    <!-- ---------- Sidebar ---------- -->
    <nav class="sidebar">
        <ul id="navList" class="nav-list">
            <div id="navLoading" class="loading-row">
                <span class="loader"></span>
                <span class="loader-label">Loading...</span>
            </div>
            <!-- NAV_PLACEHOLDER -->
        </ul>
    </nav>

    <!-- ---------- Main Content (header + iframe) ---------- -->
    <div class="main-content">
        <div id="editPanel" class="edit-panel" hidden></div>
        <aside id="editDrawer" class="edit-drawer" hidden></aside>
        <div id="iframeLoading" class="loading-row">
            <span class="loader"></span>
            <span class="loader-label">Loading content...</span>
        </div>
        <iframe id="contentFrame" name="contentFrame" class="content-frame" src="about:blank"></iframe>
    </div>
</div>
