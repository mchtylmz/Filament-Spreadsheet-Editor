import { TabulatorFull as Tabulator } from 'tabulator-tables';

window.filamentSpreadsheetEditor = function filamentSpreadsheetEditor(config = {}) {
    return {
        grid: null,

        mount(element) {
            if (config.adapter !== 'tabulator') {
                throw new Error(`Unsupported spreadsheet grid adapter [${config.adapter}].`);
            }

            this.grid = new Tabulator(element, config.options ?? {});
        },

        destroy() {
            this.grid?.destroy();
            this.grid = null;
        },
    };
};
