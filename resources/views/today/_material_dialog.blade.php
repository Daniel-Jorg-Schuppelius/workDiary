{{--
  Created on   : Wed Nov 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _material_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Material auf „Heute“ (MVP-1594): Standalone-Dialog in der Seite, damit er
     auch ohne Verbindung öffnet; offline geht das Formular als Befehl
     `material.usage` in die Warteschlange. Erwartet $day, $entryBarProjects, $dayMaterials. --}}
<x-modal id="today-material-dialog"
         :embedded="false"
         icon="category"
         tone="primary"
         :eyebrow="__('Verbrauchsmaterial')"
         :title="__('timesheet.day_material.title')"
         :action="route('today.material.store')"
         method="POST"
         :form-data="['data-offline-sync' => 'material.usage']"
         :submitLabel="__('Hinzufügen')">
    <input type="hidden" name="date" value="{{ $day->toDateString() }}" />

    <p class="text-sm text-muted">{{ __('timesheet.day_material.hint') }}</p>

    <x-form-group :legend="__('Material')" icon="category" tone="primary" cols="2">
        <x-select-field span="2" name="project_id" id="today-material-project" :label="__('Projekt')" required>
            <option value="">—</option>
            @foreach ($entryBarProjects as $p)
                <option value="{{ $p->sqid }}">{{ $p->name }}@if ($p->customer) · {{ $p->customer->name }}@endif</option>
            @endforeach
        </x-select-field>
        <x-select-field span="2" name="material_id" id="today-material-material" :label="__('Aus Stamm (optional)')">
            <option value="">— {{ __('frei') }} —</option>
            @foreach ($dayMaterials as $m)
                <option value="{{ $m->sqid }}" data-prefill-unit="{{ $m->unit }}" data-prefill-description="{{ $m->name }}">{{ $m->name }} ({{ $m->unit }})</option>
            @endforeach
        </x-select-field>
        <x-input-field span="2" name="description" id="today-material-description" :label="__('Bezeichnung')" type="text" required maxlength="255"
                       data-prefill-from="material_id" data-prefill-key="description" />
        <x-input-field name="quantity" id="today-material-quantity" :label="__('Menge')" type="number" value="1" required step="0.001" min="0.001" />
        <x-input-field name="unit" id="today-material-unit" :label="__('Einheit')" type="text" value="Stk." required maxlength="20"
                       data-prefill-from="material_id" data-prefill-key="unit" data-prefill-default="Stk." />
    </x-form-group>
</x-modal>
