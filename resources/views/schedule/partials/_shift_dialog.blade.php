{{--
  Created on   : Mon May 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _shift_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Shift create/edit dialog — native <dialog>, no Alpine.js --}}
@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\User> $users */
@endphp
<x-modal id="shift-dialog"
         :embedded="false"
         size="lg"
         tone="primary"
         icon="event"
         :eyebrow="__('Schichtplan')"
         :title="__('Schicht anlegen')"
         titleId="shift-dialog-title">

    <form id="shift-dialog-form" class="space-y-4" novalidate>
        <input type="hidden" id="shift-dialog-id" name="id" value="">

        {{-- Mitarbeiter / Datum --}}
        <x-form-group :legend="__('Zuordnung')" icon="person" tone="primary" cols="2">
            @if (auth()->user()->isAdmin())
                <div class="fieldset">
                    <label class="fieldset-label">{{ __('Mitarbeiter') }}</label>
                    <select id="shift-dialog-user" name="user_id" class="select select-bordered w-full" required>
                        @foreach ($users as $u)
                            <option value="{{ $u->sqid }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
            @else
                <input type="hidden" id="shift-dialog-user" name="user_id" value="{{ auth()->user()?->sqid }}">
            @endif

            <div class="fieldset">
                <label class="fieldset-label">{{ __('Datum') }}</label>
                <input type="date" id="shift-dialog-date" name="date" class="input input-bordered w-full" required>
            </div>
        </x-form-group>

        {{-- Schichttyp + Zeit --}}
        <x-form-group :legend="__('Schicht')" icon="schedule" tone="info">
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Schichttyp') }}</label>
                @if ($shiftTypes->isEmpty())
                    {{-- MVP-181: kein leeres Select — erklärender Setup-Hinweis. --}}
                    <div role="alert" class="alert alert-warning alert-soft text-sm" data-prerequisite="shift-types-dialog">
                        <x-icon name="settings_alert" />
                        <span>{{ __('prerequisites.shift_types.dialog_hint') }}</span>
                    </div>
                    <input type="hidden" name="shift_type_id" value="">
                @else
                    <select id="shift-dialog-type" name="shift_type_id" class="select select-bordered w-full">
                        <option value="">— {{ __('kein Typ') }} —</option>
                        @foreach ($shiftTypes as $t)
                            <option value="{{ $t->sqid }}" style="color:{{ $t->color }}">{{ $t->name }} ({{ $t->abbreviation }})</option>
                        @endforeach
                    </select>
                @endif
            </div>

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
        </x-form-group>

        {{-- Notiz + Status --}}
        <x-form-group :legend="__('Details')" icon="description" tone="ghost">
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Notiz') }}</label>
                <textarea id="shift-dialog-note" name="note" rows="2" maxlength="1000"
                          class="textarea textarea-bordered w-full resize-none"></textarea>
            </div>

            <div id="shift-dialog-status-row" class="hidden">
                <div class="fieldset">
                    <label class="fieldset-label">{{ __('Status') }}</label>
                    <select id="shift-dialog-status" name="status" class="select select-bordered w-full">
                        @foreach (\App\Enums\Shift\ScheduledShiftStatus::cases() as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </x-form-group>

        <div id="shift-dialog-error" class="alert alert-error alert-sm hidden text-sm"></div>

        <div id="shift-dialog-compliance" class="alert alert-warning alert-sm hidden flex-col items-start gap-2 text-sm">
            <div class="font-semibold">{{ __('Compliance-Hinweise') }}</div>
            <ul id="shift-dialog-compliance-list" class="list-disc list-inside space-y-1"></ul>
            <label class="cursor-pointer label justify-start gap-2 hidden" id="shift-dialog-override-row">
                <input type="checkbox" id="shift-dialog-override" class="checkbox checkbox-sm">
                <span class="label-text">{{ __('Trotzdem speichern (Override)') }}</span>
            </label>
        </div>
    </form>

    <x-slot:footerExtra>
        <x-icon-btn icon="delete" tone="error" size="sm" type="button" id="shift-dialog-delete" class="hidden" show-label>{{ __('Löschen') }}</x-icon-btn>
    </x-slot:footerExtra>

    <x-slot:actions>
        <x-icon-btn icon="publish" tone="info" size="sm" type="button" id="shift-dialog-publish" class="hidden" show-label>{{ __('Veröffentlichen') }}</x-icon-btn>
        <x-icon-btn icon="check_circle" tone="success" size="sm" type="button" id="shift-dialog-confirm" class="hidden" show-label>{{ __('Bestätigen') }}</x-icon-btn>
        <x-icon-btn icon="close" size="sm" type="button" data-entry-modal-close show-label>{{ __('Abbrechen') }}</x-icon-btn>
        <x-icon-btn icon="check" tone="primary" size="sm" type="submit" form="shift-dialog-form" id="shift-dialog-save" show-label>{{ __('Speichern') }}</x-icon-btn>
    </x-slot:actions>
</x-modal>
