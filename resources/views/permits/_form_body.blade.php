@php
    /** @var \App\Models\Permit $permit */
    /** @var array<string, string> $statusOptions */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Event> $eventOptions */
    $currentStatus = old('status', $permit->status?->value ?? \App\Enums\Permit\PermitStatus::Required->value);
    $currentEvent = old('event_id', $permit->event?->sqid);
    $evidence = $permit->exists ? $permit->evidence() : null;
@endphp

<x-form-group :legend="__('permit.sections.base')" icon="verified" tone="primary" cols="2">
    <x-input-field name="title" :label="__('permit.fields.title')" required :value="old('title', $permit->title)" :span="2" />

    <x-select-field name="status" :label="__('permit.fields.status')" required>
        @foreach ($statusOptions as $value => $label)
            <option value="{{ $value }}" @selected($currentStatus === $value)>{{ $label }}</option>
        @endforeach
    </x-select-field>

    <x-select-field name="event_id" :label="__('permit.fields.event')">
        <option value="">{{ __('permit.fields.event_none') }}</option>
        @foreach ($eventOptions as $event)
            <option value="{{ $event->sqid }}" @selected($currentEvent === $event->sqid)>{{ $event->title }}</option>
        @endforeach
    </x-select-field>

    <x-input-field name="permit_type" :label="__('permit.fields.permit_type')" :value="old('permit_type', $permit->permit_type)" />
    <x-input-field name="authority" :label="__('permit.fields.authority')" :value="old('authority', $permit->authority)" />
    <x-input-field name="reference_no" :label="__('permit.fields.reference_no')" :value="old('reference_no', $permit->reference_no)" />
</x-form-group>

<x-form-group :legend="__('permit.sections.dates')" icon="event" tone="info" cols="2">
    <x-input-field name="applied_at" type="date" :label="__('permit.fields.applied_at')"
        :value="old('applied_at', $permit->applied_at?->toDateString())" />

    <x-date-range class="md:col-span-2"
        type="date"
        fromName="valid_from"
        toName="valid_until"
        :from="old('valid_from', $permit->valid_from?->toDateString())"
        :to="old('valid_until', $permit->valid_until?->toDateString())"
        :fromLabel="__('permit.fields.valid_from')"
        :toLabel="__('permit.fields.valid_until')"
        :label="false" />
</x-form-group>

<x-form-group :legend="__('permit.fields.notes')" icon="sticky_note_2" cols="1">
    <x-textarea-field name="notes" rows="3" :value="old('notes', $permit->notes)" :span="2" />
</x-form-group>

{{-- Nachweis-Dokument: Teil der Hauptform (kein nested <form>), Upload/Replace
     über das Feld evidence_document; das Löschen liegt in footerExtra (außerhalb
     der Form). --}}
<x-form-group :legend="__('permit.fields.evidence')" icon="attachment" cols="1">
    @if ($evidence)
        <div class="flex items-center gap-2 text-sm">
            <span class="material-symbols-outlined" aria-hidden="true">description</span>
            <a class="link" href="{{ \App\Http\Controllers\AttachmentController::downloadUrl($evidence) }}">{{ $evidence->original_name }}</a>
        </div>
        <p class="text-xs text-base-content/60 mt-1">{{ __('permit.evidence.replace_hint') }}</p>
    @endif
    <x-input-field name="evidence_document" type="file" :label="$evidence ? __('permit.evidence.replace') : __('permit.evidence.upload')" />
    <p class="text-xs text-base-content/60 mt-1">{{ __('permit.evidence.hint', ['mb' => \App\Services\Attachments\FileAttacher::maxMb()]) }}</p>
</x-form-group>

<x-validation-errors />
