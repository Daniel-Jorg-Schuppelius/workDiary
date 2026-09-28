{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _allocation_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Umlageschlüssel anlegen oder ändern (MVP-982). Variablen: $year, $costCenters
--}}
<x-modal
    :title="__('accounting.allocation.action.add')"
    :eyebrow="(string) $year"
    icon="call_split"
    :action="route('reports.accounting.allocations.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">
    <input type="hidden" name="year" value="{{ $year }}">
    <x-select-field name="source" :label="__('accounting.allocation.field.source')" required>
        @foreach ($costCenters as $center)
            <option value="{{ $center->sqid }}" @selected(old('source') === $center->sqid)>{{ $center->code }} · {{ $center->label }}</option>
        @endforeach
    </x-select-field>
    <x-select-field name="target" :label="__('accounting.allocation.field.target')" required>
        @foreach ($costCenters as $center)
            <option value="{{ $center->sqid }}" @selected(old('target') === $center->sqid)>{{ $center->code }} · {{ $center->label }}</option>
        @endforeach
    </x-select-field>
    <x-input-field name="share_percent" type="number" min="0.01" max="100" step="0.01" inputmode="decimal" required
                   :label="__('accounting.allocation.field.share_percent')" :value="old('share_percent')" />
</x-modal>
