<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ScheduleXlsx;
use App\Http\Controllers\Controller;
use App\Models\Schedule;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScheduleExportController extends Controller
{
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
}
