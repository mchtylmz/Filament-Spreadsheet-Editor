import { TabulatorSpreadsheetAdapter } from './adapters/tabulator.js';
import { saveChanges } from './core/save.js';

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

        async save() {
            if (!this.adapter || !this.hasChanges) {
                return;
            }

            this.saving = true;

            const changes = this.adapter.changes();
            this.$dispatch('filament-spreadsheet-editor:saving', { changes });

            try {
                const payload = await saveChanges(config, changes, {
                    fetcher: window.fetch.bind(window),
                    csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                });

                if (payload.mocked) {
                    this.adapter.clearChanges();
                } else {
                    this.adapter.applySaveResults(payload.results ?? []);
                }

                this.hasChanges = this.adapter.buffer.hasChanges();
                this.saving = false;
                this.$dispatch('filament-spreadsheet-editor:saved', { changes, response: payload });
            } catch (error) {
                this.saving = false;
                this.$dispatch('filament-spreadsheet-editor:save-failed', { changes, error });
            }
        },

        destroy() {
            this.adapter?.destroy();
            this.adapter = null;
        },
    };
};
