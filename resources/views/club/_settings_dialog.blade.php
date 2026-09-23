{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _settings_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Vereinseinstellungen (MVP-846): optionales Graduierungsmodul je Organisation. Variablen: $graduationEnabled --}}
<x-modal
    :title="__('club.grading.action.settings')"
    :eyebrow="__('club.section')"
    icon="settings"
    tone="primary"
    :action="route('club.settings.update')"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.save')">

    <x-form-group :legend="__('club.grading.title.index')" icon="military_tech" tone="primary" cols="1" :description="__('club.grading.hint.settings')">
        <x-checkbox-field name="graduation_enabled" :label="__('club.grading.field.graduation_enabled')" :checked="(bool) old('graduation_enabled', $graduationEnabled)" />
    </x-form-group>

    <x-form-group :legend="__('club.fees.title.claims')" icon="receipt_long" tone="primary" cols="1" :description="__('club.fees.hint.notice_footer')">
        <x-textarea-field name="fee_notice_footer" :label="__('club.fees.field.notice_footer')" rows="3" maxlength="2000" :value="old('fee_notice_footer', $feeNoticeFooter)" />
    </x-form-group>
</x-modal>
