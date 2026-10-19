{{--
  Created on   : Mon Oct 19 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _reply_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Antwort auf eine eingegangene E-Mail-Notiz, in #entry-modal geladen.
  Variablen: $note (CommunicationNote), $origin (MailOrigin), $subjectDefault (string), $replyTo (string|null), $signature (string)
--}}

<x-modal
    :title="__('communication.reply.title')"
    :eyebrow="$note->subject"
    icon="reply"
    tone="primary"
    size="lg"
    :action="route('communication-notes.reply.send', $note)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('communication.reply.action_send')">

    <x-form-group :legend="__('communication.reply.recipients_legend')" icon="mail" tone="primary" cols="2">
        <x-input-field name="recipients" :label="__('communication.reply.recipients')" required
                       :value="old('recipients', $origin->fromEmail)"
                       :hint="__('communication.reply.recipients_hint')" />
        <x-input-field name="cc" :label="__('communication.reply.cc')" :value="old('cc', implode(', ', $origin->cc))" />
        <x-input-field name="subject" :label="__('communication.field.subject')" required span="2"
                       :value="old('subject', $subjectDefault)" />
    </x-form-group>

    <x-form-group :legend="__('communication.reply.body_legend')" icon="edit_note" tone="primary">
        <x-textarea-field name="body" :label="__('communication.reply.body')" rows="8" required :value="old('body')" />
    </x-form-group>

    <x-form-group :legend="__('communication.reply.signature_legend')" icon="draw" tone="ghost" cols="1">
        <p class="whitespace-pre-wrap text-sm text-base-content/70">{{ $signature }}</p>
        <p class="text-xs text-muted">{{ __('communication.reply.signature_hint') }}</p>
    </x-form-group>

    <div class="rounded-box border border-base-300 bg-base-200 p-3 text-xs text-base-content/70">
        <p class="mb-1 font-medium">{{ __('communication.reply.quote_header', ['date' => $origin->at?->fdatetime() ?? '—', 'sender' => $origin->senderLabel()]) }}</p>
        <p class="line-clamp-6 whitespace-pre-wrap">{{ $note->excerpt(1500) }}</p>
        <p class="mt-2 text-muted">{{ __('communication.reply.quote_hint') }}</p>
        @if ($replyTo !== null)
            <p class="text-muted">{{ __('communication.reply.reply_to_hint', ['address' => $replyTo]) }}</p>
        @endif
    </div>
</x-modal>
