{{--
  Created on   : Thu Aug 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : customer-circular.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Rundschreiben-Mail (Feature 119, MVP-608). Bewusst ohne Zählpixel und ohne
  umgeschriebene Links — der Text ist der Text.
--}}
@component('mail::message')
{{ $body }}
@endcomponent
