<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8" />
    <title>{{ __('Arbeitsbilanz') }} — {{ $label }}</title>
    <style>
        @page { margin: 18mm 14mm 18mm 14mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9.5pt; color: #1f2937; }
        h1 { font-size: 14pt; margin: 0 0 4px; }
        .meta { color: #6b7280; font-size: 9pt; margin-bottom: 12px; }
        .summary { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .summary td { padding: 6px 8px; border: 1px solid #d1d5db; }
        .summary .label { color: #6b7280; font-size: 8.5pt; text-transform: uppercase; letter-spacing: 0.05em; }
        .summary .value { font-size: 12pt; font-weight: bold; }
        .pos { color: #166534; }
        .neg { color: #991b1b; }
        table.days { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.days th, table.days td { padding: 4px 6px; border: 1px solid #d1d5db; font-size: 9pt; }
        table.days th { background: #f3f4f6; text-align: left; }
        table.days td.num { text-align: right; font-variant-numeric: tabular-nums; }
        table.days tfoot td { font-weight: bold; background: #f9fafb; }
        .footer { margin-top: 16px; font-size: 8pt; color: #6b7280; text-align: center; }
    </style>
</head>
<body>
    @php
        $fmt = function (int $minutes): string {
            $sign = $minutes < 0 ? '-' : '';
            $m = abs($minutes);
            return $sign . sprintf('%d:%02d', intdiv($m, 60), $m % 60);
        };
    @endphp

    <h1>{{ __('Arbeitsbilanz') }} — {{ $label }}</h1>
    <div class="meta">
        {{ $user->name }} ({{ $user->email }}) — {{ __('Erstellt') }}: {{ now()->format('d.m.Y H:i') }}
    </div>

    <table class="summary">
        <tr>
            <td><div class="label">{{ __('Soll') }}</div><div class="value">{{ $fmt($period->targetMinutes) }} h</div></td>
            <td><div class="label">{{ __('Anwesenheit') }}</div><div class="value">{{ $fmt($period->attendanceMinutes) }} h</div></td>
            <td><div class="label">{{ __('Pause') }}</div><div class="value">{{ $fmt($period->breakMinutes) }} h</div></td>
            <td><div class="label">{{ __('Erfasst') }}</div><div class="value">{{ $fmt($period->trackedMinutes) }} h</div></td>
            <td><div class="label">{{ __('Unverteilt') }}</div><div class="value">{{ $fmt($period->untrackedMinutes) }} h</div></td>
            <td><div class="label">{{ __('Saldo') }}</div>
                <div class="value {{ $period->balanceMinutes >= 0 ? 'pos' : 'neg' }}">{{ $fmt($period->balanceMinutes) }} h</div>
            </td>
        </tr>
    </table>

    @if (! empty($period->byActivity))
        <table class="summary">
            <tr>
                @foreach ($period->byActivity as $type => $minutes)
                    <td>
                        <div class="label">{{ \App\Models\TimeEntry::activityLabel($type) }}</div>
                        <div class="value">{{ $fmt((int) $minutes) }} h</div>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    <table class="days">
        <thead>
            <tr>
                <th>{{ __('Datum') }}</th>
                <th>{{ __('Soll') }}</th>
                <th>{{ __('Anwesenheit') }}</th>
                <th>{{ __('Pause') }}</th>
                <th>{{ __('Erfasst') }}</th>
                <th>{{ __('Unverteilt') }}</th>
                <th>{{ __('Saldo') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($period->days as $day)
                @if ($day->targetMinutes === 0 && $day->attendanceMinutes === 0 && $day->trackedMinutes === 0)
                    @continue
                @endif
                <tr>
                    <td>{{ \Carbon\Carbon::parse($day->date)->format('d.m.Y') }}</td>
                    <td class="num">{{ $fmt($day->targetMinutes) }}</td>
                    <td class="num">{{ $fmt($day->attendanceMinutes) }}</td>
                    <td class="num">{{ $fmt($day->breakMinutes) }}</td>
                    <td class="num">{{ $fmt($day->trackedMinutes) }}</td>
                    <td class="num">{{ $fmt($day->untrackedMinutes) }}</td>
                    <td class="num {{ $day->balanceMinutes >= 0 ? 'pos' : 'neg' }}">{{ $fmt($day->balanceMinutes) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td>{{ __('Summe') }}</td>
                <td class="num">{{ $fmt($period->targetMinutes) }}</td>
                <td class="num">{{ $fmt($period->attendanceMinutes) }}</td>
                <td class="num">{{ $fmt($period->breakMinutes) }}</td>
                <td class="num">{{ $fmt($period->trackedMinutes) }}</td>
                <td class="num">{{ $fmt($period->untrackedMinutes) }}</td>
                <td class="num {{ $period->balanceMinutes >= 0 ? 'pos' : 'neg' }}">{{ $fmt($period->balanceMinutes) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        workDiary — {{ __('Arbeitsbilanz') }} — {{ $period->from }} – {{ $period->to }}
    </div>
</body>
</html>
