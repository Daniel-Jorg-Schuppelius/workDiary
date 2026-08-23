{{--
  Created on   : Sat Aug 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : accounting.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Gemeinsame PDF-Ausgabe der Finanzberichte (Feature 125, MVP-682). Sie
  rendert dieselben Zeilen wie CSV und XLSX — ein ausgedrucktes Blatt kann
  deshalb nicht mehr aussagen als die Datei. Der Vorschau-Hinweis steht mit
  auf dem Papier: Ohne ihn sähe die Seite aus wie eine Erklärung.
--}}
@extends('reports.pdf.layout')

@section('pdf-title', $title . ' – ' . $context['from'] . ' bis ' . $context['to'])
@section('pdf-heading', $title)

@section('pdf-meta')
    {{ __('Zeitraum') }}:
    <strong>{{ \Carbon\Carbon::parse($context['from'])->fdate() }}</strong> bis
    <strong>{{ \Carbon\Carbon::parse($context['to'])->fdate() }}</strong> ·
    {{ __('accounting.ledger.field.profit_determination') }}: {{ $context['profile_label'] ?? $context['profile'] }} ·
    {{ __('accounting.ledger.field.base_currency') }}: {{ $context['currency'] }} ·
    {{ __('accounting.taxation.title') }}: {{ $context['taxation_label'] ?? $context['taxation'] }} ·
    {{ __('Erstellt') }}: {{ now()->fdatetime() }}
@endsection

@section('pdf-table')
    @if (! empty($notice))
        <p style="margin:0 0 8px; padding:6px 8px; border:1px solid #d0b000; background:#fffbe6; font-size:9px;">
            {{ $notice }}
        </p>
    @endif

    @php $header = $rows[0] ?? []; @endphp
    <table class="data">
        <thead>
            <tr>
                @foreach ($header as $index => $cell)
                    <th @class(['right' => $index > 0])>{{ $cell }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse (array_slice($rows, 1) as $row)
                <tr>
                    @foreach ($row as $index => $cell)
                        <td @class(['right' => $index > 0])>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ max(count($header), 1) }}" style="text-align:center; color:#888;">
                        {{ __('accounting.reports.empty') }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
