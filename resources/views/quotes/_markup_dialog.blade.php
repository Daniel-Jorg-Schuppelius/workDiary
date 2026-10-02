{{--
  Created on   : Fri Oct 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _markup_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Zuschlag verteilen (MVP-1055). Variablen: $quote, $titles --}}
<x-modal
    :title="__('article.calculation.markup_title')"
    :eyebrow="$quote->number"
    icon="percent"
    tone="primary"
    :action="route('quotes.markup', $quote)"
    method="POST"
    :submit-label="__('article.calculation.markup_apply')"
    size="sm">
    <x-form-group :legend="__('article.calculation.markup_title')" icon="percent" tone="primary" cols="1">
        <p class="text-xs text-muted">{{ __('article.calculation.markup_hint') }}</p>
        <x-input-field name="markup_percent" type="number" :label="__('article.calculation.field.markup_percent')" required min="-99" max="500" step="0.01" :value="old('markup_percent', '')" />
        @if ($titles->isNotEmpty())
            <x-select-field name="title_id" :label="__('article.calculation.field.markup_scope')">
                <option value="">{{ __('article.calculation.markup_all') }}</option>
                @foreach ($titles as $title)
                    <option value="{{ $title->sqid }}">{{ $title->description }}</option>
                @endforeach
            </x-select-field>
        @endif
    </x-form-group>
    <x-validation-errors />
</x-modal>
