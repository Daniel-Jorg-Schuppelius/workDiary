{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _proposal_fields.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Gemeinsame Felder eines Investitionsvorschlags (MVP-936) --}}
<x-input-field name="title" :label="__('investment.proposal.field.title')" :value="old('title')" required span="2" />
<x-select-field name="category" :label="__('Kategorie')" required>
    @foreach (\App\Models\Investments\InvestmentCase::CATEGORIES as $category)
        <option value="{{ $category }}" @selected(old('category', 'replacement') === $category)>{{ __("values.$category") }}</option>
    @endforeach
</x-select-field>
<x-select-field name="urgency" :label="__('Dringlichkeit')" required>
    @foreach (['low', 'medium', 'high'] as $urgency)
        <option value="{{ $urgency }}" @selected(old('urgency', 'medium') === $urgency)>{{ __("values.$urgency") }}</option>
    @endforeach
</x-select-field>
<x-input-field name="estimated_amount" type="number" step="0.01" min="0" :label="__('investment.proposal.field.estimated_amount')" :value="old('estimated_amount')" />
<x-textarea-field name="reason" :label="__('investment.proposal.field.reason')" rows="4" required span="2">{{ old('reason') }}</x-textarea-field>
