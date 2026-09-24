{{--
  Created on   : Thu Sep 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : custom-field-summary.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Listenfelder als Kurzzeile für Kartenlisten (Aufträge, Welle 4.6).
--}}
@props(['columns' => [], 'model'])
@if ($columns !== [])
    <div {{ $attributes->class('flex flex-wrap gap-x-4 gap-y-1 text-xs text-base-content/70') }}>
        @foreach ($columns as $field)
            <span><span class="text-muted">{{ $field->label }}:</span> <x-field-display :field="$field" :values="$model->customValues()" /></span>
        @endforeach
    </div>
@endif
