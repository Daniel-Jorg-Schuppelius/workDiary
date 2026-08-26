{{--
  Created on   : Sun May 03 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_fields.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Inhalt der Legacy-Diary Form. Erwartet: $entry, $isEdit, $isAdmin, $users --}}
@if (!empty($isAdmin) && $isAdmin)
    <div>
        <label for="user" class="mb-2 block text-sm font-medium text-base-content">{{ __('Mitarbeiter') }}</label>
        <select id="user" name="user" class="select select-bordered select-sm w-full @error('user') ring-2 ring-error/30 @enderror">
            @foreach (($users ?? collect()) as $user)
                <option value="{{ \App\Support\Sqid::encode(\App\Legacy\Models\LegacyUser::class, $user->id) }}" @selected((string) old('user', \App\Support\Sqid::encode(\App\Legacy\Models\LegacyUser::class, $entry?->user)) === \App\Support\Sqid::encode(\App\Legacy\Models\LegacyUser::class, $user->id))>{{ $user->uname }}</option>
            @endforeach
        </select>
        @error('user')
            <p class="mt-2 text-sm text-error">{{ $message }}</p>
        @enderror
    </div>
@endif

<div>
    <label for="inhalt" class="mb-2 block text-sm font-medium text-base-content">{{ __('Inhalt') }}<span class="text-error">*</span></label>
    <textarea id="inhalt" name="inhalt" rows="8" class="textarea textarea-bordered textarea-sm w-full @error('inhalt') ring-2 ring-error/30 @enderror" placeholder="{{ __('Beschreibe den Vorgang...') }}">{{ old('inhalt', $entry?->inhalt) }}</textarea>
    @error('inhalt')
        <p class="mt-2 text-sm text-error">{{ $message }}</p>
    @enderror
</div>

<div>
    <label for="antwort" class="mb-2 block text-sm font-medium text-base-content">{{ __('Rückmeldung') }}</label>
    <textarea id="antwort" name="antwort" rows="4" class="textarea textarea-bordered textarea-sm w-full">{{ old('antwort', $entry?->antwort) }}</textarea>
</div>

<div>
    <label for="gelesen" class="mb-2 block text-sm font-medium text-base-content">{{ __('Status') }}<span class="text-error">*</span></label>
    <select id="gelesen" name="gelesen" class="select select-bordered select-sm w-full @error('gelesen') ring-2 ring-error/30 @enderror">
        <option value="2" @selected(old('gelesen', $entry?->gelesen ?? 2) == 2)>{{ __('Offen') }}</option>
        <option value="3" @selected(old('gelesen', $entry?->gelesen) == 3)>{{ __('Problem') }}</option>
        <option value="1" @selected(old('gelesen', $entry?->gelesen) == 1)>{{ __('Bestätigt') }}</option>
        <option value="-1" @selected(old('gelesen', $entry?->gelesen) == -1)>{{ __('Erledigt') }}</option>
    </select>
    @error('gelesen')
        <p class="mt-2 text-sm text-error">{{ $message }}</p>
    @enderror
</div>

<div class="fieldset">
    <span class="fieldset-label">{{ __('Zeitraum') }}</span>
    <x-date-range
        type="datetime-local"
        fromName="von"
        toName="bis"
        fromId="von"
        toId="bis"
        :from="old('von', $entry?->von?->format('Y-m-d\TH:i'))"
        :to="old('bis', $entry?->bis?->format('Y-m-d\TH:i'))"
        :label="false"
        class="w-full"
    />
    @error('von')<p class="text-error text-sm">{{ $message }}</p>@enderror
    @error('bis')<p class="text-error text-sm">{{ $message }}</p>@enderror
</div>

<div class="flex items-center gap-3 rounded-box border border-base-300 bg-base-200 px-3 py-2">
    <input id="sms" name="sms" type="checkbox" value="j" @checked(old('sms', $entry?->sms) === 'j') class="checkbox checkbox-sm">
    <label for="sms" class="text-sm">{{ __('E-Mail-Hinweis senden') }}</label>
</div>

<div class="rounded-box border border-base-300 bg-base-200/60 p-4 text-xs text-base-content/70">
    {{ __('Felder mit') }} <span class="text-error">*</span> {{ __('sind erforderlich.') }}
</div>
