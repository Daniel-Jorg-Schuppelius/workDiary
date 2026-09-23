{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _review_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Altersbedingter Tarifwechsel (in #entry-modal geladen). Variablen: $account, $assignment, $suggested|null, $tariffs, $today --}}
<x-modal
    :title="__('club.fees.action.review')"
    :eyebrow="($assignment->member?->fullName() ?? '') . ' · ' . ($assignment->tariff?->name ?? '')"
    icon="rule"
    tone="warning"
    :action="route('club.fees.assignments.review', [$account, $assignment])"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.confirm')">

    <x-form-group :legend="__('club.fees.label.review_required')" icon="rule" tone="warning" cols="2" :description="$assignment->review_note ?? ''">
        <x-select-field name="decision" :label="__('club.fees.field.decision')" required span="2">
            <option value="change" @selected(old('decision', 'change') === 'change')>{{ __('club.fees.label.decision_change') }}</option>
            <option value="keep" @selected(old('decision') === 'keep')>{{ __('club.fees.label.decision_keep') }}</option>
        </x-select-field>
        <x-select-field name="club_fee_tariff_id" :label="__('club.fees.field.tariff')">
            <option value="">–</option>
            @foreach ($tariffs as $tariff)
                <option value="{{ $tariff->sqid }}" @selected((string) old('club_fee_tariff_id', $suggested?->sqid ?? '') === $tariff->sqid)>{{ $tariff->name }}@if ($tariff->hasAgeCriteria()) ({{ $tariff->min_age ?? '–' }}–{{ $tariff->max_age ?? '–' }})@endif</option>
            @endforeach
        </x-select-field>
        <x-input-field name="effective_on" type="date" :label="__('club.field.effective_on')" :value="old('effective_on', $today->addMonthNoOverflow()->startOfMonth()->toDateString())" :hint="__('club.fees.hint.effective_on')" />
    </x-form-group>
</x-modal>
