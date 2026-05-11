{{-- Variablen: $vacation --}}
@php
    $dialogUrl = route('vacations.reject-form', $vacation) . '?dialog=1';
@endphp

<x-dialog
    :title="__('Urlaubsantrag ablehnen')"
    :eyebrow="__('Urlaubsverwaltung')"
    icon="✗"
    tone="error">

    <div class="mb-4 rounded-box border border-base-300 bg-base-200/60 p-3 text-sm">
        <p class="font-semibold">{{ $vacation->user?->name }}</p>
        <p class="text-base-content/70">{{ $vacation->start_date->format('d.m.Y') }} – {{ $vacation->end_date->format('d.m.Y') }}</p>
        <p class="text-base-content/70">{{ $vacation->typeLabel() }}</p>
    </div>

    <form method="POST" action="{{ route('vacations.reject', $vacation) }}" class="space-y-4" data-entry-form>
        @csrf @method('PATCH')
        <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">

        <div>
            <label class="label" for="reject-reason"><span class="label-text">{{ __('Ablehnungsgrund (optional)') }}</span></label>
            <textarea id="reject-reason" name="reject_reason" rows="3" class="textarea textarea-bordered w-full" maxlength="500">{{ old('reject_reason') }}</textarea>
        </div>

        <div class="flex flex-wrap items-center gap-3 pt-2">
            <button type="submit" class="btn btn-sm btn-error">{{ __('Ablehnen') }}</button>
            <button type="button" class="btn btn-sm btn-ghost" data-entry-modal-close>{{ __('Abbrechen') }}</button>
        </div>
    </form>
</x-dialog>
