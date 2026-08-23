{{--
  Created on   : Sat Aug 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : vat.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Umsatzsteuer-Vorschau (Feature 125, MVP-676). Bewusst eine Vorschau: Der
  MVP übermittelt keine UStVA — eine betriebliche Auswertung darf nicht
  aussehen wie eine abgegebene Erklärung.
--}}

@extends('layouts.app')

@section('title', __('accounting.reports.card.vat.title'))
@section('nav-title', __('accounting.reports.card.vat.title'))

@section('content')
    <x-index-page :subtitle="$period?->label() ?? __('accounting.reports.period', ['from' => $from->fdate(), 'to' => $to->fdate()])">
        <x-slot:actions>
            <x-icon-btn icon="download" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.vat', ['export' => 'csv'])" :label="__('CSV')" />
            <x-icon-btn icon="table_view" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.vat', ['export' => 'xlsx'])" :label="__('Excel')" />
            <x-icon-btn icon="picture_as_pdf" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.vat', ['export' => 'pdf'])" :label="__('PDF')" />
        </x-slot:actions>

        <div class="alert bg-warning/10 border-warning/30 text-sm text-base-content" role="note">
            <x-icon name="info" />
            <span>{{ __('accounting.reports.vat_preview_hint') }}</span>
        </div>

        @if ($period === null)
            {{-- Kleinunternehmer (§ 19 UStG): kein Voranmeldungszeitraum. --}}
            <div class="alert bg-info/10 border-info/30 text-sm text-base-content" role="note">
                <x-icon name="info" />
                <span>{{ __('accounting.filing.no_period') }}</span>
            </div>
        @else
            {{-- Periodenwahl statt freiem Zeitraum (MVP-685). --}}
            <x-filter-bar :action="route('reports.accounting.vat')" :reset="route('reports.accounting.vat')">
                <select name="period" class="select select-sm select-bordered w-56 shrink-0"
                        aria-label="{{ __('accounting.filing.field.period') }}">
                    @foreach ($periods as $option)
                        <option value="{{ $option->key }}" @selected($option->key === $period->key)>{{ $option->label() }}</option>
                    @endforeach
                </select>
                <span class="text-xs text-base-content/60">
                    {{ $interval->label() }}@if ($has_extension) · {{ __('accounting.filing.extension.short') }}@endif
                </span>
            </x-filter-bar>
        @endif

        <div class="grid gap-3 sm:grid-cols-3">
            <x-kpi-tile :label="__('enums.finance.tax-code-direction.output')" :value="$output" />
            <x-kpi-tile :label="__('enums.finance.tax-code-direction.input')" :value="$input" />
            <x-kpi-tile :label="__('accounting.reports.column.payable')" :value="$payable" />
        </div>

        @if ((float) $special_prepayment !== 0.0)
            {{-- Anrechnung der Sondervorauszahlung (KZ 39) in der letzten Periode. --}}
            <div class="grid gap-3 sm:grid-cols-2">
                <x-kpi-tile :label="__('accounting.filing.field.special_prepayment')" :value="'-' . $special_prepayment" />
                <x-kpi-tile :label="__('accounting.filing.field.remaining')" :value="$remaining" />
            </div>
        @endif

        @if ($period !== null && ($fields ?? []) !== [])
            {{-- Kennziffern der Voranmeldung (MVP-688) — Abgleichhilfe, kein Vordruck. --}}
            <x-card :title="__('accounting.filing.fields.title')" icon="tag" :subtitle="__('accounting.filing.fields.subtitle')">
                <x-table :bare="true">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('accounting.filing.fields.column.field') }}</th>
                            <th class="text-right">{{ __('accounting.filing.fields.column.base') }}</th>
                            <th class="text-right">{{ __('accounting.filing.fields.column.tax') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($fields as $field)
                        <tr class="hover">
                            <td class="font-mono">{{ $field['field'] }}</td>
                            <td class="text-right font-mono">{{ $field['base'] }}</td>
                            <td class="text-right font-mono">{{ $field['tax'] }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td class="font-medium">{{ __('accounting.filing.fields.remaining') }}</td>
                        <td></td>
                        <td class="text-right font-mono font-medium">{{ $remaining }}</td>
                    </tr>
                </x-table>

                @if (($field_unclear ?? []) !== [])
                    <ul class="mt-2 list-disc pl-5 text-xs text-warning">
                        @foreach ($field_unclear as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        @endif

        <x-table scroll="flex" :zebra="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('accounting.ledger.column.account') }}</th>
                    <th>{{ __('accounting.reports.column.direction') }}</th>
                    <th class="text-right">{{ __('accounting.ledger.column.amount') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($rows as $row)
                <tr class="hover">
                    <td>
                        <a class="link" href="{{ route('reports.accounting.account-ledger', ['account' => $row['account']->sqid]) }}">
                            {{ $row['account']->displayLabel() }}
                        </a>
                    </td>
                    <td>{{ __('enums.finance.tax-code-direction.' . $row['direction']) }}</td>
                    <td class="text-right font-mono">{{ $row['amount'] }}</td>
                </tr>
            @empty
                <tr><td colspan="3"><x-empty-state icon="percent" :title="__('accounting.reports.empty')" /></td></tr>
            @endforelse
        </x-table>
    </x-index-page>
@endsection
