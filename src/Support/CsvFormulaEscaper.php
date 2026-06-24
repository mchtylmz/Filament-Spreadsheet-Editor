<?php

namespace Mivento\FilamentSpreadsheetEditor\Support;

class CsvFormulaEscaper
{
    public static function escape(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        $firstCharacter = mb_substr(ltrim($value), 0, 1);

        if (! in_array($firstCharacter, ['=', '+', '-', '@'], true)) {
            return $value;
        }

        if (str_starts_with($value, "'")) {
            return $value;
        }

        return "'".$value;
    }
}
