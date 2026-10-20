{{--
  Created on   : Sat May 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Zeiten im Kundenportal (MVP-511/1238). Erwartet: $detail, $year, $years, $projects, $users,
     $filters, $kpis, $oldestVisibleDate, $summaries|$entries, $sort, $dir. --}}
@extends('customer.layout')

@section('title', __('Zeiterfassungen'))

@section('content')
    @php
        use App\Enums\CustomerPortal\{PortalCapability, PortalTimeDetail};
        $withDescription = $detail === PortalTimeDetail::EntriesWithDescription;
        // Rückfragen (MVP-512): CTA nur mit eigener Capability.
        $portalQueryCustomer = auth('customer')->user()?->customer;
        $canQuery = $portalQueryCustomer !== null
            && app(\App\Services\CustomerPortal\PortalVisibility::class)->allows($portalQueryCustomer, PortalCapability::Queries);
        $columns = ($withDescription ? 5 : 4) + ($canQuery ? 1 : 0);
        $listRoute = route('customer.time-entries.index');
        // Jahreswechsel behält Projekt, Mitarbeiter und Sortierung; Von/Bis gehören zum Jahr.
        $carry = array_filter(['project' => $filters['project'], 'user' => $filters['user'], 'q' => $filters['q'], 'sort' => $sort !== 'date' ? $sort : null, 'dir' => $dir !== 'desc' ? $dir : null], static fn ($v) => $v !== null && $v !== '');
        $yearTabs = collect($years)->map(fn (int $y): array => [
            'label' => (string) $y,
            'href' => route('customer.time-entries.index', ['year' => $y] + $carry),
            'active' => $y === $year,
        ])->all();
        $hours = fn (int $minutes): string => \App\Support\Formats::duration($minutes, 'clock');
    @endphp

    <x-page-toolbar :title="__('Zeiterfassungen')" :subtitle="__('customer_portal.time.subtitle')" class="mb-4" />

    <div class="space-y-4">
        <x-tab-nav :items="$yearTabs" class="w-fit max-w-full flex-nowrap overflow-x-auto" aria-label="{{ __('customer_portal.time.years_nav') }}" />

        <x-filter-bar :action="$listRoute" method="GET" :reset="route('customer.time-entries.index', ['year' => $year])">
            <input type="hidden" name="year" value="{{ $year }}">
            <x-filter-field :label="__('Projekt')" for="portal-time-project">
                <select id="portal-time-project" name="project" class="select select-sm shrink-0">
                    <option value="">{{ __('customer_portal.time.filter_project_all') }}</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->sqid }}" @selected($filters['project'] === $project->sqid)>{{ $project->name }}</option>
                    @endforeach
                </select>
            </x-filter-field>
            @if ($detail->showsEntries())
                <x-filter-field :label="__('Mitarbeiter')" for="portal-time-user">
                    <select id="portal-time-user" name="user" class="select select-sm shrink-0">
                        <option value="">{{ __('customer_portal.time.filter_user_all') }}</option>
                        @foreach ($users as $member)
                            <option value="{{ $member->sqid }}" @selected($filters['user'] === $member->sqid)>{{ $member->name }}</option>
                        @endforeach
                    </select>
                </x-filter-field>
            @endif
            <x-filter-field :label="__('Zeitraum')" for="portal-time-from">
                <x-date-range class="w-80 shrink-0" :label="false" from-name="from" to-name="to" from-id="portal-time-from" to-id="portal-time-to"
                              :from="$filters['from']" :to="$filters['to']" :min="sprintf('%04d-01-01', $year)" :max="sprintf('%04d-12-31', $year)" />
            </x-filter-field>
            @if ($withDescription)
                <x-filter-field :label="__('customer_portal.time.filter_search')" for="portal-time-q" class="flex-1 min-w-52">
                    <input id="portal-time-q" type="search" name="q" value="{{ $filters['q'] }}" maxlength="100"
                           class="input input-sm w-full" placeholder="{{ __('customer_portal.time.filter_search') }}">
                </x-filter-field>
            @endif
        </x-filter-bar>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <x-kpi-tile :label="__('customer_portal.time.kpi_year', ['year' => $year])" :value="$hours($kpis['year_minutes'])" format="text" tone="primary" />
            <x-kpi-tile :label="__('customer_portal.time.kpi_filtered')" :value="$hours($kpis['filtered_minutes'])" format="text" tone="info" />
            <x-kpi-tile :label="__('customer_portal.time.kpi_entries')" :value="$kpis['entries']" />
            <x-kpi-tile :label="__('customer_portal.time.kpi_projects')" :value="$kpis['projects']" />
        </div>

        @if ($detail === PortalTimeDetail::Summary)
            {{-- Stufe „Nur Summen": je Monat und Projekt, keine Einzelzeilen. --}}
            <x-card>
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <x-table.th>{{ __('Monat') }}</x-table.th>
                            <x-table.th>{{ __('Projekt') }}</x-table.th>
                            <x-table.th align="right">{{ __('Stunden') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @forelse ($summaries as $row)
                        <tr>
                            <td class="whitespace-nowrap tabular-nums">{{ \Carbon\CarbonImmutable::createFromFormat('Y-m', $row->month)->isoFormat('MMMM YYYY') }}</td>
                            <td>{{ $row->project_name }}</td>
                            <td class="whitespace-nowrap text-right tabular-nums">{{ $hours((int) $row->minutes) }}</td>
                        </tr>
                    @empty
                        <x-table.empty :colspan="3" icon="schedule" :title="__('customer_portal.time.empty_year', ['year' => $year])" compact />
                    @endforelse
                    @if ($summaries->isNotEmpty())
                        <tr class="font-semibold">
                            <td colspan="2">{{ __('customer_portal.time.summary_total', ['year' => $year]) }}</td>
                            <td class="whitespace-nowrap text-right tabular-nums">{{ $hours($kpis['filtered_minutes']) }}</td>
                        </tr>
                    @endif
                </x-table>
            </x-card>
        @else
            <x-card>
                <x-table bare table-sort="server" :route="$listRoute" :current-sort="$sort" :current-dir="$dir" :sort-params="array_filter($filters, static fn ($v) => $v !== null)">
                    <x-slot:head>
                        <tr>
                            <x-table.th sort="date" default>{{ __('Datum') }}</x-table.th>
                            <x-table.th sort="minutes" align="right">{{ __('Dauer') }}</x-table.th>
                            <x-table.th sort="project">{{ __('Projekt') }}</x-table.th>
                            <x-table.th sort="user">{{ __('Mitarbeiter') }}</x-table.th>
                            @if ($withDescription)
                                <x-table.th>{{ __('Beschreibung') }}</x-table.th>
                            @endif
                            @if ($canQuery)
                                <x-table.th align="right"><span class="sr-only">{{ __('Aktionen') }}</span></x-table.th>
                            @endif
                        </tr>
                    </x-slot:head>
                    @forelse ($entries as $entry)
                        <tr>
                            <td class="whitespace-nowrap tabular-nums">{{ $entry->date?->fdate() ?? '—' }}</td>
                            <td class="whitespace-nowrap text-right tabular-nums">{{ $entry->hoursFormatted() }}</td>
                            <td>{{ $entry->project?->name }}</td>
                            <td>{{ $entry->user?->name }}</td>
                            @if ($withDescription)
                                {{-- Beschreibung nur für ausdrücklich veröffentlichte Einträge. --}}
                                <td class="max-w-md truncate" title="{{ $entry->customer_visible_at !== null ? $entry->description : '' }}">{{ $entry->customer_visible_at !== null ? $entry->description : '—' }}</td>
                            @endif
                            @if ($canQuery)
                                <td class="text-right whitespace-nowrap">
                                    <x-button :href="route('customer.queries.create', ['subject_type' => 'time_entry', 'subject' => $entry->sqid])"
                                            tone="ghost" size="xs" icon="help">{{ __('Rückfrage') }}</x-button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <x-table.empty :colspan="$columns" icon="schedule"
                                       :title="$kpis['year_minutes'] > 0 ? __('customer_portal.time.empty_filtered') : __('customer_portal.time.empty_year', ['year' => $year])" compact />
                    @endforelse
                </x-table>
            </x-card>
            <x-pagination :paginator="$entries" standing />
        @endif

        @if ($oldestVisibleDate !== null)
            <p class="text-xs text-muted">{{ __('customer_portal.time.since', ['date' => $oldestVisibleDate->fdate()]) }}</p>
        @endif
    </div>
@endsection
