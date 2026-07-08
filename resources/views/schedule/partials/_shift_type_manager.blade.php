{{-- Shift-type manager dialog — admin only, no Alpine.js --}}
<x-modal id="shift-type-manager"
         :embedded="false"
         size="lg"
         tone="primary"
         icon="sync"
         :title="__('Schichttypen verwalten')"
         :eyebrow="__('Schichtplan')">

    {{-- ── Existing types table ── --}}
    <x-form-group :legend="__('Vorhandene Schichttypen')" icon="list" tone="primary">
        <div class="overflow-x-auto rounded-box border border-base-300">
            <table class="table table-sm w-full" data-sortable>
                <thead>
                    <tr>
                        <th class="w-6"></th>
                        <th data-sort data-sort-default="asc">{{ __('Kürzel') }}</th>
                        <th data-sort>{{ __('Name') }}</th>
                        <th data-sort>{{ __('Von') }}</th>
                        <th data-sort>{{ __('Bis') }}</th>
                        <th data-sort>{{ __('Aktiv') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="shift-type-table-body">
                    @forelse ($shiftTypes as $t)
                        <tr data-type-row="{{ $t->sqid }}">
                            <td>
                                <span class="inline-block h-4 w-4 rounded" style="{{ $t->badgeStyle() }}"></span>
                            </td>
                            <td class="font-mono font-bold" id="type-abbr-{{ $t->sqid }}">{{ $t->abbreviation }}</td>
                            <td id="type-name-{{ $t->sqid }}">{{ $t->name }}</td>
                            <td id="type-start-{{ $t->sqid }}">{{ $t->default_start_time ?? '–' }}</td>
                            <td id="type-end-{{ $t->sqid }}">{{ $t->default_end_time ?? '–' }}</td>
                            <td>
                                <x-status-badge size="sm" :tone="$t->is_active ? 'success' : 'ghost'">
                                    {{ $t->is_active ? __('ja') : __('nein') }}
                                </x-status-badge>
                            </td>
                            <td class="text-right">
                                <x-icon-btn icon="edit" type="button"
                                            onclick='shiftTypeOpenEdit(@js($t->sqid), @js([
                                                'id' => $t->sqid,
                                                'name' => $t->name,
                                                'abbreviation' => $t->abbreviation,
                                                'color' => $t->color,
                                                'default_start_time' => $t->default_start_time,
                                                'default_end_time' => $t->default_end_time,
                                                'is_active' => (bool) $t->is_active,
                                            ]))'
                                            :label="__('Bearbeiten')" />
                                <x-icon-btn icon="delete" tone="error" type="button"
                                            onclick='shiftTypeDelete(@js($t->sqid))'
                                            :label="__('Löschen')" />
                            </td>
                        </tr>
                    @empty
                        <x-table.empty :colspan="7" icon='<span class="material-symbols-outlined" aria-hidden="true">schedule</span>' :title="__('Noch keine Schichttypen angelegt.')" compact />
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-form-group>

    {{-- ── Create / edit form ── --}}
    <x-form-group id="shift-type-form-group" :legend="__('Neuen Schichttyp anlegen')" icon="add" tone="success" class="mt-4">
        <h4 id="shift-type-form-title" class="text-sm font-semibold">{{ __('Neuen Schichttyp anlegen') }}</h4>

        <form id="shift-type-form" novalidate class="space-y-3">
            <input type="hidden" id="shift-type-id" name="id" value="">

            <div class="flex flex-wrap items-center gap-3">
                <label class="flex cursor-pointer items-center gap-1.5 text-sm">
                    <span class="text-xs text-base-content/60">{{ __('Farbe') }}</span>
                    <input type="color" id="shift-type-color" name="color" value="#3b82f6"
                           class="h-7 w-9 cursor-pointer rounded border border-base-300 p-0.5">
                </label>
                <label class="flex cursor-pointer items-center gap-2 text-sm">
                    <span>{{ __('Aktiv') }}</span>
                    <input type="checkbox" id="shift-type-active" name="is_active" checked class="toggle toggle-primary toggle-sm">
                </label>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div class="fieldset">
                    <label class="fieldset-label">{{ __('Name') }} *</label>
                    <input type="text" id="shift-type-name" name="name" maxlength="100" required
                           class="input input-bordered w-full" placeholder="{{ __('z.B. Frühschicht') }}">
                </div>
                <div class="fieldset">
                    <label class="fieldset-label">{{ __('Kürzel') }} (max. 5) *</label>
                    <input type="text" id="shift-type-abbr" name="abbreviation" maxlength="5" required
                           class="input input-bordered w-full font-mono uppercase" placeholder="{{ __('z.B. F') }}">
                </div>
                <div class="fieldset md:col-span-2">
                    <label class="fieldset-label">{{ __('Von – Bis') }}</label>
                    <div class="join w-full">
                        <input type="time" id="shift-type-start" name="default_start_time"
                               class="join-item input input-bordered flex-1 min-w-0"
                               title="{{ __('Von') }}" aria-label="{{ __('Von') }}">
                        <input type="time" id="shift-type-end" name="default_end_time"
                               class="join-item input input-bordered flex-1 min-w-0"
                               title="{{ __('Bis') }}" aria-label="{{ __('Bis') }}">
                    </div>
                </div>
            </div>

            <div id="shift-type-error" class="alert alert-error alert-sm hidden text-sm"></div>

            <div class="flex justify-between">
                <x-icon-btn icon="restart_alt" size="sm" type="button" id="shift-type-reset" onclick="shiftTypeResetForm()" show-label>{{ __('Zurücksetzen') }}</x-icon-btn>
                <x-icon-btn icon="save" tone="primary" size="sm" type="submit" id="shift-type-save" show-label>{{ __('Speichern') }}</x-icon-btn>
            </div>
        </form>
    </x-form-group>

</x-modal>

<script @cspNonce>
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('btn-open-type-manager')?.addEventListener('click', function () {
        document.getElementById('shift-type-manager').showModal();
    });
});
</script>
