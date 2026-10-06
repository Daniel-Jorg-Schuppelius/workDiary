{{--
  Created on   : Mon Oct 05 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _career_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Stelle im Karrierebereich (erneut) veröffentlichen (MVP-437).
     Nur die öffentlichen Inhaltsfelder — nie Budget oder internes Profil. --}}
<x-modal
    :title="__('Veröffentlichen')"
    :eyebrow="__('Karriere')"
    icon="public"
    tone="primary"
    :action="route('recruiting.requisitions.career.publish', $requisition)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Veröffentlichen')"
>
    <x-form-group :legend="__('Karriere')" icon="public" tone="primary" cols="2"
                  :description="__('Die Stelle wird öffentlich im Karrierebereich sichtbar und ist ohne Anmeldung bewerbbar.')">
        <x-input-field name="public_title" :label="__('validation.attributes.public_title')" required maxlength="200" span="2" :value="old('public_title', $posting->public_title ?? $requisition->title)" />
        <x-input-field name="public_summary" :label="__('validation.attributes.public_summary')" maxlength="500" span="2" :value="old('public_summary', $posting->public_summary ?? '')" />
        <x-textarea-field name="public_description" :label="__('validation.attributes.public_description')" rows="4" span="2">{{ old('public_description', $posting->public_description ?? '') }}</x-textarea-field>
        <x-textarea-field name="public_tasks" :label="__('validation.attributes.public_tasks')" rows="3" span="2">{{ old('public_tasks', $posting->public_tasks ?? '') }}</x-textarea-field>
        <x-textarea-field name="public_requirements" :label="__('validation.attributes.public_requirements')" rows="3" span="2">{{ old('public_requirements', $posting->public_requirements ?? '') }}</x-textarea-field>
        <x-textarea-field name="public_benefits" :label="__('validation.attributes.public_benefits')" rows="3" span="2">{{ old('public_benefits', $posting->public_benefits ?? '') }}</x-textarea-field>
        <x-input-field name="work_location" :label="__('validation.attributes.work_location')" maxlength="200" span="2" :value="old('work_location', $posting->work_location ?? '')" />
        <x-input-field name="application_deadline" type="date" :label="__('validation.attributes.application_deadline')" :value="old('application_deadline', optional($posting->application_deadline)->toDateString())" />
        <x-input-field name="expires_at" type="date" :label="__('validation.attributes.expires_at')" :value="old('expires_at', optional($posting->expires_at)->toDateString())" />
    </x-form-group>

    <x-validation-errors />
</x-modal>
