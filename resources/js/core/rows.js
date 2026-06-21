export function attachRowIds(rows = [], rowIds = []) {
    return rows.map((row, index) => ({
        ...row,
        id: rowIds[index] ?? row.id,
    }));
}
