{{--
  Created on   : Sun Jun 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : footer-copyright.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@php
    $startYear = (int) config('app.copyright_start_year', date('Y'));
    $currentYear = (int) date('Y');
    $years = $startYear < $currentYear ? $startYear.'-'.$currentYear : (string) $currentYear;
@endphp

&copy; {{ $years }}&nbsp;
<a href="{{ config('app.copyright_holder_url') }}"
   class="transition hover:text-base-content"
   rel="author">{{ config('app.copyright_holder') }}</a>
&nbsp;&middot; {{ config('app.name', 'WorkDiary') }}
&middot;&nbsp;
<a href="{{ config('app.license_url') }}"
   class="transition hover:text-base-content"
   rel="license">{{ config('app.license_name') }}</a>
