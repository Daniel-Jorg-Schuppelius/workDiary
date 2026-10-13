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

  Props (Kommentare stehen hier statt im Array, sonst meldet die IDE Syntaxfehler):
  - placeholder: null ⇒ keine Leer-Option
  - searchable: Suchfeld überm Select (filtert Optionen + Kunden-Optgroups live,
    app.js data-select-search)
  - recent: Collection<Project> für die Optgroup „Zuletzt verwendet“ zuerst
--}}
@props([
    'projects',
    'name' => 'project_id',
    'label' => null,
    'placeholder' => '—',
    'selected' => '',
    'group' => true,
    'dataParent' => false,
    'required' => false,
    'span' => null,
    'hint' => null,
    'error' => null,
    'searchable' => false,
    'recent' => null,
])

<x-select-field :name="$name" :label="$label" :required="$required" :span="$span" :hint="$hint" :error="$error" {{ $attributes }}>
    @if ($searchable)
        <x-slot:beforeSelect>
            <input type="search" data-select-search="{{ $name }}" autocomplete="off"
                   class="input input-sm input-bordered mb-1 w-full"
                   placeholder="{{ __('Projekt suchen…') }}" aria-label="{{ __('Projekt suchen…') }}">
        </x-slot:beforeSelect>
    @endif
    @if ($placeholder !== null)
        <option value="">{{ $placeholder }}</option>
    @endif
    <x-project-options :projects="$projects" :selected="$selected" :group="$group" :data-parent="$dataParent" :recent="$recent" />
</x-select-field>
