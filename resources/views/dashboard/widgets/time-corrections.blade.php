{{--
  Created on   : Thu Aug 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : time-corrections.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Offene Zeitkorrekturen" — Daten: TimeCorrectionsWidget.
--}}
<x-card :title="__('Offene Zeitkorrekturen')" icon="edit_calendar" :count="$requests->count()">
    <x-slot:actions>
        <x-button href="{{ route('corrections.index') }}" tone="ghost" size="xs">{{ __('Alle →') }}</x-button>
    </x-slot:actions>

    @if ($requests->isEmpty())
        <x-empty-state compact icon="event_available"
                       :title="__('Nichts offen')" :message="__('Keine offenen Korrekturanträge.')" />
    @else
        <ul class="space-y-2 text-sm">
            @foreach ($requests as $request)
                <li class="flex flex-wrap items-center justify-between gap-2 rounded-box border border-base-300 bg-base-200 px-3 py-2">
                    <a href="{{ route('corrections.show', $request) }}" class="link link-primary">
                        {{ $request->scope_date?->fdate() }}
                    </a>
                    <x-status-badge size="xs" :tone="$request->status === \App\Enums\TimeApproval\TimeCorrectionStatus::Submitted ? 'info' : 'ghost'">
                        {{ $request->status->label() }}
                    </x-status-badge>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
