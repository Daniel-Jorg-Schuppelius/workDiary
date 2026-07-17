@extends('reports.pdf.layout')

@section('pdf-title', 'Qualifikationsmatrix – ' . now()->fdate())
@section('pdf-heading', 'Qualifikationsmatrix')

@push('pdf-styles')
<style>
    .name { text-align: left; font-weight: bold; }
    .cell { text-align: center; font-size: 10px; }
    .valid    { background: #e6f4ea; }
    .expiring { background: #fff4cc; font-weight: bold; }
    .expired  { background: #fde2e2; font-weight: bold; color: #b30000; }
    .none     { background: #f5f5f5; color: #999; }
</style>
@endpush

@section('pdf-meta')
    Stand: {{ now()->fdatetime() }}
@endsection

@section('pdf-table')
    @php
        $cellClass = fn (?array $c) => match ($c['state'] ?? null) {
            'expired'  => 'expired',
            'expiring' => 'expiring',
            'valid'    => 'valid',
            default    => 'none',
        };
        $cellText = function (?array $c): string {
            if ($c === null) {
                return '–';
            }
            if ($c['valid_until'] === null) {
                return '✓';
            }
            return \Carbon\Carbon::parse($c['valid_until'])->format('d.m.y');
        };
    @endphp

    <table class="kpis">
        <tr>
            <td><div class="label">Mitarbeiter</div><div class="value">{{ $users->count() }}</div></td>
            <td><div class="label">Qualifikationen</div><div class="value">{{ $qualifications->count() }}</div></td>
            <td><div class="label">Zuweisungen</div><div class="value">{{ $totals['total_assignments'] }}</div></td>
            <td><div class="label">Laufen ab (≤30 T.)</div><div class="value">{{ $totals['expiring'] }}</div></td>
            <td><div class="label">Abgelaufen</div><div class="value">{{ $totals['expired'] }}</div></td>
        </tr>
    </table>

    @if ($users->isEmpty() || $qualifications->isEmpty())
        <p style="text-align:center; padding:20px; color:#888;">{{ __('Keine Daten vorhanden.') }}</p>
    @else
        {{-- Vollraster (Layout-Standardtabelle), Zellfarben je Status via Push-Styles --}}
        <table>
            <thead>
                <tr>
                    <th class="name">Mitarbeiter</th>
                    @foreach ($qualifications as $q)
                        <th>{{ $q->abbreviation ?? \Illuminate\Support\Str::limit($q->name, 12) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $u)
                    <tr>
                        <td class="name">{{ $u->name }}</td>
                        @foreach ($qualifications as $q)
                            @php $cell = $matrix[(int) $u->id][(int) $q->id] ?? null; @endphp
                            <td class="cell {{ $cellClass($cell) }}">{{ $cellText($cell) }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
