{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _box.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Kästchen bzw. Symbol im Stil der Altportal-Bildchen; Farbe und Symbol kommen aus dem Stylesheet. Erwartet: $kind; optional $label --}}
<span class="kp-box kp-box--{{ $kind }}" @isset($label) role="img" aria-label="{{ $label }}" title="{{ $label }}" @else aria-hidden="true" @endisset></span>
