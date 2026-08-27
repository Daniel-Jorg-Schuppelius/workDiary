{{--
  Created on   : Thu Aug 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : tax-filings.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Steuertermine" — Daten: TaxFilingsWidget.
--}}
<x-card :title="__('Steuertermine')" icon="event_note" :count="$obligations->count()">
    <x-slot:actions>
        <x-button href="{{ route('finance.accounting.filings.index') }}" tone="ghost" size="xs">{{ __('Kalender →') }}</x-button>
    </x-slot:actions>

    @if ($obligations->isEmpty())
        <x-empty-state compact icon="event_available"
                       :title="__('Keine Termine')" :message="__('In den nächsten Wochen steht keine Abgabe an.')" />
    @else
        <ul class="space-y-2 text-sm">
            @foreach ($obligations as $obligation)
                <li class="flex flex-wrap items-center justify-between gap-2 rounded-box border border-base-300 bg-base-200 px-3 py-2">
                    <span class="min-w-0 truncate">{{ $obligation->kind->label() }} <span class="text-muted">· {{ $obligation->period_key }}</span></span>
                    <x-status-badge size="xs" :tone="$obligation->due_on?->isPast() ? 'error' : 'ghost'">{{ $obligation->due_on?->fdate() }}</x-status-badge>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
