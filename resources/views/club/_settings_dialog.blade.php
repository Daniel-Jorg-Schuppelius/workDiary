{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _settings_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Vereinseinstellungen (MVP-846): optionales Graduierungsmodul je Organisation, Freistellungsdaten (MVP-1003).
     Variablen: $graduationEnabled, $feeNoticeFooter, $exemption, $feesConfirmable --}}
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

    <x-form-group :legend="__('club.donations.settings.legend')" icon="volunteer_activism" tone="primary" cols="2" :description="__('club.donations.settings.hint')">
        <x-select-field name="donations[exemption_kind]" :label="__('club.donations.settings.exemption_kind')" span="2">
            <option value="">—</option>
            @foreach (\App\Services\Club\ClubDonationService::EXEMPTION_KINDS as $kind)
                <option value="{{ $kind }}" @selected(old('donations.exemption_kind', $exemption['exemption_kind']) === $kind)>{{ __('club.donations.settings.kind.' . $kind) }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="donations[tax_office]" :label="__('club.donations.settings.tax_office')" maxlength="120" :value="old('donations.tax_office', $exemption['tax_office'])" />
        <x-input-field name="donations[tax_number]" :label="__('club.donations.settings.tax_number')" maxlength="40" :value="old('donations.tax_number', $exemption['tax_number'])" />
        <x-input-field name="donations[notice_date]" type="date" :label="__('club.donations.settings.notice_date')" :value="old('donations.notice_date', $exemption['notice_date'])" />
        <x-input-field name="donations[assessment_period]" :label="__('club.donations.settings.assessment_period')" maxlength="40" :hint="__('club.donations.settings.assessment_period_hint')" :value="old('donations.assessment_period', $exemption['assessment_period'])" />
        <x-textarea-field name="donations[purpose]" :label="__('club.donations.settings.purpose')" rows="2" maxlength="500" span="2" :hint="__('club.donations.settings.purpose_hint')" :value="old('donations.purpose', $exemption['purpose'])" />
        <x-input-field name="donations[signatory]" :label="__('club.donations.settings.signatory')" maxlength="120" span="2" :value="old('donations.signatory', $exemption['signatory'])" />
        <x-checkbox-field name="donations[membership_fees_confirmable]" span="2" :label="__('club.donations.settings.membership_fees_confirmable')"
                          :hint="__('club.donations.settings.membership_fees_hint')" :checked="(bool) old('donations.membership_fees_confirmable', $feesConfirmable)" />
    </x-form-group>
</x-modal>
