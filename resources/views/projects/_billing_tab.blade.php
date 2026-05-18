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
                            <form method="POST" action="{{ route('projects.billing-rules.destroy', [$project, $rule]) }}" class="inline"
                                  data-confirm-dialog
                                  data-confirm-message="{{ __('Regel wirklich löschen?') }}"
                                  data-confirm-icon="delete"
                                  data-confirm-tone="error"
                                  data-confirm-label="{{ __('Löschen') }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-xs btn-ghost text-error" type="submit">
                                    {{ __('Löschen') }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif

        <div class="divider"></div>

        <div class="flex justify-end">
            <a href="{{ route('projects.billing-rules.create', $project) }}" data-entry-modal-trigger class="btn btn-sm btn-primary">
                + {{ __('Neue Regel hinzufügen') }}
            </a>
        </div>
    </div>
</div>
