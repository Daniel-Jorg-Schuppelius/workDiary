{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _privacy_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Datenschutz der KI-Dienste (Feature 025, MVP-1671/1672) — Freigabepflicht, Frist, Maskierung lokaler Verbindungen. --}}
<x-modal
    :title="__('ai.privacy.title')"
    :eyebrow="__('ai.title.connections')"
    icon="shield_lock"
    tone="primary"
    :action="route('admin.ai.privacy.update')"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')"
>
    <x-form-group :legend="__('ai.privacy.legend_approval')" icon="verified_user" tone="primary" cols="1" :description="__('ai.privacy.description_approval')">
        <x-checkbox-field name="require_approval" :label="__('ai.privacy.field.require_approval')"
                          :checked="(bool) old('require_approval', $requireApproval)"
                          :hint="__('ai.privacy.field.require_approval_help')" />
        <x-input-field name="approval_months" id="ai-privacy-months" type="number" min="1" max="60" step="1" inputmode="numeric"
                       :label="__('ai.privacy.field.approval_months')"
                       :value="old('approval_months', $approvalMonths)"
                       :hint="__('ai.privacy.field.approval_months_help', ['days' => \App\Services\Ai\AiConnectionPrivacy::REMINDER_DAYS])" />
    </x-form-group>

    <x-form-group :legend="__('ai.privacy.legend_masking')" icon="visibility_off" tone="primary" cols="1" :description="__('ai.privacy.description_masking')">
        <x-checkbox-field name="mask_local" :label="__('ai.privacy.field.mask_local')"
                          :checked="(bool) old('mask_local', $maskLocal)"
                          :hint="__('ai.privacy.field.mask_local_help')" />
    </x-form-group>
</x-modal>
