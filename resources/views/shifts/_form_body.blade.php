{{-- Erwartet: $shift, $isEdit, $isDialog, $canAssignOthers, $assignableUsers, $prefillStartAt, $prefillEndAt, $prefillUserId --}}
@php
    $isDialog = $isDialog ?? false;
    $action = $isEdit ? route('shifts.update', $shift) : route('shifts.store');
    $cancelUrl = route('duties.index');
    $startAt = old('start_at', $shift?->start_at?->format('Y-m-d\TH:i') ?? $prefillStartAt ?? '');
    $endAt = old('end_at', $shift?->end_at?->format('Y-m-d\TH:i') ?? $prefillEndAt ?? '');
    $note = old('note', $shift?->note ?? '');
    $selectedUser = (int) old('user_id', $shift?->user_id ?? $prefillUserId ?? auth()->id());
    $back = request()->query('_back') ?? url()->previous();
    $dialogUrl = ($isEdit ? route('shifts.edit', $shift) : route('shifts.create')) . '?dialog=1';
@endphp

<form method="POST" action="{{ $action }}" class="space-y-4" data-entry-form>
    @csrf
    @if ($isEdit) @method('PUT') @endif
    <input type="hidden" name="_back" value="{{ $back }}">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
    @endif

    @if ($canAssignOthers)
        <div class="fieldset w-full">
            <label class="fieldset-label">{{ __('Mitarbeiter') }}</label>
            <select name="user_id" class="select select-bordered w-full">
                @foreach ($assignableUsers as $u)
                    <option value="{{ $u['id'] }}" @selected($selectedUser === (int) $u['id'])>{{ $u['name'] }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div class="fieldset">
        <label class="fieldset-label">{{ __('Zeitraum') }} *</label>
        <x-date-range
            type="datetime-local"
            :from="$startAt"
            :to="$endAt"
            fromName="start_at"
            toName="end_at"
            :fromLabel="__('Beginn')"
            :toLabel="__('Ende')"
            :label="false"
            required
            class="w-full"
        />
        @error('start_at')<p class="text-error text-sm">{{ $message }}</p>@enderror
        @error('end_at')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset w-full">
        <label class="fieldset-label">{{ __('Notiz') }}</label>
        <textarea name="note" rows="3" class="textarea textarea-bordered w-full">{{ $note }}</textarea>
    </div>

    @if ($errors->any())
        <div class="alert alert-error">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="flex flex-wrap items-center gap-3 pt-2">
        <button type="submit" class="btn btn-sm btn-primary">{{ $isEdit ? __('Speichern') : __('Bereitschaft anlegen') }}</button>
        @if ($isDialog)
            <button type="button" class="btn btn-sm btn-ghost" data-entry-modal-close>{{ __('Abbrechen') }}</button>
        @else
            <a href="{{ $cancelUrl }}" class="btn btn-sm btn-ghost">{{ __('Abbrechen') }}</a>
        @endif
    </div>
</form>

@if ($isEdit)
    <form method="POST" action="{{ route('shifts.destroy', $shift) }}" class="mt-3" onsubmit="return confirm('{{ __('Wirklich löschen?') }}');">
        @csrf @method('DELETE')
        <input type="hidden" name="_back" value="{{ $back }}">
        <button type="submit" class="btn btn-sm btn-error btn-outline">{{ __('Bereitschaft löschen') }}</button>
    </form>
@endif
