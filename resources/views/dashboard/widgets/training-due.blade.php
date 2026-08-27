{{--
  Created on   : Thu Aug 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : training-due.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Meine Schulungspflichten" — Daten: TrainingDueWidget.
--}}
<x-card :title="__('Meine Schulungspflichten')" icon="school" :count="$assignments->count()">
    <x-slot:actions>
        <x-button href="{{ route('training.assignments.index') }}" tone="ghost" size="xs">{{ __('Alle →') }}</x-button>
    </x-slot:actions>

    @if ($assignments->isEmpty())
        <x-empty-state compact icon="school"
                       :title="__('Nichts offen')" :message="__('Keine offenen Schulungspflichten.')" />
    @else
        <ul class="space-y-2 text-sm">
            @foreach ($assignments as $assignment)
                <li class="flex flex-wrap items-center justify-between gap-2 rounded-box border border-base-300 bg-base-200 px-3 py-2">
                    <span class="min-w-0 truncate">{{ $assignment->course?->title ?? '—' }}</span>
                    <x-status-badge size="xs" :tone="$assignment->due_at?->isPast() ? 'error' : 'ghost'">{{ $assignment->due_at?->fdate() }}</x-status-badge>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
