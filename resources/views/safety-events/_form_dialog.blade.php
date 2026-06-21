@php($isEdit = $event !== null)
<x-modal
    :title="$isEdit ? __('safety.action.edit') : __('safety.action.create')"
    :eyebrow="__('safety.title.index')"
    icon="health_and_safety"
    tone="primary"
    size="lg"
    :action="$isEdit ? route('safety-events.update', $event) : route('safety-events.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('safety.action.save') : __('safety.action.create')">

    <div class="grid gap-3 sm:grid-cols-2">
        <x-select-field name="kind" :label="__('safety.field.kind')" required>
            @foreach (\App\Enums\Safety\SafetyEventKind::cases() as $k)
                <option value="{{ $k->value }}" @selected(old('kind', $event?->kind->value) === $k->value)>{{ $k->label() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="severity" :label="__('safety.field.severity')" required>
            @foreach (\App\Enums\Safety\SafetyEventSeverity::cases() as $s)
                <option value="{{ $s->value }}" @selected(old('severity', $event?->severity->value ?? 'low') === $s->value)>{{ $s->label() }}</option>
            @endforeach
        </x-select-field>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <x-input-field name="occurred_at" type="datetime-local" :label="__('safety.field.occurred_at')" required
                       :value="old('occurred_at', optional($event?->occurred_at)->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i'))" />
        <x-input-field name="location" :label="__('safety.field.location')" maxlength="180"
                       :value="old('location', $event?->location)" />
    </div>

    <x-input-field name="affected_person" :label="__('safety.field.affected_person')" maxlength="180"
                   :value="old('affected_person', $event?->affected_person)" />

    <x-textarea-field name="description" :label="__('safety.field.description')" rows="3" required
                      :value="old('description', $event?->description)" />

    <x-textarea-field name="immediate_action" :label="__('safety.field.immediate_action')" rows="2"
                      :value="old('immediate_action', $event?->immediate_action)" />

    @if ($isEdit)
        <x-textarea-field name="root_cause" :label="__('safety.field.root_cause')" rows="2"
                          :value="old('root_cause', $event?->root_cause)"
                          :hint="__('safety.hint.root_cause_for_close')" />
    @endif

    @if ($errors->any())
        <div class="alert alert-error text-sm">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
</x-modal>
