{{--
  Created on   : Mon Sep 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _show_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Lesedialog einer Notiz (Feature 154, MVP-777), in #entry-modal geladen.
  Variablen: $note (CommunicationNote), $contextUrl (string|null)
--}}

<x-modal
    :title="$note->subject"
    :eyebrow="$note->notableKindLabel()"
    :icon="$note->type->icon()"
    tone="primary"
    size="lg">

    <div class="space-y-4 text-sm">
        <div class="flex flex-wrap items-center gap-2">
            <span class="font-medium">{{ $note->type->label() }}</span>
            <x-status-badge :tone="$note->direction->tone()">{{ $note->direction->label() }}</x-status-badge>
            @if ($note->confidential)
                <x-status-badge tone="error">{{ __('communication.badge.confidential') }}</x-status-badge>
            @else
                <x-status-badge :tone="$note->visibility->tone()">{{ $note->visibility->label() }}</x-status-badge>
            @endif
            <span class="text-muted">{{ $note->occurred_at->fdatetime() }}</span>
        </div>

        <dl class="grid grid-cols-1 gap-x-4 gap-y-1 sm:grid-cols-[auto_1fr]">
            <dt class="text-muted">{{ __('communication.field.storage') }}</dt>
            <dd>
                @if ($note->isOrganizationNote())
                    {{ __('communication.storage.internal') }}
                @elseif ($contextUrl)
                    {{ $note->notableKindLabel() }}: <a href="{{ $contextUrl }}" class="link link-hover">{{ $note->notableLabel() }}</a>
                @else
                    {{ $note->notableKindLabel() }}: {{ $note->notableLabel() }}
                @endif
            </dd>
            <dt class="text-muted">{{ __('communication.field.creator') }}</dt>
            <dd>{{ $note->creator?->name ?? '—' }}</dd>
            @if ($note->participants->isNotEmpty())
                <dt class="text-muted">{{ __('communication.field.participants') }}</dt>
                <dd>{{ $note->participants->map(fn($p) => $p->name . ($p->role ? ' (' . $p->role . ')' : ''))->implode(', ') }}</dd>
            @endif
        </dl>

        <div>
            <h4 class="font-semibold">{{ __('communication.field.body') }}</h4>
            <p class="whitespace-pre-wrap text-base-content/80">{{ $note->body }}</p>
        </div>

        @if ($note->result)
            <div>
                <h4 class="font-semibold">{{ __('communication.field.result') }}</h4>
                <p class="whitespace-pre-wrap text-base-content/80">{{ $note->result }}</p>
            </div>
        @endif

        @if ($note->next_action)
            <div class="rounded-box border border-warning/40 bg-warning/5 p-3">
                <h4 class="flex items-center gap-2 font-semibold">
                    <x-icon name="pending_actions" class="text-warning" /> {{ __('communication.field.next_action') }}
                </h4>
                <p>{{ $note->next_action }}</p>
                <p class="text-xs text-muted">
                    @if ($note->next_action_due_at)
                        {{ __('communication.field.next_action_due_at') }}: {{ $note->next_action_due_at->fdate() }}
                    @endif
                    @if ($note->nextActionUser)
                        · {{ $note->nextActionUser->name }}
                    @endif
                    @if ($note->next_action_completed_at)
                        · <span class="text-success">{{ __('communication.badge.followup_done') }} ({{ $note->next_action_completed_at->fdate() }})</span>
                    @endif
                </p>
            </div>
        @endif

        @can('update', $note)
            <div class="flex justify-end">
                <x-icon-btn icon="edit" tone="outline" size="sm" data-entry-modal-trigger
                            :href="route('communication-notes.edit', $note)" show-label>{{ __('communication.action.edit') }}</x-icon-btn>
            </div>
        @endcan
    </div>
</x-modal>
