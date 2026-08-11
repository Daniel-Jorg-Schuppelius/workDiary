{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', __('Veranstaltungen'))
@section('nav-title', __('Veranstaltungen'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    use App\Enums\Event\EventStatus;
    use App\Enums\Event\EventType;
    use App\Enums\Event\EventVisibility;

    /** @var \Illuminate\Pagination\LengthAwarePaginator $events */
    /** @var array<string,int> $counts */
    /** @var \Illuminate\Support\Collection $categories */
@endphp

@section('content')
    <x-index-page overflow="clip" :subtitle="__('Veranstaltungen und Termine planen und verwalten.')">
        <x-slot:actions>
            <x-icon-btn icon="calendar_month" tone="ghost" size="sm" :href="route('events.calendar')" show-label>
                {{ __('Kalender') }}
            </x-icon-btn>
            @can('create', App\Models\Event::class)
                <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger :href="route('events.create').'?dialog=1'" show-label>
                    {{ __('Neue Veranstaltung') }}
                </x-icon-btn>
            @endcan
        </x-slot:actions>
        <x-filter-bar :action="route('events.index')" :reset="route('events.index')">
            <x-filter-field :label="__('Suche')" for="ev-q" class="flex-1 min-w-60">
                <input id="ev-q" type="search" name="q" value="{{ request('q') }}"
                       placeholder="{{ __('Titel, Thema …') }}"
                       class="input input-sm input-bordered w-full">
            </x-filter-field>
            <x-filter-field :label="__('Typ')" for="ev-event-type" class="min-w-40">
                <select id="ev-event-type" name="event_type" class="select select-sm select-bordered w-full"
                        data-autosubmit>
                    <option value="">{{ __('Alle') }}</option>
                    @foreach (EventType::options() as $value => $label)
                        <option value="{{ $value }}" @selected(request('event_type') === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-filter-field>
            <x-filter-field :label="__('Status')" for="ev-status" class="min-w-40">
                <select id="ev-status" name="status" class="select select-sm select-bordered w-full"
                        data-autosubmit>
                    <option value="">{{ __('Alle') }}</option>
                    @foreach (EventStatus::options() as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-filter-field>
            <x-filter-field :label="__('Sichtbarkeit')" for="ev-visibility" class="min-w-40">
                <select id="ev-visibility" name="visibility" class="select select-sm select-bordered w-full"
                        data-autosubmit>
                    <option value="">{{ __('Alle') }}</option>
                    @foreach (EventVisibility::options() as $value => $label)
                        <option value="{{ $value }}" @selected(request('visibility') === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-filter-field>
            <x-filter-field :label="__('Kategorie')" for="ev-category" class="min-w-44">
                <select id="ev-category" name="category_id" class="select select-sm select-bordered w-full"
                        data-autosubmit>
                    <option value="">{{ __('Alle') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->sqid }}" @selected(request('category_id') === $category->sqid)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </x-filter-field>

            <x-filter-toggle name="only_mandatory" id="ev-only-mandatory"
                             :label="__('Nur Pflicht')"
                             :checked="(bool) request('only_mandatory')" data-autosubmit />
        </x-filter-bar>

        <div class="grid grid-cols-1 gap-3 flex-none sm:grid-cols-2 lg:grid-cols-4">
            <x-kpi-tile :label="__('Anstehend')"         :value="$counts['upcoming']  ?? 0" tone="primary" />
            <x-kpi-tile :label="__('Heute')"             :value="$counts['today']     ?? 0" tone="info" />
            <x-kpi-tile :label="__('Pflichtschulungen')" :value="$counts['mandatory'] ?? 0" tone="warning" />
            <x-kpi-tile :label="__('Gesamt')"            :value="$counts['total']     ?? 0" tone="neutral" />
        </div>

        <x-table scroll="flex" :pinRows="true" :zebra="true" size="sm" table-sort="server"
                 :route="route('events.index')" :current-sort="$sort" :current-dir="$dir"
                 :sort-params="request()->except(['sort', 'dir', 'page'])">
            <x-slot:head>
                <tr>
                    <x-table.th sort="title">{{ __('Titel') }}</x-table.th>
                    <x-table.th sort="event_type">{{ __('Typ') }}</x-table.th>
                    <th>{{ __('Kategorie') }}</th>
                    <x-table.th sort="started_at">{{ __('Termin') }}</x-table.th>
                    <th>{{ __('Räume') }}</th>
                    <th>{{ __('Verantwortlich') }}</th>
                    <th class="text-right">{{ __('Teilnehmer') }}</th>
                    <x-table.th sort="status">{{ __('Status') }}</x-table.th>
                    <th class="w-32 text-right">{{ __('Aktion') }}</th>
                </tr>
            </x-slot:head>
            <tbody>
                @forelse ($events as $event)
                    <tr class="hover">
                        <td class="font-semibold">
                            <a href="{{ route('events.show', $event) }}" class="link link-hover">
                                {{ $event->title }}
                            </a>
                            @if ($event->topic)
                                <div class="text-xs opacity-70">{{ $event->topic }}</div>
                            @endif
                            @if ($event->is_mandatory)
                                <x-status-badge tone="warning" size="sm" class="ml-1">{{ __('Pflicht') }}</x-status-badge>
                            @endif
                        </td>
                        <td>{{ $event->event_type?->label() }}</td>
                        <td>
                            @if ($event->category)
                                <span class="badge badge-sm" style="background:{{ $event->category->color ?? '#999' }};color:#fff">
                                    {{ $event->category->name }}
                                </span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap">
                            <div class="text-sm">{{ $event->started_at?->isoFormat('LLL') }}</div>
                            <div class="text-xs opacity-70">– {{ $event->ended_at?->isoFormat('LLL') }}</div>
                        </td>
                        <td>
                            <div class="flex flex-wrap gap-1">
                                @foreach ($event->rooms as $room)
                                    <x-status-badge tone="ghost" size="sm">{{ $room->name }}</x-status-badge>
                                @endforeach
                            </div>
                        </td>
                        <td>{{ $event->responsibleUser?->name }}</td>
                        <td class="text-right tabular-nums">{{ $event->participants_count ?? $event->participants()->count() }}</td>
                        <td>
                            <x-status-badge :status="$event->status?->value" :label="$event->status?->label()" />
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <x-icon-btn icon="visibility" :href="route('events.show', $event)" :label="__('Details')" />
                            @can('update', $event)
                                <x-icon-btn icon="edit"
                                            data-entry-modal-trigger
                                            :href="route('events.edit', $event).'?dialog=1'"
                                            :label="__('Bearbeiten')" />
                            @endcan
                            @can('cancel', $event)
                                <x-action-form :action="route('events.cancel', $event)" method="PATCH"
                                      :confirm="__('Veranstaltung wirklich absagen?')"
                                      :confirm-label="__('Absagen')">
                                    <x-icon-btn type="submit" icon="cancel" tone="warning" :label="__('Absagen')" />
                                </x-action-form>
                            @endcan
                            @can('delete', $event)
                                <x-action-form :action="route('events.destroy', $event)" method="DELETE"
                                      :confirm="__('Veranstaltung wirklich löschen?')"
                                      :confirm-label="__('Löschen')">
                                    <x-icon-btn type="submit" icon="delete" tone="error" :label="__('Löschen')" />
                                </x-action-form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="9"
                        icon='<span class="material-symbols-outlined" aria-hidden="true">event</span>'
                        :title="__('Keine Veranstaltungen gefunden')" compact />
                @endforelse
            </tbody>
        </x-table>

        <x-pagination :paginator="$events" standing />
    </x-index-page>
@endsection
