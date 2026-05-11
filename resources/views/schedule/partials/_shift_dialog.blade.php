{{-- Shift create/edit dialog — native <dialog>, no Alpine.js --}}
<dialog id="shift-dialog" class="modal">
    <div class="modal-box w-full max-w-lg">

        <div class="mb-4 flex items-center justify-between">
            <h3 id="shift-dialog-title" class="text-lg font-bold">{{ __('Schicht anlegen') }}</h3>
            <button type="button" onclick="document.getElementById('shift-dialog').close()" class="btn btn-sm btn-circle btn-ghost">✕</button>
        </div>

        <form id="shift-dialog-form" novalidate>
            <input type="hidden" id="shift-dialog-id" name="id" value="">

            {{-- User --}}
            @if (auth()->user()->isAdmin())
            <div class="form-control mb-3">
                <label class="label pb-1"><span class="label-text text-sm">{{ __('Mitarbeiter') }}</span></label>
                <select id="shift-dialog-user" name="user_id" class="select select-bordered select-sm w-full" required>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            @else
            <input type="hidden" id="shift-dialog-user" name="user_id" value="{{ auth()->id() }}">
            @endif

            {{-- Date --}}
            <div class="form-control mb-3">
                <label class="label pb-1"><span class="label-text text-sm">{{ __('Datum') }}</span></label>
                <input type="date" id="shift-dialog-date" name="date" class="input input-bordered input-sm w-full" required>
            </div>

            {{-- Shift type --}}
            <div class="form-control mb-3">
                <label class="label pb-1"><span class="label-text text-sm">{{ __('Schichttyp') }}</span></label>
                <select id="shift-dialog-type" name="shift_type_id" class="select select-bordered select-sm w-full">
                    <option value="">— {{ __('kein Typ') }} —</option>
                    @foreach ($shiftTypes as $t)
                        <option value="{{ $t->id }}" style="color:{{ $t->color }}">{{ $t->name }} ({{ $t->abbreviation }})</option>
                    @endforeach
                </select>
            </div>

            {{-- Times --}}
            <div class="mb-3 grid grid-cols-2 gap-3">
                <div class="form-control">
                    <label class="label pb-1"><span class="label-text text-sm">{{ __('Von') }}</span></label>
                    <input type="time" id="shift-dialog-start" name="start_time" class="input input-bordered input-sm w-full">
                </div>
                <div class="form-control">
                    <label class="label pb-1"><span class="label-text text-sm">{{ __('Bis') }}</span></label>
                    <input type="time" id="shift-dialog-end" name="end_time" class="input input-bordered input-sm w-full">
                </div>
            </div>

            {{-- Note --}}
            <div class="form-control mb-3">
                <label class="label pb-1"><span class="label-text text-sm">{{ __('Notiz') }}</span></label>
                <textarea id="shift-dialog-note" name="note" rows="2" maxlength="1000"
                          class="textarea textarea-bordered textarea-sm w-full resize-none"></textarea>
            </div>

            {{-- Status (edit only) --}}
            <div id="shift-dialog-status-row" class="form-control mb-4 hidden">
                <label class="label pb-1"><span class="label-text text-sm">{{ __('Status') }}</span></label>
                <select id="shift-dialog-status" name="status" class="select select-bordered select-sm w-full">
                    @foreach (\App\Models\ScheduledShift::$statuses as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div id="shift-dialog-error" class="alert alert-error alert-sm mb-3 hidden text-sm"></div>

            <div class="modal-action mt-0 flex justify-between">
                <button type="button" id="shift-dialog-delete" class="btn btn-sm btn-error btn-outline hidden">{{ __('Löschen') }}</button>
                <div class="flex gap-2">
                    <button type="button" onclick="document.getElementById('shift-dialog').close()" class="btn btn-sm btn-ghost">{{ __('Abbrechen') }}</button>
                    <button type="submit" id="shift-dialog-save" class="btn btn-sm btn-primary">{{ __('Speichern') }}</button>
                </div>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>
