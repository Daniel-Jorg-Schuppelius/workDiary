{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tabs.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Reiter eines Vereinstermins (MVP-969): Termin, Spieltag bzw. Wettkampf, Anwesenheit; erwartet $event. --}}
@php
    $tabDetails = $details ?? $event->clubDetails()->first();
    $tabKind = $tabDetails?->kind;
@endphp
<x-tab-nav class="flex-none" :items="[
    ['route' => 'club.events.show', 'params' => $event, 'routeIs' => 'club.events.show', 'icon' => 'event', 'label' => __('club.tab.event')],
    ['route' => 'club.matches.show', 'params' => $event, 'routeIs' => 'club.matches.show', 'icon' => 'sports_soccer', 'label' => __('club.tab.match'),
     'when' => $tabKind === \App\Enums\Club\ClubEventKind::Match],
    ['route' => 'club.competitions.show', 'params' => $event, 'routeIs' => 'club.competitions.show', 'icon' => 'emoji_events', 'label' => __('club.tab.competition'),
     'when' => $tabKind === \App\Enums\Club\ClubEventKind::Competition],
    ['route' => 'club.events.attendance.show', 'params' => $event, 'routeIs' => 'club.events.attendance.show', 'icon' => 'fact_check', 'label' => __('club.tab.attendance'),
     'when' => $tabDetails !== null && \Illuminate\Support\Facades\Gate::allows('manageParticipants', $tabDetails)],
]" />
