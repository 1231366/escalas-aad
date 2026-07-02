<?php

namespace App\Exports;

use App\Models\Employee;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\ShiftType;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exportação Excel da escala mensal (issue #19).
 *
 * Folha "Escala": grelha funcionária x dia com os três indicadores exigidos
 * pela folha do cliente (ADR-0004): horas/semana e nº folgas por pessoa.
 * Folha "Resumo": cobertura efetiva (colaboradoras por turno/dia), o terceiro
 * indicador do ADR-0004.
 */
class ScheduleXlsx
{
    private const WEEKDAY_ABBR = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

    private const WEEKEND_FILL = 'FFE5E7EB';

    private const SHIFT_ORDER = ['M' => 0, 'T' => 1, 'N' => 2];

    public function build(Schedule $schedule): Spreadsheet
    {
        $schedule->loadMissing(['assignments.employee', 'assignments.shiftType']);

        $dates = collect(CarbonPeriod::create($schedule->period_start, $schedule->period_end))->values();

        $employees = $schedule->assignments
            ->pluck('employee')
            ->filter()
            ->unique('id')
            ->sortBy(fn (Employee $employee) => $employee->name)
            ->values();

        $assignmentsByEmployee = $schedule->assignments
            ->groupBy('employee_id')
            ->map(fn (Collection $assignments) => $assignments->keyBy(fn (ShiftAssignment $a) => $a->date->toDateString()));

        $shiftTypes = $schedule->assignments
            ->pluck('shiftType')
            ->filter()
            ->unique('id')
            ->sortBy(fn (ShiftType $shiftType) => self::SHIFT_ORDER[$shiftType->code] ?? 99)
            ->values();

        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        $this->buildScheduleSheet($spreadsheet, $dates, $employees, $assignmentsByEmployee);
        $this->buildSummarySheet($spreadsheet, $dates, $schedule, $shiftTypes);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * @param  Collection<int, CarbonInterface>  $dates
     * @param  Collection<int, Employee>  $employees
     * @param  Collection<int, Collection<string, ShiftAssignment>>  $assignmentsByEmployee
     */
    private function buildScheduleSheet(Spreadsheet $spreadsheet, Collection $dates, Collection $employees, Collection $assignmentsByEmployee): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Escala');

        $dayCount = $dates->count();
        $totalHoursCol = 2 + $dayCount;
        $avgHoursCol = $totalHoursCol + 1;
        $daysOffCol = $avgHoursCol + 1;
        $weekendCol = $daysOffCol + 1;

        $sheet->setCellValue('A1', 'Funcionária');
        foreach ($dates as $i => $date) {
            $col = Coordinate::stringFromColumnIndex(2 + $i);
            $sheet->setCellValue($col.'1', $date->format('j')."\n".self::WEEKDAY_ABBR[$date->dayOfWeek]);
            if ($date->isWeekend()) {
                $this->fillCell($sheet, $col.'1', self::WEEKEND_FILL);
            }
        }
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($totalHoursCol).'1', 'Total horas');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($avgHoursCol).'1', 'Média horas/semana');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($daysOffCol).'1', 'Nº folgas');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($weekendCol).'1', 'Fins de semana trabalhados');

        $lastCol = Coordinate::stringFromColumnIndex($weekendCol);
        $sheet->getStyle('A1:'.$lastCol.'1')->getFont()->setBold(true);
        $sheet->getStyle('A1:'.$lastCol.'1')->getAlignment()
            ->setWrapText(true)
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(30);

        $weeksInPeriod = $dayCount > 0 ? $dayCount / 7 : 0;

        foreach ($employees as $rowIndex => $employee) {
            $row = 2 + $rowIndex;
            $sheet->setCellValue('A'.$row, $employee->name);

            $totalHours = 0.0;
            $daysOff = 0;
            $weekendsWorked = 0;

            $employeeAssignments = $assignmentsByEmployee->get($employee->id, collect());

            foreach ($dates as $i => $date) {
                $col = Coordinate::stringFromColumnIndex(2 + $i);
                $coordinate = $col.$row;
                $isWeekend = $date->isWeekend();

                /** @var ShiftAssignment|null $assignment */
                $assignment = $employeeAssignments->get($date->toDateString());

                if ($assignment === null) {
                    if ($isWeekend) {
                        $this->fillCell($sheet, $coordinate, self::WEEKEND_FILL);
                    }

                    continue;
                }

                if ($assignment->isDayOff()) {
                    $daysOff++;
                    if ($isWeekend) {
                        $this->fillCell($sheet, $coordinate, self::WEEKEND_FILL);
                    }

                    continue;
                }

                $shiftType = $assignment->shiftType;
                $totalHours += (float) ($shiftType->hours ?? 0);
                if ($isWeekend) {
                    $weekendsWorked++;
                }

                $sheet->setCellValue($coordinate, $shiftType->code);
                $this->fillCell($sheet, $coordinate, $this->hexToArgb($shiftType->color));
                $sheet->getStyle($coordinate)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                $sheet->getStyle($coordinate)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            $sheet->setCellValue(Coordinate::stringFromColumnIndex($totalHoursCol).$row, $totalHours);
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($avgHoursCol).$row, $weeksInPeriod > 0 ? round($totalHours / $weeksInPeriod, 2) : 0);
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($daysOffCol).$row, $daysOff);
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($weekendCol).$row, $weekendsWorked);
        }

        $lastRow = 1 + $employees->count();
        $sheet->getStyle('A1:'.$lastCol.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $sheet->getColumnDimension('A')->setWidth(24);
        foreach ($dates as $i => $date) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex(2 + $i))->setWidth(5);
        }
        foreach ([$totalHoursCol, $avgHoursCol, $daysOffCol, $weekendCol] as $col) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setWidth(16);
        }

        $sheet->freezePane('B2');
    }

    /**
     * @param  Collection<int, CarbonInterface>  $dates
     * @param  Collection<int, ShiftType>  $shiftTypes
     */
    private function buildSummarySheet(Spreadsheet $spreadsheet, Collection $dates, Schedule $schedule, Collection $shiftTypes): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Resumo');

        $sheet->setCellValue('A1', 'Dia');
        $sheet->setCellValue('B1', 'Dia da semana');
        foreach ($shiftTypes as $i => $shiftType) {
            $col = Coordinate::stringFromColumnIndex(3 + $i);
            $sheet->setCellValue($col.'1', $shiftType->code);
        }

        $lastCol = Coordinate::stringFromColumnIndex(2 + max($shiftTypes->count(), 1));
        $sheet->getStyle('A1:'.$lastCol.'1')->getFont()->setBold(true);

        $countsByDateAndShift = $schedule->assignments
            ->whereNotNull('shift_type_id')
            ->groupBy(fn (ShiftAssignment $a) => $a->date->toDateString().'|'.$a->shift_type_id)
            ->map->count();

        foreach ($dates as $rowIndex => $date) {
            $row = 2 + $rowIndex;
            $sheet->setCellValue('A'.$row, $date->format('d/m'));
            $sheet->setCellValue('B'.$row, self::WEEKDAY_ABBR[$date->dayOfWeek]);

            foreach ($shiftTypes as $i => $shiftType) {
                $col = Coordinate::stringFromColumnIndex(3 + $i);
                $key = $date->toDateString().'|'.$shiftType->id;
                $sheet->setCellValue($col.$row, $countsByDateAndShift->get($key, 0));
            }

            if ($date->isWeekend()) {
                $sheet->getStyle('A'.$row.':'.$lastCol.$row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB(self::WEEKEND_FILL);
            }
        }

        $lastRow = 1 + $dates->count();
        $sheet->getStyle('A1:'.$lastCol.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $sheet->getColumnDimension('A')->setWidth(10);
        $sheet->getColumnDimension('B')->setWidth(14);
        foreach ($shiftTypes as $i => $shiftType) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex(3 + $i))->setWidth(8);
        }

        $sheet->freezePane('C2');
    }

    private function fillCell(Worksheet $sheet, string $coordinate, string $argb): void
    {
        $sheet->getStyle($coordinate)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB($argb);
    }

    private function hexToArgb(string $hex): string
    {
        return 'FF'.strtoupper(ltrim($hex, '#'));
    }
}
