import { TabulatorFull as Tabulator } from 'tabulator-tables';
import { ChangeBuffer } from '../core/change-buffer.js';
import { HistoryStack } from '../core/history-stack.js';
import { attachLocalRowIds, attachRowIds } from '../core/rows.js';
import { rulesByField, validateCell } from '../core/validation.js';

export class TabulatorSpreadsheetAdapter {
    constructor(element, config = {}) {
        this.element = element;
        this.config = config;
        this.buffer = new ChangeBuffer();
        this.history = new HistoryStack(config.historyLimit ?? 100);
        this.rules = rulesByField(config.validationRules ?? []);
        this.table = null;
        this.replayingHistory = false;
    }

    mount() {
        this.table = new Tabulator(this.element, {
            ...this.remoteOptions(),
            data: this.config.dataUrl ? undefined : attachLocalRowIds(this.config.rows),
            columns: this.columns(),
            layout: 'fitColumns',
            reactiveData: true,
            selectableRows: this.config.features?.selectableRows ?? true,
            clipboard: this.config.features?.clipboard ?? true,
            clipboardCopyStyled: false,
            clipboardPasteParser: 'table',
            clipboardPasteAction: 'update',
            index: 'id',
        });

        this.table.on('cellEdited', (cell) => this.trackCell(cell));

        return this;
    }

    remoteOptions() {
        if (!this.config.dataUrl) {
            return {};
        }

        return {
            ajaxURL: this.config.dataUrl,
            ajaxConfig: 'GET',
            pagination: true,
            paginationMode: 'remote',
            paginationSize: this.config.pagination?.perPage ?? 25,
            sortMode: 'remote',
            filterMode: 'remote',
            ajaxURLGenerator: (url, _config, params) => {
                const searchParams = new URLSearchParams();

                searchParams.set('page', params.page ?? 1);
                searchParams.set('per_page', params.size ?? this.config.pagination?.perPage ?? 25);

                (params.sorters ?? []).forEach((sorter, index) => {
                    searchParams.set(`sorters[${index}][field]`, sorter.field);
                    searchParams.set(`sorters[${index}][dir]`, sorter.dir);
                });

                (params.filters ?? []).forEach((filter, index) => {
                    searchParams.set(`filters[${index}][field]`, filter.field);
                    searchParams.set(`filters[${index}][value]`, filter.value);
                });

                return `${url}?${searchParams.toString()}`;
            },
            ajaxResponse: (_url, _params, response) => ({
                data: attachRowIds(response.data, response.row_ids),
                last_page: response.meta?.last_page ?? 1,
            }),
        };
    }

    columns() {
        return (this.config.columns ?? []).map((column) => ({
            ...column,
            editor: column.editable ? column.editor : false,
            formatter: this.formatter(column),
            cellEdited: (cell) => this.validateCell(cell, column),
        }));
    }

    formatter(column) {
        if (column.type === 'boolean') {
            return 'tickCross';
        }

        return undefined;
    }

    trackCell(cell) {
        const row = cell.getRow();
        const rowData = row.getData();
        const rowId = rowData.id ?? row.getPosition();
        const field = cell.getField();
        const value = cell.getValue();
        const oldValue = cell.getOldValue();

        if (!this.replayingHistory && value !== oldValue) {
            this.history.push({
                rowId,
                field,
                before: oldValue,
                after: value,
            });
        }

        this.buffer.set(rowId, field, value, oldValue);
        this.syncCellState(cell, rowId, field);
        this.clearCellResult(cell);
    }

    validateCell(cell, column) {
        const result = validateCell(cell.getValue(), this.rules[column.field] ?? []);

        cell.getElement().classList.toggle('filament-spreadsheet-editor__cell--invalid', !result.valid);
        cell.getElement().dataset.validationErrors = result.errors.join(',');

        return result.valid;
    }

    onChange(listener) {
        return this.buffer.onChange((state) => listener({
            ...state,
            canUndo: this.history.canUndo(),
            canRedo: this.history.canRedo(),
        }));
    }

    changes() {
        return this.buffer.all().map((change) => ({
            id: change.rowId,
            field: change.field,
            old: change.oldValue,
            value: change.value,
        }));
    }

    undo() {
        const action = this.history.undo();

        if (!action) {
            return;
        }

        this.applyHistoryAction(action, action.before);
    }

    redo() {
        const action = this.history.redo();

        if (!action) {
            return;
        }

        this.applyHistoryAction(action, action.after);
    }

    discardAll() {
        const changes = this.buffer.all();

        this.replayingHistory = true;

        changes.forEach((change) => {
            const cell = this.cellFor(change.rowId, change.field);

            cell?.setValue(change.oldValue);
        });

        this.replayingHistory = false;
        this.clearChanges();
    }

    reloadCell(result) {
        if (result.status !== 'conflict' || !Object.hasOwn(result, 'current')) {
            return;
        }

        const cell = this.cellFor(result.id, result.field);

        if (!cell) {
            this.history.remove(result.id, result.field);
            this.buffer.remove(result.id, result.field);

            return;
        }

        this.replayingHistory = true;
        cell.setValue(result.current);
        this.replayingHistory = false;
        this.history.remove(result.id, result.field);
        this.buffer.remove(result.id, result.field);
        this.syncCellState(cell, result.id, result.field);
        cell.getElement().classList.remove('filament-spreadsheet-editor__cell--invalid');
        delete cell.getElement().dataset.validationErrors;
        this.clearCellResult(cell);
    }

    clearChanges(clearResults = true) {
        this.history.clear();
        this.buffer.clear();

        this.element
            .querySelectorAll('.filament-spreadsheet-editor__cell--dirty')
            .forEach((cell) => cell.classList.remove('filament-spreadsheet-editor__cell--dirty'));

        if (clearResults) {
            this.clearResultClasses();
        }
    }

    applySaveResults(results = []) {
        this.clearResultClasses();

        results.forEach((result) => {
            const cell = this.cellFor(result.id, result.field);

            if (!cell) {
                return;
            }

            const element = cell.getElement();
            const errors = result.errors ?? [];
            const committed = result.committed !== false;
            const hasError = result.status !== 'success' || !committed;

            element.dataset.saveStatus = result.status;
            element.dataset.saveErrors = errors.join(',');
            element.classList.toggle('filament-spreadsheet-editor__cell--saved', result.status === 'success' && committed);
            element.classList.toggle('filament-spreadsheet-editor__cell--error', hasError);
            element.classList.toggle('filament-spreadsheet-editor__cell--conflict', result.status === 'conflict');
            element.toggleAttribute('aria-invalid', hasError);
            element.title = errors.join('\n') || result.message || (committed ? '' : 'Batch was not committed.');
        });

        if (results.every((result) => result.status === 'success' && result.committed !== false)) {
            this.clearChanges(false);
        }
    }

    applyHistoryAction(action, value) {
        const cell = this.cellFor(action.rowId, action.field);

        if (!cell) {
            this.buffer.set(action.rowId, action.field, value, action.before);

            return;
        }

        this.replayingHistory = true;
        cell.setValue(value);
        this.replayingHistory = false;
    }

    syncCellState(cell, rowId, field) {
        cell.getElement().classList.toggle(
            'filament-spreadsheet-editor__cell--dirty',
            this.buffer.has(rowId, field),
        );
    }

    cellFor(rowId, field) {
        const row = this.table?.getRow(rowId);

        if (!row) {
            return null;
        }

        try {
            return row.getCell(field);
        } catch (_error) {
            return null;
        }
    }

    clearResultClasses() {
        this.element
            .querySelectorAll([
                '.filament-spreadsheet-editor__cell--saved',
                '.filament-spreadsheet-editor__cell--error',
                '.filament-spreadsheet-editor__cell--conflict',
            ].join(','))
            .forEach((cell) => this.clearCellResult({ getElement: () => cell }));
    }

    clearCellResult(cell) {
        const element = cell.getElement();

        element.classList.remove(
            'filament-spreadsheet-editor__cell--saved',
            'filament-spreadsheet-editor__cell--error',
            'filament-spreadsheet-editor__cell--conflict',
        );
        delete element.dataset.saveStatus;
        delete element.dataset.saveErrors;
        element.removeAttribute('aria-invalid');
        element.removeAttribute('title');
    }

    destroy() {
        this.table?.destroy();
        this.table = null;
    }
}
