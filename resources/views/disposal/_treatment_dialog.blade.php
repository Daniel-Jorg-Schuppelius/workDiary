{{-- Dialog: Datenträger-Behandlung dokumentieren (Feature 100, MVP-475).
     Erwartet: $item (DisposalItem), $users. Lokaler ad-hoc-Dialog je Position.
     treated_at als datetime-local: die Behandlung ist ein echter Zeitstempel
     ohne beherrschendes Einzeldatum (AGENTS §4.14, Ausnahmefall). --}}
@php
    $dialogId = 'disposal-treatment-' . $item->id;
@endphp
<x-modal
    :id="$dialogId"
    :embedded="false"
    :title="__('disposal.treatment.title_create')"
    :eyebrow="__('disposal.eyebrow')"
    icon="hard_drive"
    tone="primary"
    :badge="$item->category"
    :action="route('disposal.treatments.store', $item)"
    method="POST"
    :submit-label="__('Erfassen')"
>
    <x-form-group :legend="__('disposal.treatment.group_method')" icon="hard_drive" tone="primary" cols="2">
        <x-select-field name="media_type" :label="__('disposal.treatment.media_type')" required>
            <option value="">{{ __('disposal.treatment.please_select') }}</option>
            @foreach (\App\Enums\Disposal\DataMediumType::options() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="method" :label="__('disposal.treatment.method')" required>
            <option value="">{{ __('disposal.treatment.please_select') }}</option>
            @foreach (\App\Enums\Disposal\MediaTreatmentMethod::options() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="din_category" :label="__('disposal.treatment.din_category')" required>
            <option value="">{{ __('disposal.treatment.please_select') }}</option>
            @foreach (\App\Enums\Disposal\DinCategory::options() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="security_level" :label="__('disposal.treatment.security_level')" required>
            <option value="">{{ __('disposal.treatment.please_select') }}</option>
            @foreach (range(1, 7) as $level)
                <option value="{{ $level }}">{{ $level }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="protection_class" :label="__('disposal.treatment.protection_class')">
            <option value="">{{ __('disposal.treatment.protection_class_none') }}</option>
            @foreach (range(1, 3) as $class)
                <option value="{{ $class }}">{{ $class }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>

    <x-form-group :legend="__('disposal.treatment.group_evidence')" icon="fact_check" tone="primary" cols="2">
        <x-input-field name="treated_at" type="datetime-local" :label="__('disposal.treatment.treated_at')" required
                       :value="now()->format('Y-m-d\TH:i')" />
        <x-select-field name="performed_by_user_id" :label="__('disposal.treatment.performed_by')">
            @foreach ($users as $u)
                <option value="{{ $u->id }}" @selected($u->id === auth()->id())>{{ $u->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="evidence_reference" :label="__('disposal.treatment.evidence_reference')" maxlength="180" span="2" />
    </x-form-group>
</x-modal>
