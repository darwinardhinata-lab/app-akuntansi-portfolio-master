<?php

namespace App\Modules\CustomsReports\Exports;

use App\Modules\CustomsReports\Models\ReportPeriod;
use App\Modules\CustomsReports\Support\ReportLayout;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/** Identical field order to the on-screen reference layout. */
abstract class ReferenceReportExport extends DefaultValueBinder implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithCustomValueBinder, WithStrictNullComparison
{
    private int $number = 0;

    public function __construct(protected ReportPeriod $reportPeriod) {}

    public function query()
    {
        return $this->reportPeriod->lines()->getQuery()->orderBy('id');
    }

    public function headings(): array
    {
        return array_values(ReportLayout::columns($this->reportPeriod->report_type));
    }

    public function map($line): array
    {
        $row = ReportLayout::row($line, $this->reportPeriod->report_type, ++$this->number);
        foreach (array_keys(ReportLayout::columns($this->reportPeriod->report_type)) as $index => $field) {
            if (ReportLayout::numeric($field) && $row[$index] !== null) {
                $row[$index] = (float) $row[$index];
            }
        }
        return $row;
    }

    public function bindValue(Cell $cell, $value): bool
    {
        if (is_int($value) || is_float($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_NUMERIC);
            return true;
        }
        // Preserve leading zeros/long No Aju and prevent text being interpreted as formulas.
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }
        return parent::bindValue($cell, $value);
    }
}