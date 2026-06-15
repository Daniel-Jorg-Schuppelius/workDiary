@extends('layouts.app')
@section('title', $event->displayNo())
@section('nav-title', $event->displayNo())
@section('content')
@php
    // Severity-Akzent (linke Kante + getönter Icon-Kreis). 'ghost' (Low) hat
    // keine eigene Rahmenfarbe → neutraler base-300-Rahmen.
    $tone = $event->severity->tone();
    $accentBorder = match ($tone) {
        'error' => 'border-error',
        'warning' => 'border-warning',
        'info' => 'border-info',
        'success' => 'border-success',
        default => 'border-base-300',
    };
    $accentText = match ($tone) {
        'error' => 'text-error',
        'warning' => 'text-warning',
        'info' => 'text-info',
        'success' => 'text-success',
        default => 'text-base-content/60',
    };
@endphp
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$event->kind->label() . ' · ' . $event->severity->label()"
                        :badge="$event->status->label()" :badgeTone="$event->status->tone()">
            <x-slot:actions>
                @if ($canManage)
                    <x-icon-btn icon="edit" tone="outline" size="sm"
                                data-entry-modal-trigger
                                :href="route('safety-events.edit', $event)"
                                show-label>{{ __('safety.action.edit') }}</x-icon-btn>
                @endif
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                            :href="route('safety-events.index')"
                            show-label>{{ __('safety.action.back') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            {{-- Kopf: Severity-Akzent, Art-Icon, Nummer, Badges --}}
            <x-card class="border-l-4 {{ $accentBorder }}">
                <div class="flex items-start gap-4">
                    <div class="grid size-12 shrink-0 place-items-center rounded-box bg-base-200 {{ $accentText }}">
                        <x-icon :name="$event->kind->icon()" class="size-6" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-['Space_Grotesk'] text-lg font-semibold">{{ $event->displayNo() }}</h2>
                            <x-status-badge :tone="$event->severity->tone()" size="sm">{{ $event->severity->label() }}</x-status-badge>
                            <x-status-badge :tone="$event->status->tone()" size="sm">{{ $event->status->label() }}</x-status-badge>
                        </div>
                        <p class="mt-1 text-sm text-base-content/70">
                            {{ $event->kind->label() }}
                            @if ($event->occurred_at)
                                <span class="text-base-content/40">·</span>
                                {{ $event->occurred_at->format('d.m.Y H:i') }}
                            @endif
                        </p>
                    </div>
                </div>

                <div class="divider my-3"></div>

                <x-detail-grid>
                    <x-detail-grid.row :label="__('safety.field.occurred_at')" :value="optional($event->occurred_at)->format('d.m.Y H:i')" />
                    <x-detail-grid.row :label="__('safety.field.location')" :value="$event->location ?? '–'" />
                    <x-detail-grid.row :label="__('safety.field.affected_person')" :value="$event->affected_person ?? '–'" />
                    <x-detail-grid.row :label="__('safety.field.reporter')" :value="$event->reporter?->name ?? '–'" />
                    @if ($event->subject)
                        <x-detail-grid.row :label="__('safety.field.subject')"
                                           :value="class_basename($event->subject) . ': ' . ($event->subject->name ?? $event->subject->title ?? ('#' . $event->subject->getKey()))" />
                    @endif
                    <x-detail-grid.row :label="__('safety.field.description')" :value="$event->description" />
                    <x-detail-grid.row :label="__('safety.field.immediate_action')" :value="$event->immediate_action ?? '–'" />
                    <x-detail-grid.row :label="__('safety.field.root_cause')" :value="$event->root_cause ?? '–'" />
                    @if ($event->closed_at)
                        <x-detail-grid.row :label="__('safety.field.closed_at')" :value="$event->closed_at->format('d.m.Y H:i')" />
                        <x-detail-grid.row :label="__('safety.field.closed_by')" :value="$event->closer?->name ?? '–'" />
                    @endif
                </x-detail-grid>
            </x-card>

            {{-- Anhänge (Foto-Nachweise) --}}
            <x-card>
                <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold">
                    <x-icon name="attach_file" class="text-base-content/60" /> {{ __('safety.section.attachments') }}
                    <span class="font-normal text-base-content/50">({{ $event->attachments->count() }})</span>
                </h3>
                @if ($event->attachments->isEmpty())
                    <x-empty-state compact icon='<span class="material-symbols-outlined">attach_file</span>'
                                   :title="__('safety.no_attachments')"
                                   :message="__('safety.no_attachments')" />
                @else
                    <ul class="divide-y divide-base-300 text-sm">
                        @foreach ($event->attachments as $att)
                            <li class="flex items-center justify-between gap-2 py-2">
                                <div class="min-w-0 truncate">
                                    <a class="link link-hover" href="{{ URL::signedRoute('attachments.download', $att) }}">{{ $att->original_name }}</a>
                                    <span class="text-base-content/60">· {{ number_format($att->size / 1024, 0, ',', '.') }} KB</span>
                                </div>
                                @can('delete', $att)
                                    <form method="POST" action="{{ route('attachments.destroy', $att) }}" class="inline"
                                          data-confirm-dialog
                                          data-confirm-message="{{ __('Anhang löschen?') }}"
                                          data-confirm-icon="delete"
                                          data-confirm-tone="error"
                                          data-confirm-label="{{ __('Löschen') }}">
                                        @csrf @method('DELETE')
                                        <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                                    </form>
                                @endcan
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>

        <div class="space-y-4">
            @if ($canManage && ! $event->status->isClosed())
                <x-card>
                    <h3 class="mb-3 text-sm font-semibold">{{ __('safety.section.status') }}</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($event->status->allowedTransitions() as $target)
                            <form method="POST" action="{{ route('safety-events.transition', $event) }}" class="inline">
                                @csrf
                                <input type="hidden" name="status" value="{{ $target->value }}">
                                <x-icon-btn type="submit" size="sm" tone="outline" show-label
                                            icon="{{ $target->value === 'closed' ? 'lock' : 'arrow_forward' }}">{{ __('safety.transition.' . $target->value) }}</x-icon-btn>
                            </form>
                        @endforeach
                    </div>
                    @if ($errors->has('status'))
                        <p class="mt-2 text-sm text-error">{{ $errors->first('status') }}</p>
                    @endif
                </x-card>
            @endif

            {{-- Folgemaßnahmen (offene Punkte) --}}
            <x-card id="open-issues">
                <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold">
                    <x-icon name="checklist" class="text-base-content/60" /> {{ __('safety.section.followups') }}
                    <span class="font-normal text-base-content/50">({{ $event->openIssues->count() }})</span>
                </h3>
                @if ($event->openIssues->isEmpty())
                    <p class="text-sm text-base-content/60">{{ __('safety.no_followups') }}</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($event->openIssues as $issue)
                            <li id="open-issue-{{ $issue->id }}" class="rounded-box border border-base-300 p-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-status-badge :tone="$issue->severity->tone()" size="sm">{{ $issue->severity->label() }}</x-status-badge>
                                    <x-status-badge :tone="$issue->status->tone()" size="sm">{{ $issue->status->label() }}</x-status-badge>
                                    <span class="min-w-0 truncate text-sm font-medium">{{ $issue->title }}</span>
                                </div>
                                @if ($issue->description)
                                    <p class="mt-1 text-xs text-base-content/70">{{ \Illuminate\Support\Str::limit($issue->description, 140) }}</p>
                                @endif
                                <div class="mt-1 flex flex-wrap gap-x-3 text-xs text-base-content/60">
                                    @if ($issue->assignee)
                                        <span class="inline-flex items-center gap-1"><x-icon name="person" class="size-3.5" />{{ $issue->assignee->name }}</span>
                                    @endif
                                    @if ($issue->due_at)
                                        <span class="inline-flex items-center gap-1"><x-icon name="event" class="size-3.5" />{{ $issue->due_at->format('d.m.Y') }}</span>
                                    @endif
                                </div>

                                {{-- Begründung/Lösung: closed_reason (Abschluss/„wird nicht erledigt")
                                     bzw. der letzte Ereignis-Grund (z. B. Block-Grund). --}}
                                @php
                                    $note = $issue->closed_reason;
                                    if (! $note) {
                                        $lastReasonEvent = $issue->events->last(fn($e) => filled(data_get($e->payload, 'reason')) || filled(data_get($e->payload, 'resolution')));
                                        $note = $lastReasonEvent ? (data_get($lastReasonEvent->payload, 'reason') ?? data_get($lastReasonEvent->payload, 'resolution')) : null;
                                    }
                                @endphp
                                @if (filled($note))
                                    <p class="mt-1 rounded bg-base-200 px-2 py-1 text-xs text-base-content/80">
                                        <span class="font-medium">{{ __('open-issue.field.reason') }}:</span> {{ $note }}
                                    </p>
                                @endif

                                {{-- Status-Aktionen (Abschließen etc.) — wie im Offene-Punkte-Panel.
                                     Aktionen mit Pflichteingabe öffnen ein Dialog-Formular. --}}
                                @can('update', $issue)
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        @foreach ($issue->status->allowedTransitions() as $action)
                                            @php $needsInput = in_array($action, ['block', 'wontDo', 'reopen', 'complete'], true); @endphp
                                            @if ($needsInput)
                                                <x-icon-btn size="xs" tone="outline"
                                                            data-entry-modal-trigger
                                                            :href="route('open-issues.transition.form', ['issue' => $issue, 'action' => $action])"
                                                            show-label>{{ __('open-issue.action.' . $action) }}</x-icon-btn>
                                            @else
                                                <form method="POST" action="{{ route('open-issues.transition', ['issue' => $issue, 'action' => $action]) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-xs btn-outline">{{ __('open-issue.action.' . $action) }}</button>
                                                </form>
                                            @endif
                                        @endforeach
                                    </div>
                                @endcan
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($canManage)
                    <div class="divider my-3"></div>
                    <form method="POST" action="{{ route('safety-events.follow-up', $event) }}" class="space-y-2">
                        @csrf
                        <input type="text" name="title" maxlength="180" required
                               class="input input-bordered input-sm w-full"
                               placeholder="{{ __('safety.field.followup_title') }}">
                        <textarea name="description" rows="2" class="textarea textarea-bordered textarea-sm w-full"
                                  placeholder="{{ __('safety.field.followup_description') }}"></textarea>
                        <x-icon-btn type="submit" size="sm" tone="primary" icon="add_task" show-label>{{ __('safety.action.create_followup') }}</x-icon-btn>
                    </form>
                    <p class="mt-2 text-xs text-base-content/60">{{ __('safety.hint.followup') }}</p>
                @endif
            </x-card>
        </div>
    </div>
</x-page-shell>
@endsection
