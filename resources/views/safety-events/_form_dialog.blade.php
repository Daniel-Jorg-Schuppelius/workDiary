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
        <label class="form-control">
            <span class="label-text">{{ __('safety.field.kind') }}</span>
            <select name="kind" class="select select-bordered w-full" required>
                @foreach (\App\Enums\Safety\SafetyEventKind::cases() as $k)
                    <option value="{{ $k->value }}" @selected(old('kind', $event?->kind->value) === $k->value)>{{ $k->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('safety.field.severity') }}</span>
            <select name="severity" class="select select-bordered w-full" required>
                @foreach (\App\Enums\Safety\SafetyEventSeverity::cases() as $s)
                    <option value="{{ $s->value }}" @selected(old('severity', $event?->severity->value ?? 'low') === $s->value)>{{ $s->label() }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <label class="form-control">
            <span class="label-text">{{ __('safety.field.occurred_at') }}</span>
            <input type="datetime-local" name="occurred_at" required
                   class="input input-bordered w-full"
                   value="{{ old('occurred_at', optional($event?->occurred_at)->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i')) }}">
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('safety.field.location') }}</span>
            <input type="text" name="location" maxlength="180" class="input input-bordered w-full"
                   value="{{ old('location', $event?->location) }}">
        </label>
    </div>

    <label class="form-control w-full">
        <span class="label-text">{{ __('safety.field.affected_person') }}</span>
        <input type="text" name="affected_person" maxlength="180" class="input input-bordered w-full"
               value="{{ old('affected_person', $event?->affected_person) }}">
    </label>

    <label class="form-control w-full">
        <span class="label-text">{{ __('safety.field.description') }}</span>
        <textarea name="description" rows="3" required class="textarea textarea-bordered w-full">{{ old('description', $event?->description) }}</textarea>
    </label>

    <label class="form-control w-full">
        <span class="label-text">{{ __('safety.field.immediate_action') }}</span>
        <textarea name="immediate_action" rows="2" class="textarea textarea-bordered w-full">{{ old('immediate_action', $event?->immediate_action) }}</textarea>
    </label>

    @if ($isEdit)
        <label class="form-control w-full">
            <span class="label-text">{{ __('safety.field.root_cause') }}</span>
            <textarea name="root_cause" rows="2" class="textarea textarea-bordered w-full">{{ old('root_cause', $event?->root_cause) }}</textarea>
            <span class="label-text-alt text-base-content/60">{{ __('safety.hint.root_cause_for_close') }}</span>
        </label>
    @endif

    @if ($errors->any())
        <div class="alert alert-error text-sm">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
</x-modal>
