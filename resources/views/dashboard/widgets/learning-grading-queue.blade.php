{{--
  Created on   : Tue Sep 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : learning-grading-queue.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Bewertungen offen" — Daten: LearningGradingQueueWidget.
--}}
<x-card :title="__('Bewertungen offen')" icon="grading" :count="$total">
    <x-slot:actions>
        <x-button href="{{ route('learning.grading.index') }}" tone="ghost" size="xs">{{ __('Alle →') }}</x-button>
    </x-slot:actions>

    @if ($total === 0)
        <x-empty-state compact icon="grading"
                       :title="__('Nichts offen')" :message="__('learning.empty.widget_grading')" />
    @else
        <ul class="space-y-2 text-sm">
            <li class="flex items-center justify-between gap-2 rounded-box border border-base-300 bg-base-200 px-3 py-2">
                <a href="{{ route('learning.grading.index') }}" class="link link-primary">{{ __('learning.field.pending_submissions') }}</a>
                <x-status-badge size="xs" :tone="$submissions > 0 ? 'warning' : 'ghost'">{{ $submissions }}</x-status-badge>
            </li>
            <li class="flex items-center justify-between gap-2 rounded-box border border-base-300 bg-base-200 px-3 py-2">
                <a href="{{ route('learning.grading.index') }}" class="link link-primary">{{ __('learning.field.pending_essays') }}</a>
                <x-status-badge size="xs" :tone="$essays > 0 ? 'warning' : 'ghost'">{{ $essays }}</x-status-badge>
            </li>
            <li class="flex items-center justify-between gap-2 rounded-box border border-base-300 bg-base-200 px-3 py-2">
                <a href="{{ route('learning.time-approvals.index') }}" class="link link-primary">{{ __('learning.field.pending_time_approvals') }}</a>
                <x-status-badge size="xs" :tone="$timeApprovals > 0 ? 'warning' : 'ghost'">{{ $timeApprovals }}</x-status-badge>
            </li>
        </ul>
    @endif
</x-card>
