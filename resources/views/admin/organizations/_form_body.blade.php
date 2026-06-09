@php $skipStatusControls = $skipStatusControls ?? false; @endphp

{{-- Shared form fields for Organization create & edit. --}}

<x-form-group :legend="__('Stammdaten')" icon="apartment" tone="primary" cols="2">
    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Name') }} *</label>
        <input type="text" name="name" required maxlength="255" autofocus
               class="input input-bordered w-full @error('name') input-error @enderror"
               value="{{ old('name', $organization?->name) }}">
        @error('name')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset">
        <label class="fieldset-label">{{ __('Sprache') }}</label>
        <x-locale-select name="locale"
                         :selected="old('locale', $organization?->locale ?? 'de')"
                         class="@error('locale') input-error @enderror" />
        @error('locale')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset">
        <label class="fieldset-label">{{ __('Zeitzone') }}</label>
        <x-timezone-select name="timezone"
                           :selected="old('timezone', $organization?->timezone ?? 'Europe/Berlin')"
                           class="@error('timezone') input-error @enderror" />
        @error('timezone')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset">
        <label class="fieldset-label">{{ __('Datumsformat') }}</label>
        <x-format-select type="date" name="settings[personalization][date_format]"
                         :selected="old('settings.personalization.date_format', data_get($organization?->settings, 'personalization.date_format'))"
                         class="@error('settings.personalization.date_format') input-error @enderror" />
        @error('settings.personalization.date_format')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset">
        <label class="fieldset-label">{{ __('Uhrzeitformat') }}</label>
        <x-format-select type="time" name="settings[personalization][time_format]"
                         :selected="old('settings.personalization.time_format', data_get($organization?->settings, 'personalization.time_format'))"
                         class="@error('settings.personalization.time_format') input-error @enderror" />
        @error('settings.personalization.time_format')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset">
        <label class="fieldset-label">{{ __('Vergessene Stempelungen') }}</label>
        @php $_selfCorrection = old('settings.attendance.self_correction', data_get($organization?->settings, 'attendance.self_correction', 'request')); @endphp
        <select name="settings[attendance][self_correction]"
                class="select select-bordered w-full @error('settings.attendance.self_correction') select-error @enderror">
            <option value="request" @selected($_selfCorrection === 'request')>{{ __('Mitarbeiter beantragt – Personalverwaltung genehmigt') }}</option>
            <option value="self" @selected($_selfCorrection === 'self')>{{ __('Mitarbeiter darf selbst nachtragen') }}</option>
        </select>
        <p class="text-xs opacity-70 mt-1">{{ __('Nachträge werden immer als „manuell" gekennzeichnet und bleiben in der Korrektur-Inbox sichtbar.') }}</p>
        @error('settings.attendance.self_correction')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
    </div>
</x-form-group>

<x-form-group :legend="__('Plan & Status')" icon="workspace_premium" tone="info" cols="2">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Plan') }} *</label>
        <select name="plan" required class="select select-bordered w-full @error('plan') select-error @enderror">
            @foreach (\App\Models\Organization::$plans as $plan)
                <option value="{{ $plan }}" @selected(old('plan', $organization?->plan ?? 'free') === $plan)>
                    {{ \App\Models\Organization::planLabel($plan) }}
                </option>
            @endforeach
        </select>
        @error('plan')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    @unless ($skipStatusControls)
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Aktiv') }}</label>
        <label class="label cursor-pointer justify-start gap-3">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" class="toggle toggle-primary"
                   @checked(old('is_active', $organization?->is_active ?? true))>
            <span class="label-text">{{ __('Organisation ist aktiv') }}</span>
        </label>
    </div>
    @endunless

    <div class="fieldset">
        <label class="fieldset-label">{{ __('Sicherheit') }}</label>
        <label class="label cursor-pointer justify-start gap-3">
            <input type="hidden" name="two_factor_required" value="0">
            <input type="checkbox" name="two_factor_required" value="1" class="toggle toggle-primary"
                   @checked(old('two_factor_required', $organization?->two_factor_required ?? false))>
            <span class="label-text">{{ __('Zwei-Faktor-Authentifizierung für alle Mitglieder verpflichtend') }}</span>
        </label>
    </div>
</x-form-group>

@if ($organization)
    @include('admin.organizations._compliance', ['organization' => $organization])
    @include('admin.organizations._settings', ['organization' => $organization])
@endif
