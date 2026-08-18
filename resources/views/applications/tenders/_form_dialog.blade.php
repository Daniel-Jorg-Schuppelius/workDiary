{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Ausschreibungsakte anlegen/bearbeiten (Feature 068, MVP-184) --}}
@php $isEdit = $opportunity->exists; @endphp
<x-modal
    :title="$isEdit ? __('Akte bearbeiten') : __('Ausschreibung erfassen')"
    :eyebrow="__('Auftragsbewerbung')"
    icon="gavel"
    tone="primary"
    :action="$isEdit ? route('tenders.update', $opportunity) : route('tenders.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')"
>
    <x-form-group :legend="__('Akte')" icon="gavel" tone="primary" cols="2">
        <x-input-field name="title" :label="__('Titel')" required maxlength="200" span="2" :value="old('title', $opportunity->title ?? '')" />
        <x-select-field name="kind" :label="__('Art')" required>
            @foreach (\App\Models\Applications\ApplicationOpportunity::KINDS as $kind)
                <option value="{{ $kind }}" @selected(old('kind', $opportunity->kind ?? 'tender') === $kind)>{{ __("values.$kind") }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="source" :label="__('Quelle (Portal/Medium)')" maxlength="200" :value="old('source', $opportunity->source ?? '')" />
        <x-select-field name="customer_id" :label="__('Kunde (optional)')" span="2">
            <option value="">{{ __('— ohne Kundenbezug —') }}</option>
            @foreach ($customers as $c)
                <option value="{{ $c->sqid }}" @selected(old('customer_id', $opportunity->customer_id !== null ? \App\Support\Sqid::encode(\App\Models\Customer::class, $opportunity->customer_id) : '') === $c->sqid)>{{ $c->name }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="responsible_user_id" :label="__('Verantwortlich')" span="2">
            <option value="">{{ __('— offen —') }}</option>
            @foreach ($users as $u)
                <option value="{{ $u->sqid }}" @selected(old('responsible_user_id', $opportunity->responsible_user_id !== null ? \App\Support\Sqid::encode(\App\Models\User::class, $opportunity->responsible_user_id) : '') === $u->sqid)>{{ $u->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="question_deadline" type="date" :label="__('Rückfragefrist')" :value="old('question_deadline', optional($opportunity->question_deadline)->toDateString())" />
        <x-input-field name="submission_deadline" type="date" :label="__('Abgabefrist')" :value="old('submission_deadline', optional($opportunity->submission_deadline)->toDateString())" />
        <x-input-field name="decision_expected_on" type="date" :label="__('Entscheidung erwartet')" :value="old('decision_expected_on', optional($opportunity->decision_expected_on)->toDateString())" />
        <x-input-field name="estimated_value" type="number" step="0.01" min="0" :label="__('Wertpotenzial (EUR)')" :value="old('estimated_value', $opportunity->estimated_value ?? '')" />
        <x-input-field name="probability" type="number" min="0" max="100" :label="__('Erfolgswahrscheinlichkeit (%)')" :value="old('probability', $opportunity->probability ?? '')" />
        <x-textarea-field name="risk_note" :label="__('Risikobewertung')" rows="2" span="2">{{ old('risk_note', $opportunity->risk_note ?? '') }}</x-textarea-field>
        <x-textarea-field name="description" :label="__('Beschreibung')" rows="3" span="2">{{ old('description', $opportunity->description ?? '') }}</x-textarea-field>
    </x-form-group>

    {{-- Vergabevorgang (MVP-625): nur bei öffentlichen Ausschreibungen relevant. --}}
    <x-form-group :legend="__('Vergabeverfahren')" icon="gavel" cols="2"
                  :description="__('Nur bei öffentlichen Ausschreibungen auszufüllen.')">
        <x-input-field name="awarding_body" :label="__('Vergabestelle')" span="2"
                       :hint="__('Wer ausschreibt — nicht zwingend der Kunde im CRM.')"
                       :value="old('awarding_body', $opportunity->awarding_body ?? '')" />
        <x-input-field name="procedure_no" :label="__('Vergabenummer')" :value="old('procedure_no', $opportunity->procedure_no ?? '')" />
        <x-select-field name="above_threshold" :label="__('Schwellenwertlage')">
            <option value="0" @selected(! old('above_threshold', $opportunity->above_threshold ?? false))>{{ __('Unterschwellig (VOB/A, UVgO)') }}</option>
            <option value="1" @selected((bool) old('above_threshold', $opportunity->above_threshold ?? false))>{{ __('Oberschwellig (VgV, VOB/A-EU)') }}</option>
        </x-select-field>
        <x-select-field name="procedure_type" :label="__('Verfahrensart')" span="2">
            <option value="">{{ __('— offen —') }}</option>
            @foreach (\App\Enums\Applications\TenderProcedureType::cases() as $type)
                <option value="{{ $type->value }}"
                        data-threshold="{{ $type->isAboveThreshold() ? '1' : '0' }}"
                        @selected(old('procedure_type', $opportunity->procedure_type?->value) === $type->value)>
                    {{ $type->label() }}{{ $type->toGaeb() === null ? ' · ' . __('nicht in GAEB') : '' }}
                </option>
            @endforeach
        </x-select-field>
        <x-input-field name="lot_no" :label="__('Los')" :value="old('lot_no', $opportunity->lot_no ?? '')" />
        <x-input-field name="lot_group" :label="__('Losgruppe')" :value="old('lot_group', $opportunity->lot_group ?? '')" />
        <x-input-field name="cpv_codes" :label="__('CPV-Codes')" span="2"
                       :hint="__('Achtstellig, mehrere durch Komma getrennt — danach wird in Bekanntmachungen gesucht.')"
                       :value="old('cpv_codes', implode(', ', $opportunity->cpv_codes ?? []))" />
        <x-input-field name="nuts_code" :label="__('NUTS-Region')" :value="old('nuts_code', $opportunity->nuts_code ?? '')" />
        <x-input-field name="platform" :label="__('Vergabeplattform')" :value="old('platform', $opportunity->platform ?? '')" />
        <x-input-field name="external_reference" :label="__('Externe Referenz')" :value="old('external_reference', $opportunity->external_reference ?? '')" />
        <x-input-field name="notice_url" type="url" :label="__('Bekanntmachung (URL)')" span="2" :value="old('notice_url', $opportunity->notice_url ?? '')" />
        <x-input-field name="participation_deadline" type="date" :label="__('Teilnahmefrist')" :value="old('participation_deadline', optional($opportunity->participation_deadline)->toDateString())" />
        <x-input-field name="opening_at" type="datetime-local" :label="__('Eröffnungstermin')"
                       :value="old('opening_at', optional($opportunity->opening_at)->format('Y-m-d\TH:i'))" />
        <x-input-field name="binding_until" type="date" :label="__('Bindefrist')"
                       :hint="__('Bis wann das Angebot bindet — danach ist der Bieter frei.')"
                       :value="old('binding_until', optional($opportunity->binding_until)->toDateString())" />
    </x-form-group>

    <x-validation-errors />
</x-modal>
