{{--
  Created on   : Thu Aug 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : reminders.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Erinnerungen" — Daten: RemindersWidget (ReminderService).
--}}
<x-card :title="__('Erinnerungen')" icon="notifications_active" :count="count($items)">
    @if ($items === [])
        <x-empty-state compact icon="notifications_off"
                       :title="__('Nichts zu tun')" :message="__('Derzeit keine Erinnerungen.')" />
    @else
        <ul class="grid gap-2 sm:grid-cols-2">
            @foreach ($items as $item)
                @php
                    $tone = ['error' => 'border-error/40 bg-error/5', 'warning' => 'border-warning/40 bg-warning/5'][$item->severity] ?? 'border-info/40 bg-info/5';
                @endphp
                <li class="rounded-box border {{ $tone }} px-3 py-2">
                    <a href="{{ $item->url }}" class="flex items-start gap-2">
                        <x-icon :name="$item->icon" class="mt-0.5 shrink-0" />
                        <span class="min-w-0">
                            <span class="block font-medium">{{ $item->title }}</span>
                            <span class="block text-xs text-muted">{{ $item->description }}</span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
