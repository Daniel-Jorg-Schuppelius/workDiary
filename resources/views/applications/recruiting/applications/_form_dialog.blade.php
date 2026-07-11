{{-- Dialog: Bewerbung erfassen (Feature 068, MVP-190) --}}
<x-modal
    :title="__('Bewerbung erfassen')"
    :eyebrow="__('Personal')"
    icon="person_search"
    tone="primary"
    :action="route('recruiting.applications.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Erfassen')"
>
    <x-form-group :legend="__('Bewerbung')" icon="person_search" tone="primary" cols="2">
        <x-select-field name="job_requisition_id" :label="__('Stelle (optional)')" span="2">
            <option value="">{{ __('— Initiativbewerbung —') }}</option>
            @foreach ($requisitions as $requisition)
                <option value="{{ $requisition->sqid }}" @selected((string) old('job_requisition_id') === $requisition->sqid)>{{ $requisition->title }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="candidate_name" :label="__('Name')" required maxlength="200" span="2" :value="old('candidate_name')" />
        <x-input-field name="email" type="email" :label="__('E-Mail')" maxlength="200" :value="old('email')" :hint="__('Dient der Dublettenprüfung (nur als Hash).')" />
        <x-input-field name="phone" :label="__('Telefon')" maxlength="50" :value="old('phone')" />
        <x-select-field name="source" :label="__('Quelle')" required>
            @foreach (\App\Models\Applications\JobPosting::CHANNELS as $channel)
                <option value="{{ $channel }}" @selected(old('source', 'other') === $channel)>{{ __("values.$channel") }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="responsible_user_id" :label="__('Verantwortlich')">
            <option value="">{{ __('— offen —') }}</option>
            @foreach ($users as $u)
                <option value="{{ $u->sqid }}" @selected((string) old('responsible_user_id') === $u->sqid)>{{ $u->name }}</option>
            @endforeach
        </x-select-field>
        <x-textarea-field name="notes" :label="__('Interne Notiz (verschlüsselt gespeichert)')" rows="3" span="2">{{ old('notes') }}</x-textarea-field>
    </x-form-group>

    <p class="text-xs text-base-content/60">{{ __('Keine Gesundheits- oder sonstigen Art.-9-Daten erfassen (Feature 068, Rechtsrahmen).') }}</p>

    @if ($errors->any())
        <div class="alert alert-error text-sm">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
</x-modal>
