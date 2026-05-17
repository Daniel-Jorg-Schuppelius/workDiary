{{-- Variablen: $vacation --}}
@php
    $dialogUrl = route('vacations.reject-form', $vacation) . '?dialog=1';
@endphp

<x-modal
    :title="__('Urlaubsantrag ablehnen')"
    :eyebrow="__('Urlaubsverwaltung')"
    icon="cancel"
    tone="error"
    :action="route('vacations.reject', $vacation)"
    method="PATCH"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Ablehnen')"
    submit-class="btn-error">

    <div class="mb-4 rounded-box border border-base-300 bg-base-200/60 p-3 text-sm">
        <p class="font-semibold">{{ $vacation->user?->name }}</p>
        <p class="text-base-content/70">{{ $vacation->start_date->format('d.m.Y') }} – {{ $vacation->end_date->format('d.m.Y') }}</p>
        <p class="text-base-content/70">{{ $vacation->typeLabel() }}</p>
    </div>

    <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">

    <x-form-group :legend="__('Begründung')" icon="cancel" tone="error">
        <div class="fieldset">
            <label class="fieldset-label" for="reject-reason">{{ __('Ablehnungsgrund (optional)') }}</label>
            <textarea id="reject-reason" name="reject_reason" rows="3" class="textarea textarea-bordered w-full" maxlength="500">{{ old('reject_reason') }}</textarea>
        </div>
    </x-form-group>
</x-modal>
