{{--
  Created on   : Sun Jul 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : operations-tasks.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Dashboard-Kachel „Offene Betriebsaufgaben" (B3/MVP-344): Zähler, Top-3
  nach Dringlichkeit und Link ins Admin-Aufgabencenter. Daten liefert
  App\Dashboard\Widgets\OperationsTasksWidget.
--}}
<x-card :title="__('operations.title.widget')">
    <div class="space-y-2 text-sm">
        <a href="{{ route('admin.operations.index') }}" class="flex items-center justify-between hover:underline">
            <span class="flex items-center gap-2"><x-icon name="task_alt" /> {{ __('operations.widget.open') }}</span>
            <span class="badge {{ $openCount > 0 ? 'badge-warning' : 'badge-ghost' }}">{{ $openCount }}</span>
        </a>

        @if ($tasks->isEmpty())
            <p class="text-xs text-base-content/60">{{ __('operations.widget.empty') }}</p>
        @else
            <ul class="space-y-1">
                @foreach ($tasks as $task)
                    <li class="flex items-center justify-between gap-2">
                        <span class="min-w-0 truncate">{{ $task->title() }}</span>
                        <x-status-badge size="xs" :tone="$task->severity->tone()">{{ $task->severity->label() }}</x-status-badge>
                    </li>
                @endforeach
            </ul>
            <a href="{{ route('admin.operations.index') }}" class="link link-hover text-xs text-base-content/70">{{ __('operations.widget.all') }}</a>
        @endif
    </div>
</x-card>
