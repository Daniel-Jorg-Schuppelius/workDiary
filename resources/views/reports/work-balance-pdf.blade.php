@extends('reports.pdf.layout')

@section('pdf-title', __('Arbeitsbilanz') . ' — ' . $label)
@section('pdf-heading', __('Arbeitsbilanz') . ' — ' . $label)

@push('pdf-styles')
<style>
    .summary { margin-bottom: 12px; }
    .summary .label { color: #6b7280; font-size: 9px; text-transform: uppercase; letter-spacing: 0.05em; }
    .summary .value { font-size: 14px; font-weight: bold; }
    table.days { margin-top: 6px; }
    table.days tfoot td { font-weight: bold; background: #f9fafb; border-top: 1px solid #d1d5db; }
</style>
@endpush

@section('pdf-meta')
    {{ $user->name }} ({{ $user->email }}) — {{ __('Erstellt') }}: {{ now()->fdatetime() }}
@endsection

@section('pdf-table')
    @php
        $fmt = function (int $minutes): string {
            $sign = $minutes < 0 ? '-' : '';
            $m = abs($minutes);
            return $sign . sprintf('%d:%02d', intdiv($m, 60), $m % 60);
        };
    @endphp

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
                    <td>{{ \Carbon\Carbon::parse($day->date)->fdate() }}</td>
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

    <p class="small" style="text-align:center; margin-top: 16px;">
        {{ __('Arbeitsbilanz') }} — {{ $period->from }} – {{ $period->to }}
    </p>
@endsection
