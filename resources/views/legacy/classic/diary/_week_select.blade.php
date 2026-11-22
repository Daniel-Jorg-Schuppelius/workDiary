{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _week_select.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Wochenwahl wie im Altportal: Montage sieben Wochen zurück und vor. Erwartet: $route, $monday, $weekOffset --}}
<form method="GET" action="{{ route($route) }}" class="kp-msg">
    <label for="tb-week">{{ __('legacy_classic.choose_week') }}</label>
    <select id="tb-week" name="week" data-kp-autosubmit>
        @foreach (range(-7, 7) as $step)
            <option value="{{ $weekOffset + $step }}" @selected($step === 0)>{{ $monday->copy()->addWeeks($step)->format(\App\Support\Formats::date()) }}</option>
        @endforeach
    </select>
    <noscript><button type="submit">{{ __('legacy_classic.show') }}</button></noscript>
</form>
