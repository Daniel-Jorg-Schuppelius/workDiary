{{--
  Created on   : Fri Oct 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _times.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Zeitimport der Redmine-Seite (MVP-1323): Schalter je Zuordnung und
  Zuordnung der Redmine-Benutzer mit wartenden Zeiten. Variablen aus
  RedmineAdminController::extraViewData(): $timeLinks, $pendingUsers,
  $mappedUsers, $orgUsers, $timeWindowDays; dazu $routePrefix.
--}}
<x-card padding="p-0">
    <header class="border-b border-base-300 px-4 py-3">
        <span class="text-sm font-semibold">{{ __('redmine::redmine.times.title') }}</span>
        <p class="mt-1 text-xs text-muted">{{ __('redmine::redmine.times.hint', ['days' => $timeWindowDays]) }}</p>
    </header>
    <x-table bare size="sm">
        <x-slot:head>
            <tr>
                <th>{{ __('redmine::redmine.times.col.project') }}</th>
                <th>{{ __('redmine::redmine.times.col.last_run') }}</th>
                <th class="text-right">{{ __('redmine::redmine.times.col.import') }}</th>
            </tr>
        </x-slot:head>
        @forelse ($timeLinks as $timeLink)
            <tr>
                <td>
                    <div class="font-medium">{{ $timeLink->sourceLabel() }}</div>
                    <div class="text-xs text-muted">{{ $timeLink->project?->name ?? '—' }}</div>
                </td>
                <td class="text-sm">
                    {{ $timeLink->last_time_import_at?->fdatetime() ?? '—' }}
                    @if (is_array($timeLink->last_time_counters))
                        <div class="text-xs text-muted">{{ __('redmine::redmine.times.counters', array_map('intval', $timeLink->last_time_counters + ['created' => 0, 'updated' => 0, 'unresolved_users' => 0, 'removed' => 0])) }}</div>
                    @endif
                </td>
                <td class="text-right whitespace-nowrap">
                    <x-action-form :action="route($routePrefix . '.links.times', $timeLink)">
                        <x-button type="submit" size="xs" :tone="$timeLink->is_time_import_enabled ? 'success' : 'ghost'" :icon="$timeLink->is_time_import_enabled ? 'toggle_on' : 'toggle_off'">
                            {{ $timeLink->is_time_import_enabled ? __('redmine::redmine.times.enabled') : __('redmine::redmine.times.disabled') }}
                        </x-button>
                    </x-action-form>
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="3" icon="schedule" :title="__('redmine::redmine.times.empty')" compact />
        @endforelse
    </x-table>
</x-card>

<x-card :title="__('redmine::redmine.users.title')">
    <p class="mb-3 text-sm text-muted">{{ __('redmine::redmine.users.hint') }}</p>
    @forelse ($pendingUsers as $pending)
        <form method="POST" action="{{ route($routePrefix . '.users.map') }}" class="mb-2 flex flex-wrap items-end gap-2">
            @csrf
            <input type="hidden" name="redmine_user" value="{{ $pending['key'] }}">
            <x-select-field name="user" :id="'redmine-user-' . $loop->index" :label="__('redmine::redmine.users.waiting', ['name' => $pending['name'], 'count' => $pending['count']])" required>
                @foreach ($orgUsers as $orgUser)
                    <option value="{{ $orgUser->sqid }}">{{ $orgUser->name }} · {{ $orgUser->email }}</option>
                @endforeach
            </x-select-field>
            <x-button type="submit" icon="person_add">{{ __('redmine::redmine.users.map') }}</x-button>
        </form>
    @empty
        <x-empty-state icon="group" :title="__('redmine::redmine.users.none_pending')" compact />
    @endforelse

    @if ($mappedUsers->isNotEmpty())
        <h3 class="mb-2 mt-4 text-sm font-semibold">{{ __('redmine::redmine.users.mapped') }}</h3>
        <ul class="space-y-1 text-sm">
            @foreach ($mappedUsers as $reference)
                <li class="flex items-center justify-between gap-2">
                    <span>{{ __('redmine::redmine.users.mapping', ['redmine' => \Illuminate\Support\Str::after((string) $reference->external_id, ':'), 'user' => $reference->referenceable?->name ?? '—']) }}</span>
                    <x-action-form :action="route($routePrefix . '.users.unmap')" :confirm="__('redmine::redmine.users.confirm_unmap')">
                        <input type="hidden" name="redmine_user" value="{{ $reference->external_id }}">
                        <x-icon-btn icon="person_remove" type="submit" tone="error" :label="__('redmine::redmine.users.unmap')" />
                    </x-action-form>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
