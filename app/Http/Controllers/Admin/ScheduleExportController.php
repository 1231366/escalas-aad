<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ScheduleXlsx;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\ShiftType;
use App\Services\ScheduleGridBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScheduleExportController extends Controller
{
    private const WEEKDAY_ABBR = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

    public function download(Schedule $schedule): StreamedResponse
    {
        $spreadsheet = (new ScheduleXlsx)->build($schedule);
        $writer = new Xlsx($spreadsheet);

        $filename = 'escala-'.$schedule->period_start->format('Y-m').'.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function downloadPdf(Schedule $schedule, ScheduleGridBuilder $grid): \Illuminate\Http\Response
    {
        $dates = $grid->dates($schedule);
        $employees = Employee::query()->active()->orderBy('name')->get();
        $shiftTypes = ShiftType::query()->orderedByShift()->get();

        $pdf = Pdf::loadView('exports.schedule-pdf', [
            'schedule' => $schedule,
            'dates' => $dates->map(fn ($date) => [
                'day' => $date->day,
                'weekday_label' => self::WEEKDAY_ABBR[$date->dayOfWeek],
                'is_weekend' => $date->isWeekend(),
            ])->values(),
            'shiftTypes' => $shiftTypes,
            'employees' => $grid->employeeRows($schedule, $employees, $dates),
            'dayFooters' => $grid->dayFooters($schedule, $shiftTypes, $dates),
        ])->setPaper('a4', 'landscape');

        $filename = 'escala-'.$schedule->period_start->format('Y-m').'.pdf';

        return $pdf->download($filename);
    }
}
