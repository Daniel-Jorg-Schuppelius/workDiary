{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _lockdown_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Notaus der Organisation auslösen (Feature 205, MVP-1657) — Grund und erneute Bestätigung. --}}
<x-modal
    :title="__('login_policy.title.lockdown')"
    icon="emergency_home"
    tone="error"
    :action="route('admin.login-policy.lockdown.store')"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('login_policy.action.engage')"
    submit-class="btn-error"
>
    <div class="alert alert-warning bg-warning/10 border-warning/30 text-sm" role="note">
        <x-icon name="warning" />
        <span>{{ __('login_policy.lockdown.effect') }}</span>
    </div>

    <x-textarea-field name="reason" :label="__('login_policy.field.reason')" rows="3" required
                      :hint="__('login_policy.hint.reason')" />

    @if ($needsPassword)
        <x-input-field name="current_password" type="password" :label="__('login_policy.field.password')" required
                       autocomplete="current-password" :hint="__('login_policy.hint.password')" />
    @endif
</x-modal>
