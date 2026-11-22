{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _year_select.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Jahresauswahl wie im Altportal. Erwartet: $route, $years, $year; optional $keep (weitere Query-Parameter) --}}
<form method="GET" action="{{ route($route) }}" class="kp-msg">
    <label for="kp-year">{{ __('legacy_support.classic.choose_year') }}</label>
    @foreach (($keep ?? []) as $keepName => $keepValue)
        @if ($keepValue !== null && $keepValue !== '')
            <input type="hidden" name="{{ $keepName }}" value="{{ $keepValue }}">
        @endif
    @endforeach
    <select id="kp-year" name="year" data-kp-autosubmit>
        @foreach ($years as $optionYear)
            <option value="{{ $optionYear }}" @selected($year === $optionYear)>{{ $optionYear }}</option>
        @endforeach
    </select>
    <noscript><button type="submit">{{ __('legacy_support.classic.show') }}</button></noscript>
</form>
