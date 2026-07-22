@extends('layouts.app')

@section('title', __('Ausschreibung: :title', ['title' => $opportunity->title]))
@section('nav-title', $opportunity->title)

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :title="$opportunity->title" :badge="__('values.' . $opportunity->status)" badge-tone="outline">
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
                        <x-action-form :action="route('tenders.submit', $opportunity)">
                            <input type="hidden" name="channel" value="portal">
                            <x-icon-btn icon="outbox" tone="primary" size="sm" type="submit" show-label
                                        :title="__('Einreichung als versionierten Snapshot mit Hash dokumentieren')">{{ __('Einreichung dokumentieren') }}</x-icon-btn>
                        </x-action-form>
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
                        <input name="reason" maxlength="500" class="input input-sm input-bordered flex-1" placeholder="{{ __('Grund (bei Verlust/Rückzug Pflicht)') }}">
                        <x-icon-btn icon="gavel" tone="primary" size="sm" type="submit" show-label>{{ __('Entscheiden') }}</x-icon-btn>
                    </form>
                @endif
            @endcan
        </x-card>

        <x-card :title="__('Unterlagen & Eignungsnachweise')">
            @can('update', $opportunity)
                <form method="POST" action="{{ route('tenders.requirements.store', $opportunity) }}" class="mb-3 flex flex-wrap items-end gap-2">
                    @csrf
                    <input name="label" required maxlength="300" class="input input-sm input-bordered flex-1" placeholder="{{ __('Anforderung (z. B. Referenzliste)') }}">
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
                            @if ($requirement->due_on)<span class="text-xs text-base-content/60">{{ __('bis :date', ['date' => $requirement->due_on->fdate()]) }}</span>@endif
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

    @include('applications._negotiations', [
        'negotiations' => $opportunity->negotiations,
        'storeRoute' => route('tenders.negotiations.store', $opportunity),
        'canOpen' => $opportunity->status === 'won' && auth()->user()?->can('decide', $opportunity),
    ])
</x-page-shell>
@endsection
