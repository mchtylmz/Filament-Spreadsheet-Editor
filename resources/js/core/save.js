export function shouldMockSave(config = {}) {
    return config.features?.mockSave === true || !config.saveUrl;
}

export async function saveChanges(config, changes, options = {}) {
    if (shouldMockSave(config)) {
        return {
            mocked: true,
            results: changes.map((change) => ({
                id: change.id,
                field: change.field,
                status: 'success',
                committed: true,
            })),
        };
    }

    const fetcher = options.fetcher ?? globalThis.fetch;
    const response = await fetcher(config.saveUrl, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': options.csrfToken ?? '',
        },
        body: JSON.stringify({ changes }),
    });
    const payload = await response.json();

    if (!response.ok) {
        const error = new Error(payload.message ?? 'Spreadsheet changes could not be saved.');

        error.response = payload;

        throw error;
    }

    return payload;
}
