<?php

namespace App\Support;

use DateTimeInterface;
use RuntimeException;
use ZipArchive;

/**
 * A small .xlsx writer: sheets of rows, a bold header row with a filter,
 * real numbers and real dates. Enough for a data export, without pulling in
 * a spreadsheet library.
 *
 *   $xlsx = new XlsxWriter();
 *   $xlsx->addSheet('Payments', ['Date', 'Bill', 'Amount'], $rows, ['date', 'text', 'money']);
 *   $path = $xlsx->save();
 *
 * A column type is 'text', 'number', 'money' (two decimals), 'percent' (a
 * fraction shown as %) or 'date' (a DateTimeInterface or Y-m-d string). A
 * row whose first cell is a string starting with "\0total" is drawn bold:
 * use XlsxWriter::total('Σύνολο') for that cell.
 */
class XlsxWriter
{
    private const STYLE = ['text' => 0, 'number' => 0, 'header' => 1, 'date' => 2, 'money' => 3, 'percent' => 5];
    private const BOLD = ['text' => 6, 'number' => 6, 'header' => 1, 'date' => 7, 'money' => 4, 'percent' => 8];
    private const TOTAL = "\0total";

    /** @var array<int, array{name: string, xml: string}> */
    private array $sheets = [];

    public static function total(string $label): string
    {
        return self::TOTAL . $label;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  iterable<array<int, mixed>>  $rows
     * @param  array<int, string>  $types  one per column; 'text' when missing
     */
    public function addSheet(string $name, array $headers, iterable $rows, array $types = []): static
    {
        $widths = array_map(fn ($h) => mb_strlen($h) + 2, $headers);
        $body = [];
        $r = 1;

        $body[] = $this->row($r, array_map(fn ($h) => ['header', $h], $headers));

        foreach ($rows as $row) {
            $r++;
            $row = array_values($row);
            $bold = isset($row[0]) && is_string($row[0]) && str_starts_with($row[0], self::TOTAL);
            if ($bold) {
                $row[0] = substr($row[0], strlen(self::TOTAL));
            }
            $cells = [];
            foreach ($row as $i => $value) {
                $type = $types[$i] ?? 'text';
                $cells[] = [$bold ? 'bold:' . $type : $type, $value];
                $widths[$i] = max($widths[$i] ?? 8, match ($type) {
                    'date' => 12,
                    'money', 'number', 'percent' => 13,
                    default => min(60, mb_strlen((string) $value) + 2),
                });
            }
            $body[] = $this->row($r, $cells);
        }

        $cols = '';
        foreach ($widths as $i => $w) {
            $n = $i + 1;
            $cols .= '<col min="' . $n . '" max="' . $n . '" width="' . max(8, $w) . '" customWidth="1"/>';
        }
        $lastCol = $this->column(max(count($headers), 1) - 1);

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<cols>' . $cols . '</cols>'
            . '<sheetData>' . implode('', $body) . '</sheetData>'
            . ($r > 1 ? '<autoFilter ref="A1:' . $lastCol . $r . '"/>' : '')
            . '</worksheet>';

        $this->sheets[] = ['name' => $this->sheetName($name), 'xml' => $xml];

        return $this;
    }

    /** Writes the workbook to a temporary file and returns its path. */
    public function save(?string $path = null): string
    {
        if (! $this->sheets) {
            throw new RuntimeException('A workbook needs at least one sheet.');
        }
        $path ??= tempnam(sys_get_temp_dir(), 'xlsx');

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot write {$path}.");
        }

        $overrides = '';
        $sheetsXml = '';
        $rels = '';
        foreach ($this->sheets as $i => $sheet) {
            $n = $i + 1;
            $zip->addFromString("xl/worksheets/sheet{$n}.xml", $sheet['xml']);
            $overrides .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $sheetsXml .= '<sheet name="' . $this->esc($sheet['name']) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
            $rels .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
        }
        $styleRel = count($this->sheets) + 1;

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . $overrides . '</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $sheetsXml . '</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels
            . '<Relationship Id="rId' . $styleRel . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->close();

        return $path;
    }

    /** @param array<int, array{0: string, 1: mixed}> $cells */
    private function row(int $r, array $cells): string
    {
        $xml = '<row r="' . $r . '">';
        foreach ($cells as $i => [$type, $value]) {
            $bold = str_starts_with($type, 'bold:');
            $type = $bold ? substr($type, 5) : $type;
            $style = ($bold ? self::BOLD : self::STYLE)[$type] ?? 0;
            $ref = $this->column($i) . $r;

            if ($value === null || $value === '') {
                $xml .= $style ? '<c r="' . $ref . '" s="' . $style . '"/>' : '';
                continue;
            }
            if ($type === 'date' && ($serial = $this->dateSerial($value)) !== null) {
                $xml .= '<c r="' . $ref . '" s="' . $style . '"><v>' . $serial . '</v></c>';
            } elseif (in_array($type, ['number', 'money', 'percent'], true) && is_numeric($value)) {
                $xml .= '<c r="' . $ref . '" s="' . $style . '"><v>' . (0 + $value) . '</v></c>';
            } else {
                $xml .= '<c r="' . $ref . '" s="' . ($bold || $type === 'header' ? $style : 0) . '" t="inlineStr"><is><t xml:space="preserve">'
                    . $this->esc((string) $value) . '</t></is></c>';
            }
        }

        return $xml . '</row>';
    }

    private function dateSerial(mixed $value): ?int
    {
        if (is_string($value)) {
            $value = \DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10)) ?: null;
        }
        if (! $value instanceof DateTimeInterface) {
            return null;
        }
        $epoch = new \DateTimeImmutable('1899-12-30');
        $day = new \DateTimeImmutable($value->format('Y-m-d'));

        return (int) $epoch->diff($day)->format('%r%a');
    }

    private function column(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26) . $s;
        }

        return $s;
    }

    /** Excel names: at most 31 characters, none of []:*?/\, unique. */
    private function sheetName(string $name): string
    {
        $base = mb_substr(trim(str_replace(['[', ']', ':', '*', '?', '/', '\\'], ' ', $name)) ?: 'Sheet', 0, 31);
        $taken = array_column($this->sheets, 'name');
        $candidate = $base;
        for ($n = 2; in_array($candidate, $taken, true); $n++) {
            $candidate = mb_substr($base, 0, 28) . " ({$n})";
        }

        return $candidate;
    }

    private function esc(string $s): string
    {
        // Control characters are not allowed in XML 1.0 at all.
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s);

        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function styles(): string
    {
        // cellXfs: 0 text · 1 header · 2 date · 3 money · 4 bold money · 5 percent
        //          6 bold text · 7 bold date · 8 bold percent
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="2"><numFmt numFmtId="164" formatCode="dd/mm/yyyy"/><numFmt numFmtId="165" formatCode="#,##0.00"/></numFmts>'
            . '<fonts count="3"><font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FF0F172A"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFFCD34D"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left/><right/><top style="thin"><color rgb="FF94A3B8"/></top><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="9">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="165" fontId="2" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1"/>'
            . '<xf numFmtId="10" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"/>'
            . '<xf numFmtId="164" fontId="2" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1"/>'
            . '<xf numFmtId="10" fontId="2" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1"/>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }
}
