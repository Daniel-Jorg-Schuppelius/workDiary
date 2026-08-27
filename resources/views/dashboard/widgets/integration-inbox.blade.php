{{--
  Created on   : Thu Aug 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : integration-inbox.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Zuordnungs-Inbox" — Daten: IntegrationInboxWidget.
--}}
<x-card :title="__('Zuordnungs-Inbox')" icon="inbox" :count="$total">
    <x-slot:actions>
        <x-button href="{{ route('admin.integration.inbox') }}" tone="ghost" size="xs">{{ __('Inbox →') }}</x-button>
    </x-slot:actions>

    @if ($perPlugin->isEmpty())
        <x-empty-state compact icon="inbox"
                       :title="__('Alles zugeordnet')" :message="__('Keine offenen Positionen aus Importen.')" />
    @else
        <ul class="space-y-2 text-sm">
            @foreach ($perPlugin as $row)
                <li class="flex items-center justify-between gap-3 rounded-box border border-base-300 bg-base-200 px-3 py-2">
                    <span class="min-w-0 truncate">{{ $row->plugin_id }}</span>
                    <span class="badge badge-warning badge-sm tabular-nums">{{ $row->cnt }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
