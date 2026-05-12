{{-- Shift-type manager dialog — admin only, no Alpine.js --}}
<dialog id="shift-type-manager" class="modal">
    <div class="modal-box w-full max-w-2xl">

        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-lg font-bold">{{ __('Schichttypen verwalten') }}</h3>
            <button type="button" onclick="document.getElementById('shift-type-manager').close()" class="btn btn-sm btn-circle btn-ghost">✕</button>
        </div>

        {{-- ── Existing types table ── --}}
        <div class="mb-4 overflow-x-auto rounded-box border border-base-300">
            <table class="table table-sm w-full">
                <thead>
                    <tr>
                        <th class="w-6"></th>
                        <th>{{ __('Kürzel') }}</th>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Von') }}</th>
                        <th>{{ __('Bis') }}</th>
                        <th>{{ __('Aktiv') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="shift-type-table-body">
                    @forelse ($shiftTypes as $t)
                        <tr data-type-row="{{ $t->id }}">
                            <td>
                                <span class="inline-block h-4 w-4 rounded" style="{{ $t->badgeStyle() }}"></span>
                            </td>
                            <td class="font-mono font-bold" id="type-abbr-{{ $t->id }}">{{ $t->abbreviation }}</td>
                            <td id="type-name-{{ $t->id }}">{{ $t->name }}</td>
                            <td id="type-start-{{ $t->id }}">{{ $t->default_start_time ?? '–' }}</td>
                            <td id="type-end-{{ $t->id }}">{{ $t->default_end_time ?? '–' }}</td>
                            <td>
                                <span class="badge badge-sm {{ $t->is_active ? 'badge-success' : 'badge-ghost' }}">
                                    {{ $t->is_active ? __('ja') : __('nein') }}
                                </span>
                            </td>
                            <td class="text-right">
                                <button type="button"
                                        onclick="shiftTypeOpenEdit({{ $t->id }}, @json($t))"
                                        class="btn btn-xs btn-ghost">{{ __('Bearbeiten') }}</button>
                                <button type="button"
                                        onclick="shiftTypeDelete({{ $t->id }})"
                                        class="btn btn-xs btn-ghost text-error">{{ __('Löschen') }}</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-4 text-center text-sm text-base-content/50">{{ __('Noch keine Schichttypen angelegt.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- ── Create / edit form ── --}}
        <div class="rounded-box border border-base-300 bg-base-200/40 p-4">
            <h4 id="shift-type-form-title" class="mb-3 text-sm font-semibold">{{ __('Neuen Schichttyp anlegen') }}</h4>
            <form id="shift-type-form" novalidate>
                <input type="hidden" id="shift-type-id" name="id" value="">

                <div class="grid grid-cols-2 gap-3">
                    <div class="form-control">
                        <label class="label pb-1"><span class="label-text text-xs">{{ __('Name') }} *</span></label>
                        <input type="text" id="shift-type-name" name="name" maxlength="100" required
                               class="input input-bordered input-sm w-full" placeholder="{{ __('z.B. Frühschicht') }}">
                    </div>
                    <div class="form-control">
                        <label class="label pb-1"><span class="label-text text-xs">{{ __('Kürzel') }} (max. 5) *</span></label>
                        <input type="text" id="shift-type-abbr" name="abbreviation" maxlength="5" required
                               class="input input-bordered input-sm w-full font-mono uppercase" placeholder="{{ __('z.B. F') }}">
                    </div>
                    <div class="form-control">
                        <label class="label pb-1"><span class="label-text text-xs">{{ __('Von') }}</span></label>
                        <input type="time" id="shift-type-start" name="default_start_time" class="input input-bordered input-sm w-full">
                    </div>
                    <div class="form-control">
                        <label class="label pb-1"><span class="label-text text-xs">{{ __('Bis') }}</span></label>
                        <input type="time" id="shift-type-end" name="default_end_time" class="input input-bordered input-sm w-full">
                    </div>
                    <div class="form-control">
                        <label class="label pb-1"><span class="label-text text-xs">{{ __('Farbe') }}</span></label>
                        <input type="color" id="shift-type-color" name="color" value="#3b82f6"
                               class="input input-bordered input-sm h-9 w-full cursor-pointer px-1">
                    </div>
                    <div class="form-control justify-end">
                        <label class="label cursor-pointer justify-start gap-3 pb-1">
                            <input type="checkbox" id="shift-type-active" name="is_active" checked class="checkbox checkbox-sm">
                            <span class="label-text text-xs">{{ __('Aktiv') }}</span>
                        </label>
                    </div>
                </div>

                <div id="shift-type-error" class="alert alert-error alert-sm mt-3 hidden text-sm"></div>

                <div class="mt-4 flex justify-between">
                    <button type="button" id="shift-type-reset" onclick="shiftTypeResetForm()" class="btn btn-sm btn-ghost">{{ __('Zurücksetzen') }}</button>
                    <button type="submit" id="shift-type-save" class="btn btn-sm btn-primary">{{ __('Speichern') }}</button>
                </div>
            </form>
        </div>

        <div class="modal-action mt-4">
            <button type="button" onclick="document.getElementById('shift-type-manager').close()" class="btn btn-sm btn-ghost">{{ __('Schließen') }}</button>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('btn-open-type-manager')?.addEventListener('click', function () {
        document.getElementById('shift-type-manager').showModal();
    });
});
</script>
