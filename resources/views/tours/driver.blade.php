{{--
  Created on   : Thu Oct 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : driver.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Fahreransicht (MVP-1183): fürs Handy, offline über die Sync-Outbox (tour.stop-arrive / tour.stop-depart). --}}
@extends('layouts.app')

@section('title', __('Fahreransicht') . ' · ' . ($tour->name ?? ('#' . $tour->id)))
@section('nav-title', __('Fahreransicht'))

@section('content')
    @php
        $done = $tour->stops->filter(fn($s) => $s->departed_at !== null)->count();
        $running = $tour->status === \App\Enums\Tour\TourStatus::InProgress;
        $mapLink = static function (?array $point): ?string {
            if ($point === null) {
                return null;
            }
            return 'https://www.openstreetmap.org/?mlat=' . rawurlencode((string) $point['lat']) . '&mlon=' . rawurlencode((string) $point['lng']) . '#map=17/' . rawurlencode((string) $point['lat']) . '/' . rawurlencode((string) $point['lng']);
        };
    @endphp
    <x-page-shell gap="3">
        <x-slot:toolbar>
            <x-page-toolbar :title="$tour->name ?? ('#' . $tour->id)" :badge="$tour->status->label()" badge-tone="ghost"
                            :subtitle="__(':done von :total Stopps erledigt', ['done' => $done, 'total' => $tour->stops->count()])">
                <x-slot:actions>
                    <x-icon-btn icon="list" size="sm" :href="route('tours.show', $tour)" :label="__('Tour')" />
                    @if (in_array($tour->status, [\App\Enums\Tour\TourStatus::Draft, \App\Enums\Tour\TourStatus::Planned], true))
                        <x-action-form :action="route('tours.start', $tour)">
                            <x-icon-btn icon="play_arrow" tone="primary" size="sm" type="submit" show-label>{{ __('Tour starten') }}</x-icon-btn>
                        </x-action-form>
                    @elseif ($running)
                        <x-action-form :action="route('tours.complete', $tour)" :confirm="__('Tour beenden? Nicht erledigte Stopps bleiben offen.')" :confirm-label="__('Beenden')">
                            <x-icon-btn icon="check_circle" tone="success" size="sm" type="submit" show-label>{{ __('Tour beenden') }}</x-icon-btn>
                        </x-action-form>
                    @endif
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>

        @forelse ($tour->stops as $stop)
            @php
                $entry = $stop->diaryEntry;
                $point = $entry?->planningCoordinates();
                $isNext = $next !== null && $next->id === $stop->id;
                $isDone = $stop->departed_at !== null;
            @endphp
            <x-card class="{{ $isNext ? 'ring-2 ring-primary' : ($isDone ? 'opacity-70' : '') }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-status-badge :tone="$isDone ? 'success' : ($isNext ? 'primary' : 'ghost')" size="sm">{{ $stop->position }}</x-status-badge>
                            <span class="truncate font-medium">{{ $entry?->title ?? '—' }}</span>
                            @if ($stop->outcome === \App\Models\Diary\TourStop::OUTCOME_NOT_MET)
                                <x-status-badge tone="warning" size="xs">{{ __('nicht angetroffen') }}</x-status-badge>
                            @endif
                        </div>
                        <div class="truncate text-sm text-muted">{{ $entry?->customer?->name }} · {{ trim((string) ($entry?->address_line ?? '') . ' ' . ($entry?->address_city ?? '')) ?: '—' }}</div>
                        <div class="mt-1 text-xs text-muted">
                            {{ __('Plan') }}: {{ $stop->planned_arrival_at ? \App\Support\Tz::toLocal($stop->planned_arrival_at)?->format('H:i') : '—' }}–{{ $stop->planned_departure_at ? \App\Support\Tz::toLocal($stop->planned_departure_at)?->format('H:i') : '—' }}
                            · {{ __('Ist') }}: {{ $stop->arrived_at ? \App\Support\Tz::toLocal($stop->arrived_at)?->format('H:i') : '—' }}–{{ $stop->departed_at ? \App\Support\Tz::toLocal($stop->departed_at)?->format('H:i') : '—' }}
                        </div>
                    </div>
                    @if ($mapLink($point) !== null)
                        <x-icon-btn icon="navigation" tone="ghost" size="sm" :href="$mapLink($point)" target="_blank" rel="noopener" :label="__('Navigation starten')" />
                    @endif
                </div>
                @if ($running && ! $isDone)
                    <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-3">
                        @if ($stop->arrived_at === null)
                            <form method="POST" action="{{ route('tours.stops.arrive', [$tour, $stop]) }}" data-offline-sync="tour.stop-arrive" data-sync-payload-stop="{{ $stop->sqid }}">
                                @csrf
                                <x-button type="submit" tone="primary" class="w-full">{{ __('Angekommen') }}</x-button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('tours.stops.depart', [$tour, $stop]) }}" data-offline-sync="tour.stop-depart" data-sync-payload-stop="{{ $stop->sqid }}">
                            @csrf
                            <input type="hidden" name="outcome" value="{{ \App\Models\Diary\TourStop::OUTCOME_DONE }}">
                            <x-button type="submit" tone="success" class="w-full">{{ __('Erledigt') }}</x-button>
                        </form>
                        <form method="POST" action="{{ route('tours.stops.depart', [$tour, $stop]) }}" data-offline-sync="tour.stop-depart" data-sync-payload-stop="{{ $stop->sqid }}">
                            @csrf
                            <input type="hidden" name="outcome" value="{{ \App\Models\Diary\TourStop::OUTCOME_NOT_MET }}">
                            <x-button type="submit" tone="ghost" class="w-full">{{ __('Nicht angetroffen') }}</x-button>
                        </form>
                    </div>
                @endif
            </x-card>
        @empty
            <x-card>
                <x-empty-state icon="route" :title="__('Keine Stopps zugewiesen.')" compact />
            </x-card>
        @endforelse
    </x-page-shell>
@endsection
