{{--
  Created on   : Mon Aug 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Mahnlauf-Cockpit (Feature 127, MVP-691): fällige Mahnstufen lokal geführter
  Rechnungen mit Sammelmahnung; Mahnsperren als eigener Abschnitt.
--}}

@extends('layouts.app')

@section('title', __('finance.dunning.title'))
@section('nav-title', __('finance.dunning.title'))

@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('finance.dunning.subtitle')">

        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <x-kpi-tile :label="__('finance.dunning.kpi.candidates')" :value="count($candidates)" format="int"
                        :tone="count($candidates) > 0 ? 'warning' : 'neutral'" />
            <x-kpi-tile :label="__('finance.dunning.kpi.open_sum')"
                        :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($openSum, 2, withThousandsSeparator: true) . ' €'"
                        format="raw" tone="primary"
                        :hint="__('finance.dunning.kpi.open_sum_hint')" />
            <x-kpi-tile :label="__('finance.dunning.kpi.waiting')" :value="count($waiting)" format="int"
                        :hint="__('finance.dunning.kpi.waiting_hint')" />
            <x-kpi-tile :label="__('finance.dunning.kpi.blocked')" :value="count($blocked)" format="int"
                        :tone="count($blocked) > 0 ? 'info' : 'neutral'"
                        :hint="__('finance.dunning.kpi.blocked_hint')" />
        </div>

        @if ($interestRate > 0)
            <div class="alert text-sm">
                <x-icon name="percent" />
                <span>{{ __('finance.dunning.interest_active', ['rate' => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($interestRate, 2)]) }}</span>
            </div>
        @endif

        @if (count($blocked) > 0)
            {{-- Mahnsperren: eigener Abschnitt — nie in der Auswahl. --}}
            <details class="rounded-box border border-base-300 bg-base-100 shadow-xs">
                <summary class="cursor-pointer px-4 py-3 text-sm font-medium">
                    {{ trans_choice('finance.dunning.blocked_heading', count($blocked), ['count' => count($blocked)]) }}
                </summary>
                <div class="px-4 pb-3">
                    <ul class="space-y-1 text-sm">
                        @foreach ($blocked as $row)
                            <li class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('invoices.show', $row['invoice']) }}" class="link">{{ $row['invoice']->number }}</a>
                                <span class="text-muted">{{ $row['invoice']->customer->name ?? '—' }}</span>
                                <x-status-badge tone="warning" outline>{{ __('finance.dunning.badge_blocked') }}</x-status-badge>
                                <span class="text-xs text-muted">
                                    {{ __('finance.dunning.blocked_since', ['date' => $row['invoice']->dunning_blocked_at?->fdate() ?? '—']) }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </details>
        @endif

        @if (count($waiting) > 0)
            {{-- Karenz läuft: sichtbar halten, damit nichts lautlos verschwindet. --}}
            <details class="rounded-box border border-base-300 bg-base-100 shadow-xs">
                <summary class="cursor-pointer px-4 py-3 text-sm font-medium">
                    {{ trans_choice('finance.dunning.waiting_heading', count($waiting), ['count' => count($waiting)]) }}
                </summary>
                <div class="px-4 pb-3">
                    <ul class="space-y-1 text-sm">
                        @foreach ($waiting as $row)
                            <li class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('invoices.show', $row['invoice']) }}" class="link">{{ $row['invoice']->number }}</a>
                                <span class="text-muted">{{ $row['invoice']->customer->name ?? '—' }}</span>
                                <span class="text-xs text-muted">
                                    {{ __('finance.dunning.waiting_until', ['level' => $row['next_level'], 'date' => $row['next_due_on']?->fdate() ?? '—']) }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </details>
        @endif

        @php($bulkEnabled = count($candidates) > 0)

        @if ($bulkEnabled)
            <form method="POST" action="{{ route('finance.dunning.run') }}" data-bulk-form
                  class="min-h-0 flex-1 flex flex-col overflow-hidden">
                @csrf
                <div class="px-3 pt-3 flex-none">
                    <x-bulk-toolbar :label="__('finance.dunning.bulk_selected')">
                        <x-slot:actions>
                            <button type="submit"
                                    formaction="{{ route('finance.dunning.run') }}"
                                    class="btn btn-warning btn-sm"
                                    data-confirm-dialog
                                    data-confirm-message="{{ __('finance.dunning.bulk_confirm') }}"
                                    data-confirm-icon="notification_important"
                                    data-confirm-tone="warning"
                                    data-confirm-label="{{ __('finance.dunning.bulk_action') }}">
                                <x-icon name="notification_important" /> {{ __('finance.dunning.bulk_action') }}
                            </button>
                        </x-slot:actions>
                    </x-bulk-toolbar>
                </div>
        @endif
        <x-table scroll="flex">
            <x-slot:head>
                <tr>
                    @if ($bulkEnabled)
                        <th class="w-8">
                            <input type="checkbox" class="checkbox checkbox-sm" data-bulk-select-all
                                   aria-label="{{ __('finance.dunning.select_all') }}">
                        </th>
                    @endif
                    <th>{{ __('finance.dunning.column.customer') }}</th>
                    <th>{{ __('finance.dunning.column.number') }}</th>
                    <th>{{ __('finance.dunning.column.overdue_since') }}</th>
                    <th class="text-right">{{ __('finance.dunning.column.open') }}</th>
                    <th>{{ __('finance.dunning.column.current_level') }}</th>
                    <th>{{ __('finance.dunning.column.next_level') }}</th>
                    <th class="text-right">{{ __('finance.dunning.column.fee') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($candidates as $row)
                <tr class="hover">
                    @if ($bulkEnabled)
                        <td>
                            <input type="checkbox" class="checkbox checkbox-sm"
                                   data-bulk-checkbox name="ids[]" value="{{ $row['invoice']->sqid }}"
                                   aria-label="{{ __('finance.dunning.select_row', ['nr' => $row['invoice']->number]) }}">
                        </td>
                    @endif
                    <td class="font-medium">
                        @if ($row['invoice']->customer !== null)
                            <a class="link link-hover" href="{{ route('customers.show', $row['invoice']->customer) }}">{{ $row['invoice']->customer->name }}</a>
                        @else
                            —
                        @endif
                    </td>
                    <td><a class="link link-hover" href="{{ route('invoices.show', $row['invoice']) }}">{{ $row['invoice']->number }}</a></td>
                    <td class="whitespace-nowrap">
                        {{ $row['invoice']->due_on?->fdate() ?? '—' }}
                        <span class="text-xs text-muted">{{ trans_choice('finance.dunning.days_overdue', $row['overdue_days'], ['count' => $row['overdue_days']]) }}</span>
                    </td>
                    <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['open'], 2, withThousandsSeparator: true) }} {{ $row['invoice']->currency->value }}</td>
                    <td>
                        @if ((int) $row['invoice']->dunning_level === 0)
                            <span class="text-muted">{{ __('finance.dunning.level_none') }}</span>
                        @else
                            <x-status-badge tone="warning" outline>{{ __('finance.dunning.level_n', ['level' => (int) $row['invoice']->dunning_level]) }}</x-status-badge>
                        @endif
                    </td>
                    <td>
                        <x-status-badge tone="warning">{{ $row['next_level'] <= 1 ? __('finance.dunning.level_reminder') : __('finance.dunning.level_n', ['level' => $row['next_level']]) }}</x-status-badge>
                    </td>
                    <td class="text-right tabular-nums">
                        @if ($row['fee'] > 0)
                            {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['fee'], 2, withThousandsSeparator: true) }} {{ $row['invoice']->currency->value }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="$bulkEnabled ? 8 : 7"
                               :title="__('finance.dunning.empty_title')"
                               :message="__('finance.dunning.empty_message')" />
            @endforelse
        </x-table>
        @if ($bulkEnabled)
            </form>
        @endif
    </x-index-page>
@endsection
