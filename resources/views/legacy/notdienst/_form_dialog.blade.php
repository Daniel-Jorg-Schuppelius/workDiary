@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Legacy\LegacyUser> $users */
    $isEdit = $isEdit ?? false;
    $isDialog = $isDialog ?? true;
    $action = $isEdit ? route('legacy.notdienst.update', $item) : route('legacy.notdienst.store');
    $dialogUrl = ($isEdit ? route('legacy.notdienst.edit', $item) : route('legacy.notdienst.create')) . '?dialog=1';
@endphp

<x-dialog
    :title="$isEdit ? __('Notdienst bearbeiten') : __('Notdienst neu')"
    :eyebrow="__('Legacy')"
    icon="🚨"
    tone="warning">
    <form method="POST" action="{{ $action }}" class="space-y-5" data-entry-form>
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif
        @if ($isDialog)
            <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
        @endif

        <div>
            <label for="user" class="label text-sm font-semibold pb-1">{{ __('Mitarbeiter') }}</label>
            <select id="user" name="user" class="select select-bordered select-sm w-full {{ $errors->has('user') ? 'select-error' : '' }}">
                @foreach ($users as $legacyUser)
                    <option value="{{ $legacyUser->id }}" @selected((int) old('user', $item?->user) === (int) $legacyUser->id)>{{ $legacyUser->uname }}</option>
                @endforeach
            </select>
            @if ($errors->has('user'))
                <p class="mt-2 text-sm text-error">{{ $errors->first('user') }}</p>
            @endif
        </div>

        <x-date-range
            layout="split"
            fromName="von"
            toName="bis"
            fromId="von"
            toId="bis"
            :from="old('von', $item?->von?->format('Y-m-d'))"
            :to="old('bis', $item?->bis?->format('Y-m-d'))"
            :fromError="$errors->first('von')"
            :toError="$errors->first('bis')"
        />

        <div class="flex gap-2 pt-1">
            <button type="submit" class="btn btn-primary btn-sm">{{ __('Speichern') }}</button>
            @if ($isDialog)
                <button type="button" class="btn btn-ghost btn-sm" data-entry-modal-close>{{ __('Abbrechen') }}</button>
            @else
                <a href="{{ route('legacy.notdienst.index') }}" class="btn btn-ghost btn-sm">{{ __('Abbrechen') }}</a>
            @endif
        </div>
    </form>
</x-dialog>
