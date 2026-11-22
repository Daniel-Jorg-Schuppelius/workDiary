{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _details.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Eintrag lesen wie im Altportal (tbdetails/tbinfo). Erwartet: $entry, $employee (Name oder null), $heading (bool) --}}
<table class="kp-msg">
    @if ($heading)
        <tr><td class="rechts">&nbsp;</td><th colspan="2">{{ __('legacy_classic.order_diary') }}</th></tr>
    @endif
    @if ($employee !== null)
        <tr><td class="rechts">{{ __('legacy_classic.label.employee') }}</td><td colspan="2">{{ $employee }}</td></tr>
    @endif
    <tr><td class="rechts">{{ __('legacy_classic.label.from') }}</td><th colspan="2">{{ $entry->von ? __('legacy_classic.at_time', ['time' => \App\Legacy\Support\LegacyClock::format($entry->von)]) : '' }}</th></tr>
    <tr><td class="rechts">{{ __('legacy_classic.label.until') }}</td><th colspan="2">{{ $entry->bis ? __('legacy_classic.at_time', ['time' => \App\Legacy\Support\LegacyClock::format($entry->bis)]) : '' }}</th></tr>
    <tr><td class="rechts">&nbsp;</td><td colspan="2" class="kp-pre">{{ $entry->inhalt }}</td></tr>
    @if (filled($entry->antwort))
        <tr><td class="rechts">&nbsp;</td><td colspan="2" class="kp-pre">{{ $entry->antwort }}</td></tr>
    @endif
    <tr><td class="rechts">{{ __('legacy_classic.label.status') }}</td><td colspan="2">{{ $entry->statusLabel() }}</td></tr>
</table>
