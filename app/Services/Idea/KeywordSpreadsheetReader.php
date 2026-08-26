<?php

namespace App\Services\Idea;

use Illuminate\Support\Str;
use RuntimeException;

class KeywordSpreadsheetReader
{
    /**
     * Read a CSV or first XLSX worksheet and return unique keyword values.
     *
     * @return array<int, string>
     */
    public function read(string $path, string $extension): array
    {
        $rows = strtolower($extension) === 'csv' ? $this->csvRows($path) : $this->xlsxRows($path);

        if ($rows === []) {
            throw new RuntimeException('File khong co du lieu keyword.');
        }

        $header = array_shift($rows) ?: [];
        $keywordIndex = $this->keywordIndex($header);
        $values = [];

        foreach ($rows as $row) {
            $value = trim((string) ($row[$keywordIndex] ?? $row[0] ?? ''));
            if ($value !== '') {
                $values[Str::lower($value)] = $value;
            }
        }

        if ($values === []) {
            throw new RuntimeException('Khong tim thay cot Keyword hoac gia tri keyword trong file.');
        }

        return array_values($values);
    }

    private function keywordIndex(array $header): int
    {
        foreach ($header as $index => $value) {
            $name = Str::of((string) $value)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString();
            if (in_array($name, ['keyword', 'keywords', 'keyword phrase', 'search keyword', 'search term'], true)) {
                return $index;
            }
        }

        return 0;
    }

    private function csvRows(string $path): array
    {
        $handle = fopen($path, 'rb');
        if (! $handle) {
            throw new RuntimeException('Khong doc duoc file CSV.');
        }

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = array_map(fn (mixed $value): string => trim((string) $value), $row);
        }
        fclose($handle);

        return $rows;
    }

    private function xlsxRows(string $path): array
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new RuntimeException('Server chua co php-zip de doc .xlsx. Hay xuat file thanh CSV va upload lai.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Khong mo duoc file .xlsx.');
        }

        try {
            $strings = $this->sharedStrings($zip);
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        } finally {
            $zip->close();
        }

        if (! is_string($xml) || trim($xml) === '') {
            throw new RuntimeException('Khong doc duoc sheet dau tien cua file Excel.');
        }

        $sheet = simplexml_load_string($xml);
        if (! $sheet) {
            throw new RuntimeException('Noi dung Excel khong hop le.');
        }
        $sheet->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = [];
        foreach ($sheet->xpath('//a:sheetData/a:row') ?: [] as $row) {
            $row->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $values = [];
            foreach ($row->xpath('./a:c') ?: [] as $cell) {
                $cell->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                preg_match('/^[A-Z]+/i', (string) $cell['r'], $match);
                $index = $this->columnIndex($match[0] ?? '');
                if ($index === null) continue;
                $type = (string) ($cell['t'] ?? '');
                $value = trim((string) ($cell->v[0] ?? ''));
                if ($type === 's') $value = $strings[(int) $value] ?? '';
                if ($type === 'inlineStr') $value = trim((string) (($cell->xpath('./a:is/a:t')[0] ?? '')));
                $values[$index] = $value;
            }
            ksort($values);
            $rows[] = $values === [] ? [] : array_replace(array_fill(0, max(array_keys($values)) + 1, ''), $values);
        }

        return $rows;
    }

    private function sharedStrings(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if (! is_string($xml) || trim($xml) === '') return [];
        $document = simplexml_load_string($xml);
        if (! $document) return [];
        $document->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        return array_map(fn ($node): string => trim((string) $node), $document->xpath('//a:si/a:t') ?: []);
    }

    private function columnIndex(string $letters): ?int
    {
        if ($letters === '') return null;
        $index = 0;
        foreach (str_split(strtoupper($letters)) as $letter) $index = ($index * 26) + ord($letter) - 64;
        return $index - 1;
    }
}
