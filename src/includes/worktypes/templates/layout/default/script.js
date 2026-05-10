(function () {
   
    const initDefaultLayout = () => {
        const root = document.querySelector('.poff-default-layout');
        if (!root) {
            return;
        }

        root.setAttribute('data-poff-default-layout-ready', 'true');
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDefaultLayout, { once: true });
        return;
    }

    initDefaultLayout();
})();
