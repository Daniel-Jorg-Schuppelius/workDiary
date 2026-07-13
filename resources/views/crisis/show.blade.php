@extends('layouts.app')

@section('title', __('Krise: :title', ['title' => $case->title]))
@section('nav-title', $case->title)

@section('content')
<x-page-shell>
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    <x-page-toolbar :title="$case->title" :badge="__('values.' . $case->status)" badge-tone="outline">
        <div class="text-sm text-base-content/70">
            {{ __("values.{$case->category}") }} · {{ __("values.{$case->severity}") }}
            @if ($case->trigger_source) · {{ __('Auslöser: :source', ['source' => $case->trigger_source]) }} @endif
            @if ($case->activated_at) · {{ __('aktiviert :date', ['date' => $case->activated_at->fdatetime()]) }} @endif
        </div>
        <x-slot:actions>
            @can('approve', $case)
                @if (in_array($case->status, ['reported', 'assessed'], true))
                    <x-action-form :action="route('crisis.activate', $case)">
                        <x-icon-btn icon="emergency_home" tone="error" size="sm" type="submit" show-label>{{ __('Krise aktivieren') }}</x-icon-btn>
                    </x-action-form>
                @endif
                @if ($case->isActive())
                    <x-action-form :action="route('crisis.all-clear', $case)"
                          :confirm="__('Entwarnung dokumentieren?')" confirm-icon="task_alt" confirm-tone="success" :confirm-label="__('Entwarnen')">
                        <x-icon-btn icon="task_alt" tone="success" size="sm" type="submit" show-label>{{ __('Entwarnen') }}</x-icon-btn>
                    </x-action-form>
                @endif
                @if ($case->status === 'post_review')
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
                @if ($case->isActive())
                    <form method="POST" action="{{ route('crisis.status', $case) }}" class="flex items-center gap-1">
                        @csrf
                        <select name="status" class="select select-sm select-bordered" data-autosubmit aria-label="{{ __('Status') }}">
                            @foreach (['assessed', 'in_progress', 'stabilized', 'recovery'] as $status)
                                <option value="{{ $status }}" @selected($case->status === $status)>{{ __("values.$status") }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif
            @endif
        </x-slot:actions>
    </x-page-toolbar>

    {{-- Meldefristen (D9) --}}
    @if ($deadlines !== [])
        <x-card :title="__('Meldefristen (konfigurierbare Templates)')">
            <ul class="space-y-1 text-sm">
                @foreach ($deadlines as $deadline)
                    <li class="flex flex-wrap items-center gap-2">
                        <x-status-badge size="xs" :tone="$deadline['overdue'] ? 'error' : 'outline'">
                            {{ $deadline['immediate'] ? __('unverzüglich') : ($deadline['due_at'] !== null ? $deadline['due_at']->fdatetime() : '—') }}
                        </x-status-badge>
                        <span>{{ $deadline['label'] }}</span>
                        @if ($deadline['source'])<span class="text-xs text-base-content/60">({{ $deadline['source'] }})</span>@endif
                        @if ($deadline['overdue'])<span class="text-error text-xs font-semibold">{{ __('überfällig') }}</span>@endif
                    </li>
                @endforeach
            </ul>
            @unless ($case->activated_at)
                <p class="mt-1 text-xs text-base-content/60">{{ __('Fristen laufen ab der Aktivierung (aktuell: ab Meldung gerechnet).') }}</p>
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
                    <input name="contact_note" maxlength="300" class="input input-sm input-bordered w-40" placeholder="{{ __('Erreichbarkeit') }}">
                    <x-icon-btn icon="person_add" tone="primary" size="sm" type="submit" show-label>{{ __('Benennen') }}</x-icon-btn>
                </form>
                <form method="POST" action="{{ route('crisis.roles.store') }}" class="mb-3 flex flex-wrap items-end gap-1 text-xs">
                    @csrf
                    <input name="name" required maxlength="120" class="input input-xs input-bordered w-48" placeholder="{{ __('Neue Stabsrolle (z. B. Kommunikation)') }}">
                    <button type="submit" class="btn btn-xs">{{ __('Rolle anlegen') }}</button>
                </form>
            @endif
            @if ($case->team->isEmpty())
                <x-empty-state icon="groups" :title="__('Noch kein Krisenstab benannt.')" compact />
            @else
                <ul class="space-y-2 text-sm">
                    @foreach ($case->team as $assignment)
                        <li class="flex flex-wrap items-center gap-2">
                            <span class="badge badge-outline badge-sm">{{ $assignment->role->name ?? '—' }}</span>
                            <span class="font-medium">{{ $assignment->user->name ?? '—' }}</span>
                            @if ($assignment->deputy)<span class="text-xs text-base-content/60">{{ __('Vertretung: :name', ['name' => $assignment->deputy->name]) }}</span>@endif
                            @if ($assignment->contact_note)<span class="text-xs text-base-content/60">{{ $assignment->contact_note }}</span>@endif
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

        {{-- Lagebild (MVP-214) --}}
        <x-card :title="__('Lagebild (versioniert)')">
            @if ($canManage && ! in_array($case->status, ['closed', 'discarded'], true))
                <form method="POST" action="{{ route('crisis.sitrep.store', $case) }}" class="mb-3 grid gap-2">
                    @csrf
                    <textarea name="content" required rows="2" class="textarea textarea-bordered textarea-sm" placeholder="{{ __('Aktuelle Lage/Bewertung') }}"></textarea>
                    <div class="grid gap-2 sm:grid-cols-3">
                        <input name="risks" maxlength="5000" class="input input-sm input-bordered" placeholder="{{ __('Offene Risiken') }}">
                        <input name="communication_status" maxlength="5000" class="input input-sm input-bordered" placeholder="{{ __('Kommunikationsstand') }}">
                        <input name="recovery_status" maxlength="5000" class="input input-sm input-bordered" placeholder="{{ __('Wiederanlaufstatus') }}">
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
                            <div class="flex items-center gap-2 text-xs text-base-content/60">
                                <span class="badge badge-outline badge-xs">V{{ $report->version }}</span>
                                {{ $report->created_at->fdatetime() }}
                            </div>
                            <p class="mt-1 whitespace-pre-line">{{ $report->content }}</p>
                            @if ($report->risks)<p class="text-xs"><span class="font-semibold">{{ __('Risiken:') }}</span> {{ $report->risks }}</p>@endif
                        </div>
                    @endforeach
                </div>
            @endif

            <h4 class="mt-4 text-sm font-semibold">{{ __('Entscheidungsprotokoll') }}</h4>
            @if ($canManage && ! in_array($case->status, ['closed', 'discarded'], true))
                <form method="POST" action="{{ route('crisis.decisions.store', $case) }}" class="my-1 flex flex-wrap items-end gap-2">
                    @csrf
                    <input name="decision" required maxlength="1000" class="input input-sm input-bordered flex-1" placeholder="{{ __('Entscheidung') }}">
                    <input name="rationale" maxlength="1000" class="input input-sm input-bordered w-48" placeholder="{{ __('Begründung') }}">
                    <x-icon-btn icon="gavel" size="sm" type="submit" show-label>{{ __('Protokollieren') }}</x-icon-btn>
                </form>
            @endif
            <ul class="space-y-1 text-sm">
                @foreach ($case->decisions as $decision)
                    <li>
                        <span class="text-xs text-base-content/60">{{ $decision->decided_at->fdatetime() }}</span>
                        {{ $decision->decision }}
                        @if ($decision->rationale)<span class="text-xs text-base-content/60">— {{ $decision->rationale }}</span>@endif
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
                    <input name="title" required maxlength="300" class="input input-sm input-bordered flex-1" placeholder="{{ __('Maßnahme') }}">
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
                            <x-status-badge size="xs" outline>{{ __("values.{$action->status}") }}</x-status-badge>
                            <span @class(['line-through opacity-60' => in_array($action->status, ['done', 'cancelled'], true)])>{{ $action->title }}</span>
                            @if ($action->assignee)<span class="text-xs text-base-content/60">{{ $action->assignee->name }}</span>@endif
                            @if ($action->due_at)
                                <span @class(['text-xs', 'text-error font-semibold' => $action->due_at->isPast() && ! in_array($action->status, ['done', 'cancelled'], true), 'text-base-content/60' => ! $action->due_at->isPast()])>{{ $action->due_at->fdatetime() }}</span>
                            @endif
                            @if ($canManage && ! in_array($action->status, ['done', 'cancelled'], true))
                                <form method="POST" action="{{ route('crisis.actions.update', [$case, $action]) }}" class="ml-auto flex items-center gap-1">
                                    @csrf @method('PUT')
                                    <select name="status" class="select select-xs select-bordered" data-autosubmit>
                                        @foreach (\App\Models\Crisis\CrisisAction::STATUSES as $status)
                                            <option value="{{ $status }}" @selected($action->status === $status)>{{ __("values.$status") }}</option>
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
                        <input name="subject" required maxlength="300" class="input input-sm input-bordered flex-1" placeholder="{{ __('Betreff') }}">
                    </div>
                    <textarea name="body" required rows="2" class="textarea textarea-bordered textarea-sm" placeholder="{{ __('Inhalt (Entwurf)') }}"></textarea>
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
                                <span class="badge badge-outline badge-xs">{{ __("values.{$communication->audience}") }}</span>
                                <span class="font-medium">{{ $communication->subject }}</span>
                                <x-status-badge size="xs" :tone="$communication->status === 'sent' ? 'success' : ($communication->status === 'approved' ? 'info' : 'outline')">{{ __("values.{$communication->status}") }}</x-status-badge>
                                @if ($communication->sent_at)<span class="text-xs text-base-content/60">{{ $communication->sent_at->fdatetime() }} · {{ $communication->channel }}</span>@endif
                            </div>
                            @if ($communication->status === 'draft')
                                @can('approve', $case)
                                    <x-action-form :action="route('crisis.communications.approve', [$case, $communication])" class="mt-1">
                                        <x-icon-btn icon="verified" tone="info" size="xs" type="submit" show-label>{{ __('Freigeben') }}</x-icon-btn>
                                    </x-action-form>
                                @endcan
                            @elseif ($communication->status === 'approved' && $canManage)
                                <form method="POST" action="{{ route('crisis.communications.sent', [$case, $communication]) }}" class="mt-1 flex items-center gap-1">
                                    @csrf
                                    <input name="channel" required maxlength="100" class="input input-xs input-bordered w-40" placeholder="{{ __('Kanal (Mail/Telefon/Presse)') }}">
                                    <button type="submit" class="btn btn-xs">{{ __('Aussendung dokumentieren') }}</button>
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
                <form method="POST" action="{{ route('crisis.bcm.store', $case) }}" class="mb-3 flex flex-wrap items-end gap-2">
                    @csrf
                    <input name="process_name" required maxlength="200" class="input input-sm input-bordered flex-1" placeholder="{{ __('Kritischer Prozess/Service') }}">
                    <input name="rto_hours" type="number" min="0" class="input input-sm input-bordered w-24" placeholder="RTO h">
                    <input name="rpo_hours" type="number" min="0" class="input input-sm input-bordered w-24" placeholder="RPO h">
                    <input name="workaround" maxlength="1000" class="input input-sm input-bordered w-48" placeholder="{{ __('Workaround') }}">
                    <x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>{{ __('Erfassen') }}</x-icon-btn>
                </form>
            @endif
            @if ($case->continuityImpacts->isEmpty())
                <x-empty-state icon="settings_backup_restore" :title="__('Keine kritischen Prozesse erfasst.')" compact />
            @else
                <ul class="space-y-1 text-sm">
                    @foreach ($case->continuityImpacts as $impact)
                        <li class="flex flex-wrap items-center gap-2">
                            <x-status-badge size="xs" :tone="$impact->status === 'restored' ? 'success' : ($impact->status === 'down' ? 'error' : 'warning')">{{ __("values.{$impact->status}") }}</x-status-badge>
                            <span class="font-medium">{{ $impact->process_name }}</span>
                            @if ($impact->rto_hours !== null)<span class="text-xs text-base-content/60">RTO {{ $impact->rto_hours }} h</span>@endif
                            @if ($impact->rpo_hours !== null)<span class="text-xs text-base-content/60">RPO {{ $impact->rpo_hours }} h</span>@endif
                            @if ($impact->workaround)<span class="text-xs text-base-content/60">{{ $impact->workaround }}</span>@endif
                            @if ($canManage)
                                <form method="POST" action="{{ route('crisis.bcm.update', [$case, $impact]) }}" class="ml-auto flex items-center gap-1">
                                    @csrf @method('PUT')
                                    <select name="status" class="select select-xs select-bordered" data-autosubmit>
                                        @foreach (\App\Models\Crisis\CrisisContinuityImpact::STATUSES as $status)
                                            <option value="{{ $status }}" @selected($impact->status === $status)>{{ __("values.$status") }}</option>
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
                    </select>
                    <input name="linkable_sqid" required maxlength="64" class="input input-sm input-bordered w-40" placeholder="{{ __('Sqid/ID des Vorgangs') }}">
                    <x-icon-btn icon="link" tone="primary" size="sm" type="submit" show-label>{{ __('Verknüpfen') }}</x-icon-btn>
                </form>
            @endif
            @if ($case->links->isEmpty())
                <x-empty-state icon="link" :title="__('Keine verknüpften Vorgänge.')" compact />
            @else
                <ul class="space-y-1 text-sm">
                    @foreach ($case->links as $link)
                        <li>
                            <span class="badge badge-outline badge-xs">{{ \App\Support\EntityType::label($link->linkable_type) }}</span>
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
        @elseif ($case->status === 'all_clear')
            @if ($canManage)
                <form method="POST" action="{{ route('crisis.review.store', $case) }}" class="grid gap-2">
                    @csrf
                    <textarea name="summary" required rows="2" class="textarea textarea-bordered textarea-sm" placeholder="{{ __('Zusammenfassung (Pflicht)') }}"></textarea>
                    <textarea name="lessons" rows="2" class="textarea textarea-bordered textarea-sm" placeholder="{{ __('Lessons Learned') }}"></textarea>
                    <textarea name="follow_up" rows="2" class="textarea textarea-bordered textarea-sm" placeholder="{{ __('Folgemaßnahmen') }}"></textarea>
                    <div><x-icon-btn icon="fact_check" tone="primary" size="sm" type="submit" show-label>{{ __('Nachbereitung speichern') }}</x-icon-btn></div>
                </form>
            @endif
        @else
            <p class="text-sm text-base-content/60">{{ __('Nachbereitung wird nach der Entwarnung möglich.') }}</p>
        @endif
    </x-card>
</x-page-shell>
@endsection
