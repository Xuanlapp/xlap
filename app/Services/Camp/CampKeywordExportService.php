<?php

namespace App\Services\Camp;

use App\Models\CampRow;
use RuntimeException;
use ZipArchive;

class CampKeywordExportService
{
    /** @var array<int, string> */
    private array $headers = [
        'Product', 'Entity', 'Operation', 'Campaign Id', 'Ad Group Id', 'Portfolio Id', 'Ad Id (Read only)',
        'Keyword Id (Read only)', 'Product Targeting Id (Read only)', 'Campaign Name', 'Ad Group Name', 'Start Date',
        'End Date', 'Targeting Type', 'State', 'Daily Budget', 'SKU', 'ASIN (Informational only)',
        'Ad Group Default Bid', 'Bid', 'Keyword Text', 'Match Type', 'Bidding Strategy', 'Placement', 'Percentage',
        'Product Targeting Expression', 'Impressions', 'Clicks', 'Click-through Rate', 'Spend', 'Sales', 'Orders',
        'Units', 'Conversion Rate', 'Acos', 'CPC', 'ROAS', 'Campaign Name (Informational only)',
        'Ad Group Name (Informational only)', 'Campaign State (Informational only)', 'Ad Group State (Informational only)',
        'Ad Group Default Bid (Informational only)', 'Resolved Product Targeting Expression (Informational only)',
    ];

    /**
     * @param  iterable<CampRow>  $campRows
     */
    public function create(iterable $campRows, string $outputPath): string
    {
        $rows = [$this->headers];

        foreach ($campRows as $campRow) {
            foreach ($this->rowsForCampRow($campRow) as $row) {
                $rows[] = $row;
            }
        }

        if (count($rows) === 1) {
            throw new RuntimeException('Khong co du lieu Camp Keyword de export.');
        }

        $directory = dirname($outputPath);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $zip = new ZipArchive();
        if ($zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Khong tao duoc file export.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml($rows));
        $zip->addFromString('docProps/app.xml', $this->appXml());
        $zip->addFromString('docProps/core.xml', $this->coreXml());
        $zip->close();

        return $outputPath;
    }

    /**
     * @return array<int, array<int, string|int|float|null>>
     */
    private function rowsForCampRow(CampRow $campRow): array
    {
        $campaignName = (string) $campRow->campaign_name;
        $adGroupName = $campaignName;
        $startDate = optional($campRow->start_date)?->format('Ymd') ?? '';
        $budget = $this->formatPositiveInteger($campRow->campaign_daily_budget);
        $bid = $campRow->bid !== null ? rtrim(rtrim(number_format((float) $campRow->bid, 2, '.', ''), '0'), '.') : '';
        $portfolioId = $this->normalizePortfolioId((string) ($campRow->portfolio_id ?? ''));
        $sku = (string) ($campRow->sku_target ?? '');
        $matchType = (string) ($campRow->match_type ?: 'exact');
        $biddingStrategy = (string) ($campRow->bidding_strategy ?: 'Dynamic bids - up and down');
        $keywords = collect(explode(',', (string) $campRow->keyword))
            ->map(fn (string $keyword): string => trim($keyword))
            ->filter()
            ->values();

        $rows = [
            $this->row([
                'A' => 'Sponsored Products', 'B' => 'Campaign', 'C' => 'Create', 'D' => $campaignName,
                'F' => $portfolioId, 'J' => $campaignName, 'L' => $startDate, 'N' => 'MANUAL', 'O' => 'enabled',
                'P' => $budget, 'W' => $biddingStrategy, 'Y' => 'Percentage', 'AL' => $campaignName, 'AN' => 'paused',
            ]),
            $this->row([
                'A' => 'Sponsored Products', 'B' => 'Ad Group', 'C' => 'Create', 'D' => $campaignName, 'E' => $adGroupName,
                'K' => $adGroupName, 'O' => 'enabled', 'S' => $bid, 'Y' => 'Percentage', 'AL' => $campaignName,
                'AM' => $adGroupName, 'AN' => 'paused', 'AO' => 'enabled',
            ]),
            $this->row([
                'A' => 'Sponsored Products', 'B' => 'Product Ad', 'C' => 'Create', 'D' => $campaignName, 'E' => $adGroupName,
                'O' => 'enabled', 'Q' => $sku, 'Y' => 'Percentage', 'AL' => $campaignName, 'AM' => $adGroupName,
                'AN' => 'paused', 'AO' => '', 'AP' => 100,
            ]),
        ];

        foreach ($keywords as $keyword) {
            $rows[] = $this->row([
                'A' => 'Sponsored Products', 'B' => 'Keyword', 'C' => 'Create', 'D' => $campaignName, 'E' => $adGroupName,
                'O' => 'enabled', 'T' => $bid, 'U' => $keyword, 'V' => $matchType, 'Y' => 'Percentage',
                'AL' => $campaignName, 'AM' => $adGroupName, 'AN' => 'paused', 'AO' => '', 'AP' => 100,
            ]);
        }

        return $rows;
    }

    private function normalizePortfolioId(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (preg_match('/^([+-]?\d+)(?:\.(\d+))?[eE]\+?(\d+)$/', $value, $matches)) {
            $integer = ltrim($matches[1], '+');
            $fraction = $matches[2] ?? '';
            $exponent = (int) $matches[3];
            $digits = ltrim($integer.$fraction, '0');

            if ($digits === '') {
                return '0';
            }

            $zeros = $exponent - strlen($fraction);
            $value = $zeros >= 0
                ? $digits.str_repeat('0', $zeros)
                : substr($digits, 0, $zeros).'.'.substr($digits, $zeros);
        }

        $digitsOnly = preg_replace('/\D+/', '', $value) ?? '';

        return $digitsOnly === '' ? '' : $digitsOnly;
    }

    private function formatPositiveInteger(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return (string) ((int) $value);
    }

    /**
     * @param  array<string, string|int|float|null>  $values
     * @return array<int, string|int|float|null>
     */
    private function row(array $values): array
    {
        $row = array_fill(0, count($this->headers), '');

        foreach ($values as $column => $value) {
            $row[$this->columnIndex($column)] = $value;
        }

        foreach (range($this->columnIndex('AA'), $this->columnIndex('AK')) as $index) {
            if ($row[$index] === '') {
                $row[$index] = in_array($index, [$this->columnIndex('AC'), $this->columnIndex('AI')], true) ? '0,00%' : 0;
            }
        }

        return $row;
    }

    private function worksheetXml(array $rows): string
    {
        $rowXml = '';
        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 1;
            $rowXml .= '<row r="'.$excelRow.'">';
            foreach ($row as $columnIndex => $value) {
                $cellRef = $this->columnName($columnIndex + 1).$excelRow;
                $escaped = htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
                $rowXml .= '<c r="'.$cellRef.'" t="inlineStr"><is><t>'.$escaped.'</t></is></c>';
            }
            $rowXml .= '</row>';
        }

        $lastRow = count($rows);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<dimension ref="A1:AQ'.$lastRow.'"/>'
            .'<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            .'<sheetData>'.$rowXml.'</sheetData>'
            .'<ignoredErrors><ignoredError numberStoredAsText="1" sqref="A1:AQ'.$lastRow.'"/></ignoredErrors>'
            .'</worksheet>';
    }

    private function columnIndex(string $column): int
    {
        $index = 0;
        foreach (str_split($column) as $letter) {
            $index = $index * 26 + ord($letter) - 64;
        }

        return $index - 1;
    }

    private function columnName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $name = chr(65 + $mod).$name;
            $index = intdiv($index - $mod, 26);
        }

        return $name;
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Sponsored Products Campaigns" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts><fills count="1"><fill><patternFill patternType="none"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs></styleSheet>';
    }

    private function appXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>XLAP</Application><HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant><vt:variant><vt:i4>1</vt:i4></vt:variant></vt:vector></HeadingPairs><TitlesOfParts><vt:vector size="1" baseType="lpstr"><vt:lpstr>Sponsored Products Campaigns</vt:lpstr></vt:vector></TitlesOfParts></Properties>';
    }

    private function coreXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:creator>XLAP</dc:creator></cp:coreProperties>';
    }
}

