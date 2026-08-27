{{--
  Created on   : Thu Aug 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : kanban-status.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Meine Aufträge im Kanban" — Daten: KanbanStatusWidget.
--}}
<x-card :title="__('Meine Aufträge im Kanban')" icon="view_kanban">
    <x-slot:actions>
        <x-button href="{{ route('kanban.index') }}" tone="ghost" size="xs">{{ __('Zum Board →') }}</x-button>
    </x-slot:actions>

    @if ($counts->isEmpty())
        <x-empty-state compact icon="view_kanban"
                       :title="__('Nichts zugewiesen')" :message="__('Dir sind derzeit keine offenen Aufträge zugewiesen.')" />
    @else
        <ul class="grid grid-cols-2 gap-2 text-sm sm:grid-cols-3">
            @foreach ($statuses as $status)
                @php $count = (int) ($counts[$status->value] ?? 0); @endphp
                <li @class([
                    'rounded-box border px-3 py-2',
                    'border-base-300 bg-base-200 opacity-60' => $count === 0,
                    'border-primary/40 bg-primary/5' => $count > 0,
                ])>
                    <p class="truncate text-xs text-muted">{{ $status->label() }}</p>
                    <p class="font-['Space_Grotesk'] text-xl font-bold tabular-nums">{{ $count }}</p>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
