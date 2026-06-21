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
                class="filament-spreadsheet-editor__changes-toggle"
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
                class="filament-spreadsheet-editor__dirty-rows"
                x-show="dirtyRowCount > 0"
                x-cloak
                x-text="`${dirtyRowCount} dirty ${dirtyRowCount === 1 ? 'row' : 'rows'}`"
            ></span>
        </div>

        <div class="filament-spreadsheet-editor__actions">
            <button
                type="button"
                class="filament-spreadsheet-editor__discard"
                x-bind:disabled="! hasChanges || saving"
                x-on:click="discardAll()"
            >
                Discard all
            </button>

            <button
                type="button"
                class="filament-spreadsheet-editor__save"
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

    <div x-ref="grid" wire:ignore class="filament-spreadsheet-editor__grid"></div>
</div>
