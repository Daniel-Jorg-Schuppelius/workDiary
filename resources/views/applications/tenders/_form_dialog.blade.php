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

    @if ($errors->any())
        <div class="alert alert-error text-sm">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
</x-modal>
