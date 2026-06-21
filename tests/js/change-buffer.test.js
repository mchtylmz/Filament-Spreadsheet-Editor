import test from 'node:test';
import assert from 'node:assert/strict';
import { ChangeBuffer } from '../../resources/js/core/change-buffer.js';

test('tracks and clears dirty cell changes', () => {
    const buffer = new ChangeBuffer();

    buffer.set(1, 'price', 12, 10);
    buffer.set(2, 'stock', 5, 4);

    assert.equal(buffer.hasChanges(), true);
    assert.equal(buffer.dirtyRowCount(), 2);
    assert.deepEqual(buffer.all(), [
        { rowId: 1, field: 'price', value: 12, oldValue: 10 },
        { rowId: 2, field: 'stock', value: 5, oldValue: 4 },
    ]);

    buffer.remove(2, 'stock');
    buffer.set(1, 'price', 10, 10);
    assert.equal(buffer.hasChanges(), false);
});

test('preserves the original value across repeated edits', () => {
    const buffer = new ChangeBuffer();

    buffer.set(1, 'price', 12, 10);
    buffer.set(1, 'price', 15, 12);

    assert.deepEqual(buffer.all(), [
        { rowId: 1, field: 'price', value: 15, oldValue: 10 },
    ]);

    buffer.set(1, 'price', 10, 15);
    assert.equal(buffer.hasChanges(), false);
    assert.equal(buffer.has(1, 'price'), false);
});
