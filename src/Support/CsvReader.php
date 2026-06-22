<?php

namespace Mivento\FilamentSpreadsheetEditor\Support;

use Generator;
use RuntimeException;

class CsvReader
{
    public function __construct(
        protected CsvImportStore $store,
    ) {
        //
    }

    /**
     * @return array<int, string>
     */
    public function headers(string $token): array
    {
        $stream = $this->store->readStream($token);

        try {
            $headers = fgetcsv($stream);
        } finally {
            fclose($stream);
        }

        if (! is_array($headers)) {
            throw new RuntimeException('The CSV file does not contain a header row.');
        }

        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]) ?? (string) $headers[0];
        $headers = array_map(fn (mixed $header): string => trim((string) $header), $headers);

        if (in_array('', $headers, true)) {
            throw new RuntimeException('CSV headers cannot be empty.');
        }

        if (count(array_unique($headers)) !== count($headers)) {
            throw new RuntimeException('CSV headers must be unique.');
        }

        return $headers;
    }

    /**
     * @return Generator<int, array{line: int, values: array<string, string|null>}>
     */
    public function rows(string $token): Generator
    {
        $headers = $this->headers($token);
        $stream = $this->store->readStream($token);

        try {
            fgetcsv($stream);
            $line = 1;

            while (($values = fgetcsv($stream)) !== false) {
                $line++;

                if ($this->emptyRow($values)) {
                    continue;
                }

                $values = array_pad($values, count($headers), null);
                $values = array_slice($values, 0, count($headers));

                yield [
                    'line' => $line,
                    'values' => array_combine($headers, $values),
                ];
            }
        } finally {
            fclose($stream);
        }
    }

    public function countRows(string $token): int
    {
        $count = 0;

        foreach ($this->rows($token) as $_row) {
            $count++;
        }

        return $count;
    }

    /**
     * @param  array<int, mixed>  $values
     */
    protected function emptyRow(array $values): bool
    {
        return collect($values)->every(
            fn (mixed $value): bool => trim((string) $value) === '',
        );
    }
}
