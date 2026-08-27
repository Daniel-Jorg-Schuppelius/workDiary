{{--
  Created on   : Thu Aug 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : leasing-deadlines.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Leasing-Fristen" — Daten: LeasingDeadlinesWidget.
--}}
<x-card :title="__('Leasing-Fristen')" icon="event_repeat" :count="$deadlines->count()">
    <x-slot:actions>
        <x-button href="{{ route('asset-finance.deadlines.index') }}" tone="ghost" size="xs">{{ __('Fristenkalender →') }}</x-button>
    </x-slot:actions>

    @if ($deadlines->isEmpty())
        <x-empty-state compact icon="event_available"
                       :title="__('Keine Fristen')" :message="__('In den nächsten Wochen läuft keine Leasing-Frist ab.')" />
    @else
        <ul class="space-y-2 text-sm">
            @foreach ($deadlines as $deadline)
                <li class="flex flex-wrap items-center justify-between gap-2 rounded-box border border-base-300 bg-base-200 px-3 py-2">
                    <span class="min-w-0 truncate">
                        {{ $deadline->contract?->title ?? $deadline->contract?->number ?? '—' }}
                        <span class="text-muted">· {{ $deadline->kind->label() }}</span>
                    </span>
                    <x-status-badge size="xs" :tone="$deadline->due_on?->isPast() ? 'error' : 'ghost'">{{ $deadline->due_on?->fdate() }}</x-status-badge>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
