{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _rules.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Mietpreisregeln einer Preisliste (MVP-950). Erwartet: $card --}}
@php($isDraft = $card->status === \App\Enums\Rental\RentalRateCardStatus::Draft)
<div class="border-t border-base-300 p-3">
    <h3 class="mb-1 text-sm font-semibold">{{ __('rental.rule.title') }}</h3>
    @if ($card->rules->isEmpty())
        <p class="text-xs text-muted">{{ __('rental.rule.empty') }}</p>
    @else
        <ul class="text-sm">
            @foreach ($card->rules as $rule)
                <li class="flex items-center justify-between gap-2">
                    <span>
                        {{ $rule->label }} · {{ $rule->kind->label() }} ·
                        @if ($rule->kind === \App\Enums\Rental\RentalRateRuleKind::Season)
                            {{ $rule->valid_from?->format('d.m.Y') }} – {{ $rule->valid_until?->format('d.m.Y') ?? '…' }}
                        @elseif ($rule->kind === \App\Enums\Rental\RentalRateRuleKind::Weekday)
                            {{ collect($rule->weekdays)->map(fn ($d) => __('rental.rule.weekday.' . $d))->implode(', ') }}
                        @else
                            {{ __('rental.rule.from_utilization', ['percent' => $rule->utilization_min_percent]) }}
                        @endif
                        · <span class="font-mono">{{ (float) $rule->adjust_percent > 0 ? '+' : '' }}{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($rule->adjust_percent, 2) }} %</span>
                    </span>
                    @can('update', $card)
                        @if ($isDraft)
                            <form method="POST" action="{{ route('rental.rates.rules.destroy', [$card, $rule]) }}" class="inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-xs btn-ghost text-error">{{ __('Entfernen') }}</button>
                            </form>
                        @endif
                    @endcan
                </li>
            @endforeach
        </ul>
    @endif
    @can('update', $card)
        @if ($isDraft)
            <details class="mt-2">
                <summary class="cursor-pointer text-sm font-medium">{{ __('rental.rule.add') }}</summary>
                <form method="POST" action="{{ route('rental.rates.rules.store', $card) }}" class="mt-2 flex flex-wrap items-end gap-2">
                    @csrf
                    <x-select-field name="kind" :id="'rule-kind-' . $card->sqid" :label="__('rental.rule.field.kind')" required>
                        @foreach (\App\Enums\Rental\RentalRateRuleKind::cases() as $kind)
                            <option value="{{ $kind->value }}">{{ $kind->label() }}</option>
                        @endforeach
                    </x-select-field>
                    <x-input-field name="label" :id="'rule-label-' . $card->sqid" :label="__('rental.rule.field.label')" required />
                    <x-input-field name="valid_from" :id="'rule-from-' . $card->sqid" type="date" :label="__('rental.rule.field.valid_from')" />
                    <x-input-field name="valid_until" :id="'rule-until-' . $card->sqid" type="date" :label="__('rental.rule.field.valid_until')" />
                    <fieldset class="flex items-center gap-1 text-xs">
                        <legend class="sr-only">{{ __('rental.rule.field.weekdays') }}</legend>
                        @foreach (range(1, 7) as $day)
                            <label class="flex items-center gap-0.5"><input type="checkbox" class="checkbox checkbox-xs" name="weekdays[]" value="{{ $day }}">{{ __('rental.rule.weekday.' . $day) }}</label>
                        @endforeach
                    </fieldset>
                    <x-input-field name="utilization_min_percent" :id="'rule-util-' . $card->sqid" type="number" min="1" max="100" :label="__('rental.rule.field.utilization_min_percent')" />
                    <x-input-field name="adjust_percent" :id="'rule-adjust-' . $card->sqid" type="number" step="0.01" :label="__('rental.rule.field.adjust_percent')" required />
                    <button type="submit" class="btn btn-sm">{{ __('rental.rule.add') }}</button>
                </form>
            </details>
        @endif
    @endcan
</div>
