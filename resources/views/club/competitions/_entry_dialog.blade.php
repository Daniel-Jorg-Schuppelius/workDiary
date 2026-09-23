{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _entry_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Meldung anlegen (in #entry-modal geladen). Variablen: $event, $competition (mit profile), $members. --}}
<x-modal
    :title="__('club.competitions.action.enter')"
    :eyebrow="$event->title"
    icon="how_to_reg"
    tone="primary"
    :action="route('club.competitions.entries.store', $event)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.competitions.action.enter')">
    <x-form-group :legend="__('club.competitions.card.entries')" icon="how_to_reg" tone="primary" cols="2" :description="__('club.competitions.hint.entry')">
        <x-select-field name="club_member_id" :label="__('club.field.member')" required span="2">
            <option value="">–</option>
            @foreach ($members as $member)
                <option value="{{ $member->sqid }}" @selected(old('club_member_id') === $member->sqid)>{{ $member->last_name }}, {{ $member->first_name }} ({{ $member->member_no }})</option>
            @endforeach
        </x-select-field>
        @foreach ($competition->disciplines as $code)
            @php($discipline = $competition->discipline($code))
            <x-checkbox-field name="disciplines[]" :id="'entry-' . $code" :value="$code" :label="($discipline['label'] ?? $code) . (($discipline['unit'] ?? null) ? ' (' . $discipline['unit'] . ')' : '')" :checked="in_array($code, (array) old('disciplines', []), true)" :toggle="false" />
        @endforeach
        <x-checkbox-field name="force" :label="__('club.competitions.field.force')" :checked="(bool) old('force', false)" span="2" />
    </x-form-group>
</x-modal>
