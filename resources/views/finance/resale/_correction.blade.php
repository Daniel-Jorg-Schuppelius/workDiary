{{--
  Created on   : Fri Nov 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _correction.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Lizenzbild und Korrekturblatt des Empfängers (Phase 187, MVP-1772/1776): Produkt × Lizenzjahr über alle Anbieter,
     darunter was gutzuschreiben, zu berechnen und abweichend ist — in Euro mit Saldo. --}}
@php
    $states = \App\Services\Reselling\Register\RecipientCorrection::class;
    $stateTone = [$states::COVERED => 'success', $states::MISSING => 'error', $states::PARTIAL => 'warning', $states::DEVIATING => 'warning', $states::PLANNED => 'neutral', $states::WAIVED => 'neutral'];
    $euro = static fn(float $value): string => \CommonToolkit\ValueObjects\Money::ofFloat($value, \CommonToolkit\Enums\CurrencyCode::Euro, 2)->format();
    $totals = $correction['totals'];
    $hasCorrection = $correction['credit'] !== [] || $correction['bill'] !== [] || $correction['wrong'] !== [];
@endphp
@if ($correction['rows'] !== [])
    <x-card :title="__('resale.correction.picture_title')" :subtitle="__('resale.correction.picture_hint')" padding="p-0" class="mb-4" id="picture">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('resale.field.article') }}</x-table.th>
                    @foreach ($correction['years'] as $year)
                        <x-table.th class="text-center tabular-nums">{{ $year }}</x-table.th>
                    @endforeach
                </tr>
            </x-slot:head>
            @foreach ($correction['rows'] as $row)
                <tr>
                    <td class="font-medium align-top">{{ $row['label'] }}</td>
                    @foreach ($correction['years'] as $year)
                        <td class="text-center align-top">
                            @foreach ($row['cells'][$year] ?? [] as $cell)
                                <a href="{{ route('finance.resale.show', $cell['subscription']->sqid) }}" class="inline-block" title="{{ $cell['subscription']->holderLabel() }} · {{ $cell['subscription']->provider->label() }} · {{ $cell['period']->label() }}">
                                    <x-status-badge size="xs" :tone="$stateTone[$cell['state']]" class="whitespace-nowrap" :label="$cell['period']->quantity . ' × ' . __('resale.correction.state.' . $cell['state'])" />
                                </a>
                            @endforeach
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </x-table>
    </x-card>
@endif
@if ($hasCorrection)
    <x-card :title="__('resale.correction.title')" :subtitle="__('resale.correction.hint')" padding="p-0" class="mb-4" id="correction">
        <x-slot:actions>
            <x-icon-btn icon="download" size="sm" tone="ghost" :href="route('finance.resale.reconcile.correction', $customer)" show-label>{{ __('resale.correction.export') }}</x-icon-btn>
        </x-slot:actions>
        <x-table bare>
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('resale.correction.col.kind') }}</x-table.th>
                    <x-table.th>{{ __('resale.correction.col.subject') }}</x-table.th>
                    <x-table.th>{{ __('resale.correction.col.reason') }}</x-table.th>
                    <x-table.th class="text-right">{{ __('resale.correction.col.amount') }}</x-table.th>
                </tr>
            </x-slot:head>
            @foreach ($correction['credit'] as $entry)
                <tr>
                    <td class="align-top whitespace-nowrap"><x-status-badge size="xs" tone="warning" class="whitespace-nowrap" :label="__('resale.correction.credit')" /></td>
                    <td class="text-sm"><span class="font-mono text-xs">{{ $entry['line']->voucherNumber }}</span> · {{ $entry['line']->voucherDate?->fdate() }} · {{ \Illuminate\Support\Str::limit($entry['line']->label(), 40) }}</td>
                    <td class="text-sm text-muted">{{ __('resale.board.surplus.' . $entry['reason']) }}</td>
                    <td class="text-right tabular-nums">−{{ $euro($entry['amount']) }}</td>
                </tr>
            @endforeach
            @foreach ($correction['bill'] as $entry)
                <tr>
                    <td class="align-top whitespace-nowrap"><x-status-badge size="xs" tone="error" class="whitespace-nowrap" :label="__('resale.correction.bill')" /></td>
                    <td class="text-sm">{{ $entry['subscription']->label }} · {{ $entry['period']->label() }}</td>
                    <td class="text-sm text-muted">{{ $entry['subscription']->holderLabel() }}</td>
                    <td class="text-right tabular-nums">{{ $entry['amount'] > 0.0 ? $euro($entry['amount']) : '—' }}</td>
                </tr>
            @endforeach
            @foreach ($correction['wrong'] as $entry)
                <tr>
                    <td class="align-top whitespace-nowrap"><x-status-badge size="xs" tone="warning" class="whitespace-nowrap" :label="__('resale.correction.wrong')" /></td>
                    <td class="text-sm">{{ $entry['period']->subscription->label }} · {{ $entry['period']->label() }}</td>
                    <td class="text-sm text-muted">{{ __('resale.deviation.kind.' . $entry['kind']) }}</td>
                    <td class="text-right tabular-nums">{{ ($entry['diff'] < 0 ? '−' : '') . $euro(abs($entry['diff'])) }}</td>
                </tr>
            @endforeach
            <tr class="font-medium border-t border-base-300">
                <td colspan="3">{{ __('resale.correction.balance') }} <span class="font-normal text-xs text-muted">{{ __('resale.correction.balance_hint') }}</span></td>
                <td class="text-right tabular-nums">{{ ($totals['balance'] < 0 ? '−' : '') . $euro(abs($totals['balance'])) }}</td>
            </tr>
        </x-table>
    </x-card>
@endif
