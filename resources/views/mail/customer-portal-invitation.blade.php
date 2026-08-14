{{--
  Created on   : Mon Aug 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : customer-portal-invitation.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@component('mail::message')
# {{ __('Willkommen im Kundenportal von :org', ['org' => $brandName]) }}

{{ __('Hallo :name,', ['name' => $portalUser->name]) }}

{{ __(':org hat für Sie einen Zugang zum Kundenportal eingerichtet. Über den folgenden Link legen Sie Ihr persönliches Passwort fest und melden sich anschließend an.', ['org' => $brandName]) }}

@component('mail::button', ['url' => $acceptUrl])
{{ __('Passwort festlegen') }}
@endcomponent

@component('mail::panel')
{{ __('Der Link ist einmalig verwendbar und gültig bis :date.', ['date' => optional($portalUser->portal_invite_expires_at)->format('d.m.Y')]) }}
@endcomponent

{{ __('Wenn Sie diese Einladung nicht erwartet haben, ignorieren Sie diese E-Mail — es wird kein Zugang ohne Passwortvergabe aktiviert.') }}
@endcomponent
