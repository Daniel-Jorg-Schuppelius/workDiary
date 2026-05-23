{{-- Offene-Punkte-Panel. Erwartet: $subject (Model), $subjectKind ('diary'|'project'|'customer') --}}
@php
    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\OpenIssue> $issues */
    $issues = $subject->openIssues()->with(['assignee', 'creator'])->get();
    $canCreate = \Illuminate\Support\Facades\Gate::allows('create', \App\Models\OpenIssue::class);
    $canAssign = \Illuminate\Support\Facades\Gate::allows('assign', \App\Models\OpenIssue::class);
    $canPublishToCustomer = \Illuminate\Support\Facades\Gate::allows('publishToCustomer', \App\Models\OpenIssue::class);
@endphp

<section id="open-issues" class="rounded-box border border-base-300 bg-base-100 p-6 shadow-xs">
    <header class="mb-4 flex items-center justify-between gap-4">
        <h2 class="font-['Space_Grotesk'] text-xl font-bold text-base-content">
            {{ __('open-issue.title.index') }}
            <span class="ml-2 text-sm font-normal text-base-content/60">({{ $issues->count() }})</span>
        </h2>
        @if ($canCreate)
            <details class="dropdown dropdown-end">
                <summary class="btn btn-sm btn-primary">
                    <span class="material-symbols-outlined text-base">add</span>
                    <span>{{ __('open-issue.action.create') }}</span>
                </summary>
                <div class="dropdown-content z-10 mt-2 w-96 rounded-box border border-base-300 bg-base-100 p-4 shadow-xl">
                    <form method="POST" action="{{ route('open-issues.store') }}" class="space-y-3">
                        @csrf
                        <input type="hidden" name="subject_kind" value="{{ $subjectKind }}">
                        <input type="hidden" name="subject_id" value="{{ $subject->id }}">

                        <label class="form-control w-full">
                            <span class="label-text">{{ __('open-issue.field.title') }}</span>
                            <input type="text" name="title" required maxlength="200"
                                   class="input input-bordered input-sm w-full"
                                   value="{{ old('title') }}">
                        </label>

                        <label class="form-control w-full">
                            <span class="label-text">{{ __('open-issue.field.description') }}</span>
                            <textarea name="description" rows="3"
                                      class="textarea textarea-bordered textarea-sm w-full">{{ old('description') }}</textarea>
                        </label>

                        <div class="grid grid-cols-2 gap-2">
                            <label class="form-control">
                                <span class="label-text">{{ __('open-issue.field.severity') }}</span>
                                <select name="severity" class="select select-bordered select-sm">
                                    @foreach (\App\Enums\OpenIssue\OpenIssueSeverity::cases() as $sev)
                                        <option value="{{ $sev->value }}" @selected(old('severity', 'medium') === $sev->value)>
                                            {{ $sev->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="form-control">
                                <span class="label-text">{{ __('open-issue.field.category') }}</span>
                                <input type="text" name="category" maxlength="100"
                                       class="input input-bordered input-sm" value="{{ old('category') }}">
                            </label>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <label class="form-control">
                                <span class="label-text">{{ __('open-issue.field.due_at') }}</span>
                                <input type="datetime-local" name="due_at"
                                       class="input input-bordered input-sm" value="{{ old('due_at') }}">
                            </label>
                            @if ($canPublishToCustomer)
                                <label class="form-control">
                                    <span class="label-text">{{ __('open-issue.field.visibility') }}</span>
                                    <select name="visibility" class="select select-bordered select-sm">
                                        @foreach (\App\Enums\OpenIssue\OpenIssueVisibility::cases() as $vis)
                                            <option value="{{ $vis->value }}" @selected(old('visibility', 'internal') === $vis->value)>
                                                {{ $vis->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif
                        </div>

                        <button type="submit" class="btn btn-sm btn-primary w-full">
                            {{ __('open-issue.action.create') }}
                        </button>
                    </form>
                </div>
            </details>
        @endif
    </header>

    @if ($issues->isEmpty())
        <p class="text-sm text-base-content/60">{{ __('Noch keine offenen Punkte vorhanden.') }}</p>
    @else
        <ul class="space-y-3">
            @foreach ($issues as $issue)
                @php
                    $canUpdate = \Illuminate\Support\Facades\Gate::allows('update', $issue);
                    $canDelete = \Illuminate\Support\Facades\Gate::allows('delete', $issue);
                    $status = $issue->status;
                    $sev = $issue->severity;
                @endphp
                <li class="rounded-box border border-base-300 bg-base-200 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="mb-1 flex flex-wrap items-center gap-2">
                                <span @class([
                                    'badge badge-sm',
                                    'badge-warning' => $status->value === 'open',
                                    'badge-info' => $status->value === 'inProgress',
                                    'badge-error' => $status->value === 'blocked',
                                    'badge-success' => $status->value === 'done',
                                    'badge-ghost' => in_array($status->value, ['wontDo', 'reopened'], true),
                                ])>{{ $status->label() }}</span>
                                <span @class([
                                    'badge badge-sm badge-outline',
                                    'badge-error' => $sev->value === 'critical',
                                    'badge-warning' => $sev->value === 'high',
                                ])>{{ $sev->label() }}</span>
                                @if ($issue->category)
                                    <span class="badge badge-sm badge-ghost">{{ $issue->category }}</span>
                                @endif
                                @if ($issue->visibility->value === 'customer')
                                    <span class="badge badge-sm badge-accent">{{ $issue->visibility->label() }}</span>
                                @endif
                                @if ($issue->due_at)
                                    <span @class([
                                        'badge badge-sm',
                                        'badge-error' => $issue->due_at->isPast() && ! $issue->closed_at,
                                        'badge-ghost' => ! ($issue->due_at->isPast() && ! $issue->closed_at),
                                    ])>{{ __('open-issue.field.due_at') }}: {{ $issue->due_at->format('d.m.Y') }}</span>
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
                                    · {{ __('open-issue.field.closed_at') }}: {{ $issue->closed_at->format('d.m.Y H:i') }}
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
                                        <details class="dropdown dropdown-end">
                                            <summary class="btn btn-xs btn-outline">{{ __('open-issue.action.' . $action) }}</summary>
                                            <div class="dropdown-content z-10 mt-1 w-80 rounded-box border border-base-300 bg-base-100 p-3 shadow-xl">
                                                <form method="POST" action="{{ route('open-issues.transition', ['issue' => $issue, 'action' => $action]) }}" class="space-y-2">
                                                    @csrf
                                                    <label class="form-control w-full">
                                                        <span class="label-text">
                                                            {{ $requiresResolution ? __('open-issue.field.resolution') : __('open-issue.field.reason') }}
                                                        </span>
                                                        <textarea name="{{ $requiresResolution ? 'resolution' : 'reason' }}"
                                                                  rows="3" required minlength="3"
                                                                  class="textarea textarea-bordered textarea-sm w-full"></textarea>
                                                    </label>
                                                    <button type="submit" class="btn btn-xs btn-primary w-full">
                                                        {{ __('open-issue.action.' . $action) }}
                                                    </button>
                                                </form>
                                            </div>
                                        </details>
                                    @else
                                        <form method="POST" action="{{ route('open-issues.transition', ['issue' => $issue, 'action' => $action]) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-outline">
                                                {{ __('open-issue.action.' . $action) }}
                                            </button>
                                        </form>
                                    @endif
                                @endforeach

                                @if ($canDelete)
                                    <form method="POST" action="{{ route('open-issues.destroy', $issue) }}"
                                          data-confirm-dialog
                                          data-confirm-title="{{ __('open-issue.action.delete') }}"
                                          data-confirm-message="{{ __('Offenen Punkt wirklich löschen?') }}"
                                          data-confirm-label="{{ __('open-issue.action.delete') }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-error btn-outline">
                                            <span class="material-symbols-outlined text-sm">delete</span>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>
