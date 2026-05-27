{{--
  Created on   : Thu May 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : create.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Zählerstand erfassen'))
@section('nav-title', __('Zählerstand erfassen'))

@section('content')
<x-page-shell>
    <x-page-toolbar>
        <x-slot:title>{{ __('Zählerstand erfassen') }}</x-slot:title>
        <x-slot:actions>
            <x-icon-btn icon="arrow_back" size="sm" :href="route('meter-readings.index')" show-label>{{ __('Zurück') }}</x-icon-btn>
        </x-slot:actions>
    </x-page-toolbar>

    <div class="card bg-base-100 border border-base-200">
        <div class="card-body">
            <form method="POST" action="{{ route('meter-readings.store') }}" class="grid gap-4">
                @csrf

                <div>
                    <label class="label"><span class="label-text">{{ __('Asset (Zähler)') }}</span></label>
                    <input type="number" name="asset_id" value="{{ old('asset_id', $presetAssetId) }}" required
                           class="input input-sm input-bordered w-full max-w-xs" />
                    @error('asset_id')<div class="text-error text-xs mt-1">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label class="label"><span class="label-text">{{ __('Ablesezeitpunkt') }}</span></label>
                    <input type="datetime-local" name="read_at" value="{{ old('read_at') }}"
                           class="input input-sm input-bordered w-full max-w-xs" />
                </div>

                <div class="grid gap-4 md:grid-cols-2 max-w-xl">
                    <div>
                        <label class="label"><span class="label-text">{{ __('Stand') }}</span></label>
                        <input type="number" step="0.0001" name="value" value="{{ old('value') }}" required
                               class="input input-sm input-bordered w-full" />
                        @error('value')<div class="text-error text-xs mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="label"><span class="label-text">{{ __('Einheit') }}</span></label>
                        <input type="text" name="unit" value="{{ old('unit', 'kWh') }}" required
                               class="input input-sm input-bordered w-full" />
                    </div>
                </div>

                <div class="form-control">
                    <label class="label cursor-pointer justify-start gap-2">
                        <input type="checkbox" name="is_estimated" value="1" class="checkbox checkbox-sm"
                               @checked(old('is_estimated')) />
                        <span class="label-text">{{ __('Geschätzter Wert') }}</span>
                    </label>
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
