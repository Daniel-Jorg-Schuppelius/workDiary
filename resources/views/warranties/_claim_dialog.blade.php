{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _claim_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Rüge zur Gewährleistungsfrist erfassen (MVP-1564, in #entry-modal geladen).
     Variablen: $period, $claims (null = Reklamationsmodul aus oder ohne Leserecht) --}}
<x-modal
    :title="__('warranty.claim.title')"
    :eyebrow="implode(' · ', array_filter([$period->project?->name, $period->partyLabel() !== '—' ? $period->partyLabel() : null, $period->trade]))"
    icon="report"
    tone="warning"
    :action="route('warranties.claim', $period)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    submit-class="btn-warning"
    :submit-label="__('warranty.claim.submit')">

    <x-form-group :legend="__('warranty.claim.legend')" icon="report" tone="warning" cols="1" :description="__('warranty.claim.hint', ['date' => $period->ends_on->fdate()])">
        @if ($claims !== null)
            <x-select-field name="claim_case_id" id="w-claim-case" :label="__('warranty.claim.case')" :hint="__('warranty.claim.case_hint')">
                <option value="">—</option>
                @foreach ($claims as $claim)
                    <option value="{{ $claim->sqid }}" @selected(old('claim_case_id') === $claim->sqid)>{{ $claim->number }} · {{ $claim->title }}</option>
                @endforeach
            </x-select-field>
        @endif
    </x-form-group>
</x-modal>
