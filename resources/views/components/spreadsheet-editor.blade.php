@php
    use Mivento\FilamentSpreadsheetEditor\SpreadsheetEditor;

    $config = $config ?? null;

    if (($editor ?? null) instanceof SpreadsheetEditor) {
        $config = $editor->toFrontendConfig($rows ?? null);
    }

    $config ??= [
        'adapter' => $adapter ?? 'tabulator',
        'columns' => $columns ?? [],
        'rows' => $rows ?? [],
        'validationRules' => $validationRules ?? [],
        'features' => [
            'selectableRows' => $selectableRows ?? true,
            'clipboard' => $clipboard ?? true,
            'dirtyCells' => true,
            'mockSave' => true,
        ],
    ];
@endphp

<div
    x-data="filamentSpreadsheetEditor(@js($config))"
    x-init="mount($refs.grid)"
    x-id="['pending-panel']"
    class="filament-spreadsheet-editor"
>
    <div class="filament-spreadsheet-editor__toolbar">
        <div class="filament-spreadsheet-editor__tools">
            <button
                type="button"
                class="filament-spreadsheet-editor__icon-button"
                x-bind:disabled="! canUndo || saving"
                x-on:click="undo()"
                title="Undo"
                aria-label="Undo"
            >
                <x-filament::icon icon="heroicon-m-arrow-uturn-left" />
            </button>

            <button
                type="button"
                class="filament-spreadsheet-editor__icon-button"
                x-bind:disabled="! canRedo || saving"
                x-on:click="redo()"
                title="Redo"
                aria-label="Redo"
            >
                <x-filament::icon icon="heroicon-m-arrow-uturn-right" />
            </button>

            <span class="filament-spreadsheet-editor__divider" aria-hidden="true"></span>

            <button
                type="button"
                class="filament-spreadsheet-editor__changes-toggle filament-spreadsheet-editor__pill-button"
                x-bind:class="{ 'is-active': panelOpen }"
                x-on:click="panelOpen = ! panelOpen"
                x-bind:aria-expanded="panelOpen"
                x-bind:aria-controls="$id('pending-panel')"
            >
                <x-filament::icon icon="heroicon-m-list-bullet" />
                <span>Pending changes</span>
                <span class="filament-spreadsheet-editor__count" x-text="pendingChanges.length"></span>
            </button>

            <span
                class="filament-spreadsheet-editor__dirty-rows filament-spreadsheet-editor__status-pill"
                x-show="dirtyRowCount > 0"
                x-cloak
                x-text="`${dirtyRowCount} dirty ${dirtyRowCount === 1 ? 'row' : 'rows'}`"
            ></span>
        </div>

        <div class="filament-spreadsheet-editor__actions">
            <input
                x-ref="csvFile"
                type="file"
                accept=".csv,text/csv"
                class="filament-spreadsheet-editor__file-input"
                x-on:change="previewCsv($event.target.files[0]); $event.target.value = ''"
            />

            <button
                x-show="csvImportEnabled"
                x-cloak
                type="button"
                class="filament-spreadsheet-editor__secondary-action filament-spreadsheet-editor__secondary-action--import"
                x-bind:disabled="csvPreviewing || csvImporting"
                x-on:click="$refs.csvFile.click()"
            >
                <x-filament::icon icon="heroicon-m-arrow-up-tray" />
                <span x-text="csvPreviewing ? 'Reading...' : 'Import CSV'"></span>
            </button>

            <button
                x-show="csvExportEnabled"
                x-cloak
                type="button"
                class="filament-spreadsheet-editor__secondary-action filament-spreadsheet-editor__secondary-action--export"
                x-on:click="exportCsv(false)"
            >
                <x-filament::icon icon="heroicon-m-arrow-down-tray" />
                <span>Export visible</span>
            </button>

            <button
                x-show="csvExportEnabled"
                x-cloak
                type="button"
                class="filament-spreadsheet-editor__icon-button"
                x-on:click="exportCsv(true)"
                title="Export all configured columns"
                aria-label="Export all configured columns"
            >
                <x-filament::icon icon="heroicon-m-table-cells" />
            </button>

            <button
                type="button"
                class="filament-spreadsheet-editor__discard filament-spreadsheet-editor__danger-action"
                x-bind:disabled="! hasChanges || saving"
                x-on:click="discardAll()"
            >
                Discard all
            </button>

            <button
                type="button"
                class="filament-spreadsheet-editor__save filament-spreadsheet-editor__primary-action"
                x-bind:disabled="! hasChanges || saving"
                x-on:click="saveAll()"
            >
                <x-filament::icon x-show="! saving" x-cloak icon="heroicon-m-check" />
                <x-filament::icon x-show="saving" x-cloak icon="heroicon-m-arrow-path" class="filament-spreadsheet-editor__spin" />
                <span x-text="saving ? 'Saving...' : 'Save all'"></span>
            </button>
        </div>
    </div>

    <section
        x-bind:id="$id('pending-panel')"
        class="filament-spreadsheet-editor__pending"
        x-show="panelOpen"
        x-cloak
        x-transition.opacity.duration.150ms
    >
        <div class="filament-spreadsheet-editor__pending-heading">
            <h3>Pending changes</h3>
            <span x-text="`${pendingChanges.length} ${pendingChanges.length === 1 ? 'cell' : 'cells'}`"></span>
        </div>

        <div class="filament-spreadsheet-editor__pending-empty" x-show="pendingChanges.length === 0">
            No unsaved changes.
        </div>

        <div class="filament-spreadsheet-editor__pending-list" x-show="pendingChanges.length > 0">
            <template x-for="change in pendingChanges" x-bind:key="`${change.rowId}:${change.field}`">
                <div
                    class="filament-spreadsheet-editor__pending-item"
                    x-bind:class="{
                        'has-error': resultFor(change) && (resultFor(change).status !== 'success' || resultFor(change).committed === false),
                        'has-conflict': resultFor(change)?.status === 'conflict',
                    }"
                >
                    <div class="filament-spreadsheet-editor__pending-cell">
                        <strong x-text="fieldLabel(change.field)"></strong>
                        <span x-text="`Row ${change.rowId}`"></span>
                    </div>

                    <div class="filament-spreadsheet-editor__value-change">
                        <span x-text="displayValue(change.oldValue)"></span>
                        <x-filament::icon icon="heroicon-m-arrow-right" />
                        <strong x-text="displayValue(change.value)"></strong>
                    </div>

                    <template x-if="resultFor(change) && resultMessage(resultFor(change))">
                        <div class="filament-spreadsheet-editor__cell-error" role="alert">
                            <x-filament::icon icon="heroicon-m-exclamation-circle" />
                            <span x-text="resultMessage(resultFor(change))"></span>
                        </div>
                    </template>

                    <template x-if="canReloadConflict(resultFor(change))">
                        <button
                            type="button"
                            class="filament-spreadsheet-editor__reload"
                            x-on:click="reloadConflict(resultFor(change))"
                        >
                            <x-filament::icon icon="heroicon-m-arrow-path" />
                            <span>Reload cell</span>
                        </button>
                    </template>
                </div>
            </template>
        </div>
    </section>

    <section
        class="filament-spreadsheet-editor__csv"
        x-show="csvPanelOpen"
        x-cloak
    >
        <div class="filament-spreadsheet-editor__pending-heading">
            <h3>CSV import</h3>
            <button
                type="button"
                class="filament-spreadsheet-editor__icon-button"
                x-on:click="csvPanelOpen = false"
                title="Close CSV import"
                aria-label="Close CSV import"
            >
                <x-filament::icon icon="heroicon-m-x-mark" />
            </button>
        </div>

        <template x-if="csvErrors.length > 0">
            <div class="filament-spreadsheet-editor__csv-errors" role="alert">
                <template x-for="error in csvErrors" x-bind:key="error">
                    <p x-text="error"></p>
                </template>
            </div>
        </template>

        <template x-if="csvPreview">
            <div class="filament-spreadsheet-editor__csv-body">
                <div class="filament-spreadsheet-editor__csv-summary">
                    <strong x-text="`${csvPreview.total_rows} rows`"></strong>
                    <span>Previewing the first 20 rows</span>
                </div>

                <div class="filament-spreadsheet-editor__csv-mapping">
                    <template x-for="header in csvPreview.headers" x-bind:key="header">
                        <label>
                            <span x-text="header"></span>
                            <select x-model="csvMapping[header]">
                                <option value="">Do not import</option>
                                <template x-for="column in csvPreview.columns" x-bind:key="column.field">
                                    <option x-bind:value="column.field" x-text="column.label"></option>
                                </template>
                            </select>
                        </label>
                    </template>
                </div>

                <div class="filament-spreadsheet-editor__csv-options">
                    <label>
                        <span>Match rows by</span>
                        <select x-model="csvMatchBy">
                            <option value="primary" x-text="`Primary key (${csvPreview.primary_key})`"></option>
                            <option
                                x-show="csvPreview.unique_column"
                                value="unique"
                                x-text="`Unique column (${csvPreview.unique_column})`"
                            ></option>
                        </select>
                    </label>

                    <label
                        class="filament-spreadsheet-editor__csv-queue"
                        x-show="csvPreview.total_rows > maxSyncImportRows"
                    >
                        <input type="checkbox" x-model="csvQueue" />
                        <span>Queue this large import</span>
                    </label>
                </div>

                <div class="filament-spreadsheet-editor__csv-preview">
                    <table>
                        <thead>
                            <tr>
                                <template x-for="header in csvPreview.headers" x-bind:key="header">
                                    <th x-text="header"></th>
                                </template>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="row in csvPreview.preview" x-bind:key="row.line">
                                <tr>
                                    <template x-for="header in csvPreview.headers" x-bind:key="header">
                                        <td x-text="displayValue(row[header])"></td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <template x-if="csvResult?.row_errors?.length">
                    <div class="filament-spreadsheet-editor__csv-errors" role="alert">
                        <template x-for="error in csvResult.row_errors" x-bind:key="`${error.line}:${error.field}`">
                            <p x-text="`Line ${error.line}, ${error.field}: ${(error.errors ?? []).join(' ')}`"></p>
                        </template>
                    </div>
                </template>

                <div class="filament-spreadsheet-editor__csv-footer">
                    <span
                        x-show="csvResult?.applied"
                        x-text="`${csvResult?.updated_rows ?? 0} rows updated`"
                    ></span>
                    <span x-show="csvResult?.queued">Import queued</span>
                    <button
                        type="button"
                        class="filament-spreadsheet-editor__save"
                        x-bind:disabled="csvImporting"
                        x-on:click="applyCsvImport()"
                    >
                        <x-filament::icon icon="heroicon-m-arrow-up-tray" />
                        <span x-text="csvImporting ? 'Importing...' : 'Apply import'"></span>
                    </button>
                </div>
            </div>
        </template>
    </section>

    <div x-ref="grid" wire:ignore class="filament-spreadsheet-editor__grid"></div>
</div>
