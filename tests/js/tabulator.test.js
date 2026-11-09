import test from 'node:test';
import assert from 'node:assert/strict';
import { TabulatorSpreadsheetAdapter } from '../../resources/js/adapters/tabulator.js';

test('keeps spreadsheet metadata out of Tabulator column definitions', () => {
    const column = {
        field: 'price',
        title: 'Unit Price',
        type: 'number',
        searchable: true,
        validationRules: ['numeric', 'min:0'],
        editable: true,
        editor: 'number',
        sorter: 'number',
        width: 150,
    };
    const original = structuredClone(column);
    const adapter = new TabulatorSpreadsheetAdapter(null, { columns: [column] });
    const [definition] = adapter.columns();

    for (const metadata of ['type', 'searchable', 'validationRules']) {
        assert.equal(Object.hasOwn(definition, metadata), false);
    }

    assert.equal(definition.field, 'price');
    assert.equal(definition.editor, 'number');
    assert.equal(definition.sorter, 'number');
    assert.equal(definition.width, 150);
    assert.deepEqual(column, original);
});

test('preserves boolean formatting and read-only behavior without exposing metadata', () => {
    const adapter = new TabulatorSpreadsheetAdapter(null, {
        columns: [{ field: 'active', type: 'boolean', editable: false, editor: 'tickCross' }],
    });
    const [definition] = adapter.columns();

    assert.equal(definition.formatter, 'tickCross');
    assert.equal(definition.editor, false);
});

test('still validates edited cells using the spreadsheet rules', () => {
    const adapter = new TabulatorSpreadsheetAdapter(null, {
        columns: [{ field: 'price', type: 'number', validationRules: ['numeric', 'min:0'], editable: true, editor: 'number' }],
        validationRules: [{ attribute: 'price', rules: ['numeric', 'min:0'] }],
    });
    const classes = new Set();
    const element = {
        dataset: {},
        classList: { toggle: (name, enabled) => enabled ? classes.add(name) : classes.delete(name) },
    };
    let value = -5;
    const cell = { getValue: () => value, getElement: () => element };
    const [definition] = adapter.columns();

    assert.equal(definition.cellEdited(cell), false);
    assert.equal(classes.has('filament-spreadsheet-editor__cell--invalid'), true);
    assert.equal(element.dataset.validationErrors, 'min:0');

    value = 10;
    assert.equal(definition.cellEdited(cell), true);
    assert.equal(classes.size, 0);
    assert.equal(element.dataset.validationErrors, '');
});
