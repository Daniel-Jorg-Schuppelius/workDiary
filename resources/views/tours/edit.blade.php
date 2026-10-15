{{--
  Created on   : Sun May 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : edit.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Tour bearbeiten'))
@section('nav-title', __('Tour bearbeiten'))

@section('content')
    @php
        $stops = $tour->diaryEntries;
        $markers = [];
        if ($tour->start_lat && $tour->start_lng) {
            $markers[] = ['lat' => (float) $tour->start_lat, 'lng' => (float) $tour->start_lng, 'label' => __('Start')];
        }
        foreach ($stops as $i => $s) {
            $point = $s->planningCoordinates();
            if ($point !== null) {
                $markers[] = $point + ['label' => ($i + 1) . '. ' . $s->title];
            }
        }
        if ($tour->end_lat && $tour->end_lng) {
            $markers[] = ['lat' => (float) $tour->end_lat, 'lng' => (float) $tour->end_lng, 'label' => __('Ziel')];
        }
        $center = $markers !== [] ? ['lat' => $markers[0]['lat'], 'lng' => $markers[0]['lng']] : null;
        $geometry = $tour->geometryArray();
    @endphp

    <x-page-shell>
        <x-slot:toolbar>
            <x-page-toolbar :title="__('Tour') . ' ' . ($tour->name ?? ('#' . $tour->id))" :badge="$tour->status?->label() ?? ''" badge-tone="ghost">
                <div class="text-sm text-base-content/70">
                    {{ $tour->tour_date?->fdate() }} ·
                    {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $tour->planned_distance_km, 2, withThousandsSeparator: true) }} km ·
                    {{ $tour->planned_duration_minutes }} min
                </div>
                <x-slot:actions>
                    <x-icon-btn icon="visibility" size="sm" :href="route('tours.show', $tour)" show-label>{{ __('Ansicht') }}</x-icon-btn>
                    <x-action-form :action="route('tours.proposals.store', $tour)">
                        <x-icon-btn icon="auto_awesome" size="sm" type="submit" show-label>{{ __('Vorschlag berechnen') }}</x-icon-btn>
                    </x-action-form>
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>

        <div class="grid gap-4 lg:grid-cols-2">
            <x-card as="form" class="space-y-4" method="POST" action="{{ route('tours.update', $tour) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="user_id" value="{{ \App\Support\Sqid::encode(\App\Models\Platform\User::class, (int) $tour->user_id) }}">
                <input type="hidden" name="tour_date" value="{{ $tour->tour_date?->toDateString() }}">

                <div class="grid gap-3 md:grid-cols-2">
                    <x-select-field name="vehicle_id" :label="__('Fahrzeug')" class="select-sm">
                        <option value="">—</option>
                        @foreach ($vehicles as $v)
                            <option value="{{ $v->sqid }}" @selected((string) old('vehicle_id', \App\Support\Sqid::encode(\App\Models\Fleet\Vehicle::class, $tour->vehicle_id)) === $v->sqid)>{{ $v->license_plate }} {{ $v->label }}</option>
                        @endforeach
                    </x-select-field>
                    <x-input-field name="name" :label="__('Name')" maxlength="200" :value="$tour->name" class="input-sm" />
                    <x-input-field name="planned_start_time" type="time" :label="__('Startzeit')" :value="old('planned_start_time', $tour->planned_start_at ? \App\Support\Tz::toLocal($tour->planned_start_at)?->format('H:i') : '')" class="input-sm" />
                    <x-select-field name="start_site_id" :label="__('Start ab Betriebsstätte')" class="select-sm">
                        <option value="">{{ __('— Adresse unten —') }}</option>
                        @foreach ($sites as $site)
                            <option value="{{ $site->sqid }}" @selected((string) old('start_site_id', \App\Support\Sqid::encode(\App\Models\Facility\Site::class, $tour->start_site_id)) === $site->sqid)>{{ $site->name }}</option>
                        @endforeach
                    </x-select-field>
                </div>

                {{-- Koordinaten aus der Adresse (Geokodierung in app.js, MVP-1170). --}}
                @foreach (['start' => __('Start'), 'end' => __('Ziel')] as $anchor => $anchorLabel)
                    <fieldset class="grid gap-3 rounded-box border border-base-300 p-3">
                        <legend class="px-1 text-sm font-medium">{{ $anchorLabel }}</legend>
                        <x-input-field :name="$anchor . '_address'" :label="__('Adresse')" :value="$tour->{$anchor . '_address'}" class="input-sm" :data-geocode-part="$anchor" />
                        <input type="hidden" name="{{ $anchor }}_lat" value="{{ $tour->{$anchor . '_lat'} }}" data-geocode-lat="{{ $anchor }}">
                        <input type="hidden" name="{{ $anchor }}_lng" value="{{ $tour->{$anchor . '_lng'} }}" data-geocode-lng="{{ $anchor }}">
                        <p class="text-xs text-muted" data-geocode-status="{{ $anchor }}"
                           data-hit="{{ __('Koordinaten aus der Adresse ermittelt.') }}"
                           data-miss="{{ __('Adresse nicht gefunden.') }}">
                            {{ $tour->{$anchor . '_lat'} !== null ? __('Koordinaten aus der Adresse ermittelt.') : __('Koordinaten werden beim Verlassen des Adressfelds ermittelt.') }}
                        </p>
                    </fieldset>
                @endforeach

                <fieldset class="rounded-box border border-base-300 p-3">
                    <legend class="px-1 text-sm font-medium">{{ __('Stopps') }}</legend>
                    <p class="mb-2 text-xs text-muted">{{ __('Per Drag & Drop sortieren. „Vorschlag berechnen“ liefert Reihenfolge und Zeiten zur Übernahme.') }}</p>
                    {{-- Sortierlogik: resources/js/tour-stops.js --}}
                    <ol class="space-y-1" data-stop-list>
                        @foreach ($stops as $s)
                            <li class="flex items-center gap-2 rounded-box border border-base-200 p-2" data-stop-item>
                                <x-icon name="drag_indicator" class="cursor-grab touch-none text-muted select-none" data-stop-handle />
                                <input type="hidden" name="order_ids[]" value="{{ $s->sqid }}">
                                <x-status-badge tone="primary" size="sm" data-stop-pos>{{ $s->tour_position ?? '?' }}</x-status-badge>
                                <div class="flex-1">
                                    <div class="flex flex-wrap items-center gap-2 text-sm font-medium">
                                        <span>{{ $s->title }}</span>
                                        @if ($s->planningCoordinates() === null)
                                            <x-status-badge tone="warning" size="xs">{{ __('ohne Koordinaten') }}</x-status-badge>
                                        @endif
                                    </div>
                                    <div class="text-xs text-muted">{{ $s->customer?->name }} · {{ $s->address_city }}</div>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                    @if ($stops->isEmpty())
                        <x-empty-state icon="route" :title="__('Noch keine Stopps zugewiesen.')" compact />
                    @endif
                </fieldset>

                <div class="flex justify-end gap-2">
                    <x-icon-btn icon="close" size="sm" :href="route('tours.index')" show-label>{{ __('Schließen') }}</x-icon-btn>
                    <x-icon-btn icon="save" tone="primary" size="sm" type="submit" show-label>{{ __('Speichern') }}</x-icon-btn>
                </div>
            </x-card>

            <div class="space-y-4">
                @if ($markers !== [])
                    <x-map :center="$center" :markers="$markers" :route="$geometry" :zoom="11" height="380px" />
                @endif

                <x-card>
                    <h2 class="mb-2 text-sm font-medium">{{ __('Verfügbare Aufträge') }}</h2>
                    @if ($available->isEmpty() && $flexBacklog->isEmpty())
                        <x-empty-state icon="assignment" :title="__('Keine offenen Aufträge für dieses Datum.')" compact />
                    @else
                        <form method="POST" action="{{ route('tours.update', $tour) }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="user_id" value="{{ \App\Support\Sqid::encode(\App\Models\Platform\User::class, (int) $tour->user_id) }}">
                            <input type="hidden" name="tour_date" value="{{ $tour->tour_date?->toDateString() }}">
                            @foreach ($stops as $s)
                                <input type="hidden" name="order_ids[]" value="{{ $s->sqid }}">
                            @endforeach

                            @if ($available->isNotEmpty())
                                <div class="mb-2 text-xs uppercase tracking-wide text-muted">{{ __('Terminiert für diesen Tag') }}</div>
                                <ul class="space-y-1">
                                    @foreach ($available as $a)
                                        <li class="flex items-center gap-2 rounded-box border border-base-200 p-2">
                                            <input type="checkbox" name="order_ids[]" value="{{ $a->sqid }}" class="checkbox checkbox-sm">
                                            <div class="flex-1">
                                                <div class="flex flex-wrap items-center gap-2 text-sm">
                                                    <span>{{ $a->title }}</span>
                                                    @if ($a->planningCoordinates() === null)
                                                        <x-status-badge tone="warning" size="xs">{{ __('ohne Koordinaten') }}</x-status-badge>
                                                    @endif
                                                </div>
                                                <div class="text-xs text-muted">{{ $a->address_city }}</div>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            @if ($flexBacklog->isNotEmpty())
                                <div class="mt-4 mb-2 text-xs uppercase tracking-wide text-muted">
                                    {{ __('Flex-Backlog (Lückenfüller-Vorschläge)') }}
                                </div>
                                <ul class="space-y-1">
                                    @foreach ($flexBacklog as $a)
                                        @php
                                            $svc = $a->service_minutes;
                                            $svcLabel = $svc ? \App\Support\Formats::duration((int) $svc, 'clock') : __('keine Dauer');
                                        @endphp
                                        <li class="flex items-center gap-2 rounded-box border border-base-200 bg-base-200/30 p-2">
                                            <input type="checkbox" name="order_ids[]" value="{{ $a->sqid }}" class="checkbox checkbox-sm">
                                            <div class="flex-1">
                                                <div class="flex flex-wrap items-center gap-2 text-sm">
                                                    <span>{{ $a->title ?: \Illuminate\Support\Str::limit((string) $a->content, 50) }}</span>
                                                    <x-status-badge size="xs" outline>{{ $a->modeLabel() }}</x-status-badge>
                                                    @if ($a->location_mode === \App\Enums\Diary\LocationMode::Remote)
                                                        <x-status-badge tone="ghost" size="xs">{{ __('Remote') }}</x-status-badge>
                                                    @endif
                                                    @if ($a->planningCoordinates() === null)
                                                        <x-status-badge tone="warning" size="xs">{{ __('ohne Koordinaten') }}</x-status-badge>
                                                    @endif
                                                </div>
                                                <div class="text-xs text-muted">
                                                    {{ $svcLabel }}
                                                    @if ($a->mode === \App\Enums\Diary\Mode::Deadline && $a->due_date)
                                                        · {{ __('fällig bis') }} {{ $a->due_date->fdate() }}
                                                    @elseif ($a->mode === \App\Enums\Diary\Mode::Window && $a->window_end_date)
                                                        · {{ __('Fenster bis') }} {{ $a->window_end_date->fdate() }}
                                                    @endif
                                                </div>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            <div class="mt-3 flex justify-end">
                                <x-icon-btn icon="check" type="submit" show-label>{{ __('Auswahl übernehmen') }}</x-icon-btn>
                            </div>
                        </form>
                    @endif
                </x-card>
            </div>
        </div>
    </x-page-shell>
@endsection
