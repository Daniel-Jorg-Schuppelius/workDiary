{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : interview-offer-confirmed.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Bestätigung der Terminwahl (MVP-925). Erwartet: $interview, $orgName --}}
<x-public-page :title="__('recruiting.offer.confirmed_title')" main="max-w-md mx-auto p-4" icons>
    <div role="status" class="alert alert-success items-start">
        <x-icon name="event_available" />
        <div>
            <div class="font-semibold">{{ __('recruiting.offer.confirmed_title') }}</div>
            <p class="text-sm">{{ __('recruiting.offer.confirmed_text', ['when' => $interview->scheduled_at->copy()->setTimezone(\App\Support\Tz::current())->format('d.m.Y H:i'), 'org' => $orgName]) }}</p>
        </div>
    </div>
</x-public-page>
