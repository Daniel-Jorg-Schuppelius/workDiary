{{--
  Created on   : Sun May 03 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_body.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Erwartet: $assignment, $isEdit, $isDialog, $canAssignOthers, $assignableUsers, $shiftOptions, $prefillStartAt, $prefillEndAt, $prefillUserId --}}
@php
    $isDialog = $isDialog ?? false;
    $isEdit = $isEdit ?? false;
    $startAt = old('start_at', $assignment?->start_at?->orgTz()->format('Y-m-d\TH:i') ?? $prefillStartAt ?? '');
    $endAt = old('end_at', $assignment?->end_at?->orgTz()->format('Y-m-d\TH:i') ?? $prefillEndAt ?? '');
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
        <x-select-field name="user_id" :label="__('Mitarbeiter')" class="w-full">
            @foreach ($assignableUsers as $u)
                <option value="{{ $u['id'] }}" @selected($selectedUser === (int) $u['id'])>{{ $u['name'] }}</option>
            @endforeach
        </x-select-field>
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

    <x-select-field name="on_call_shift_id" :label="__('Zugehörige Bereitschaft (optional)')" class="w-full">
        <option value="">{{ __('— keine —') }}</option>
        @foreach ($shiftOptions as $s)
            <option value="{{ $s->sqid }}" @selected((string) old('on_call_shift_id', \App\Support\Sqid::encode(\App\Models\OnCallShift::class, $selectedShift)) === $s->sqid)>
                {{ $s->user?->name }} · {{ $s->start_at?->fdatetime() }} – {{ $s->end_at?->fdatetime() }}
            </option>
        @endforeach
    </x-select-field>
</x-form-group>

<x-form-group :legend="__('Grund')" icon="description" tone="ghost">
    <x-textarea-field name="reason" :label="__('Grund')" rows="3" :value="$reason" class="w-full" />
</x-form-group>

<x-validation-errors />
