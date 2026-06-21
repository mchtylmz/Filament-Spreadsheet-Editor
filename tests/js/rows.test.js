import test from 'node:test';
import assert from 'node:assert/strict';
import { attachLocalRowIds, attachRowIds } from '../../resources/js/core/rows.js';

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

test('adds stable grid identifiers to local rows without model ids', () => {
    assert.deepEqual(
        attachLocalRowIds([
            { sku: 'SKU-001' },
            { id: 25, sku: 'SKU-002' },
        ]),
        [
            { id: '__fse_local_1', sku: 'SKU-001' },
            { id: 25, sku: 'SKU-002' },
        ],
    );
});
