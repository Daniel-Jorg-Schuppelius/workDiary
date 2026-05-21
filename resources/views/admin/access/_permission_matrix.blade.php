{{-- Permission-Matrix für Rollen- und Gruppen-Formulare.
     Erwartet: $grouped (array<group, list<PermissionEnum>>), $assigned (list<string>), $name (string). --}}
@php
    $assignedSet = array_flip($assigned ?? []);
@endphp

<div x-data="{ filter: '' }">
    <label class="input input-bordered input-sm flex items-center gap-2 mb-3 max-w-md">
        <x-icon name="search" class="text-base-content/60" />
        <input type="text" x-model="filter" class="grow" placeholder="{{ __('access.placeholder.filter_permissions') }}" />
    </label>

    <div class="space-y-4">
        @foreach ($grouped as $groupKey => $items)
            @php($groupEnum = \App\Enums\User\PermissionGroup::from($groupKey))
            <div class="collapse collapse-arrow bg-base-200/40"
                 x-show="filter === '' || [{{ collect($items)->map(fn($p) => "'".$p->value."'")->implode(',') }}].some(v => v.toLowerCase().includes(filter.toLowerCase()))">
                <input type="checkbox" checked />
                <div class="collapse-title font-medium flex items-center gap-2">
                    <x-icon :name="$groupEnum->icon()" />
                    {{ $groupEnum->label() }}
                    <span class="badge badge-ghost badge-sm ml-auto">
                        {{ collect($items)->filter(fn($p) => isset($assignedSet[$p->value]))->count() }}
                        / {{ count($items) }}
                    </span>
                </div>
                <div class="collapse-content">
                    <div class="flex gap-2 mb-2">
                        <button type="button"
                                class="btn btn-xs btn-ghost"
                                @click="$root.querySelectorAll('input[data-group=&quot;{{ $groupKey }}&quot;]').forEach(el => el.checked = true)">
                            {{ __('access.action.select_all') }}
                        </button>
                        <button type="button"
                                class="btn btn-xs btn-ghost"
                                @click="$root.querySelectorAll('input[data-group=&quot;{{ $groupKey }}&quot;]').forEach(el => el.checked = false)">
                            {{ __('access.action.select_none') }}
                        </button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                        @foreach ($items as $permission)
                            <label class="label cursor-pointer justify-start gap-3 hover:bg-base-200 rounded px-2"
                                   x-show="filter === '' || '{{ $permission->value }}'.toLowerCase().includes(filter.toLowerCase()) || @js((string) $permission->label()).toLowerCase().includes(filter.toLowerCase())">
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
