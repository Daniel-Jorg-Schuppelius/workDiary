@php
    /** @var \App\Models\Project $project */
    $project = $project ?? null;
    $rules = $project?->billingRules()->orderByDesc('priority')->orderBy('id')->get() ?? collect();
    $articles = \App\Models\LexofficeArticle::active()->orderBy('name')->get(['external_id', 'name', 'unit_name', 'net_unit_price', 'vat_rate']);
    $kinds = \App\Models\TimeEntry::KINDS;
    $itemTypes = ['service' => __('Dienstleistung'), 'material' => __('Material'), 'custom' => __('Sonstige')];

    $parentRules = collect();
    $cursor = $project?->parent;
    while ($cursor) {
        foreach ($cursor->billingRules as $r) {
            $parentRules->push($r);
        }
        $cursor = $cursor->parent;
    }
@endphp

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
            <div class="overflow-x-auto">
                <table class="table table-zebra table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('Art') }}</th>
                            <th>{{ __('Lexoffice-Artikel') }}</th>
                            <th>{{ __('Item-Typ') }}</th>
                            <th>{{ __('Einheit') }}</th>
                            <th>{{ __('VAT %') }}</th>
                            <th>{{ __('Preis (netto)') }}</th>
                            <th>{{ __('Prio') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rules as $rule)
                            <tr>
                                <td>
                                    @if ($rule->applies_to_kind)
                                        <span class="badge">{{ $rule->applies_to_kind }}</span>
                                    @else
                                        <span class="badge badge-ghost">{{ __('Alle (Fallback)') }}</span>
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
                                    <form method="POST" action="{{ route('projects.billing-rules.destroy', [$project, $rule]) }}" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-xs btn-ghost text-error" type="submit"
                                                onclick="return confirm('{{ __('Regel wirklich löschen?') }}')">
                                            {{ __('Löschen') }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="divider"></div>

        <h3 class="font-semibold">{{ __('Neue Regel hinzufügen') }}</h3>
        <form method="POST" action="{{ route('projects.billing-rules.store', $project) }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @csrf
            <div>
                <label class="label"><span class="label-text">{{ __('Tätigkeitsart') }}</span></label>
                <select name="applies_to_kind" class="select select-bordered w-full">
                    <option value="">{{ __('Alle (Fallback)') }}</option>
                    @foreach ($kinds as $k)
                        <option value="{{ $k }}">{{ $k }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label"><span class="label-text">{{ __('Lexoffice-Artikel') }}</span></label>
                <select name="lexoffice_article_id" class="select select-bordered w-full">
                    <option value="">{{ __('— ohne Artikel —') }}</option>
                    @foreach ($articles as $art)
                        <option value="{{ $art->external_id }}">
                            {{ $art->name }}@if ($art->net_unit_price !== null) — {{ number_format((float) $art->net_unit_price, 2, ',', '.') }} €@endif
                        </option>
                    @endforeach
                </select>
                @if ($articles->isEmpty())
                    <p class="text-xs opacity-60 mt-1">
                        {{ __('Noch keine Artikel synchronisiert. Führe :cmd aus.', ['cmd' => 'php artisan lexoffice:sync-articles']) }}
                    </p>
                @endif
            </div>
            <div>
                <label class="label"><span class="label-text">{{ __('Item-Typ') }}</span></label>
                <select name="item_type" class="select select-bordered w-full">
                    @foreach ($itemTypes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label"><span class="label-text">{{ __('Einheit') }}</span></label>
                <input type="text" name="unit_name" class="input input-bordered w-full" placeholder="Stunde">
            </div>
            <div>
                <label class="label"><span class="label-text">{{ __('VAT %') }}</span></label>
                <input type="number" step="0.01" min="0" max="100" name="vat_rate" class="input input-bordered w-full" placeholder="19">
            </div>
            <div>
                <label class="label"><span class="label-text">{{ __('Preis (netto)') }}</span></label>
                <input type="number" step="0.0001" min="0" name="net_unit_price" class="input input-bordered w-full">
            </div>
            <div>
                <label class="label"><span class="label-text">{{ __('Priorität') }}</span></label>
                <input type="number" step="1" min="0" max="1000" name="priority" value="0" class="input input-bordered w-full">
            </div>
            <div class="flex items-end">
                <button type="submit" class="btn btn-primary w-full">{{ __('Regel speichern') }}</button>
            </div>
        </form>
    </div>
</div>
