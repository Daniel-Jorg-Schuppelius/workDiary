{{--
  Created on   : Mon Sep 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tool_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  LTI-Tool registrieren oder bearbeiten (Feature 149). Variable: $tool (LearningLtiTool|null)
--}}
<x-modal
    :title="$tool !== null ? __('learning.lti_registration.edit') : __('learning.lti_registration.create_tool')"
    :eyebrow="__('learning.lti_registration.tools')"
    icon="hub"
    tone="primary"
    size="lg"
    :action="$tool !== null ? route('learning.lti-registrations.tools.update', $tool) : route('learning.lti-registrations.tools.store')"
    :method="$tool !== null ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('learning.lti_registration.save')">

    <x-form-group :legend="__('learning.lti_registration.tools')" icon="hub" tone="primary" cols="2">
        <x-input-field name="name" :label="__('learning.lti_registration.name')" required minlength="2" maxlength="150" span="2" :value="old('name', $tool?->name)" />
        @if ($tool !== null)
            <x-input-field name="client_id_display" :label="__('learning.lti_registration.client_id')" :value="$tool->client_id" readonly />
            <x-input-field name="deployment_id_display" :label="__('learning.lti_registration.deployment_id')" :value="$tool->deployment_id" readonly />
        @else
            <p class="text-xs text-muted md:col-span-2">{{ __('learning.lti_registration.help_generated') }}</p>
        @endif
        <x-input-field name="login_url" type="url" :label="__('learning.lti_registration.login_url')" required maxlength="2000" span="2" :value="old('login_url', $tool?->login_url)" />
        <x-input-field name="launch_url" type="url" :label="__('learning.lti_registration.launch_url')" required maxlength="2000" span="2" :value="old('launch_url', $tool?->launch_url)" />
        <x-textarea-field name="redirect_uris" :label="__('learning.lti_registration.redirect_uris')" rows="2" span="2" required :hint="__('learning.lti_registration.help_lines')" :value="old('redirect_uris', implode(PHP_EOL, $tool?->redirect_uris ?? []))" />
        <x-input-field name="deep_linking_url" type="url" :label="__('learning.lti_registration.deep_linking_url')" maxlength="2000" span="2" :value="old('deep_linking_url', $tool?->deep_linking_url)" />
        <x-input-field name="jwks_url" type="url" :label="__('learning.lti_registration.jwks_url')" maxlength="2000" span="2" :hint="__('learning.lti_registration.help_keys')" :value="old('jwks_url', $tool?->jwks_url)" />
        <x-textarea-field name="public_jwks" :label="__('learning.lti_registration.public_jwks')" rows="3" span="2" maxlength="20000" :value="old('public_jwks', $tool?->public_jwks)" />
        <x-checkbox-field name="share_name" :label="__('learning.lti_registration.share_name')" :checked="(bool) old('share_name', $tool?->share_name)" />
        <x-checkbox-field name="share_email" :label="__('learning.lti_registration.share_email')" :checked="(bool) old('share_email', $tool?->share_email)" />
        <x-checkbox-field name="is_active" :label="__('learning.lti_registration.is_active')" :checked="(bool) old('is_active', $tool?->is_active ?? true)" />
    </x-form-group>
</x-modal>
