{{--
  Created on   : Sun May 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Tour') . ' ' . ($tour->name ?? '#' . $tour->id))
@section('nav-title', __('Tour') . ' ' . ($tour->name ?? '#' . $tour->id))

@section('content')
    @php
        $stops = $tour->diaryEntries;
        $markers = $tour->mapMarkers();
        $center = $markers !== [] ? ['lat' => $markers[0]['lat'], 'lng' => $markers[0]['lng']] : null;
        $geometry = $tour->geometryArray();
    @endphp

    <x-page-shell>
        <x-slot:toolbar>
            <x-page-toolbar :badge="$tour->status?->label() ?? ''" badge-tone="ghost">
                <div class="text-sm text-base-content/70">
                    {{ $tour->tour_date?->fdate() }} · {{ $tour->user?->name }} · {{ $tour->vehicle?->license_plate ?? '—' }}
                    · {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $tour->planned_distance_km, 2, withThousandsSeparator: true) }} km · {{ $tour->planned_duration_minutes }} min
                    @if ($tour->planned_start_at)
                        · {{ __('Start') }} {{ \App\Support\Tz::toLocal($tour->planned_start_at)?->format('H:i') }}
                    @endif
                    @if ($tour->travel_logs_count > 0)
                        · {{ trans_choice(':count Fahrt im Fahrtenbuch|:count Fahrten im Fahrtenbuch', $tour->travel_logs_count, ['count' => $tour->travel_logs_count]) }}
                    @endif
                </div>
                <x-slot:actions>
                    <x-icon-btn icon="edit" size="sm" :href="route('tours.edit', $tour)" show-label>{{ __('Bearbeiten') }}</x-icon-btn>
                    <x-icon-btn icon="smartphone" size="sm" :href="route('tours.driver', $tour)" show-label>{{ __('Fahreransicht') }}</x-icon-btn>
                    @if ($tour->status === \App\Enums\Tour\TourStatus::InProgress)
                        <x-action-form :action="route('tours.proposals.store', $tour)">
                            <input type="hidden" name="remainder" value="1">
                            <x-icon-btn icon="auto_awesome" size="sm" type="submit" show-label>{{ __('Rest neu planen') }}</x-icon-btn>
                        </x-action-form>
                    @elseif (! in_array($tour->status, [\App\Enums\Tour\TourStatus::Completed, \App\Enums\Tour\TourStatus::Cancelled], true))
                        <x-action-form :action="route('tours.proposals.store', $tour)">
                            <x-icon-btn icon="auto_awesome" size="sm" type="submit" show-label>{{ __('Vorschlag berechnen') }}</x-icon-btn>
                        </x-action-form>
                    @endif
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
                    @if ($tour->travel_logs_count === 0 && in_array($tour->status, [\App\Enums\Tour\TourStatus::InProgress, \App\Enums\Tour\TourStatus::Completed], true))
                        <x-icon-btn icon="directions_car" size="sm" data-entry-modal-trigger :href="route('tours.materialize.form', $tour)" show-label>{{ __('Als Fahrten übernehmen') }}</x-icon-btn>
                    @endif
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>

        <div class="grid gap-4 lg:grid-cols-2">
            <x-card padding="p-0">
                <x-table table-sort="client" bare>
                    <x-slot:head>
                        <tr>
                            <th>#</th>
                            <x-table.th sort>{{ __('Auftrag') }}</x-table.th>
                            <x-table.th sort>{{ __('Kunde') }}</x-table.th>
                            <x-table.th sort>{{ __('Ort') }}</x-table.th>
                            <x-table.th sort>{{ __('Ankunft') }}</x-table.th>
                            <x-table.th sort>{{ __('Abfahrt') }}</x-table.th>
                            <x-table.th sort>{{ __('Ist') }}</x-table.th>
                            <x-table.th sort>{{ __('Status') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @forelse ($stops as $s)
                        @php($plannedStop = $stopRows->get($s->id))
                        <tr>
                            <td>{{ $s->tour_position }}</td>
                            <td><x-order-link :entry="$s" link-class="link">{{ $s->title }}</x-order-link></td>
                            <td>{{ $s->customer?->name }}</td>
                            <td>{{ $s->address_city }}</td>
                            <td>
                                {{ $plannedStop?->planned_arrival_at ? \App\Support\Tz::toLocal($plannedStop->planned_arrival_at)?->format('H:i') : '—' }}
                                @if (($plannedStop?->waiting_minutes ?? 0) > 0)
                                    <span class="text-xs text-warning">+{{ $plannedStop->waiting_minutes }} min</span>
                                @endif
                            </td>
                            <td>{{ $plannedStop?->planned_departure_at ? \App\Support\Tz::toLocal($plannedStop->planned_departure_at)?->format('H:i') : '—' }}</td>
                            <td class="text-xs">
                                @if ($plannedStop?->arrived_at)
                                    {{ \App\Support\Tz::toLocal($plannedStop->arrived_at)?->format('H:i') }}–{{ $plannedStop->departed_at ? \App\Support\Tz::toLocal($plannedStop->departed_at)?->format('H:i') : '…' }}
                                    @if ($plannedStop->planned_arrival_at)
                                        @php($delta = (int) round($plannedStop->planned_arrival_at->diffInMinutes($plannedStop->arrived_at, false)))
                                        <span class="{{ $delta > 0 ? 'text-warning' : 'text-success' }}">({{ $delta > 0 ? '+' : '' }}{{ $delta }} min)</span>
                                    @endif
                                    @if ($plannedStop->outcome === \App\Models\Diary\TourStop::OUTCOME_NOT_MET)
                                        <x-status-badge tone="warning" size="xs">{{ __('nicht angetroffen') }}</x-status-badge>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td><x-status-badge tone="ghost" size="xs">{{ $s->statusLabel() }}</x-status-badge></td>
                        </tr>
                    @empty
                        <x-table.empty icon="route" :colspan="8" :title="__('Keine Stopps zugewiesen.')" compact />
                    @endforelse
                </x-table>
            </x-card>

            @if ($markers !== [])
                <x-map :center="$center" :markers="$markers" :route="$geometry" :zoom="11" height="420px" />
            @endif
        </div>

        @if ($tour->notes)
            <x-card>
                <h2 class="mb-2 text-sm font-medium">{{ __('Notizen') }}</h2>
                <p class="whitespace-pre-line text-sm">{{ $tour->notes }}</p>
            </x-card>
        @endif

        <div class="grid gap-4 lg:grid-cols-2">
            <x-card>
                <h2 class="mb-2 text-sm font-medium">{{ __('Vorschläge') }}</h2>
                @if ($tour->proposals->isEmpty())
                    <x-empty-state icon="auto_awesome" :title="__('Noch kein Vorschlag berechnet.')" compact />
                @else
                    <ul class="space-y-1 text-sm">
                        @foreach ($tour->proposals->take(5) as $proposal)
                            <li class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('tour-proposals.show', $proposal) }}" class="link">{{ $proposal->created_at?->fdatetime() ?? '#' . $proposal->id }}</a>
                                <x-status-badge :tone="$proposal->status->tone()" size="xs">{{ $proposal->status->label() }}</x-status-badge>
                                @if (isset($proposal->metrics['after']['distance_km']))
                                    <span class="text-xs text-muted">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $proposal->metrics['after']['distance_km'], 2, withThousandsSeparator: true) }} km · {{ $proposal->metrics['after']['stops'] ?? 0 }} {{ __('Stopps') }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
            <x-card>
                <h2 class="mb-2 text-sm font-medium">{{ __('Verlauf') }}</h2>
                <x-journal :entries="$tour->journal" />
            </x-card>
        </div>
    </x-page-shell>
@endsection
