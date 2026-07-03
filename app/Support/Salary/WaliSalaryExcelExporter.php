<?php

namespace App\Support\Salary;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use ZipArchive;

class WaliSalaryExcelExporter
{
    public function create(CarbonImmutable $month, Collection $rows, string $outputPath): void
    {
        $headers = ['Nhân viên','Lương cơ bản','Lương cứng biến động','Điểm hiệu suất','Đi trễ (phút)','Điểm trừ','Xin nghỉ','Số ngày được nghỉ','Nghỉ vượt','Công chuẩn','Công thực tế','Điểm tính lương','Thưởng ngày','Bổ sung','Tiền khác','Note','Tổng lương','Tiền điểm lẻ','Hoa hồng','Thực nhận'];
        $dataRows = [];

        foreach ($rows as $row) {
            $dataRows[] = [
                (string) $row->employee_name,
                $this->money((float) $row->base_salary),
                $this->money((float) $row->variable_salary),
                $this->decimal((float) $row->performance_score),
                $this->integer((float) $row->late_minutes),
                $this->integer((float) $row->late_days),
                $this->integer((float) $row->leave_days),
                $this->integer((float) $row->allowed_leave_days),
                $this->integer(max(0, (float) $row->leave_days - (float) $row->allowed_leave_days)),
                $this->integer((float) $row->standard_work_days),
                $this->integer((float) $row->actual_work_days),
                $this->decimal((float) $row->score),
                $this->money((float) $row->daily_bonus),
                $this->money((float) $row->supplement),
                $this->money((float) $row->other_money),
                (string) ($row->note ?? ''),
                $this->money((float) $row->total_salary),
                $this->money((float) $row->odd_point_money),
                $this->money((float) $row->commission),
                $this->money((float) $row->net_received),
            ];
        }

        $zip = new ZipArchive();
        if ($zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Khong tao duoc file Excel.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelsXml());
        $zip->addFromString('docProps/app.xml', $this->appXml());
        $zip->addFromString('docProps/core.xml', $this->coreXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml('Luong '.$month->format('m-Y')));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml('Danh sách lương '.$month->format('m/Y'), $headers, $dataRows));
        $zip->close();
    }

    private function money(float $value): string { return number_format($value, 0, ',', '.'); }
    private function decimal(float $value): string { return number_format($value, 1, ',', '.'); }
    private function integer(float $value): string { return number_format($value, 0, ',', '.'); }
    private function esc(string $value): string { return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }

    private function cell(string $ref, string $value, int $style = 0): string
    {
        return '<c r="'.$ref.'" s="'.$style.'" t="inlineStr"><is><t>'.$this->esc($value).'</t></is></c>';
    }

    private function sheetXml(string $title, array $headers, array $rows): string
    {
        $xmlRows = [];
        $xmlRows[] = '<row r="1" ht="24" customHeight="1">'.$this->cell('A1', $title, 1).'</row>';

        $headerCells = '';
        foreach ($headers as $index => $header) {
            $headerCells .= $this->cell($this->col($index + 1).'3', $header, 2);
        }
        $xmlRows[] = '<row r="3" ht="28" customHeight="1">'.$headerCells.'</row>';

        $rowNumber = 4;
        foreach ($rows as $row) {
            $cells = '';
            foreach ($row as $index => $value) {
                $cells .= $this->cell($this->col($index + 1).$rowNumber, (string) $value, $index === 0 || $index === 15 ? 4 : 3);
            }
            $xmlRows[] = '<row r="'.$rowNumber.'">'.$cells.'</row>';
            $rowNumber++;
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<cols><col min="1" max="1" width="18" customWidth="1"/><col min="2" max="15" width="16" customWidth="1"/><col min="16" max="16" width="48" customWidth="1"/><col min="17" max="20" width="16" customWidth="1"/></cols>'
            .'<sheetData>'.implode('', $xmlRows).'</sheetData>'
            .'<mergeCells count="1"><mergeCell ref="A1:T1"/></mergeCells>'
            .'</worksheet>';
    }

    private function col(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)).$name;
            $index = intdiv($index, 26);
        }
        return $name;
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>';
    }

    private function appXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>Microsoft Excel</Application></Properties>';
    }

    private function coreXml(): string
    {
        $now = now()->utc()->format('Y-m-d\TH:i:s\Z');
        return '<?xml version="1.0" encoding="UTF-8"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>Wali Salary Export</dc:title><dc:creator>XLAP</dc:creator><cp:lastModifiedBy>XLAP</cp:lastModifiedBy><dcterms:created xsi:type="dcterms:W3CDTF">'.$now.'</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">'.$now.'</dcterms:modified></cp:coreProperties>';
    }

    private function workbookXml(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.$this->esc($sheetName).'" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Arial"/></font><font><b/><sz val="16"/><name val="Arial"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE5E7EB"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="5"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/><xf numFmtId="0" fontId="0" fillId="2" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="top" wrapText="1"/></xf></cellXfs></styleSheet>';
    }
}
