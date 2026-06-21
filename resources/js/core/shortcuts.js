export function spreadsheetShortcut(event) {
    if (!(event.ctrlKey || event.metaKey) || event.altKey) {
        return null;
    }

    const key = event.key.toLowerCase();

    if (key === 's') {
        return 'save';
    }

    if (key === 'z') {
        return event.shiftKey ? 'redo' : 'undo';
    }

    return null;
}

export function isTextEntryTarget(target) {
    return typeof target?.matches === 'function'
        && target.matches('input, textarea, select, [contenteditable="true"]');
}
