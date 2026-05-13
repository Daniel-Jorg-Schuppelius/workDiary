{{-- Shift-type manager dialog — admin only, no Alpine.js --}}
<dialog id="shift-type-manager" class="modal">
    <div class="modal-box w-full max-w-2xl overflow-hidden p-0">

        {{-- ── Header (x-dialog style) ── --}}
        <div class="sticky top-0 z-10 flex items-start gap-3 border-b border-base-300 bg-linear-to-br from-primary/15 via-primary/5 to-transparent px-6 py-5 pr-14">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-box bg-primary/15 text-primary text-lg">
                🔄
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-base-content/60">{{ __('Schichtplan') }}</p>
                <h2 class="mt-1 font-['Space_Grotesk'] text-xl font-bold text-base-content">{{ __('Schichttypen verwalten') }}</h2>
            </div>
            <button type="button" onclick="document.getElementById('shift-type-manager').close()"
                    class="absolute right-4 top-4 btn btn-sm btn-ghost btn-circle" aria-label="{{ __('Schließen') }}">✕</button>
        </div>

        {{-- ── Body ── --}}
        <div class="px-6 py-5">

            {{-- ── Existing types table ── --}}
            <div class="mb-5 overflow-x-auto rounded-box border border-base-300">
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
                <div class="mb-3 flex items-center justify-between">
                    <h4 id="shift-type-form-title" class="text-sm font-semibold">{{ __('Neuen Schichttyp anlegen') }}</h4>
                    <div class="flex items-center gap-3">
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
                </div>
                <form id="shift-type-form" novalidate>
                    <input type="hidden" id="shift-type-id" name="id" value="">

                    <div class="grid grid-cols-2 gap-3">
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
                        <div class="fieldset col-span-2">
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

                    <div id="shift-type-error" class="alert alert-error alert-sm mt-3 hidden text-sm"></div>

                    <div class="mt-4 flex justify-between">
                        <button type="button" id="shift-type-reset" onclick="shiftTypeResetForm()" class="btn btn-sm btn-ghost">{{ __('Zurücksetzen') }}</button>
                        <button type="submit" id="shift-type-save" class="btn btn-sm btn-primary">{{ __('Speichern') }}</button>
                    </div>
                </form>
            </div>

        </div>

        {{-- ── Footer ── --}}
        <div class="sticky bottom-0 flex items-center justify-end border-t border-base-300 bg-base-100 px-6 py-3">
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
