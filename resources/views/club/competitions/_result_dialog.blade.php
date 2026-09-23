{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _result_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Ergebnis zu einer Meldung erfassen (in #entry-modal geladen). Variablen: $event, $entry (mit member), $competition, $discipline. --}}
<x-modal
    :title="__('club.competitions.action.record_result')"
    :eyebrow="$entry->member?->fullName() . ' · ' . ($discipline['label'] ?? $entry->discipline_code)"
    icon="scoreboard"
    tone="primary"
    :action="route('club.competitions.entries.result', [$event, $entry])"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.save')">
    <x-form-group :legend="__('club.competitions.card.results')" icon="scoreboard" tone="primary" cols="2" :description="__('club.competitions.hint.result', ['direction' => ($discipline['lower_is_better'] ?? false) ? __('club.competitions.label.lower_is_better') : __('club.competitions.label.higher_is_better')])">
        <x-input-field name="value" :label="__('club.competitions.field.value') . (($discipline['unit'] ?? null) ? ' (' . $discipline['unit'] . ')' : '')" required maxlength="20" :value="old('value')" :hint="__('club.competitions.hint.value')" />
        <x-input-field name="placement" type="number" min="1" max="9999" :label="__('club.competitions.field.placement')" :value="old('placement')" />
        <x-input-field name="note" :label="__('club.field.notes')" maxlength="255" span="2" :value="old('note')" />
        <x-checkbox-field name="confirm" :label="__('club.competitions.field.confirm')" :checked="(bool) old('confirm', true)" span="2" />
    </x-form-group>
</x-modal>
