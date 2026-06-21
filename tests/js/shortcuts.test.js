import test from 'node:test';
import assert from 'node:assert/strict';
import { isTextEntryTarget, spreadsheetShortcut } from '../../resources/js/core/shortcuts.js';

test('maps save, undo, and redo keyboard shortcuts', () => {
    assert.equal(spreadsheetShortcut({ key: 's', ctrlKey: true }), 'save');
    assert.equal(spreadsheetShortcut({ key: 'z', metaKey: true }), 'undo');
    assert.equal(spreadsheetShortcut({ key: 'Z', metaKey: true, shiftKey: true }), 'redo');
});

test('ignores shortcuts without a command modifier or with alt pressed', () => {
    assert.equal(spreadsheetShortcut({ key: 's' }), null);
    assert.equal(spreadsheetShortcut({ key: 'z', ctrlKey: true, altKey: true }), null);
    assert.equal(spreadsheetShortcut({ key: 'x', ctrlKey: true }), null);
});

test('detects active text entry controls', () => {
    const input = {
        matches: (selector) => selector.includes('input'),
    };
    const button = {
        matches: () => false,
    };

    assert.equal(isTextEntryTarget(input), true);
    assert.equal(isTextEntryTarget(button), false);
    assert.equal(isTextEntryTarget(null), false);
});
