{{--
  Created on   : Thu May 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : create.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Schlüsselvorgang erfassen'))
@section('nav-title', __('Schlüsselvorgang erfassen'))

@section('content')
<x-page-shell>
    <x-page-toolbar>
        <x-slot:title>{{ __('Schlüsselvorgang erfassen') }}</x-slot:title>
        <x-slot:actions>
            <x-icon-btn icon="arrow_back" size="sm" :href="route('key-handovers.index')" show-label>{{ __('Zurück') }}</x-icon-btn>
        </x-slot:actions>
    </x-page-toolbar>

    <div class="card bg-base-100 border border-base-200">
        <div class="card-body">
            <form method="POST" action="{{ route('key-handovers.store') }}" class="grid gap-4">
                @csrf

                <div>
                    <label class="label"><span class="label-text">{{ __('Asset (Schlüssel)') }}</span></label>
                    <input type="number" name="asset_id" value="{{ old('asset_id', $presetAssetId) }}" required
                           class="input input-sm input-bordered w-full max-w-xs" />
                    @error('asset_id')<div class="text-error text-xs mt-1">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label class="label"><span class="label-text">{{ __('Richtung') }}</span></label>
                    <select name="direction" class="select select-sm select-bordered w-full max-w-xs" required>
                        @foreach ($directionOptions as $val => $label)
                            <option value="{{ $val }}" @selected(old('direction') === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="label"><span class="label-text">{{ __('Person') }}</span></label>
                    <input type="text" name="person_name" value="{{ old('person_name') }}" required
                           class="input input-sm input-bordered w-full max-w-md" />
                    @error('person_name')<div class="text-error text-xs mt-1">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label class="label"><span class="label-text">{{ __('Referenz (Ausweis-Nr., Vertrag …)') }}</span></label>
                    <input type="text" name="person_reference" value="{{ old('person_reference') }}"
                           class="input input-sm input-bordered w-full max-w-md" />
                </div>

                <div>
                    <label class="label"><span class="label-text">{{ __('Zeitpunkt') }}</span></label>
                    <input type="datetime-local" name="occurred_at" value="{{ old('occurred_at') }}"
                           class="input input-sm input-bordered w-full max-w-xs" />
                </div>

                <div>
                    <label class="label"><span class="label-text">{{ __('Rückgabe erwartet bis') }}</span></label>
                    <input type="date" name="expected_return_at" value="{{ old('expected_return_at') }}"
                           class="input input-sm input-bordered w-full max-w-xs" />
                </div>

                <div>
                    <label class="label"><span class="label-text">{{ __('Notizen') }}</span></label>
                    <textarea name="notes" rows="3"
                              class="textarea textarea-bordered textarea-sm w-full">{{ old('notes') }}</textarea>
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('Erfassen') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-page-shell>
@endsection
