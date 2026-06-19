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
