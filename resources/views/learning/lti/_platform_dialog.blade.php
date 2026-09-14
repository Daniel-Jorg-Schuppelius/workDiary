{{--
  Created on   : Mon Sep 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _platform_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  LTI-Plattform registrieren oder bearbeiten (Feature 149). Variable: $platform (LearningLtiPlatform|null)
--}}
<x-modal
    :title="$platform !== null ? __('learning.lti_registration.edit') : __('learning.lti_registration.create_platform')"
    :eyebrow="__('learning.lti_registration.platforms')"
    icon="hub"
    tone="primary"
    size="lg"
    :action="$platform !== null ? route('learning.lti-registrations.platforms.update', $platform) : route('learning.lti-registrations.platforms.store')"
    :method="$platform !== null ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('learning.lti_registration.save')">

    <x-form-group :legend="__('learning.lti_registration.platforms')" icon="hub" tone="primary" cols="2">
        <x-input-field name="name" :label="__('learning.lti_registration.name')" required minlength="2" maxlength="150" span="2" :value="old('name', $platform?->name)" />
        <x-input-field name="issuer" type="url" :label="__('learning.lti_registration.issuer')" required maxlength="500" span="2" :value="old('issuer', $platform?->issuer)" />
        <x-input-field name="client_id" :label="__('learning.lti_registration.client_id')" required maxlength="255" span="2" :value="old('client_id', $platform?->client_id)" />
        <x-textarea-field name="deployment_ids" :label="__('learning.lti_registration.deployment_ids')" rows="2" span="2" required :hint="__('learning.lti_registration.help_lines')" :value="old('deployment_ids', implode(PHP_EOL, $platform?->deployment_ids ?? []))" />
        <x-input-field name="authorization_endpoint" type="url" :label="__('learning.lti_registration.authorization_endpoint')" required maxlength="2000" span="2" :value="old('authorization_endpoint', $platform?->authorization_endpoint)" />
        <x-input-field name="jwks_url" type="url" :label="__('learning.lti_registration.jwks_url')" required maxlength="2000" span="2" :value="old('jwks_url', $platform?->jwks_url)" />
        <x-checkbox-field name="is_active" :label="__('learning.lti_registration.is_active')" :checked="(bool) old('is_active', $platform?->is_active ?? true)" />
    </x-form-group>
</x-modal>
