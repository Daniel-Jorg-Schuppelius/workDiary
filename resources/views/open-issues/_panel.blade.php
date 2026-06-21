{{-- Offene-Punkte-Panel. Erwartet: $subject (Model), $subjectKind ('diary'|'project'|'customer') --}}
@php
    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\OpenIssue> $issues */
    $issues = $subject->openIssues()->with(['assignee', 'creator'])->get();
    $canCreate = \Illuminate\Support\Facades\Gate::allows('create', \App\Models\OpenIssue::class);
    $canAssign = \Illuminate\Support\Facades\Gate::allows('assign', \App\Models\OpenIssue::class);
    $canPublishToCustomer = \Illuminate\Support\Facades\Gate::allows('publishToCustomer', \App\Models\OpenIssue::class);
@endphp

<x-card as="section" id="open-issues" :title="__('open-issue.title.index')" icon="flag" :count="$issues->count()">
    @if ($canCreate)
        <x-slot:actions>
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('open-issues.create', ['subject_kind' => $subjectKind, 'subject_id' => \App\Support\Sqid::encode(get_class($subject), (int) $subject->id)])"
                        show-label>{{ __('open-issue.action.create') }}</x-icon-btn>
        </x-slot:actions>
    @endif

    @if ($issues->isEmpty())
        <x-empty-state compact icon='<span class="material-symbols-outlined">flag</span>'
                       :title="__('open-issue.title.index')"
                       :message="__('Noch keine offenen Punkte vorhanden.')" />
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
                            <p class="mt-2 text-xs text-base-content/60">
                                {{ __('open-issue.field.creator') }}: {{ optional($issue->creator)->name ?? '—' }}
                                · {{ __('open-issue.field.assignee') }}: {{ optional($issue->assignee)->name ?? '—' }}
                                @if ($issue->closed_at)
                                    · {{ __('open-issue.field.closed_at') }}: {{ $issue->closed_at->fdatetime() }}
                                @endif
                            </p>
                            @if ($issue->closed_reason)
                                <p class="mt-1 text-xs italic text-base-content/70">
                                    {{ __('open-issue.field.reason') }}: {{ $issue->closed_reason }}
                                </p>
                            @endif
                        </div>

                        @if ($canUpdate || $canDelete)
                            <div class="flex flex-wrap gap-1">
                                @foreach ($issue->status->allowedTransitions() as $action)
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
                            </div>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
