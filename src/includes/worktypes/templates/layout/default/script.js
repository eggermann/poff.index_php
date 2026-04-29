(function () {
    const root = document.querySelector('.poff-default-layout');
    if (!root) {
        return;
    }

    document.documentElement.dataset.poffDefaultLayout = 'active';

    const syncButton = root.querySelector('[data-layout-sync]');
    const toggleButton = root.querySelector('[data-layout-toggle]');
    const lastSync = root.querySelector('[data-layout-last-sync]');
    const health = root.querySelector('[data-layout-health]');
    const status = root.querySelector('[data-layout-status]');

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
            if (health) {
                health.textContent = 'Syncing';
            }

            window.setTimeout(() => {
                syncButton.dataset.syncing = 'false';
                syncButton.textContent = originalText;
                if (lastSync) {
                    lastSync.textContent = formatTime();
                }
                if (health) {
                    health.textContent = 'On Track';
                }
                if (status) {
                    status.textContent = 'Updated';
                }
            }, 900);
        });
    }

    if (toggleButton) {
        toggleButton.addEventListener('click', () => {
            root.classList.toggle('poff-default-layout--focus');
            const focused = root.classList.contains('poff-default-layout--focus');
            toggleButton.setAttribute('aria-pressed', focused ? 'true' : 'false');
            toggleButton.textContent = focused ? 'Full View' : 'Focus View';
        });
    }
})();
