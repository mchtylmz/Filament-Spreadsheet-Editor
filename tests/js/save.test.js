import test from 'node:test';
import assert from 'node:assert/strict';
import { saveChanges, shouldMockSave } from '../../resources/js/core/save.js';

const changes = [
    { id: 1, field: 'price', old: '10.00', value: '12.50' },
];

test('uses mock saving when no persistence endpoint is configured', async () => {
    let fetchCalled = false;
    const payload = await saveChanges(
        { features: { mockSave: true } },
        changes,
        {
            fetcher: async () => {
                fetchCalled = true;
            },
        },
    );

    assert.equal(shouldMockSave({}), true);
    assert.equal(fetchCalled, false);
    assert.deepEqual(payload, {
        mocked: true,
        results: [
            { id: 1, field: 'price', status: 'success', committed: true },
        ],
    });
});

test('posts changes when a persistence endpoint is configured', async () => {
    const requests = [];
    const payload = await saveChanges(
        { saveUrl: '/spreadsheet/rows', features: { mockSave: false } },
        changes,
        {
            csrfToken: 'token',
            fetcher: async (url, options) => {
                requests.push({ url, options });

                return {
                    ok: true,
                    json: async () => ({ results: [] }),
                };
            },
        },
    );

    assert.equal(shouldMockSave({ saveUrl: '/spreadsheet/rows' }), false);
    assert.deepEqual(payload, { results: [] });
    assert.equal(requests[0].url, '/spreadsheet/rows');
    assert.equal(requests[0].options.method, 'POST');
    assert.equal(requests[0].options.headers['X-CSRF-TOKEN'], 'token');
    assert.equal(requests[0].options.body, JSON.stringify({ changes }));
});
