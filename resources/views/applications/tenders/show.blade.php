{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Ausschreibung: :title', ['title' => $opportunity->title]))
@section('nav-title', $opportunity->title)

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :badge="__('values.' . $opportunity->status)" badge-tone="outline">
            <div class="text-sm text-base-content/70">
                {{ __("values.{$opportunity->kind}") }}
                @if ($opportunity->customer) · {{ $opportunity->customer->name }} @endif
                @if ($opportunity->submission_deadline) · {{ __('Abgabefrist: :date', ['date' => $opportunity->submission_deadline->fdate()]) }} @endif
            </div>
            <x-slot:actions>
                @can('update', $opportunity)
                    <x-icon-btn icon="edit" size="sm" data-entry-modal-trigger :href="route('tenders.edit', $opportunity)" show-label>{{ __('Bearbeiten') }}</x-icon-btn>
                @endcan
                @can('decide', $opportunity)
                    @if ($opportunity->go_decision === 'pending' && $opportunity->isOpen())
                        <x-action-form :action="route('tenders.go', $opportunity)">
                            <input type="hidden" name="decision" value="go">
                            <x-icon-btn icon="thumb_up" tone="success" size="sm" type="submit" show-label>{{ __('Go') }}</x-icon-btn>
                        </x-action-form>
                        <x-action-form :action="route('tenders.go', $opportunity)"
                              :confirm="__('No-go dokumentieren? Die Akte wird als zurückgezogen geschlossen.')"
                              confirm-icon="thumb_down" confirm-tone="warning" :confirm-label="__('No-go')">
                            <input type="hidden" name="decision" value="no_go">
                            <x-icon-btn icon="thumb_down" tone="warning" size="sm" type="submit" show-label>{{ __('No-go') }}</x-icon-btn>
                        </x-action-form>
                    @endif
                    @if ($opportunity->go_decision === 'go' && $opportunity->isOpen())
                        {{-- Über den Assistenten: geprüft wird vor der Abgabe, nicht danach. --}}
                        <x-icon-btn icon="outbox" tone="primary" size="sm" :href="route('tenders.submit-wizard', $opportunity)" show-label
                                    :title="__('Prüfen, ausgeben und Einreichung dokumentieren')">{{ __('Abgabe vorbereiten') }}</x-icon-btn>
                    @endif
                    @if ($opportunity->status === 'won')
                        <x-action-form :action="route('tenders.transfer', $opportunity)"
                              :confirm="__('Gewonnene Ausschreibung in ein Projekt überführen?')"
                              confirm-icon="folder_special" confirm-tone="primary" :confirm-label="__('Überführen')">
                            <x-icon-btn icon="folder_special" tone="primary" size="sm" type="submit" show-label>{{ __('In Projekt überführen') }}</x-icon-btn>
                        </x-action-form>
                    @endif
                @endcan
                @can('delete', $opportunity)
                    <x-action-form :action="route('tenders.destroy', $opportunity)" method="DELETE"
                          :confirm="__('Akte wirklich löschen?')" confirm-icon="delete" confirm-tone="error" :confirm-label="__('Löschen')">
                        <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Löschen') }}</x-icon-btn>
                    </x-action-form>
                @endcan
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 lg:grid-cols-2">
        {{-- Vergabeverfahren (MVP-625): nur zeigen, wenn die Akte eines ist. --}}
        @if ($opportunity->procedure_type !== null || $opportunity->awarding_body !== null)
            <x-card :title="__('Vergabeverfahren')">
                <x-detail-grid>
                    <x-detail-grid.row :label="__('Vergabestelle')">{{ $opportunity->awarding_body ?? '—' }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('Vergabenummer')">{{ $opportunity->procedure_no ?? '—' }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('Verfahrensart')">
                        {{ $opportunity->procedure_type?->label() ?? '—' }}
                        @if ($opportunity->procedure_type?->hasCallForParticipation())
                            <span class="wd-badge badge-outline">{{ __('mit Teilnahmewettbewerb') }}</span>
                        @endif
                    </x-detail-grid.row>
                    <x-detail-grid.row :label="__('Schwellenwertlage')">{{ $opportunity->above_threshold ? __('Oberschwellig') : __('Unterschwellig') }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('Los')">{{ $opportunity->lot_no ?? '—' }}{{ $opportunity->lot_group ? ' · ' . $opportunity->lot_group : '' }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('CPV')">{{ implode(', ', $opportunity->cpv_codes ?? []) ?: '—' }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('Region (NUTS)')">{{ $opportunity->nuts_code ?? '—' }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('Plattform')">{{ $opportunity->platform ?? '—' }}{{ $opportunity->external_reference ? ' · ' . $opportunity->external_reference : '' }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('Teilnahmefrist')">{{ optional($opportunity->participation_deadline)->fdate() ?? '—' }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('Eröffnungstermin')">{{ optional($opportunity->opening_at)->fdatetime() ?? '—' }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('Bindefrist')">{{ optional($opportunity->binding_until)->fdate() ?? '—' }}</x-detail-grid.row>
                </x-detail-grid>
                @if ($opportunity->notice_url)
                    <a href="{{ $opportunity->notice_url }}" rel="noopener noreferrer" target="_blank"
                       class="mt-3 inline-flex items-center gap-1 text-sm link">{{ __('Bekanntmachung öffnen') }}</a>
                @endif
            </x-card>
        @endif

        <x-card :title="__('Akte')">
            <x-detail-grid>
                <x-detail-grid.row :label="__('Wertpotenzial')">{{ $opportunity->estimated_value !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $opportunity->estimated_value, 2, withThousandsSeparator: true) . ' €' : '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Erfolgswahrscheinlichkeit')">{{ $opportunity->probability !== null ? $opportunity->probability . ' %' : '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Rückfragefrist')">{{ optional($opportunity->question_deadline)->fdate() ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Entscheidung erwartet')">{{ optional($opportunity->decision_expected_on)->fdate() ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Verantwortlich')">{{ $opportunity->responsible->name ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Go/No-go')">{{ __("values.{$opportunity->go_decision}") }}@if ($opportunity->go_note) — {{ $opportunity->go_note }}@endif</x-detail-grid.row>
                @if ($opportunity->loss_reason)
                    <x-detail-grid.row :label="__('Verlust-/Rückzugsgrund')">{{ $opportunity->loss_reason }}</x-detail-grid.row>
                @endif
                @if ($opportunity->project)
                    <x-detail-grid.row :label="__('Projekt')"><a class="link" href="{{ route('projects.show', $opportunity->project) }}">{{ $opportunity->project->name }}</a></x-detail-grid.row>
                @endif
            </x-detail-grid>
            @if ($opportunity->risk_note)
                <p class="mt-2 whitespace-pre-line text-sm text-base-content/70">{{ $opportunity->risk_note }}</p>
            @endif
            @can('decide', $opportunity)
                @if ($opportunity->isOpen())
                    <form method="POST" action="{{ route('tenders.decide', $opportunity) }}" class="mt-3 flex flex-wrap items-end gap-2">
                        @csrf
                        <select name="decision" class="select select-sm select-bordered">
                            <option value="won">{{ __('Gewonnen') }}</option>
                            <option value="lost">{{ __('Verloren') }}</option>
                            <option value="withdrawn">{{ __('Zurückgezogen') }}</option>
                        </select>
                        <input aria-label="{{ __('Grund (bei Verlust/Rückzug Pflicht)') }}" name="reason" maxlength="500" class="input input-sm input-bordered flex-1" placeholder="{{ __('Grund (bei Verlust/Rückzug Pflicht)') }}">
                        <x-icon-btn icon="gavel" tone="primary" size="sm" type="submit" show-label>{{ __('Entscheiden') }}</x-icon-btn>
                    </form>
                @endif
            @endcan
        </x-card>

        <x-card :title="__('Unterlagen & Eignungsnachweise')">
            @can('update', $opportunity)
                <form method="POST" action="{{ route('tenders.requirements.store', $opportunity) }}" class="mb-3 flex flex-wrap items-end gap-2">
                    @csrf
                    <input aria-label="{{ __('Anforderung (z. B. Referenzliste)') }}" name="label" required maxlength="300" class="input input-sm input-bordered flex-1" placeholder="{{ __('Anforderung (z. B. Referenzliste)') }}">
                    <select name="kind" class="select select-sm select-bordered">
                        <option value="document">{{ __('Dokument') }}</option>
                        <option value="proof">{{ __('Nachweis') }}</option>
                        <option value="question">{{ __('Rückfrage') }}</option>
                        <option value="task">{{ __('Aufgabe') }}</option>
                    </select>
                    <x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>{{ __('Hinzufügen') }}</x-icon-btn>
                </form>
            @endcan
            @if ($opportunity->requirements->isEmpty())
                <x-empty-state icon="checklist" :title="__('Noch keine Anforderungen erfasst.')" compact />
            @else
                <ul class="space-y-2">
                    @foreach ($opportunity->requirements as $requirement)
                        <li class="flex flex-wrap items-center gap-2 text-sm">
                            <x-status-badge size="xs" outline>{{ __("values.{$requirement->status}") }}</x-status-badge>
                            <span @class(['font-medium', 'line-through opacity-60' => $requirement->status === 'not_applicable'])>{{ $requirement->label }}</span>
                            @unless ($requirement->required)<span class="badge badge-ghost badge-xs">{{ __('optional') }}</span>@endunless
                            @if ($requirement->due_on)<span class="text-xs text-muted">{{ __('bis :date', ['date' => $requirement->due_on->fdate()]) }}</span>@endif
                            @if ($requirement->document)
                                <a class="link text-xs" href="{{ route('documents.show', $requirement->document) }}">{{ __('Dokument') }}</a>
                            @endif
                            @can('update', $opportunity)
                                <form method="POST" action="{{ route('tenders.requirements.update', [$opportunity, $requirement]) }}" class="ml-auto flex items-center gap-1">
                                    @csrf @method('PUT')
                                    <select name="status" class="select select-xs select-bordered" data-autosubmit>
                                        @foreach (\App\Models\Applications\ApplicationRequirement::STATUSES as $status)
                                            <option value="{{ $status }}" @selected($requirement->status === $status)>{{ __("values.$status") }}</option>
                                        @endforeach
                                    </select>
                                </form>
                                <x-action-form :action="route('tenders.requirements.destroy', [$opportunity, $requirement])" method="DELETE">
                                    <x-icon-btn icon="delete" size="xs" tone="error" type="submit" :title="__('Entfernen')" />
                                </x-action-form>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>

    <x-card :title="__('Einreichungen (versioniert, mit Hash)')">
        @if ($opportunity->submissions->isEmpty())
            <x-empty-state icon="outbox" :title="__('Noch keine Einreichung dokumentiert.')" compact />
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Version') }}</th>
                        <th>{{ __('Zeitpunkt') }}</th>
                        <th>{{ __('Kanal') }}</th>
                        <th>{{ __('SHA-256') }}</th>
                        <th>{{ __('Anmerkung') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($opportunity->submissions as $submission)
                    <tr>
                        <td>V{{ $submission->version }}</td>
                        <td>{{ $submission->created_at->fdatetime() }}</td>
                        <td>{{ __("values.{$submission->channel}") }}</td>
                        <td class="font-mono text-xs">{{ substr($submission->sha256, 0, 16) }}…</td>
                        <td>{{ $submission->note ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>

    {{-- Submissionsergebnis (MVP-628): Was im Eröffnungstermin verlesen wurde,
         ist die einzige belastbare Quelle für den eigenen Preisabstand. --}}
    <x-card :title="__('Submissionsergebnis')">
        @php
            $bids = $opportunity->competitorBids;
            $own = $bids->firstWhere('is_own', true);
            $best = $bids->filter(fn ($bid) => $bid->amount !== null)->sortBy(fn ($bid) => (float) $bid->amount)->first();
        @endphp

        @can('update', $opportunity)
            <form method="POST" action="{{ route('tenders.bids.store', $opportunity) }}" class="mb-3 flex flex-wrap items-end gap-2">
                @csrf
                <input aria-label="{{ __('Bieter') }}" name="bidder_name" required maxlength="300" class="input input-sm input-bordered flex-1 min-w-48"
                       placeholder="{{ __('Bieter') }}">
                <input aria-label="{{ __('Endsumme') }}" name="amount" type="number" step="0.01" min="0" class="input input-sm input-bordered w-40"
                       placeholder="{{ __('Endsumme') }}">
                <input aria-label="{{ __('Rang') }}" name="rank" type="number" min="1" max="999" class="input input-sm input-bordered w-20"
                       placeholder="{{ __('Rang') }}">
                <select name="source" class="select select-sm select-bordered w-48">
                    <option value="opening">{{ __('Eröffnungstermin') }}</option>
                    <option value="information_letter">{{ __('Informationsschreiben') }}</option>
                    <option value="other">{{ __('values.other') }}</option>
                </select>
                <label class="label cursor-pointer gap-1 text-xs">
                    <input type="checkbox" name="is_own" value="1" class="checkbox checkbox-sm">
                    <span>{{ __('Eigenes Angebot') }}</span>
                </label>
                <label class="label cursor-pointer gap-1 text-xs">
                    <input type="checkbox" name="is_winner" value="1" class="checkbox checkbox-sm">
                    <span>{{ __('Zuschlag') }}</span>
                </label>
                <x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>{{ __('Hinzufügen') }}</x-icon-btn>
            </form>
        @endcan

        @if ($bids->isEmpty())
            <x-empty-state icon="rule" compact
                           :title="__('Noch kein Submissionsergebnis erfasst.')"
                           :message="__('Bei Öffentlichen Ausschreibungen werden die Endsummen im Eröffnungstermin verlesen; oberschwellig nennt das Informationsschreiben den vorgesehenen Zuschlagsempfänger.')" />
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Rang') }}</th>
                        <th>{{ __('Bieter') }}</th>
                        <th class="text-right">{{ __('Endsumme') }}</th>
                        <th class="text-right">{{ __('Abstand') }}</th>
                        <th>{{ __('Quelle') }}</th>
                        <th class="text-right"></th>
                    </tr>
                </x-slot:head>
                @foreach ($bids as $bid)
                    <tr @class(['bg-base-200/60' => $bid->is_own])>
                        <td class="tabular-nums">{{ $bid->rank ?? '—' }}</td>
                        <td>
                            {{ $bid->bidder_name }}
                            @if ($bid->is_own)<span class="wd-badge badge-outline">{{ __('eigenes Angebot') }}</span>@endif
                            @if ($bid->is_winner)<span class="wd-badge badge-success">{{ __('Zuschlag') }}</span>@endif
                            @if ($bid->note)<div class="text-xs text-muted">{{ $bid->note }}</div>@endif
                        </td>
                        <td class="text-right tabular-nums">
                            {{ $bid->amount !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $bid->amount, 2, withThousandsSeparator: true) . ' €' : '—' }}
                        </td>
                        <td class="text-right tabular-nums text-base-content/70">
                            @if ($best && $bid->amount !== null && (float) $best->amount > 0.0 && $bid->id !== $best->id)
                                +{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(((float) $bid->amount / (float) $best->amount - 1) * 100, 1) }} %
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-xs text-base-content/70">{{ __('tender.bid_source.' . $bid->source) }}</td>
                        <td class="text-right">
                            @can('update', $opportunity)
                                <x-action-form :action="route('tenders.bids.destroy', [$opportunity, $bid])" method="DELETE">
                                    <x-icon-btn icon="delete" size="xs" tone="error" type="submit" :title="__('Entfernen')" />
                                </x-action-form>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </x-table>

            @if ($own && $best && $own->id !== $best->id && (float) $best->amount > 0.0)
                <p class="mt-2 text-sm text-base-content/70">
                    {{ __('Das eigene Angebot lag :percent % über dem günstigsten.', [
                        'percent' => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(((float) $own->amount / (float) $best->amount - 1) * 100, 1),
                    ]) }}
                </p>
            @endif
        @endif
    </x-card>

    @include('applications._negotiations', [
        'negotiations' => $opportunity->negotiations,
        'storeRoute' => route('tenders.negotiations.store', $opportunity),
        'canOpen' => $opportunity->status === 'won' && auth()->user()?->can('decide', $opportunity),
    ])
</x-page-shell>
@endsection
