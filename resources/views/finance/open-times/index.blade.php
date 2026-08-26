{{--
  Created on   : Fri Aug 01 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Offene Zeiten (MVP-460): Arbeitsliste der Buchhaltung über alle noch nicht
  abgerechneten Zeiteinträge, mit Summen je Kunde/Projekt und CSV-Export.
--}}

@extends('layouts.app')

@section('title', __('finance.open_times.title'))
@section('nav-title', __('finance.open_times.title'))

@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('finance.open_times.subtitle')">
        <x-slot:actions>
            @if ($canMarkBilled)
                <x-icon-btn icon="price_check" tone="outline" size="sm"
                            data-entry-modal-trigger
                            :href="route('finance.open-times.mark-billed-dialog')"
                            show-label>{{ __('finance.open_times.action.mark_billed') }}</x-icon-btn>
            @endif
            <x-icon-btn icon="download" tone="outline" size="sm"
                        :href="route('finance.open-times.export', request()->query())"
                        show-label>{{ __('finance.open_times.action.export') }}</x-icon-btn>
        </x-slot:actions>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
            <x-kpi-tile :label="__('finance.open_times.kpi.count')" :value="$totals['count']" format="int" />
            <x-kpi-tile :label="__('finance.open_times.kpi.minutes')" :value="\App\Support\Formats::duration($totals['minutes'])" format="raw" />
            <x-kpi-tile :label="__('finance.open_times.kpi.amount')"
                        :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($totals['amount'], 2, withThousandsSeparator: true) . ' €'"
                        format="raw" tone="primary"
                        :hint="__('finance.open_times.kpi.amount_hint')" />
            <x-kpi-tile :label="__('finance.open_times.kpi.late')" :value="$lateCount" format="int"
                        :tone="$lateCount > 0 ? 'warning' : 'neutral'"
                        :hint="__('finance.open_times.kpi.late_hint')" />
            <x-kpi-tile :label="__('finance.open_times.kpi.stale', ['days' => $staleAfterDays])" :value="$staleCount" format="int"
                        :tone="$staleCount > 0 ? 'warning' : 'neutral'"
                        :hint="__('finance.open_times.kpi.stale_hint')" />
        </div>

        @if ($ledgerManagedCount > 0)
            {{-- Saldo-geführte Kunden (Konto-/Pauschal-Modus) sind ausgeblendet;
                 der Zähler hält die Kontrollfunktion der Liste aufrecht. --}}
            <div class="alert text-sm">
                <x-icon name="info" />
                <span>{{ trans_choice('finance.open_times.ledger_managed', $ledgerManagedCount, ['count' => $ledgerManagedCount]) }}</span>
            </div>
        @endif

        @if ($invoicedMismatches->isNotEmpty())
            <div class="rounded-box border border-warning/40 bg-warning/5 px-4 py-3">
                <p class="text-sm font-medium">{{ __('finance.open_times.mismatch.heading') }}</p>
                <p class="mt-1 text-xs text-muted">{{ __('finance.open_times.mismatch.hint') }}</p>
                <ul class="mt-2 space-y-1 text-sm">
                    @foreach ($invoicedMismatches as $diary)
                        <li class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('diary.show', $diary) }}" class="link">{{ $diary->title ?? ('#' . $diary->sqid) }}</a>
                            <span class="text-muted">{{ $diary->customer->name ?? '—' }}</span>
                            <x-status-badge tone="warning" outline>{{ trans_choice('finance.open_times.mismatch.open_entries', (int) $diary->open_time_entries_count, ['count' => (int) $diary->open_time_entries_count]) }}</x-status-badge>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-filter-bar :action="route('finance.open-times.index')"
                      :reset="$hasActiveFilters ? route('finance.open-times.index') : null">
            <x-filter-field :label="__('finance.open_times.filter.customer')" for="open-times-customer" class="min-w-44">
                <select id="open-times-customer" name="customer" class="select select-sm select-bordered w-full">
                    <option value="">{{ __('finance.filter.all') }}</option>
                    @foreach ($customers as $c)
                        <option value="{{ $c->sqid }}" @selected($filters['customer'] === $c->sqid)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </x-filter-field>

            <x-filter-field :label="__('finance.open_times.filter.project')" for="open-times-project" class="min-w-44">
                <select id="open-times-project" name="project" class="select select-sm select-bordered w-full">
                    <option value="">{{ __('finance.filter.all') }}</option>
                    <x-project-options :projects="$projects" :selected="(string) $filters['project']" />
                </select>
            </x-filter-field>

            <x-filter-field :label="__('finance.open_times.filter.user')" for="open-times-user" class="min-w-40">
                <select id="open-times-user" name="user" class="select select-sm select-bordered w-full">
                    <option value="">{{ __('finance.filter.all') }}</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->sqid }}" @selected($filters['user'] === $u->sqid)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </x-filter-field>

            <x-filter-field :label="__('finance.open_times.filter.billable')" for="open-times-billable" class="min-w-36">
                <select id="open-times-billable" name="billable" class="select select-sm select-bordered w-full">
                    <option value="yes" @selected($filters['billable'] === 'yes')>{{ __('finance.open_times.filter.billable_yes') }}</option>
                    <option value="no" @selected($filters['billable'] === 'no')>{{ __('finance.open_times.filter.billable_no') }}</option>
                    <option value="all" @selected($filters['billable'] === 'all')>{{ __('finance.filter.all') }}</option>
                </select>
            </x-filter-field>

            {{-- „Alle offenen Zeiten" wird über den Außerhalb-Hinweis aktiviert
                 (Link unten) und beim Umfiltern beibehalten; Abschalten über
                 den Zurücksetzen-Link der Filterleiste. --}}
            @if ($filters['all'])
                <input type="hidden" name="all" value="1">
            @endif
        </x-filter-bar>

        @if ($outsideRangeCount > 0)
            {{-- Offene-Posten-Schutz: Altbestand außerhalb des Zeitraums nie lautlos ausblenden. --}}
            <div class="alert alert-warning text-sm">
                <x-icon name="event_busy" />
                <span>{{ trans_choice('finance.open_times.outside_range', $outsideRangeCount, ['count' => $outsideRangeCount]) }}</span>
                <a href="{{ route('finance.open-times.index', array_merge(request()->query(), ['all' => 1])) }}" class="link whitespace-nowrap">
                    {{ __('finance.open_times.outside_range_show_all') }}
                </a>
            </div>
        @endif

        @if (count($groups) > 0)
            <details class="rounded-box border border-base-300 bg-base-100 shadow-xs">
                <summary class="cursor-pointer px-4 py-3 text-sm font-medium">{{ __('finance.open_times.groups_heading') }}</summary>
                <div class="px-4 pb-4">
                    <x-table :bare="true">
                        <x-slot:head>
                            <tr>
                                <th>{{ __('finance.open_times.column.customer') }}</th>
                                <th>{{ __('finance.open_times.column.project') }}</th>
                                <th class="text-right">{{ __('finance.open_times.column.count') }}</th>
                                <th class="text-right">{{ __('finance.open_times.column.duration') }}</th>
                                <th class="text-right">{{ __('finance.open_times.column.amount') }}</th>
                            </tr>
                        </x-slot:head>
                            @foreach ($groups as $group)
                                <tr class="hover">
                                    <td class="font-medium">{{ $group['customer_name'] ?? '—' }}</td>
                                    <td>{{ $group['project_name'] ?? '—' }}</td>
                                    <td class="text-right tabular-nums">{{ $group['entry_count'] }}</td>
                                    <td class="text-right"><x-duration :minutes="$group['minutes_sum']" /></td>
                                    <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($group['rate_sum'], 2, withThousandsSeparator: true) }}</td>
                                </tr>
                            @endforeach
                    </x-table>
                </div>
            </details>
        @endif

        <x-table scroll="flex">
            <x-slot:head>
                <tr>
                    <th>{{ __('finance.open_times.column.date') }}</th>
                    <th>{{ __('finance.open_times.column.customer') }}</th>
                    <th>{{ __('finance.open_times.column.project') }}</th>
                    <th>{{ __('finance.open_times.column.user') }}</th>
                    <th>{{ __('finance.open_times.column.description') }}</th>
                    <th>{{ __('finance.open_times.column.billable') }}</th>
                    <th class="text-right">{{ __('finance.open_times.column.duration') }}</th>
                    <th class="text-right">{{ __('finance.open_times.column.amount') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($entries as $entry)
                <tr class="hover">
                    <td class="whitespace-nowrap">{{ $entry->date?->format(\App\Support\Formats::date()) ?? '—' }}</td>
                    <td class="font-medium">
                        @if ($entry->project?->customer !== null)
                            <a class="link link-hover" href="{{ route('customers.show', $entry->project->customer) }}">{{ $entry->project->customer->name }}</a>
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @if ($entry->project !== null)
                            <a class="link link-hover" href="{{ route('projects.show', $entry->project) }}">{{ $entry->project->name }}</a>
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $entry->user?->name ?? '—' }}</td>
                    <td class="max-w-md truncate" title="{{ $entry->description }}">{{ $entry->description }}</td>
                    <td>
                        @php
                            $latestBilled = $latestBilledByCustomer[$entry->project->customer_id ?? 0] ?? null;
                            $isLate = $latestBilled !== null && $entry->date !== null && $entry->date->lte($latestBilled);
                        @endphp
                        @if ($entry->billable)
                            <span class="text-muted">{{ __('Ja') }}</span>
                        @else
                            <x-status-badge tone="warning" outline>{{ __('finance.open_times.badge.non_billable') }}</x-status-badge>
                        @endif
                        @if ($isLate)
                            <x-status-badge tone="warning">{{ __('finance.open_times.badge.late') }}</x-status-badge>
                        @endif
                    </td>
                    <td class="text-right"><x-duration :minutes="$entry->minutes" /></td>
                    <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($entry->rate?->toFloat() ?? 0.0, 2, withThousandsSeparator: true) }}</td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            @if ($entry->project !== null)
                                <x-icon-btn icon="visibility" tone="outline" size="xs"
                                            :href="route('projects.show', $entry->project) . '?tab=time'"
                                            :label="__('finance.open_times.action.show_project')" />
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="9"
                               :title="__('finance.open_times.empty_title')"
                               :message="$hasActiveFilters ? __('finance.open_times.empty_filtered') : __('finance.open_times.empty_message')" />
            @endforelse
        </x-table>

        <x-pagination :paginator="$entries" standing />
    </x-index-page>
@endsection
