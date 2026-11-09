{{--
  Created on   : Mon Nov 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : create.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Eigene Seite statt Dialog: die Kundenseiten laufen im öffentlichen Layout ohne Dialog-Host. --}}
@extends('layouts.public')

@section('title', __('legacy_support.portal.new_order'))
@section('header-action')
    @include('legacy.support.portal._logout')
@endsection

@section('content')
    @php $inputClass = 'input input-bordered w-full'; @endphp
    @include('legacy.support.portal._frame', ['customer' => $customer, 'active' => 'create'])

    <form method="POST" action="{{ route('legacy.portal.orders.store') }}" class="mx-auto max-w-3xl space-y-4 rounded-box border border-base-300 bg-base-100 p-5 shadow-xs">
        @csrf
        <h2 class="font-['Space_Grotesk'] text-xl font-bold">{{ __('legacy_support.portal.new_order') }}</h2>
        <p class="text-sm text-base-content/70">{{ __('legacy_support.portal.new_order_intro') }}</p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="portal-kname" class="mb-1 block text-sm font-medium">{{ __('legacy_support.field.name') }}<span class="text-error">*</span></label>
                <input id="portal-kname" name="kname" type="text" maxlength="30" autocomplete="name" value="{{ old('kname') }}" class="{{ $inputClass }} @error('kname') input-error @enderror" required>
                @error('kname')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="portal-telefon" class="mb-1 block text-sm font-medium">{{ __('legacy_support.field.phone') }}</label>
                <input id="portal-telefon" name="telefon" type="tel" maxlength="30" autocomplete="tel" value="{{ old('telefon') }}" class="{{ $inputClass }} @error('telefon') input-error @enderror">
                @error('telefon')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2">
                <label for="portal-objekt" class="mb-1 block text-sm font-medium">{{ __('legacy_support.field.object') }}</label>
                <input id="portal-objekt" name="objekt" type="text" maxlength="255" value="{{ old('objekt') }}" class="{{ $inputClass }} @error('objekt') input-error @enderror">
                @error('objekt')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2">
                <label for="portal-auftrag" class="mb-1 block text-sm font-medium">{{ __('legacy_support.field.order_text') }}<span class="text-error">*</span></label>
                <textarea id="portal-auftrag" name="auftrag" rows="6" class="textarea textarea-bordered w-full @error('auftrag') textarea-error @enderror" required>{{ old('auftrag') }}</textarea>
                @error('auftrag')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <x-icon-btn icon="send" tone="primary" size="sm" type="submit" show-label>{{ __('legacy_support.portal.submit_order') }}</x-icon-btn>
            <x-icon-btn icon="arrow_back" size="sm" :href="route('legacy.portal.orders.index')" show-label>{{ __('legacy_support.portal.back_to_list') }}</x-icon-btn>
        </div>
    </form>
@endsection
