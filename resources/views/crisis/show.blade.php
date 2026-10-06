{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Krise: :title', ['title' => $case->title]))
@section('nav-title', $case->title)

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :badge="$case->status->label()" badge-tone="outline">
            <div class="text-sm text-base-content/70">
                {{ __("values.{$case->category}") }} · {{ __("values.{$case->severity}") }}
                @if ($case->trigger_source) · {{ __('Auslöser: :source', ['source' => $case->trigger_source]) }} @endif
                @if ($case->activated_at) · {{ __('aktiviert :date', ['date' => $case->activated_at->fdatetime()]) }} @endif
            </div>
            <x-slot:actions>
                @can('approve', $case)
                    @if ($case->canBeActivated())
                        <x-action-form :action="route('crisis.activate', $case)">
                            <x-icon-btn icon="emergency_home" tone="error" size="sm" type="submit" show-label>{{ __('Krise aktivieren') }}</x-icon-btn>
                        </x-action-form>
                    @endif
                    @if ($case->status->canTransitionTo(\App\Enums\Crisis\CrisisCaseStatus::AllClear))
                        <x-action-form :action="route('crisis.all-clear', $case)"
                              :confirm="__('Entwarnung dokumentieren?')" confirm-icon="task_alt" confirm-tone="success" :confirm-label="__('Entwarnen')">
                            <x-icon-btn icon="task_alt" tone="success" size="sm" type="submit" show-label>{{ __('Entwarnen') }}</x-icon-btn>
                        </x-action-form>
                    @endif
                    @if ($case->status === \App\Enums\Crisis\CrisisCaseStatus::PostReview)
                        <x-action-form :action="route('crisis.close', $case)">
                            <x-icon-btn icon="lock" size="sm" type="submit" show-label>{{ __('Akte schließen') }}</x-icon-btn>
                        </x-action-form>
                    @endif
                @endcan
                @if ($canManage)
                    <x-action-form :action="route('crisis.alert', $case)">
                        <x-icon-btn icon="campaign" tone="warning" size="sm" type="submit" show-label
                                    :title="__('Alarmiert alle unquittierten Stabsmitglieder (überstimmt Ruhezeiten)')">{{ __('Stab alarmieren') }}</x-icon-btn>
                    </x-action-form>
                    <x-action-form :action="route('crisis.alert.escalate', $case)">
                        <x-icon-btn icon="notification_important" tone="warning" size="sm" type="submit" show-label
                                    :title="__('Unquittierte Alarme erneut + an Stellvertretungen')">{{ __('Eskalieren') }}</x-icon-btn>
                    </x-action-form>
                    @if ($case->status->isActive())
                        <form method="POST" action="{{ route('crisis.status', $case) }}" class="flex items-center gap-1">
                            @csrf
                            <select name="status" class="select select-sm select-bordered" data-autosubmit aria-label="{{ __('Status') }}">
                                {{-- Gemeldet und aktiviert sind keine Lagezustände: ohne leere Vorauswahl zeigte das Feld den ersten. --}}
                                @unless ($case->status->isStage())
                                    <option value="" selected disabled>{{ __('Bitte wählen') }}</option>
                                @endunless
                                @foreach ($case->status->selectableStages() as $status)
                                    <option value="{{ $status->value }}" @selected($case->status === $status)>{{ $status->label() }}</option>
                                @endforeach
                            </select>
                        </form>
                    @endif
                @endif
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    {{-- Geschlossen/verworfen: die Policy weist jede Änderung ab, die Seite zeigt nur noch. --}}
    @if ($case->status->isShelved())
        <div role="status" class="alert alert-info text-sm">
            <x-icon name="lock" />
            @if ($case->status === \App\Enums\Crisis\CrisisCaseStatus::Closed)
                {{-- Ohne closed_at (Altbestand) ist die letzte Änderung der Abschluss: danach nimmt die Akte nichts mehr an. --}}
                <span>{{ __('Akte geschlossen am :date — nur lesend.', ['date' => ($case->closed_at ?? $case->updated_at)->fdatetime()]) }}</span>
            @else
                <span>{{ __('Akte verworfen — nur lesend.') }}</span>
            @endif
        </div>
    @endif

    {{-- Meldefristen (D9) --}}
    @if ($deadlines !== [])
        <x-card :title="__('Meldefristen (konfigurierbare Templates)')">
            <ul class="space-y-1 text-sm">
                @foreach ($deadlines as $deadline)
                    <li class="flex flex-wrap items-center gap-2">
                        <x-status-badge size="xs" :tone="$deadline['overdue'] ? 'error' : 'ghost'">
                            {{ $deadline['immediate'] ? __('unverzüglich') : ($deadline['due_at'] !== null ? $deadline['due_at']->fdatetime() : '—') }}
                        </x-status-badge>
                        <span>{{ $deadline['label'] }}</span>
                        @if ($deadline['source'])<span class="text-xs text-muted">({{ $deadline['source'] }})</span>@endif
                        @if ($deadline['overdue'])<span class="text-error text-xs font-semibold">{{ __('überfällig') }}</span>@endif
                    </li>
                @endforeach
            </ul>
            @unless ($case->activated_at)
                <p class="mt-1 text-xs text-muted">{{ __('Fristen laufen ab der Aktivierung (aktuell: ab Meldung gerechnet).') }}</p>
            @endunless
        </x-card>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        {{-- Krisenstab (MVP-213) --}}
        <x-card :title="__('Krisenstab & Alarmierung')">
            @if ($canManage)
                <form method="POST" action="{{ route('crisis.team.store', $case) }}" class="mb-2 flex flex-wrap items-end gap-2">
                    @csrf
                    <select name="crisis_role_id" required class="select select-sm select-bordered" aria-label="{{ __('Rolle') }}">
                        <option value="">{{ __('Rolle …') }}</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->sqid }}">{{ $role->name }}</option>
                        @endforeach
                    </select>
                    <select name="user_id" required class="select select-sm select-bordered" aria-label="{{ __('Person') }}">
                        <option value="">{{ __('Person …') }}</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->sqid }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                    <select name="deputy_user_id" class="select select-sm select-bordered" aria-label="{{ __('Stellvertretung') }}">
                        <option value="">{{ __('Stellvertretung …') }}</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->sqid }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                    <input aria-label="{{ __('Erreichbarkeit') }}" name="contact_note" maxlength="300" class="input input-sm input-bordered w-40" placeholder="{{ __('Erreichbarkeit') }}">
                    <x-icon-btn icon="person_add" tone="primary" size="sm" type="submit" show-label>{{ __('Benennen') }}</x-icon-btn>
                </form>
                <form method="POST" action="{{ route('crisis.roles.store') }}" class="mb-3 flex flex-wrap items-end gap-1 text-xs">
                    @csrf
                    <input aria-label="{{ __('Neue Stabsrolle (z. B. Kommunikation)') }}" name="name" required maxlength="120" class="input input-xs input-bordered w-48" placeholder="{{ __('Neue Stabsrolle (z. B. Kommunikation)') }}">
                    <x-button type="submit" tone="plain" size="xs">{{ __('Rolle anlegen') }}</x-button>
                </form>
            @endif
            @if ($case->team->isEmpty())
                <x-empty-state icon="groups" :title="__('Noch kein Krisenstab benannt.')" compact />
            @else
                <ul class="space-y-2 text-sm">
                    @foreach ($case->team as $assignment)
                        <li class="flex flex-wrap items-center gap-2">
                            <x-status-badge tone="plain" outline>{{ $assignment->role->name ?? '—' }}</x-status-badge>
                            <span class="font-medium">{{ $assignment->user->name ?? '—' }}</span>
                            @if ($assignment->deputy)<span class="text-xs text-muted">{{ __('Vertretung: :name', ['name' => $assignment->deputy->name]) }}</span>@endif
                            @if ($assignment->contact_note)<span class="text-xs text-muted">{{ $assignment->contact_note }}</span>@endif
                            @if ($assignment->acknowledged_at)
                                <x-status-badge size="xs" tone="success">{{ __('quittiert :time', ['time' => $assignment->acknowledged_at->fdatetime()]) }}</x-status-badge>
                            @elseif ($assignment->alerted_at)
                                <x-status-badge size="xs" tone="warning">{{ __('alarmiert :time', ['time' => $assignment->alerted_at->fdatetime()]) }}</x-status-badge>
                            @endif
                            @can('acknowledge', $case)
                                @if ($assignment->alerted_at && ! $assignment->acknowledged_at && in_array(auth()->id(), [(int) $assignment->user_id, (int) $assignment->deputy_user_id], true))
                                    <x-action-form :action="route('crisis.team.acknowledge', [$case, $assignment])" class="ml-auto">
                                        <x-icon-btn icon="check" tone="success" size="xs" type="submit" show-label>{{ __('Quittieren') }}</x-icon-btn>
                                    </x-action-form>
                                @endif
                            @endcan
                            @if ($canManage)
                                <x-action-form :action="route('crisis.team.destroy', [$case, $assignment])" method="DELETE" class="ml-auto">
                                    <x-icon-btn icon="delete" size="xs" tone="error" type="submit" :title="__('Entfernen')" />
                                </x-action-form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        @include('crisis._room', ['case' => $case, 'roomMarkers' => $roomMarkers, 'roomPresent' => $roomPresent, 'roomPoints' => $roomPoints, 'canManage' => $canManage])

        {{-- Lagebild (MVP-214) --}}
        <x-card :title="__('Lagebild (versioniert)')">
            @if ($canManage)
                <form method="POST" action="{{ route('crisis.sitrep.store', $case) }}" class="mb-3 grid gap-2">
                    @csrf
                    <textarea aria-label="{{ __('Aktuelle Lage/Bewertung') }}" name="content" required rows="2" class="textarea textarea-bordered textarea-sm" placeholder="{{ __('Aktuelle Lage/Bewertung') }}"></textarea>
                    <div class="grid gap-2 sm:grid-cols-3">
                        <input aria-label="{{ __('Offene Risiken') }}" name="risks" maxlength="5000" class="input input-sm input-bordered" placeholder="{{ __('Offene Risiken') }}">
                        <input aria-label="{{ __('Kommunikationsstand') }}" name="communication_status" maxlength="5000" class="input input-sm input-bordered" placeholder="{{ __('Kommunikationsstand') }}">
                        <input aria-label="{{ __('Wiederanlaufstatus') }}" name="recovery_status" maxlength="5000" class="input input-sm input-bordered" placeholder="{{ __('Wiederanlaufstatus') }}">
                    </div>
                    <div><x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>{{ __('Lagebericht dokumentieren') }}</x-icon-btn></div>
                </form>
            @endif
            @if ($case->situationReports->isEmpty())
                <x-empty-state icon="monitoring" :title="__('Noch kein Lagebericht.')" compact />
            @else
                <div class="max-h-72 space-y-2 overflow-y-auto">
                    @foreach ($case->situationReports as $report)
                        <div class="rounded-box border border-base-300 p-2 text-sm">
                            <div class="flex items-center gap-2 text-xs text-muted">
                                <x-status-badge tone="plain" size="xs" outline>V{{ $report->version }}</x-status-badge>
                                {{ $report->created_at->fdatetime() }}
                            </div>
                            <p class="mt-1 whitespace-pre-line">{{ $report->content }}</p>
                            @if ($report->risks)<p class="text-xs"><span class="font-semibold">{{ __('Risiken:') }}</span> {{ $report->risks }}</p>@endif
                        </div>
                    @endforeach
                </div>
            @endif

            <h4 class="mt-4 text-sm font-semibold">{{ __('Entscheidungsprotokoll') }}</h4>
            @if ($canManage)
                <form method="POST" action="{{ route('crisis.decisions.store', $case) }}" class="my-1 flex flex-wrap items-end gap-2">
                    @csrf
                    <input aria-label="{{ __('Entscheidung') }}" name="decision" required maxlength="1000" class="input input-sm input-bordered flex-1" placeholder="{{ __('Entscheidung') }}">
                    <input aria-label="{{ __('Begründung') }}" name="rationale" maxlength="1000" class="input input-sm input-bordered w-48" placeholder="{{ __('Begründung') }}">
                    <x-icon-btn icon="gavel" size="sm" type="submit" show-label>{{ __('Protokollieren') }}</x-icon-btn>
                </form>
            @endif
            <ul class="space-y-1 text-sm">
                @foreach ($case->decisions as $decision)
                    <li>
                        <span class="text-xs text-muted">{{ $decision->decided_at->fdatetime() }}</span>
                        {{ $decision->decision }}
                        @if ($decision->rationale)<span class="text-xs text-muted">— {{ $decision->rationale }}</span>@endif
                    </li>
                @endforeach
            </ul>
        </x-card>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        {{-- Maßnahmen (MVP-216) --}}
        <x-card :title="__('Maßnahmen')">
            @if ($canManage)
                <form method="POST" action="{{ route('crisis.actions.store', $case) }}" class="mb-3 flex flex-wrap items-end gap-2">
                    @csrf
                    <input aria-label="{{ __('Maßnahme') }}" name="title" required maxlength="300" class="input input-sm input-bordered flex-1" placeholder="{{ __('Maßnahme') }}">
                    <select name="assignee_id" class="select select-sm select-bordered" aria-label="{{ __('Verantwortlich') }}">
                        <option value="">{{ __('Verantwortlich …') }}</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->sqid }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                    <input name="due_at" type="datetime-local" class="input input-sm input-bordered" aria-label="{{ __('Frist') }}">
                    <select name="priority" class="select select-sm select-bordered">
                        <option value="high">{{ __('values.high') }}</option>
                        <option value="medium">{{ __('values.medium') }}</option>
                        <option value="low">{{ __('values.low') }}</option>
                    </select>
                    <x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>{{ __('Erfassen') }}</x-icon-btn>
                </form>
            @endif
            @if ($case->actions->isEmpty())
                <x-empty-state icon="checklist" :title="__('Keine Maßnahmen.')" compact />
            @else
                <ul class="space-y-2 text-sm">
                    @foreach ($case->actions as $action)
                        <li class="flex flex-wrap items-center gap-2">
                            <x-status-badge size="xs" outline>{{ $action->status->label() }}</x-status-badge>
                            <span @class(['line-through opacity-60' => $action->status->isSettled()])>{{ $action->title }}</span>
                            @if ($action->assignee)<span class="text-xs text-muted">{{ $action->assignee->name }}</span>@endif
                            @if ($action->due_at)
                                <span @class(['text-xs', 'text-error font-semibold' => $action->due_at->isPast() && ! $action->status->isSettled(), 'text-muted' => ! $action->due_at->isPast()])>{{ $action->due_at->fdatetime() }}</span>
                            @endif
                            @if ($canManage && ! $action->status->isSettled())
                                <form method="POST" action="{{ route('crisis.actions.update', [$case, $action]) }}" class="ml-auto flex items-center gap-1">
                                    @csrf @method('PUT')
                                    <select name="status" class="select select-xs select-bordered" data-autosubmit>
                                        @foreach (\App\Enums\Crisis\CrisisActionStatus::cases() as $status)
                                            <option value="{{ $status->value }}" @selected($action->status === $status)>{{ $status->label() }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        {{-- Kommunikation (MVP-217) --}}
        <x-card :title="__('Kommunikation (Entwurf → Freigabe → Aussendung)')">
            @if ($canManage)
                <form method="POST" action="{{ route('crisis.communications.store', $case) }}" class="mb-3 grid gap-2">
                    @csrf
                    <div class="flex flex-wrap gap-2">
                        <select name="audience" class="select select-sm select-bordered">
                            @foreach (\App\Models\Crisis\CrisisCommunication::AUDIENCES as $audience)
                                <option value="{{ $audience }}">{{ __("values.$audience") }}</option>
                            @endforeach
                        </select>
                        <input aria-label="{{ __('Betreff') }}" name="subject" required maxlength="300" class="input input-sm input-bordered flex-1" placeholder="{{ __('Betreff') }}">
                    </div>
                    <textarea aria-label="{{ __('Inhalt (Entwurf)') }}" name="body" required rows="2" class="textarea textarea-bordered textarea-sm" placeholder="{{ __('Inhalt (Entwurf)') }}"></textarea>
                    <div><x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>{{ __('Entwurf anlegen') }}</x-icon-btn></div>
                </form>
            @endif
            @if ($case->communications->isEmpty())
                <x-empty-state icon="forum" :title="__('Keine Kommunikation.')" compact />
            @else
                <ul class="space-y-2 text-sm">
                    @foreach ($case->communications as $communication)
                        <li class="rounded-box border border-base-300 p-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-status-badge tone="plain" size="xs" outline>{{ __("values.{$communication->audience}") }}</x-status-badge>
                                <span class="font-medium">{{ $communication->subject }}</span>
                                <x-status-badge size="xs" :tone="$communication->status->tone()">{{ $communication->status->label() }}</x-status-badge>
                                @if ($communication->sent_at)<span class="text-xs text-muted">{{ $communication->sent_at->fdatetime() }} · {{ $communication->channel }}</span>@endif
                            </div>
                            @if ($communication->status === \App\Enums\Crisis\CrisisCommunicationStatus::Draft)
                                @can('approve', $case)
                                    <x-action-form :action="route('crisis.communications.approve', [$case, $communication])" class="mt-1">
                                        <x-icon-btn icon="verified" tone="info" size="xs" type="submit" show-label>{{ __('Freigeben') }}</x-icon-btn>
                                    </x-action-form>
                                @endcan
                            @elseif ($communication->status === \App\Enums\Crisis\CrisisCommunicationStatus::Approved && $canManage)
                                <form method="POST" action="{{ route('crisis.communications.sent', [$case, $communication]) }}" class="mt-1 flex items-center gap-1">
                                    @csrf
                                    <input aria-label="{{ __('Kanal (Mail/Telefon/Presse)') }}" name="channel" required maxlength="100" class="input input-xs input-bordered w-40" placeholder="{{ __('Kanal (Mail/Telefon/Presse)') }}">
                                    <x-button type="submit" tone="plain" size="xs">{{ __('Aussendung dokumentieren') }}</x-button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        {{-- BCM (MVP-219) --}}
        <x-card :title="__('Wiederanlauf / Business Continuity')">
            @if ($canManage)
                @if ($businessProcesses->isNotEmpty())
                    {{-- Aus dem BIA-Register übernehmen (MVP-943) --}}
                    <form method="POST" action="{{ route('crisis.bcm.adopt', $case) }}" class="mb-2 flex flex-wrap items-end gap-2">
                        @csrf
                        <select name="process_id" class="select select-sm select-bordered flex-1" aria-label="{{ __('crisis.bia.adopt') }}" required>
                            @foreach ($businessProcesses as $bp)
                                <option value="{{ $bp->sqid }}">{{ $bp->name }} ({{ $bp->criticality->label() }}, RTO {{ $bp->rto_hours ?? '—' }} h)</option>
                            @endforeach
                        </select>
                        <x-icon-btn icon="playlist_add" size="sm" type="submit" show-label>{{ __('crisis.bia.adopt') }}</x-icon-btn>
                    </form>
                @endif
                <form method="POST" action="{{ route('crisis.bcm.store', $case) }}" class="mb-3 flex flex-wrap items-end gap-2">
                    @csrf
                    <input aria-label="{{ __('Kritischer Prozess/Service') }}" name="process_name" required maxlength="200" class="input input-sm input-bordered flex-1" placeholder="{{ __('Kritischer Prozess/Service') }}">
                    <input aria-label="{{ __('Wiederanlaufzeit (RTO) in Stunden') }}" name="rto_hours" type="number" min="0" class="input input-sm input-bordered w-24" placeholder="RTO h">
                    <input aria-label="{{ __('Maximaler Datenverlust (RPO) in Stunden') }}" name="rpo_hours" type="number" min="0" class="input input-sm input-bordered w-24" placeholder="RPO h">
                    <input aria-label="{{ __('Workaround') }}" name="workaround" maxlength="1000" class="input input-sm input-bordered w-48" placeholder="{{ __('Workaround') }}">
                    <x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>{{ __('Erfassen') }}</x-icon-btn>
                </form>
            @endif
            @if ($case->continuityImpacts->isEmpty())
                <x-empty-state icon="settings_backup_restore" :title="__('Keine kritischen Prozesse erfasst.')" compact />
            @else
                <ul class="space-y-1 text-sm">
                    @foreach ($case->continuityImpacts as $impact)
                        <li class="flex flex-wrap items-center gap-2">
                            <x-status-badge size="xs" :tone="$impact->status->tone()">{{ $impact->status->label() }}</x-status-badge>
                            <span class="font-medium">{{ $impact->process_name }}</span>
                            @if ($impact->rto_hours !== null)<span class="text-xs text-muted">RTO {{ $impact->rto_hours }} h</span>@endif
                            @if ($impact->rpo_hours !== null)<span class="text-xs text-muted">RPO {{ $impact->rpo_hours }} h</span>@endif
                            @if ($impact->workaround)<span class="text-xs text-muted">{{ $impact->workaround }}</span>@endif
                            @if ($canManage)
                                <form method="POST" action="{{ route('crisis.bcm.update', [$case, $impact]) }}" class="ml-auto flex items-center gap-1">
                                    @csrf @method('PUT')
                                    <select name="status" class="select select-xs select-bordered" data-autosubmit>
                                        @foreach (\App\Enums\Crisis\CrisisContinuityImpactStatus::cases() as $status)
                                            <option value="{{ $status->value }}" @selected($impact->status === $status)>{{ $status->label() }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        {{-- Verknüpfte Vorgänge (MVP-218) --}}
        <x-card :title="__('Verknüpfte Vorgänge')">
            @if ($canManage)
                <form method="POST" action="{{ route('crisis.links.store', $case) }}" class="mb-3 flex flex-wrap items-end gap-2">
                    @csrf
                    <select name="linkable_type" class="select select-sm select-bordered">
                        <option value="service_ticket">{{ __('Service-Ticket') }}</option>
                        <option value="isms_incident">{{ __('Security Incident') }}</option>
                        <option value="privacy_incident">{{ __('Datenschutzvorfall') }}</option>
                        <option value="safety_event">{{ __('Arbeitsschutzereignis') }}</option>
                        <option value="procedure_run">{{ __('Playbook-/Prozedurlauf') }}</option>
                        <option value="document">{{ __('Dokument') }}</option>
                        <option value="asset">{{ __('crisis.room.link.asset') }}</option>
                        <option value="customer">{{ __('crisis.room.link.customer') }}</option>
                    </select>
                    <input aria-label="{{ __('Sqid/ID des Vorgangs') }}" name="linkable_sqid" required maxlength="64" class="input input-sm input-bordered w-40" placeholder="{{ __('Sqid/ID des Vorgangs') }}">
                    <x-icon-btn icon="link" tone="primary" size="sm" type="submit" show-label>{{ __('Verknüpfen') }}</x-icon-btn>
                </form>
            @endif
            @if ($case->links->isEmpty())
                <x-empty-state icon="link" :title="__('Keine verknüpften Vorgänge.')" compact />
            @else
                <ul class="space-y-1 text-sm">
                    @foreach ($case->links as $link)
                        <li>
                            <x-status-badge tone="plain" size="xs" outline>{{ \App\Support\EntityType::label($link->linkable_type) }}</x-status-badge>
                            {{ $link->linkable?->getAttribute('title') ?? $link->linkable?->getAttribute('subject') ?? $link->linkable?->getAttribute('ticket_no') ?? ('#' . $link->linkable_id) }}
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>

    {{-- Nachbereitung (MVP-221) --}}
    <x-card :title="__('Nachbereitung')">
        @if ($case->review !== null)
            <x-detail-grid>
                <x-detail-grid.row :label="__('Zusammenfassung')">{{ $case->review->summary }}</x-detail-grid.row>
                @if ($case->review->lessons)<x-detail-grid.row :label="__('Lessons Learned')">{{ $case->review->lessons }}</x-detail-grid.row>@endif
                @if ($case->review->follow_up)<x-detail-grid.row :label="__('Folgemaßnahmen')">{{ $case->review->follow_up }}</x-detail-grid.row>@endif
                <x-detail-grid.row :label="__('Nachbereitet am')">{{ optional($case->review->reviewed_at)->fdatetime() ?? '—' }}</x-detail-grid.row>
            </x-detail-grid>
        @elseif ($case->status === \App\Enums\Crisis\CrisisCaseStatus::AllClear)
            @if ($canManage)
                <form method="POST" action="{{ route('crisis.review.store', $case) }}" class="grid gap-2">
                    @csrf
                    <textarea aria-label="{{ __('Zusammenfassung (Pflicht)') }}" name="summary" required rows="2" class="textarea textarea-bordered textarea-sm" placeholder="{{ __('Zusammenfassung (Pflicht)') }}"></textarea>
                    <textarea aria-label="{{ __('Lessons Learned') }}" name="lessons" rows="2" class="textarea textarea-bordered textarea-sm" placeholder="{{ __('Lessons Learned') }}"></textarea>
                    <textarea aria-label="{{ __('Folgemaßnahmen') }}" name="follow_up" rows="2" class="textarea textarea-bordered textarea-sm" placeholder="{{ __('Folgemaßnahmen') }}"></textarea>
                    <div><x-icon-btn icon="fact_check" tone="primary" size="sm" type="submit" show-label>{{ __('Nachbereitung speichern') }}</x-icon-btn></div>
                </form>
            @endif
        @elseif ($case->status->isShelved())
            <x-empty-state icon="fact_check" :title="__('Keine Nachbereitung dokumentiert.')" compact />
        @else
            <p class="text-sm text-muted">{{ __('Nachbereitung wird nach der Entwarnung möglich.') }}</p>
        @endif
    </x-card>
</x-page-shell>
@endsection
