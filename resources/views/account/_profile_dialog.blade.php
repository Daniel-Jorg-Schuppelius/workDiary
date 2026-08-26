{{--
  Created on   : Sun May 03 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _profile_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@php
    $isDialog = $isDialog ?? true;
    /** @var \App\Models\User $user */
    $prefs = $user->preferences();
    $themeService = app(\App\Services\ThemeService::class);
    $builtinThemes = $themeService->builtinThemes();
    $lightThemes = array_values(array_filter($builtinThemes, fn($t) => $t['scheme'] === 'light'));
    $darkThemes = array_values(array_filter($builtinThemes, fn($t) => $t['scheme'] === 'dark'));
    $customThemes = array_map(fn($d) => $d->toPickerEntry(), $themeService->customDefinitions());
    $currentTheme = (string) (old('preferences.theme', data_get($user->preferences, 'theme')) ?? '');
    $startpages = (array) config('personalization.startpages', []);
    $avatarMaxKb = (int) config('branding.limits.avatar_kb', 1024);
    $currentAvatar = $user->avatar();
    $avatarPreview = $currentAvatar !== null
        ? \App\Http\Controllers\AttachmentController::downloadUrl($currentAvatar)
        : null;
@endphp

<x-modal
    :title="__('Profil')"
    :eyebrow="__('Konto')"
    icon="person"
    tone="info"
    :action="route('account.profile.update')"
    method="PUT"
    enctype="multipart/form-data"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ route('account.profile.edit') }}?dialog=1">
    @endif

    <x-form-group :legend="__('Profildaten')" icon="person" tone="info" cols="2">
        <div class="fieldset">
            <label class="fieldset-label" for="name">{{ __('Anzeigename') }}</label>
            <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" class="input input-bordered w-full" required>
            @error('name')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="email">{{ __('E-Mail') }}</label>
            <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" class="input input-bordered w-full" required>
            @error('email')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>
    </x-form-group>

    @include('users._contact_fields', ['user' => $user])

    <x-form-group :legend="__('Profilbild')" icon="account_circle" tone="primary" cols="1"
                  :description="__('PNG, JPG oder WEBP. Max. :max KB.', ['max' => $avatarMaxKb])"
                  x-data="fileUpload({{ $avatarMaxKb }}, @js(__('Datei ist größer als das Limit.')))">
        <div class="flex items-start gap-4">
            <div class="shrink-0">
                @if ($avatarPreview)
                    <img src="{{ $avatarPreview }}" alt="{{ __('Vorschau') }}"
                         class="w-20 h-20 rounded-full object-cover ring ring-base-300">
                @else
                    <div class="w-20 h-20 rounded-full bg-base-200 flex items-center justify-center text-muted">
                        <x-icon name="person" />
                    </div>
                @endif
            </div>

            <div class="flex-1 space-y-2">
                <input type="file" name="avatar"
                       accept="image/png,image/jpeg,image/webp"
                       class="file-input file-input-bordered file-input-sm w-full"
                       @change="onChange($event)">
                <template x-if="fileName">
                    <p class="text-xs opacity-70" x-text="fileName"></p>
                </template>
                <p class="text-xs text-error" x-show="error" x-text="error"></p>
                @error('avatar')<p class="text-xs text-error">{{ $message }}</p>@enderror

                @if ($currentAvatar)
                    <label class="label cursor-pointer justify-start gap-2 py-1">
                        <input type="hidden" name="remove_avatar" value="0">
                        <input type="checkbox" name="remove_avatar" value="1" class="checkbox checkbox-sm checkbox-error"
                               x-model="remove">
                        <span class="label-text text-sm">{{ __('Aktuelles Bild beim Speichern entfernen') }}</span>
                    </label>
                @endif
            </div>
        </div>
    </x-form-group>

    <x-form-group :legend="__('Persönliche Präferenzen')" icon="tune" tone="ghost" cols="2">
        <x-select-field name="preferences[theme]" :label="__('Theme')">
            <option value="">{{ __('Standard (Organisation)') }}</option>
            <option value="auto" @selected($currentTheme === 'auto')>{{ __('Automatisch – folgt System (hell/dunkel)') }}</option>
            <optgroup label="{{ __('Hell') }}">
                @foreach ($lightThemes as $t)
                    <option value="{{ $t['key'] }}" @selected($currentTheme === $t['key'])>{{ $t['label'] }}</option>
                @endforeach
            </optgroup>
            <optgroup label="{{ __('Dunkel') }}">
                @foreach ($darkThemes as $t)
                    <option value="{{ $t['key'] }}" @selected($currentTheme === $t['key'])>{{ $t['label'] }}</option>
                @endforeach
            </optgroup>
            @if ($customThemes !== [])
                <optgroup label="{{ __('Eigene Themes') }}">
                    @foreach ($customThemes as $t)
                        <option value="{{ $t['key'] }}" @selected($currentTheme === $t['key'])>{{ $t['label'] }} ({{ $t['scheme'] === 'dark' ? __('Dunkel') : __('Hell') }})</option>
                    @endforeach
                </optgroup>
            @endif
        </x-select-field>

        <div class="fieldset">
            <label for="preferences-locale" class="fieldset-label">{{ __('Sprache') }}</label>
            <x-locale-select id="preferences-locale" name="preferences[locale]"
                             :selected="old('preferences.locale', $prefs['locale'] ?? null)"
                             include-blank :blank-label="__('Organisation übernehmen')" />
            @error('preferences.locale')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label for="preferences-timezone" class="fieldset-label">{{ __('Zeitzone') }}</label>
            <x-timezone-select id="preferences-timezone" name="preferences[timezone]"
                               :selected="old('preferences.timezone', $prefs['timezone'] ?? null)"
                               include-blank :blank-label="__('Organisation übernehmen')" />
            <p class="mt-1 text-xs text-muted">{{ __('Überschreibt die Zeitzone der Organisation für deine Ansichten.') }}</p>
            @error('preferences.timezone')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label for="preferences-date_format" class="fieldset-label">{{ __('Datumsformat') }}</label>
            <x-format-select id="preferences-date_format" type="date" name="preferences[date_format]"
                             :selected="old('preferences.date_format', data_get($user->preferences, 'date_format'))"
                             include-blank :blank-label="__('Organisation übernehmen')" />
            @error('preferences.date_format')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label for="preferences-time_format" class="fieldset-label">{{ __('Uhrzeitformat') }}</label>
            <x-format-select id="preferences-time_format" type="time" name="preferences[time_format]"
                             :selected="old('preferences.time_format', data_get($user->preferences, 'time_format'))"
                             include-blank :blank-label="__('Organisation übernehmen')" />
            @error('preferences.time_format')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <x-select-field span="2" name="preferences[startpage]" :label="__('Startseite nach dem Login')">
            <option value="">{{ __('Standard') }}</option>
            @foreach ($startpages as $route)
                <option value="{{ $route }}" @selected(old('preferences.startpage', $prefs['startpage'] ?? '') === $route)>{{ $route }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>

    @php
        $notifPrefs = (array) ($prefs['notifications'] ?? []);
    @endphp
    <x-form-group :legend="__('notification.title.preferences')" icon="notifications" tone="info" cols="2"
                  :description="__('notification.field.preferences_help')">
        <div class="fieldset md:col-span-2">
            <label class="label cursor-pointer justify-start gap-3">
                <input type="hidden" name="preferences[notifications][mail_enabled]" value="0">
                <input type="checkbox" name="preferences[notifications][mail_enabled]" value="1" class="toggle toggle-primary"
                       @checked(filter_var(old('preferences.notifications.mail_enabled', $notifPrefs['mail_enabled'] ?? true), FILTER_VALIDATE_BOOL))>
                <span class="label-text">{{ __('notification.field.mail_enabled') }}</span>
            </label>
            @error('preferences.notifications.mail_enabled')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <x-input-field name="preferences[notifications][quiet_from]"
                       :label="__('notification.field.quiet_from')"
                       type="time"
                       value="{{ old('preferences.notifications.quiet_from', $notifPrefs['quiet_from'] ?? '') }}" />

        <x-input-field name="preferences[notifications][quiet_to]"
                       :label="__('notification.field.quiet_to')"
                       type="time"
                       value="{{ old('preferences.notifications.quiet_to', $notifPrefs['quiet_to'] ?? '') }}" />
    </x-form-group>

    {{-- CTI-Anrufer-Pop-up (MVP-118): eigene Durchwahl als Opt-in. --}}
    <x-form-group :legend="__('cti.profile.heading')" icon="ring_volume" tone="info"
                  :description="__('cti.profile.extension_help')">
        <x-input-field name="cti_extension"
                       :label="__('cti.profile.extension_label')"
                       type="tel"
                       value="{{ old('cti_extension', $user->cti_extension) }}"
                       placeholder="{{ __('cti.profile.extension_placeholder') }}"
                       autocomplete="off" />
    </x-form-group>

    <x-slot:footerExtra>
        <x-icon-btn icon="lock" size="sm"
                    data-entry-modal-trigger
                    :href="route('account.password.edit')"
                    show-label>{{ __('Passwort ändern') }}</x-icon-btn>
        {{-- Alarm-SMS (Feature 147): eigene Seite, weil Bestätigungscode und
             Widerruf eigene Formulare brauchen — nicht schachtelbar. --}}
        <x-icon-btn icon="sms" size="sm"
                    :href="route('account.sms.index')"
                    show-label>{{ __('sms.section') }}</x-icon-btn>
    </x-slot:footerExtra>
</x-modal>
