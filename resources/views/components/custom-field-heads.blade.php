{{--
  Created on   : Thu Sep 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : custom-field-heads.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Spaltenköpfe der als „In der Liste zeigen" markierten eigenen Felder (Welle 4.6).
--}}
@props(['columns' => []])
@foreach ($columns as $field)
    <th>{{ $field->label }}</th>
@endforeach
