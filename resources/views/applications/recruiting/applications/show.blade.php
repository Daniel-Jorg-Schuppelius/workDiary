{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Bewerbung: :name', ['name' => $application->isAnonymized() ? __('(anonymisiert)') : ($application->candidate_name ?? '—')]))
@section('nav-title', __('Bewerbungsakte'))

@section('content')
<x-page-shell>
    @if ($application->isAnonymized())
        <div role="status" class="alert alert-info text-sm">
            <x-icon name="shield" />
            {{ __('Diese Akte wurde am :date anonymisiert — es sind keine personenbezogenen Daten mehr gespeichert.', ['date' => $application->anonymized_at->fdatetime()]) }}
        </div>
    @endif

    <x-slot:toolbar>
        <x-page-toolbar :title="$application->isAnonymized() ? __('(anonymisiert)') : ($application->candidate_name ?? '—')" :badge="$application->status->label()" badge-tone="outline">
            <div class="text-sm text-base-content/70">
                @if ($application->requisition) {{ $application->requisition->title }} · @endif
                {{ __('Quelle: :source', ['source' => __("values.{$application->source}")]) }}
                @if ($application->received_at) · {{ __('eingegangen :date', ['date' => $application->received_at->fdate()]) }} @endif
            </div>
            <x-slot:actions>
                @if (auth()->user()?->hasEffectivePermission(\App\Services\Hr\PersonnelFilePermissions::CREATE))
                    <x-icon-btn icon="draw" size="sm" data-entry-modal-trigger :href="route('contracts.employment.application.create', $application)" show-label>{{ __('hr.employment.title') }}</x-icon-btn>
                @endif
                @can('update', $application)
                    @if ($application->status->inPipeline())
                        <form method="POST" action="{{ route('recruiting.applications.status', $application) }}" class="flex items-center gap-1">
                            @csrf
                            <select name="status" class="select select-sm select-bordered" data-autosubmit aria-label="{{ __('Status') }}">
                                @foreach (\App\Enums\Applications\JobApplicationStatus::working() as $status)
                                    <option value="{{ $status->value }}" @selected($application->status === $status)>{{ $status->label() }}</option>
                                @endforeach
                            </select>
                        </form>
                    @endif
                @endcan
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('Kandidat')">
            <x-detail-grid>
                <x-detail-grid.row :label="__('E-Mail')">{{ $application->email ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Telefon')">{{ $application->phone ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Adresse')">{{ implode(', ', $application->postalAddressLines()) ?: '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Verantwortlich')">{{ $application->responsible->name ?? '—' }}</x-detail-grid.row>
            </x-detail-grid>
            @if ($application->notes)
                <p class="mt-2 whitespace-pre-line text-sm text-base-content/70">{{ $application->notes }}</p>
            @endif

            {{-- Unterlagen (MVP-190) --}}
            <h4 class="mt-4 text-sm font-semibold">{{ __('Unterlagen') }}</h4>
            @can('update', $application)
                <form method="POST" action="{{ route('recruiting.applications.documents.store', $application) }}" enctype="multipart/form-data" class="my-2 flex flex-wrap items-end gap-2">
                    @csrf
                    <input type="file" name="file" required class="file-input file-input-sm file-input-bordered">
                    <input aria-label="{{ __('Bezeichnung') }}" name="label" maxlength="180" class="input input-sm input-bordered" placeholder="{{ __('Bezeichnung') }}">
                    <x-icon-btn icon="upload" tone="primary" size="sm" type="submit" show-label>{{ __('Ablegen') }}</x-icon-btn>
                </form>
            @endcan
            @if ($application->documents->isEmpty())
                <x-empty-state icon="description" :title="__('Keine Unterlagen abgelegt.')" compact />
            @else
                <ul class="space-y-1 text-sm">
                    @foreach ($application->documents as $link)
                        <li>
                            <a class="link" href="{{ route('documents.show', $link->document) }}">{{ $link->label ?? $link->document->title }}</a>
                        </li>
                    @endforeach
                </ul>
            @endif

            {{-- Über den öffentlichen Karrierebereich eingereichte Unterlagen
                 (MVP-795): erreichbar erst nach bestandener Prüfung. --}}
            <h4 class="mt-4 text-sm font-semibold">{{ __('Eingereichte Unterlagen') }}</h4>
            @if ($application->uploads->isEmpty())
                <x-empty-state icon="upload_file" :title="__('Keine Unterlagen eingereicht.')" compact />
            @else
                <ul class="space-y-1 text-sm">
                    @foreach ($application->uploads as $upload)
                        <li class="flex flex-wrap items-center gap-2">
                            @if ($upload->isReleased())
                                <a class="link" href="{{ route('recruiting.applications.uploads.download', [$application, $upload]) }}">{{ $upload->original_name }}</a>
                            @else
                                <span>{{ $upload->original_name }}</span>
                                <x-status-badge :tone="$upload->scan_status === \App\Enums\Applications\JobApplicationUploadScanStatus::Rejected ? 'error' : 'warning'">
                                    {{ $upload->scan_status === \App\Enums\Applications\JobApplicationUploadScanStatus::Rejected ? __('Abgewiesen') : __('In Prüfung') }}
                                </x-status-badge>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-card :title="__('Datenschutzstatus')">
            <x-detail-grid>
                <x-detail-grid.row :label="__('Löschvormerkung')">{{ optional($application->retention_until)->fdate() ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Talentpool-Einwilligung')">{{ $application->consent_talent_pool_at !== null ? $application->consent_talent_pool_at->fdate() : '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Einwilligung läuft ab')">{{ optional($application->consent_expires_on)->fdate() ?? '—' }}</x-detail-grid.row>
            </x-detail-grid>
            @can('privacy', $application)
                <div class="mt-3 flex flex-wrap gap-2">
                    <x-icon-btn icon="download" size="sm" tone="outline" :href="route('recruiting.applications.export', $application)" show-label
                                :title="__('Auskunft nach Art. 15 DSGVO als strukturierte Kopie')">{{ __('Auskunft (JSON)') }}</x-icon-btn>
                    @unless ($application->isAnonymized())
                        <x-action-form :action="route('recruiting.applications.anonymize', $application)"
                              :confirm="__('Bewerberdaten unwiderruflich anonymisieren? Die Akte bleibt als anonymer Nachweis erhalten.')"
                              confirm-icon="shield" confirm-tone="error" :confirm-label="__('Anonymisieren')">
                            <x-icon-btn icon="shield" tone="error" size="sm" type="submit" show-label>{{ __('Anonymisieren / löschen') }}</x-icon-btn>
                        </x-action-form>
                    @endunless
                </div>
            @endcan
        </x-card>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('Gespräche')">
            @can('update', $application)
                {{-- Entschieden ist endgültig: Gespräche nur in der Pipeline. --}}
                @if ($application->status->inPipeline())
                    <form method="POST" action="{{ route('recruiting.applications.interviews.store', $application) }}" class="mb-3 flex flex-wrap items-end gap-2">
                        @csrf
                        <input type="datetime-local" name="scheduled_at" required class="input input-sm input-bordered" aria-label="{{ __('Termin') }}">
                        <select name="mode" class="select select-sm select-bordered">
                            <option value="onsite">{{ __('Vor Ort') }}</option>
                            <option value="remote">{{ __('Remote') }}</option>
                            <option value="phone">{{ __('Telefon') }}</option>
                        </select>
                        <x-icon-btn icon="event" tone="primary" size="sm" type="submit" show-label>{{ __('Gespräch planen') }}</x-icon-btn>
                    </form>
                    {{-- Terminwahl durch den Bewerber (MVP-925) --}}
                    <details class="mb-3 rounded-box border border-base-300 p-2 text-sm">
                        <summary class="cursor-pointer font-medium">{{ __('recruiting.offer.title') }}</summary>
                        <form method="POST" action="{{ route('recruiting.applications.interview-offers.store', $application) }}" class="mt-2 grid gap-2 sm:grid-cols-2">
                            @csrf
                            @foreach (range(0, 2) as $i)
                                <input type="datetime-local" name="slots[]" class="input input-sm input-bordered" @if ($i === 0) required @endif aria-label="{{ __('recruiting.offer.slot', ['n' => $i + 1]) }}">
                            @endforeach
                            <select name="mode" class="select select-sm select-bordered" aria-label="{{ __('recruiting.offer.mode') }}">
                                @foreach (\App\Models\Applications\JobApplicationInterview::MODES as $m)
                                    <option value="{{ $m }}">{{ __('values.' . $m) }}</option>
                                @endforeach
                            </select>
                            <input type="number" name="duration_minutes" value="60" min="15" max="240" class="input input-sm input-bordered" aria-label="{{ __('recruiting.offer.duration') }}" title="{{ __('recruiting.offer.duration') }}">
                            <input type="number" name="valid_days" value="7" min="1" max="30" class="input input-sm input-bordered" aria-label="{{ __('recruiting.offer.valid_days') }}" title="{{ __('recruiting.offer.valid_days') }}">
                            <select name="interviewer_user_id" class="select select-sm select-bordered" aria-label="{{ __('recruiting.offer.interviewer') }}">
                                <option value="">{{ __('recruiting.offer.interviewer') }}: —</option>
                                @foreach ($users as $u)
                                    <option value="{{ $u->sqid }}">{{ $u->name }}</option>
                                @endforeach
                            </select>
                            <x-icon-btn icon="send" size="sm" tone="primary" type="submit" show-label>{{ __('recruiting.offer.send') }}</x-icon-btn>
                        </form>
                    </details>
                @endif
            @endcan
            @if ($application->interviews->isEmpty())
                <x-empty-state icon="event" :title="__('Keine Gespräche geplant.')" compact />
            @else
                <ul class="space-y-2 text-sm">
                    @foreach ($application->interviews as $interview)
                        <li class="flex flex-wrap items-center gap-2">
                            <x-status-badge size="xs" outline>{{ $interview->status->label() }}</x-status-badge>
                            {{ $interview->scheduled_at->fdatetime() }} · {{ __("values.{$interview->mode}") }}
                            @if ($interview->interviewer)<span class="text-muted">{{ $interview->interviewer->name }}</span>@endif
                            @if ($interview->rating)<span class="text-xs">{{ str_repeat('★', (int) $interview->rating) }}</span>@endif
                            @if ($interview->status === \App\Enums\Applications\JobApplicationInterviewStatus::Planned && $application->status->inPipeline())
                                @can('update', $application)
                                    <x-action-form :action="route('recruiting.applications.interviews.complete', [$application, $interview])" class="ml-auto">
                                        <x-icon-btn icon="check" size="xs" tone="success" type="submit" :title="__('Als geführt dokumentieren')" />
                                    </x-action-form>
                                @endcan
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-card :title="__('Bewertungen')">
            @can('update', $application)
                @unless ($application->isAnonymized())
                    <form method="POST" action="{{ route('recruiting.applications.reviews.store', $application) }}" class="mb-3 flex flex-wrap items-end gap-2">
                        @csrf
                        <select name="rating" class="select select-sm select-bordered" aria-label="{{ __('Bewertung') }}">
                            @foreach ([5, 4, 3, 2, 1] as $rating)
                                <option value="{{ $rating }}">{{ str_repeat('★', $rating) }}</option>
                            @endforeach
                        </select>
                        <input aria-label="{{ __('Kommentar (intern, verschlüsselt)') }}" name="comment" maxlength="2000" class="input input-sm input-bordered flex-1" placeholder="{{ __('Kommentar (intern, verschlüsselt)') }}">
                        <x-icon-btn icon="grade" tone="primary" size="sm" type="submit" show-label>{{ __('Bewerten') }}</x-icon-btn>
                    </form>
                @endunless
            @endcan
            @if ($application->reviews->isEmpty())
                <x-empty-state icon="grade" :title="__('Keine Bewertungen.')" compact />
            @else
                <ul class="space-y-1 text-sm">
                    @foreach ($application->reviews as $review)
                        <li>
                            <span>{{ str_repeat('★', (int) $review->rating) }}</span>
                            <span class="text-muted">{{ $review->reviewer->name ?? '—' }}</span>
                            @if ($review->comment) — {{ $review->comment }} @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        {{-- Kompetenz-Einschätzung (MVP-924) gegen das Soll der Stelle --}}
        @if ($suitabilityRequirements->isNotEmpty() && ! $application->isAnonymized())
            <x-card :title="__('recruiting.suitability.rating_title')" icon="grid_view">
                <ul class="space-y-2 text-sm">
                    @foreach ($suitabilityRequirements as $requirement)
                        @php
                            $rating = $ratings->get($requirement->competency_id);
                            $max = (int) ($requirement->competency?->max_level ?? 5);
                        @endphp
                        <li class="flex flex-wrap items-center gap-2">
                            <span class="min-w-40 flex-1">{{ $requirement->competency?->name }}
                                <span class="text-muted">({{ __('recruiting.suitability.required', ['level' => $requirement->required_level]) }})</span></span>
                            @can('update', $application)
                                <form method="POST" action="{{ route('recruiting.applications.ratings.store', $application) }}" class="flex flex-wrap items-center gap-1">
                                    @csrf
                                    <input type="hidden" name="competency_id" value="{{ $requirement->competency?->sqid }}">
                                    <select name="level" class="select select-xs select-bordered" aria-label="{{ __('recruiting.suitability.level') }}">
                                        @foreach (range(0, $max) as $lvl)
                                            <option value="{{ $lvl }}" @selected(($rating?->level ?? 0) === $lvl)>{{ $lvl }}</option>
                                        @endforeach
                                    </select>
                                    <input name="note" maxlength="2000" value="{{ $rating?->note }}" class="input input-xs input-bordered w-48" aria-label="{{ __('recruiting.suitability.note') }}" placeholder="{{ __('recruiting.suitability.note') }}">
                                    <x-button type="submit" tone="plain" size="xs">{{ __('recruiting.suitability.save') }}</x-button>
                                </form>
                            @else
                                <span>{{ $rating?->level ?? '—' }}</span>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif
    </div>

    {{-- Entscheidung (MVP-191) --}}
    @can('decide', $application)
        @if ($application->status->inPipeline())
            <x-card :title="__('Entscheidung')">
                <form method="POST" action="{{ route('recruiting.applications.decide', $application) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <select name="decision" class="select select-sm select-bordered">
                        <option value="offer">{{ __('Angebot') }}</option>
                        <option value="accepted">{{ __('Zusage') }}</option>
                        <option value="rejected">{{ __('Absage') }}</option>
                        <option value="withdrawn">{{ __('Rückzug (Kandidat)') }}</option>
                        <option value="talent_pool">{{ __('Talentpool') }}</option>
                    </select>
                    <input aria-label="{{ __('Anmerkung') }}" name="note" maxlength="1000" class="input input-sm input-bordered flex-1" placeholder="{{ __('Anmerkung') }}">
                    <label class="label cursor-pointer gap-2">
                        <input type="checkbox" name="talent_pool_consent" value="1" class="checkbox checkbox-sm">
                        <span class="label-text text-xs">{{ __('Ausdrückliche Talentpool-Einwilligung liegt vor') }}</span>
                    </label>
                    <x-icon-btn icon="gavel" tone="primary" size="sm" type="submit" show-label>{{ __('Entscheiden') }}</x-icon-btn>
                </form>
                <p class="mt-2 text-xs text-muted">{{ __('Absage/Rückzug startet die Löschvormerkung (:months Monate, konfigurierbar); Talentpool braucht eine befristete Einwilligung.', ['months' => (int) config('applications.rejected_retention_months', 6)]) }}</p>
            </x-card>
        @elseif ($application->status === \App\Enums\Applications\JobApplicationStatus::TalentPool)
            {{-- Aufnahme aus dem Talentpool: die einzige Ausnahme von „entschieden ist endgültig“. --}}
            <x-card :title="__('Entscheidung')">
                @if ($application->hasValidTalentPoolConsent())
                    <x-action-form :action="route('recruiting.applications.readmit', $application)" class="flex flex-wrap items-end gap-2"
                          :confirm="__('Diese Akte aus dem Talentpool wieder in die Pipeline aufnehmen? Löschvormerkung und Talentpool-Einwilligung werden entfernt.')"
                          confirm-icon="restore" confirm-tone="primary" :confirm-label="__('Aufnehmen')">
                        <input aria-label="{{ __('Anmerkung') }}" name="note" maxlength="1000" class="input input-sm input-bordered flex-1" placeholder="{{ __('Anmerkung') }}">
                        <x-icon-btn icon="restore" tone="primary" size="sm" type="submit" show-label>{{ __('Aus dem Talentpool aufnehmen') }}</x-icon-btn>
                    </x-action-form>
                    <p class="mt-2 text-xs text-muted">{{ __('Die Akte beginnt wieder als eingegangene Bewerbung; die Löschfrist entsteht mit der nächsten Entscheidung neu.') }}</p>
                @else
                    <p class="text-sm text-base-content/70">{{ __('Die Talentpool-Einwilligung fehlt oder ist abgelaufen — die Akte kann nicht wieder aufgenommen werden.') }}</p>
                @endif
            </x-card>
        @endif
    @endcan

    {{-- Onboarding-Übergabe (MVP-193) --}}
    @if ($application->status === \App\Enums\Applications\JobApplicationStatus::Accepted)
        <x-card :title="__('Onboarding-Übergabe (Mitarbeiter-Entwurf)')">
            @if ($application->employeeDraft === null)
                @can('decide', $application)
                    <form method="POST" action="{{ route('recruiting.applications.draft.store', $application) }}" class="flex flex-wrap items-end gap-2">
                        @csrf
                        <textarea aria-label="{{ __('Qualifikationen (eine je Zeile)') }}" name="qualifications" rows="2" class="textarea textarea-bordered textarea-sm flex-1" placeholder="{{ __('Qualifikationen (eine je Zeile)') }}"></textarea>
                        <x-icon-btn icon="badge" tone="primary" size="sm" type="submit" show-label>{{ __('Entwurf anlegen') }}</x-icon-btn>
                    </form>
                    <p class="mt-2 text-xs text-muted">{{ __('Der Entwurf ist KEIN Live-Konto — erst die bewusste Einladung erzeugt einen Nutzer.') }}</p>
                @endcan
            @else
                @php $draft = $application->employeeDraft; @endphp
                <x-detail-grid>
                    <x-detail-grid.row :label="__('Status')">{{ $draft->status->label() }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('Name')">{{ $draft->name }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('E-Mail')">{{ $draft->email ?? '—' }}</x-detail-grid.row>
                    @if (($draft->qualifications ?? []) !== [])
                        <x-detail-grid.row :label="__('Qualifikationen')">{{ implode(', ', $draft->qualifications) }}</x-detail-grid.row>
                    @endif
                </x-detail-grid>
                @if ($draft->checklist !== null && ! $draft->checklist->isEmpty())
                    <ul class="mt-2 space-y-1 text-sm">
                        @foreach ($draft->checklist->items() as $item)
                            <li>{{ $item['value'] === true ? '☑' : '☐' }} {{ $item['field']->label }}</li>
                        @endforeach
                    </ul>
                @endif
                @if ($draft->status === \App\Enums\Applications\EmployeeDraftStatus::Draft)
                    @can('invite', $draft)
                        <x-action-form :action="route('recruiting.applications.draft.invite', [$application, $draft])" class="mt-3"
                              :confirm="__('Jetzt ein Nutzerkonto anlegen? Der Kandidat muss beim ersten Login das Passwort setzen.')"
                              confirm-icon="person_add" confirm-tone="primary" :confirm-label="__('Einladen')">
                            <x-icon-btn icon="person_add" tone="primary" size="sm" type="submit" show-label>{{ __('Als Mitglied einladen') }}</x-icon-btn>
                        </x-action-form>
                    @endcan
                @elseif ($draft->invitedUser)
                    <p class="mt-2 text-sm text-success">{{ __('Konto angelegt: :name', ['name' => $draft->invitedUser->name]) }}</p>
                @endif
            @endif
        </x-card>
    @endif

    @include('applications._negotiations', [
        'negotiations' => $application->negotiations,
        'storeRoute' => route('recruiting.applications.negotiations.store', $application),
        'canOpen' => in_array($application->status, [\App\Enums\Applications\JobApplicationStatus::Offer, \App\Enums\Applications\JobApplicationStatus::Accepted], true) && auth()->user()?->can('decide', $application),
    ])
</x-page-shell>
@endsection
