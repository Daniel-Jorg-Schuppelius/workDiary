{{-- Erwartet: $assignment, $isEdit, $isDialog, $canAssignOthers, $assignableUsers, $shiftOptions, $prefillStartAt, $prefillEndAt, $prefillUserId --}}
@php
    $isDialog = $isDialog ?? false;
    $isEdit = $isEdit ?? false;
    $startAt = old('start_at', $assignment?->start_at?->format('Y-m-d\TH:i') ?? $prefillStartAt ?? '');
    $endAt = old('end_at', $assignment?->end_at?->format('Y-m-d\TH:i') ?? $prefillEndAt ?? '');
    $reason = old('reason', $assignment?->reason ?? '');
    $selectedUser = (int) old('user_id', $assignment?->user_id ?? $prefillUserId ?? auth()->id());
    $selectedShift = (int) old('on_call_shift_id', $assignment?->on_call_shift_id ?? 0);
    $back = request()->query('_back') ?? url()->previous();
    $dialogUrl = ($isEdit ? route('assignments.edit', $assignment) : route('assignments.create')) . '?dialog=1';
@endphp

<input type="hidden" name="_back" value="{{ $back }}">
@if ($isDialog)
    <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
@endif

@if ($canAssignOthers)
    <x-form-group :legend="__('Mitarbeiter')" icon="person" tone="primary">
        <div class="fieldset w-full">
            <label class="fieldset-label">{{ __('Mitarbeiter') }}</label>
            <select name="user_id" class="select select-bordered w-full">
                @foreach ($assignableUsers as $u)
                    <option value="{{ $u['id'] }}" @selected($selectedUser === (int) $u['id'])>{{ $u['name'] }}</option>
                @endforeach
            </select>
        </div>
    </x-form-group>
@endif

<x-form-group :legend="__('Einsatz')" icon="warning" tone="error">
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
</x-form-group>

<x-form-group :legend="__('Grund')" icon="description" tone="ghost">
    <div class="fieldset w-full">
        <label class="fieldset-label">{{ __('Grund') }}</label>
        <textarea name="reason" rows="3" class="textarea textarea-bordered w-full">{{ $reason }}</textarea>
    </div>
</x-form-group>

@if ($errors->any())
    <div class="alert alert-error">
        <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif
