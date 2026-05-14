{{-- Shift create/edit dialog — native <dialog>, no Alpine.js --}}
<dialog id="shift-dialog" class="modal">
    <div class="modal-box w-full max-w-lg">

        <div class="mb-4 flex items-center justify-between">
            <h3 id="shift-dialog-title" class="text-lg font-bold">{{ __('Schicht anlegen') }}</h3>
            <button type="button" onclick="document.getElementById('shift-dialog').close()" class="btn btn-sm btn-circle btn-ghost">✕</button>
        </div>

        <form id="shift-dialog-form" class="space-y-4" novalidate>
            <input type="hidden" id="shift-dialog-id" name="id" value="">

            {{-- User --}}
            @if (auth()->user()->isAdmin())
                <div class="fieldset">
                    <label class="fieldset-label">{{ __('Mitarbeiter') }}</label>
                    <select id="shift-dialog-user" name="user_id" class="select select-bordered w-full" required>
                        @foreach ($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
            @else
                <input type="hidden" id="shift-dialog-user" name="user_id" value="{{ auth()->id() }}">
            @endif

            {{-- Date --}}
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Datum') }}</label>
                <input type="date" id="shift-dialog-date" name="date" class="input input-bordered w-full" required>
            </div>

            {{-- Shift type --}}
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Schichttyp') }}</label>
                <select id="shift-dialog-type" name="shift_type_id" class="select select-bordered w-full">
                    <option value="">— {{ __('kein Typ') }} —</option>
                    @foreach ($shiftTypes as $t)
                        <option value="{{ $t->id }}" style="color:{{ $t->color }}">{{ $t->name }} ({{ $t->abbreviation }})</option>
                    @endforeach
                </select>
            </div>

            {{-- Times --}}
            <x-date-range
                type="time"
                fromName="start_time"
                toName="end_time"
                fromId="shift-dialog-start"
                toId="shift-dialog-end"
                :fromLabel="__('Von')"
                :toLabel="__('Bis')"
                :label="__('Von – Bis')"
                formControl
                class="w-full"
            />

            {{-- Note --}}
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Notiz') }}</label>
                <textarea id="shift-dialog-note" name="note" rows="2" maxlength="1000"
                          class="textarea textarea-bordered w-full resize-none"></textarea>
            </div>

            {{-- Status (edit only) --}}
            <div id="shift-dialog-status-row" class="hidden">
                <div class="fieldset">
                    <label class="fieldset-label">{{ __('Status') }}</label>
                    <select id="shift-dialog-status" name="status" class="select select-bordered w-full">
                        @foreach (\App\Models\ScheduledShift::$statuses as $status)
                            <option value="{{ $status }}">{{ (new \App\Models\ScheduledShift(['status' => $status]))->statusLabel() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div id="shift-dialog-error" class="alert alert-error alert-sm hidden text-sm"></div>

            <div id="shift-dialog-compliance" class="alert alert-warning alert-sm hidden flex-col items-start gap-2 text-sm">
                <div class="font-semibold">{{ __('Compliance-Hinweise') }}</div>
                <ul id="shift-dialog-compliance-list" class="list-disc list-inside space-y-1"></ul>
                <label class="cursor-pointer label justify-start gap-2 hidden" id="shift-dialog-override-row">
                    <input type="checkbox" id="shift-dialog-override" class="checkbox checkbox-sm">
                    <span class="label-text">{{ __('Trotzdem speichern (Override)') }}</span>
                </label>
            </div>

            <div class="modal-action mt-0 flex justify-between">
                <button type="button" id="shift-dialog-delete" class="btn btn-sm btn-error btn-outline hidden">{{ __('Löschen') }}</button>
                <div class="flex flex-wrap gap-2 justify-end">
                    <button type="button" id="shift-dialog-publish" class="btn btn-sm btn-info hidden">{{ __('Veröffentlichen') }}</button>
                    <button type="button" id="shift-dialog-confirm" class="btn btn-sm btn-success hidden">{{ __('Bestätigen') }}</button>
                    <button type="button" onclick="document.getElementById('shift-dialog').close()" class="btn btn-sm btn-ghost">{{ __('Abbrechen') }}</button>
                    <button type="submit" id="shift-dialog-save" class="btn btn-sm btn-primary">{{ __('Speichern') }}</button>
                </div>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>
