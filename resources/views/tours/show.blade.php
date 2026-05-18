@extends('layouts.app')

@section('title', __('Tour') . ' ' . ($tour->name ?? '#' . $tour->id))

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
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">{{ __('Tour') }} {{ $tour->name ?? ('#' . $tour->id) }}</h1>
                <p class="text-sm text-base-content/60">
                    {{ $tour->tour_date?->format('d.m.Y') }} · {{ $tour->user?->name }} · {{ $tour->vehicle?->license_plate ?? '—' }}
                </p>
                <p class="text-sm">
                    <span class="badge badge-ghost badge-sm">{{ __($tour->status) }}</span>
                    {{ number_format((float) $tour->planned_distance_km, 2, ',', '.') }} km ·
                    {{ $tour->planned_duration_minutes }} min
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('tours.edit', $tour) }}" class="btn btn-sm">{{ __('Bearbeiten') }}</a>
                @if (in_array($tour->status, ['draft', 'planned'], true))
                    <form method="POST" action="{{ route('tours.start', $tour) }}">
                        @csrf
                        <button class="btn btn-sm btn-primary">{{ __('Starten') }}</button>
                    </form>
                @endif
                @if (in_array($tour->status, ['planned', 'in_progress'], true))
                    <form method="POST" action="{{ route('tours.complete', $tour) }}">
                        @csrf
                        <button class="btn btn-sm btn-success">{{ __('Abschließen') }}</button>
                    </form>
                @endif
                @if (in_array($tour->status, ['in_progress', 'completed'], true))
                    <form method="POST" action="{{ route('tours.materialize', $tour) }}"
                          onsubmit="return confirm('{{ __('Stopps als Fahrten ins Fahrtenbuch übernehmen?') }}');">
                        @csrf
                        <button class="btn btn-sm btn-ghost">{{ __('Als Fahrten übernehmen') }}</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-box border border-base-300 bg-base-100">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('Auftrag') }}</th>
                            <th>{{ __('Kunde') }}</th>
                            <th>{{ __('Ort') }}</th>
                            <th>{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($stops as $s)
                            <tr>
                                <td>{{ $s->tour_position }}</td>
                                <td><a href="{{ route('diary.show', $s) }}" class="link">{{ $s->title }}</a></td>
                                <td>{{ $s->customer?->name }}</td>
                                <td>{{ $s->address_city }}</td>
                                <td><span class="badge badge-ghost badge-xs">{{ $s->statusLabel() }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-4 text-center text-base-content/60">{{ __('Keine Stopps zugewiesen.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
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
