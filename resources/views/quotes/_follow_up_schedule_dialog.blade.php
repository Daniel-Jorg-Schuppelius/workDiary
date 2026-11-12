{{--
  Created on   : Mon Oct 05 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _follow_up_schedule_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Nachfasstermin setzen oder verschieben (Feature 112, MVP-601) — ohne
  Gesprächsergebnis; das protokolliert der Nachbar-Dialog.
--}}
<x-modal
    :title="__('quotes.follow_up.schedule_title', ['number' => $quote->number])"
    :eyebrow="__('quotes.follow_up.title')"
    icon="event"
    :action="route('quotes.follow-ups.schedule', $quote)"
    method="POST"
    :submit-label="__('quotes.follow_up.schedule_submit')"
>
    <p class="text-sm text-base-content/70">{{ __('quotes.follow_up.schedule_hint') }}</p>

    {{-- Ein überfälliger Termin wird nicht vorbelegt: Die Prüfung verlangt heute oder später. --}}
    <x-input-field name="follow_up_at" type="date" required :min="today()->toDateString()"
                   :label="__('quotes.follow_up.column.follow_up_at')"
                   :value="old('follow_up_at', $quote->follow_up_at?->gte(today()) ? $quote->follow_up_at->toDateString() : '')" />
    @php($owner = (int) ($quote->follow_up_user_id ?? $quote->created_by ?? 0))
    <x-select-field name="follow_up_user_id" :label="__('quotes.follow_up.owner_field')" :hint="__('quotes.follow_up.owner_hint')">
        @foreach ($followUpUsers as $followUpUser)
            <option value="{{ $followUpUser->sqid }}" @selected((string) old('follow_up_user_id', $owner === (int) $followUpUser->id ? $followUpUser->sqid : '') === $followUpUser->sqid)>{{ $followUpUser->name }}</option>
        @endforeach
    </x-select-field>
</x-modal>
