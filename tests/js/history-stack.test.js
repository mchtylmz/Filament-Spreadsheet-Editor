import test from 'node:test';
import assert from 'node:assert/strict';
import { HistoryStack } from '../../resources/js/core/history-stack.js';

test('moves edit actions through undo and redo stacks', () => {
    const history = new HistoryStack();
    const action = { rowId: 1, field: 'price', before: 10, after: 12 };

    history.push(action);

    assert.equal(history.canUndo(), true);
    assert.deepEqual(history.undo(), action);
    assert.equal(history.canRedo(), true);
    assert.deepEqual(history.redo(), action);
    assert.equal(history.canUndo(), true);
});

test('clears redo actions after a new edit and removes one cell history', () => {
    const history = new HistoryStack();

    history.push({ rowId: 1, field: 'price', before: 10, after: 12 });
    history.push({ rowId: 2, field: 'stock', before: 4, after: 5 });
    history.undo();
    history.push({ rowId: 3, field: 'name', before: 'Old', after: 'New' });

    assert.equal(history.canRedo(), false);

    history.remove(1, 'price');

    assert.deepEqual(history.undo(), {
        rowId: 3,
        field: 'name',
        before: 'Old',
        after: 'New',
    });
    assert.equal(history.canUndo(), false);
});
