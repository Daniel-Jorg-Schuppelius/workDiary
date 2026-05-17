{{-- Shared form fields for Organization create & edit (modernized) --}}

<x-form-group :legend="__('Stammdaten')" icon="apartment" tone="primary" cols="2">
    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Name') }} *</label>
        <input type="text" name="name" required maxlength="255" autofocus
               class="input input-bordered w-full @error('name') input-error @enderror"
               value="{{ old('name', $organization?->name) }}">
        @error('name')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset">
        <label class="fieldset-label">{{ __('Plan') }} *</label>
        <select name="plan" required class="select select-bordered w-full @error('plan') select-error @enderror">
            @foreach (\App\Models\Organization::$plans as $plan)
                <option value="{{ $plan }}" @selected(old('plan', $organization?->plan ?? 'free') === $plan)>
                    {{ ucfirst($plan) }}
                </option>
            @endforeach
        </select>
        @error('plan')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset">
        <label class="cursor-pointer label justify-start gap-3">
            <input type="checkbox" name="is_active" class="checkbox" value="1"
                   @checked(old('is_active', $organization?->is_active ?? true))>
            <span class="fieldset-label">{{ __('Aktiv') }}</span>
        </label>
    </div>

    <div class="fieldset">
        <label class="fieldset-label">{{ __('Sprache') }}</label>
        <input type="text" name="locale" maxlength="10" placeholder="de"
               class="input input-bordered w-full @error('locale') input-error @enderror"
               value="{{ old('locale', $organization?->locale ?? 'de') }}">
        @error('locale')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset">
        <label class="fieldset-label">{{ __('Zeitzone') }}</label>
        <input type="text" name="timezone" maxlength="64"
               class="input input-bordered w-full @error('timezone') input-error @enderror"
               value="{{ old('timezone', $organization?->timezone ?? 'Europe/Berlin') }}">
        @error('timezone')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
    </div>
</x-form-group>

@if ($organization)
    @include('admin.organizations._compliance', ['organization' => $organization])
    @include('admin.organizations._settings', ['organization' => $organization])
@endif
