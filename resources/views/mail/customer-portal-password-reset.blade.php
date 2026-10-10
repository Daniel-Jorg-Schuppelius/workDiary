{{--
  Created on   : Fri Oct 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : customer-portal-password-reset.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@component('mail::message')
# {{ __('customer_portal.password.mail_heading') }}

{{ __('Hallo :name,', ['name' => $portalUser->name]) }}

{{ __('customer_portal.password.mail_intro', ['org' => $brandName]) }}

@component('mail::button', ['url' => $resetUrl])
{{ __('Passwort festlegen') }}
@endcomponent

@component('mail::panel')
{{ __('customer_portal.password.mail_validity', ['minutes' => $validMinutes]) }}
@endcomponent

{{ __('customer_portal.password.mail_ignore') }}
@endcomponent
