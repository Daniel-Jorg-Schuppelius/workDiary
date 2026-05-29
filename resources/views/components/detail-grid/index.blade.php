@props([])

{{--
    Definitions-Liste für Detail-/Show-Seiten.
    Inhalt sind <x-detail-grid.row>-Elemente.
    Spalten-Layout bei Bedarf via class-Attribut überschreiben
    (z. B. class="grid-cols-1 sm:grid-cols-2").
--}}
<dl {{ $attributes->class(['grid grid-cols-[max-content_1fr] gap-x-4 gap-y-1 text-sm']) }}>
    {{ $slot }}
</dl>
