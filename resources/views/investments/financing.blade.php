{{--
  Created on   : Sat Sep 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : financing.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Finanzierungsvergleich einer Investitionsvariante (MVP-907). --}}
@extends('layouts.app')

@php
    $fmt = static fn (?string $v): string => $v === null ? '—' : \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, 2, withThousandsSeparator: true) . ' €';
@endphp

@section('title', __('investment.financing.title'))
@section('nav-title', __('investment.financing.title'))

@section('content')
<x-page-shell gap="4">
    <x-slot:toolbar>
        <x-page-toolbar :title="$option->title" :subtitle="__('investment.financing.subtitle', ['price' => $fmt((string) $option->one_time_cost)])">
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" size="sm" :href="route('investments.show', $case)" :label="$case->title" />
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-card :title="__('investment.financing.comparison')" :count="count($rows)">
        @if ($rows === [])
            <p class="text-sm text-muted">{{ __('investment.financing.none') }}</p>
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('investment.financing.kind_label') }}</th>
                        <th class="text-right">{{ __('investment.financing.monthly') }}</th>
                        <th class="text-right">{{ __('investment.financing.term') }}</th>
                        <th class="text-right">{{ __('investment.financing.interest') }}</th>
                        <th class="text-right">{{ __('investment.financing.total') }}</th>
                        <th class="text-right">{{ __('investment.financing.extra') }}</th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['variant']->kind->label() }}@if ($row['variant']->note)<span class="block text-xs text-muted">{{ $row['variant']->note }}</span>@endif</td>
                        <td class="text-right tabular-nums">{{ $fmt($row['monthly']) }}</td>
                        <td class="text-right tabular-nums">{{ $row['variant']->term_months ? __('investment.financing.months', ['count' => $row['variant']->term_months]) : '—' }}</td>
                        <td class="text-right tabular-nums">{{ $fmt($row['interest']) }}</td>
                        <td class="text-right tabular-nums font-medium">{{ $fmt($row['total']) }}</td>
                        <td class="text-right tabular-nums">{{ $fmt($row['extra']) }}</td>
                        <td class="text-right">
                            @if ($canEdit)
                                <x-action-form :action="route('investments.options.financing.destroy', [$case, $option, $row['variant']])" method="DELETE" class="inline">
                                    <x-icon-btn icon="delete" size="xs" tone="error" type="submit" :title="__('investment.financing.delete')" />
                                </x-action-form>
                            @endif
                        </td>
                    </tr>
                    @if ($row['schedule'] !== [])
                        <tr>
                            <td colspan="7">
                                <details class="text-sm">
                                    <summary class="cursor-pointer text-muted">{{ __('investment.financing.schedule') }}</summary>
                                    <x-table bare>
                                        <x-slot:head>
                                            <tr><th>{{ __('investment.financing.period') }}</th><th class="text-right">{{ __('investment.financing.payment') }}</th><th class="text-right">{{ __('investment.financing.interest') }}</th><th class="text-right">{{ __('investment.financing.principal') }}</th><th class="text-right">{{ __('investment.financing.balance') }}</th></tr>
                                        </x-slot:head>
                                        @foreach ($row['schedule'] as $line)
                                            <tr><td>{{ $line['period'] }}</td><td class="text-right tabular-nums">{{ $fmt($line['payment']) }}</td><td class="text-right tabular-nums">{{ $fmt($line['interest']) }}</td><td class="text-right tabular-nums">{{ $fmt($line['principal']) }}</td><td class="text-right tabular-nums">{{ $fmt($line['balance']) }}</td></tr>
                                        @endforeach
                                    </x-table>
                                </details>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </x-table>
            <p class="mt-2 text-xs text-muted">{{ __('investment.financing.note') }}</p>
        @endif
    </x-card>

    @if ($canEdit)
        <x-card :title="__('investment.financing.add')">
            <form method="POST" action="{{ route('investments.options.financing.store', [$case, $option]) }}" class="grid gap-2 sm:grid-cols-4">
                @csrf
                <x-select-field name="kind" :label="__('investment.financing.kind_label')" required>
                    @foreach (\App\Enums\Investments\InvestmentFinancingKind::cases() as $kind)
                        <option value="{{ $kind->value }}" @selected(old('kind') === $kind->value)>{{ $kind->label() }}</option>
                    @endforeach
                </x-select-field>
                <x-input-field name="term_months" type="number" min="1" max="600" :label="__('investment.financing.term')" :value="old('term_months')" />
                <x-input-field name="interest_rate" type="number" step="0.001" min="0" max="100" :label="__('investment.financing.rate')" :value="old('interest_rate')" />
                <x-input-field name="rate_amount" type="number" step="0.01" min="0" :label="__('investment.financing.lease_rate')" :value="old('rate_amount')" />
                <x-input-field name="down_payment_amount" type="number" step="0.01" min="0" :label="__('investment.financing.down_payment')" :value="old('down_payment_amount')" />
                <x-input-field name="residual_amount" type="number" step="0.01" min="0" :label="__('investment.financing.residual')" :value="old('residual_amount')" />
                <x-input-field name="fee_amount" type="number" step="0.01" min="0" :label="__('investment.financing.fee')" :value="old('fee_amount')" />
                <x-input-field name="note" :label="__('investment.financing.note_label')" :value="old('note')" maxlength="2000" />
                <div class="sm:col-span-4"><x-button type="submit" tone="primary" size="sm">{{ __('investment.financing.add') }}</x-button></div>
            </form>
            <x-validation-errors />
        </x-card>
    @endif
</x-page-shell>
@endsection
