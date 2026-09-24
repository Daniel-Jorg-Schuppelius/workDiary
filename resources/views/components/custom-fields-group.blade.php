{{--
  Created on   : Thu Sep 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : custom-fields-group.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Formularabschnitt „Weitere Felder" (MVP-868): Eingaben custom[<key>] nach
  dem Schema der Organisation für den Träger; ohne Schema kein Markup.
--}}
@props([
    'model',                 // Trägerklasse (class-string) — Schema der aktuellen Organisation
    'subject' => null,       // vorhandener Datensatz (Werte), null beim Anlegen
])

@php
    $organizationId = $subject?->getAttribute('organization_id') ?? auth()->user()?->getAttribute('organization_id');
    $schema = app(\App\Services\Fields\CustomFieldService::class)->schemaFor($model, $organizationId !== null ? (int) $organizationId : null);
    $values = $subject?->customValues() ?? new \App\Services\Fields\FieldValues;
@endphp
@if (! $schema->isEmpty())
    <x-form-group :legend="__('fields.custom.legend')" icon="dashboard_customize" tone="ghost" cols="2">
        @foreach ($schema as $field)
            <x-field-input :field="$field" prefix="custom" :value="$values->get($field->key)" />
        @endforeach
    </x-form-group>
@endif
