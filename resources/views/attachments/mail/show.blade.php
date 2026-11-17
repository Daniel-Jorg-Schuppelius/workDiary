{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Ansicht „Mail“ einer abgelegten .eml/.msg (Feature 204, MVP-1653).
     Erwartet: $attachment, $format, $state ('pending'|'ready'|'failed'), $message (MailFileMessage|null),
     $failure (MailFileFailure|null), $carrier, $canAdopt, $preview (['path', 'part']|null),
     $htmlFrame (['path', 'message']|null), $downloadUrl.
     Gelesen wird die Mail im Hintergrund; bis dahin „wird gelesen“. Fremd-HTML erscheint nur auf
     Wunsch im iframe mit leerem sandbox (keine Skripte, keine Formulare, kein gleicher Ursprung)
     und eigener CSP der Antwort. --}}
@extends('layouts.app')

@php
    $mailTitle = $message !== null
        ? __('mail_file.title_with_subject', ['subject' => $message->subject !== '' ? $message->subject : __('mail_file.no_subject')])
        : __('mail_file.title');
    $carrierUrl = $carrier instanceof \Illuminate\Database\Eloquent\Model ? \App\Support\EntityUrl::for($carrier) : null;
    $carrierLabel = $carrier instanceof \Illuminate\Database\Eloquent\Model ? \App\Support\EntityType::label($carrier::class) : null;
@endphp

@section('title', $mailTitle)
@section('nav-title', __('mail_file.title'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :title="$mailTitle"
                        :subtitle="__('mail_file.format.' . $format->value) . ' · ' . $attachment->original_name"
                        :back="$carrierUrl" :back-label="$carrierLabel">
            <x-slot:actions>
                <x-icon-btn icon="download" size="sm" :href="$downloadUrl" show-label>{{ __('mail_file.download_original') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($state === 'pending')
        <x-card :title="__('mail_file.title')" icon="mail">
            <div class="alert alert-info text-sm" role="status">
                <x-icon name="hourglass_top" />
                <span>{{ __('mail_file.unreadable.pending') }}</span>
            </div>
        </x-card>
    @elseif ($message === null)
        <x-card :title="__('mail_file.unreadable.title')" icon="mail">
            <div class="alert alert-warning text-sm" role="alert">
                <x-icon name="warning" />
                <span>{{ $failure?->message() ?? __('mail_file.failure.malformed') }}</span>
            </div>
            <p class="mt-3 text-sm text-muted">{{ __('mail_file.unreadable.hint') }}</p>
        </x-card>
    @else
        @if ($preview !== null)
            @php
                /** @var \App\Services\Attachments\Mail\Dto\MailFilePart $previewPart */
                $previewPart = $preview['part'];
                $previewUrl = \App\Http\Controllers\Attachments\MailAttachmentController::partUrl($attachment, $preview['path'], true);
            @endphp
            <x-card id="vorschau" :title="__('mail_file.attachments.preview_heading', ['name' => $previewPart->name])" icon="preview" padding="p-0">
                <x-slot:actions>
                    <x-icon-btn icon="close" size="sm" :href="route('attachments.mail.show', $attachment)" :label="__('mail_file.attachments.preview_close')" />
                </x-slot:actions>
                {{-- Wie die Belegvorschau: Bilder direkt, PDF im iframe (die Seiten-CSP sperrt object/embed). --}}
                @if (str_starts_with($previewPart->mime, 'image/'))
                    <div class="max-h-[75vh] overflow-auto bg-base-200">
                        <img src="{{ $previewUrl }}" alt="{{ $previewPart->name }}" class="mx-auto max-w-full">
                    </div>
                @else
                    <iframe src="{{ $previewUrl }}#view=FitH&navpanes=0" class="block h-[75vh] w-full bg-white"
                            title="{{ $previewPart->name }}" referrerpolicy="no-referrer"></iframe>
                @endif
            </x-card>
        @endif

        @include('attachments.mail._message', ['mail' => $message, 'path' => '', 'depth' => 0])

        @if ($htmlFrame !== null)
            <x-card id="original" :title="__('mail_file.html.heading')" icon="html" padding="p-0">
                <x-slot:actions>
                    <x-icon-btn icon="visibility_off" size="sm" :href="route('attachments.mail.show', $attachment)" show-label>{{ __('mail_file.html.hide') }}</x-icon-btn>
                </x-slot:actions>
                <p class="px-4 pt-3 text-xs text-muted">{{ __('mail_file.html.hint') }}</p>
                {{-- sandbox="" ohne allow-*: keine Skripte, keine Formulare, keine Pop-ups, fremder Ursprung. --}}
                <iframe sandbox="" src="{{ \App\Http\Controllers\Attachments\MailAttachmentController::htmlUrl($attachment, $htmlFrame['path']) }}"
                        class="mt-3 block h-[70vh] w-full border-t border-base-300 bg-white"
                        title="{{ __('mail_file.html.frame_title') }}" referrerpolicy="no-referrer"></iframe>
            </x-card>
        @endif
    @endif
</x-page-shell>
@endsection
