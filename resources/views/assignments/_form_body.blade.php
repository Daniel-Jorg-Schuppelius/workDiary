{{-- Erwartet: $assignment, $isEdit, $isDialog, $canAssignOthers, $assignableUsers, $shiftOptions, $prefillStartAt, $prefillEndAt, $prefillUserId --}}
@php
    $isDialog = $isDialog ?? false;
    $action = $isEdit ? route('assignments.update', $assignment) : route('assignments.store');
    $cancelUrl = route('duties.index', ['tab' => 'notdienst']);
    $startAt = old('start_at', $assignment?->start_at?->format('Y-m-d\TH:i') ?? $prefillStartAt ?? '');
    $endAt = old('end_at', $assignment?->end_at?->format('Y-m-d\TH:i') ?? $prefillEndAt ?? '');
    $reason = old('reason', $assignment?->reason ?? '');
    $selectedUser = (int) old('user_id', $assignment?->user_id ?? $prefillUserId ?? auth()->id());
    $selectedShift = (int) old('on_call_shift_id', $assignment?->on_call_shift_id ?? 0);
    $back = request()->query('_back') ?? url()->previous();
    $dialogUrl = ($isEdit ? route('assignments.edit', $assignment) : route('assignments.create')) . '?dialog=1';
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
        <label class="fieldset-label">{{ __('Zugehörige Bereitschaft (optional)') }}</label>
        <select name="on_call_shift_id" class="select select-bordered w-full">
            <option value="">{{ __('— keine —') }}</option>
            @foreach ($shiftOptions as $s)
                <option value="{{ $s->id }}" @selected($selectedShift === (int) $s->id)>
                    {{ $s->user?->name }} · {{ $s->start_at?->format('d.m.Y H:i') }} – {{ $s->end_at?->format('d.m.Y H:i') }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="fieldset w-full">
        <label class="fieldset-label">{{ __('Grund') }}</label>
        <textarea name="reason" rows="3" class="textarea textarea-bordered w-full">{{ $reason }}</textarea>
    </div>

    @if ($errors->any())
        <div class="alert alert-error">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="flex flex-wrap items-center gap-3 pt-2">
        <button type="submit" class="btn btn-sm btn-primary">{{ $isEdit ? __('Speichern') : __('Notdienst anlegen') }}</button>
        @if ($isDialog)
            <button type="button" class="btn btn-sm btn-ghost" data-entry-modal-close>{{ __('Abbrechen') }}</button>
        @else
            <a href="{{ $cancelUrl }}" class="btn btn-sm btn-ghost">{{ __('Abbrechen') }}</a>
        @endif
    </div>
</form>

@if ($isEdit)
    <form method="POST" action="{{ route('assignments.destroy', $assignment) }}" class="mt-3"
          data-confirm-dialog
          data-confirm-message="{{ __('Wirklich löschen?') }}"
          data-confirm-label="{{ __('Löschen') }}">
        @csrf @method('DELETE')
        <input type="hidden" name="_back" value="{{ $back }}">
        <button type="submit" class="btn btn-sm btn-error btn-outline">{{ __('Notdienst löschen') }}</button>
    </form>
@endif
