{{--
  Created on   : Thu Oct 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Tourvorschlag'))
@section('nav-title', __('Tourvorschlag'))

@section('content')
    @php
        $reasonLabel = static fn(string $reason): string => match ($reason) {
            \App\Services\Routing\Solver\UnassignedJob::REASON_NO_QUALIFICATION => __('Qualifikation fehlt'),
            \App\Services\Routing\Solver\UnassignedJob::REASON_TIME_WINDOW => __('Zeitfenster nicht erreichbar'),
            \App\Services\Routing\Solver\UnassignedJob::REASON_DAY_FULL => __('Arbeitstag voll'),
            \App\Services\Routing\Solver\UnassignedJob::REASON_UNREACHABLE => __('Nicht erreichbar'),
            \App\Services\Routing\Solver\UnassignedJob::REASON_NO_COORDINATES => __('Ohne Koordinaten'),
            default => $reason,
        };
        $before = $metrics['before'] ?? null;
        $after = $metrics['after'] ?? [];
        $open = $proposal->status === \App\Enums\Tour\TourProposalStatus::Open;
        $isDay = $kind === \App\Services\Routing\TourProposalService::KIND_DAY;
        $title = $isDay
            ? __('Tagesvorschlag') . ' · ' . $proposal->proposal_date->fdate()
            : ($kind === \App\Services\Routing\TourProposalService::KIND_REMAINDER ? __('Rest neu planen') : __('Tourvorschlag')) . ' · ' . ($tour?->name ?? ('#' . ($tour?->id ?? $proposal->id)));
        $fmtKm = static fn($km): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $km, 2, withThousandsSeparator: true) . ' km';
    @endphp

    <x-page-shell>
        <x-slot:toolbar>
            <x-page-toolbar :title="$title" :badge="$proposal->status->label()" :badge-tone="$proposal->status->badgeTone()">
                <div class="text-sm text-base-content/70">
                    {{ $proposal->proposal_date->fdate() }}
                    @if ($tour)
                        · {{ $tour->user?->name }}
                    @endif
                    @if ($proposal->solver)
                        · {{ __('Solver') }}: {{ $proposal->solver }}
                    @endif
                    @if ($proposal->is_estimate)
                        · <span class="text-warning">{{ __('Luftlinie, grobe Schätzung') }}</span>
                    @endif
                </div>
                <x-slot:actions>
                    @if ($tour)
                        <x-icon-btn icon="route" size="sm" :href="route('tours.show', $tour)" show-label>{{ __('Zur Tour') }}</x-icon-btn>
                    @else
                        <x-icon-btn icon="dashboard" size="sm" :href="route('dispatch.day-plan', ['date' => $proposal->proposal_date->toDateString()])" show-label>{{ __('Zur Tagesplanung') }}</x-icon-btn>
                    @endif
                    @if ($open)
                        <x-icon-btn icon="close" size="sm" data-entry-modal-trigger :href="route('tour-proposals.reject-form', $proposal)" show-label>{{ __('Verwerfen') }}</x-icon-btn>
                        <x-action-form :action="route('tour-proposals.adopt', $proposal)"
                              :confirm="$isDay ? __('Touren dieses Vorschlags anlegen und die Aufträge zuweisen?') : __('Reihenfolge und Zeiten dieses Vorschlags in die Tour übernehmen?')"
                              :confirm-label="__('Übernehmen')">
                            <x-icon-btn icon="check" tone="primary" size="sm" type="submit" show-label>{{ __('Übernehmen') }}</x-icon-btn>
                        </x-action-form>
                    @endif
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>

        @if ($proposal->status === \App\Enums\Tour\TourProposalStatus::Computing)
            <x-card>
                <p class="text-sm">{{ __('Der Vorschlag wird berechnet. Die Seite aktualisiert sich nicht von selbst.') }}</p>
                <div class="mt-3">
                    <x-icon-btn icon="refresh" size="sm" :href="route('tour-proposals.show', $proposal)" show-label>{{ __('Neu laden') }}</x-icon-btn>
                </div>
            </x-card>
        @elseif ($proposal->status === \App\Enums\Tour\TourProposalStatus::Failed)
            <x-card>
                <p class="text-sm text-error">{{ __('Die Berechnung ist fehlgeschlagen.') }} {{ $proposal->failure }}</p>
            </x-card>
        @else
            <x-card>
                <h2 class="mb-2 text-sm font-medium">{{ __('Vergleich') }}</h2>
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <th></th>
                            <th class="text-right">{{ __('Bisher') }}</th>
                            <th class="text-right">{{ __('Vorschlag') }}</th>
                        </tr>
                    </x-slot:head>
                    <tr>
                        <td>{{ __('Strecke') }}</td>
                        <td class="text-right">{{ $before !== null ? $fmtKm($before['distance_km'] ?? 0) : '—' }}</td>
                        <td class="text-right">{{ $fmtKm($after['distance_km'] ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td>{{ __('Dauer') }}</td>
                        <td class="text-right">{{ $before !== null ? \App\Support\Formats::duration((int) ($before['duration_minutes'] ?? 0), 'clock') : '—' }}</td>
                        <td class="text-right">{{ \App\Support\Formats::duration((int) ($after['duration_minutes'] ?? 0), 'clock') }}</td>
                    </tr>
                    <tr>
                        <td>{{ __('Fahrzeit') }}</td>
                        <td class="text-right">—</td>
                        <td class="text-right">{{ \App\Support\Formats::duration((int) ($after['driving_minutes'] ?? 0), 'clock') }}</td>
                    </tr>
                    <tr>
                        <td>{{ __('Wartezeit') }}</td>
                        <td class="text-right">—</td>
                        <td class="text-right">{{ \App\Support\Formats::duration((int) ($after['waiting_minutes'] ?? 0), 'clock') }}</td>
                    </tr>
                    <tr>
                        <td>{{ __('Stopps') }}</td>
                        <td class="text-right">{{ $before !== null ? ($before['stops'] ?? 0) : '—' }}</td>
                        <td class="text-right">{{ $after['stops'] ?? 0 }}</td>
                    </tr>
                    @if ($isDay)
                        <tr>
                            <td>{{ __('Touren') }}</td>
                            <td class="text-right">—</td>
                            <td class="text-right">{{ $after['tours'] ?? 0 }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td>{{ __('Pausen') }}</td>
                        <td class="text-right">—</td>
                        <td class="text-right">{{ $after['breaks'] ?? 0 }}</td>
                    </tr>
                    <tr>
                        <td>{{ __('Nicht einplanbar') }}</td>
                        <td class="text-right">—</td>
                        <td class="text-right @if (($after['unassigned'] ?? 0) > 0) text-warning @endif">{{ $after['unassigned'] ?? 0 }}</td>
                    </tr>
                </x-table>
                @if ($proposal->decision_note)
                    <p class="mt-3 text-sm"><span class="font-medium">{{ __('Grund') }}:</span> {{ $proposal->decision_note }}</p>
                @endif
            </x-card>

            @forelse ($routes as $r)
                <x-card padding="p-0">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-base-300 px-4 py-2 text-sm">
                        <span class="font-medium">{{ $r['label'] }}</span>
                        <span class="text-base-content/70">{{ __('Tour von :from bis :to', ['from' => $r['start'], 'to' => $r['end']]) }} · {{ $fmtKm($r['distance_km']) }} · {{ __('Fahrzeit') }} {{ \App\Support\Formats::duration($r['driving_minutes'], 'clock') }}@if ($r['waiting_minutes'] > 0) · {{ __('Wartezeit') }} {{ \App\Support\Formats::duration($r['waiting_minutes'], 'clock') }}@endif</span>
                    </div>
                    <x-table bare>
                        <x-slot:head>
                            <tr>
                                <th>#</th>
                                <th>{{ __('Auftrag') }}</th>
                                <th>{{ __('Kunde') }}</th>
                                <th class="text-right">{{ __('Fahrt') }}</th>
                                <th>{{ __('Ankunft') }}</th>
                                <th class="text-right">{{ __('Warten') }}</th>
                                <th>{{ __('Beginn') }}</th>
                                <th>{{ __('Abfahrt') }}</th>
                                <th>{{ __('Zeitfenster') }}</th>
                            </tr>
                        </x-slot:head>
                        @forelse ($r['stops'] as $s)
                            <tr>
                                <td>{{ $s['position'] }}</td>
                                <td>
                                    @if ($s['entry'])
                                        <x-order-link :entry="$s['entry']" link-class="link">{{ $s['entry']->title }}</x-order-link>
                                    @else
                                        {{ $s['label'] }}
                                    @endif
                                </td>
                                <td>{{ $s['entry']?->customer?->name }}</td>
                                <td class="text-right">{{ $s['driving'] }} min · {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($s['distance_km'], 1, withThousandsSeparator: true) }} km</td>
                                <td>{{ $s['arrival'] }}</td>
                                <td class="text-right">@if ($s['waiting'] > 0)<span class="text-warning">{{ $s['waiting'] }} min</span>@else — @endif</td>
                                <td>{{ $s['service_start'] }}</td>
                                <td>{{ $s['departure'] }}</td>
                                <td class="text-xs text-muted">{{ $s['window'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <x-table.empty icon="route" :colspan="9" :title="__('Keine Stopps im Vorschlag.')" compact />
                        @endforelse
                    </x-table>
                    @if ($r['breaks'] !== [])
                        <div class="border-t border-base-300 px-4 py-2 text-xs text-muted">
                            {{ __('Pausen') }}:
                            @foreach ($r['breaks'] as $b)
                                {{ $b['start'] }}–{{ $b['end'] }} ({{ $b['at'] }})@if (! $loop->last), @endif
                            @endforeach
                        </div>
                    @endif
                </x-card>
            @empty
                <x-card>
                    <x-empty-state icon="route" :title="__('Keine Stopps im Vorschlag.')" compact />
                </x-card>
            @endforelse

            @if ($unassigned !== [])
                <x-card>
                    <h2 class="mb-2 text-sm font-medium">{{ __('Nicht einplanbar') }}</h2>
                    <ul class="space-y-1 text-sm">
                        @foreach ($unassigned as $u)
                            <li class="flex flex-wrap items-center gap-2">
                                @if ($u['entry'])
                                    <x-order-link :entry="$u['entry']" link-class="link">{{ $u['entry']->title }}</x-order-link>
                                @else
                                    <span>{{ $u['label'] }}</span>
                                @endif
                                <x-status-badge tone="warning" size="xs">{{ $reasonLabel($u['reason']) }}</x-status-badge>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        @endif
    </x-page-shell>
@endsection
