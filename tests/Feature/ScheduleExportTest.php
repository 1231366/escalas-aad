<?php

use App\Models\Employee;
use App\Models\Organization;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\ShiftType;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->admin = User::factory()->admin()->inOrganization($this->org)->create();

    $this->shiftM = ShiftType::factory()->for($this->org)->create();
    $this->shiftT = ShiftType::factory()->tarde()->for($this->org)->create();
    $this->shiftN = ShiftType::factory()->noite()->for($this->org)->create();

    // 2026-08-01 é sábado, 2026-08-02 é domingo — dá um fim de semana no período.
    $this->schedule = Schedule::factory()->for($this->org)->create([
        'period_start' => '2026-08-01',
        'period_end' => '2026-08-05',
    ]);

    $this->ana = Employee::factory()->for($this->org)->create(['name' => 'Ana Silva']);
    $this->beatriz = Employee::factory()->for($this->org)->create(['name' => 'Beatriz Costa']);
    $this->carla = Employee::factory()->for($this->org)->create(['name' => 'Carla Nunes']);

    $assign = function (Employee $employee, string $date, ?ShiftType $shift) {
        ShiftAssignment::factory()->create([
            'schedule_id' => $this->schedule->id,
            'employee_id' => $employee->id,
            'date' => $date,
            'shift_type_id' => $shift?->id,
        ]);
    };

    // Ana: trabalha os dois dias de fim de semana, tem 2 folgas.
    $assign($this->ana, '2026-08-01', $this->shiftM); // sáb
    $assign($this->ana, '2026-08-02', $this->shiftM); // dom
    $assign($this->ana, '2026-08-03', null); // folga
    $assign($this->ana, '2026-08-04', null); // folga
    $assign($this->ana, '2026-08-05', $this->shiftM);

    // Beatriz: trabalha um dia do fim de semana, tem 2 folgas.
    $assign($this->beatriz, '2026-08-01', $this->shiftT); // sáb
    $assign($this->beatriz, '2026-08-02', null); // dom, folga
    $assign($this->beatriz, '2026-08-03', $this->shiftN);
    $assign($this->beatriz, '2026-08-04', $this->shiftT);
    $assign($this->beatriz, '2026-08-05', null); // folga

    // Carla: folga todo o fim de semana.
    $assign($this->carla, '2026-08-01', null); // sáb, folga
    $assign($this->carla, '2026-08-02', null); // dom, folga
    $assign($this->carla, '2026-08-03', $this->shiftM);
    $assign($this->carla, '2026-08-04', $this->shiftN);
    $assign($this->carla, '2026-08-05', $this->shiftT);
});

function loadDownloadedSpreadsheet($response): Spreadsheet
{
    $path = tempnam(sys_get_temp_dir(), 'escala-export-').'.xlsx';
    file_put_contents($path, $response->streamedContent());

    $spreadsheet = IOFactory::createReaderForFile($path)->load($path);
    unlink($path);

    return $spreadsheet;
}

test('admin can download the monthly schedule as xlsx', function () {
    $response = $this->actingAs($this->admin)
        ->get("/admin/escalas/{$this->schedule->id}/excel");

    $response->assertOk();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $spreadsheet = loadDownloadedSpreadsheet($response);

    expect($spreadsheet->getSheetNames())->toBe(['Escala', 'Resumo']);

    $escala = $spreadsheet->getSheetByName('Escala');

    // Linha 2 é a Ana (ordem alfabética), coluna B é o dia 1 (sáb) = turno M.
    expect($escala->getCell('A2')->getValue())->toBe('Ana Silva')
        ->and($escala->getCell('B2')->getValue())->toBe($this->shiftM->code);

    // Beatriz (linha 3): 2 folgas, 1 fim de semana trabalhado (sáb).
    expect($escala->getCell('A3')->getValue())->toBe('Beatriz Costa');
    $headerRow = 1;
    $dayCount = 5;
    $totalHoursCol = 2 + $dayCount;
    $daysOffCol = $totalHoursCol + 2;
    $weekendCol = $daysOffCol + 1;

    $daysOffColLetter = Coordinate::stringFromColumnIndex($daysOffCol);
    $weekendColLetter = Coordinate::stringFromColumnIndex($weekendCol);

    expect((int) $escala->getCell($daysOffColLetter.'3')->getValue())->toBe(2)
        ->and((int) $escala->getCell($weekendColLetter.'3')->getValue())->toBe(1);

    // Resumo: dia 1 (sáb, linha 2), coluna do turno M tem 1 colaboradora (Ana).
    $resumo = $spreadsheet->getSheetByName('Resumo');
    $mColLetter = null;
    foreach (range('C', 'E') as $col) {
        if ($resumo->getCell($col.'1')->getValue() === $this->shiftM->code) {
            $mColLetter = $col;
        }
    }
    expect($mColLetter)->not->toBeNull()
        ->and((int) $resumo->getCell($mColLetter.'2')->getValue())->toBe(1);
});

test('employee cannot download the schedule export', function () {
    $employee = User::factory()->inOrganization($this->org)->create();

    $this->actingAs($employee)
        ->get("/admin/escalas/{$this->schedule->id}/excel")
        ->assertForbidden();
});

test('schedule export is tenant-scoped', function () {
    $orgB = Organization::factory()->create();
    $adminB = User::factory()->admin()->inOrganization($orgB)->create();

    $this->actingAs($adminB)
        ->get("/admin/escalas/{$this->schedule->id}/excel")
        ->assertNotFound();
});
