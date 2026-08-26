{{--
  Created on   : Mon May 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _shift_type_manager.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
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
        <x-table table-sort="client">
            <x-slot:head>
                    <tr>
                        <th class="w-6"></th>
                        <th data-sort data-sort-default="asc">{{ __('Kürzel') }}</th>
                        <th data-sort>{{ __('Name') }}</th>
                        <th data-sort>{{ __('Von') }}</th>
                        <th data-sort>{{ __('Bis') }}</th>
                        <th data-sort>{{ __('Aktiv') }}</th>
                        <th></th>
                    </tr>
            </x-slot:head>
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
                                            data-type-edit="{{ $t->sqid }}"
                                            data-type-payload="{{ json_encode([
                                                'id' => $t->sqid,
                                                'name' => $t->name,
                                                'abbreviation' => $t->abbreviation,
                                                'color' => $t->color,
                                                'default_start_time' => $t->default_start_time,
                                                'default_end_time' => $t->default_end_time,
                                                'is_active' => (bool) $t->is_active,
                                            ]) }}"
                                            :label="__('Bearbeiten')" />
                                <x-icon-btn icon="delete" tone="error" type="button"
                                            data-type-delete="{{ $t->sqid }}"
                                            :label="__('Löschen')" />
                            </td>
                        </tr>
                    @empty
                        <x-table.empty :colspan="7" icon="schedule" :title="__('Noch keine Schichttypen angelegt.')" compact />
                    @endforelse
        </x-table>
    </x-form-group>

    {{-- ── Create / edit form ── --}}
    <x-form-group id="shift-type-form-group" :legend="__('Neuen Schichttyp anlegen')" icon="add" tone="success" class="mt-4">
        <h4 id="shift-type-form-title" class="text-sm font-semibold">{{ __('Neuen Schichttyp anlegen') }}</h4>

        <form id="shift-type-form" novalidate class="space-y-3">
            <input type="hidden" id="shift-type-id" name="id" value="">

            <div class="flex flex-wrap items-center gap-3">
                <label class="flex cursor-pointer items-center gap-1.5 text-sm">
                    <span class="text-xs text-muted">{{ __('Farbe') }}</span>
                    <input type="color" id="shift-type-color" name="color" value="#3b82f6"
                           class="h-7 w-9 cursor-pointer rounded border border-base-300 p-0.5">
                </label>
                <label class="flex cursor-pointer items-center gap-2 text-sm">
                    <span>{{ __('Aktiv') }}</span>
                    <input type="checkbox" id="shift-type-active" name="is_active" checked class="toggle toggle-primary toggle-sm">
                </label>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <x-input-field name="name"
                               :label="__('Name')"
                               type="text"
                               required
                               id="shift-type-name"
                               maxlength="100"
                               placeholder="{{ __('z.B. Frühschicht') }}" />
                <x-input-field name="abbreviation"
                               label="{{ __('Kürzel') }} (max. 5)"
                               type="text"
                               required
                               class="font-mono uppercase"
                               id="shift-type-abbr"
                               maxlength="5"
                               placeholder="{{ __('z.B. F') }}" />
                <div class="fieldset md:col-span-2">
                    <span class="fieldset-label">{{ __('Von – Bis') }}</span>
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
                <x-icon-btn icon="restart_alt" size="sm" type="button" id="shift-type-reset" show-label>{{ __('Zurücksetzen') }}</x-icon-btn>
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
