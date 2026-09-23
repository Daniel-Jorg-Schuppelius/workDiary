{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _resources_card.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Ressourcen eines Termins (MVP-853): Belegungen mit Fenster, markierte zur Neuplanung, fehlende Freigaben.
  Variablen: $event, $resourceBookings, $missingClearances, $canParticipants, $isCancelled.
--}}
<x-card :title="__('club.resources.card.event')" icon="stadium" :count="$resourceBookings->count()">
    <ul class="space-y-1 text-sm">
        @forelse ($resourceBookings as $booking)
            <li class="flex flex-wrap items-center gap-2">
                <x-icon :name="$booking->resource?->kind->icon() ?? 'category'" class="text-muted" />
                @if ($booking->resource)<a href="{{ route('club.resources.show', $booking->resource) }}" class="link link-hover font-medium">{{ $booking->resource->fullName() }}</a>@endif
                @if ($booking->quantity > 1)<span class="badge badge-ghost badge-xs">× {{ $booking->quantity }}</span>@endif
                <span class="text-xs tabular-nums text-muted">{{ $booking->starts_at->orgTz()->format('H:i') }}–{{ $booking->ends_at->orgTz()->format('H:i') }}</span>
                @if ($booking->member)<span class="text-xs text-muted">{{ $booking->member->fullName() }}</span>@endif
                @if ($booking->isFlagged())<x-status-badge tone="warning" size="xs" icon="warning" :label="$booking->flag_reason ?? __('club.resources.label.replan')" />@endif
                @if ($canParticipants && ! $isCancelled)
                    <x-action-form :action="route('club.events.resources.destroy', [$event, $booking])" method="DELETE" class="ml-auto">
                        <x-icon-btn type="submit" icon="close" tone="ghost" size="xs" :label="__('club.resources.action.release')" />
                    </x-action-form>
                @endif
            </li>
        @empty
            <li class="text-muted">{{ __('club.resources.empty.event') }}</li>
        @endforelse
    </ul>
    @if ($missingClearances->isNotEmpty())
        <div class="mt-2 text-xs text-warning">
            <x-icon name="warning" /> {{ __('club.resources.label.missing_clearances') }}
            {{ $missingClearances->map(fn ($row) => $row['member']->fullName() . ' (' . $row['resource']->name . ')')->implode(', ') }}
        </div>
    @endif
    @if ($canParticipants && ! $isCancelled)
        <div class="mt-2"><x-icon-btn icon="add" tone="outline" size="xs" data-entry-modal-trigger :href="route('club.events.resources.create', $event)" show-label>{{ __('club.resources.action.book') }}</x-icon-btn></div>
    @endif
</x-card>
