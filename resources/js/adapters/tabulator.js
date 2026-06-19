import { TabulatorFull as Tabulator } from 'tabulator-tables';
import { ChangeBuffer } from '../core/change-buffer.js';
import { rulesByField, validateCell } from '../core/validation.js';

export class TabulatorSpreadsheetAdapter {
    constructor(element, config = {}) {
        this.element = element;
        this.config = config;
        this.buffer = new ChangeBuffer();
        this.rules = rulesByField(config.validationRules ?? []);
        this.table = null;
    }

    mount() {
        this.table = new Tabulator(this.element, {
            ...this.remoteOptions(),
            data: this.config.dataUrl ? undefined : (this.config.rows ?? []),
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
                data: response.data ?? [],
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

        this.buffer.set(rowId, field, cell.getValue(), cell.getOldValue());

        const element = cell.getElement();
        element.classList.toggle('filament-spreadsheet-editor__cell--dirty', cell.getValue() !== cell.getOldValue());
    }

    validateCell(cell, column) {
        const result = validateCell(cell.getValue(), this.rules[column.field] ?? []);

        cell.getElement().classList.toggle('filament-spreadsheet-editor__cell--invalid', !result.valid);
        cell.getElement().dataset.validationErrors = result.errors.join(',');

        return result.valid;
    }

    onChange(listener) {
        return this.buffer.onChange(listener);
    }

    changes() {
        return this.buffer.all().map((change) => ({
            id: change.rowId,
            field: change.field,
            old: change.oldValue,
            value: change.value,
        }));
    }

    clearChanges() {
        this.buffer.clear();

        this.element
            .querySelectorAll('.filament-spreadsheet-editor__cell--dirty')
            .forEach((cell) => cell.classList.remove('filament-spreadsheet-editor__cell--dirty'));

        this.clearResultClasses();
    }

    applySaveResults(results = []) {
        this.clearResultClasses();

        results.forEach((result) => {
            const cell = this.cellFor(result.id, result.field);

            if (!cell) {
                return;
            }

            const element = cell.getElement();
            element.dataset.saveStatus = result.status;
            element.dataset.saveErrors = (result.errors ?? []).join(',');
            element.classList.toggle('filament-spreadsheet-editor__cell--saved', result.status === 'success' && result.committed !== false);
            element.classList.toggle('filament-spreadsheet-editor__cell--error', result.status !== 'success');
            element.classList.toggle('filament-spreadsheet-editor__cell--conflict', result.status === 'conflict');
        });

        if (results.every((result) => result.status === 'success' && result.committed !== false)) {
            this.clearChanges();
        }
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
            .forEach((cell) => {
                cell.classList.remove(
                    'filament-spreadsheet-editor__cell--saved',
                    'filament-spreadsheet-editor__cell--error',
                    'filament-spreadsheet-editor__cell--conflict',
                );
                delete cell.dataset.saveStatus;
                delete cell.dataset.saveErrors;
            });
    }

    destroy() {
        this.table?.destroy();
        this.table = null;
    }
}
