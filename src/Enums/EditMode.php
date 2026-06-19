<?php

namespace Mivento\FilamentSpreadsheetEditor\Enums;

enum EditMode: string
{
    case Inline = 'inline';
    case Modal = 'modal';
    case ReadOnly = 'read_only';
}
