@extends('layouts.app')
@section('title', __('Veranstaltungen'))
@section('nav-title', __('Veranstaltungen'))

@php
    use App\Enums\Event\EventStatus;
    use App\Enums\Event\EventType;
    use App\Enums\Event\EventVisibility;

    /** @var \Illuminate\Pagination\LengthAwarePaginator $events */
    /** @var array<string,int> $counts */
    /** @var \Illuminate\Support\Collection $categories */
@endphp

@section('content')
    <x-page-shell>
        <x-slot:toolbar>
            <x-page-toolbar :subtitle="__('Veranstaltungen und Termine planen und verwalten.')">
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
            </x-page-toolbar>
        </x-slot:toolbar>
        <x-filter-bar :action="route('events.index')" :reset="route('events.index')">
            <x-filter-field :label="__('Suche')" for="ev-q" class="flex-1 min-w-60">
                <input id="ev-q" type="search" name="q" value="{{ request('q') }}"
                       placeholder="{{ __('Titel, Thema …') }}"
                       class="input input-sm input-bordered w-full">
            </x-filter-field>
            <x-filter-field :label="__('Typ')" for="ev-event-type" class="min-w-40">
                <select id="ev-event-type" name="event_type" class="select select-sm select-bordered w-full"
                        onchange="this.form.submit()">
                    <option value="">{{ __('Alle') }}</option>
                    @foreach (EventType::options() as $value => $label)
                        <option value="{{ $value }}" @selected(request('event_type') === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-filter-field>
            <x-filter-field :label="__('Status')" for="ev-status" class="min-w-40">
                <select id="ev-status" name="status" class="select select-sm select-bordered w-full"
                        onchange="this.form.submit()">
                    <option value="">{{ __('Alle') }}</option>
                    @foreach (EventStatus::options() as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-filter-field>
            <x-filter-field :label="__('Sichtbarkeit')" for="ev-visibility" class="min-w-40">
                <select id="ev-visibility" name="visibility" class="select select-sm select-bordered w-full"
                        onchange="this.form.submit()">
                    <option value="">{{ __('Alle') }}</option>
                    @foreach (EventVisibility::options() as $value => $label)
                        <option value="{{ $value }}" @selected(request('visibility') === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-filter-field>
            <x-filter-field :label="__('Kategorie')" for="ev-category" class="min-w-44">
                <select id="ev-category" name="category_id" class="select select-sm select-bordered w-full"
                        onchange="this.form.submit()">
                    <option value="">{{ __('Alle') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->sqid }}" @selected(request('category_id') === $category->sqid)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </x-filter-field>

            <label class="flex items-center gap-2 pb-2">
                <input type="checkbox" id="ev-only-mandatory" name="only_mandatory" value="1"
                       @checked(request('only_mandatory'))
                       class="checkbox checkbox-primary checkbox-sm"
                       onchange="this.form.submit()">
                <span class="text-sm text-base-content/75">{{ __('Nur Pflicht') }}</span>
            </label>
        </x-filter-bar>

        <div class="grid grid-cols-1 gap-3 flex-none sm:grid-cols-2 lg:grid-cols-4">
            <x-kpi-tile :label="__('Anstehend')"         :value="$counts['upcoming']  ?? 0" tone="primary" />
            <x-kpi-tile :label="__('Heute')"             :value="$counts['today']     ?? 0" tone="info" />
            <x-kpi-tile :label="__('Pflichtschulungen')" :value="$counts['mandatory'] ?? 0" tone="warning" />
            <x-kpi-tile :label="__('Gesamt')"            :value="$counts['total']     ?? 0" tone="neutral" />
        </div>

        <x-table scroll="flex" :pinRows="true" :zebra="true" size="sm">
            <thead class="bg-base-200">
                <tr>
                    <th data-sort data-sort-default="asc">{{ __('Titel') }}</th>
                    <th data-sort>{{ __('Typ') }}</th>
                    <th data-sort>{{ __('Kategorie') }}</th>
                    <th data-sort>{{ __('Termin') }}</th>
                    <th>{{ __('Räume') }}</th>
                    <th data-sort>{{ __('Verantwortlich') }}</th>
                    <th class="text-right" data-sort data-sort-type="number">{{ __('Teilnehmer') }}</th>
                    <th data-sort>{{ __('Status') }}</th>
                    <th class="w-32 text-right">{{ __('Aktion') }}</th>
                </tr>
            </thead>
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
                                <span class="badge badge-warning badge-sm ml-1">{{ __('Pflicht') }}</span>
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
                                    <span class="badge badge-ghost badge-sm">{{ $room->name }}</span>
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
                                <form method="POST" action="{{ route('events.cancel', $event) }}" class="inline"
                                      data-confirm-dialog
                                      data-confirm-message="{{ __('Veranstaltung wirklich absagen?') }}"
                                      data-confirm-label="{{ __('Absagen') }}">
                                    @csrf @method('PATCH')
                                    <x-icon-btn type="submit" icon="cancel" tone="warning" :label="__('Absagen')" />
                                </form>
                            @endcan
                            @can('delete', $event)
                                <form method="POST" action="{{ route('events.destroy', $event) }}" class="inline"
                                      data-confirm-dialog
                                      data-confirm-message="{{ __('Veranstaltung wirklich löschen?') }}"
                                      data-confirm-label="{{ __('Löschen') }}">
                                    @csrf @method('DELETE')
                                    <x-icon-btn type="submit" icon="delete" tone="error" :label="__('Löschen')" />
                                </form>
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

        @if ($events->hasPages())
            <div class="flex-none">{{ $events->links() }}</div>
        @endif
    </x-page-shell>
@endsection
