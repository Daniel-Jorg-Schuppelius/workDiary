{{--
  Created on   : Wed May 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : create.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Ticket anlegen'))
@section('nav-title', __('Ticket anlegen'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Neues Service- oder FM-Ticket erfassen.')">
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" size="sm" :href="route('service-tickets.index')"
                            show-label>{{ __('Zurück') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-card>
        <form method="POST" action="{{ route('service-tickets.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="label" for="title">{{ __('Titel') }} *</label>
                <input id="title" name="title" type="text" required maxlength="200"
                       class="input input-bordered w-full" value="{{ old('title') }}">
                @error('title')<p class="text-error text-xs">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="label" for="description">{{ __('Beschreibung') }}</label>
                <textarea id="description" name="description" rows="4"
                          class="textarea textarea-bordered w-full">{{ old('description') }}</textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="label" for="priority">{{ __('Priorität') }} *</label>
                    <select id="priority" name="priority" class="select select-bordered w-full">
                        @foreach ($priorityOptions as $val => $label)
                            <option value="{{ $val }}" @selected(old('priority', 'normal') === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="customer_id">{{ __('Kunden-ID (optional)') }}</label>
                    <input id="customer_id" name="customer_id" type="number" min="1"
                           class="input input-bordered w-full" value="{{ old('customer_id') }}">
                </div>
                <div>
                    <label class="label" for="asset_id">{{ __('Asset-ID (optional)') }}</label>
                    <input id="asset_id" name="asset_id" type="number" min="1"
                           class="input input-bordered w-full" value="{{ old('asset_id') }}">
                </div>
                <div>
                    <label class="label" for="project_id">{{ __('Projekt-ID (optional)') }}</label>
                    <input id="project_id" name="project_id" type="number" min="1"
                           class="input input-bordered w-full" value="{{ old('project_id') }}">
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <x-button href="{{ route('service-tickets.index') }}" tone="ghost">{{ __('Abbrechen') }}</x-button>
                <x-button type="submit" tone="primary">{{ __('Ticket anlegen') }}</x-button>
            </div>
        </form>
    </x-card>
</x-page-shell>
@endsection
