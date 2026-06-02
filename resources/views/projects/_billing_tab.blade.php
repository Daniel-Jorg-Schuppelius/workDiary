@php
    /** @var \App\Models\Project $project */
    $project = $project ?? null;
    $rules = $project?->billingRules()->orderByDesc('priority')->orderBy('id')->get() ?? collect();
    $articles = \App\Models\LexofficeArticle::active()->orderBy('name')->get(['external_id', 'name', 'unit_name', 'net_unit_price', 'vat_rate']);
    $kinds = \App\Enums\TimeEntry\TimeEntryKind::values();
    $itemTypes = ['service' => __('Dienstleistung'), 'material' => __('Material'), 'custom' => __('Sonstige')];

    $parentRules = collect();
    $cursor = $project?->parent;
    while ($cursor) {
        foreach ($cursor->billingRules as $r) {
            $parentRules->push($r);
        }
        $cursor = $cursor->parent;
    }

    $increment = $project?->billing_increment_minutes;
    $gap = $project?->billing_grouping_gap_minutes;
    $effectiveIncrement = $project?->effectiveBillingIncrement() ?? 1;
    $effectiveGap = $project?->effectiveBillingGroupingGap() ?? 0;
    $presetIncrements = [1 => __('Jede angefangene Minute'), 15 => __('Viertelstunde'), 30 => __('Halbe Stunde'), 60 => __('Stunde')];
    $isPreset = $increment === null || array_key_exists((int) $increment, $presetIncrements);
@endphp

@if ($project)
<div class="card bg-base-100 shadow mb-4" x-data="{ inc: '{{ $increment === null ? '' : ($isPreset ? (int) $increment : 'custom') }}' }">
    <div class="card-body space-y-4">
        <h2 class="card-title">{{ __('Taktung & Zusammenfassung') }}</h2>
        <p class="text-sm opacity-70">
            {{ __('Abrechenbare Zeit wird auf die Taktung aufgerundet (jede angefangene Einheit zählt voll). Liegen Einträge desselben Projekts höchstens die eingestellte Lücke auseinander, werden sie zu einem Block zusammengefasst und gemeinsam einmal aufgerundet.') }}
        </p>

        <form method="POST" action="{{ route('projects.billing-settings.update', $project) }}" class="space-y-4">
            @csrf
            @method('PATCH')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="fieldset">
                    <label class="fieldset-label">{{ __('Taktung') }}</label>
                    <select class="select select-bordered w-full" x-model="inc"
                            x-bind:name="inc === 'custom' ? 'billing_increment_select' : 'billing_increment_minutes'">
                        <option value="">{{ __('Erben (aktuell: :min Min)', ['min' => $effectiveIncrement]) }}</option>
                        @foreach ($presetIncrements as $min => $label)
                            <option value="{{ $min }}" @selected($isPreset && (int) $increment === $min)>{{ $label }} ({{ $min }} Min)</option>
                        @endforeach
                        <option value="custom" @selected(!$isPreset)>{{ __('Eigener Wert…') }}</option>
                    </select>
                    <input type="number" name="billing_increment_minutes_custom" min="1" max="1440" step="1"
                           value="{{ $isPreset ? '' : (int) $increment }}"
                           x-show="inc === 'custom'" x-cloak
                           placeholder="{{ __('Minuten') }}"
                           class="input input-bordered w-full mt-2"
                           x-bind:name="inc === 'custom' ? 'billing_increment_minutes' : 'billing_increment_minutes_custom'">
                </div>

                <div class="fieldset">
                    <label class="fieldset-label">{{ __('Max. Lücke zum Zusammenfassen (Min)') }}</label>
                    <input type="number" name="billing_grouping_gap_minutes" min="0" max="1440" step="1"
                           value="{{ $gap === null ? '' : (int) $gap }}"
                           placeholder="{{ __('Erben (aktuell: :min)', ['min' => $effectiveGap]) }}"
                           class="input input-bordered w-full">
                    <p class="text-xs opacity-60 mt-1">{{ __('0 = keine Zusammenfassung. Leer = erben.') }}</p>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary btn-sm">
                    <span class="material-symbols-outlined" aria-hidden="true">save</span>
                    <span>{{ __('Taktung speichern') }}</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endif

<div class="card bg-base-100 shadow">
    <div class="card-body space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="card-title">{{ __('Abrechnungs-Regeln (Lexoffice)') }}</h2>
        </div>

        <p class="text-sm opacity-70">
            {{ __('Pro Tätigkeitsart (kind) lässt sich festlegen, welcher Lexoffice-Artikel beim Rechnungs-Export verwendet wird. Ohne kind = Fallback für alle Einträge. Sub-Projekte erben Regeln vom Parent, können sie aber überschreiben.') }}
        </p>

        @if ($parentRules->isNotEmpty())
            <div class="alert alert-info text-sm">
                {{ __('Erbt :count Regel(n) vom Parent-Projekt.', ['count' => $parentRules->count()]) }}
            </div>
        @endif

        @if ($rules->isEmpty())
            <p class="italic opacity-60">{{ __('Noch keine Regeln definiert. Beim Rechnungs-Export wird der Default-Stundensatz genommen.') }}</p>
        @else
            <x-table table-sort="client">
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Art') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Lexoffice-Artikel') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Item-Typ') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Einheit') }}</x-table.th>
                        <x-table.th sort type="number">{{ __('VAT %') }}</x-table.th>
                        <x-table.th sort type="number">{{ __('Preis (netto)') }}</x-table.th>
                        <x-table.th sort type="number">{{ __('Prio') }}</x-table.th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @foreach ($rules as $rule)
                    <tr>
                        <td>
                            @if ($rule->applies_to_kind)
                                <span class="badge">{{ $rule->applies_to_kind }}</span>
                            @else
                                <x-status-badge tone="ghost">{{ __('Alle (Fallback)') }}</x-status-badge>
                            @endif
                        </td>
                        <td>
                            @if ($rule->lexoffice_article_id)
                                @php $art = $articles->firstWhere('external_id', $rule->lexoffice_article_id); @endphp
                                {{ $art?->name ?? $rule->lexoffice_article_id }}
                            @else
                                <span class="opacity-60">—</span>
                            @endif
                        </td>
                        <td>{{ $itemTypes[$rule->item_type] ?? $rule->item_type }}</td>
                        <td>{{ $rule->unit_name ?: '—' }}</td>
                        <td>{{ $rule->vat_rate !== null ? rtrim(rtrim((string) $rule->vat_rate, '0'), '.') : '—' }}</td>
                        <td>{{ $rule->net_unit_price !== null ? number_format((float) $rule->net_unit_price, 2, ',', '.') : '—' }}</td>
                        <td>{{ $rule->priority }}</td>
                        <td class="text-right">
                            <form method="POST" action="{{ route('projects.billing-rules.destroy', [$project, $rule]) }}" class="inline"
                                  data-confirm-dialog
                                  data-confirm-message="{{ __('Regel wirklich löschen?') }}"
                                  data-confirm-icon="delete"
                                  data-confirm-tone="error"
                                  data-confirm-label="{{ __('Löschen') }}">
                                @csrf
                                @method('DELETE')
                                <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                            </form>
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif

        <div class="divider"></div>

        <div class="flex justify-end">
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('projects.billing-rules.create', $project)"
                        show-label>{{ __('Neue Regel hinzufügen') }}</x-icon-btn>
        </div>
    </div>
</div>
