{{--
  Created on   : Thu Aug 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : recent-entries.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Meine letzten Einträge" — Daten: RecentEntriesWidget.
--}}
<x-card :title="__('Meine letzten Einträge')" icon="history_edu">
    @if ($entries->isEmpty())
        <x-empty-state compact icon="edit_note"
                       :title="__('Noch keine Einträge')" :message="__('Noch keine Einträge.')" />
    @else
        <ul class="space-y-2 text-sm">
            @foreach ($entries as $entry)
                <li class="rounded-box border border-base-300 bg-base-200 px-3 py-2">
                    <a href="{{ route('diary.show', $entry) }}" class="link link-primary block">{{ \CommonToolkit\Helper\Data\StringHelper::truncate($entry->content, 80) }}</a>
                    <span class="text-xs text-muted">{{ $entry->statusLabel() }} · {{ $entry->updated_at->diffForHumans() }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
