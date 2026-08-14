{{--
  Created on   : Fri May 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
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
