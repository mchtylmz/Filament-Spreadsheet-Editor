import { TabulatorSpreadsheetAdapter } from './adapters/tabulator.js';
import { saveChanges } from './core/save.js';
import { isTextEntryTarget, spreadsheetShortcut } from './core/shortcuts.js';

window.filamentSpreadsheetEditor = function filamentSpreadsheetEditor(config = {}) {
    return {
        adapter: null,
        hasChanges: false,
        saving: false,
        canUndo: false,
        canRedo: false,
        dirtyRowCount: 0,
        pendingChanges: [],
        saveResults: [],
        panelOpen: false,
        csvPanelOpen: false,
        csvPreviewing: false,
        csvImporting: false,
        csvPreview: null,
        csvMapping: {},
        csvMatchBy: 'primary',
        csvQueue: false,
        csvErrors: [],
        csvResult: null,
        csvImportEnabled: config.features?.csvImport === true,
        csvExportEnabled: config.features?.csvExport === true,
        maxSyncImportRows: config.maxSyncImportRows ?? 1000,
        rootElement: null,
        activationHandler: null,
        keydownHandler: null,
        beforeUnloadHandler: null,

        mount(element) {
            if ((config.adapter ?? 'tabulator') !== 'tabulator') {
                throw new Error(`Unsupported spreadsheet grid adapter [${config.adapter}].`);
            }

            this.rootElement = element.closest('.filament-spreadsheet-editor');
            this.adapter = new TabulatorSpreadsheetAdapter(element, config).mount();
            this.adapter.onChange(({
                hasChanges,
                changes,
                dirtyRowCount,
                canUndo,
                canRedo,
                lastChanged,
            }) => {
                this.hasChanges = hasChanges;
                this.pendingChanges = changes;
                this.dirtyRowCount = dirtyRowCount;
                this.canUndo = canUndo;
                this.canRedo = canRedo;

                if (lastChanged) {
                    this.saveResults = this.saveResults.filter((result) => (
                        String(result.id) !== String(lastChanged.rowId)
                        || result.field !== lastChanged.field
                    ));
                }
            });

            this.activationHandler = () => {
                window.filamentSpreadsheetEditorActive = this;
            };
            this.keydownHandler = (event) => this.handleKeydown(event);
            this.beforeUnloadHandler = (event) => this.handleBeforeUnload(event);
            this.rootElement?.addEventListener('pointerdown', this.activationHandler);
            this.rootElement?.addEventListener('focusin', this.activationHandler);
            window.addEventListener('keydown', this.keydownHandler);
            window.addEventListener('beforeunload', this.beforeUnloadHandler);

            window.filamentSpreadsheetEditorActive ??= this;
        },

        async saveAll() {
            if (!this.adapter || !this.hasChanges) {
                return;
            }

            this.saving = true;

            const changes = this.adapter.changes();
            this.$dispatch('filament-spreadsheet-editor:saving', { changes });

            try {
                const payload = await saveChanges(config, changes, {
                    fetcher: window.fetch.bind(window),
                    csrfToken: this.csrfToken(),
                });

                if (payload.mocked) {
                    this.adapter.clearChanges();
                } else {
                    this.adapter.applySaveResults(payload.results ?? []);
                }

                this.saveResults = payload.results ?? [];
                this.panelOpen = payload.has_errors === true || this.panelOpen;
                this.hasChanges = this.adapter.buffer.hasChanges();
                this.saving = false;
                this.$dispatch('filament-spreadsheet-editor:saved', { changes, response: payload });
            } catch (error) {
                this.saving = false;
                this.$dispatch('filament-spreadsheet-editor:save-failed', { changes, error });
            }
        },

        save() {
            return this.saveAll();
        },

        undo() {
            if (!this.adapter || !this.canUndo || this.saving) {
                return;
            }

            this.saveResults = [];
            this.adapter.clearResultClasses();
            this.adapter.undo();
        },

        redo() {
            if (!this.adapter || !this.canRedo || this.saving) {
                return;
            }

            this.saveResults = [];
            this.adapter.clearResultClasses();
            this.adapter.redo();
        },

        discardAll() {
            if (!this.adapter || !this.hasChanges || this.saving) {
                return;
            }

            this.adapter.discardAll();
            this.saveResults = [];
            this.panelOpen = false;
            this.$dispatch('filament-spreadsheet-editor:discarded');
        },

        reloadConflict(result) {
            this.adapter?.reloadCell(result);
            this.saveResults = this.saveResults.filter((item) => this.resultKey(item) !== this.resultKey(result));
        },

        canReloadConflict(result) {
            return result?.status === 'conflict' && Object.hasOwn(result, 'current');
        },

        resultFor(change) {
            return this.saveResults.find((result) => (
                String(result.id) === String(change.rowId)
                && result.field === change.field
            )) ?? null;
        },

        resultKey(result) {
            return `${result.id}:${result.field}`;
        },

        fieldLabel(field) {
            return config.columns?.find((column) => column.field === field)?.title ?? field;
        },

        displayValue(value) {
            if (value === null || value === undefined || value === '') {
                return 'Empty';
            }

            if (typeof value === 'boolean') {
                return value ? 'True' : 'False';
            }

            return String(value);
        },

        resultMessage(result) {
            if (!result) {
                return '';
            }

            if ((result.errors ?? []).length > 0) {
                return result.errors.join(' ');
            }

            if (result.status === 'conflict') {
                const current = Object.hasOwn(result, 'current')
                    ? ` Current value: ${this.displayValue(result.current)}.`
                    : '';

                return (result.message ?? 'This cell changed on the server.') + current;
            }

            if (result.status === 'forbidden') {
                return 'You are not allowed to edit this cell.';
            }

            if (result.committed === false) {
                return 'The batch was not committed.';
            }

            return '';
        },

        exportCsv(allConfiguredColumns = false) {
            if (!config.features?.csvExport || !config.exportUrl) {
                return;
            }

            window.location.assign(this.adapter.csvExportUrl(allConfiguredColumns));
        },

        async previewCsv(file) {
            if (!file || !config.features?.csvImport || !config.importPreviewUrl) {
                return;
            }

            this.csvPreviewing = true;
            this.csvErrors = [];
            this.csvResult = null;
            this.csvPanelOpen = true;
            const body = new FormData();
            body.append('file', file);

            try {
                const response = await window.fetch(config.importPreviewUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                    },
                    body,
                });
                const payload = await response.json();

                if (!response.ok || payload.has_errors) {
                    this.csvErrors = Object.values(payload.errors ?? {}).flat();

                    return;
                }

                this.csvPreview = payload;
                this.csvMapping = { ...(payload.suggested_mapping ?? {}) };
                this.csvMatchBy = payload.unique_column ? 'unique' : 'primary';
                this.csvPanelOpen = true;
            } catch (error) {
                this.csvErrors = [error.message ?? 'The CSV preview failed.'];
            } finally {
                this.csvPreviewing = false;
            }
        },

        async applyCsvImport() {
            if (!this.csvPreview || this.csvImporting) {
                return;
            }

            this.csvImporting = true;
            this.csvErrors = [];
            this.csvResult = null;

            try {
                const response = await window.fetch(config.importApplyUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                    },
                    body: JSON.stringify({
                        import_token: this.csvPreview.import_token,
                        mapping: this.csvMapping,
                        match_by: this.csvMatchBy,
                        queue: this.csvQueue,
                    }),
                });
                const payload = await response.json();

                this.csvResult = payload;
                this.csvErrors = payload.errors ?? [];

                if (payload.applied) {
                    await this.adapter.refreshData();
                }
            } catch (error) {
                this.csvErrors = [error.message ?? 'The CSV import failed.'];
            } finally {
                this.csvImporting = false;
            }
        },

        csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        },

        handleKeydown(event) {
            if (window.filamentSpreadsheetEditorActive !== this) {
                return;
            }

            const target = event.target;
            const action = spreadsheetShortcut(event);

            if (!action) {
                return;
            }

            if (isTextEntryTarget(target)) {
                if (action !== 'save' || !this.rootElement?.contains(target)) {
                    return;
                }

                event.preventDefault();
                target.blur();
                queueMicrotask(() => this.saveAll());

                return;
            }

            event.preventDefault();

            if (action === 'save') {
                this.saveAll();

                return;
            }

            if (action === 'redo') {
                this.redo();
            } else {
                this.undo();
            }
        },

        handleBeforeUnload(event) {
            if (!this.hasChanges) {
                return;
            }

            event.preventDefault();
            event.returnValue = '';
        },

        destroy() {
            this.rootElement?.removeEventListener('pointerdown', this.activationHandler);
            this.rootElement?.removeEventListener('focusin', this.activationHandler);
            window.removeEventListener('keydown', this.keydownHandler);
            window.removeEventListener('beforeunload', this.beforeUnloadHandler);

            if (window.filamentSpreadsheetEditorActive === this) {
                window.filamentSpreadsheetEditorActive = null;
            }

            this.adapter?.destroy();
            this.adapter = null;
        },
    };
};
