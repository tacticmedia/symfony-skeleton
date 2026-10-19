/**
 * Ignore double-click on buttons not meant to be double-clicked.
 */
window.addEventListener('click', (event) => {
    if (event.detail > 1 && event.target.closest('a, button:not([data-multi-click])')) {
        event.preventDefault();
        event.stopImmediatePropagation();
    }
}, { capture: true });
