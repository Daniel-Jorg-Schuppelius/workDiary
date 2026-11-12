{{--
  Created on   : Wed Nov 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : survey-opt-out.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Umfrage-Opt-out über den Einladungs-Token (MVP-1603), ohne Login.
--}}
<x-public-page :title="__('Umfragen abbestellen')" main="mx-auto max-w-2xl p-4" icons>
    <div class="rounded-box bg-base-100 p-8 text-center shadow">
        @if ($done)
            <x-icon name="check_circle" class="text-5xl text-success" />
            <h1 class="mt-2 text-xl font-semibold">{{ __('Sie erhalten keine Umfragen mehr.') }}</h1>
            <p class="mt-1 text-sm text-base-content/70">{{ __('Ihr Widerspruch ist eingetragen. Möchten Sie später wieder teilnehmen, wenden Sie sich bitte an :organization.', ['organization' => $organization ?: __('uns')]) }}</p>
        @else
            <x-icon name="unsubscribe" class="text-5xl text-muted" />
            <h1 class="mt-2 text-xl font-semibold">{{ __('Umfragen abbestellen') }}</h1>
            <p class="mt-1 text-sm text-base-content/70">{{ __('Möchten Sie künftig keine Umfrage-Einladungen mehr von :organization erhalten?', ['organization' => $organization ?: __('uns')]) }}</p>
            <form method="POST" action="{{ route('surveys.public-opt-out.store', $token) }}" class="mt-4">
                @csrf
                <x-button type="submit" size="md">{{ __('Keine Umfragen mehr senden') }}</x-button>
            </form>
        @endif
    </div>
</x-public-page>
