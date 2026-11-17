{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _message.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Kopf, Text und Anhänge einer Mail (Feature 204); eingebettete Mails rekursiv
     (der Leser begrenzt die Tiefe). Erwartet: $attachment, $mail (MailFileMessage),
     $path (Pfad der eingebetteten Mail, '' = die Mail selbst), $depth, $canAdopt. --}}
@php
    /** @var \App\Services\Attachments\Mail\Dto\MailFileMessage $mail */
    $sentAt = $mail->sentAt();
    $mailCtl = \App\Http\Controllers\Attachments\MailAttachmentController::class;
@endphp

<x-card :title="$depth === 0 ? __('mail_file.title') : null" icon="mail">
    <x-detail-grid>
        <x-detail-grid.row :label="__('mail_file.field.subject')" :value="$mail->subject !== '' ? $mail->subject : __('mail_file.no_subject')" />
        <x-detail-grid.row :label="__('mail_file.field.from')" :value="\App\Services\Attachments\Mail\Dto\MailFileMessage::addressLine($mail->from)" />
        <x-detail-grid.row :label="__('mail_file.field.to')" :value="\App\Services\Attachments\Mail\Dto\MailFileMessage::addressList($mail->to)" />
        <x-detail-grid.row :label="__('mail_file.field.cc')" :value="\App\Services\Attachments\Mail\Dto\MailFileMessage::addressList($mail->cc)" />
        <x-detail-grid.row :label="__('mail_file.field.bcc')" :value="\App\Services\Attachments\Mail\Dto\MailFileMessage::addressList($mail->bcc)" />
        <x-detail-grid.row :label="__('mail_file.field.date')" :value="$sentAt !== null ? \App\Support\CarbonFmt::fdatetime($sentAt) : null" />
        <x-detail-grid.row :label="__('mail_file.field.message_id')" :value="$mail->messageId" class="break-all" />
    </x-detail-grid>

    <h3 class="mt-4 mb-1 text-sm font-semibold">{{ __('mail_file.text.heading') }}</h3>
    @if ($mail->textFromHtml)
        <p class="mb-1 text-xs text-muted">{{ __('mail_file.text.from_html') }}</p>
    @endif
    @if ($mail->text !== '')
        <div class="max-h-[60vh] overflow-auto whitespace-pre-wrap break-words rounded-box border border-base-300 bg-base-200/40 p-3 text-sm">{{ $mail->text }}</div>
        @if ($mail->textTruncated)
            <p class="mt-1 text-xs text-muted">{{ __('mail_file.text.truncated') }}</p>
        @endif
    @elseif ($mail->hasRtf)
        <div class="alert alert-info text-sm" role="status"><x-icon name="info" /><span>{{ __('mail_file.text.rtf_only') }}</span></div>
    @else
        <x-empty-state compact icon="notes" :title="__('mail_file.text.empty')" />
    @endif

    @if ($mail->frameAvailable)
        <div class="mt-2">
            <x-icon-btn icon="html" size="sm" show-label
                        :href="route('attachments.mail.show', [$attachment, 'html' => $path === '' ? 'mail' : $path]) . '#original'">{{ __('mail_file.html.show') }}</x-icon-btn>
        </div>
    @elseif ($mail->hasHtml)
        <p class="mt-2 text-xs text-muted">{{ __('mail_file.html.frame_unavailable') }}</p>
    @endif

    <h3 class="mt-4 mb-1 text-sm font-semibold">{{ __('mail_file.attachments.heading') }}</h3>
    @if ($mail->attachments === [])
        <x-empty-state compact icon="attach_file" :title="__('mail_file.attachments.none')" />
    @else
        <ul class="divide-y divide-base-300 text-sm">
            @foreach ($mail->attachments as $index => $part)
                @php($partPath = $path === '' ? (string) $index : $path . '.' . $index)
                @if ($part->message !== null)
                    <li class="py-2">
                        <details>
                            <summary class="cursor-pointer">
                                <x-icon name="forward_to_inbox" class="text-muted" />
                                <span class="font-medium">{{ __('mail_file.attachments.embedded') }}:</span>
                                <span class="break-all">{{ $part->name }}</span>
                            </summary>
                            <div class="mt-2">
                                @include('attachments.mail._message', ['mail' => $part->message, 'path' => $partPath, 'depth' => $depth + 1])
                            </div>
                        </details>
                    </li>
                @else
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <div class="flex min-w-0 items-center gap-3">
                            <x-icon :name="str_starts_with($part->mime, 'image/') ? 'image' : 'attach_file'" class="text-xl text-muted" />
                            <div class="min-w-0">
                                <p class="break-all">{{ $part->name }}</p>
                                <p class="text-xs text-muted">
                                    {{ $part->mime }} · {{ \CommonToolkit\Helper\Data\NumberHelper::formatBytes($part->size, 1) }}@if ($part->inline) · {{ __('mail_file.attachments.inline') }}@endif
                                </p>
                                @unless ($part->safe)
                                    <p class="flex items-center gap-1 text-xs text-warning"><x-icon name="warning" class="text-sm" />{{ __('mail_file.attachments.unchecked') }}</p>
                                @endunless
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-1">
                            @if ($part->isPreviewable())
                                <x-icon-btn icon="preview" size="sm" show-label
                                            :href="route('attachments.mail.show', [$attachment, 'vorschau' => $partPath]) . '#vorschau'">{{ __('mail_file.attachments.open') }}</x-icon-btn>
                            @endif
                            <x-icon-btn icon="download" size="sm" show-label :href="$mailCtl::partUrl($attachment, $partPath)">{{ __('mail_file.attachments.download') }}</x-icon-btn>
                            @if ($canAdopt && $part->safe)
                                <x-action-form :action="route('attachments.mail.adopt', [$attachment, $partPath])">
                                    <x-icon-btn icon="note_add" size="sm" type="submit" show-label :label="__('mail_file.attachments.adopt_hint')">{{ __('mail_file.attachments.adopt') }}</x-icon-btn>
                                </x-action-form>
                            @endif
                        </div>
                    </li>
                @endif
            @endforeach
        </ul>
    @endif
</x-card>
