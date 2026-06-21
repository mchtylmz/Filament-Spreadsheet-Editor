import test from 'node:test';
import assert from 'node:assert/strict';
import { rulesByField, validateCell } from '../../resources/js/core/validation.js';

test('validates required and numeric rules', () => {
    assert.deepEqual(validateCell('', ['required']), {
        valid: false,
        errors: ['required'],
    });

    assert.deepEqual(validateCell('12.50', ['numeric', 'min:0']), {
        valid: true,
        errors: [],
    });
});

test('indexes serialized validation rules by field', () => {
    assert.deepEqual(rulesByField([
        { attribute: 'price', rules: ['numeric', 'min:0'] },
    ]), {
        price: ['numeric', 'min:0'],
    });
});

test('validates boolean and date rules', () => {
    assert.deepEqual(validateCell('yes', ['boolean']), {
        valid: false,
        errors: ['boolean'],
    });
    assert.deepEqual(validateCell('1', ['boolean']), {
        valid: true,
        errors: [],
    });
    assert.deepEqual(validateCell('not-a-date', ['date']), {
        valid: false,
        errors: ['date'],
    });
    assert.deepEqual(validateCell('2026-06-22', ['date']), {
        valid: true,
        errors: [],
    });
});
