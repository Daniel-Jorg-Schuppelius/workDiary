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
        <x-slot:toolbar>
            <x-page-toolbar :title="__('Tour') . ' ' . ($tour->name ?? ('#' . $tour->id))" :badge="$tour->status?->label() ?? ''" badge-tone="ghost">
                <div class="text-sm text-base-content/70">
                    {{ $tour->tour_date?->fdate() }} ·
                    {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $tour->planned_distance_km, 2, withThousandsSeparator: true) }} km ·
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
        </x-slot:toolbar>

        <div class="grid gap-4 lg:grid-cols-2">
            <form method="POST" action="{{ route('tours.update', $tour) }}"
                  class="space-y-4 rounded-box border border-base-300 bg-base-100 p-4">
                @csrf
                @method('PUT')
                <input type="hidden" name="user_id" value="{{ $tour->user_id }}">
                <input type="hidden" name="tour_date" value="{{ $tour->tour_date?->toDateString() }}">

                <div class="grid gap-3 md:grid-cols-2">
                    <x-select-field name="vehicle_id" :label="__('Fahrzeug')" class="select-sm">
                        <option value="">—</option>
                        @foreach ($vehicles as $v)
                            <option value="{{ $v->sqid }}" @selected((string) old('vehicle_id', \App\Support\Sqid::encode(\App\Models\Vehicle::class, $tour->vehicle_id)) === $v->sqid)>{{ $v->license_plate }} {{ $v->label }}</option>
                        @endforeach
                    </x-select-field>
                    <x-select-field name="status" :label="__('Status')" class="select-sm">
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected($tour->status?->value === $value)>{{ $label }}</option>
                        @endforeach
                    </x-select-field>
                    <x-input-field name="name" :label="__('Name')" maxlength="200" :value="$tour->name" class="input-sm" span="2" />
                </div>

                <fieldset class="grid gap-3 md:grid-cols-2 rounded-box border border-base-300 p-3">
                    <legend class="px-1 text-sm font-medium">{{ __('Start') }}</legend>
                    <x-input-field name="start_address" :label="__('Adresse')" :value="$tour->start_address" class="input-sm" span="2" />
                    <x-input-field name="start_lat" type="number" step="0.0000001" :label="__('Lat')" :value="$tour->start_lat" class="input-sm" />
                    <x-input-field name="start_lng" type="number" step="0.0000001" :label="__('Lng')" :value="$tour->start_lng" class="input-sm" />
                </fieldset>

                <fieldset class="grid gap-3 md:grid-cols-2 rounded-box border border-base-300 p-3">
                    <legend class="px-1 text-sm font-medium">{{ __('Ziel') }}</legend>
                    <x-input-field name="end_address" :label="__('Adresse')" :value="$tour->end_address" class="input-sm" span="2" />
                    <x-input-field name="end_lat" type="number" step="0.0000001" :label="__('Lat')" :value="$tour->end_lat" class="input-sm" />
                    <x-input-field name="end_lng" type="number" step="0.0000001" :label="__('Lng')" :value="$tour->end_lng" class="input-sm" />
                </fieldset>

                <fieldset class="rounded-box border border-base-300 p-3">
                    <legend class="px-1 text-sm font-medium">{{ __('Stopps') }}</legend>
                    <p class="mb-2 text-xs text-base-content/60">{{ __('Per Drag & Drop sortieren. „Optimieren" sortiert automatisch.') }}</p>
                    <ol class="space-y-1" data-stop-list>
                        @foreach ($stops as $s)
                            <li class="flex items-center gap-2 rounded-box border border-base-200 p-2" draggable="true" data-stop-item>
                                <span class="material-symbols-outlined cursor-grab text-base-content/40 select-none" aria-hidden="true" data-stop-handle>drag_indicator</span>
                                <input type="hidden" name="order_ids[]" value="{{ $s->id }}">
                                <x-status-badge tone="primary" size="sm" data-stop-pos>{{ $s->tour_position ?? '?' }}</x-status-badge>
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
                                            $svcLabel = $svc ? \App\Support\Formats::duration((int) $svc, 'clock') : __('keine Dauer');
                                        @endphp
                                        <li class="flex items-center gap-2 rounded-box border border-base-200 bg-base-200/30 p-2">
                                            <input type="checkbox" name="order_ids[]" value="{{ $a->id }}" class="checkbox checkbox-sm">
                                            <div class="flex-1">
                                                <div class="flex flex-wrap items-center gap-2 text-sm">
                                                    <span>{{ $a->title ?: \Illuminate\Support\Str::limit((string) $a->content, 50) }}</span>
                                                    <x-status-badge size="xs" outline>{{ $a->modeLabel() }}</x-status-badge>
                                                    @if ($a->location_mode === \App\Enums\Diary\LocationMode::Remote)
                                                        <x-status-badge tone="ghost" size="xs">{{ __('Remote') }}</x-status-badge>
                                                    @endif
                                                </div>
                                                <div class="text-xs text-base-content/60">
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
                </div>
            </div>
        </div>
    </x-page-shell>
@endsection

@push('scripts')
    <script @cspNonce>
        (function () {
            const list = document.querySelector('[data-stop-list]');
            if (!list) {
                return;
            }
            let dragged = null;

            const renumber = () => {
                list.querySelectorAll('[data-stop-item]').forEach((li, i) => {
                    const badge = li.querySelector('[data-stop-pos]');
                    if (badge) {
                        badge.textContent = String(i + 1);
                    }
                });
            };

            list.querySelectorAll('[data-stop-item]').forEach((li) => {
                li.addEventListener('dragstart', (e) => {
                    dragged = li;
                    li.classList.add('opacity-50');
                    e.dataTransfer.effectAllowed = 'move';
                });
                li.addEventListener('dragend', () => {
                    if (dragged) {
                        dragged.classList.remove('opacity-50');
                    }
                    dragged = null;
                    renumber();
                });
                li.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    if (!dragged || dragged === li) {
                        return;
                    }
                    const rect = li.getBoundingClientRect();
                    const after = (e.clientY - rect.top) / rect.height > 0.5;
                    list.insertBefore(dragged, after ? li.nextSibling : li);
                });
            });
        })();
    </script>
@endpush
