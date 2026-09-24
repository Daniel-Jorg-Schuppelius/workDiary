{{--
  Created on   : Thu Sep 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : custom-field-cells.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Zellen der Listenspalten eigener Felder (Welle 4.6); Werte über x-field-display.
--}}
@props(['columns' => [], 'model'])
@foreach ($columns as $field)
    <td class="text-base-content/70"><x-field-display :field="$field" :values="$model->customValues()" /></td>
@endforeach
