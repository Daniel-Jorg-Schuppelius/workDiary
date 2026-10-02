{{--
  Created on   : Fri Oct 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : efb-221.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- EFB-Formblatt 221, Preisermittlung bei Zuschlagskalkulation (MVP-1056). Erwartet: $bill, $organization, $form221 --}}
@php
    $eur = static fn (\CommonToolkit\ValueObjects\Money $m): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($m->toFloat(), 2, withThousandsSeparator: true);
    $pct = static fn (string $v): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $v, 2);
    $kinds = [\App\Enums\Article\CostKind::Labour, \App\Enums\Article\CostKind::Material, \App\Enums\Article\CostKind::Equipment, \App\Enums\Article\CostKind::Other, \App\Enums\Article\CostKind::Subcontract];
@endphp
<x-pdf-layout pdf-type="report" :pdf-title="__('gaeb.efb.221.title') . ' — ' . $bill->name">
    <h1>{{ __('gaeb.efb.221.title') }}</h1>
    <div class="meta">{{ $organization->name }} · {{ $bill->name }} · {{ now()->format('d.m.Y') }}</div>

    <h2>{{ __('gaeb.efb.221.wage_section') }}</h2>
    <table>
        <tr><th>1.1 {{ __('gaeb.efb.221.average_wage') }}</th><td class="num"></td><td class="num">{{ $eur($form221['averageWage']) }} €/h</td></tr>
        <tr><th>1.2 {{ __('gaeb.efb.221.wage_related') }}</th><td class="num">{{ $pct($form221['wageRelatedPercent']) }} %</td><td class="num">{{ $eur($form221['wageRelated']) }} €/h</td></tr>
        <tr><th>1.3 {{ __('gaeb.efb.221.ancillary') }}</th><td class="num"></td><td class="num">{{ $eur($form221['ancillary']) }} €/h</td></tr>
        <tr><th>1.4 {{ __('gaeb.efb.221.calculation_wage') }}</th><td class="num"></td><td class="num"><strong>{{ $eur($form221['calculationWage']) }} €/h</strong></td></tr>
        <tr><th>1.5 {{ __('gaeb.efb.221.labour_markup') }}</th><td class="num">{{ $pct($form221['labourMarkupPercent']) }} %</td><td class="num">{{ $eur($form221['labourMarkup']) }} €/h</td></tr>
        <tr><th>1.6 {{ __('gaeb.efb.221.billing_wage') }}</th><td class="num"></td><td class="num"><strong>{{ $eur($form221['billingWage']) }} €/h</strong></td></tr>
    </table>

    <h2>{{ __('gaeb.efb.221.markup_section') }}</h2>
    <table>
        <thead>
            <tr>
                <th></th>
                @foreach ($kinds as $kind)<th class="num">{{ $kind->label() }}</th>@endforeach
            </tr>
        </thead>
        <tbody>
            @foreach (['site' => '2.1', 'general' => '2.2', 'risk' => '2.3'] as $field => $no)
                <tr>
                    <td>{{ $no }} {{ __('gaeb.efb.221.markup.' . $field) }}</td>
                    @foreach ($kinds as $kind)<td class="num">{{ $pct($form221['markups'][$kind->value][$field]) }} %</td>@endforeach
                </tr>
            @endforeach
            <tr>
                <td><strong>2.4 {{ __('gaeb.efb.221.markup.total') }}</strong></td>
                @foreach ($kinds as $kind)<td class="num"><strong>{{ $pct($form221['markups'][$kind->value]['total']) }} %</strong></td>@endforeach
            </tr>
        </tbody>
    </table>
    <p style="margin-top: 12pt; font-size: 8pt;">{{ __('gaeb.efb.221.note') }}</p>
</x-pdf-layout>
