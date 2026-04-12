<?php
/**
 * HTML layout structure for the file browser
 */
?>
<div class="container">
    <div class="app-edit-toggle-wrap">
        <button id="editToggle" class="edit-toggle" type="button">Edit mode</button>
    </div>
  
    <!-- Visible app shell: edit UI + direct preview surface. The page/sidebar chrome should come from the active HBS layout. -->
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
