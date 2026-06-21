import test from 'node:test';
import assert from 'node:assert/strict';
import { attachRowIds } from '../../resources/js/core/rows.js';

test('attaches server row identifiers without adding them to configured data columns', () => {
    assert.deepEqual(
        attachRowIds(
            [{ sku: 'SKU-001' }, { sku: 'SKU-002' }],
            [10, 20],
        ),
        [
            { id: 10, sku: 'SKU-001' },
            { id: 20, sku: 'SKU-002' },
        ],
    );
});
