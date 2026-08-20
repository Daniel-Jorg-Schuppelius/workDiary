{{--
  Created on   : Thu May 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Rollen- und Gruppen-Zuweisung für ein Org-Mitglied. --}}
@php
    /** @var \App\Models\User $member */
    /** @var \Illuminate\Support\Collection $roles */
    /** @var \Illuminate\Support\Collection $groups */
    /** @var array<int, int> $assignedRoles */
    /** @var array<int, int> $assignedGroups */
    /** @var \Illuminate\Support\Collection $effectivePermissions */
    $permissionLabels = (array) trans('access.permission');
@endphp
<x-modal
    :title="__('access.title.member_edit', ['name' => $member->name])"
    :eyebrow="$member->email"
    icon="manage_accounts"
    tone="primary"
    size="xl"
    :action="route('admin.access.members.update', $member)"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('access.action.save')"
>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <section class="card bg-base-200/40">
            <div class="card-body space-y-2">
                <h3 class="card-title text-base">{{ __('access.title.assigned_roles') }}</h3>
                @forelse ($roles as $role)
                    <label class="label cursor-pointer justify-start gap-3 hover:bg-base-100 rounded px-2">
                        {{-- Rollen-IDs bleiben numerisch: Role ist ein Spatie-Vendor-Modell ohne HasSqid;
                             die Schutzlinie ist die org-/global-gescopte Whitelist im Controller (Audit W3.3). --}}
                        <input type="checkbox" name="roles[]" value="{{ $role->id }}"
                               class="checkbox checkbox-sm"
                               @checked(in_array($role->id, $assignedRoles, true)) />
                        <span class="text-sm">{{ \Illuminate\Support\Facades\Lang::has("user.role.{$role->name}") ? __("user.role.{$role->name}") : $role->name }}</span>
                        @if ($role->getAttribute(config('permission.column_names.team_foreign_key', 'team_id')) === null)
                            <x-status-badge tone="ghost" size="xs">{{ __('access.badge.global') }}</x-status-badge>
                        @endif
                    </label>
                @empty
                    <p class="text-sm text-base-content/60">{{ __('access.empty.roles') }}</p>
                @endforelse
            </div>
        </section>

        <section class="card bg-base-200/40">
            <div class="card-body space-y-2">
                <h3 class="card-title text-base">{{ __('access.title.assigned_groups') }}</h3>
                @forelse ($groups as $group)
                    <label class="label cursor-pointer justify-start gap-3 hover:bg-base-100 rounded px-2">
                        <input type="checkbox" name="groups[]" value="{{ $group->sqid }}"
                               class="checkbox checkbox-sm"
                               @checked(in_array($group->id, $assignedGroups, true)) />
                        <span class="text-sm">
                            {{ $group->name }}
                            @if ($group->description)
                                <span class="block text-xs text-base-content/50">{{ $group->description }}</span>
                            @endif
                        </span>
                    </label>
                @empty
                    <p class="text-sm text-base-content/60">{{ __('access.empty.groups') }}</p>
                @endforelse
            </div>
        </section>
    </div>

    <section class="card bg-base-200/40 mt-4">
        <div class="card-body space-y-2">
            <h3 class="card-title text-base flex items-center gap-2">
                <x-icon name="lock_open" />
                {{ __('access.title.effective_permissions') }}
                <x-status-badge tone="ghost" size="sm">{{ $effectivePermissions->count() }}</x-status-badge>
            </h3>
            <p class="text-xs text-base-content/60">{{ __('access.hint.effective_permissions') }}</p>
            @if ($effectivePermissions->isEmpty())
                <p class="text-sm text-base-content/60">{{ __('access.empty.effective_permissions') }}</p>
            @else
                <div class="flex flex-wrap gap-1 max-h-40 overflow-y-auto">
                    @foreach ($effectivePermissions as $permission)
                        <x-status-badge tone="ghost" size="sm">{{ $permissionLabels[$permission] ?? $permission }}</x-status-badge>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</x-modal>
