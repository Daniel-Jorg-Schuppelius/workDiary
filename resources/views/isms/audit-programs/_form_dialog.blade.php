{{--
  Created on   : Mon Aug 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Auditprogramm anlegen (Nachtrag 044d). --}}
@php
    /** @var \Illuminate\Support\Collection $scopes */
@endphp
<x-modal
    :title="__('Programm anlegen')"
    :eyebrow="__('Auditprogramme')"
    icon="event_repeat"
    tone="primary"
    :action="route('isms.audit-programs.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Programm anlegen')">

    <x-form-group :legend="__('Auditprogramm')" icon="event_repeat" tone="primary" cols="3" compact>
        <div class="fieldset md:col-span-2">
            <label class="fieldset-label" for="ap-name">{{ __('Name') }}</label>
            <input id="ap-name" name="name" required minlength="3" maxlength="180" class="input input-bordered w-full"
                   placeholder="{{ __('z. B. ISO-27001-Zyklus 2026–2028') }}" value="{{ old('name') }}">
            @error('name')<p class="text-sm text-error">{{ $message }}</p>@enderror
        </div>
        <div class="fieldset">
            <label class="fieldset-label" for="ap-scope">{{ __('Geltungsbereich') }}</label>
            <select id="ap-scope" name="isms_scope_id" class="select select-bordered w-full" required>
                @foreach ($scopes as $scopeOption)
                    <option value="{{ $scopeOption->sqid }}">{{ $scopeOption->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="fieldset">
            <label class="fieldset-label" for="ap-norm">{{ __('Norm') }}</label>
            <input id="ap-norm" name="norm" maxlength="64" class="input input-bordered w-full" placeholder="ISO/IEC 27001" value="{{ old('norm') }}">
        </div>
        <div class="fieldset">
            <label class="fieldset-label" for="ap-edition">{{ __('Ausgabe') }}</label>
            <input id="ap-edition" name="edition" maxlength="16" class="input input-bordered w-full" placeholder="2022" value="{{ old('edition') }}">
        </div>
        <div class="fieldset">
            <label class="fieldset-label" for="ap-cycle">{{ __('Zyklus (Jahre)') }}</label>
            <input id="ap-cycle" name="cycle_years" type="number" min="1" max="6" value="{{ old('cycle_years', 3) }}" class="input input-bordered w-full" required>
        </div>
        <div class="fieldset">
            <label class="fieldset-label" for="ap-start">{{ __('Beginn') }}</label>
            <input id="ap-start" name="starts_on" type="date" value="{{ old('starts_on', now()->format('Y-m-d')) }}" class="input input-bordered w-full" required>
        </div>
        <div class="fieldset md:col-span-2">
            <label class="fieldset-label" for="ap-notes">{{ __('Notizen') }}</label>
            <input id="ap-notes" name="notes" maxlength="5000" class="input input-bordered w-full" value="{{ old('notes') }}">
        </div>
    </x-form-group>
</x-modal>
