{{--
  Created on   : Mon Aug 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : subject-export-pdf.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Betroffenen-Auskunft (Art. 15 DSGVO, Feature 129); Layout wie DSFA-Bericht, ohne SVG. --}}
@extends('reports.pdf.layout')

@section('pdf-title', __('Auskunft nach Art. 15 DSGVO') . ' — ' . $payload['request_number'])
@section('pdf-heading', __('Auskunft nach Art. 15 DSGVO'))

@push('pdf-styles')
<style>
    body { line-height: 1.45; }
    h2 { margin: 16px 0 6px; border-bottom: 1px solid #d1d5db; padding-bottom: 3px; }
    table { margin-top: 6px; }
    td.k { width: 34%; color: #374151; }
    .hint { color: #6b7280; font-size: 9px; margin-top: 4px; }
</style>
@endpush

@section('pdf-meta')
    {{ __('Betroffenenfall') }}: {{ $payload['request_number'] }} ·
    {{ __('Betroffenenart') }}: {{ $payload['subject_kind_label'] }} ·
    {{ __('Betroffene Person') }}: {{ $payload['subject_label'] }} ·
    {{ __('Erstellt am') }}: {{ \Illuminate\Support\Carbon::parse($payload['generated_at'])->format('d.m.Y H:i') }}
@endsection

@section('pdf-table')
    @foreach ($payload['sections'] as $section)
        <h2>{{ $section['title'] }}</h2>

        @if (!empty($section['fields']))
            <table>
                @foreach ($section['fields'] as $field)
                    <tr>
                        <td class="k">{{ $field['label'] }}</td>
                        <td>{{ $field['value'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </table>
        @endif

        @foreach ($section['lists'] ?? [] as $listTitle => $rows)
            @if ($rows !== [])
                <h2>{{ $listTitle }}</h2>
                <table>
                    <tr>
                        @foreach (array_keys($rows[0]) as $col)
                            <th>{{ $col }}</th>
                        @endforeach
                    </tr>
                    @foreach ($rows as $row)
                        <tr>
                            @foreach ($row as $cell)
                                <td>{{ $cell ?? '—' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </table>
            @endif
        @endforeach

        @if (!empty($section['families']))
            <table>
                <tr>
                    <th>{{ __('Datenfamilie') }}</th>
                    <th class="num">{{ __('Anzahl') }}</th>
                    <th>{{ __('Zeitraum') }}</th>
                </tr>
                @foreach ($section['families'] as $family)
                    <tr>
                        <td>{{ $family['label'] }}</td>
                        <td class="num">{{ $family['count'] }}</td>
                        <td>
                            @if ($family['from'] !== null || $family['to'] !== null)
                                {{ $family['from'] ?? '…' }} – {{ $family['to'] ?? '…' }}
                            @else
                                —
                            @endif
                            @if (!empty($family['details']))
                                @foreach ($family['details'] as $dk => $dv)
                                    · {{ $dk }}: {{ $dv }}
                                @endforeach
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>
            <p class="hint">{{ __('Aggregierte Übersicht der Verknüpfungsfamilien — Detailauszüge stellt die verantwortliche Stelle auf Anforderung bereit.') }}</p>
        @endif
    @endforeach
@endsection
