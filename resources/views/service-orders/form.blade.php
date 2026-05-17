@extends('layouts.app')

@section('title', $order ? __('Auftrag bearbeiten') : __('Neuer Auftrag'))

@section('content')
    <div class="mx-auto max-w-3xl space-y-4">
        <h1 class="text-xl font-semibold">
            {{ $order ? __('Auftrag bearbeiten') : __('Neuer Auftrag') }}
        </h1>

        <form method="POST"
              action="{{ $order ? route('service-orders.update', $order) : route('service-orders.store') }}"
              class="space-y-4 rounded-box border border-base-300 bg-base-100 p-4">
            @csrf
            @if ($order) @method('PUT') @endif

            <div class="grid gap-3 md:grid-cols-2">
                <label class="form-control">
                    <span class="label-text">{{ __('Titel') }} *</span>
                    <input type="text" name="title" required maxlength="200"
                           value="{{ old('title', $order?->title) }}"
                           class="input input-bordered input-sm @error('title') input-error @enderror">
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Termin') }} *</span>
                    <input type="date" name="scheduled_for" required
                           value="{{ old('scheduled_for', $order?->scheduled_for?->toDateString() ?? $date) }}"
                           class="input input-bordered input-sm">
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Kunde') }}</span>
                    <select name="customer_id" class="select select-bordered select-sm">
                        <option value="">—</option>
                        @foreach ($customers as $c)
                            <option value="{{ $c->id }}" @selected((int) old('customer_id', $order?->customer_id) === (int) $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Projekt') }}</span>
                    <select name="project_id" class="select select-bordered select-sm">
                        <option value="">—</option>
                        @foreach ($projects as $p)
                            <option value="{{ $p->id }}" @selected((int) old('project_id', $order?->project_id) === (int) $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Mitarbeiter') }}</span>
                    <select name="assigned_user_id" class="select select-bordered select-sm">
                        <option value="">{{ __('offen') }}</option>
                        @foreach ($users as $u)
                            <option value="{{ $u->id }}" @selected((int) old('assigned_user_id', $order?->assigned_user_id) === (int) $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Priorität') }}</span>
                    <select name="priority" class="select select-bordered select-sm">
                        @foreach ($priorities as $p)
                            <option value="{{ $p }}" @selected(old('priority', $order?->priority ?? 'normal') === $p)>{{ __($p) }}</option>
                        @endforeach
                    </select>
                </label>
                @if ($order)
                    <label class="form-control">
                        <span class="label-text">{{ __('Status') }}</span>
                        <select name="status" class="select select-bordered select-sm">
                            @foreach ($statuses as $s)
                                <option value="{{ $s }}" @selected(old('status', $order->status) === $s)>{{ __($s) }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <label class="form-control">
                    <span class="label-text">{{ __('Servicezeit (min)') }}</span>
                    <input type="number" name="service_minutes" min="1" max="1440"
                           value="{{ old('service_minutes', $order?->service_minutes ?? 60) }}"
                           class="input input-bordered input-sm">
                </label>
            </div>

            <fieldset class="grid gap-3 md:grid-cols-2 rounded-box border border-base-300 p-3">
                <legend class="px-1 text-sm font-medium">{{ __('Einsatzort') }}</legend>
                <label class="form-control md:col-span-2">
                    <span class="label-text">{{ __('Straße') }}</span>
                    <input type="text" name="address_line" value="{{ old('address_line', $order?->address_line) }}" class="input input-bordered input-sm">
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('PLZ') }}</span>
                    <input type="text" name="address_zip" value="{{ old('address_zip', $order?->address_zip) }}" class="input input-bordered input-sm">
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Ort') }}</span>
                    <input type="text" name="address_city" value="{{ old('address_city', $order?->address_city) }}" class="input input-bordered input-sm">
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Land (ISO 2)') }}</span>
                    <input type="text" name="address_country" maxlength="2" value="{{ old('address_country', $order?->address_country ?? 'DE') }}" class="input input-bordered input-sm">
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Lat') }}</span>
                    <input type="number" step="0.0000001" name="address_lat" value="{{ old('address_lat', $order?->address_lat) }}" class="input input-bordered input-sm">
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Lng') }}</span>
                    <input type="number" step="0.0000001" name="address_lng" value="{{ old('address_lng', $order?->address_lng) }}" class="input input-bordered input-sm">
                </label>
            </fieldset>

            <fieldset class="grid gap-3 md:grid-cols-2 rounded-box border border-base-300 p-3">
                <legend class="px-1 text-sm font-medium">{{ __('Zeitfenster') }}</legend>
                <label class="form-control">
                    <span class="label-text">{{ __('Frühestens') }}</span>
                    <input type="time" name="time_window_start" value="{{ old('time_window_start', $order?->time_window_start) }}" class="input input-bordered input-sm">
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Spätestens') }}</span>
                    <input type="time" name="time_window_end" value="{{ old('time_window_end', $order?->time_window_end) }}" class="input input-bordered input-sm">
                </label>
            </fieldset>

            <label class="form-control">
                <span class="label-text">{{ __('Beschreibung') }}</span>
                <textarea name="description" rows="3" class="textarea textarea-bordered textarea-sm">{{ old('description', $order?->description) }}</textarea>
            </label>

            <label class="form-control">
                <span class="label-text">{{ __('Notizen') }}</span>
                <textarea name="notes" rows="2" class="textarea textarea-bordered textarea-sm">{{ old('notes', $order?->notes) }}</textarea>
            </label>

            @if ($order && $order->address_lat && $order->address_lng)
                <x-map :center="['lat' => (float) $order->address_lat, 'lng' => (float) $order->address_lng]"
                       :markers="[['lat' => (float) $order->address_lat, 'lng' => (float) $order->address_lng, 'label' => $order->title]]"
                       :zoom="14" height="240px" />
            @endif

            <div class="flex justify-end gap-2">
                <a href="{{ route('service-orders.index') }}" class="btn btn-sm btn-ghost">{{ __('Abbrechen') }}</a>
                <button type="submit" class="btn btn-sm btn-primary">{{ __('Speichern') }}</button>
            </div>
        </form>
    </div>
@endsection
