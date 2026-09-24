{{--
  Created on   : Thu Sep 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : custom-fields-card.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Detailkarte „Weitere Felder" (MVP-868); ohne Schema kein Markup.
--}}
@props([
    'subject',               // Träger mit HasCustomFields
])

@php
    $schema = $subject->customFieldSchema();
    $values = $subject->customValues();
@endphp
@if (! $schema->isEmpty())
    <x-card :title="__('fields.custom.legend')" icon="dashboard_customize">
        <x-detail-grid>
            @foreach ($schema as $field)
                @continue(! $field->type->hasValue())
                <x-detail-grid.row :label="$field->label . ($field->unit !== null ? ' (' . $field->unit . ')' : '')">
                    <x-field-display :field="$field" :values="$values" />
                </x-detail-grid.row>
            @endforeach
        </x-detail-grid>
    </x-card>
@endif
