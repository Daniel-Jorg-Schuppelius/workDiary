{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _panel.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kommunikations-Panel (MVP-012). Erwartet: $notable (Model), $notableKind ('diary'|'customer'|'project')
--}}
@php
    /** @var \App\Models\User $panelUser */
    $panelUser = \Illuminate\Support\Facades\Auth::user();
    $canViewAny = \Illuminate\Support\Facades\Gate::allows('viewAny', \App\Models\CommunicationNote::class);
@endphp

@if ($canViewAny)
@php
    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\CommunicationNote> $notes */
    $notes = $notable->communicationNotes()
        ->visibleTo($panelUser)
        ->with(['creator', 'participants', 'nextActionUser'])
        ->get();
    $openFollowUps = $notes->filter->hasOpenFollowUp()->sortBy('next_action_due_at');
    $canCreate = \Illuminate\Support\Facades\Gate::allows('create', \App\Models\CommunicationNote::class);
    $canPublish = \Illuminate\Support\Facades\Gate::allows('publishToCustomer', \App\Models\CommunicationNote::class);
    $canManageConfidential = \Illuminate\Support\Facades\Gate::allows('manageConfidential', \App\Models\CommunicationNote::class);
@endphp

<x-card as="section" id="communication-notes">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h2 class="flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold text-base-content">
            <x-icon name="forum" class="text-base-content/60" /> {{ __('communication.title.index') }}
            <span class="font-normal text-base-content/50">({{ $notes->count() }})</span>
        </h2>
        @if ($canCreate)
            <x-icon-btn icon="add_comment" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('communication-notes.create', ['notable_kind' => $notableKind, 'notable_id' => \App\Support\Sqid::encode(get_class($notable), (int) $notable->id)])"
                        show-label>{{ __('communication.action.create') }}</x-icon-btn>
        @endif
    </div>

    @if ($openFollowUps->isNotEmpty())
        <div class="mb-4 rounded-box border border-warning/40 bg-warning/5 p-4">
            <h3 class="mb-2 flex items-center gap-2 text-sm font-semibold text-base-content">
                <x-icon name="pending_actions" class="text-warning" /> {{ __('communication.title.followups') }}
            </h3>
            <ul class="space-y-2">
                @foreach ($openFollowUps as $note)
                    @php
                        $dueOverdue = $note->next_action_due_at && $note->next_action_due_at->isPast();
                    @endphp
                    <li class="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <div class="min-w-0">
                            <a href="#communication-note-{{ $note->id }}" class="font-medium link-hover">{{ $note->next_action }}</a>
                            <span class="text-base-content/60">
                                — {{ $note->subject }}
                                · {{ __('communication.field.next_action_user') }}: {{ optional($note->nextActionUser)->name ?? '—' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($note->next_action_due_at)
                                <x-status-badge :tone="$dueOverdue ? 'error' : 'warning'">{{ $note->next_action_due_at->fdate() }}</x-status-badge>
                            @endif
                            @can('completeFollowup', $note)
                                <form method="POST" action="{{ route('communication-notes.followup-complete', $note) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-xs btn-outline btn-success">
                                        {{ __('communication.action.complete_followup') }}
                                    </button>
                                </form>
                            @endcan
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($notes->isEmpty())
        <x-empty-state compact icon='<span class="material-symbols-outlined">forum</span>'
                       :title="__('communication.title.index')"
                       :message="__('communication.empty')" />
    @else
        <ul class="space-y-3">
            @foreach ($notes as $note)
                @php
                    $canUpdate = \Illuminate\Support\Facades\Gate::allows('update', $note);
                    $canDelete = \Illuminate\Support\Facades\Gate::allows('delete', $note);
                    $canComplete = \Illuminate\Support\Facades\Gate::allows('completeFollowup', $note);
                    $isCustomerVisible = $note->visibility === \App\Enums\Communication\CommunicationVisibility::Customer;
                    $publishable = $canPublish
                        && ! $isCustomerVisible
                        && ! $note->confidential
                        && $note->direction !== \App\Enums\Communication\CommunicationDirection::Internal;
                @endphp
                <li id="communication-note-{{ $note->id }}" class="rounded-box border border-base-300 bg-base-200 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="mb-1 flex flex-wrap items-center gap-2">
                                <span class="flex items-center gap-1 text-sm font-medium text-base-content/80"
                                      title="{{ $note->type->label() }}">
                                    <x-icon :name="$note->type->icon()" class="text-base-content/60" /> {{ $note->type->label() }}
                                </span>
                                <x-status-badge :tone="$note->direction->tone()">{{ $note->direction->label() }}</x-status-badge>
                                @if ($note->confidential)
                                    <x-status-badge tone="error">{{ __('communication.badge.confidential') }}</x-status-badge>
                                @else
                                    <x-status-badge :tone="$note->visibility->tone()">{{ $note->visibility->label() }}</x-status-badge>
                                @endif
                                <span class="text-xs text-base-content/60">{{ $note->occurred_at->fdatetime() }}</span>
                            </div>
                            <p class="font-semibold text-base-content">{{ $note->subject }}</p>
                            <p class="mt-1 whitespace-pre-wrap text-sm text-base-content/80">{{ \CommonToolkit\Helper\Data\StringHelper::truncate($note->body, 400) }}</p>
                            @if ($note->result)
                                <p class="mt-1 text-sm italic text-base-content/70">
                                    {{ __('communication.field.result') }}: {{ $note->result }}
                                </p>
                            @endif
                            @if ($note->next_action)
                                <p class="mt-1 text-xs text-base-content/70">
                                    <x-icon name="pending_actions" class="align-text-bottom text-warning" />
                                    {{ $note->next_action }}
                                    @if ($note->next_action_due_at)
                                        · {{ __('communication.field.next_action_due_at') }}: {{ $note->next_action_due_at->fdate() }}
                                    @endif
                                    @if ($note->nextActionUser)
                                        · {{ optional($note->nextActionUser)->name }}
                                    @endif
                                    @if ($note->next_action_completed_at)
                                        · <span class="text-success">{{ __('communication.badge.followup_done') }} ({{ $note->next_action_completed_at->fdate() }})</span>
                                    @endif
                                </p>
                            @endif
                            <p class="mt-2 text-xs text-base-content/60">
                                {{ __('communication.field.creator') }}: {{ optional($note->creator)->name ?? '—' }}
                                @if ($note->participants->isNotEmpty())
                                    · {{ __('communication.field.participants') }}:
                                    {{ $note->participants->map(fn($p) => $p->name . ($p->role ? ' (' . $p->role . ')' : ''))->implode(', ') }}
                                @endif
                            </p>
                        </div>

                        @if ($canUpdate || $canDelete || $publishable || $canManageConfidential || $canComplete)
                            <div class="flex flex-wrap gap-1">
                                @if ($canUpdate)
                                    <x-icon-btn icon="edit" tone="outline" size="xs"
                                                data-entry-modal-trigger
                                                :href="route('communication-notes.edit', $note)"
                                                :label="__('communication.action.edit')" />
                                @endif

                                @if ($note->hasOpenFollowUp() && $canComplete)
                                    <form method="POST" action="{{ route('communication-notes.followup-complete', $note) }}">
                                        @csrf
                                        <x-icon-btn icon="task_alt" tone="success" size="xs" type="submit"
                                                    :label="__('communication.action.complete_followup')" />
                                    </form>
                                @endif

                                @if ($publishable)
                                    <form method="POST" action="{{ route('communication-notes.publish', $note) }}"
                                          data-confirm-dialog
                                          data-confirm-title="{{ __('communication.action.publish') }}"
                                          data-confirm-message="{{ __('communication.confirm_publish') }}"
                                          data-confirm-icon="visibility"
                                          data-confirm-tone="success"
                                          data-confirm-label="{{ __('communication.action.publish') }}">
                                        @csrf
                                        <x-icon-btn icon="visibility" tone="success" size="xs" type="submit"
                                                    :label="__('communication.action.publish')" />
                                    </form>
                                @endif

                                @if ($canManageConfidential && ! $isCustomerVisible)
                                    <form method="POST" action="{{ route('communication-notes.confidential', $note) }}">
                                        @csrf
                                        <input type="hidden" name="confidential" value="{{ $note->confidential ? 0 : 1 }}">
                                        <x-icon-btn :icon="$note->confidential ? 'lock_open' : 'lock'" tone="warning" size="xs" type="submit"
                                                    :label="$note->confidential ? __('communication.action.unmark_confidential') : __('communication.action.mark_confidential')" />
                                    </form>
                                @endif

                                @if ($canDelete)
                                    <form method="POST" action="{{ route('communication-notes.destroy', $note) }}"
                                          data-confirm-dialog
                                          data-confirm-title="{{ __('communication.action.delete') }}"
                                          data-confirm-message="{{ __('communication.confirm_delete') }}"
                                          data-confirm-icon="delete"
                                          data-confirm-tone="error"
                                          data-confirm-label="{{ __('communication.action.delete') }}">
                                        @csrf @method('DELETE')
                                        <x-icon-btn icon="delete" tone="error" size="xs" type="submit" :label="__('communication.action.delete')" />
                                    </form>
                                @endif
                            </div>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
@endif
