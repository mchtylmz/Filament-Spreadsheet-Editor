export function validateCell(value, rules = []) {
    const errors = [];

    rules.forEach((rule) => {
        const [name, parameter] = String(rule).split(':');

        if (name === 'required' && (value === null || value === undefined || value === '')) {
            errors.push('required');
        }

        if (name === 'numeric' && value !== '' && value !== null && Number.isNaN(Number(value))) {
            errors.push('numeric');
        }

        if (name === 'integer' && value !== '' && value !== null && !Number.isInteger(Number(value))) {
            errors.push('integer');
        }

        if (name === 'min' && value !== '' && value !== null && Number(value) < Number(parameter)) {
            errors.push(`min:${parameter}`);
        }

        if (name === 'max' && value !== '' && value !== null && Number(value) > Number(parameter)) {
            errors.push(`max:${parameter}`);
        }
    });

    return {
        valid: errors.length === 0,
        errors,
    };
}

export function rulesByField(validationRules = []) {
    return validationRules.reduce((rules, rule) => {
        rules[rule.attribute] = rule.rules ?? [];

        return rules;
    }, {});
}
