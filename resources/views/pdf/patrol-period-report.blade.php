{{--
  Created on   : Thu Oct 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : patrol-period-report.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Rundgangsbericht über einen Zeitraum (MVP-1292): je Lauf Soll, pünktlich,
  verspätet, verpasst und Begründung — Abweichungen werden gezeigt, nie geglättet.
--}}

<x-pdf-layout pdf-type="report" :pdf-title="__('patrol.report.title')">
    <x-slot:documentMeta>
        {{ __('patrol.report.title') }} · {{ $from->format('d.m.Y') }}–{{ $to->format('d.m.Y') }}
        · {{ __('Erzeugt am :date', ['date' => now()->format('d.m.Y')]) }}
    </x-slot:documentMeta>

    <h1 style="font-size:14pt;margin:0 0 2mm 0">{{ __('patrol.report.title') }}</h1>
    <p style="font-size:9pt;color:#555;margin:0 0 4mm 0">
        {{ $from->format('d.m.Y') }}–{{ $to->format('d.m.Y') }}
        · {{ __('patrol.report.summary', ['runs' => $totals['runs'], 'completed' => $totals['completed'], 'aborted' => $totals['aborted']]) }}
        · {{ __('patrol.report.punctuality') }}: {{ $totals['punctuality'] !== null ? $totals['punctuality'] . ' %' : '—' }}
    </p>

    <table style="width:100%;border-collapse:collapse;font-size:8pt">
        <thead>
            <tr>
                <th style="text-align:left;border-bottom:1px solid #999;padding:1mm">{{ __('Beginn') }}</th>
                <th style="text-align:left;border-bottom:1px solid #999;padding:1mm">{{ __('Route') }}</th>
                <th style="text-align:left;border-bottom:1px solid #999;padding:1mm">{{ __('Objekt') }}</th>
                <th style="text-align:left;border-bottom:1px solid #999;padding:1mm">{{ __('Status') }}</th>
                <th style="text-align:right;border-bottom:1px solid #999;padding:1mm">{{ __('patrol.report.expected') }}</th>
                <th style="text-align:right;border-bottom:1px solid #999;padding:1mm">{{ __('patrol.report.onTime') }}</th>
                <th style="text-align:right;border-bottom:1px solid #999;padding:1mm">{{ __('patrol.report.late') }}</th>
                <th style="text-align:right;border-bottom:1px solid #999;padding:1mm">{{ __('patrol.report.missed') }}</th>
                <th style="text-align:left;border-bottom:1px solid #999;padding:1mm">{{ __('patrol.report.note') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                @php($run = $row['run'])
                <tr>
                    <td style="padding:1mm;border-bottom:1px solid #eee">{{ $run->started_at?->orgTz()->format('d.m.Y H:i') }}</td>
                    <td style="padding:1mm;border-bottom:1px solid #eee">{{ $run->route?->name }}</td>
                    <td style="padding:1mm;border-bottom:1px solid #eee">{{ $run->route?->site?->name ?? '—' }}</td>
                    <td style="padding:1mm;border-bottom:1px solid #eee">{{ $run->status->label() }}</td>
                    <td style="text-align:right;padding:1mm;border-bottom:1px solid #eee">{{ $row['expected'] }}</td>
                    <td style="text-align:right;padding:1mm;border-bottom:1px solid #eee">{{ $row['on_time'] }}</td>
                    <td style="text-align:right;padding:1mm;border-bottom:1px solid #eee">{{ $row['late'] }}</td>
                    <td style="text-align:right;padding:1mm;border-bottom:1px solid #eee">{{ $row['missed'] }}</td>
                    <td style="padding:1mm;border-bottom:1px solid #eee">{{ $run->deviation_note ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="9" style="padding:2mm">{{ __('patrol.report.empty') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</x-pdf-layout>
