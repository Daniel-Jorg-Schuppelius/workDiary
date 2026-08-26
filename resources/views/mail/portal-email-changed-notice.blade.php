{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : portal-email-changed-notice.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@component('mail::message')
# {{ __('E-Mail-Adresse geändert') }}

{{ __('Hallo :name,', ['name' => $portalUser->name]) }}

{{ __('die Anmelde-E-Mail Ihres Zugangs zum Kundenportal von :org wurde von :old auf :new geändert. Diese Adresse erhält ab sofort keine Portal-Nachrichten mehr.', ['org' => $brandName, 'old' => $oldEmail, 'new' => $newEmail]) }}

{{ __('Wenn Sie diese Änderung nicht veranlasst haben, wenden Sie sich bitte umgehend an Ihre Ansprechperson bei :org.', ['org' => $brandName]) }}
@endcomponent
