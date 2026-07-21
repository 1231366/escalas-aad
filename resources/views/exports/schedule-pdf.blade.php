<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>Escala — {{ $schedule->period_start->format('m/Y') }}</title>
    <style>
        @page {
            margin: 9mm 8mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #1f2937;
            font-size: 8pt;
        }

        .header {
            margin-bottom: 6px;
        }

        .header .brand {
            font-size: 8pt;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #6b7280;
            margin: 0 0 2px;
        }

        .header h1 {
            font-size: 16pt;
            margin: 0 0 2px;
            text-transform: capitalize;
        }

        .header .period {
            font-size: 8pt;
            color: #6b7280;
            margin: 0;
        }

        .legend {
            margin: 6px 0 8px;
        }

        .legend span.chip {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 3px;
            color: #fff;
            font-weight: bold;
            font-size: 7pt;
            margin-right: 6px;
        }

        .legend span.chip-off {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 3px;
            background: #f3f4f6;
            color: #6b7280;
            font-size: 7pt;
        }

        table.grid {
            width: 100%;
            border-collapse: collapse;
        }

        table.grid th, table.grid td {
            border: 0.5pt solid #d1d5db;
            text-align: center;
            padding: 3.5px 1px;
            font-size: 7pt;
        }

        table.grid th {
            background: #f9fafb;
            font-weight: bold;
            font-size: 6.6pt;
        }

        table.grid th.name-col, table.grid td.name-col {
            text-align: left;
            padding-left: 5px;
            width: 108px;
            font-size: 7.4pt;
            white-space: nowrap;
        }

        table.grid td.weekend, table.grid th.weekend {
            background: #eef0f3;
        }

        table.grid td.stat {
            width: 30px;
            font-weight: bold;
            color: #374151;
        }

        table.grid th.stat {
            width: 30px;
        }

        table.grid td.shift {
            color: #fff;
            font-weight: bold;
        }

        table.grid td.off {
            color: #9ca3af;
            font-size: 6pt;
        }

        .summary {
            page-break-before: always;
        }

        .summary h2 {
            font-size: 13pt;
            margin: 0 0 8px;
        }

        table.summary-table {
            width: 100%;
            border-collapse: collapse;
        }

        table.summary-table th, table.summary-table td {
            border: 0.5pt solid #d1d5db;
            text-align: center;
            padding: 3px 4px;
            font-size: 8pt;
        }

        table.summary-table th {
            background: #f9fafb;
            font-weight: bold;
        }

        table.summary-table td.weekend {
            background: #eef0f3;
        }

        table.summary-table td.ok {
            color: #15803d;
        }

        table.summary-table td.short {
            color: #b91c1c;
            font-weight: bold;
        }

        .footer-note {
            margin-top: 10px;
            font-size: 6.5pt;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="header">
        <p class="brand">Escalas AAD</p>
        <h1>Escala — {{ $schedule->period_start->translatedFormat('F Y') }}</h1>
        <p class="period">
            {{ $schedule->period_start->format('d/m/Y') }} a {{ $schedule->period_end->format('d/m/Y') }}
            &middot; gerada em {{ $schedule->generated_at?->format('d/m/Y H:i') }}
        </p>
    </div>

    <div class="legend">
        @foreach ($shiftTypes as $shiftType)
            <span class="chip" style="background: {{ $shiftType->color }};">{{ $shiftType->code }} — {{ $shiftType->name }}</span>
        @endforeach
        <span class="chip-off">F — Folga</span>
    </div>

    <table class="grid">
        <thead>
            <tr>
                <th class="name-col">Funcionária</th>
                @foreach ($dates as $date)
                    <th class="{{ $date['is_weekend'] ? 'weekend' : '' }}">{{ $date['day'] }}<br>{{ $date['weekday_label'] }}</th>
                @endforeach
                <th class="stat">h/mês</th>
                <th class="stat">h/sem.</th>
                <th class="stat">Folgas</th>
                <th class="stat">FDS</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($employees as $employee)
                <tr>
                    <td class="name-col">{{ $employee['name'] }}</td>
                    @foreach ($employee['cells'] as $i => $cell)
                        @php $isWeekend = $dates[$i]['is_weekend']; @endphp
                        @if ($cell['shift_code'])
                            @php $shiftType = $shiftTypes->firstWhere('id', $cell['shift_type_id']); @endphp
                            <td class="shift" style="background: {{ $shiftType?->color }};">{{ $cell['shift_code'] }}</td>
                        @elseif ($cell['is_day_off'])
                            <td class="off {{ $isWeekend ? 'weekend' : '' }}">F</td>
                        @else
                            <td class="{{ $isWeekend ? 'weekend' : '' }}"></td>
                        @endif
                    @endforeach
                    <td class="stat">{{ $employee['total_hours'] }}</td>
                    <td class="stat">{{ $employee['avg_weekly_hours'] }}</td>
                    <td class="stat">{{ $employee['days_off'] }}</td>
                    <td class="stat">{{ $employee['weekends_worked'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="footer-note">Escalas AAD &middot; exportado em {{ now()->format('d/m/Y H:i') }}</p>

    <div class="summary">
        <h2>Cobertura por dia</h2>
        <table class="summary-table">
            <thead>
                <tr>
                    <th>Dia</th>
                    @foreach ($shiftTypes as $shiftType)
                        <th>{{ $shiftType->code }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($dayFooters as $i => $footer)
                    <tr>
                        <td class="{{ $footer['is_weekend'] ? 'weekend' : '' }}">{{ $dates[$i]['day'] }}/{{ $dates[$i]['weekday_label'] }}</td>
                        @foreach ($footer['shifts'] as $shift)
                            <td class="{{ $shift['ok'] ? 'ok' : 'short' }} {{ $footer['is_weekend'] ? 'weekend' : '' }}">
                                {{ $shift['actual'] }}/{{ $shift['required'] }}
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>
</html>
