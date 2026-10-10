{{--
  Created on   : Fri Oct 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : portal-second-factor-reset-notice.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@component('mail::message')
# {{ __('customer_portal.second_factor.mail_heading') }}

{{ __('Hallo :name,', ['name' => $portalUser->name]) }}

{{ __('customer_portal.second_factor.mail_intro', ['org' => $brandName]) }}

@component('mail::button', ['url' => $loginUrl])
{{ __('Anmelden') }}
@endcomponent

{{ __('customer_portal.second_factor.mail_unexpected', ['org' => $brandName]) }}
@endcomponent
