{{--
  Created on   : Fri Oct 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : work-queue.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Arbeitsvorrat“ — Daten: WorkQueueWidget ($items, $total, $sources, $now).
--}}
<x-card :title="__('work-queue.widget.label')" icon="task_alt" :count="$total">
    @if ($total > 0)
        <x-slot:actions>
            <a href="{{ route('work-queue.index') }}" class="link link-hover text-xs">{{ __('work-queue.widget.all') }}</a>
        </x-slot:actions>
    @endif

    @if ($items === [])
        <x-empty-state compact icon="task_alt" :title="__('work-queue.empty.title')" :message="__('work-queue.widget.empty')" />
    @else
        <ul class="space-y-2 text-sm">
            @foreach ($items as $item)
                @php($urgency = $item->urgency($now))
                <li class="flex flex-wrap items-center gap-2">
                    <x-status-badge size="xs" :tone="$urgency->tone()">{{ $urgency->label() }}</x-status-badge>
                    @if ($item->url !== null)
                        <a href="{{ $item->url }}" class="link link-hover font-medium">{{ $item->title }}</a>
                    @else
                        <span class="font-medium">{{ $item->title }}</span>
                    @endif
                    @if ($item->count > 1)
                        <x-status-badge size="xs" tone="neutral">{{ $item->count }}</x-status-badge>
                    @endif
                    <span class="text-xs text-muted">{{ $sources[$item->source]->label() }}@if ($item->dueAt !== null) · {{ $item->dueAt->fdate() }}@endif</span>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
