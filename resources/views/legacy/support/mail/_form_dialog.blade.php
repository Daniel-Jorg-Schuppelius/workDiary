{{--
  Created on   : Mon Nov 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Mail-Hinweis bei neuen Kundenaufträgen. Erwartet: $setting (LegacySupportMailSetting|null) --}}
<x-modal
    :title="__('legacy_support.mail.title')"
    :eyebrow="__('legacy_support.nav.orders')"
    icon="mail"
    tone="primary"
    :action="route('legacy.support.mail.update')"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('legacy_support.save')">

    <p class="text-sm text-base-content/70">{{ __('legacy_support.mail.intro_hint') }}</p>

    <label class="flex items-center gap-3 rounded-box border border-base-300 bg-base-200 px-3 py-2">
        <input type="hidden" name="enabled" value="0">
        <input type="checkbox" name="enabled" value="1" class="toggle toggle-sm toggle-primary" @checked((bool) old('enabled', $setting?->isEnabled() ?? false))>
        <span class="text-sm">{{ __('legacy_support.mail.enabled') }}</span>
    </label>

    <div>
        <label for="support-mail-email" class="mb-1 block text-sm font-medium">{{ __('legacy_support.field.mail_recipient') }}</label>
        <input id="support-mail-email" name="email" type="email" maxlength="{{ \App\Legacy\Models\LegacySupportMailSetting::EMAIL_MAX_LENGTH }}" value="{{ old('email', $setting?->email) }}" class="input input-bordered input-sm w-full @error('email') input-error @enderror">
        <p class="mt-1 text-xs text-muted">{{ __('legacy_support.mail.length_hint', ['max' => \App\Legacy\Models\LegacySupportMailSetting::EMAIL_MAX_LENGTH]) }}</p>
        @error('email')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
    </div>
</x-modal>
