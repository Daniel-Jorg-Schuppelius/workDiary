{{--
  Created on   : Wed Oct 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _request_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Tauschantrag (Feature 007): eigene kommende Schicht abgeben, optional an
  eine Wunschperson und eine Gegenschicht. Wirksam erst nach Freigabe durch die Teamleitung.
--}}
<x-modal
    :title="__('schedule.exchange.request_title')"
    :eyebrow="__('schedule.exchange.title')"
    icon="swap_horiz"
    tone="primary"
    size="md"
    :action="route('schedule.exchanges.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('schedule.exchange.request_action')">
    <p class="mb-2 text-sm text-muted">{{ __('schedule.exchange.request_hint') }}</p>
    <x-select-field name="scheduled_shift_id" id="exchange-shift" :label="__('schedule.exchange.field_shift')" required>
        @foreach ($shifts as $shift)
            <option value="{{ $shift->sqid }}" @selected((string) old('scheduled_shift_id') === $shift->sqid)>{{ $shift->date?->fdate() }} · {{ $shift->shiftType?->name ?? '—' }}</option>
        @endforeach
    </x-select-field>
    <x-select-field name="target_user_id" id="exchange-target" :label="__('schedule.exchange.field_target')">
        <option value="">{{ __('schedule.exchange.open_target') }}</option>
        @foreach ($colleagues as $colleague)
            <option value="{{ $colleague->sqid }}" @selected((string) old('target_user_id') === $colleague->sqid)>{{ $colleague->name }}</option>
        @endforeach
    </x-select-field>
    {{-- Echter Tausch (MVP-1553): Gegenschicht der Tauschperson; sie legt die Person fest, wenn keine gewählt ist. --}}
    <x-select-field name="offered_shift_id" id="exchange-offered" :label="__('schedule.exchange.field_offered')"
                    :hint="__('schedule.exchange.field_offered_hint')">
        <option value="">{{ __('schedule.exchange.no_offered') }}</option>
        @foreach ($offerableShifts as $colleagueName => $colleagueShifts)
            <optgroup label="{{ $colleagueName }}">
                @foreach ($colleagueShifts as $offered)
                    <option value="{{ $offered->sqid }}" @selected((string) old('offered_shift_id') === $offered->sqid)>{{ $offered->date?->fdate() }} · {{ $offered->shiftType?->name ?? '—' }}</option>
                @endforeach
            </optgroup>
        @endforeach
    </x-select-field>
    <x-input-field name="reason" id="exchange-reason" :label="__('schedule.exchange.field_reason')" maxlength="255" :value="old('reason')" />
</x-modal>
