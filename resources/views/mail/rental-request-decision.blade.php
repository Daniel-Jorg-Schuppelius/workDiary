{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : rental-request-decision.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@component('mail::message')
@if ($accepted)
# {{ __('Ihre Verleih-Anfrage wurde angenommen') }}

{{ __('Hallo :name,', ['name' => $request->portalUser?->name ?? '']) }}

{{ __('wir haben Ihre Anfrage angenommen und den Zeitraum vorgemerkt. Die verbindliche Reservierung sowie Übergabe und Konditionen stimmen wir mit Ihnen ab.') }}

@component('mail::panel')
**{{ __('Gerät') }}:** {{ $request->subjectLabel() }}

**{{ __('Zeitraum') }}:** {{ $request->starts_at->format('d.m.Y H:i') }} – {{ $request->ends_at->format('d.m.Y H:i') }}
@endcomponent
@else
# {{ __('Ihre Verleih-Anfrage konnte nicht angenommen werden') }}

{{ __('Hallo :name,', ['name' => $request->portalUser?->name ?? '']) }}

{{ __('leider können wir Ihre Anfrage für :subject vom :from bis :to nicht annehmen.', ['subject' => $request->subjectLabel(), 'from' => $request->starts_at->format('d.m.Y H:i'), 'to' => $request->ends_at->format('d.m.Y H:i')]) }}

@component('mail::panel')
**{{ __('Grund') }}:** {{ $request->decline_reason }}
@endcomponent
@endif

{{ __('Den Status Ihrer Anfragen sehen Sie im Kundenportal unter „Verleih-Anfrage".') }}
@endcomponent
