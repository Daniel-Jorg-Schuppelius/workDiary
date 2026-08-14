{{--
  Created on   : Fri Jul 31 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _legend.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Print-Legende: Farbkästchen + Label (Pflichtteil des PDF-Chart-Kontrakts,
    Spec 064 §Diagramm-UX). Erwartet: $entries ([['color' => '#hex', 'label' => …]]).
--}}
<p class="small chart-legend">
    @foreach ($entries ?? [] as $entry)
        <span style="display: inline-block; margin-right: 10px;">
            <span style="display: inline-block; width: 8px; height: 8px; background: {{ $entry['color'] }};">&nbsp;</span>
            {{ $entry['label'] }}
        </span>
    @endforeach
</p>
