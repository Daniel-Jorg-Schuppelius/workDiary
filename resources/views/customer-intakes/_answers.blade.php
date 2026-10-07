{{--
  Created on   : Wed Oct 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _answers.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Eingefrorene Formularangaben (FieldDocument) — intern und im Portal. Erwartet: $document --}}
@if ($document !== null && ! $document->schema->isEmpty())
    <x-detail-grid class="grid-cols-1 sm:grid-cols-2">
        @foreach ($document->schema->visibleFor($document->values->toArray()) as $field)
            @if ($field->type->hasValue())
                <x-detail-grid.row :label="$field->label"><x-field-display :field="$field" :values="$document->values" /></x-detail-grid.row>
            @endif
        @endforeach
    </x-detail-grid>
@endif
