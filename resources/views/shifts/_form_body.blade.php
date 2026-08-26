{{--
  Created on   : Sun May 03 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_body.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Erwartet: $shift, $isEdit, $isDialog, $canAssignOthers, $assignableUsers, $prefillStartAt, $prefillEndAt, $prefillUserId --}}
@php
    $isDialog = $isDialog ?? false;
    $isEdit = $isEdit ?? false;
    $action = $isEdit ? route('shifts.update', $shift) : route('shifts.store');
    $startAt = old('start_at', $shift?->start_at?->orgTz()->format('Y-m-d\TH:i') ?? $prefillStartAt ?? '');
    $endAt = old('end_at', $shift?->end_at?->orgTz()->format('Y-m-d\TH:i') ?? $prefillEndAt ?? '');
    $note = old('note', $shift?->note ?? '');
    $selectedUser = (int) old('user_id', $shift?->user_id ?? $prefillUserId ?? auth()->id());
    $back = request()->query('_back') ?? url()->previous();
    $dialogUrl = ($isEdit ? route('shifts.edit', $shift) : route('shifts.create')) . '?dialog=1';
@endphp

<input type="hidden" name="_back" value="{{ $back }}">
@if ($isDialog)
    <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
@endif

@if ($canAssignOthers)
    <x-form-group :legend="__('Zuordnung')" icon="person" tone="primary">
        <div class="fieldset w-full">
            <label for="user_id" class="fieldset-label">{{ __('Mitarbeiter') }}</label>
            <select id="user_id" name="user_id" class="select select-bordered w-full">
                @foreach ($assignableUsers as $u)
                    <option value="{{ $u['id'] }}" @selected($selectedUser === (int) $u['id'])>{{ $u['name'] }}</option>
                @endforeach
            </select>
        </div>
    </x-form-group>
@endif

<x-form-group :legend="__('Zeitraum')" icon="schedule" tone="info">
    <div class="fieldset">
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
</x-form-group>

<x-form-group :legend="__('Details')" icon="description" tone="ghost">
    <div class="fieldset w-full">
        <label for="note" class="fieldset-label">{{ __('Notiz') }}</label>
        <textarea id="note" name="note" rows="3" class="textarea textarea-bordered w-full">{{ $note }}</textarea>
    </div>
</x-form-group>

<x-validation-errors />
