{{-- Shared form fields for ServiceOrder --}}

<x-form-group :legend="__('Allgemein')" icon="assignment" tone="primary" cols="2">
    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Titel') }} *</label>
        <input type="text" name="title" required maxlength="200"
               value="{{ old('title', $order?->title) }}"
               class="input input-bordered w-full @error('title') input-error @enderror">
        @error('title')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Termin') }} *</label>
        <input type="date" name="scheduled_for" required
               value="{{ old('scheduled_for', $order?->scheduled_for?->toDateString() ?? $date) }}"
               class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Servicezeit (min)') }}</label>
        <input type="number" name="service_minutes" min="1" max="1440"
               value="{{ old('service_minutes', $order?->service_minutes ?? 60) }}"
               class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Kunde') }}</label>
        <select name="customer_id" class="select select-bordered w-full">
            <option value="">—</option>
            @foreach ($customers as $c)
                <option value="{{ $c->id }}" @selected((int) old('customer_id', $order?->customer_id) === (int) $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Projekt') }}</label>
        <select name="project_id" class="select select-bordered w-full">
            <option value="">—</option>
            @foreach ($projects as $p)
                <option value="{{ $p->id }}" @selected((int) old('project_id', $order?->project_id) === (int) $p->id)>{{ $p->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Mitarbeiter') }}</label>
        <select name="assigned_user_id" class="select select-bordered w-full">
            <option value="">{{ __('offen') }}</option>
            @foreach ($users as $u)
                <option value="{{ $u->id }}" @selected((int) old('assigned_user_id', $order?->assigned_user_id) === (int) $u->id)>{{ $u->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Priorität') }}</label>
        <select name="priority" class="select select-bordered w-full">
            @foreach ($priorities as $p)
                <option value="{{ $p }}" @selected(old('priority', $order?->priority ?? 'normal') === $p)>{{ __($p) }}</option>
            @endforeach
        </select>
    </div>
    @if ($order)
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Status') }}</label>
            <select name="status" class="select select-bordered w-full">
                @foreach ($statuses as $s)
                    <option value="{{ $s }}" @selected(old('status', $order->status) === $s)>{{ __($s) }}</option>
                @endforeach
            </select>
        </div>
    @endif
</x-form-group>

<x-form-group :legend="__('Einsatzort')" icon="home" tone="info" cols="2">
    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Straße') }}</label>
        <input type="text" name="address_line" value="{{ old('address_line', $order?->address_line) }}" class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('PLZ') }}</label>
        <input type="text" name="address_zip" value="{{ old('address_zip', $order?->address_zip) }}" class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Ort') }}</label>
        <input type="text" name="address_city" value="{{ old('address_city', $order?->address_city) }}" class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Land (ISO 2)') }}</label>
        <input type="text" name="address_country" maxlength="2" value="{{ old('address_country', $order?->address_country ?? 'DE') }}" class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Lat') }}</label>
        <input type="number" step="0.0000001" name="address_lat" value="{{ old('address_lat', $order?->address_lat) }}" class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Lng') }}</label>
        <input type="number" step="0.0000001" name="address_lng" value="{{ old('address_lng', $order?->address_lng) }}" class="input input-bordered w-full">
    </div>
</x-form-group>

<x-form-group :legend="__('Zeitfenster')" icon="schedule" tone="warning" cols="2">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Frühestens') }}</label>
        <input type="time" name="time_window_start" value="{{ old('time_window_start', $order?->time_window_start) }}" class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Spätestens') }}</label>
        <input type="time" name="time_window_end" value="{{ old('time_window_end', $order?->time_window_end) }}" class="input input-bordered w-full">
    </div>
</x-form-group>

<x-form-group :legend="__('Beschreibung & Notizen')" icon="edit_note" tone="ghost">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Beschreibung') }}</label>
        <textarea name="description" rows="3" class="textarea textarea-bordered w-full">{{ old('description', $order?->description) }}</textarea>
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Notizen') }}</label>
        <textarea name="notes" rows="2" class="textarea textarea-bordered w-full">{{ old('notes', $order?->notes) }}</textarea>
    </div>
</x-form-group>

@if ($errors->any())
    <div class="alert alert-error text-sm">
        <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif
