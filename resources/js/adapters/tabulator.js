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
            data: this.config.rows ?? [],
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
        return this.buffer.all();
    }

    clearChanges() {
        this.buffer.clear();

        this.element
            .querySelectorAll('.filament-spreadsheet-editor__cell--dirty')
            .forEach((cell) => cell.classList.remove('filament-spreadsheet-editor__cell--dirty'));
    }

    destroy() {
        this.table?.destroy();
        this.table = null;
    }
}
