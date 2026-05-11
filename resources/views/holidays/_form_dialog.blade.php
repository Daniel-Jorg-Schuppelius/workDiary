@php
    $isEdit = $isEdit ?? false;
    $isDialog = $isDialog ?? true;
    $action = $isEdit ? route('holidays.update', $holiday) : route('holidays.store');
    $dialogUrl = ($isEdit ? route('holidays.edit', $holiday) : route('holidays.create')) . '?dialog=1';
@endphp

<x-dialog
    :title="$isEdit ? __('Feiertag bearbeiten') : __('Feiertag anlegen')"
    :eyebrow="__('Kalender')"
    icon="🎉"
    tone="warning">
    <form method="POST" action="{{ $action }}" class="space-y-4" data-entry-form>
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif
        @if ($isDialog)
            <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
        @endif

        <div>
            <label class="label" for="holiday-date"><span class="label-text">{{ __('Datum') }}</span></label>
            <input id="holiday-date" type="date" name="date" value="{{ old('date', optional($holiday?->date)->format('Y-m-d')) }}" class="input input-bordered w-full {{ $errors->has('date') ? 'input-error' : '' }}" required>
            @if ($errors->has('date'))
                <p class="mt-1 text-sm text-error">{{ $errors->first('date') }}</p>
            @endif
        </div>

        <div>
            <label class="label" for="holiday-name"><span class="label-text">{{ __('Name') }}</span></label>
            <input id="holiday-name" type="text" name="name" value="{{ old('name', $holiday?->name) }}" maxlength="120" class="input input-bordered w-full {{ $errors->has('name') ? 'input-error' : '' }}" required>
            @if ($errors->has('name'))
                <p class="mt-1 text-sm text-error">{{ $errors->first('name') }}</p>
            @endif
        </div>

        <label class="label cursor-pointer justify-start gap-3">
            <input type="checkbox" name="is_recurring" value="1" class="checkbox checkbox-sm" @checked((bool) old('is_recurring', $holiday?->is_recurring))>
            <span class="label-text">{{ __('Jährlich wiederholen') }}</span>
        </label>

        <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="btn btn-sm btn-ghost" data-entry-modal-close>{{ __('Abbrechen') }}</button>
            <button type="submit" class="btn btn-sm btn-primary">{{ $isEdit ? __('Speichern') : __('Anlegen') }}</button>
        </div>
    </form>
</x-dialog>
