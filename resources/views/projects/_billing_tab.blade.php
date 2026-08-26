{{--
  Created on   : Fri May 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _billing_tab.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Tab: Abrechnung — erwartet: $project, $billingRules, $parentBillingRules, $billingArticles --}}
@php
    $increment = $project->billing_increment_minutes;
    $gap = $project->billing_grouping_gap_minutes;
    $effectiveIncrement = $project->effectiveBillingIncrement() ?? 1;
    $effectiveGap = $project->effectiveBillingGroupingGap() ?? 0;
    $presetIncrements = [1 => __('Jede angefangene Minute'), 15 => __('Viertelstunde'), 30 => __('Halbe Stunde'), 60 => __('Stunde')];
    $isPreset = $increment === null || array_key_exists((int) $increment, $presetIncrements);
    $itemTypes = \App\Models\ProjectBillingRule::itemTypeOptions();
@endphp

<div class="flex flex-col gap-3">
    {{-- Taktung & Zusammenfassung --}}
    <div class="rounded-box border border-base-300 bg-base-100 shadow-xs"
         x-data="reveal('{{ $increment === null ? '' : ($isPreset ? (int) $increment : 'custom') }}')">
        <header class="border-b border-base-300 px-4 py-3">
            <span class="font-['Space_Grotesk'] text-sm font-semibold">{{ __('Taktung & Zusammenfassung') }}</span>
            <p class="mt-0.5 text-xs text-muted">
                {{ __('Abrechenbare Zeit wird auf die Taktung aufgerundet (jede angefangene Einheit zählt voll). Liegen Einträge desselben Projekts höchstens die eingestellte Lücke auseinander, werden sie zu einem Block zusammengefasst und gemeinsam einmal aufgerundet.') }}
            </p>
        </header>

        <form method="POST" action="{{ route('projects.billing-settings.update', $project) }}" class="flex flex-col gap-4 p-4">
            @csrf
            @method('PATCH')

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="fieldset">
                    <label :for="choose('custom', 'billing_increment_select', 'billing_increment_minutes')" class="fieldset-label">{{ __('Taktung') }}</label>
                    <select class="select select-bordered w-full" x-model="value"
                            :id="choose('custom', 'billing_increment_select', 'billing_increment_minutes')" x-bind:name="choose('custom', 'billing_increment_select', 'billing_increment_minutes')">
                        <option value="">{{ __('Erben (aktuell: :min Min)', ['min' => $effectiveIncrement]) }}</option>
                        @foreach ($presetIncrements as $min => $label)
                            <option value="{{ $min }}" @selected($isPreset && (int) $increment === $min)>{{ $label }} ({{ $min }} Min)</option>
                        @endforeach
                        <option value="custom" @selected(!$isPreset)>{{ __('Eigener Wert…') }}</option>
                    </select>
                    <input type="number" name="billing_increment_minutes_custom" min="1" max="1440" step="1"
                           value="{{ $isPreset ? '' : (int) $increment }}"
                           x-show="is('custom')" x-cloak
                           placeholder="{{ __('Minuten') }}"
                           class="input input-bordered mt-2 w-full"
                           x-bind:name="choose('custom', 'billing_increment_minutes', 'billing_increment_minutes_custom')">
                </div>

                <x-input-field name="billing_grouping_gap_minutes"
                               :label="__('Max. Lücke zum Zusammenfassen (Min)')"
                               type="number"
                               value="{{ $gap === null ? '' : (int) $gap }}"
                               :hint="__('0 = keine Zusammenfassung. Leer = erben.')"
                               min="0"
                               max="1440"
                               step="1"
                               placeholder="{{ __('Erben (aktuell: :min)', ['min' => $effectiveGap]) }}" />
            </div>

            <div class="flex justify-end">
                <x-button type="submit" tone="primary" size="sm" icon="save">{{ __('Taktung speichern') }}</x-button>
            </div>
        </form>
    </div>

    {{-- Sätze: Projektstufe der Satzhierarchie (Kunde bzw. Org-Standard erben). --}}
    @php
        $inheritedHourly = $project->customer?->hourly_rate?->toFloat()
            ?? (\App\Support\Setting::get('invoicing.default_hourly_rate') !== null
                ? (float) \App\Support\Setting::get('invoicing.default_hourly_rate') : null);
        $inheritedInternal = $project->customer?->internal_rate?->toFloat();
        $ratePlaceholder = fn(?float $value): string => $value !== null
            ? __('Erben (aktuell: :value)', ['value' => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($value, 2)])
            : __('Erben (kein Satz hinterlegt)');
    @endphp
    <x-card padding="p-0">
        <header class="border-b border-base-300 px-4 py-3">
            <span class="font-['Space_Grotesk'] text-sm font-semibold">{{ __('Sätze') }}</span>
            <p class="mt-0.5 text-xs text-muted">
                {{ __('Gilt für Zeiten dieses Projekts, sofern weder Eintrag, Kundenkondition, Mitarbeiter noch Tätigkeit einen Satz setzen. Leer = Satz des Kunden bzw. der Organisations-Standardsatz.') }}
            </p>
        </header>

        <form method="POST" action="{{ route('projects.rates.update', $project) }}" class="flex flex-col gap-4 p-4">
            @csrf
            @method('PATCH')

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-input-field name="hourly_rate"
                               :label="__('Stundensatz (Erlös)')"
                               type="number"
                               value="{{ old('hourly_rate', $project->hourly_rate?->getAmount()) }}"
                               min="0"
                               max="10000"
                               step="0.01"
                               inputmode="decimal"
                               placeholder="{{ $ratePlaceholder($inheritedHourly) }}" />

                <x-input-field name="internal_rate"
                               :label="__('Interner Satz (Kosten)')"
                               type="number"
                               value="{{ old('internal_rate', $project->internal_rate?->getAmount()) }}"
                               min="0"
                               max="10000"
                               step="0.01"
                               inputmode="decimal"
                               placeholder="{{ $ratePlaceholder($inheritedInternal) }}" />
            </div>

            <div class="flex justify-end">
                <x-button type="submit" tone="primary" size="sm" icon="save">{{ __('Sätze speichern') }}</x-button>
            </div>
        </form>
    </x-card>

    {{-- Abrechnungs-Regeln --}}
    <x-card padding="p-0">
        <header class="flex items-center justify-between gap-3 border-b border-base-300 px-4 py-3">
            <div>
                <span class="font-['Space_Grotesk'] text-sm font-semibold">{{ __('Abrechnungs-Regeln (Lexoffice)') }}</span>
                <p class="mt-0.5 text-xs text-muted">
                    {{ __('Pro Tätigkeitsart lässt sich festlegen, welcher Lexoffice-Artikel beim Rechnungs-Export verwendet wird. Ohne Tätigkeitsart = Fallback für alle Einträge. Sub-Projekte erben Regeln vom Parent, können sie aber überschreiben.') }}
                </p>
            </div>
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('projects.billing-rules.create', $project)"
                        show-label>{{ __('Regel') }}</x-icon-btn>
        </header>

        @if ($parentBillingRules->isNotEmpty())
            <p class="border-b border-base-300 bg-info/5 px-4 py-2 text-xs text-base-content/70">
                {{ __('Erbt :count Regel(n) vom Parent-Projekt.', ['count' => $parentBillingRules->count()]) }}
            </p>
        @endif

        @if ($billingRules->isEmpty())
            <div class="p-4">
                <x-empty-state compact
                    icon="receipt_long"
                    :title="__('Noch keine Regeln definiert.')"
                    :message="__('Beim Rechnungs-Export wird der Default-Stundensatz genommen.')" />
            </div>
        @else
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr class="text-xs text-muted">
                        <x-table.th sort type="string">{{ __('Art') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Lexoffice-Artikel') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Item-Typ') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Einheit') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('USt %') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Preis (netto)') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Prio') }}</x-table.th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @foreach ($billingRules as $rule)
                    <tr class="hover:bg-base-200/50">
                        <td class="text-xs">
                            @if ($rule->applies_to_kind)
                                <x-status-badge size="xs" outline>{{ \App\Enums\TimeEntry\TimeEntryKind::tryFrom($rule->applies_to_kind)?->label() ?? $rule->applies_to_kind }}</x-status-badge>
                            @else
                                <x-status-badge tone="ghost" size="xs">{{ __('Alle (Fallback)') }}</x-status-badge>
                            @endif
                        </td>
                        <td class="text-xs">
                            @if ($rule->lexoffice_article_id)
                                {{ $billingArticles->firstWhere('external_id', $rule->lexoffice_article_id)?->name ?? $rule->lexoffice_article_id }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-xs">{{ $itemTypes[$rule->item_type] ?? $rule->item_type }}</td>
                        <td class="text-xs">{{ $rule->unit_name ?: '—' }}</td>
                        <td class="text-right text-xs tabular-nums">{{ $rule->vat_rate !== null ? rtrim(rtrim(($rule->vat_rate?->getNumericValue() ?? '0'), '0'), '.') : '—' }}</td>
                        <td class="text-right text-xs tabular-nums">{{ $rule->net_unit_price !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($rule->net_unit_price?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) . ' €' : '—' }}</td>
                        <td class="text-right text-xs tabular-nums">{{ $rule->priority }}</td>
                        <td class="whitespace-nowrap text-right">
                            <x-action-form :action="route('projects.billing-rules.destroy', [$project, $rule])" method="DELETE"
                                  :confirm="__('Regel wirklich löschen?')"
                                  confirm-icon="delete"
                                  confirm-tone="error"
                                  :confirm-label="__('Löschen')">
                                <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                            </x-action-form>
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</div>
