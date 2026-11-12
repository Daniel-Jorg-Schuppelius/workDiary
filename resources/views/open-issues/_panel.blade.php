{{--
  Created on   : Sat May 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _panel.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Offene-Punkte-Panel. Erwartet: $subject (Model), $subjectKind ('diary'|'project'|'customer'|'asset') --}}
@php
    // Filter (MVP-1597): Status, Schwere, zuständige Person — serverseitig über die Seitenadresse.
    $issueTotal = $subject->openIssues()->count();
    $issueStatus = \App\Enums\OpenIssue\OpenIssueStatus::tryFrom((string) request('issue_status'));
    $issueSeverity = \App\Enums\OpenIssue\OpenIssueSeverity::tryFrom((string) request('issue_severity'));
    $issueAssigneeRaw = (string) request('issue_assignee');
    $issueAssigneeId = $issueAssigneeRaw !== '' && $issueAssigneeRaw !== 'none'
        ? \App\Support\Sqid::decodeOrNumeric(\App\Models\Platform\User::class, $issueAssigneeRaw)
        : null;
    $issueFiltered = $issueStatus !== null || $issueSeverity !== null || $issueAssigneeRaw !== '';
    $issueAssignees = \App\Models\Platform\User::query()
        ->whereIn('id', $subject->openIssues()->reorder()->whereNotNull('assignee_user_id')->select('assignee_user_id'))
        ->orderBy('name')
        ->get(['id', 'name']);
    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Diary\OpenIssue> $issues */
    $issues = $subject->openIssues()
        ->when($issueStatus !== null, fn ($q) => $q->where('status', $issueStatus->value))
        ->when($issueSeverity !== null, fn ($q) => $q->where('severity', $issueSeverity->value))
        ->when($issueAssigneeRaw === 'none', fn ($q) => $q->whereNull('assignee_user_id'))
        // Unbekannte Person ergibt keine Treffer statt aller.
        ->when($issueAssigneeRaw !== '' && $issueAssigneeRaw !== 'none', fn ($q) => $q->where('assignee_user_id', $issueAssigneeId ?? 0))
        ->with(['assignee', 'creator', 'followUps'])
        ->withCount(['comments', 'attachments'])
        ->get();
    $canCreate = \Illuminate\Support\Facades\Gate::allows('create', \App\Models\Diary\OpenIssue::class);
    $canAssign = \Illuminate\Support\Facades\Gate::allows('assign', \App\Models\Diary\OpenIssue::class);
    $canPublishToCustomer = \Illuminate\Support\Facades\Gate::allows('publishToCustomer', \App\Models\Diary\OpenIssue::class);
    // Ticket aus dem offenen Punkt (MVP-1549): Angebot und Verweis kommen über den Contract vom Helpdesk.
    $ticketOpener = app(\App\Services\Diary\Contracts\IssueTicketOpener::class);
    $canOpenTicket = auth()->user() !== null && $ticketOpener->available(auth()->user());
    $issueTickets = auth()->user() !== null ? $ticketOpener->linksFor($issues->modelKeys(), auth()->user()) : [];
@endphp

<x-card as="section" id="open-issues" :title="__('open-issue.title.index')" icon="flag" :count="$issueFiltered ? $issues->count() . ' / ' . $issueTotal : $issueTotal">
    @if ($canCreate)
        <x-slot:actions>
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('open-issues.create', ['subject_kind' => $subjectKind, 'subject_id' => \App\Support\Sqid::encode(get_class($subject), (int) $subject->id)])"
                        show-label>{{ __('open-issue.action.create') }}</x-icon-btn>
        </x-slot:actions>
    @endif

    @if ($issueTotal > 0)
        <x-filter-bar class="mb-4" :action="url()->current() . '#open-issues'" :reset="url()->current()">
            <x-filter-field :label="__('open-issue.field.status')" for="issue-status">
                <select id="issue-status" name="issue_status" class="select select-sm select-bordered shrink-0">
                    <option value="">{{ __('open-issue.filter.all_statuses') }}</option>
                    @foreach (\App\Enums\OpenIssue\OpenIssueStatus::cases() as $option)
                        <option value="{{ $option->value }}" @selected($issueStatus === $option)>{{ $option->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>
            <x-filter-field :label="__('open-issue.field.severity')" for="issue-severity">
                <select id="issue-severity" name="issue_severity" class="select select-sm select-bordered shrink-0">
                    <option value="">{{ __('open-issue.filter.all_severities') }}</option>
                    @foreach (\App\Enums\OpenIssue\OpenIssueSeverity::cases() as $option)
                        <option value="{{ $option->value }}" @selected($issueSeverity === $option)>{{ $option->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>
            <x-filter-field :label="__('open-issue.field.assignee')" for="issue-assignee">
                <select id="issue-assignee" name="issue_assignee" class="select select-sm select-bordered shrink-0">
                    <option value="">{{ __('open-issue.filter.all_assignees') }}</option>
                    <option value="none" @selected($issueAssigneeRaw === 'none')>{{ __('Unzugewiesen') }}</option>
                    @foreach ($issueAssignees as $option)
                        <option value="{{ $option->sqid }}" @selected($issueAssigneeId === $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </x-filter-field>
        </x-filter-bar>
    @endif

    @if ($issues->isEmpty())
        <x-empty-state compact icon="flag"
                       :title="__('open-issue.title.index')"
                       :message="$issueFiltered ? __('open-issue.filter.no_match') : __('Noch keine offenen Punkte vorhanden.')" />
    @else
        <ul class="space-y-3">
            @foreach ($issues as $issue)
                @php
                    $canUpdate = \Illuminate\Support\Facades\Gate::allows('update', $issue);
                    $canDelete = \Illuminate\Support\Facades\Gate::allows('delete', $issue);
                    $status = $issue->status;
                    $sev = $issue->severity;
                    $statusTone = [
                        'open' => 'warning',
                        'inProgress' => 'info',
                        'blocked' => 'error',
                        'done' => 'success',
                        'wontDo' => 'ghost',
                        'reopened' => 'ghost',
                    ][$status->value] ?? 'ghost';
                    $sevTone = ['critical' => 'error', 'high' => 'warning'][$sev->value] ?? 'ghost';
                    $dueOverdue = $issue->due_at && $issue->due_at->isPast() && ! $issue->closed_at;
                @endphp
                <li id="open-issue-{{ $issue->id }}" class="rounded-box border border-base-300 bg-base-200 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="mb-1 flex flex-wrap items-center gap-2">
                                <x-status-badge :tone="$statusTone">{{ $status->label() }}</x-status-badge>
                                <x-status-badge :tone="$sevTone">{{ $sev->label() }}</x-status-badge>
                                @if ($issue->category)
                                    <x-status-badge tone="ghost">{{ $issue->category }}</x-status-badge>
                                @endif
                                @if ($issue->visibility->value === 'customer')
                                    <x-status-badge tone="accent">{{ $issue->visibility->label() }}</x-status-badge>
                                @endif
                                @if ($issue->due_at)
                                    <x-status-badge :tone="$dueOverdue ? 'error' : 'ghost'">{{ __('open-issue.field.due_at') }}: {{ $issue->due_at->fdate() }}</x-status-badge>
                                @endif
                            </div>
                            <p class="font-semibold text-base-content">{{ $issue->title }}</p>
                            @if ($issue->description)
                                <p class="mt-1 whitespace-pre-wrap text-sm text-base-content/80">{{ $issue->description }}</p>
                            @endif
                            <p class="mt-2 text-xs text-muted">
                                {{ __('open-issue.field.creator') }}: {{ optional($issue->creator)->name ?? '—' }}
                                · {{ __('open-issue.field.assignee') }}: {{ optional($issue->assignee)->name ?? '—' }}
                                @if ($issue->comments_count > 0)
                                    · {{ __('Kommentare') }}: {{ $issue->comments_count }}
                                @endif
                                @if ($issue->attachments_count > 0)
                                    · {{ __('Anhänge') }}: {{ $issue->attachments_count }}
                                @endif
                                @if ($issue->closed_at)
                                    · {{ __('open-issue.field.closed_at') }}: {{ $issue->closed_at->fdatetime() }}
                                @endif
                            </p>
                            @if ($issue->closed_reason)
                                <p class="mt-1 text-xs italic text-base-content/70">
                                    {{ __('open-issue.field.reason') }}: {{ $issue->closed_reason }}
                                </p>
                            @endif
                            @if (isset($issueTickets[$issue->id]))
                                <p class="mt-1 text-xs text-base-content/70" data-open-issue-ticket>
                                    {{ __('open-issue.field.ticket') }}:
                                    @if ($issueTickets[$issue->id]['url'] !== null)
                                        <a class="link link-primary" href="{{ $issueTickets[$issue->id]['url'] }}">{{ $issueTickets[$issue->id]['label'] }}</a>
                                    @else
                                        {{ $issueTickets[$issue->id]['label'] }}
                                    @endif
                                </p>
                            @endif
                            @foreach ($issue->followUps as $followUp)
                                <p class="mt-1 text-xs text-base-content/70">
                                    {{ __('open-issue.field.follow_up') }}:
                                    <x-order-link :entry="$followUp" link-class="link link-primary">{{ $followUp->title ?: __('Auftrag') }} · {{ $followUp->start_at?->fdate() ?? '—' }}</x-order-link>
                                </p>
                            @endforeach
                        </div>

                        <div class="flex flex-wrap gap-1">
                            @can('view', $issue)
                                <x-icon-btn icon="visibility" size="xs" tone="outline" data-entry-modal-trigger
                                            :href="route('open-issues.show', $issue)" show-label>{{ __('open-issue.action.details') }}</x-icon-btn>
                            @endcan
                            @if ($canUpdate || $canDelete)
                                @foreach ($issue->status->allowedActions() as $action)
                                    @if (! $canUpdate)
                                        @break
                                    @endif
                                    @php
                                        $requiresReason = in_array($action, ['block', 'wontDo', 'reopen'], true);
                                        $requiresResolution = $action === 'complete';
                                    @endphp
                                    @if ($requiresReason || $requiresResolution)
                                        <x-icon-btn size="xs" tone="outline"
                                                    data-entry-modal-trigger
                                                    :href="route('open-issues.transition.form', ['issue' => $issue, 'action' => $action])"
                                                    show-label>{{ __('open-issue.action.' . $action) }}</x-icon-btn>
                                    @else
                                        <form method="POST" action="{{ route('open-issues.transition', ['issue' => $issue, 'action' => $action]) }}">
                                            @csrf
                                            <x-button type="submit" size="xs" tone="outline">
                                                {{ __('open-issue.action.' . $action) }}
                                            </x-button>
                                        </form>
                                    @endif
                                @endforeach

                                @if ($canUpdate && ! $issue->closed_at)
                                    <x-icon-btn icon="edit" size="xs" tone="outline" data-entry-modal-trigger
                                                :href="route('open-issues.edit', $issue)" show-label>{{ __('open-issue.action.edit') }}</x-icon-btn>
                                @endif
                                @if ($canAssign && ! $issue->closed_at)
                                    <x-icon-btn icon="person_add" size="xs" tone="outline" data-entry-modal-trigger
                                                :href="route('open-issues.assign.form', $issue)" show-label>{{ __('open-issue.action.assign') }}</x-icon-btn>
                                @endif

                                {{-- Folgeauftrag (Feature 139, mehrere seit MVP-991): öffnet den Auftragsdialog vorbefüllt; Verknüpfung beim Speichern. --}}
                                @if ($canUpdate && ! $issue->closed_at)
                                    <x-icon-btn icon="add_task" size="xs" tone="outline"
                                                data-entry-modal-trigger
                                                :href="route('diary.create', ['open_issue' => $issue->sqid])"
                                                show-label>{{ __('open-issue.action.followUp') }}</x-icon-btn>
                                @endif

                                @if ($canUpdate && $canOpenTicket && ! $issue->closed_at && ! isset($issueTickets[$issue->id]))
                                    <form method="POST" action="{{ route('open-issues.ticket', $issue) }}">
                                        @csrf
                                        <x-icon-btn icon="support_agent" size="xs" tone="outline" type="submit" show-label>{{ __('open-issue.action.openTicket') }}</x-icon-btn>
                                    </form>
                                @endif

                                @if ($canDelete)
                                    <x-action-form :action="route('open-issues.destroy', $issue)" method="DELETE"
                                          data-confirm-title="{{ __('open-issue.action.delete') }}"
                                          :confirm="__('Offenen Punkt wirklich löschen?')"
                                          confirm-icon="delete"
                                          confirm-tone="error"
                                          :confirm-label="__('open-issue.action.delete')">
                                        <x-icon-btn icon="delete" tone="error" size="xs" type="submit" :label="__('open-issue.action.delete')" />
                                    </x-action-form>
                                @endif
                            @endif
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
