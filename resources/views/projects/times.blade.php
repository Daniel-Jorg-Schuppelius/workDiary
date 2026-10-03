{{--
  Created on   : Sat Oct 03 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : times.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Zeitenübersicht über alle Projekte (MVP-1073): Zeiteinträge im
  Header-Zeitraum, gruppiert nach Projekt, Tag oder Person.
--}}

@extends('layouts.app')
@section('title', __('Projekte') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Projekte'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
@php
    $fmt = fn (int $min): string => \App\Support\Formats::duration($min, 'clock');
    $groupOptions = array_filter([
        'project' => __('Projekt'),
        'day' => __('Datum'),
        'user' => $seesAll ? __('Person') : null,
    ]);
    // Die Spalte, nach der gruppiert wird, steht schon im Gruppenkopf.
    $showDate = $group !== 'day';
    $showProject = $group !== 'project';
    $showUser = $seesAll && $group !== 'user';
    $colspan = 4 + (int) $showDate + (int) $showProject + (int) $showUser;
    $listQuery = request()->except(['page', 'group']);
@endphp
<x-index-page overflow="clip" :subtitle="__('Zeiteinträge aller Projekte im gewählten Zeitraum.')">
    @include('projects._list_tabs')

    <x-filter-bar :action="route('projects.times')" :reset="$hasActiveFilters ? route('projects.times', array_filter(['group' => $group === 'project' ? null : $group])) : null">
        @if ($group !== 'project')
            <input type="hidden" name="group" value="{{ $group }}">
        @endif

        <x-filter-field :label="__('Suche')" for="project-times-q" class="min-w-52 flex-1">
            <input id="project-times-q" type="search" name="q" value="{{ $filters['q'] }}"
                   class="input input-sm input-bordered w-full"
                   placeholder="{{ __('Projekt, Aufgabe oder Beschreibung …') }}">
        </x-filter-field>

        <x-filter-field :label="__('Kunde')" for="project-times-customer" class="min-w-44">
            <select id="project-times-customer" name="customer" class="select select-sm select-bordered w-full">
                <option value="">{{ __('Alle Kunden') }}</option>
                @foreach ($customers as $customer)
                    <option value="{{ $customer->sqid }}" @selected($filters['customer'] === $customer->sqid)>{{ $customer->name }}</option>
                @endforeach
            </select>
        </x-filter-field>

        <x-filter-field :label="__('Projekt')" for="project-times-project" class="min-w-44">
            <select id="project-times-project" name="project" class="select select-sm select-bordered w-full">
                <option value="">{{ __('Alle Projekte') }}</option>
                <x-project-options :projects="$projects" :selected="$filters['project']" />
            </select>
        </x-filter-field>

        @if ($seesAll)
            <x-filter-field :label="__('Mitarbeitende')" for="project-times-user" class="min-w-40">
                <select id="project-times-user" name="user" class="select select-sm select-bordered w-full">
                    <option value="">{{ __('Alle Mitarbeitenden') }}</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->sqid }}" @selected($filters['user'] === $user->sqid)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </x-filter-field>
        @endif

        @if ($tags->isNotEmpty())
            <x-filter-field :label="__('Tag')" for="project-times-tag" class="min-w-36">
                <select id="project-times-tag" name="tag" class="select select-sm select-bordered w-full">
                    <option value="">{{ __('Alle Tags') }}</option>
                    @foreach ($tags as $tag)
                        <option value="{{ $tag->sqid }}" @selected($filters['tag'] === $tag->sqid)>{{ $tag->displayName() }}</option>
                    @endforeach
                </select>
            </x-filter-field>
        @endif

        <x-filter-field :label="__('Abrechenbar')" for="project-times-billable" class="min-w-36">
            <select id="project-times-billable" name="billable" class="select select-sm select-bordered w-full">
                <option value="">{{ __('Abrechenbar und nicht abrechenbar') }}</option>
                <option value="yes" @selected($filters['billable'] === 'yes')>{{ __('Nur abrechenbar') }}</option>
                <option value="no" @selected($filters['billable'] === 'no')>{{ __('Nur nicht abrechenbar') }}</option>
            </select>
        </x-filter-field>
    </x-filter-bar>

    {{-- Kennzahlen des Zeitraums und Gruppierung in einer Zeile: die Tabelle braucht die Höhe. --}}
    <div class="flex flex-none flex-wrap items-center justify-between gap-x-4 gap-y-2 px-1 text-sm">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
            <span class="text-muted">{{ $rangeLabel }}</span>
            <span><span class="font-semibold tabular-nums">{{ $totals['count'] }}</span> {{ __('Einträge') }}</span>
            <span><span class="font-semibold tabular-nums">{{ $fmt($totals['minutes']) }}</span> {{ __('gesamt') }}</span>
            <span><span class="font-semibold tabular-nums">{{ $fmt($totals['billable']) }}</span> {{ __('abrechenbar') }}</span>
            <span><span class="font-semibold tabular-nums">{{ $totals['projects'] }}</span> {{ __('Projekte mit Zeiten') }}</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-muted">{{ __('Gruppieren nach') }}</span>
            <div class="join">
                @foreach ($groupOptions as $value => $label)
                    <a href="{{ route('projects.times', $listQuery + ['group' => $value]) }}"
                       class="join-item btn btn-sm {{ $group === $value ? 'btn-primary' : 'btn-ghost' }}"
                       @if ($group === $value) aria-current="true" @endif>{{ $label }}</a>
                @endforeach
            </div>
            <x-icon-btn icon="unfold_less" size="sm" tone="outline"
                        data-toggle-rows-all="project-times" aria-expanded="true"
                        :label="__('Alle Gruppen ein- oder ausklappen')" />
        </div>
    </div>

    <x-table id="project-times" scroll="flex" :pinRows="true" :zebra="false" table-sort="server"
             :route="route('projects.times')"
             :current-sort="$sort"
             :current-dir="$dir"
             :sort-params="request()->except(['sort', 'dir', 'page'])"
             empty-icon="schedule" :empty-title="__('Keine Zeiteinträge im gewählten Zeitraum.')">
        <x-slot:head>
            <tr>
                @if ($showDate)
                    <x-table.th sort="date" default="desc">{{ __('Datum') }}</x-table.th>
                @endif
                @if ($showProject)
                    <x-table.th sort="project">{{ __('Projekt') }}</x-table.th>
                @endif
                @if ($showUser)
                    <x-table.th sort="user">{{ __('Mitarbeitende') }}</x-table.th>
                @endif
                <x-table.th sort="minutes" align="right">{{ __('Zeit') }}</x-table.th>
                <x-table.th sort="task">{{ __('Aufgabe') }}</x-table.th>
                <x-table.th sort="description">{{ __('Beschreibung') }}</x-table.th>
                <th class="text-right"></th>
            </tr>
        </x-slot:head>
        @foreach ($groups as $data)
            @php
                $groupId = 'project-times-group-' . $loop->index;
                $first = $data['entries']->first();
                $groupLabel = match ($group) {
                    'day' => $first->date?->fdate() ?? '—',
                    'user' => $first->user->name ?? '—',
                    default => $first->project->name ?? '—',
                };
            @endphp
            <tr class="bg-base-200/60">
                <td colspan="{{ $colspan }}">
                    <div class="flex items-center gap-2">
                        <x-icon-btn icon="expand_more"
                                    data-toggle-rows="{{ $groupId }}" aria-expanded="true"
                                    :label="__('Einträge von :name ein- oder ausklappen', ['name' => $groupLabel])" />
                        @if ($group === 'project')
                            <span class="inline-block h-3 w-3 shrink-0 rounded-full"
                                  style="background:{{ $first->project->color ?? '#94a3b8' }}"></span>
                            @if ($first->project)
                                <a href="{{ route('projects.show', ['project' => $first->project, 'tab' => 'time']) }}"
                                   class="font-['Space_Grotesk'] font-semibold hover:text-primary">{{ $groupLabel }}</a>
                            @else
                                <span class="font-['Space_Grotesk'] font-semibold">{{ $groupLabel }}</span>
                            @endif
                            <span class="text-muted">{{ $first->project?->customer?->name ?? __('Intern (ohne Kunde)') }}</span>
                        @elseif ($group === 'day')
                            <span class="font-['Space_Grotesk'] font-semibold">{{ $groupLabel }}</span>
                            <span class="text-muted">{{ $first->date?->isoFormat('dddd') }}</span>
                        @else
                            <span class="font-['Space_Grotesk'] font-semibold">{{ $groupLabel }}</span>
                        @endif
                        <span class="ml-auto whitespace-nowrap text-muted">
                            {{ trans_choice(':count Eintrag|:count Einträge', $data['count'], ['count' => $data['count']]) }}
                        </span>
                        <span class="w-16 whitespace-nowrap text-right font-semibold tabular-nums">{{ $fmt($data['minutes']) }}</span>
                    </div>
                </td>
            </tr>
            @foreach ($data['entries'] as $entry)
                <tr id="time-entry-{{ $entry->sqid }}" class="hover" data-row-group="{{ $groupId }}">
                    @if ($showDate)
                        <td class="whitespace-nowrap">{{ $entry->date?->fdate() ?? '—' }}</td>
                    @endif
                    @if ($showProject)
                        <td>
                            <div class="flex items-center gap-2">
                                <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-full"
                                      style="background:{{ $entry->project->color ?? '#94a3b8' }}"></span>
                                @if ($entry->project)
                                    <a href="{{ route('projects.show', ['project' => $entry->project, 'tab' => 'time']) }}"
                                       class="hover:text-primary">{{ $entry->project->name }}</a>
                                    <span class="text-muted">{{ $entry->project->customer?->name }}</span>
                                @else
                                    <span>—</span>
                                @endif
                            </div>
                        </td>
                    @endif
                    @if ($showUser)
                        <td>{{ $entry->user->name ?? '—' }}</td>
                    @endif
                    <td class="whitespace-nowrap text-right font-medium tabular-nums">
                        @if (! $entry->billable)
                            <x-status-badge tone="warning" size="xs" class="mr-1">{{ __('nicht abrechenbar') }}</x-status-badge>
                        @endif
                        {{ $entry->hoursFormatted() }}
                    </td>
                    <td class="text-base-content/70">{{ $entry->task->title ?? '—' }}</td>
                    <td class="max-w-xs text-base-content/70">
                        <span class="block truncate" title="{{ $entry->description }}">{{ $entry->description }}</span>
                        @if ($entry->tags->isNotEmpty())
                            <span class="mt-0.5 flex flex-wrap gap-1">
                                @foreach ($entry->tags as $tag)
                                    <span class="badge badge-xs" style="background:{{ $tag->color ?? '#94a3b8' }};color:#fff">{{ $tag->displayName() }}</span>
                                @endforeach
                            </span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap text-right">
                        @if ($entry->project)
                            @can('update', $entry)
                                <x-icon-btn icon="edit"
                                            data-entry-modal-trigger
                                            :href="route('projects.time-entries.edit', [$entry->project, $entry, 'return_to' => 'times'])"
                                            :label="__('Bearbeiten')" />
                                <x-icon-btn icon="call_split"
                                            data-entry-modal-trigger
                                            :href="route('time-entries.allocations.edit', $entry)"
                                            :label="__('allocation.action.split')" />
                            @endcan
                            @can('delete', $entry)
                                <form method="POST" action="{{ route('projects.time-entries.destroy', [$entry->project, $entry]) }}"
                                      data-confirm-dialog
                                      data-confirm-title="{{ __('Zeiteintrag löschen') }}"
                                      data-confirm-label="{{ __('Löschen') }}"
                                      class="inline">
                                    @csrf @method('DELETE')
                                    <input type="hidden" name="return_to" value="times">
                                    <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                                </form>
                            @endcan
                        @endif
                    </td>
                </tr>
            @endforeach
        @endforeach
    </x-table>

    <x-pagination :paginator="$entries" standing />
</x-index-page>
@endsection
