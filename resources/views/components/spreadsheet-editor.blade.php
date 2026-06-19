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
    class="filament-spreadsheet-editor"
>
    <div class="filament-spreadsheet-editor__toolbar">
        <button
            type="button"
            class="filament-spreadsheet-editor__save"
            x-bind:disabled="! hasChanges || saving"
            x-on:click="save()"
        >
            <span x-show="! saving">Save changes</span>
            <span x-show="saving">Saving...</span>
        </button>
    </div>

    <div x-ref="grid" wire:ignore class="filament-spreadsheet-editor__grid"></div>
</div>
