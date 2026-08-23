{{--
  Created on   : Fri Aug 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : setup.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Buchhaltung einrichten (Feature 125, MVP-671). Bewusst eine geführte Seite
  statt eines Schalters: Hoheit, Stichtag, Geschäftsjahr und Preflight gehören
  zusammen. Solange der Preflight blockiert, bleibt die Aktivierung gesperrt —
  ein Override wäre genau der stille Doppelbetrieb, den das Modul verhindert.
--}}

@extends('layouts.app')

@section('title', __('accounting.ledger.title'))
@section('nav-title', __('accounting.ledger.title'))

@section('content')
    <x-page-toolbar :subtitle="__('accounting.ledger.subtitle')">
        <x-slot:actions>
            <x-status-badge :tone="$currentSovereignty->tone()">{{ $currentSovereignty->label() }}</x-status-badge>
        </x-slot:actions>
    </x-page-toolbar>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <x-card :title="__('accounting.ledger.section.profile')" icon="tune">
            <form method="POST" action="{{ route('finance.accounting.update') }}" class="grid gap-3">
                @csrf
                @method('PUT')

                <x-select-field name="profit_determination" :label="__('accounting.ledger.field.profit_determination')" :hint="__('accounting.ledger.hint.profit_determination')">
                    @foreach ($profitOptions as $option)
                        <option value="{{ $option->value }}" @selected(old('profit_determination', $profile->profit_determination->value) === $option->value)>{{ $option->label() }}</option>
                    @endforeach
                </x-select-field>

                <div class="grid gap-3 sm:grid-cols-2">
                    <x-select-field name="base_currency" :label="__('accounting.ledger.field.base_currency')" :hint="__('accounting.ledger.hint.base_currency')">
                        <x-currency-options :selected="old('base_currency', $profile->base_currency->value)" />
                    </x-select-field>

                    <x-select-field name="fiscal_year_start_month" :label="__('accounting.ledger.field.fiscal_year_start_month')">
                        @foreach (range(1, 12) as $month)
                            <option value="{{ $month }}" @selected((int) old('fiscal_year_start_month', $profile->fiscal_year_start_month) === $month)>
                                {{ \Carbon\CarbonImmutable::create(null, $month, 1)?->translatedFormat('F') }}
                            </option>
                        @endforeach
                    </x-select-field>
                </div>

                <x-input-field name="starts_on" type="date"
                               :label="__('accounting.ledger.field.starts_on')"
                               :hint="__('accounting.ledger.hint.starts_on')"
                               :value="old('starts_on', $profile->starts_on?->toDateString() ?? '')"
                               :readonly="$profile->isLocalActive()" />

                <x-input-field name="note" type="text" maxlength="1000"
                               :label="__('accounting.ledger.field.note')"
                               :value="old('note', $profile->note ?? '')" />

                @if ($canConfigure)
                    <div class="flex justify-end">
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('Speichern') }}</button>
                    </div>
                @endif
            </form>
        </x-card>

        <x-card :title="__('accounting.ledger.section.preflight')" icon="checklist">
            @if ($preflight === null)
                <x-empty-state icon="checklist" :title="__('accounting.ledger.preflight.not_configured')" />
            @else
                <ul class="flex flex-col gap-2">
                    @foreach ($preflight->checks as $check)
                        <li class="flex items-start gap-2 text-sm">
                            <x-status-badge :tone="$check->tone()">{{ __('accounting.ledger.preflight.key.' . $check->key) }}</x-status-badge>
                            <span class="min-w-0 flex-1">{{ $check->message }}</span>
                        </li>
                    @endforeach
                </ul>

                @if ($canConfigure && ! $profile->isLocalActive())
                    <form method="POST" action="{{ route('finance.accounting.activate') }}" class="mt-4 flex justify-end">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm" @disabled(! $preflight->isReady())>
                            {{ __('accounting.ledger.action.activate') }}
                        </button>
                    </form>
                    @unless ($preflight->isReady())
                        <p class="mt-2 text-xs text-base-content/60">{{ __('accounting.ledger.preflight.blocked_hint') }}</p>
                    @endunless
                @endif
            @endif
        </x-card>

        <x-card :title="__('accounting.ledger.section.fiscal_years')" icon="calendar_month">
            <x-slot:actions>
                @if ($canConfigure)
                    <x-icon-btn icon="add" size="xs" tone="ghost"
                                data-entry-modal-trigger
                                :href="route('finance.accounting.fiscal-years.create')"
                                :label="__('accounting.ledger.action.add_fiscal_year')" />
                @endif
            </x-slot:actions>

            <x-table :bare="true">
                <x-slot:head>
                    <tr>
                        <th>{{ __('accounting.ledger.column.fiscal_year') }}</th>
                        <th>{{ __('accounting.ledger.column.range') }}</th>
                        <th>{{ __('accounting.ledger.column.periods') }}</th>
                        <th>{{ __('accounting.ledger.column.status') }}</th>
                    </tr>
                </x-slot:head>
                @forelse ($fiscalYears as $year)
                    <tr class="hover">
                        <td class="font-medium">{{ $year->label }}</td>
                        <td>{{ $year->starts_on->fdate() }} – {{ $year->ends_on->fdate() }}</td>
                        <td>{{ $year->periods_count }}</td>
                        <td><x-status-badge :tone="$year->status->tone()">{{ $year->status->label() }}</x-status-badge></td>
                    </tr>
                @empty
                    <tr><td colspan="4"><x-empty-state icon="calendar_month" :title="__('accounting.ledger.empty.fiscal_years')" /></td></tr>
                @endforelse
            </x-table>
        </x-card>

        <x-card :title="__('accounting.taxation.title')" icon="swap_vert" :subtitle="__('accounting.taxation.subtitle')">
            <x-slot:actions>
                @if ($canConfigure)
                    <x-icon-btn icon="swap_vert" size="xs" tone="ghost"
                                data-entry-modal-trigger
                                :href="route('finance.accounting.taxation.create')"
                                :label="__('accounting.taxation.action.switch')" />
                @endif
            </x-slot:actions>

            <div class="flex items-center gap-2">
                <x-status-badge :tone="$taxationMethod->tone()">{{ $taxationMethod->label() }}</x-status-badge>
                <span class="text-xs text-base-content/60">{{ __('accounting.taxation.default_hint') }}</span>
            </div>

            @if ($taxationPeriods->isNotEmpty())
                <x-table :bare="true" class="mt-3">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('accounting.ledger.column.from') }}</th>
                            <th>{{ __('accounting.ledger.column.to') }}</th>
                            <th>{{ __('accounting.taxation.field.method') }}</th>
                            <th>{{ __('accounting.taxation.column.changeover') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($taxationPeriods as $period)
                        <tr class="hover">
                            <td>{{ $period->valid_from->fdate() }}</td>
                            <td>{{ $period->valid_to?->fdate() ?? __('accounting.ledger.open_ended') }}</td>
                            <td><x-status-badge :tone="$period->method->tone()">{{ $period->method->label() }}</x-status-badge></td>
                            <td class="text-xs text-base-content/70">
                                {{ __('accounting.taxation.changeover.summary', [
                                    'count' => $period->changeover['count'] ?? 0,
                                    'amount' => $period->changeover['open_amount'] ?? '0.00',
                                ]) }}
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>

        {{-- Meldeprofil (MVP-684): Zeitraum, Verlängerung, Vorschlag. --}}
        <x-card :title="__('accounting.filing.title')" icon="event_repeat" :subtitle="__('accounting.filing.subtitle')">
            <x-slot:actions>
                @if ($canConfigure)
                    <x-icon-btn icon="payments" size="xs" tone="ghost"
                                data-entry-modal-trigger
                                :href="route('finance.accounting.prepayment.create')"
                                :label="__('accounting.filing.prepayment.title')" />
                    <x-icon-btn icon="more_time" size="xs" tone="ghost"
                                data-entry-modal-trigger
                                :href="route('finance.accounting.vat-extension.create')"
                                :label="__('accounting.filing.extension.title')" />
                    <x-icon-btn icon="event_repeat" size="xs" tone="ghost"
                                data-entry-modal-trigger
                                :href="route('finance.accounting.filing-interval.create')"
                                :label="__('accounting.filing.action.switch')" />
                @endif
            </x-slot:actions>

            <div class="flex flex-wrap items-center gap-2">
                <x-status-badge :tone="$filingInterval->tone()">{{ $filingInterval->label() }}</x-status-badge>
                @if ($filingExtension?->granted_on)
                    <x-status-badge tone="success">
                        {{ __('accounting.filing.extension.active', ['year' => $filingExtension->year]) }}
                    </x-status-badge>
                @endif
                <span class="text-xs text-base-content/60">{{ __('accounting.filing.default_hint') }}</span>
            </div>

            <p class="mt-2 text-xs text-base-content/70">
                {{ __('accounting.filing.suggestion.headline', [
                    'interval' => $filingSuggestion['interval']->label(),
                    'year' => $filingSuggestion['prior_year'],
                    'amount' => $filingSuggestion['prior_year_tax'],
                ]) }}
            </p>

            @if ($filingExtension?->special_prepayment_amount)
                <p class="mt-1 text-xs text-base-content/70">
                    {{ __('accounting.filing.extension.prepayment_note', [
                        'amount' => $filingExtension->special_prepayment_amount->getAmount(),
                        'year' => $filingExtension->year,
                    ]) }}
                </p>
            @endif

            @if ($filingPeriods->isNotEmpty())
                <x-table :bare="true" class="mt-3">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('accounting.ledger.column.from') }}</th>
                            <th>{{ __('accounting.ledger.column.to') }}</th>
                            <th>{{ __('accounting.filing.field.interval') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($filingPeriods as $period)
                        <tr class="hover">
                            <td>{{ $period->valid_from->fdate() }}</td>
                            <td>{{ $period->valid_to?->fdate() ?? __('accounting.ledger.open_ended') }}</td>
                            <td><x-status-badge :tone="$period->interval->tone()">{{ $period->interval->label() }}</x-status-badge></td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>

        <x-card :title="__('accounting.ledger.section.sovereignty')" icon="history" :subtitle="__('accounting.ledger.hint.sovereignty')">
            <x-slot:actions>
                @if ($canConfigure)
                    <x-icon-btn icon="swap_horiz" size="xs" tone="ghost"
                                data-entry-modal-trigger
                                :href="route('finance.accounting.sovereignty.create')"
                                :label="__('accounting.ledger.action.switch')" />
                @endif
            </x-slot:actions>

            <x-table :bare="true">
                <x-slot:head>
                    <tr>
                        <th>{{ __('accounting.ledger.column.from') }}</th>
                        <th>{{ __('accounting.ledger.column.to') }}</th>
                        <th>{{ __('accounting.ledger.column.holder') }}</th>
                        <th>{{ __('accounting.ledger.column.reason') }}</th>
                    </tr>
                </x-slot:head>
                @forelse ($sections as $section)
                    <tr class="hover">
                        <td>{{ $section->valid_from->fdate() }}</td>
                        <td>{{ $section->valid_to?->fdate() ?? __('accounting.ledger.open_ended') }}</td>
                        <td>
                            <x-status-badge :tone="$section->sovereignty->tone()">{{ $section->sovereignty->label() }}</x-status-badge>
                            @if ($section->external_provider)
                                <span class="ml-1 text-xs text-base-content/60">{{ $section->external_provider }}</span>
                            @endif
                        </td>
                        <td class="text-sm text-base-content/70">{{ $section->reason }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4"><x-empty-state icon="history" :title="__('accounting.ledger.empty.sections')" /></td></tr>
                @endforelse
            </x-table>
        </x-card>
    </div>
@endsection
