{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _legend.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Legende der Wochenraster: Dienste wie im Altportal, dazu die Zustandsfarben. --}}
<table class="tb-legend kp-msg">
    <tr>
        <td class="rogb">&nbsp;</td><td class="lob">&nbsp;</td><td>&nbsp;{{ __('legacy_classic.legend.oncall') }}&nbsp;</td>
        <td class="rogn">&nbsp;</td><td class="lon">&nbsp;</td><td>&nbsp;{{ __('legacy_classic.legend.notdienst') }}&nbsp;</td>
    </tr>
</table>
<table class="tb-legend kp-msg">
    <tr>
        <td class="off">&nbsp;</td><td>&nbsp;{{ __('legacy_classic.status.unread') }}&nbsp;</td>
        <td class="work">&nbsp;</td><td>&nbsp;{{ __('legacy_classic.status.confirmed') }}&nbsp;</td>
        <td class="ok">&nbsp;</td><td>&nbsp;{{ __('legacy_classic.status.done') }}&nbsp;</td>
        <td class="neu">&nbsp;</td><td>&nbsp;{{ __('legacy_classic.status.problem') }}&nbsp;</td>
    </tr>
</table>
