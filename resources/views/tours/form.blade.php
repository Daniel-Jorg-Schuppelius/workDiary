@extends('layouts.app')

@section('title', __('Neue Tour'))

@section('content')
    <div class="mx-auto max-w-2xl space-y-4">
        <h1 class="text-xl font-semibold">{{ __('Neue Tour') }}</h1>

        <form method="POST" action="{{ route('tours.store') }}"
              class="space-y-4 rounded-box border border-base-300 bg-base-100 p-4">
            @csrf
            <div class="grid gap-3 md:grid-cols-2">
                <label class="form-control">
                    <span class="label-text">{{ __('Datum') }} *</span>
                    <input type="date" name="tour_date" required value="{{ old('tour_date', $date) }}"
                           class="input input-bordered input-sm">
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Fahrer') }} *</span>
                    <select name="user_id" required class="select select-bordered select-sm">
                        @foreach ($users as $u)
                            <option value="{{ $u->id }}" @selected((int) old('user_id') === (int) $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Fahrzeug') }}</span>
                    <select name="vehicle_id" class="select select-bordered select-sm">
                        <option value="">—</option>
                        @foreach ($vehicles as $v)
                            <option value="{{ $v->id }}">{{ $v->license_plate }} {{ $v->label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Name') }}</span>
                    <input type="text" name="name" maxlength="200" class="input input-bordered input-sm">
                </label>
            </div>
            <div class="flex justify-end gap-2">
                <a href="{{ route('tours.index') }}" class="btn btn-sm btn-ghost">{{ __('Abbrechen') }}</a>
                <button type="submit" class="btn btn-sm btn-primary">{{ __('Anlegen') }}</button>
            </div>
        </form>
    </div>
@endsection
