{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Supportfreigabe erteilen (Rang 64) --}}
<x-modal
    :title="__('Supportfreigabe erteilen')"
    :eyebrow="__('Supportzugriff')"
    icon="support_agent"
    tone="warning"
    :action="route('admin.support.grants.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Freigabe erteilen')"
>
    <x-form-group :legend="__('Freigabe')" icon="support_agent" tone="warning" cols="2">
        <div class="fieldset">
            <label class="fieldset-label" for="sag-scope">{{ __('Umfang') }}</label>
            <select id="sag-scope" name="scope" class="select select-bordered w-full" required>
                <option value="read_only" @selected(old('scope', 'read_only') === 'read_only')>{{ __('Nur lesend') }}</option>
                <option value="full" @selected(old('scope') === 'full')>{{ __('Vollzugriff (schreibend)') }}</option>
            </select>
            <p class="text-xs text-base-content/60">{{ __('Passwort-/2FA-Änderungen, API-Tokens und Exporte bleiben in jedem Fall gesperrt.') }}</p>
            @error('scope')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="sag-duration">{{ __('Gültigkeitsdauer') }}</label>
            <select id="sag-duration" name="duration_hours" class="select select-bordered w-full" required>
                <option value="4" @selected(old('duration_hours') === '4')>{{ __(':n Stunden', ['n' => 4]) }}</option>
                <option value="24" @selected(old('duration_hours', '24') === '24')>{{ __(':n Stunden', ['n' => 24]) }}</option>
                <option value="72" @selected(old('duration_hours') === '72')>{{ __(':n Tage', ['n' => 3]) }}</option>
                <option value="168" @selected(old('duration_hours') === '168')>{{ __(':n Tage', ['n' => 7]) }}</option>
            </select>
            @error('duration_hours')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset md:col-span-2">
            <label class="fieldset-label" for="sag-purpose">{{ __('Zweck / Ticket-Referenz') }}</label>
            <input id="sag-purpose" name="purpose" type="text" maxlength="300" required
                   class="input input-bordered w-full"
                   placeholder="{{ __('z. B. Ticket #4711 — Fehleranalyse Zeiterfassung') }}"
                   value="{{ old('purpose') }}">
            @error('purpose')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        @if ($supportUsers->isNotEmpty())
            <div class="fieldset md:col-span-2">
                <label class="fieldset-label" for="sag-granted-to">{{ __('Beschränken auf Support-Konto (optional)') }}</label>
                <select id="sag-granted-to" name="granted_to_user_id" class="select select-bordered w-full">
                    <option value="">{{ __('Alle Support-Konten') }}</option>
                    @foreach ($supportUsers as $supportUser)
                        <option value="{{ $supportUser->sqid }}" @selected((string) old('granted_to_user_id') === $supportUser->sqid)>{{ $supportUser->name }}</option>
                    @endforeach
                </select>
                @error('granted_to_user_id')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            </div>
        @endif
    </x-form-group>
</x-modal>
