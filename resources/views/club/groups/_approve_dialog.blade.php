{{--
  Created on   : Tue Sep 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _approve_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Antrag freigeben (in #entry-modal geladen) — Kriterien und Kapazität werden
  serverseitig geprüft; Ausnahme nur mit Recht und Begründung.
  Variablen: $group, $membership (mit member), $today, $canOverride, $result (ClubCriteriaResult)
--}}
<x-modal
    :title="__('club.action.approve')"
    :eyebrow="$group->name . ' · ' . $membership->member?->fullName()"
    icon="how_to_reg"
    tone="primary"
    :action="route('club.groups.memberships.approve', [$group, $membership])"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.approve')">

    <x-form-group :legend="__('club.label.criteria')" icon="rule" tone="primary" cols="1">
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <x-status-badge :tone="$result->tone()" size="sm">{{ $result->label() }}</x-status-badge>
            @if ($group->ageRangeLabel())
                <span class="text-muted">{{ $group->ageRangeLabel() }}</span>
            @endif
        </div>
        <x-input-field name="note" :label="__('club.field.note')" maxlength="255" :value="old('note', $membership->note)" />
        @if ($canOverride && ! $result->isMet())
            <x-checkbox-field name="override" :label="__('club.field.override')" :checked="(bool) old('override', false)" :hint="__('club.hint.override')" />
        @endif
    </x-form-group>
</x-modal>
