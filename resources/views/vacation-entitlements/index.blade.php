{{--
  Created on   : Fri Jul 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@php
/**
 * @var int $year
 * @var \Illuminate\Support\Collection<int, \App\Models\VacationEntitlement> $entitlements
 * @var array<int, \App\Services\Absence\VacationBalance> $balances
 * @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $usersWithoutEntitlement
 * @var float $defaultDays
 */
@endphp

@section('nav-title', __('Urlaubskonto'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Jahresansprüche, Übertrag und Restsalden je Mitarbeiter.')">

    <x-filter-bar :action="route('vacation-entitlements.index')" :reset="route('vacation-entitlements.index')">
        <x-filter-field :label="__('Jahr')" for="ve-year-filter">
            <input id="ve-year-filter" type="number" name="year" min="2000" max="2100" step="1"
                   class="input input-bordered input-sm w-28" value="{{ $year }}" data-autosubmit>
        </x-filter-field>
        <x-slot:extra>
            <x-icon-btn icon="arrow_back" size="sm"
                        :href="route('duties.index', ['tab' => 'urlaub'])"
                        show-label>{{ __('Zur Urlaubsliste') }}</x-icon-btn>
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('vacation-entitlements.create', ['year' => $year, 'dialog' => 1])"
                        show-label>{{ __('Anspruch anlegen') }}</x-icon-btn>
        </x-slot:extra>
    </x-filter-bar>

    @if ($usersWithoutEntitlement->isNotEmpty())
        <div class="alert alert-warning text-sm flex-wrap">
            <span>{{ __(':count Mitarbeiter ohne Anspruch für :year.', ['count' => $usersWithoutEntitlement->count(), 'year' => $year]) }}</span>
            <form method="POST" action="{{ route('vacation-entitlements.bulk') }}" class="flex items-center gap-2">
                @csrf
                <input type="hidden" name="year" value="{{ $year }}">
                <label class="text-xs" for="ve-bulk-days">{{ __('Standardanspruch (Tage)') }}</label>
                <input id="ve-bulk-days" type="number" name="default_days" min="0" max="365" step="0.5"
                       class="input input-bordered input-sm w-24" value="{{ $defaultDays }}">
                <button type="submit" class="btn btn-sm btn-primary">{{ __('Fehlende anlegen') }}</button>
            </form>
        </div>
    @endif

    <x-table table-sort="client" scroll="flex" :pinRows="true" :zebra="true" size="sm">
        <x-slot:head>
            <tr>
                <x-table.th sort type="string" default>{{ __('Mitarbeiter') }}</x-table.th>
                <x-table.th sort type="number">{{ __('Anspruch') }}</x-table.th>
                <x-table.th sort type="number">{{ __('Übertrag') }}</x-table.th>
                <th>{{ __('Übertrag verfällt am') }}</th>
                <x-table.th sort type="number">{{ __('Genommen') }}</x-table.th>
                <x-table.th sort type="number">{{ __('Beantragt') }}</x-table.th>
                <x-table.th sort type="number">{{ __('Rest') }}</x-table.th>
                <th>{{ __('Notiz') }}</th>
                <th class="w-px"></th>
            </tr>
        </x-slot:head>
        @forelse ($entitlements as $entitlement)
            @php
                $balance = $balances[(int) $entitlement->user_id] ?? null;
                $remaining = $balance?->remainingDays();
                $afterPending = $balance?->remainingAfterPendingDays();
            @endphp
            <tr class="hover">
                <td class="font-medium">{{ $entitlement->user?->name ?? '—' }}</td>
                <td class="tabular-nums">{{ rtrim(rtrim(\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($entitlement->entitled_days, 1, withThousandsSeparator: true), '0'), ',') }}</td>
                <td class="tabular-nums">{{ rtrim(rtrim(\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($entitlement->carryover_days, 1, withThousandsSeparator: true), '0'), ',') }}</td>
                <td class="whitespace-nowrap">{{ $entitlement->carryover_expires_on?->fdate() ?? '—' }}</td>
                <td class="tabular-nums">{{ $balance !== null ? rtrim(rtrim(\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($balance->takenDays, 1, withThousandsSeparator: true), '0'), ',') : '—' }}</td>
                <td class="tabular-nums">{{ $balance !== null ? rtrim(rtrim(\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($balance->pendingDays, 1, withThousandsSeparator: true), '0'), ',') : '—' }}</td>
                <td class="tabular-nums" data-sort-value="{{ $remaining ?? 0 }}">
                    @if ($remaining !== null)
                        <span @class(['text-error font-semibold' => $afterPending !== null && $afterPending < 0])>
                            {{ rtrim(rtrim(\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($remaining, 1, withThousandsSeparator: true), '0'), ',') }}
                        </span>
                        @if ($afterPending !== null && $afterPending < 0)
                            <span class="tooltip tooltip-left" data-tip="{{ __('Offene Anträge übersteigen den Restanspruch.') }}">
                                <x-icon name="warning" class="text-error" />
                            </span>
                        @endif
                    @else
                        —
                    @endif
                </td>
                <td class="max-w-xs truncate text-base-content/60 text-xs">{{ $entitlement->note }}</td>
                <td class="text-right">
                    <div class="flex items-center justify-end gap-1">
                        <x-icon-btn icon="edit"
                                    data-entry-modal-trigger
                                    :href="route('vacation-entitlements.edit', $entitlement) . '?dialog=1'"
                                    :label="__('Bearbeiten')" />
                        <x-action-form :action="route('vacation-entitlements.destroy', $entitlement)" method="DELETE"
                              :confirm="__('Urlaubsanspruch wirklich löschen?')"
                              :confirm-label="__('Löschen')">
                            <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                        </x-action-form>
                    </div>
                </td>
            </tr>
        @empty
            <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">event_available</span>' :colspan="9" :title="__('Keine Ansprüche für dieses Jahr angelegt')" compact />
        @endforelse
    </x-table>

</x-index-page>
@endsection
