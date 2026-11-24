{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _legend.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Legende der Wochenraster: Dienste wie im Altportal, dazu die Zustandsfarben; Farbfeld und Text bleiben beim Umbruch zusammen. --}}
@php
    $legendRows = [
        [[['rogb', 'lob'], __('legacy_classic.legend.oncall')], [['rogn', 'lon'], __('legacy_classic.legend.notdienst')]],
        [[['off'], __('legacy_classic.status.unread')], [['work'], __('legacy_classic.status.confirmed')], [['ok'], __('legacy_classic.status.done')], [['neu'], __('legacy_classic.status.problem')]],
    ];
@endphp
@foreach ($legendRows as $legendRow)
    <div class="tb-legend">
        @foreach ($legendRow as [$legendSwatches, $legendText])
            <table role="presentation">
                <tr>
                    @foreach ($legendSwatches as $legendSwatch)
                        <td class="{{ $legendSwatch }}">&nbsp;</td>
                    @endforeach
                    <td>&nbsp;{{ $legendText }}&nbsp;</td>
                </tr>
            </table>
        @endforeach
    </div>
@endforeach
