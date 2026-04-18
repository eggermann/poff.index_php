export function getClosestElementByTag(target, tagName) {
    const desiredTag = String(tagName || '').toUpperCase();
    let node = target instanceof Node ? target : null;

    while (node && node.nodeType === Node.ELEMENT_NODE) {
        if (node.tagName === desiredTag) {
            return node;
        }
        node = node.parentElement;
    }

    return null;
}

export function setElementVisibility(element, visible, display = 'block') {
    if (!element) {
        return;
    }

    element.style.display = visible ? display : 'none';
}
