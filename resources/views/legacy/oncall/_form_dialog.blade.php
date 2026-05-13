@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Legacy\LegacyUser> $users */
    $isEdit = $isEdit ?? false;
    $isDialog = $isDialog ?? true;
    $action = $isEdit ? route('legacy.oncall.update', $item) : route('legacy.oncall.store');
    $dialogUrl = ($isEdit ? route('legacy.oncall.edit', $item) : route('legacy.oncall.create')) . '?dialog=1';
@endphp

<x-dialog
    :title="$isEdit ? __('Bereitschaft bearbeiten') : __('Bereitschaft neu')"
    :eyebrow="__('Legacy')"
    icon="⏱"
    tone="info">
    <form method="POST" action="{{ $action }}" class="space-y-5" data-entry-form>
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif
        @if ($isDialog)
            <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
        @endif

        <div class="fieldset">
            <label for="user" class="fieldset-label">{{ __('Mitarbeiter') }}</label>
            <select id="user" name="user" class="select select-bordered w-full @error('user') select-error @enderror">
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected((int) old('user', $item?->user) === (int) $user->id)>{{ $user->uname }}</option>
                @endforeach
            </select>
            @error('user')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label">{{ __('Zeitraum') }}</label>
            <x-date-range
                fromName="von"
                toName="bis"
                fromId="von"
                toId="bis"
                :from="old('von', $item?->von?->format('Y-m-d'))"
                :to="old('bis', $item?->bis?->format('Y-m-d'))"
                :label="false"
                class="w-full"
            />
            @error('von')<p class="text-error text-sm">{{ $message }}</p>@enderror
            @error('bis')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>

        <div class="flex gap-2 pt-1">
            <button type="submit" class="btn btn-primary btn-sm">{{ __('Speichern') }}</button>
            @if ($isDialog)
                <button type="button" class="btn btn-ghost btn-sm" data-entry-modal-close>{{ __('Abbrechen') }}</button>
            @else
                <a href="{{ route('legacy.oncall.index') }}" class="btn btn-ghost btn-sm">{{ __('Abbrechen') }}</a>
            @endif
        </div>
    </form>
</x-dialog>
