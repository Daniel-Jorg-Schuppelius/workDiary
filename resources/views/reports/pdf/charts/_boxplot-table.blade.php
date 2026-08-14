{{--
  Created on   : Sun Aug 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _boxplot-table.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Print-Rückfall für Boxplots: dompdf rendert keine SVG-Whisker — die fünf
    Kennwerte tragen die Information als Kompakttabelle (bewusste
    Rückfall-Abbildung laut §Diagramm-UX/PDF).

    Erwartet: $series ([['x','min','q1','median','q3','max','n'?]]), $unit, $xLabel?
--}}
@php
    $points = collect($series ?? [])->values();
    $num = fn(float $v): string => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
@endphp
@if ($points->isEmpty())
    <p class="small">{{ __('Noch keine Daten für dieses Diagramm.') }}</p>
@else
    <table class="data">
        <thead>
            <tr>
                <th>{{ $xLabel ?? __('Kategorie') }}</th>
                <th class="right">{{ __('Min') }} ({{ $unit }})</th>
                <th class="right">{{ __('Q1') }}</th>
                <th class="right">{{ __('Median') }}</th>
                <th class="right">{{ __('Q3') }}</th>
                <th class="right">{{ __('Max') }}</th>
                <th class="right">n</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($points as $point)
                <tr>
                    <td>{{ $point['x'] }}</td>
                    <td class="right">{{ $num((float) $point['min']) }}</td>
                    <td class="right">{{ $num((float) $point['q1']) }}</td>
                    <td class="right">{{ $num((float) $point['median']) }}</td>
                    <td class="right">{{ $num((float) $point['q3']) }}</td>
                    <td class="right">{{ $num((float) $point['max']) }}</td>
                    <td class="right">{{ $point['n'] ?? '–' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
