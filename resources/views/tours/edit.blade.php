@extends('layouts.app')

@section('title', __('Tour bearbeiten'))

@section('content')
    @php
        $stops = $tour->diaryEntries;
        $markers = [];
        if ($tour->start_lat && $tour->start_lng) {
            $markers[] = ['lat' => (float) $tour->start_lat, 'lng' => (float) $tour->start_lng, 'label' => __('Start')];
        }
        foreach ($stops as $i => $s) {
            if ($s->address_lat && $s->address_lng) {
                $markers[] = ['lat' => (float) $s->address_lat, 'lng' => (float) $s->address_lng, 'label' => ($i + 1) . '. ' . $s->title];
            }
        }
        if ($tour->end_lat && $tour->end_lng) {
            $markers[] = ['lat' => (float) $tour->end_lat, 'lng' => (float) $tour->end_lng, 'label' => __('Ziel')];
        }
        $center = $markers !== [] ? ['lat' => $markers[0]['lat'], 'lng' => $markers[0]['lng']] : null;
        $geometry = $tour->geometryArray();
    @endphp

    <x-page-shell>
        <x-page-toolbar :title="__('Tour') . ' ' . ($tour->name ?? ('#' . $tour->id))" :badge="__($tour->status)" badge-tone="ghost">
            <div class="text-sm text-base-content/70">
                {{ $tour->tour_date?->format('d.m.Y') }} ·
                {{ number_format((float) $tour->planned_distance_km, 2, ',', '.') }} km ·
                {{ $tour->planned_duration_minutes }} min
            </div>
            <x-slot:actions>
                <x-icon-btn icon="visibility" size="sm" :href="route('tours.show', $tour)" show-label>{{ __('Ansicht') }}</x-icon-btn>
                <form method="POST" action="{{ route('tours.optimize', $tour) }}" class="inline">
                    @csrf
                    <x-icon-btn icon="auto_awesome" size="sm" type="submit" show-label>{{ __('Optimieren') }}</x-icon-btn>
                </form>
            </x-slot:actions>
        </x-page-toolbar>

        <div class="grid gap-4 lg:grid-cols-2">
            <form method="POST" action="{{ route('tours.update', $tour) }}"
                  class="space-y-4 rounded-box border border-base-300 bg-base-100 p-4">
                @csrf
                @method('PUT')
                <input type="hidden" name="user_id" value="{{ $tour->user_id }}">
                <input type="hidden" name="tour_date" value="{{ $tour->tour_date?->toDateString() }}">

                <div class="grid gap-3 md:grid-cols-2">
                    <label class="form-control">
                        <span class="label-text">{{ __('Fahrzeug') }}</span>
                        <select name="vehicle_id" class="select select-bordered select-sm">
                            <option value="">—</option>
                            @foreach ($vehicles as $v)
                                <option value="{{ $v->id }}" @selected((int) $tour->vehicle_id === (int) $v->id)>{{ $v->license_plate }} {{ $v->label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="form-control">
                        <span class="label-text">{{ __('Status') }}</span>
                        <select name="status" class="select select-bordered select-sm">
                            @foreach ($statuses as $s)
                                <option value="{{ $s }}" @selected($tour->status === $s)>{{ __($s) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="form-control md:col-span-2">
                        <span class="label-text">{{ __('Name') }}</span>
                        <input type="text" name="name" maxlength="200" value="{{ $tour->name }}" class="input input-bordered input-sm">
                    </label>
                </div>

                <fieldset class="grid gap-3 md:grid-cols-2 rounded-box border border-base-300 p-3">
                    <legend class="px-1 text-sm font-medium">{{ __('Start') }}</legend>
                    <label class="form-control md:col-span-2">
                        <span class="label-text">{{ __('Adresse') }}</span>
                        <input type="text" name="start_address" value="{{ $tour->start_address }}" class="input input-bordered input-sm">
                    </label>
                    <label class="form-control">
                        <span class="label-text">{{ __('Lat') }}</span>
                        <input type="number" step="0.0000001" name="start_lat" value="{{ $tour->start_lat }}" class="input input-bordered input-sm">
                    </label>
                    <label class="form-control">
                        <span class="label-text">{{ __('Lng') }}</span>
                        <input type="number" step="0.0000001" name="start_lng" value="{{ $tour->start_lng }}" class="input input-bordered input-sm">
                    </label>
                </fieldset>

                <fieldset class="grid gap-3 md:grid-cols-2 rounded-box border border-base-300 p-3">
                    <legend class="px-1 text-sm font-medium">{{ __('Ziel') }}</legend>
                    <label class="form-control md:col-span-2">
                        <span class="label-text">{{ __('Adresse') }}</span>
                        <input type="text" name="end_address" value="{{ $tour->end_address }}" class="input input-bordered input-sm">
                    </label>
                    <label class="form-control">
                        <span class="label-text">{{ __('Lat') }}</span>
                        <input type="number" step="0.0000001" name="end_lat" value="{{ $tour->end_lat }}" class="input input-bordered input-sm">
                    </label>
                    <label class="form-control">
                        <span class="label-text">{{ __('Lng') }}</span>
                        <input type="number" step="0.0000001" name="end_lng" value="{{ $tour->end_lng }}" class="input input-bordered input-sm">
                    </label>
                </fieldset>

                <fieldset class="rounded-box border border-base-300 p-3">
                    <legend class="px-1 text-sm font-medium">{{ __('Stopps') }}</legend>
                    <p class="mb-2 text-xs text-base-content/60">{{ __('Reihenfolge per Nummer festlegen. „Optimieren" sortiert automatisch.') }}</p>
                    <ol class="space-y-1" data-stop-list>
                        @foreach ($stops as $s)
                            <li class="flex items-center gap-2 rounded-box border border-base-200 p-2">
                                <input type="number" min="1" name="order_ids[]" value="{{ $s->id }}"
                                       data-position="{{ $s->tour_position }}"
                                       class="hidden">
                                <span class="badge badge-primary badge-sm">{{ $s->tour_position ?? '?' }}</span>
                                <div class="flex-1">
                                    <div class="font-medium text-sm">{{ $s->title }}</div>
                                    <div class="text-xs text-base-content/60">{{ $s->customer?->name }} · {{ $s->address_city }}</div>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                    @if ($stops->isEmpty())
                        <p class="text-sm text-base-content/60">{{ __('Noch keine Stopps zugewiesen.') }}</p>
                    @endif
                </fieldset>

                <div class="flex justify-end gap-2">
                    <x-icon-btn icon="close" size="sm" :href="route('tours.index')" show-label>{{ __('Schließen') }}</x-icon-btn>
                    <x-icon-btn icon="save" tone="primary" size="sm" type="submit" show-label>{{ __('Speichern') }}</x-icon-btn>
                </div>
            </form>

            <div class="space-y-4">
                @if ($markers !== [])
                    <x-map :center="$center" :markers="$markers" :route="$geometry" :zoom="11" height="380px" />
                @endif

                <div class="rounded-box border border-base-300 bg-base-100 p-4">
                    <h2 class="mb-2 text-sm font-medium">{{ __('Verfügbare Aufträge') }}</h2>
                    @if ($available->isEmpty() && $flexBacklog->isEmpty())
                        <p class="text-sm text-base-content/60">{{ __('Keine offenen Aufträge für dieses Datum.') }}</p>
                    @else
                        <form method="POST" action="{{ route('tours.update', $tour) }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="user_id" value="{{ $tour->user_id }}">
                            <input type="hidden" name="tour_date" value="{{ $tour->tour_date?->toDateString() }}">
                            @foreach ($stops as $s)
                                <input type="hidden" name="order_ids[]" value="{{ $s->id }}">
                            @endforeach

                            @if ($available->isNotEmpty())
                                <div class="mb-2 text-xs uppercase tracking-wide text-base-content/50">{{ __('Terminiert für diesen Tag') }}</div>
                                <ul class="space-y-1">
                                    @foreach ($available as $a)
                                        <li class="flex items-center gap-2 rounded-box border border-base-200 p-2">
                                            <input type="checkbox" name="order_ids[]" value="{{ $a->id }}" class="checkbox checkbox-sm">
                                            <div class="flex-1">
                                                <div class="text-sm">{{ $a->title }}</div>
                                                <div class="text-xs text-base-content/60">{{ $a->address_city }}</div>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            @if ($flexBacklog->isNotEmpty())
                                <div class="mt-4 mb-2 text-xs uppercase tracking-wide text-base-content/50">
                                    {{ __('Flex-Backlog (Lückenfüller-Vorschläge)') }}
                                </div>
                                <ul class="space-y-1">
                                    @foreach ($flexBacklog as $a)
                                        @php
                                            $svc = $a->service_minutes;
                                            $svcLabel = $svc ? intdiv($svc, 60).':'.str_pad((string) ($svc % 60), 2, '0', STR_PAD_LEFT).' h' : __('keine Dauer');
                                        @endphp
                                        <li class="flex items-center gap-2 rounded-box border border-base-200 bg-base-200/30 p-2">
                                            <input type="checkbox" name="order_ids[]" value="{{ $a->id }}" class="checkbox checkbox-sm">
                                            <div class="flex-1">
                                                <div class="flex flex-wrap items-center gap-2 text-sm">
                                                    <span>{{ $a->title ?: \Illuminate\Support\Str::limit((string) $a->content, 50) }}</span>
                                                    <span class="badge badge-xs badge-outline">{{ $a->modeLabel() }}</span>
                                                    @if ($a->location_mode === \App\Models\DiaryEntry::LOCATION_REMOTE)
                                                        <span class="badge badge-xs badge-ghost">{{ __('Remote') }}</span>
                                                    @endif
                                                </div>
                                                <div class="text-xs text-base-content/60">
                                                    {{ $svcLabel }}
                                                    @if ($a->mode === \App\Models\DiaryEntry::MODE_DEADLINE && $a->due_date)
                                                        · {{ __('fällig bis') }} {{ $a->due_date->format('d.m.Y') }}
                                                    @elseif ($a->mode === \App\Models\DiaryEntry::MODE_WINDOW && $a->window_end_date)
                                                        · {{ __('Fenster bis') }} {{ $a->window_end_date->format('d.m.Y') }}
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
                </div>
            </div>
        </div>
    </x-page-shell>
@endsection
