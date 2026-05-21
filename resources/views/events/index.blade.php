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
    <x-page-shell gap="6">
        <div class="grid grid-cols-1 gap-3 flex-none sm:grid-cols-2 lg:grid-cols-4">
            <x-kpi-tile :label="__('Anstehend')"         :value="$counts['upcoming']  ?? 0" tone="primary" />
            <x-kpi-tile :label="__('Heute')"             :value="$counts['today']     ?? 0" tone="info" />
            <x-kpi-tile :label="__('Pflichtschulungen')" :value="$counts['mandatory'] ?? 0" tone="warning" />
            <x-kpi-tile :label="__('Gesamt')"            :value="$counts['total']     ?? 0" tone="neutral" />
        </div>

        <x-filter-bar :action="route('events.index')" :reset="route('events.index')">
            <x-filter-field name="q" :label="__('Suche')" type="search" :value="request('q')" placeholder="{{ __('Titel, Thema …') }}" />
            <x-filter-field name="event_type"  :label="__('Typ')"          type="select" :value="request('event_type')"  :options="EventType::options()"       placeholder="{{ __('Alle') }}" onchange="this.form.submit()" />
            <x-filter-field name="status"      :label="__('Status')"       type="select" :value="request('status')"      :options="EventStatus::options()"     placeholder="{{ __('Alle') }}" onchange="this.form.submit()" />
            <x-filter-field name="visibility"  :label="__('Sichtbarkeit')" type="select" :value="request('visibility')"  :options="EventVisibility::options()" placeholder="{{ __('Alle') }}" onchange="this.form.submit()" />
            <x-filter-field name="category_id" :label="__('Kategorie')"    type="select" :value="request('category_id')" :options="$categories->pluck('name', 'id')->all()" placeholder="{{ __('Alle') }}" onchange="this.form.submit()" />
            <x-filter-field name="from" :label="__('Von')" type="date" :value="request('from')" />
            <x-filter-field name="to"   :label="__('Bis')" type="date" :value="request('to')" />
            <x-filter-field name="only_mandatory" :label="__('Nur Pflicht')" type="checkbox" :value="request('only_mandatory')" onchange="this.form.submit()" />

            <x-slot:extra>
                <x-icon-btn icon="calendar_month" tone="ghost" size="sm" :href="route('events.calendar')" show-label>
                    {{ __('Kalender') }}
                </x-icon-btn>
                @can('create', App\Models\Event::class)
                    <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger :href="route('events.create').'?dialog=1'" show-label>
                        {{ __('Neue Veranstaltung') }}
                    </x-icon-btn>
                @endcan
            </x-slot:extra>
        </x-filter-bar>

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
                            @if ($event->is_mandatory)
                                <span class="badge badge-warning badge-sm ml-1">{{ __('Pflicht') }}</span>
                            @endif
                            @if ($event->topic)
                                <div class="text-xs opacity-70">{{ $event->topic }}</div>
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
