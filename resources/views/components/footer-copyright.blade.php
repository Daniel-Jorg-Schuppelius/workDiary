@php
    $startYear = (int) config('app.copyright_start_year', date('Y'));
    $currentYear = (int) date('Y');
    $years = $startYear < $currentYear ? $startYear.'-'.$currentYear : (string) $currentYear;
@endphp

&copy; {{ $years }}
<a href="{{ config('app.copyright_holder_url') }}"
   class="transition hover:text-base-content"
   rel="author">{{ config('app.copyright_holder') }}</a>
&middot; {{ config('app.name', 'WorkDiary') }}
&middot;
<a href="{{ config('app.license_url') }}"
   class="transition hover:text-base-content"
   rel="license">{{ config('app.license_name') }}</a>
