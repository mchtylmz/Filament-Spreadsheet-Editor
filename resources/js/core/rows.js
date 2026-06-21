export function attachRowIds(rows = [], rowIds = []) {
    return rows.map((row, index) => ({
        ...row,
        id: rowIds[index] ?? row.id,
    }));
}

export function attachLocalRowIds(rows = []) {
    return rows.map((row, index) => ({
        ...row,
        id: row.id ?? `__fse_local_${index + 1}`,
    }));
}
