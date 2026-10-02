{{--
  Created on   : Fri Oct 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : efb-223.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- EFB-Formblatt 223, Aufgliederung der Einheitspreise (MVP-1056). Erwartet: $bill, $organization, $form221, $form223 --}}
@php
    $eur = static fn (?\CommonToolkit\ValueObjects\Money $m): string => $m === null ? '—' : \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($m->toFloat(), 2, withThousandsSeparator: true);
@endphp
<x-pdf-layout pdf-type="report" :pdf-title="__('gaeb.efb.223.title') . ' — ' . $bill->name">
    <h1>{{ __('gaeb.efb.223.title') }}</h1>
    <div class="meta">{{ $organization->name }} · {{ $bill->name }} · {{ __('gaeb.efb.223.billing_wage', ['wage' => $eur($form221['billingWage'])]) }}</div>
    <table>
        <thead>
            <tr>
                <th>{{ __('gaeb.efb.223.col.reference') }}</th>
                <th>{{ __('gaeb.efb.223.col.short_text') }}</th>
                <th class="num">{{ __('gaeb.efb.223.col.quantity') }}</th>
                <th class="num">{{ __('gaeb.efb.223.col.hours') }}</th>
                <th class="num">{{ __('gaeb.efb.223.col.labour') }}</th>
                <th class="num">{{ __('gaeb.efb.223.col.material') }}</th>
                <th class="num">{{ __('gaeb.efb.223.col.equipment') }}</th>
                <th class="num">{{ __('gaeb.efb.223.col.other') }}</th>
                <th class="num">{{ __('gaeb.efb.223.col.unit_price') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($form223['rows'] as $row)
                <tr>
                    <td>{{ $row['item']->reference_no }}</td>
                    <td>{{ \Illuminate\Support\Str::limit((string) $row['item']->short_text, 60) }}</td>
                    <td class="num">{{ $row['item']->quantity?->getNumericValue() !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $row['item']->quantity->getNumericValue(), 3, trimTrailingZeros: true) : '—' }} {{ $row['item']->unit }}</td>
                    <td class="num">{{ $row['hours'] === null ? '—' : \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['hours'], 3, trimTrailingZeros: true) }}</td>
                    <td class="num">{{ $eur($row['labour']) }}</td>
                    <td class="num">{{ $eur($row['material']) }}</td>
                    <td class="num">{{ $eur($row['equipment']) }}</td>
                    <td class="num">{{ $eur($row['other']) }}</td>
                    <td class="num">{{ $eur($row['unitPrice']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @if ($form223['missing'] > 0)
        <p style="margin-top: 8pt; font-size: 8pt;">{{ trans_choice('gaeb.efb.223.missing', $form223['missing'], ['count' => $form223['missing']]) }}</p>
    @endif
    <p style="margin-top: 12pt; font-size: 8pt;">{{ __('gaeb.efb.223.note') }}</p>
</x-pdf-layout>
