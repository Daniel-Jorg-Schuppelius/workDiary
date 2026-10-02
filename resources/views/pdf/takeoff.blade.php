{{--
  Created on   : Fri Oct 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : takeoff.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Aufmaßblatt (MVP-1058). Erwartet: $takeoff, $organization, $totals --}}
@php
    $num = static fn ($v, int $d = 3): string => $v === null ? '—' : \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $v, $d, trimTrailingZeros: true);
@endphp
<x-pdf-layout pdf-type="protocol" :pdf-title="__('takeoff.title') . ' — ' . $takeoff->title">
    <h1>{{ __('takeoff.title') }}: {{ $takeoff->title }}</h1>
    <div class="meta">{{ $organization->name }}@if ($takeoff->measured_on) · {{ __('takeoff.field.measured_on') }} {{ $takeoff->measured_on->format('d.m.Y') }}@endif</div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>{{ __('takeoff.col.label') }}</th>
                <th>{{ __('takeoff.col.formula') }}</th>
                <th>{{ __('takeoff.col.values') }}</th>
                <th class="num">{{ __('takeoff.col.factor') }}</th>
                <th class="num">{{ __('takeoff.col.quantity') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($takeoff->lines as $i => $line)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $line->label }}@if ($line->boqItem) <br><span style="font-size: 8pt;">{{ $line->boqItem->reference_no }}</span>@elseif ($line->description) <br><span style="font-size: 8pt;">{{ $line->description }}</span>@endif</td>
                    <td>{{ $line->formula->value }} {{ $line->formula->label() }}</td>
                    <td>{{ $line->formula->isExpression() ? ($line->values[0] ?? '') : implode('; ', array_map($num, (array) $line->values)) }}</td>
                    <td class="num">{{ $num($line->factor) }}</td>
                    <td class="num">{{ $num($line->quantity) }} {{ $line->unit }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>{{ __('takeoff.totals') }}</h2>
    <table>
        @foreach ($totals as $total)
            <tr><th>{{ $total['label'] }}</th><td class="num">{{ $num($total['quantity']) }} {{ $total['unit'] }}</td></tr>
        @endforeach
    </table>
    @if ($takeoff->note)
        <p style="margin-top: 8pt;">{{ $takeoff->note }}</p>
    @endif
    <p style="margin-top: 12pt; font-size: 8pt;">{{ __('takeoff.pdf_note') }}</p>
</x-pdf-layout>
