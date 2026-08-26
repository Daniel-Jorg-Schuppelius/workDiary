{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : portal-email-change-confirm.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@component('mail::message')
# {{ __('Neue E-Mail-Adresse bestätigen') }}

{{ __('Hallo :name,', ['name' => $portalUser->name]) }}

{{ __('für Ihren Zugang zum Kundenportal von :org wurde die Adresse :email als neue Anmelde-E-Mail hinterlegt. Bestätigen Sie die Änderung über den folgenden Link — erst dann wird sie wirksam.', ['org' => $brandName, 'email' => $newEmail]) }}

@component('mail::button', ['url' => $confirmUrl])
{{ __('E-Mail-Adresse bestätigen') }}
@endcomponent

@component('mail::panel')
{{ __('Der Link ist :hours Stunden gültig. Danach muss die Änderung im Portal erneut angestoßen werden.', ['hours' => $ttlHours]) }}
@endcomponent

{{ __('Wenn Sie diese Änderung nicht veranlasst haben, ignorieren Sie diese E-Mail — Ihre bisherige Adresse bleibt unverändert.') }}
@endcomponent
