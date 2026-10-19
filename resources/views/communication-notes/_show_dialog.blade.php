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
            @foreach ($note->tags as $tag)
                <x-tag-badge :tag="$tag" />
            @endforeach
        </div>

        @php
            $mailOrigin = $note->mailOrigin();
        @endphp

        <x-detail-grid>
            <x-detail-grid.row :label="__('communication.field.storage')">
                @if ($note->isOrganizationNote())
                    {{ __('communication.storage.internal') }}
                @elseif ($contextUrl)
                    {{ $note->notableKindLabel() }}: <a href="{{ $contextUrl }}" class="link link-hover">{{ $note->notableLabel() }}</a>
                @else
                    {{ $note->notableKindLabel() }}: {{ $note->notableLabel() }}
                @endif
            </x-detail-grid.row>
            <x-detail-grid.row :label="__('communication.field.creator')">{{ $note->creator?->name ?? '—' }}</x-detail-grid.row>
            @if ($note->participants->isNotEmpty())
                <x-detail-grid.row :label="__('communication.field.participants')">{{ $note->participants->map(fn($p) => $p->name . ($p->role ? ' (' . $p->role . ')' : ''))->implode(', ') }}</x-detail-grid.row>
            @endif
        </x-detail-grid>

        {{-- Die Mail hinter der Notiz: eingegangen oder aus WorkDiary gesendet. --}}
        @if ($mailOrigin !== null)
            <div>
                <h4 class="flex items-center gap-2 font-semibold"><x-icon name="mail" class="text-muted" /> {{ __('communication.mail.section') }}</h4>
                <x-detail-grid>
                    @if ($mailOrigin->isInbound())
                        <x-detail-grid.row :label="__('communication.mail.from')">{{ $mailOrigin->senderLabel() }}</x-detail-grid.row>
                        <x-detail-grid.row :label="__('communication.mail.received_at')">{{ $mailOrigin->at?->fdatetime() ?? '—' }}</x-detail-grid.row>
                        @if ($mailOrigin->mailbox !== null)
                            <x-detail-grid.row :label="__('communication.mail.mailbox')">{{ $mailOrigin->mailbox }}</x-detail-grid.row>
                        @endif
                    @else
                        <x-detail-grid.row :label="__('communication.mail.to')">{{ implode(', ', $mailOrigin->to) }}</x-detail-grid.row>
                        @if ($mailOrigin->cc !== [])
                            <x-detail-grid.row :label="__('communication.mail.cc')">{{ implode(', ', $mailOrigin->cc) }}</x-detail-grid.row>
                        @endif
                        <x-detail-grid.row :label="__('communication.mail.sent_at')">{{ $mailOrigin->at?->fdatetime() ?? '—' }}</x-detail-grid.row>
                        @if ($mailOrigin->replyTo !== null)
                            <x-detail-grid.row :label="__('communication.mail.reply_to')">{{ $mailOrigin->replyTo }}</x-detail-grid.row>
                        @endif
                    @endif
                    @if ($mailOrigin->inquiry !== null)
                        <x-detail-grid.row :label="__('communication.mail.inquiry')">
                            @if ($mailOrigin->inquiry->invoice !== null && \Illuminate\Support\Facades\Gate::allows('view', $mailOrigin->inquiry->invoice))
                                <a href="{{ route('invoices.show', $mailOrigin->inquiry->invoice) }}" class="link link-hover">{{ __('communication.mail.inquiry_number', ['number' => $mailOrigin->inquiry->invoice_number]) }}</a>
                            @else
                                {{ __('communication.mail.inquiry_number', ['number' => $mailOrigin->inquiry->invoice_number]) }}
                            @endif
                            <x-status-badge :tone="$mailOrigin->inquiry->status->tone()" size="xs">{{ $mailOrigin->inquiry->status->label() }}</x-status-badge>
                        </x-detail-grid.row>
                    @endif
                </x-detail-grid>
            </div>
        @endif

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

        @if ($note->attachments->isNotEmpty())
            <x-attachments-section :attachments="$note->attachments" details />
        @endif

        {{-- Herkunft übernommener Notizen (MVP-815). --}}
        <x-import-origin :subject="$note" />

        {{-- Verweise und Rückverweise (MVP-811). --}}
        <x-content-references :subject="$note" />

        <div class="flex flex-wrap justify-end gap-2">
            {{-- Umwandeln (MVP-813): Entwurf im Wissensarchiv mit Herkunftsverweis; vertrauliche Notizen nicht. --}}
            @if (! $note->confidential
                && app(\App\Services\Licensing\FeatureFlagResolver::class)->isEnabled('module.knowledge')
                && \Illuminate\Support\Facades\Gate::allows('create', \App\Models\Knowledge\KnowledgeArticle::class))
                <x-action-form :action="route('communication-notes.convert-knowledge', $note)">
                    <x-icon-btn icon="school" tone="outline" size="sm" type="submit" show-label>{{ __('communication.convert.action') }}</x-icon-btn>
                </x-action-form>
            @endif
            @if ($mailOrigin?->isInbound() && \Illuminate\Support\Facades\Gate::allows('reply', $note))
                <x-icon-btn icon="reply" tone="primary" size="sm" data-entry-modal-trigger data-entry-modal-stack
                            :href="route('communication-notes.reply', $note)" show-label>{{ __('communication.reply.action') }}</x-icon-btn>
            @endif
            <x-collection-add-button :item="$note" />
            @can('update', $note)
                <x-icon-btn icon="edit" tone="outline" size="sm" data-entry-modal-trigger
                            :href="route('communication-notes.edit', $note)" show-label>{{ __('communication.action.edit') }}</x-icon-btn>
            @endcan
        </div>
    </div>
</x-modal>
