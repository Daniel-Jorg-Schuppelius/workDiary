{{--
  Created on   : Fri Jul 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- MVP-417: Sichtprüfung dokumentieren. Variablen: $assignableUsers, $prefillUser --}}
<x-modal
    :title="__('Führerscheinkontrolle dokumentieren')"
    :eyebrow="__('Fuhrpark')"
    icon="badge"
    tone="primary"
    :action="route('driver-license-checks.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Dokumentieren')">

    <div class="alert alert-info text-sm">
        <span>{{ __('Sichtprüfung des Original-Führerscheins — bewusst ohne Foto-Upload (Datensparsamkeit).') }}</span>
    </div>

    <x-form-group :legend="__('Kontrolle')" icon="badge" tone="primary" cols="2">
        <div class="fieldset">
            <label class="fieldset-label" for="dlc-user">{{ __('Fahrer') }} *</label>
            <select id="dlc-user" name="user_id" class="select select-bordered w-full" required>
                <option value="">{{ __('Bitte wählen') }}</option>
                @foreach ($assignableUsers as $u)
                    @php($uid = (int) ($u['id'] ?? $u->id))
                    @php($usqid = \App\Support\Sqid::encode(\App\Models\User::class, $uid))
                    <option value="{{ $usqid }}" @selected(old('user_id', $prefillUser) === $usqid)>{{ $u['name'] ?? $u->name }}</option>
                @endforeach
            </select>
            @error('user_id')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>
        <x-input-field name="checked_at" type="date" :label="__('Geprüft am')" required :value="old('checked_at', now()->format('Y-m-d'))" />
        <x-input-field name="license_classes" :label="__('Führerscheinklassen (z. B. B, BE)')" maxlength="60" :value="old('license_classes', '')" />
        <x-input-field name="license_valid_until" type="date" :label="__('Führerschein gültig bis (falls befristet)')" :value="old('license_valid_until', '')" />
        <div class="fieldset" style="grid-column: span 2;">
            <label class="fieldset-label" for="dlc-note">{{ __('Notiz') }}</label>
            <textarea id="dlc-note" name="note" rows="2" maxlength="1000" class="textarea textarea-bordered w-full">{{ old('note') }}</textarea>
        </div>
    </x-form-group>

    @if ($errors->any())
        <div class="alert alert-error text-sm">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
</x-modal>
