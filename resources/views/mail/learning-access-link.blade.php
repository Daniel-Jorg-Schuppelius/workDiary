{{--
  Created on   : Mon Sep 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : learning-access-link.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@component('mail::message')
# {{ __('learning.mail.access_heading') }}

{{ __('learning.mail.access_intro', ['name' => $participant->name, 'course' => $courseTitle]) }}

@component('mail::button', ['url' => $accessUrl])
{{ __('learning.mail.access_button') }}
@endcomponent

@component('mail::panel')
{{ __('learning.mail.access_expires', ['date' => $expiresAt->translatedFormat('d.m.Y')]) }}
@endcomponent

{{ __('learning.mail.access_note') }}
@endcomponent
