{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : interview-offer.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Terminwahl des Bewerbers (MVP-925). Erwartet: $offer, $token, $orgName, $title --}}
<x-public-page :title="__('recruiting.offer.public_title')" main="max-w-md mx-auto p-4 flex flex-col gap-4" icons>
    <x-card class="flex flex-col gap-2">
        <h1 class="card-title">{{ __('recruiting.offer.public_title') }}</h1>
        <p class="text-sm opacity-70">{{ $orgName }}{{ $title !== '' ? ' · ' . $title : '' }}</p>
        <p class="text-sm">{{ __('recruiting.offer.public_intro', ['minutes' => $offer->duration_minutes, 'mode' => __('values.' . $offer->mode)]) }}</p>
    </x-card>
    @if (session('error'))
        <div role="alert" class="alert alert-error">{{ session('error') }}</div>
    @endif
    <x-card>
        <form method="POST" action="{{ route('interview-offers.choose', $token) }}" class="flex flex-col gap-2">
            @csrf
            <fieldset class="flex flex-col gap-2">
                <legend class="mb-1 text-sm font-medium">{{ __('recruiting.offer.choose') }}</legend>
                @foreach ($offer->slots as $i => $slot)
                    <label class="flex items-center gap-2 rounded-box border border-base-300 p-2">
                        <input type="radio" name="slot" value="{{ $i }}" class="radio radio-sm" @checked($i === 0) required>
                        <span>{{ \Carbon\CarbonImmutable::parse($slot)->setTimezone(\App\Support\Tz::current())->format('d.m.Y H:i') }}</span>
                    </label>
                @endforeach
            </fieldset>
            <x-button type="submit" icon="event_available">{{ __('recruiting.offer.confirm') }}</x-button>
        </form>
    </x-card>
</x-public-page>
