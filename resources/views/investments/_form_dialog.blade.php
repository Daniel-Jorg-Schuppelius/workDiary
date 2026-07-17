{{-- Dialog: Investitionsakte anlegen/bearbeiten (Feature 069, MVP-200) --}}
@php $isEdit = $case->exists; @endphp
<x-modal
    :title="$isEdit ? __('Akte bearbeiten') : __('Investition erfassen')"
    :eyebrow="__('Investitionsplanung')"
    icon="trending_up"
    tone="primary"
    :action="$isEdit ? route('investments.update', $case) : route('investments.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')"
>
    <x-form-group :legend="__('Investition')" icon="trending_up" tone="primary" cols="2">
        <x-input-field name="title" :label="__('Titel')" required maxlength="200" span="2" :value="old('title', $case->title ?? '')" />
        <x-select-field name="category" :label="__('Kategorie')" required>
            @foreach (\App\Models\Investments\InvestmentCase::CATEGORIES as $category)
                <option value="{{ $category }}" @selected(old('category', $case->category ?? 'replacement') === $category)>{{ __("values.$category") }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="urgency" :label="__('Dringlichkeit')" required>
            @foreach (['low', 'medium', 'high'] as $urgency)
                <option value="{{ $urgency }}" @selected(old('urgency', $case->urgency ?? 'medium') === $urgency)>{{ __("values.$urgency") }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="responsible_user_id" :label="__('Verantwortlich')" span="2">
            <option value="">{{ __('— offen —') }}</option>
            @foreach ($users as $u)
                <option value="{{ $u->sqid }}" @selected(old('responsible_user_id', $case->responsible_user_id !== null ? \App\Support\Sqid::encode(\App\Models\User::class, $case->responsible_user_id) : '') === $u->sqid)>{{ $u->name }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="cost_center_id" :label="__('Kostenstelle')" :hint="$costCenters->isEmpty() ? __('Noch keine Kostenstellen — Anlage in der Akte möglich.') : null">
            <option value="">{{ __('— keine —') }}</option>
            @foreach ($costCenters as $center)
                <option value="{{ $center->sqid }}" @selected(old('cost_center_id', $case->cost_center_id !== null ? \App\Support\Sqid::encode(\App\Models\CostCenter::class, $case->cost_center_id) : '') === $center->sqid)>{{ $center->code }} — {{ $center->label }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="cost_center_label" :label="__('Kostenstelle (Freitext-Fallback)')" maxlength="200" :value="old('cost_center_label', $case->cost_center_label ?? '')" />
        <x-date-range class="md:col-span-2" layout="split" form-control
                      from-name="starts_on" to-name="ends_on" type="date"
                      :from-label="__('Zeitraum von')" :to-label="__('Zeitraum bis')"
                      :from="old('starts_on', optional($case->starts_on)->toDateString())"
                      :to="old('ends_on', optional($case->ends_on)->toDateString())" />
        <x-textarea-field name="reason" :label="__('Anlass')" rows="2" span="2">{{ old('reason', $case->reason ?? '') }}</x-textarea-field>
        <x-textarea-field name="objective" :label="__('Ziel / erwarteter Nutzen')" rows="2" span="2">{{ old('objective', $case->objective ?? '') }}</x-textarea-field>
        <x-textarea-field name="risk_note" :label="__('Risiko')" rows="2" span="2">{{ old('risk_note', $case->risk_note ?? '') }}</x-textarea-field>
    </x-form-group>

    <x-validation-errors />
</x-modal>
