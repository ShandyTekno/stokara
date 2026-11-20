<?php

namespace App\Exports;

use App\Support\ReportFormatter;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReportExport implements FromArray, WithTitle, WithStyles, WithColumnFormatting, WithColumnWidths
{
    private array $data;
    private int $headerRow;

    public function __construct(
        private readonly array $report,
        ?string $storeName,
        string $generatedAt,
    ) {
        $columns = $report['columns'];

        // Judul, filter, dan ringkasan di atas tabel; teks diformat agar langsung terbaca.
        $data = [[$report['title']], [$storeName ?? '']];
        foreach ($report['filters_text'] as $line) {
            $data[] = [$line];
        }
        $data[] = ['Dibuat: '.$generatedAt];
        $data[] = [''];

        foreach ($report['summary'] as $item) {
            $data[] = [$item['label'], ReportFormatter::display($item['value'], $item['type'])];
        }
        $data[] = [''];

        $this->headerRow = count($data) + 1;
        $data[] = array_column($columns, 'label');

        foreach ($report['rows'] as $row) {
            $data[] = array_map(
                fn (array $c) => ReportFormatter::excelValue($row[$c['key']] ?? null, $c['type']),
                $columns,
            );
        }

        if (! empty($report['notes'])) {
            $data[] = [''];
            foreach ($report['notes'] as $note) {
                $data[] = [$note];
            }
        }

        $this->data = $data;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function title(): string
    {
        return mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '', $this->report['title']), 0, 31);
    }

    public function styles(Worksheet $sheet): array
    {
        $lastColumn = Coordinate::stringFromColumnIndex(count($this->report['columns']));

        return [
            'A1' => ['font' => ['bold' => true, 'size' => 14]],
            "A{$this->headerRow}:{$lastColumn}{$this->headerRow}" => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A8A']],
            ],
        ];
    }

    public function columnFormats(): array
    {
        $formats = [];
        foreach ($this->report['columns'] as $i => $column) {
            $letter = Coordinate::stringFromColumnIndex($i + 1);
            if ($column['type'] === 'money') {
                $formats[$letter] = '#,##0.00';
            } elseif ($column['type'] === 'number') {
                $formats[$letter] = '#,##0';
            }
        }

        return $formats;
    }

    public function columnWidths(): array
    {
        $widths = [];
        foreach ($this->report['columns'] as $i => $column) {
            $width = match ($column['type']) {
                'money' => 18,
                'number' => 14,
                'status' => 16,
                default => 26,
            };
            $widths[Coordinate::stringFromColumnIndex($i + 1)] = $i === 0 ? max($width, 28) : $width;
        }

        return $widths;
    }
}
