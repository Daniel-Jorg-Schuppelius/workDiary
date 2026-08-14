{{--
  Created on   : Fri Aug 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : project-select.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Standard-Projektauswahl als Formularfeld: x-select-field mit
  x-project-options (Kundengruppierung, Endkunden-Suffix). Zusätzliche
  Attribute (x-model, data-depends-on, …) werden ans <select> durchgereicht.
  Für Filterleisten/Sonder-Selects x-project-options direkt einsetzen.
--}}
@props([
    'projects',
    'name' => 'project_id',
    'label' => null,
    'placeholder' => '—', {{-- null ⇒ keine Leer-Option --}}
    'selected' => '',
    'group' => true,
    'dataParent' => false,
    'required' => false,
    'span' => null,
    'hint' => null,
    'error' => null,
])

<x-select-field :name="$name" :label="$label" :required="$required" :span="$span" :hint="$hint" :error="$error" {{ $attributes }}>
    @if ($placeholder !== null)
        <option value="">{{ $placeholder }}</option>
    @endif
    <x-project-options :projects="$projects" :selected="$selected" :group="$group" :data-parent="$dataParent" />
</x-select-field>
