{{--
  Created on   : Thu May 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _permission_matrix.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Permission-Matrix für Rollen- und Gruppen-Formulare.
     Erwartet: $grouped (array<group, list<PermissionEnum>>), $assigned (list<string>), $name (string). --}}
@php
    $assignedSet = array_flip($assigned ?? []);
@endphp

<div x-data="permissionMatrix">
    <label class="input input-bordered input-sm flex items-center gap-2 mb-3 max-w-md">
        <x-icon name="search" class="text-base-content/60" />
        <input type="text" x-model="filter" class="grow" placeholder="{{ __('access.placeholder.filter_permissions') }}" />
    </label>

    <div class="space-y-4">
        @foreach ($grouped as $groupKey => $items)
            @php($groupEnum = \App\Enums\User\PermissionGroup::from($groupKey))
            @php($groupHaystack = \Illuminate\Support\Str::lower(collect($items)->map(fn($p) => $p->value . ' ' . $p->label())->implode(' ')))
            <div class="collapse collapse-arrow bg-base-200/40"
                 x-show="matches({{ \Illuminate\Support\Js::from($groupHaystack) }})">
                <input type="checkbox" checked />
                <div class="collapse-title font-medium flex items-center gap-2">
                    <x-icon :name="$groupEnum->icon()" />
                    {{ $groupEnum->label() }}
                    <x-status-badge tone="ghost" size="sm" class="ml-auto">
                        {{ collect($items)->filter(fn($p) => isset($assignedSet[$p->value]))->count() }}
                        / {{ count($items) }}
                    </x-status-badge>
                </div>
                <div class="collapse-content">
                    <div class="flex gap-2 mb-2">
                        <button type="button"
                                class="btn btn-xs btn-ghost"
                                @click="selectGroup('{{ $groupKey }}')">
                            {{ __('access.action.select_all') }}
                        </button>
                        <button type="button"
                                class="btn btn-xs btn-ghost"
                                @click="clearGroup('{{ $groupKey }}')">
                            {{ __('access.action.select_none') }}
                        </button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                        @foreach ($items as $permission)
                            <label class="label cursor-pointer justify-start gap-3 hover:bg-base-200 rounded px-2"
                                   x-show="matches({{ \Illuminate\Support\Js::from(\Illuminate\Support\Str::lower($permission->value . ' ' . $permission->label())) }})">
                                <input type="checkbox"
                                       name="{{ $name }}"
                                       value="{{ $permission->value }}"
                                       data-group="{{ $groupKey }}"
                                       class="checkbox checkbox-sm"
                                       @checked(isset($assignedSet[$permission->value])) />
                                <span class="flex-1">
                                    <span class="block text-sm">{{ $permission->label() }}</span>
                                    <span class="block text-xs text-base-content/50 font-mono">{{ $permission->value }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
