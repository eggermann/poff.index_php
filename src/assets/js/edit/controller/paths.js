import { getActiveSelection } from '../../core/selection.js';

export function getContentTargetPath(selection = getActiveSelection()) {
    if (selection?.isLayout) {
        return selection.path || '';
    }
    const previewPath = selection?.previewPath || selection?.path || '';
    if (selection?.previewIsFile) {
        return previewPath.split('/').slice(0, -1).join('/');
    }
    return previewPath;
}

export function getEditTargetPath(selection = getActiveSelection()) {
    if (selection?.isLayout) {
        return selection.path || '';
    }
    if (selection?.previewIsFile) {
        const activeFileLink = document.querySelector('#navList a.nav-link-active[data-path]');
        const navPath = (activeFileLink?.getAttribute('data-path') || '').trim();
        if (navPath) {
            return navPath;
        }
    }
    return selection?.previewPath || selection?.path || '';
}
