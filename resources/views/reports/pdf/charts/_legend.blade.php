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
