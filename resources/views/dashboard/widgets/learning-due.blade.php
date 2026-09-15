{{--
  Created on   : Tue Sep 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : learning-due.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Meine Schulungen" — Daten: LearningDueWidget.
--}}
<x-card :title="__('Meine Schulungen')" icon="school" :count="$enrollments->count()">
    <x-slot:actions>
        <x-button href="{{ route('learning.my.index') }}" tone="ghost" size="xs">{{ __('Alle →') }}</x-button>
    </x-slot:actions>

    @if ($enrollments->isEmpty())
        <x-empty-state compact icon="school"
                       :title="__('Nichts offen')" :message="__('learning.empty.widget_due')" />
    @else
        <ul class="space-y-2 text-sm">
            @foreach ($enrollments as $enrollment)
                <li class="flex flex-wrap items-center justify-between gap-2 rounded-box border border-base-300 bg-base-200 px-3 py-2">
                    <a href="{{ route('learning.my.show', $enrollment) }}" class="link link-primary min-w-0 truncate">{{ $enrollment->course?->title ?? '—' }}</a>
                    <x-status-badge size="xs" :tone="$enrollment->due_at?->isPast() ? 'error' : 'ghost'">{{ $enrollment->due_at?->fdate() ?? $enrollment->status->label() }}</x-status-badge>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
