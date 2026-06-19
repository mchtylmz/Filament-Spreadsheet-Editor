<div
    x-data="filamentSpreadsheetEditor({
        adapter: @js($adapter ?? 'tabulator'),
        options: @js($options ?? []),
    })"
    x-init="mount($refs.grid)"
    wire:ignore
    class="filament-spreadsheet-editor"
>
    <div x-ref="grid" class="filament-spreadsheet-editor__grid"></div>
</div>
