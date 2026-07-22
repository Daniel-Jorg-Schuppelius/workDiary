@extends('layouts.app')

@section('title', __('Tour') . ' ' . ($tour->name ?? '#' . $tour->id))
@section('nav-title', __('Tour') . ' ' . ($tour->name ?? '#' . $tour->id))

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
            <x-page-toolbar :badge="$tour->status?->label() ?? ''" badge-tone="ghost">
                <div class="text-sm text-base-content/70">
                    {{ $tour->tour_date?->fdate() }} · {{ $tour->user?->name }} · {{ $tour->vehicle?->license_plate ?? '—' }}
                    · {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $tour->planned_distance_km, 2, withThousandsSeparator: true) }} km · {{ $tour->planned_duration_minutes }} min
                </div>
                <x-slot:actions>
                    <x-icon-btn icon="edit" size="sm" :href="route('tours.edit', $tour)" show-label>{{ __('Bearbeiten') }}</x-icon-btn>
                    @if (in_array($tour->status, [\App\Enums\Tour\TourStatus::Draft, \App\Enums\Tour\TourStatus::Planned], true))
                        <x-action-form :action="route('tours.start', $tour)">
                            <x-icon-btn icon="play_arrow" tone="primary" size="sm" type="submit" show-label>{{ __('Starten') }}</x-icon-btn>
                        </x-action-form>
                    @endif
                    @if (in_array($tour->status, [\App\Enums\Tour\TourStatus::Planned, \App\Enums\Tour\TourStatus::InProgress], true))
                        <x-action-form :action="route('tours.complete', $tour)">
                            <x-icon-btn icon="check_circle" tone="success" size="sm" type="submit" show-label>{{ __('Abschließen') }}</x-icon-btn>
                        </x-action-form>
                    @endif
                    @if (in_array($tour->status, [\App\Enums\Tour\TourStatus::InProgress, \App\Enums\Tour\TourStatus::Completed], true))
                        <x-action-form :action="route('tours.materialize', $tour)"
                              :confirm="__('Stopps als Fahrten ins Fahrtenbuch übernehmen?')"
                              :confirm-label="__('Übernehmen')">
                            <x-icon-btn icon="directions_car" size="sm" type="submit" show-label>{{ __('Als Fahrten übernehmen') }}</x-icon-btn>
                        </x-action-form>
                    @endif
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-box border border-base-300 bg-base-100">
                <x-table table-sort="client" bare>
                    <x-slot:head>
                        <tr>
                            <th>#</th>
                            <x-table.th sort>{{ __('Auftrag') }}</x-table.th>
                            <x-table.th sort>{{ __('Kunde') }}</x-table.th>
                            <x-table.th sort>{{ __('Ort') }}</x-table.th>
                            <x-table.th sort>{{ __('Status') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @forelse ($stops as $s)
                        <tr>
                            <td>{{ $s->tour_position }}</td>
                            <td><a href="{{ route('diary.show', $s) }}" class="link">{{ $s->title }}</a></td>
                            <td>{{ $s->customer?->name }}</td>
                            <td>{{ $s->address_city }}</td>
                            <td><x-status-badge tone="ghost" size="xs">{{ $s->statusLabel() }}</x-status-badge></td>
                        </tr>
                    @empty
                        <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">route</span>' :colspan="5" :title="__('Keine Stopps zugewiesen.')" compact />
                    @endforelse
                </x-table>
            </div>

            @if ($markers !== [])
                <x-map :center="$center" :markers="$markers" :route="$geometry" :zoom="11" height="420px" />
            @endif
        </div>

        @if ($tour->notes)
            <div class="rounded-box border border-base-300 bg-base-100 p-4">
                <h2 class="mb-2 text-sm font-medium">{{ __('Notizen') }}</h2>
                <p class="whitespace-pre-line text-sm">{{ $tour->notes }}</p>
            </div>
        @endif
    </x-page-shell>
@endsection
