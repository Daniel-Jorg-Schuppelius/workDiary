{{--
  Created on   : Thu Nov 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _mail_body.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Text der Herkunfts-Mail (MVP-1812): reiner Text, nie HTML der Mail. --}}
@php($mailMeta = (array) $incoming->summary)
<div class="text-sm">
    <p class="font-semibold">{{ $mailMeta['mail_subject'] ?? __('Ohne Betreff') }}</p>
    <p class="text-xs text-muted">
        @if (! empty($mailMeta['mail_from_name'])){{ $mailMeta['mail_from_name'] }} · @endif{{ $incoming->sender_email }}
        @if (! empty($mailMeta['mail_received_at'])) · {{ \Illuminate\Support\Carbon::parse((string) $mailMeta['mail_received_at'])->orgTz()->isoFormat('L LT') }}@endif
    </p>
    <div class="mt-3 whitespace-pre-wrap break-words">{{ $incoming->mail_body }}</div>
</div>
