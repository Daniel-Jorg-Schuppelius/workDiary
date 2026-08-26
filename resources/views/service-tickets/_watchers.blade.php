{{--
  Created on   : Sun Jul 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _watchers.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Beobachter-Widget (Feature 065, MVP-160). Erwartet: $ticket (mit
  watchers.user geladen), $orgUsers, $canUpdate.
--}}

<x-card :title="__('Beobachter')" icon="visibility">
    @if ($ticket->watchers->isEmpty())
        <p class="text-sm text-muted">{{ __('Noch keine Beobachter.') }}</p>
    @else
        <ul class="space-y-1 text-sm">
            @foreach ($ticket->watchers as $watcher)
                <li class="flex items-center gap-2">
                    <x-icon name="person" class="text-muted" />
                    <span class="flex-1">{{ $watcher->user?->name ?? '—' }}</span>
                    @if ($canUpdate && $watcher->user)
                        <x-action-form :action="route('helpdesk.tickets.watchers.destroy', [$ticket, $watcher->user])"
                              method="DELETE"
                              :confirm="__('Beobachter entfernen?')"
                              :confirm-label="__('Entfernen')">
                            <x-icon-btn icon="close" tone="ghost" size="xs" type="submit" :label="__('Entfernen')" />
                        </x-action-form>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @if ($canUpdate)
        <form method="POST" action="{{ route('helpdesk.tickets.watchers.store', $ticket) }}"
              class="mt-3 flex items-end gap-2">
            @csrf
            <div class="flex-1">
                <x-user-select name="user" :users="$orgUsers" value-key="sqid" required
                               class="select-sm" :placeholder="__('Benutzer auswählen…')" />
            </div>
            <x-icon-btn icon="person_add" tone="primary" size="sm" type="submit" show-label>{{ __('Hinzufügen') }}</x-icon-btn>
        </form>
    @endif
</x-card>
