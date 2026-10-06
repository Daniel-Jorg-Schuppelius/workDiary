{{--
  Created on   : Fri May 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@props([
    'layout' => 'list',
    'cols' => null,
    'smallLabels' => false,
    'divided' => false,
])

{{--
    Definitions-Liste für Detail-/Show-Seiten.
    Inhalt sind <x-detail-grid.row>-Elemente.

    layout:
      - list  (Standard) Label-Spalte links, Wert daneben. Eine Zeile ohne Wert
              entfällt samt Label. Spalten-Layout bei Bedarf via class-Attribut
              überschreiben (z. B. class="grid-cols-1 sm:grid-cols-2").
      - cells Raster aus Zellen, Label über dem Wert.
      - split Zeilen mit Label links und Wert am rechten Rand.

    Für cells und split:
      - cols         : 1–4 Spalten ab md (bei 4 ab sm zwei); Standard cells 2, split 1
      - small-labels : Label in text-xs
      - divided      : Trennlinie zwischen den Zeilen (split)
    Eine Zelle bleibt auch ohne Wert stehen und zeigt „—“, damit das Raster
    nicht rutscht; eine bedingte Zelle bekommt ihr @if um das Tag.

    Die Zeilen lesen layout, small-labels und divided per @aware: eine Liste
    innerhalb einer Zelle nennt layout="list" ausdrücklich.
--}}

@php
    // Klassen ausgeschrieben, damit Tailwind sie findet.
    $grid = match ($layout) {
        'cells' => 'grid gap-x-6 gap-y-2 text-sm',
        'split' => 'grid gap-x-8 gap-y-1 text-sm',
        default => 'grid grid-cols-[max-content_1fr] gap-x-4 gap-y-1 text-sm',
    };
    if (in_array($layout, ['cells', 'split'], true)) {
        $grid .= ' ' . match ((int) ($cols ?? ($layout === 'cells' ? 2 : 1))) {
            1 => 'grid-cols-1',
            3 => 'grid-cols-1 md:grid-cols-3',
            4 => 'grid-cols-1 sm:grid-cols-2 md:grid-cols-4',
            default => 'grid-cols-1 md:grid-cols-2',
        };
    }
@endphp
<dl {{ $attributes->class([$grid]) }}>
    {{ $slot }}
</dl>
