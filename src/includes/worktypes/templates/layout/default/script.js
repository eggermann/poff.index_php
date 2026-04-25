(function () {
    const root = document.querySelector('.poff-default-layout');
    if (!root) {
        return;
    }

    document.documentElement.dataset.poffDefaultLayout = 'active';

    const syncButton = root.querySelector('[data-layout-sync]');
    const toggleButton = root.querySelector('[data-layout-toggle]');
    const lastSync = root.querySelector('[data-layout-last-sync]');

    const formatTime = () => {
        try {
            return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        } catch (error) {
            return String(new Date().getHours()).padStart(2, '0') + ':' + String(new Date().getMinutes()).padStart(2, '0');
        }
    };

    if (lastSync) {
        lastSync.textContent = formatTime();
    }

    if (syncButton) {
        syncButton.addEventListener('click', () => {
            if (syncButton.dataset.syncing === 'true') {
                return;
            }

            const originalText = syncButton.textContent || 'Sync';
            syncButton.dataset.syncing = 'true';
            syncButton.textContent = 'Syncing...';

            window.setTimeout(() => {
                syncButton.dataset.syncing = 'false';
                syncButton.textContent = originalText;
                if (lastSync) {
                    lastSync.textContent = formatTime();
                }
            }, 900);
        });
    }

    if (toggleButton) {
        toggleButton.addEventListener('click', () => {
            root.classList.toggle('poff-default-layout--compact');
            const compact = root.classList.contains('poff-default-layout--compact');
            toggleButton.setAttribute('aria-pressed', compact ? 'true' : 'false');
        });
    }
})();
