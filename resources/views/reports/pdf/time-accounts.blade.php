@extends('reports.pdf.layout')

@section('pdf-title', __('Zeitkonten (Auswertung)') . ' – ' . $account->name)
@section('pdf-heading', __('Zeitkonto') . ' ' . $account->name)

@section('pdf-meta')
    {{ __('Zeitraum') }}: <strong>{{ \Carbon\Carbon::parse($from)->fdate() }}</strong> –
    <strong>{{ \Carbon\Carbon::parse($to)->fdate() }}</strong> · {{ now()->fdatetime() }}
@endsection

@section('pdf-table')
    <table class="data">
        <thead>
            <tr>
                <th>{{ __('Mitarbeiter') }}</th>
                <th class="right">{{ __('Anfangsstand') }}</th>
                <th class="right">{{ __('Umsatz') }}</th>
                <th class="right">{{ __('Endstand') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['user']->name }}</td>
                    <td class="right">{{ $account->unit->format($row['opening']) }}</td>
                    <td class="right">{{ $account->unit->format($row['turnover']) }}</td>
                    <td class="right">{{ $account->unit->format($row['closing']) }}</td>
                </tr>
            @empty
                <tr><td colspan="4">{{ __('Keine Buchungen im gewählten Zeitraum.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
