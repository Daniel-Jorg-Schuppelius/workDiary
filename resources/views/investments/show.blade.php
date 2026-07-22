@extends('layouts.app')

@section('title', __('Investition: :title', ['title' => $case->title]))
@section('nav-title', $case->title)

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :title="$case->title" :badge="__('values.' . $case->status)" badge-tone="outline">
            <div class="text-sm text-base-content/70">
                {{ __("values.{$case->category}") }} · {{ __('Dringlichkeit: :urgency', ['urgency' => __("values.{$case->urgency}")]) }}
                @if ($case->costCenterDisplay()) · {{ $case->costCenterDisplay() }} @endif
            </div>
            <x-slot:actions>
                @can('update', $case)
                    <x-icon-btn icon="edit" size="sm" data-entry-modal-trigger :href="route('investments.edit', $case)" show-label>{{ __('Bearbeiten') }}</x-icon-btn>
                    @if (in_array($case->status, ['approved', 'in_progress'], true))
                        <x-action-form :action="route('investments.status', $case)">
                            <input type="hidden" name="status" value="{{ $case->status === 'approved' ? 'in_progress' : 'completed' }}">
                            <x-icon-btn icon="task_alt" tone="primary" size="sm" type="submit" show-label>{{ $case->status === 'approved' ? __('Umsetzung starten') : __('Abschließen') }}</x-icon-btn>
                        </x-action-form>
                    @endif
                @endcan
                @can('delete', $case)
                    <x-action-form :action="route('investments.destroy', $case)" method="DELETE"
                          :confirm="__('Akte wirklich löschen?')" confirm-icon="delete" confirm-tone="error" :confirm-label="__('Löschen')">
                        <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Löschen') }}</x-icon-btn>
                    </x-action-form>
                @endcan
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    {{-- Soll-Ist (MVP-205) --}}
    <div class="grid gap-4 sm:grid-cols-4">
        <x-kpi-tile :label="__('Genehmigt')" :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($projection['approved'], 2, withThousandsSeparator: true) . ' €'" />
        <x-kpi-tile :label="__('Gebunden (Bestellungen)')" :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($projection['committed'], 2, withThousandsSeparator: true) . ' €'" />
        <x-kpi-tile :label="__('Ist-Kosten')" :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($projection['actual'], 2, withThousandsSeparator: true) . ' €'" />
        <x-kpi-tile :label="__('Rest')" :value="$projection['remaining'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($projection['remaining'], 2, withThousandsSeparator: true) . ' €' : '—'" />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('Akte')">
            <x-detail-grid>
                <x-detail-grid.row :label="__('Verantwortlich')">{{ $case->responsible->name ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Zeitraum')">{{ optional($case->starts_on)->fdate() ?? '—' }} – {{ optional($case->ends_on)->fdate() ?? '—' }}</x-detail-grid.row>
                @if ($case->project)
                    <x-detail-grid.row :label="__('Projekt')"><a class="link" href="{{ route('projects.show', $case->project) }}">{{ $case->project->name }}</a></x-detail-grid.row>
                @endif
            </x-detail-grid>
            @if ($case->reason)<p class="mt-2 text-sm"><span class="font-semibold">{{ __('Anlass:') }}</span> {{ $case->reason }}</p>@endif
            @if ($case->objective)<p class="mt-1 text-sm"><span class="font-semibold">{{ __('Ziel/Nutzen:') }}</span> {{ $case->objective }}</p>@endif
            @if ($case->risk_note)<p class="mt-1 text-sm"><span class="font-semibold">{{ __('Risiko:') }}</span> {{ $case->risk_note }}</p>@endif
            @can('update', $case)
                @if (in_array($case->status, \App\Models\Investments\InvestmentCase::PLANNING_STATUSES, true))
                    <form method="POST" action="{{ route('investments.status', $case) }}" class="mt-2 flex items-center gap-1">
                        @csrf
                        <select name="status" class="select select-sm select-bordered" data-autosubmit aria-label="{{ __('Status') }}">
                            @foreach (\App\Models\Investments\InvestmentCase::PLANNING_STATUSES as $status)
                                <option value="{{ $status }}" @selected($case->status === $status)>{{ __("values.$status") }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif
            @endcan
        </x-card>

        {{-- Variantenvergleich (MVP-201) --}}
        <x-card :title="__('Variantenvergleich')">
            @can('update', $case)
                @if (in_array($case->status, \App\Models\Investments\InvestmentCase::PLANNING_STATUSES, true))
                    <form method="POST" action="{{ route('investments.options.store', $case) }}" class="mb-3 grid gap-2 sm:grid-cols-2">
                        @csrf
                        <input name="title" required maxlength="200" class="input input-sm input-bordered sm:col-span-2" placeholder="{{ __('Variante (z. B. Angebot Lieferant A)') }}">
                        <select name="supplier_id" class="select select-sm select-bordered">
                            <option value="">{{ __('— Lieferant —') }}</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->sqid }}">{{ $supplier->company ?: $supplier->name }}</option>
                            @endforeach
                        </select>
                        <input name="one_time_cost" type="number" step="0.01" min="0" required class="input input-sm input-bordered" placeholder="{{ __('Einmalkosten €') }}">
                        <input name="recurring_cost_yearly" type="number" step="0.01" min="0" class="input input-sm input-bordered" placeholder="{{ __('Folgekosten €/Jahr') }}">
                        <input name="delivery_weeks" type="number" min="0" class="input input-sm input-bordered" placeholder="{{ __('Lieferzeit (Wochen)') }}">
                        <div class="flex gap-2 sm:col-span-2">
                            <select name="quality_score" class="select select-sm select-bordered" aria-label="{{ __('Qualität') }}">
                                <option value="">{{ __('Qualität') }}</option>
                                @foreach ([5, 4, 3, 2, 1] as $score)<option value="{{ $score }}">{{ str_repeat('★', $score) }}</option>@endforeach
                            </select>
                            <x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>{{ __('Variante erfassen') }}</x-icon-btn>
                        </div>
                    </form>
                @endif
            @endcan
            @if ($case->options->isEmpty())
                <x-empty-state icon="compare" :title="__('Noch keine Varianten.')" compact />
            @else
                <x-table>
                    <x-slot:head>
                            <tr>
                                <th>{{ __('Variante') }}</th>
                                <th class="text-right">{{ __('Einmalig') }}</th>
                                <th class="text-right">{{ __('Folgekosten p. a.') }}</th>
                                <th class="text-right">{{ __('Lieferzeit') }}</th>
                                <th>{{ __('Qualität') }}</th>
                                <th></th>
                            </tr>
                    </x-slot:head>
                            @foreach ($case->options as $option)
                                <tr @class(['bg-success/10' => $option->recommended])>
                                    <td>
                                        {{ $option->title }}
                                        @if ($option->recommended)<span class="badge badge-success badge-xs">{{ __('Empfehlung') }}</span>@endif
                                        @if ($option->supplier)<div class="text-xs text-base-content/60">{{ $option->supplier->company ?: $option->supplier->name }}</div>@endif
                                    </td>
                                    <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $option->one_time_cost, 2, withThousandsSeparator: true) }} €</td>
                                    <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $option->recurring_cost_yearly, 2, withThousandsSeparator: true) }} €</td>
                                    <td class="text-right">{{ $option->delivery_weeks !== null ? __(':count Wo.', ['count' => $option->delivery_weeks]) : '—' }}</td>
                                    <td>{{ $option->quality_score !== null ? str_repeat('★', (int) $option->quality_score) : '—' }}</td>
                                    <td class="whitespace-nowrap text-right">
                                        @can('update', $case)
                                            <x-action-form :action="route('investments.options.recommend', [$case, $option])" class="inline">
                                                <x-icon-btn icon="recommend" size="xs" tone="ghost" type="submit" :title="__('Als Empfehlung markieren')" />
                                            </x-action-form>
                                            <x-action-form :action="route('investments.options.destroy', [$case, $option])" method="DELETE" class="inline">
                                                <x-icon-btn icon="delete" size="xs" tone="error" type="submit" :title="__('Entfernen')" />
                                            </x-action-form>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                </x-table>
            @endif
        </x-card>
    </div>

    {{-- Budgetantrag + Freigabekette (MVP-202/203) --}}
    <x-card :title="__('Budget & Freigaben')">
        @can('update', $case)
            @if (in_array($case->status, \App\Models\Investments\InvestmentCase::PLANNING_STATUSES, true))
                @unless ($hasCostCenters)
                    <div class="alert alert-warning text-sm">
                        <span class="material-symbols-outlined" aria-hidden="true">warning</span>
                        <div class="flex flex-wrap items-center gap-2">
                            {{ __('Noch keine Kostenstellen angelegt — für saubere Budgetauswertung zuerst eine anlegen:') }}
                            <form method="POST" action="{{ route('investments.cost-centers.store') }}" class="flex flex-wrap items-center gap-1">
                                @csrf
                                <input name="code" required maxlength="30" class="input input-xs input-bordered w-24" placeholder="{{ __('Code') }}">
                                <input name="label" required maxlength="200" class="input input-xs input-bordered w-48" placeholder="{{ __('Bezeichnung') }}">
                                <button type="submit" class="btn btn-xs btn-primary">{{ __('Anlegen') }}</button>
                            </form>
                        </div>
                    </div>
                @endunless
                <form method="POST" action="{{ route('investments.budget.submit', $case) }}" class="mb-3 grid gap-2 sm:grid-cols-4">
                    @csrf
                    <input name="amount" type="number" step="0.01" min="0.01" required class="input input-sm input-bordered" placeholder="{{ __('Betrag €') }}">
                    <select name="cost_kind" class="select select-sm select-bordered">
                        <option value="purchase">{{ __('Kauf') }}</option>
                        <option value="leasing">{{ __('Leasing') }}</option>
                        <option value="service">{{ __('Dienstleistung') }}</option>
                        <option value="mixed">{{ __('Gemischt') }}</option>
                    </select>
                    <select name="financing" class="select select-sm select-bordered">
                        <option value="cash">{{ __('Eigenmittel') }}</option>
                        <option value="loan">{{ __('Kredit') }}</option>
                        <option value="leasing">{{ __('Leasingfinanzierung') }}</option>
                        <option value="subsidy">{{ __('Förderung') }}</option>
                        <option value="mixed">{{ __('Gemischt') }}</option>
                    </select>
                    <x-icon-btn icon="request_quote" tone="primary" size="sm" type="submit" show-label>{{ __('Budget beantragen') }}</x-icon-btn>
                    <input name="payment_plan" maxlength="5000" class="input input-sm input-bordered sm:col-span-4" placeholder="{{ __('Zahlungs-/Lieferplan (optional)') }}">
                </form>
            @endif
        @endcan

        @if ($case->budgetRequests->isEmpty())
            <x-empty-state icon="request_quote" :title="__('Noch kein Budgetantrag.')" compact />
        @else
            <div class="space-y-3">
                @foreach ($case->budgetRequests as $request)
                    <div class="rounded-box border border-base-300 p-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">V{{ $request->version }} · {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $request->amount, 2, withThousandsSeparator: true) }} €</span>
                            <x-status-badge size="xs" outline>{{ __("values.{$request->status}") }}</x-status-badge>
                            <span class="text-xs text-base-content/60">{{ __("values.{$request->cost_kind}") }} · {{ __("values.{$request->financing}") }}</span>
                        </div>
                        <div class="mt-1 text-sm">
                            @foreach ($request->approvals->sortBy('step') as $approval)
                                <span class="badge badge-sm {{ $approval->decision === 'approved' ? 'badge-success' : ($approval->decision === 'rejected' ? 'badge-error' : 'badge-ghost') }}">
                                    {{ __('Stufe :step', ['step' => $approval->step]) }}: {{ $approval->decision !== null ? __("values.{$approval->decision}") : __('offen') }}
                                </span>
                            @endforeach
                        </div>
                        @if ($request->status === 'in_approval')
                            @can('approve', $case)
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <x-action-form :action="route('investments.budget.approve', [$case, $request])">
                                        <x-icon-btn icon="verified" tone="success" size="xs" type="submit" show-label>{{ __('Freigeben') }}</x-icon-btn>
                                    </x-action-form>
                                    <form method="POST" action="{{ route('investments.budget.reject', [$case, $request]) }}" class="flex items-center gap-1">
                                        @csrf
                                        <input name="reason" required maxlength="1000" class="input input-xs input-bordered w-56" placeholder="{{ __('Ablehnungsgrund (Pflicht)') }}">
                                        <button type="submit" class="btn btn-xs btn-outline">{{ __('Ablehnen') }}</button>
                                    </form>
                                </div>
                            @endcan
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>

    <div class="grid gap-4 lg:grid-cols-2">
        {{-- Verknüpfungen (MVP-204) --}}
        <x-card :title="__('Umsetzung (Verknüpfungen)')">
            @can('update', $case)
                <form method="POST" action="{{ route('investments.links.store', $case) }}" class="mb-3 flex flex-wrap items-end gap-2">
                    @csrf
                    <select name="linkable_type" class="select select-sm select-bordered">
                        <option value="project">{{ __('Projekt') }}</option>
                        <option value="purchase_order">{{ __('Bestellung') }}</option>
                        <option value="asset">{{ __('Asset') }}</option>
                        <option value="incoming_einvoice">{{ __('Eingangsrechnung') }}</option>
                        <option value="document">{{ __('Dokument') }}</option>
                    </select>
                    <input name="linkable_sqid" required maxlength="64" class="input input-sm input-bordered w-40" placeholder="{{ __('Sqid/ID des Ziels') }}">
                    <x-icon-btn icon="link" tone="primary" size="sm" type="submit" show-label>{{ __('Verknüpfen') }}</x-icon-btn>
                </form>
            @endcan
            @if ($case->links->isEmpty())
                <x-empty-state icon="link" :title="__('Keine Folgeobjekte verknüpft.')" compact />
            @else
                <ul class="space-y-1 text-sm">
                    @foreach ($case->links as $link)
                        <li>
                            <span class="badge badge-outline badge-xs">{{ \App\Support\EntityType::label($link->linkable_type) }}</span>
                            {{ $link->linkable?->getAttribute('title') ?? $link->linkable?->getAttribute('name') ?? $link->linkable?->getAttribute('number') ?? ('#' . $link->linkable_id) }}
                            @if ($link->note)<span class="text-base-content/60">— {{ $link->note }}</span>@endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @can('update', $case)
                <h4 class="mt-4 text-sm font-semibold">{{ __('Ist-Wert manuell erfassen') }}</h4>
                <form method="POST" action="{{ route('investments.actuals.store', $case) }}" class="mt-1 flex flex-wrap items-end gap-2">
                    @csrf
                    <input name="amount" type="number" step="0.01" required class="input input-sm input-bordered w-32" placeholder="{{ __('Betrag €') }}">
                    <input name="occurred_on" type="date" required class="input input-sm input-bordered" value="{{ now()->toDateString() }}">
                    <input name="note" maxlength="500" class="input input-sm input-bordered flex-1" placeholder="{{ __('Anmerkung') }}">
                    <x-icon-btn icon="add" size="sm" type="submit" show-label>{{ __('Erfassen') }}</x-icon-btn>
                </form>
            @endcan
        </x-card>

        {{-- Abweichungen (MVP-206) --}}
        <x-card :title="__('Abweichungen & Nachträge')">
            @can('update', $case)
                <form method="POST" action="{{ route('investments.deviations.store', $case) }}" class="mb-3 flex flex-wrap items-end gap-2">
                    @csrf
                    <select name="kind" class="select select-sm select-bordered">
                        <option value="budget">{{ __('Budget') }}</option>
                        <option value="schedule">{{ __('Termin') }}</option>
                        <option value="scope">{{ __('Umfang') }}</option>
                        <option value="cancellation">{{ __('Abbruch') }}</option>
                    </select>
                    <input name="description" required maxlength="1000" class="input input-sm input-bordered flex-1" placeholder="{{ __('Beschreibung/Begründung') }}">
                    <input name="amount_delta" type="number" step="0.01" class="input input-sm input-bordered w-32" placeholder="{{ __('Δ Betrag €') }}">
                    <x-icon-btn icon="report" tone="warning" size="sm" type="submit" show-label>{{ __('Melden') }}</x-icon-btn>
                </form>
            @endcan
            @if ($case->deviations->isEmpty())
                <x-empty-state icon="report" :title="__('Keine Abweichungen.')" compact />
            @else
                <ul class="space-y-2 text-sm">
                    @foreach ($case->deviations as $deviation)
                        <li class="rounded-box border border-base-300 p-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-status-badge size="xs" outline>{{ __("values.{$deviation->kind}") }}</x-status-badge>
                                <span>{{ $deviation->description }}</span>
                                @if ($deviation->amount_delta !== null)<span class="tabular-nums">Δ {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $deviation->amount_delta, 2, withThousandsSeparator: true) }} €</span>@endif
                                <x-status-badge size="xs" :tone="$deviation->status === 'approved' ? 'success' : ($deviation->status === 'rejected' ? 'error' : 'warning')">{{ __("values.{$deviation->status}") }}</x-status-badge>
                            </div>
                            @if ($deviation->status === 'open')
                                @can('approve', $case)
                                    <form method="POST" action="{{ route('investments.deviations.decide', [$case, $deviation]) }}" class="mt-1 flex flex-wrap items-center gap-1">
                                        @csrf
                                        <select name="decision" class="select select-xs select-bordered">
                                            <option value="approved">{{ __('Genehmigen') }}</option>
                                            <option value="rejected">{{ __('Ablehnen') }}</option>
                                        </select>
                                        <input name="note" maxlength="1000" class="input input-xs input-bordered w-48" placeholder="{{ __('Begründung') }}">
                                        <button type="submit" class="btn btn-xs">{{ __('Entscheiden') }}</button>
                                    </form>
                                @endcan
                            @elseif ($deviation->status === 'approved' && $deviation->kind === 'budget')
                                @can('update', $case)
                                    <form method="POST" action="{{ route('investments.budget.supplement', [$case, $deviation]) }}" class="mt-1 flex flex-wrap items-center gap-1">
                                        @csrf
                                        <input name="amount" type="number" step="0.01" min="0.01" required class="input input-xs input-bordered w-32" placeholder="{{ __('Neues Budget €') }}">
                                        <button type="submit" class="btn btn-xs btn-primary">{{ __('Nachtrag beantragen') }}</button>
                                    </form>
                                @endcan
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>

    {{-- Nachbewertung (MVP-207) --}}
    <x-card :title="__('Nachbewertung')">
        @if ($case->review !== null)
            <x-detail-grid>
                <x-detail-grid.row :label="__('Tatsächlicher Nutzen')">{{ $case->review->benefit_result }}</x-detail-grid.row>
                @if ($case->review->economics_result)<x-detail-grid.row :label="__('Wirtschaftlichkeit')">{{ $case->review->economics_result }}</x-detail-grid.row>@endif
                @if ($case->review->lessons)<x-detail-grid.row :label="__('Lessons Learned')">{{ $case->review->lessons }}</x-detail-grid.row>@endif
                @if ($case->review->follow_up)<x-detail-grid.row :label="__('Folgemaßnahmen')">{{ $case->review->follow_up }}</x-detail-grid.row>@endif
                <x-detail-grid.row :label="__('Bewertet am')">{{ optional($case->review->reviewed_at)->fdatetime() ?? '—' }}</x-detail-grid.row>
            </x-detail-grid>
        @elseif (in_array($case->status, ['completed', 'cancelled'], true))
            @can('update', $case)
                <form method="POST" action="{{ route('investments.review.store', $case) }}" class="grid gap-2">
                    @csrf
                    <textarea name="benefit_result" required rows="2" class="textarea textarea-bordered textarea-sm" placeholder="{{ __('Tatsächlicher Nutzen (Pflicht)') }}"></textarea>
                    <textarea name="economics_result" rows="2" class="textarea textarea-bordered textarea-sm" placeholder="{{ __('Wirtschaftlichkeit') }}"></textarea>
                    <textarea name="lessons" rows="2" class="textarea textarea-bordered textarea-sm" placeholder="{{ __('Lessons Learned') }}"></textarea>
                    <textarea name="follow_up" rows="2" class="textarea textarea-bordered textarea-sm" placeholder="{{ __('Folgeinvestitionen/-aufgaben') }}"></textarea>
                    <div><x-icon-btn icon="fact_check" tone="primary" size="sm" type="submit" show-label>{{ __('Nachbewertung speichern') }}</x-icon-btn></div>
                </form>
            @endcan
        @else
            <p class="text-sm text-base-content/60">{{ __('Nachbewertung wird nach Abschluss oder Abbruch möglich.') }}</p>
        @endif
    </x-card>
</x-page-shell>
@endsection
