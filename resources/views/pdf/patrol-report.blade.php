{{--
  Created on   : Tue Aug 19 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : patrol-report.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Rundgangsbericht (Feature 089): Nachweis je Kontrollpunkt — Soll, Ist,
  Abweichung. Abweichungen werden gezeigt, nie geglättet.
--}}

<x-pdf-layout pdf-type="report" :pdf-title="__('Rundgangsbericht: :name', ['name' => (string) $run->route?->name])">
    <x-slot:documentMeta>
        {{ __('Rundgangsbericht') }} · {{ $run->route?->name }}
        · {{ __('Erzeugt am :date', ['date' => now()->format('d.m.Y')]) }}
    </x-slot:documentMeta>

    <h1 style="font-size:14pt;margin:0 0 2mm 0">{{ __('Rundgangsbericht: :name', ['name' => (string) $run->route?->name]) }}</h1>
    <p style="font-size:9pt;color:#555;margin:0 0 4mm 0">
        {{ __('Durchgeführt von :name', ['name' => $run->starter?->name ?? '—']) }}
        · {{ $run->started_at->format('d.m.Y H:i') }}–{{ $run->finished_at?->format('H:i') ?? '—' }}
        @if ($run->route?->site) · {{ $run->route->site->name }} @endif
    </p>

    <table style="width:100%;border-collapse:collapse;font-size:8pt">
        <thead>
            <tr>
                <th style="text-align:right;border-bottom:1px solid #999;padding:1mm">#</th>
                <th style="text-align:left;border-bottom:1px solid #999;padding:1mm">{{ __('Kontrollpunkt') }}</th>
                <th style="text-align:right;border-bottom:1px solid #999;padding:1mm">{{ __('Soll (ab Start)') }}</th>
                <th style="text-align:right;border-bottom:1px solid #999;padding:1mm">{{ __('Ist') }}</th>
                <th style="text-align:right;border-bottom:1px solid #999;padding:1mm">{{ __('Abweichung') }}</th>
                <th style="text-align:left;border-bottom:1px solid #999;padding:1mm">{{ __('Befund') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($run->route?->checkpoints ?? [] as $checkpoint)
                @php($scan = $scans->get($checkpoint->id))
                <tr>
                    <td style="text-align:right;padding:1mm;border-bottom:1px solid #eee">{{ $checkpoint->position }}</td>
                    <td style="padding:1mm;border-bottom:1px solid #eee">{{ $checkpoint->label }}</td>
                    <td style="text-align:right;padding:1mm;border-bottom:1px solid #eee">+{{ $checkpoint->expected_offset_minutes }} min ± {{ $checkpoint->tolerance_minutes }}</td>
                    <td style="text-align:right;padding:1mm;border-bottom:1px solid #eee">{{ $scan?->scanned_at?->format('H:i') ?? '—' }}</td>
                    <td style="text-align:right;padding:1mm;border-bottom:1px solid #eee">{{ $scan !== null ? (($scan->delta_minutes > 0 ? '+' : '') . $scan->delta_minutes . ' min') : '—' }}</td>
                    <td style="padding:1mm;border-bottom:1px solid #eee">
                        @if ($scan === null)
                            {{ __('verpasst') }}
                        @elseif ($scan->in_window)
                            {{ __('im Fenster') }}
                        @else
                            {{ __('außerhalb des Fensters') }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($run->deviation_note)
        <h2 style="font-size:10pt;margin:4mm 0 1mm 0">{{ __('Begründung der Abweichungen') }}</h2>
        <p style="font-size:9pt">{{ $run->deviation_note }}</p>
    @endif
</x-pdf-layout>
