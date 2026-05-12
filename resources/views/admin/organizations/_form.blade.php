{{-- Shared form fields for create & edit --}}

<div class="form-control">
    <label class="label"><span class="label-text">{{ __('Name') }} *</span></label>
    <input type="text" name="name" class="input input-bordered @error('name') input-error @enderror"
           value="{{ old('name', $organization?->name) }}" required maxlength="255" autofocus>
    @error('name')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
</div>

<div class="form-control">
    <label class="label"><span class="label-text">{{ __('Plan') }} *</span></label>
    <select name="plan" class="select select-bordered @error('plan') select-error @enderror" required>
        @foreach (\App\Models\Organization::$plans as $plan)
            <option value="{{ $plan }}" @selected(old('plan', $organization?->plan ?? 'free') === $plan)>
                {{ ucfirst($plan) }}
            </option>
        @endforeach
    </select>
    @error('plan')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
</div>

<div class="grid grid-cols-2 gap-4">
    <div class="form-control">
        <label class="label"><span class="label-text">{{ __('Sprache') }}</span></label>
        <input type="text" name="locale" class="input input-bordered @error('locale') input-error @enderror"
               value="{{ old('locale', $organization?->locale ?? 'de') }}" maxlength="10" placeholder="de">
        @error('locale')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
    </div>
    <div class="form-control">
        <label class="label"><span class="label-text">{{ __('Zeitzone') }}</span></label>
        <input type="text" name="timezone" class="input input-bordered @error('timezone') input-error @enderror"
               value="{{ old('timezone', $organization?->timezone ?? 'Europe/Berlin') }}" maxlength="64">
        @error('timezone')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
    </div>
</div>

<div class="form-control">
    <label class="label cursor-pointer justify-start gap-3">
        <input type="checkbox" name="is_active" class="checkbox" value="1"
               @checked(old('is_active', $organization?->is_active ?? true))>
        <span class="label-text">{{ __('Aktiv') }}</span>
    </label>
</div>
