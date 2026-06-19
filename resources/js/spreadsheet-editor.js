import { TabulatorSpreadsheetAdapter } from './adapters/tabulator.js';

window.filamentSpreadsheetEditor = function filamentSpreadsheetEditor(config = {}) {
    return {
        adapter: null,
        hasChanges: false,
        saving: false,

        mount(element) {
            if ((config.adapter ?? 'tabulator') !== 'tabulator') {
                throw new Error(`Unsupported spreadsheet grid adapter [${config.adapter}].`);
            }

            this.adapter = new TabulatorSpreadsheetAdapter(element, config).mount();
            this.adapter.onChange(({ hasChanges }) => {
                this.hasChanges = hasChanges;
            });
        },

        save() {
            if (!this.adapter || !this.hasChanges) {
                return;
            }

            this.saving = true;

            const changes = this.adapter.changes();
            this.$dispatch('filament-spreadsheet-editor:saving', { changes });

            window.setTimeout(() => {
                this.adapter.clearChanges();
                this.hasChanges = false;
                this.saving = false;
                this.$dispatch('filament-spreadsheet-editor:saved', { changes });
            }, 250);
        },

        destroy() {
            this.adapter?.destroy();
            this.adapter = null;
        },
    };
};
