{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _accept_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Vorschlag übernehmen (in #entry-modal geladen): Gegner, Ort und Zeit vor der Anlage prüfen. Variablen: $proposal, $formTz, $start, $end, $leaders, $rooms. --}}
<x-modal
    :title="__('club.matches.action.accept')"
    :eyebrow="$proposal->team?->name"
    icon="check"
    tone="primary"
    size="wide"
    :action="route('club.matches.proposals.accept', $proposal)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.matches.action.accept')">
    @if ($proposal->duplicateEvent)
        <div class="alert alert-warning mb-3 text-sm" role="status"><x-icon name="warning" /><span>{{ __('club.matches.label.duplicate_of', ['title' => $proposal->duplicateEvent->title]) }}</span></div>
    @endif
    <x-form-group :legend="__('club.matches.card.match')" icon="sports_soccer" tone="primary" cols="2">
        <x-input-field name="opponent_name" :label="__('club.matches.field.opponent')" required maxlength="150" :value="old('opponent_name', $proposal->opponent_name)" />
        <x-input-field name="competition" :label="__('club.matches.field.competition')" maxlength="120" :value="old('competition', $proposal->competition)" />
        <x-checkbox-field name="is_home" :label="__('club.matches.field.is_home')" :checked="(bool) old('is_home', $proposal->is_home)" />
        <x-input-field name="venue" :label="__('club.matches.field.venue')" maxlength="200" :value="old('venue', $proposal->venue)" />
        <x-date-range class="md:col-span-2" layout="split" form-control required type="datetime-local" from-name="started_at" to-name="ended_at"
                      :from-label="__('club.events.field.starts')" :to-label="__('club.events.field.ends')"
                      :from="old('started_at', $start)" :to="old('ended_at', $end)" />
        <input type="hidden" name="timezone" value="{{ old('timezone', $formTz) }}">
        <x-select-field name="room_id" :label="__('club.events.field.room')">
            <option value="">–</option>
            @foreach ($rooms as $room)
                <option value="{{ $room->sqid }}" @selected(old('room_id') === $room->sqid)>{{ $room->name }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="leader_user_id" :label="__('club.events.field.leader')">
            <option value="">–</option>
            @foreach ($leaders as $leader)
                <option value="{{ $leader->sqid }}" @selected(old('leader_user_id') === $leader->sqid)>{{ $leader->name }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>
</x-modal>
